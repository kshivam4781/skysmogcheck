<?php
session_start();
require_once '../config/db_connection.php';

// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set proper JSON header
header('Content-Type: application/json');

// Check if user is logged in and has consultant access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$appointment_id = $_POST['appointment_id'] ?? null;
$reason = $_POST['reason'] ?? '';
$notify_client = isset($_POST['notify_client']) ? 1 : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get appointment details
    $stmt = $conn->prepare("
        SELECT a.*, ce.id as calendar_event_id, ce.vehid
        FROM appointments a
        JOIN calendar_events ce ON a.id = ce.appointment_id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Update calendar_events status
    $stmt = $conn->prepare("
        UPDATE calendar_events 
        SET status = 'cancelled'
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    // Handle appointment status based on number of vehicles
    if ($appointment['number_of_vehicles'] == 1) {
        // If only one vehicle, cancel the entire appointment
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'cancelled'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
    } else {
        // If multiple vehicles, reduce the count
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET number_of_vehicles = number_of_vehicles - 1
            WHERE id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
    }

    // If notification is requested, send email
    if ($notify_client) {
        // Get client email
        $stmt = $conn->prepare("
            SELECT email 
            FROM appointments 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();

        if ($client && $client['email']) {
            // Send cancellation email
            $to = $client['email'];
            $subject = "Appointment Cancellation - Sky Smoke Check LLC";
            $message = "Dear Client,\n\n";
            $message .= "Your appointment has been cancelled.\n";
            $message .= "Reason: " . $reason . "\n\n";
            $message .= "If you need to reschedule, please contact us.\n\n";
            $message .= "Best regards,\nSky Smoke Check LLC";
            $headers = "From: noreply@skysmokecheck.com";

            mail($to, $subject, $message, $headers);
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    // Handle PHP 7+ errors
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
}
?>