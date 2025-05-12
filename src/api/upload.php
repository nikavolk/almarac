<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['file'];
    
    // Validate file size (5MB limit)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size exceeds 5MB limit');
    }

    // Generate a unique S3 key
    $s3Key = uniqid() . '_' . $file['name'];

    // Upload to S3
    $result = $s3->putObject([
        'Bucket' => S3_BUCKET,
        'Key'    => $s3Key,
        'Body'   => fopen($file['tmp_name'], 'rb'),
        'ACL'    => 'private',
    ]);

    // Store file metadata in database
    $stmt = $pdo->prepare("
        INSERT INTO files (name, s3_key, size, mime_type)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $file['name'],
        $s3Key,
        $file['size'],
        $file['type']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 