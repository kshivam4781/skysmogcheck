<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Set proper JSON header for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
}

// Get parameters from URL or POST data
$token = $_REQUEST['token'] ?? '';
$appointment_id = $_REQUEST['id'] ?? '';
$action = $_REQUEST['action'] ?? '';

if (!$token || !$appointment_id || !$action) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    } else {
        die("Invalid request parameters");
    }
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Verify token and get appointment details
    $stmt = $conn->prepare("
        SELECT at.*, a.status as appointment_status, ce.status as calendar_status
        FROM appointment_tokens at
        JOIN appointments a ON at.appointment_id = a.id
        JOIN calendar_events ce ON a.id = ce.appointment_id
        WHERE at.token = ? 
        AND at.appointment_id = ?
        AND at.used = 0
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("si", $token, $appointment_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $token_data = $result->fetch_assoc();

    if (!$token_data) {
        throw new Exception("Invalid or expired token");
    }

    if ($action === 'accept') {
        // Update appointments table
        $update_appointment = $conn->prepare("
            UPDATE appointments 
            SET status = 'confirmed'
            WHERE id = ?
        ");

        if (!$update_appointment) {
            throw new Exception("Prepare failed for appointment update: " . $conn->error);
        }

        $update_appointment->bind_param("i", $appointment_id);
        
        if (!$update_appointment->execute()) {
            throw new Exception("Execute failed for appointment update: " . $update_appointment->error);
        }

        // Update calendar_events table
        $update_calendar = $conn->prepare("
            UPDATE calendar_events 
            SET status = 'confirmed'
            WHERE appointment_id = ?
        ");

        if (!$update_calendar) {
            throw new Exception("Prepare failed for calendar update: " . $conn->error);
        }

        $update_calendar->bind_param("i", $appointment_id);
        
        if (!$update_calendar->execute()) {
            throw new Exception("Execute failed for calendar update: " . $update_calendar->error);
        }

        // Mark token as used
        $update_token = $conn->prepare("
            UPDATE appointment_tokens 
            SET used = 1, 
                used_at = CURRENT_TIMESTAMP
            WHERE token = ? 
            AND appointment_id = ?
        ");

        if (!$update_token) {
            throw new Exception("Prepare failed for token update: " . $conn->error);
        }

        $update_token->bind_param("si", $token, $appointment_id);
        
        if (!$update_token->execute()) {
            throw new Exception("Execute failed for token update: " . $update_token->error);
        }

        // Commit transaction
        $conn->commit();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo json_encode(['success' => true, 'message' => 'Appointment confirmed successfully']);
        } else {
            header("Location: view_appointment_details.php?token=" . $token . "&id=" . $appointment_id);
        }
        exit();
    } else if ($action === 'deny') {
        // Mark token as used
        $update_token = $conn->prepare("
            UPDATE appointment_tokens 
            SET used = 1, 
                used_at = CURRENT_TIMESTAMP
            WHERE token = ? 
            AND appointment_id = ?
        ");

        if (!$update_token) {
            throw new Exception("Prepare failed for token update: " . $conn->error);
        }

        $update_token->bind_param("si", $token, $appointment_id);
        
        if (!$update_token->execute()) {
            throw new Exception("Execute failed for token update: " . $update_token->error);
        }

        // Commit transaction
        $conn->commit();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo json_encode(['success' => true, 'message' => 'Appointment denied']);
        } else {
            header("Location: reschedule_appointment.php?id=" . $appointment_id);
        }
        exit();
    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Error in process_updated_appointment_response.php: " . $e->getMessage());
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header("Location: view_appointment_details.php?token=" . $token . "&id=" . $appointment_id . "&error=" . urlencode($e->getMessage()));
    }
    exit();
}
?>