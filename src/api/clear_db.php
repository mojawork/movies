<?php
// src/api/clear_db.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
/** @var PDO $pdo */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit;
}

try {
    // We use DELETE FROM because TRUNCATE might have issues with foreign keys (not present here, but good practice)
    // or permissions in some environments.
    $stmt = $pdo->prepare('DELETE FROM movies');
    $stmt->execute();
    
    // Reset AUTO_INCREMENT
    $pdo->prepare('ALTER TABLE movies AUTO_INCREMENT = 1')->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Database cleared successfully.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
