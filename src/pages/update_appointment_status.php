<?php
session_start();
require_once '../config/db_connection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

$appointment_id = $data['appointment_id'] ?? null;
$status = $data['status'] ?? null;

if (!$appointment_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update appointment status
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $appointment_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    // Update calendar event status
    $stmt = $conn->prepare("UPDATE calendar_events SET status = ? WHERE appointment_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $appointment_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Error in update_appointment_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 