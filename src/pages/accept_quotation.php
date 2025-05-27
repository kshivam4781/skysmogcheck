<?php
session_start();
require_once '../config/db_connection.php';

// Check if token is provided
if (!isset($_GET['token'])) {
    header("Location: login.php");
    exit();
}

$token = $_GET['token'];

// Verify token and get appointment details
$stmt = $conn->prepare("
    SELECT at.*, a.*, acc.idaccounts, acc.email as consultant_email
    FROM appointment_tokens at
    JOIN appointments a ON at.appointment_id = a.id
    JOIN accounts acc ON acc.email = at.consultant_email
    WHERE at.token = ? AND at.used = 0
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Invalid or expired token");
}

// Start transaction
$conn->begin_transaction();

try {
    // Update appointment status and approved_by
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'confirmed', 
            approved_by = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("si", $data['consultant_email'], $data['appointment_id']);
    $stmt->execute();

    // Mark token as used
    $stmt = $conn->prepare("
        UPDATE appointment_tokens 
        SET used = 1, 
            used_at = NOW() 
        WHERE token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Get service_id from vehicles table
    $stmt = $conn->prepare("
        SELECT DISTINCT service_id 
        FROM vehicles 
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $data['appointment_id']);
    $stmt->execute();
    $service_result = $stmt->get_result();
    $service_data = $service_result->fetch_assoc();
    $service_id = $service_data['service_id'];

    // Handle service-specific updates
    if ($service_id == 1) {
        // Update clean_truck_checks with consultant's idaccounts
        $stmt = $conn->prepare("
            UPDATE clean_truck_checks 
            SET user_id = ? 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("ii", $data['idaccounts'], $data['appointment_id']);
        $stmt->execute();
    } 
    elseif ($service_id == 2) {
        // Update calendar_events with consultant's email
        $stmt = $conn->prepare("
            UPDATE calendar_events 
            SET user_id = ? 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("si", $data['consultant_email'], $data['appointment_id']);
        $stmt->execute();
    } 
    elseif ($service_id == 3) {
        // Update both tables
        // First update clean_truck_checks with consultant's idaccounts
        $stmt = $conn->prepare("
            UPDATE clean_truck_checks 
            SET user_id = ? 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("ii", $data['idaccounts'], $data['appointment_id']);
        $stmt->execute();

        // Then update calendar_events with consultant's email
        $stmt = $conn->prepare("
            UPDATE calendar_events 
            SET user_id = ? 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("si", $data['consultant_email'], $data['appointment_id']);
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    // Send confirmation email to customer
    require_once '../includes/email_helper.php';
    $to = $data['email'];
    $subject = "Your Service Request Has Been Accepted - Sky Smoke Check LLC";
    
    // Get appointment details for service_id 2 or 3
    $appointment_details = '';
    if ($service_id == 2 || $service_id == 3) {
        $stmt = $conn->prepare("
            SELECT ce.start_time, v.vin, v.vehYear, v.vehMake, v.plateNo
            FROM calendar_events ce
            JOIN vehicles v ON ce.vehid = v.id
            WHERE ce.appointment_id = ?
            ORDER BY ce.start_time ASC
        ");
        $stmt->bind_param("i", $data['appointment_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $appointment_details = '<div class="appointment-details">
                <h3>Your Scheduled Appointments:</h3>';
            
            while ($row = $result->fetch_assoc()) {
                $appointment_details .= '
                <div class="appointment-slot">
                    <p><strong>Vehicle:</strong> ' . htmlspecialchars($row['vehMake'] . ' ' . $row['vehYear']) . '</p>
                    <p><strong>License Plate:</strong> ' . htmlspecialchars($row['plateNo']) . '</p>
                    <p><strong>Date:</strong> ' . date('F j, Y', strtotime($row['start_time'])) . '</p>
                    <p><strong>Time:</strong> ' . date('g:i A', strtotime($row['start_time'])) . '</p>
                </div>';
            }
            
            $appointment_details .= '</div>';
        }
    }
    
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
            .appointment-details {
                background-color: #e3f2fd;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                border-left: 4px solid #3498db;
            }
            .appointment-slot {
                background-color: white;
                padding: 15px;
                border-radius: 5px;
                margin: 10px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                color: #7f8c8d;
            }
            .welcome-message {
                font-size: 1.1em;
                color: #2c3e50;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Service Request Accepted!</h2>
        </div>
        
        <div class="welcome-message">
            <p>Dear ' . htmlspecialchars($data['Name']) . ',</p>
            
            <p>Great news! Your service request has been accepted by our consultant. You can now sit back and relax - our team is on it!</p>
            
            <p>We\'re committed to providing you with the best service possible. Our consultant will be handling your request with care and attention to detail.</p>
        </div>
        
        <div class="details">
            <h3>Service Request Details:</h3>
            <p><strong>Company:</strong> ' . htmlspecialchars($data['companyName']) . '</p>
            <p><strong>Number of Vehicles:</strong> ' . htmlspecialchars($data['number_of_vehicles']) . '</p>
            <p><strong>Service Type:</strong> ' . ($service_id == 1 ? 'Clean Truck Check' : ($service_id == 2 ? 'Smog Test' : 'Combined Service')) . '</p>
        </div>

        ' . $appointment_details . '
        
        <div class="details">
            <h3>What\'s Next?</h3>
            <p>Our consultant will be in touch with you shortly to confirm all the details and answer any questions you may have.</p>
            <p>If you need to make any changes or have any questions, please don\'t hesitate to contact us.</p>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing Sky Smoke Check LLC!</p>
            <p>Best regards,<br>Sky Smoke Check LLC Team</p>
            <p>121 E 11th St, Tracy, CA 95376<br>Phone: (209) 123-4567</p>
        </div>
    </body>
    </html>';

    sendEmail($to, $subject, $message, true);

    // Redirect to success page
    header("Location: quotation_accepted.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    die("An error occurred: " . $e->getMessage());
}
?>