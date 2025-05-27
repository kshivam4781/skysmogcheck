<?php
session_start();
require_once '../config/db_connection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has consultant access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get action and appointment ID
$action = $_POST['action'] ?? '';
$appointment_id = $_POST['appointment_id'] ?? null;

if (!$appointment_id || !in_array($action, ['accept', 'delete'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $conn->begin_transaction();

    if ($action === 'accept') {
        // Update appointment status to confirmed
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'confirmed',
                approved_by = ?
            WHERE id = ? AND status = 'pending'
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare appointment update: " . $conn->error);
        }
        
        $stmt->bind_param("si", $_SESSION['user_id'], $appointment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update appointment: " . $stmt->error);
        }

        // Update calendar event status
        $stmt = $conn->prepare("
            UPDATE calendar_events 
            SET status = 'confirmed'
            WHERE appointment_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare calendar event update: " . $conn->error);
        }
        
        $stmt->bind_param("i", $appointment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update calendar event: " . $stmt->error);
        }

    } elseif ($action === 'delete') {
        // Delete related records first
        $stmt = $conn->prepare("DELETE FROM calendar_events WHERE appointment_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare calendar events deletion: " . $conn->error);
        }
        $stmt->bind_param("i", $appointment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete calendar events: " . $stmt->error);
        }

        // Delete vehicles
        $stmt = $conn->prepare("DELETE FROM vehicles WHERE appointment_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare vehicles deletion: " . $conn->error);
        }
        $stmt->bind_param("i", $appointment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete vehicles: " . $stmt->error);
        }

        // Finally delete the appointment
        $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND status = 'pending'");
        if (!$stmt) {
            throw new Exception("Failed to prepare appointment deletion: " . $conn->error);
        }
        $stmt->bind_param("i", $appointment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete appointment: " . $stmt->error);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Operation completed successfully']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error processing appointment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 