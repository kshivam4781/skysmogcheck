<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the appointment ID and email notification preference from the request body
$data = json_decode(file_get_contents('php://input'), true);
$appointment_id = $data['id'] ?? null;
$send_email = $data['send_email'] ?? false;
$cancellation_reason = $data['reason'] ?? '';

if (!$appointment_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update appointment status to cancelled
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    // Update calendar event status to cancelled instead of deleting
    $stmt = $conn->prepare("UPDATE calendar_events SET status = 'cancelled' WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    // If email notification is requested, send it
    if ($send_email) {
        // Get appointment details for email
        $stmt = $conn->prepare("
            SELECT a.*, c.email, c.Name 
            FROM appointments a
            JOIN clients c ON a.client_id = c.id
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();

        if ($appointment) {
            // Prepare email content
            $to = $appointment['email'];
            $subject = "Appointment Cancellation - Sky Smoke Check LLC";
            $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { text-align: center; padding: 20px; font-size: 0.9em; color: #6c757d; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Appointment Cancellation</h2>
                        </div>
                        <div class='content'>
                            <p>Dear {$appointment['Name']},</p>
                            <p>We regret to inform you that your appointment has been cancelled.</p>
                            <p><strong>Appointment Details:</strong></p>
                            <ul>
                                <li>Date: " . date('F j, Y', strtotime($appointment['bookingDate'])) . "</li>
                                <li>Time: " . date('g:i A', strtotime($appointment['bookingTime'])) . "</li>
                                <li>Location: {$appointment['test_address']}</li>
                            </ul>";
            
            if (!empty($cancellation_reason)) {
                $message .= "<p><strong>Reason for Cancellation:</strong><br>{$cancellation_reason}</p>";
            }
            
            $message .= "
                            <p>If you would like to reschedule your appointment, please contact us at:</p>
                            <p>Phone: (209) 833-4299<br>
                            Email: info@skysmokecheck.com</p>
                        </div>
                        <div class='footer'>
                            <p>Sky Smoke Check LLC<br>
                            123 Main Street, City, State 12345</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            // Send email using the helper function
            require_once '../helpers/email_helper.php';
            sendEmail($to, $subject, $message);
        }
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 