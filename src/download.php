<?php

require_once __DIR__ . '/config.php';
function showErrorPage($message, $logContext = '')
{
    if (!empty($logContext)) {
        write_log("Download Error ({$logContext}): " . $message);
    } else {
        write_log("Download Error: " . $message);
    }

    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>Download Error</title><style>body{font-family:sans-serif;padding:20px;text-align:center;}h1{color:red;}</style></head><body>";
    echo "<h1>Download Error</h1><p>" . htmlspecialchars($message) . "</p>";
    echo "<p><a href='/index.php'>Back to File List</a></p></body></html>";
    exit;
}

if (empty($_GET['id'])) {
    showErrorPage('No file ID provided.', 'Missing ID');
}

$fileId = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if ($fileId === false) {
    showErrorPage('Invalid file ID format.', 'Invalid ID format');
}

try {
    // fetch file details from db
    $stmt = $pdo->prepare("SELECT id, s3_key, original_filename FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        showErrorPage('File not found in our records.', "File ID {$fileId} not in DB");
    }

    $s3Key = $file['s3_key'];
    $originalFilename = $file['original_filename'];

    // generate pre-signed url for s3 object
    try {
        write_log("Attempting to generate pre-signed URL for S3 key: {$s3Key}, file ID: {$fileId} ({$originalFilename})");

        $command = $s3->getCommand('GetObject', [
            'Bucket' => S3_BUCKET,
            'Key' => $s3Key,
            // add response-content-disposition to suggest a filename to the browser
            'ResponseContentDisposition' => 'attachment; filename="' . addslashes($originalFilename) . '"',
        ]);

        // create a presigned URL for 15 min
        $presignedUrl = $s3->createPresignedRequest($command, '+15 minutes')->getUri();

        write_log("Successfully generated pre-signed URL for S3 key: {$s3Key}. Redirecting user.");

        // redirect user to presigned url
        header("Location: " . (string) $presignedUrl);
        exit;

    } catch (Aws\S3\Exception\S3Exception $e) {
        showErrorPage("Could not generate download link due to an S3 error: " . $e->getAwsErrorMessage(), "S3 presign error for {$s3Key}");
    } catch (Exception $e) {
        showErrorPage("An unexpected error occurred while preparing the download link: " . $e->getMessage(), "General presign error for {$s3Key}");
    }

} catch (PDOException $e) {
    showErrorPage("Database error while trying to retrieve file information.", "PDOException for file ID {$fileId}");
} catch (Exception $e) {
    showErrorPage("An unexpected error occurred: " . $e->getMessage(), "General error for file ID {$fileId}");
}