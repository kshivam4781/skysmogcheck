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

// Get appointment ID from POST data
$appointment_id = $_POST['appointment_id'] ?? null;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get the consultant's account ID
    $stmt = $conn->prepare("
        SELECT idaccounts 
        FROM accounts 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();

    if (!$account) {
        throw new Exception("Consultant account not found");
    }

    $consultant_id = $account['idaccounts'];

    // Update appointments table
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'confirmed',
            approved_by = ?
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("si", $_SESSION['user_id'], $appointment_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update appointment: " . $stmt->error);
    }

    // Update calendar_events table
    $stmt = $conn->prepare("
        UPDATE calendar_events 
        SET status = 'confirmed',
            user_id = ?
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("si", $_SESSION['user_id'], $appointment_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update calendar event: " . $stmt->error);
    }

    // Update clean_truck_checks table
    $stmt = $conn->prepare("
        UPDATE clean_truck_checks 
        SET user_id = ?,
            clean_truck_status = 'pending'
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("ii", $consultant_id, $appointment_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update clean truck check: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Appointment accepted successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 