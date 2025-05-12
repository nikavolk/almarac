<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('No file ID specified');
    }

    $fileId = $_GET['id'];

    // Get file info from database
    $stmt = $pdo->prepare("
        SELECT s3_key
        FROM files
        WHERE id = ?
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        throw new Exception('File not found');
    }

    // Delete from S3
    $s3->deleteObject([
        'Bucket' => S3_BUCKET,
        'Key'    => $file['s3_key']
    ]);

    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
    $stmt->execute([$fileId]);

    echo json_encode([
        'success' => true,
        'message' => 'File deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 