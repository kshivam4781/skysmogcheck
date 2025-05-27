<?php
session_start();
require_once '../config/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    // Insert new event
    $stmt = $conn->prepare("INSERT INTO calendar_events (user_id, title, start_time, end_time, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss",
        $_SESSION['user_id'],
        $data['title'],
        $data['start'],
        $data['end'],
        $data['description']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Event saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save event']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 