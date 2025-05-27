<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable displaying errors
ini_set('log_errors', 1); // Enable error logging

// Start session and include database connection
session_start();
require_once '../config/db_connection.php';

// Set proper JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = []) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Ensure we're sending clean JSON
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit();
}

// Check if user is logged in and has consultant access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    sendJsonResponse(false, 'Unauthorized access');
}

try {
    // Handle getting available time slots
    if (isset($_POST['get_available_slots']) && isset($_POST['appointment_date'])) {
        $selected_date = $_POST['appointment_date'];
        $appointment_id = $_POST['appointment_id'] ?? null;
        
        if (!$selected_date) {
            sendJsonResponse(false, 'Date is required');
        }

        // Get booked slots for the selected date
        $stmt = $conn->prepare("
            SELECT start_time 
            FROM calendar_events 
            WHERE DATE(start_time) = ? 
            AND status = 'confirmed'
            AND appointment_id != ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("si", $selected_date, $appointment_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $booked_slots = $result->fetch_all(MYSQLI_ASSOC);
        
        $booked_times = array_map(function($slot) {
            return date('H:i', strtotime($slot['start_time']));
        }, $booked_slots);

        // Generate all possible time slots
        $available_slots = [];
        $start = strtotime('09:00');
        $end = strtotime('17:30');
        $interval = 30 * 60; // 30 minutes in seconds

        for ($time = $start; $time <= $end; $time += $interval) {
            $time_value = date('H:i', $time);
            if (!in_array($time_value, $booked_times)) {
                $available_slots[] = $time_value;
            }
        }

        sendJsonResponse(true, 'Available slots retrieved successfully', [
            'available_slots' => $available_slots
        ]);
    }

    // Handle appointment update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $appointment_id = $_POST['appointment_id'] ?? null;
        
        if (!$appointment_id) {
            sendJsonResponse(false, 'No appointment ID provided');
        }

        // Get the updated data
        $appointment_date = $_POST['appointmentDate'] ?? null;
        $appointment_time = $_POST['appointmentTime'] ?? null;
        $test_location = $_POST['testLocation'] ?? null;
        $test_address = $_POST['testAddress'] ?? null;
        $on_site_charges = $_POST['onSiteCharges'] ?? 0;

        // Log the received data
        error_log("Received update data - Date: $appointment_date, Time: $appointment_time, Location: $test_location");

        // Validate required fields
        if (!$appointment_date || !$appointment_time || !$test_location) {
            sendJsonResponse(false, 'Required fields are missing');
        }

        // Convert client_location to your_location if needed
        if ($test_location === 'client_location') {
            $test_location = 'your_location';
        }

        // Validate test_location value
        $valid_locations = ['our_location', 'your_location'];
        if (!in_array($test_location, $valid_locations)) {
            sendJsonResponse(false, 'Invalid test location value');
        }

        // If test_location is 'our_location', set test_address to null
        if ($test_location === 'our_location') {
            $test_address = null;
        }

        // Combine date and time
        $start_time = date('Y-m-d H:i:s', strtotime("$appointment_date $appointment_time"));
        $end_time = date('Y-m-d H:i:s', strtotime("$appointment_date $appointment_time +30 minutes"));

        // Start transaction
        $conn->begin_transaction();

        try {
            // Update the appointment
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET test_location = ?, 
                    test_address = ?, 
                    onsitecharges = ?,
                    status = 'pending'
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed for appointment update: " . $conn->error);
            }

            $stmt->bind_param("ssdi", $test_location, $test_address, $on_site_charges, $appointment_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for appointment update: " . $stmt->error);
            }

            // Update vehicle information
            if (!isset($_POST['vehicles'])) {
                throw new Exception("Vehicle data is required");
            }

            $vehicles = json_decode($_POST['vehicles'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($vehicles)) {
                throw new Exception("Invalid vehicle data format");
            }

            // Get the vehicle ID from calendar_events
            $stmt = $conn->prepare("
                SELECT vehid 
                FROM calendar_events 
                WHERE appointment_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed for getting vehicle ID: " . $conn->error);
            }
            
            $stmt->bind_param("i", $appointment_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for getting vehicle ID: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $calendar_event = $result->fetch_assoc();
            
            if (!$calendar_event || !$calendar_event['vehid']) {
                throw new Exception("No vehicle found for this appointment");
            }

            $vehicle_id = $calendar_event['vehid'];
            
            // Get vehicle information directly from form fields
            $vehYear = $_POST['vehYear'] ?? '';
            $vehMake = $_POST['vehMake'] ?? '';
            $vin = $_POST['vin'] ?? '';
            $plateNo = $_POST['plateNo'] ?? '';

            // Update the vehicle using form data
            $vehicle_stmt = $conn->prepare("
                UPDATE vehicles 
                SET vehYear = ?,
                    vehMake = ?,
                    vin = ?,
                    plateNo = ?
                WHERE id = ?
            ");

            if (!$vehicle_stmt) {
                throw new Exception("Prepare failed for vehicle update: " . $conn->error);
            }

            $vehicle_stmt->bind_param(
                "isssi",
                $vehYear,
                $vehMake,
                $vin,
                $plateNo,
                $vehicle_id
            );

            if (!$vehicle_stmt->execute()) {
                throw new Exception("Execute failed for vehicle update: " . $vehicle_stmt->error);
            }

            // Update the calendar event
            $stmt = $conn->prepare("
                UPDATE calendar_events 
                SET start_time = ?, 
                    end_time = ?,
                    description = ?,
                    status = 'pending',
                    user_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE appointment_id = ?
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed for calendar update: " . $conn->error);
            }

            // Set description based on test_location
            $description = "Test Address: " . ($test_location === 'our_location' ? '121 E 11th St, Tracy, CA 95376' : $test_address);
            
            $stmt->bind_param("ssssi", $start_time, $end_time, $description, $_SESSION['user_id'], $appointment_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for calendar update: " . $stmt->error);
            }

            // Get appointment and consultant details for email
            $stmt = $conn->prepare("
                SELECT a.*, ce.user_id, acc.email as consultant_email 
                FROM appointments a
                JOIN calendar_events ce ON a.id = ce.appointment_id
                JOIN accounts acc ON ce.user_id = acc.email
                WHERE a.id = ?
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed for fetching appointment details: " . $conn->error);
            }

            $stmt->bind_param("i", $appointment_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for fetching appointment details: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $appointment_data = $result->fetch_assoc();

            if (!$appointment_data) {
                throw new Exception("Failed to fetch appointment data after update");
            }

            // Generate a new token for the updated appointment
            $token = bin2hex(random_bytes(32));
            $token_stmt = $conn->prepare("INSERT INTO appointment_tokens (appointment_id, token, used, consultant_email) VALUES (?, ?, 0, ?)");
            
            if (!$token_stmt) {
                throw new Exception("Prepare failed for token insertion: " . $conn->error);
            }

            $token_stmt->bind_param("iss", $appointment_id, $token, $appointment_data['consultant_email']);
            
            if (!$token_stmt->execute()) {
                throw new Exception("Execute failed for token insertion: " . $token_stmt->error);
            }

            // Commit transaction
            $conn->commit();

            // Get current domain for email links
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $domain = $protocol . $_SERVER['HTTP_HOST'];
            $base_url = $domain . dirname($_SERVER['PHP_SELF']);
            $base_url = rtrim($base_url, '/');

            // Send email to customer for approval
            require_once('../includes/email_helper.php');
            
            $to = $appointment_data['email'];
            $cc = $appointment_data['consultant_email'];
            $subject = "Updated Smoke Check Appointment Request";
            
            $message = '<!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .header {
                        color: #2c3e50;
                        border-bottom: 2px solid #3498db;
                        padding-bottom: 10px;
                        margin-bottom: 20px;
                    }
                    .details {
                        background-color: #f8f9fa;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        margin: 10px 5px;
                        background-color: #3498db;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                    }
                    .button.accept {
                        background-color: #2ecc71;
                    }
                    .button.deny {
                        background-color: #e74c3c;
                    }
                    .footer {
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #ddd;
                        color: #7f8c8d;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Updated Smoke Check Appointment Request</h2>
                </div>
                
                <p>Dear ' . htmlspecialchars($appointment_data['Name']) . ',</p>
                
                <p>Your smoke check appointment has been updated. Please review the changes below:</p>
                
                <div class="details">
                    <h3>Updated Appointment Details:</h3>
                    <p><strong>Company:</strong> ' . htmlspecialchars($appointment_data['companyName']) . '</p>
                    <p><strong>Date:</strong> ' . htmlspecialchars($appointment_date) . '</p>
                    <p><strong>Time:</strong> ' . htmlspecialchars($appointment_time) . '</p>
                    <p><strong>Test Location:</strong> ' . htmlspecialchars($test_location === 'our_location' ? 'Our Location' : 'Your Location') . '</p>
                    ' . ($test_location === 'your_location' ? '<p><strong>Address:</strong> ' . htmlspecialchars($test_address) . '</p>' : '') . '
                    <p><strong>Contact Name:</strong> ' . htmlspecialchars($appointment_data['Name']) . '</p>
                    <p><strong>Contact Email:</strong> ' . htmlspecialchars($appointment_data['email']) . '</p>
                    <p><strong>Contact Phone:</strong> ' . htmlspecialchars($appointment_data['phone']) . '</p>
                </div>
                
                <p>Please review the changes and click one of the buttons below:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $base_url . '/view_appointment_details.php?token=' . $token . '&id=' . $appointment_id . '" class="button" style="background-color: #3498db;">View Full Appointment Details</a>
                    <a href="' . $base_url . '/process_updated_appointment_response.php?action=accept&token=' . $token . '&id=' . $appointment_id . '" class="button accept">Confirm Appointment</a>
                </div>
                
                <div class="footer">
                    <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                </div>
            </body>
            </html>';

            sendEmail($to, $subject, $message, true, $cc);

            sendJsonResponse(true, 'Appointment updated successfully and notification sent', [
                'new_total_price' => number_format($on_site_charges, 2)
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            error_log("Error in update_appointment.php transaction: " . $e->getMessage());
            throw $e;
        }
    }

    // If we get here, it's an invalid request
    sendJsonResponse(false, 'Invalid request');

} catch (Exception $e) {
    // Log the error with more details
    error_log("Error in update_appointment.php: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    
    // Send error response
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage());
}
?>