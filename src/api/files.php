<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'files' => []];

try {
    // fetch files from db, ordered by upload date descending
    $stmt = $pdo->query('SELECT id, original_filename, s3_key, file_size, file_type, uploaded_at FROM files ORDER BY uploaded_at DESC');
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['files'] = $files;

    unset($response['message']);

} catch (PDOException $e) {
    $logMessage = "Database error fetching files: " . $e->getMessage();
    write_log($logMessage); // log to CloudWatch
    error_log($logMessage); // log to php error log

    http_response_code(500);

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