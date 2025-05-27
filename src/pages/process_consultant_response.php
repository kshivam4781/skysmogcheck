<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Get the action and token from the URL
$action = $_GET['action'] ?? '';
$token = $_GET['token'] ?? '';

if (!$action || !$token) {
    header("Location: index.php");
    exit();
}

// Verify token and get appointment details
$stmt = $conn->prepare("
    SELECT at.*, a.* 
    FROM appointment_tokens at 
    JOIN appointments a ON at.appointment_id = a.id 
    WHERE at.token = ? AND at.used = 0
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$appointment_data = $result->fetch_assoc();

if (!$appointment_data) {
    // Check if the token exists but appointment is already accepted
    $stmt = $conn->prepare("
        SELECT a.*, at.consultant_email 
        FROM appointments a 
        JOIN appointment_tokens at ON a.id = at.appointment_id 
        WHERE at.token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_appointment = $result->fetch_assoc();

    if ($existing_appointment && $existing_appointment['status'] === 'confirmed') {
        $message = "This appointment has already been accepted by " . $existing_appointment['consultant_email'] . " and added to the calendar.";
        $already_accepted = true;
    } else {
        header("Location: index.php?error=invalid_token");
        exit();
    }
} else {
    $appointment_id = $appointment_data['appointment_id'];
    $consultant_email = $appointment_data['consultant_email'];

    // Check if appointment has already been accepted by another consultant
    $stmt = $conn->prepare("SELECT status, approved_by FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment_status = $result->fetch_assoc();

    if ($appointment_status['status'] === 'confirmed' && $action === 'accept') {
        // Show message that appointment is already accepted
        $message = "This appointment has already been accepted by " . $appointment_status['approved_by'] . " and added to the calendar.";
        $already_accepted = true;
    } else {
        $already_accepted = false;
        
        if ($action === 'accept') {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE appointment_tokens SET used = 1 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();

            // Calculate start and end times
            $start_time = $appointment_data['bookingDate'] . ' ' . $appointment_data['bookingTime'];
            $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' +30 minutes'));
            
            // Prepare calendar event data
            $title = $appointment_data['companyName'] . " - Smog Test";
            $description = "Number of Vehicles: " . $appointment_data['number_of_vehicles'] . "\n" .
                          "Test Address: " . $appointment_data['test_address'];
            $status = "confirmed";

            // Insert into calendar_events
            $stmt = $conn->prepare("
                INSERT INTO calendar_events 
                (user_id, appointment_id, title, start_time, end_time, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssss", $consultant_email, $appointment_id, $title, $start_time, $end_time, $description, $status);
            $stmt->execute();

            // Update appointment status
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'confirmed', 
                    approved_by = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $consultant_email, $appointment_id);
            $stmt->execute();

            // Send confirmation email to customer
            $to = $appointment_data['email'];
            $subject = "Your Smoke Check Appointment Has Been Confirmed";
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
                    <h2>Appointment Confirmation</h2>
                </div>
                
                <p>Dear ' . htmlspecialchars($appointment_data['Name']) . ',</p>
                
                <p>Your smoke check appointment has been confirmed.</p>
                
                <div class="details">
                    <h3>Appointment Details:</h3>
                    <p><strong>Date:</strong> ' . htmlspecialchars($appointment_data['bookingDate']) . '</p>
                    <p><strong>Time:</strong> ' . htmlspecialchars($appointment_data['bookingTime']) . '</p>
                    <p><strong>Number of Vehicles:</strong> ' . htmlspecialchars($appointment_data['number_of_vehicles']) . '</p>
                </div>
                
                <p>A calendar invite has been sent to your email.</p>
                
                <div class="footer">
                    <p>Thank you for choosing Sky Smoke Check LLC.</p>
                    <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                </div>
            </body>
            </html>';
            
            sendEmail($to, $subject, $message, true);

            // Show success message
            $message = "You have accepted the Smog Test for " . $appointment_data['bookingDate'] . " at " . $appointment_data['bookingTime'];
        } else if ($action === 'deny') {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE appointment_tokens SET used = 1 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();

            // Update appointment status
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'denied', 
                    approved_by = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $consultant_email, $appointment_id);
            $stmt->execute();

            // Show denial message
            $message = "You have denied the Smog Test request for " . $appointment_data['bookingDate'] . " at " . $appointment_data['bookingTime'];
        }
    }
}

// Display the response page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Response - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .response-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .response-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .accept-icon {
            color: #28a745;
        }
        .deny-icon {
            color: #dc3545;
        }
        .info-icon {
            color: #3498db;
        }
        .message-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="response-container">
        <?php if ($already_accepted): ?>
            <div class="response-icon info-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h2>Appointment Already Accepted</h2>
            <div class="message-box">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php elseif ($action === 'accept'): ?>
            <div class="response-icon accept-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Appointment Accepted</h2>
            <div class="message-box">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php else: ?>
            <div class="response-icon deny-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2>Appointment Denied</h2>
            <div class="message-box">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="calendar.php" class="btn btn-primary">View Calendar</a>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php exit(); ?>