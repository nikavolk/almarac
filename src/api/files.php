<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Default response
$response = ['success' => false, 'message' => 'An unknown error occurred.', 'files' => []];

try {
    // Fetch files from the database, ordered by upload date descending
    $stmt = $pdo->query('SELECT id, original_filename, s3_key, file_size, file_type, uploaded_at FROM files ORDER BY uploaded_at DESC');
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['files'] = $files;
    // Remove the default error message on success
    unset($response['message']);

} catch (PDOException $e) {
    $logMessage = "Database error fetching files: " . $e->getMessage();
    write_log($logMessage); // Log to CloudWatch
    error_log($logMessage); // Log to PHP error log as well
    http_response_code(500); // Internal Server Error
    $response['message'] = "Database error occurred while fetching files.";

} catch (Exception $e) {
    $logMessage = "General error fetching files: " . $e->getMessage();
    write_log($logMessage);
    error_log($logMessage);
    http_response_code(500);
    $response['message'] = "An internal error occurred.";
}

echo json_encode($response);
exit;