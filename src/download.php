<?php
require_once __DIR__ . '/config.php';

try {
    if (!isset($_GET['file'])) {
        throw new Exception('No file specified');
    }

    $fileName = $_GET['file'];

    // Get file info from database
    $stmt = $pdo->prepare("
        SELECT s3_key, mime_type
        FROM files
        WHERE name = ?
    ");
    
    $stmt->execute([$fileName]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        throw new Exception('File not found');
    }

    // Generate pre-signed URL
    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => S3_BUCKET,
        'Key'    => $file['s3_key']
    ]);

    $request = $s3->createPresignedRequest($cmd, '+20 minutes');
    $presignedUrl = (string)$request->getUri();

    // Redirect to pre-signed URL
    header('Location: ' . $presignedUrl);
    exit;

} catch (Exception $e) {
    http_response_code(404);
    echo 'Error: ' . $e->getMessage();
} 