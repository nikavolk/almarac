<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT id, name, size, mime_type, created_at
        FROM files
        ORDER BY created_at DESC
    ");

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'files' => $files
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch files'
    ]);
} 