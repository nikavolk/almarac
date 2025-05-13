<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

// Get JSON input from the request body
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['file_id'])) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Missing file_id in request body.';
    echo json_encode($response);
    exit;
}

$fileId = filter_var($input['file_id'], FILTER_VALIDATE_INT);

if ($fileId === false) {
    http_response_code(400);
    $response['message'] = 'Invalid file_id format.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Fetch file details from the database
    $stmt = $pdo->prepare("SELECT id, s3_key, original_filename FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404); // Not Found
        $response['message'] = 'File record not found in database.';
        write_log("Delete attempt: File ID {$fileId} not found in DB.");
        echo json_encode($response);
        exit;
    }

    $s3Key = $file['s3_key'];
    $originalFilename = $file['original_filename'];

    // 2. Delete the object from S3
    try {
        write_log("Attempting to delete S3 object: {$s3Key} for file ID: {$fileId} ({$originalFilename})");
        $s3->deleteObject([
            'Bucket' => S3_BUCKET,
            'Key' => $s3Key,
        ]);
        write_log("Successfully deleted S3 object: {$s3Key} (or it did not exist).");

        // 3. Delete the record from the database
        try {
            $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            if ($stmt->execute([$fileId])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = "File '{$originalFilename}' (ID: {$fileId}) deleted successfully from S3 and database.";
                    write_log("Successfully deleted DB record for file ID: {$fileId} ({$originalFilename}), S3 key: {$s3Key}");
                } else {
                    // Should not happen if we fetched the file first, but good to handle
                    http_response_code(404);
                    $response['message'] = "File record for ID {$fileId} was not found for deletion after S3 operation (race condition?)";
                    write_log("DB delete warning: Record for file ID {$fileId} not found during delete, though S3 object {$s3Key} was targeted.");
                }
            } else {
                http_response_code(500);
                $response['message'] = "Failed to delete file record from database after S3 deletion.";
                write_log("CRITICAL: Failed to delete DB record for file ID: {$fileId} ({$originalFilename}) after S3 object {$s3Key} was deleted. Error: " . implode("; ", $stmt->errorInfo()));
                // This leaves an inconsistency: S3 object deleted, DB record remains.
            }
        } catch (PDOException $e) {
            http_response_code(500);
            $response['message'] = "Database error while deleting file record: " . $e->getMessage();
            write_log("CRITICAL: PDOException during DB delete for file ID: {$fileId} ({$originalFilename}), S3 key: {$s3Key}. S3 object already deleted. DB Error: " . $e->getMessage());
        }

    } catch (Aws\S3\Exception\S3Exception $e) {
        // Log the S3 error, but decide if we should proceed to DB delete.
        // If S3 object is already gone (e.g. NoSuchKey), we might want to proceed.
        // For other S3 errors, it might be safer to stop and not delete DB record to avoid inconsistency.
        write_log("S3 delete error for key {$s3Key}, file ID {$fileId} ({$originalFilename}): " . $e->getMessage());
        if ($e->getAwsErrorCode() === 'NoSuchKey') {
            write_log("S3 object {$s3Key} not found (NoSuchKey), proceeding to attempt DB record deletion for file ID {$fileId}.");
            // Fall through to attempt DB deletion if S3 object was already gone.
            // Re-wrap the DB deletion logic here or refactor to avoid duplication if complex.
            try {
                $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
                if ($stmt->execute([$fileId])) {
                    if ($stmt->rowCount() > 0) {
                        $response['success'] = true;
                        $response['message'] = "File '{$originalFilename}' (ID: {$fileId}) deleted (S3 object was already missing, DB record removed).";
                        write_log("DB record for file ID: {$fileId} ({$originalFilename}) deleted. S3 object {$s3Key} was already missing.");
                    } else {
                        http_response_code(404);
                        $response['message'] = "File record for ID {$fileId} not found for deletion (S3 object was also missing).";
                        write_log("DB delete warning: Record for file ID {$fileId} not found. S3 object {$s3Key} was also missing.");
                    }
                } else {
                    http_response_code(500);
                    $response['message'] = "Failed to delete file record from database (S3 object was missing).";
                    write_log("CRITICAL: Failed to delete DB record for file ID: {$fileId} ({$originalFilename}) after S3 object {$s3Key} was confirmed missing. Error: " . implode("; ", $stmt->errorInfo()));
                }
            } catch (PDOException $pdoExc) {
                http_response_code(500);
                $response['message'] = "Database error while deleting file record (S3 object was missing): " . $pdoExc->getMessage();
                write_log("CRITICAL: PDOException during DB delete for file ID: {$fileId} ({$originalFilename}), S3 key: {$s3Key} was missing. DB Error: " . $pdoExc->getMessage());
            }
        } else {
            http_response_code(500);
            $response['message'] = "Error deleting file from S3: " . $e->getAwsErrorMessage();
            if ($e->getAwsErrorCode())
                $response['aws_error_code'] = $e->getAwsErrorCode();
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = "Database error: " . $e->getMessage();
    write_log("PDOException before S3 operation for file ID {$fileId}: " . $e->getMessage());

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "An unexpected error occurred: " . $e->getMessage();
    write_log("General error during delete for file ID {$fileId}: " . $e->getMessage());
}

echo json_encode($response);
exit;