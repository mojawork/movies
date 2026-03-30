<?php
// src/api/delete_movie.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
/** @var PDO $pdo */

// Support DELETE method or POST method for simplicity
$method = $_SERVER['REQUEST_METHOD'];
$id = null;

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : (isset($_POST['id']) ? intval($_POST['id']) : null);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use DELETE or POST.']);
    exit;
}

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Movie ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM movies WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Movie deleted successfully.',
            'id' => $id
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Movie not found.',
            'id' => $id
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
