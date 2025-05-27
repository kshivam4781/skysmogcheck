<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied';
    exit();
}

$filename = $_GET['filename'] ?? '';

if (empty($filename)) {
    header('HTTP/1.0 400 Bad Request');
    echo 'No filename provided';
    exit();
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$filepath = __DIR__ . '/../../temp/' . $filename;

// Check if file exists and is readable
if (!file_exists($filepath) || !is_readable($filepath)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit();
}

// Set headers for file download
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the file
readfile($filepath);
exit();
?>