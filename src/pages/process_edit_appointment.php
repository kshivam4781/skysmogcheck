<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || strpos($_SESSION['email'], '@skytransportsolutions.com') === false) {
    header("Location: login.php");
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid form submission. Please try again.";
    header("Location: calendar.php");
    exit();
}

// Get appointment ID
$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

if ($appointment_id <= 0) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: calendar.php");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current appointment details for email
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment_data = $result->fetch_assoc();

    // Get vehicle details
    $vehicle_stmt = $conn->prepare("SELECT * FROM vehicles WHERE appointment_id = ?");
    $vehicle_stmt->bind_param("i", $appointment_id);
    $vehicle_stmt->execute();
    $vehicles_result = $vehicle_stmt->get_result();
    $vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);

    // Generate a new token for the updated appointment
    $token = bin2hex(random_bytes(32));
    $token_stmt = $conn->prepare("INSERT INTO appointment_tokens (appointment_id, token, used, consultant_email) VALUES (?, ?, 0, ?)");
    $token_stmt->bind_param("iss", $appointment_id, $token, $_SESSION['email']);
    $token_stmt->execute();

    // Update appointment details
    $stmt = $conn->prepare("UPDATE appointments SET 
        companyName = ?, 
        Name = ?, 
        email = ?, 
        phone = ?, 
        bookingDate = ?, 
        bookingTime = ?, 
        test_location = ?, 
        test_address = ?, 
        special_instructions = ?,
        status = 'pending',
        rescheduled_by = ?
        WHERE id = ?");
    
    $stmt->bind_param("ssssssssssi", 
        $_POST['company'],
        $_POST['name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['preferred_date'],
        $_POST['preferred_time'],
        $_POST['test_location'],
        $_POST['test_address'],
        $_POST['special_instructions'],
        $_SESSION['email'],
        $appointment_id
    );
    
    $stmt->execute();

    // Delete existing vehicle records
    $delete_stmt = $conn->prepare("DELETE FROM vehicles WHERE appointment_id = ?");
    $delete_stmt->bind_param("i", $appointment_id);
    $delete_stmt->execute();

    // Insert new vehicle records
    $vehicle_stmt = $conn->prepare("INSERT INTO vehicles (appointment_id, vehYear, vehMake, vin, plateNo) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($_POST['vehicle_year'] as $index => $year) {
        $vehicle_stmt->bind_param("issss", 
            $appointment_id,
            $year,
            $_POST['make'][$index],
            $_POST['vin'][$index],
            $_POST['license_plate'][$index]
        );
        $vehicle_stmt->execute();
    }

    // Update calendar event
    $event_stmt = $conn->prepare("UPDATE calendar_events SET 
        title = ?, 
        start_time = ?, 
        end_time = ?,
        status = 'pending',
        user_id = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE appointment_id = ?");
    
    $start_time = $_POST['preferred_date'] . ' ' . $_POST['preferred_time'];
    $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' +1 hour'));
    
    $event_stmt->bind_param("ssssi", 
        $_POST['company'],
        $start_time,
        $end_time,
        $_SESSION['user_id'],
        $appointment_id
    );
    
    $event_stmt->execute();

    // Get current domain for email links
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $protocol . $_SERVER['HTTP_HOST'];
    $base_url = $domain . dirname($_SERVER['PHP_SELF']);
    $base_url = rtrim($base_url, '/');

    // Send email to customer for approval
    $to = $_POST['email'];
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
        
        <p>Dear ' . htmlspecialchars($_POST['name']) . ',</p>
        
        <p>Your smoke check appointment has been updated. Please review the changes below:</p>
        
        <div class="details">
            <h3>Updated Appointment Details:</h3>
            <p><strong>Company:</strong> ' . htmlspecialchars($_POST['company']) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($_POST['preferred_date']) . '</p>
            <p><strong>Time:</strong> ' . htmlspecialchars($_POST['preferred_time']) . '</p>
            <p><strong>Test Location:</strong> ' . htmlspecialchars($_POST['test_location'] === 'our_location' ? 'Our Location' : 'Your Location') . '</p>
            ' . ($_POST['test_location'] === 'your_location' ? '<p><strong>Address:</strong> ' . htmlspecialchars($_POST['test_address']) . '</p>' : '') . '
            <p><strong>Contact Name:</strong> ' . htmlspecialchars($_POST['name']) . '</p>
            <p><strong>Contact Email:</strong> ' . htmlspecialchars($_POST['email']) . '</p>
            <p><strong>Contact Phone:</strong> ' . htmlspecialchars($_POST['phone']) . '</p>
            <p><strong>Number of Vehicles:</strong> ' . count($_POST['vehicle_year']) . '</p>
            
            <h4>Vehicle Details:</h4>';
    
    foreach ($_POST['vehicle_year'] as $index => $year) {
        $message .= '
            <div style="margin-bottom: 15px;">
                <p><strong>Vehicle ' . ($index + 1) . ':</strong></p>
                <p><strong>Year:</strong> ' . htmlspecialchars($year) . '</p>
                <p><strong>Make:</strong> ' . htmlspecialchars($_POST['make'][$index]) . '</p>
                <p><strong>VIN:</strong> ' . htmlspecialchars($_POST['vin'][$index]) . '</p>
                <p><strong>License Plate:</strong> ' . htmlspecialchars($_POST['license_plate'][$index]) . '</p>
            </div>';
    }
    
    $message .= '
        </div>
        
        <p>Please review the changes and respond by clicking one of the buttons below:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $base_url . '/process_updated_appointment_response.php?action=accept&token=' . $token . '&id=' . $appointment_id . '" class="button accept">Accept Changes</a>
            <a href="' . $base_url . '/process_updated_appointment_response.php?action=deny&token=' . $token . '&id=' . $appointment_id . '" class="button deny">Request Different Time</a>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>Sky Smoke Check LLC Team</p>
        </div>
    </body>
    </html>';

    require_once('../includes/email_helper.php');
    sendEmail($to, $subject, $message, true);

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Appointment updated successfully. An email has been sent to the client for approval.";
    header("Location: calendar.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error updating appointment: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while updating the appointment. Please try again.";
    header("Location: edit_appointment.php?id=" . $appointment_id);
    exit();
}
?>