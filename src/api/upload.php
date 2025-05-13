<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

if (empty($_FILES['uploadedFile'])) {
    http_response_code(400);
    $response['message'] = 'No file uploaded or incorrect field name. Expected "uploadedFile".';
    echo json_encode($response);
    exit;
}

$file = $_FILES['uploadedFile'];

// --- file validation ---
$maxFileSize = 5 * 1024 * 1024; // 5 MB max upload
if ($file['size'] > $maxFileSize) {
    http_response_code(413); // file too large
    $response['message'] = 'File is too large. Maximum size is 5MB.';
    echo json_encode($response);
    exit;
}

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'text/plain',
    'application/zip'
];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt', 'zip'];

$fileMimeType = mime_content_type($file['tmp_name']);
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileMimeType, $allowedMimeTypes) || !in_array($fileExtension, $allowedExtensions)) {
    http_response_code(415); // unsupported Media Type
    $response['message'] = 'Invalid file type. Allowed types: JPG, PNG, PDF, DOC, DOCX, TXT, ZIP.';
    $response['debug_details'] = ['mime' => $fileMimeType, 'ext' => $fileExtension]; // for debugging
    write_log("Invalid file type attempt: MIME: {$fileMimeType}, EXT: {$fileExtension}, Filename: {$file['name']}");
    echo json_encode($response);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    $response['message'] = 'File upload error: ' . upload_error_to_string($file['error']);
    write_log("File upload error code {$file['error']} for {$file['name']}: " . upload_error_to_string($file['error']));
    echo json_encode($response);
    exit;
}

// --- S3 upload ---
$originalFilename = basename($file['name']);
$s3Key = 'uploads/' . uniqid() . '-' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalFilename); // sanitize and make unique

try {
    write_log("Attempting to upload {$originalFilename} to S3 with key {$s3Key}");

    $s3->putObject([
        'Bucket' => S3_BUCKET,
        'Key' => $s3Key,
        'Body' => fopen($file['tmp_name'], 'rb'), // stream the file
        'ACL' => 'private'
    ]);

    write_log("Successfully uploaded {$s3Key} to S3 bucket " . S3_BUCKET);

    // --- insert data into db ---
    $sql = "INSERT INTO files (original_filename, s3_key, file_size, file_type, uploaded_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$originalFilename, $s3Key, $file['size'], $fileMimeType])) {
        $fileId = $pdo->lastInsertId();
        $response['success'] = true;
        $response['message'] = 'File uploaded and record created successfully.';
        $response['file_id'] = $fileId;
        $response['s3_key'] = $s3Key;
        $response['filename'] = $originalFilename;
        http_response_code(201); // success
        write_log("Successfully inserted DB record for {$s3Key}, ID: {$fileId}");

        // --- aws rekognition detect labels and tag S3 object ---
        if (isset($GLOBALS['rekognition']) && $GLOBALS['rekognition']) {
            /** @var \Aws\Rekognition\RekognitionClient $rekognitionClient */
            $rekognitionClient = $GLOBALS['rekognition'];
            $rekognitionS3Object = [
                'Bucket' => S3_BUCKET,
                'Name' => $s3Key
            ];

            // only attempt rekognition for image types typically supported
            $rekognitionEligibleMimeTypes = ['image/jpeg', 'image/png'];
            if (in_array($fileMimeType, $rekognitionEligibleMimeTypes)) {
                try {
                    write_log("Attempting Rekognition for {$s3Key}");
                    $rekognitionResult = $rekognitionClient->detectLabels([
                        'Image' => ['S3Object' => $rekognitionS3Object],
                        'MaxLabels' => 10,
                        'MinConfidence' => 75,
                    ]);

                    $labels = $rekognitionResult->get('Labels');
                    if (!empty($labels)) {
                        $s3Tags = [];
                        foreach ($labels as $index => $label) {
                            $tagName = preg_replace('/[^a-zA-Z0-9_.:/=+\-@]/ ', '_', $label['Name']);
                            $tagName = substr('rekognition-' . $tagName, 0, 128);

                            $s3Tags[] = [
                                'Key' => $tagName,
                                'Value' => substr((string) round($label['Confidence'], 2), 0, 256)
                            ];
                        }

                        if (!empty($s3Tags)) {
                            $s3->putObjectTagging([
                                'Bucket' => S3_BUCKET,
                                'Key' => $s3Key,
                                'Tagging' => ['TagSet' => $s3Tags],
                            ]);
                            write_log("Successfully applied Rekognition tags to {$s3Key}", 'rekognition-logs', ['tags_count' => count($s3Tags)]);
                        }
                    } else {
                        write_log("Rekognition found no labels for {$s3Key} above MinConfidence.", 'rekognition-logs');
                    }
                } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
                    write_log("AWS Rekognition Error for {$s3Key}: " . $e->getMessage(), 'rekognition-errors', ['aws_error_code' => $e->getAwsErrorCode()]);
                } catch (\Aws\S3\Exception\S3Exception $e) {
                    write_log("S3 Error putting Rekognition tags for {$s3Key}: " . $e->getMessage(), 'rekognition-errors', ['aws_error_code' => $e->getAwsErrorCode()]);
                } catch (Exception $e) {
                    write_log("General Error during Rekognition processing for {$s3Key}: " . $e->getMessage(), 'rekognition-errors');
                }
            } else {
                write_log("Skipping Rekognition for {$s3Key} due to unsupported MIME type: {$fileMimeType}", 'rekognition-logs');
            }
        } else {
            write_log("Rekognition client not available. Skipping label detection.", 'rekognition-logs');
        }
        // --- end aws rekognition ---

    } else {
        // if DB insert fails, delete S3 object to prevent orphans
        write_log("Database insert failed for {$s3Key} after S3 upload. Error: " . implode("; ", $stmt->errorInfo()));
        try {
            $s3->deleteObject([
                'Bucket' => S3_BUCKET,
                'Key' => $s3Key
            ]);
            write_log("Orphaned S3 object {$s3Key} deleted after DB insert failure.");
        } catch (Aws\S3\Exception\S3Exception $e) {
            write_log("CRITICAL: Failed to delete orphaned S3 object {$s3Key} after DB insert failure. S3 Error: " . $e->getMessage());
        }
        http_response_code(500);
        $response['message'] = 'File uploaded to S3, but failed to save record to database.';
    }

} catch (Aws\S3\Exception\S3Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error uploading file to S3: ' . $e->getAwsErrorMessage();
    write_log("S3 Upload Error for {$originalFilename}: " . $e->getMessage());
    // add more detailed error info if available
    if ($e->getAwsErrorCode())
        $response['aws_error_code'] = $e->getAwsErrorCode();
    if ($e->getAwsErrorType())
        $response['aws_error_type'] = $e->getAwsErrorType();

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error after S3 upload: ' . $e->getMessage();
    write_log("Database Error post-S3 upload for {$originalFilename} (S3 key {$s3Key}): " . $e->getMessage());

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
    write_log("General Error during upload for {$originalFilename}: " . $e->getMessage());
}

echo json_encode($response);
exit;

// helper function to convert upload error codes to strings for easier debugging
function upload_error_to_string($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}