<?php
session_start();
require_once '../config/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$filename = $_GET['filename'] ?? '';

if (empty($filename)) {
    echo json_encode(['error' => 'No filename provided']);
    exit();
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$filepath = __DIR__ . '/../../temp/' . $filename;

// Check if file exists and is readable
$exists = file_exists($filepath) && is_readable($filepath);

echo json_encode(['exists' => $exists]);
?> 