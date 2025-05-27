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
    WHERE at.token = ? AND at.appointment_id = ? AND at.used = 1
");
$stmt->bind_param("si", $token, $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();
$appointment_data = $result->fetch_assoc();

if (!$appointment_data) {
    header("Location: index.php?error=invalid_token");
    exit();
}

$appointment_id = $appointment_data['appointment_id'];

if ($action === 'accept') {
    // Update appointment status to confirmed
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'confirmed'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

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

    // Insert into calendar_events
    $stmt = $conn->prepare("
        INSERT INTO calendar_events 
        (user_id, appointment_id, title, start_time, end_time, description) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $appointment_data['approved_by'], $appointment_id, $title, $start_time, $end_time, $description);
    $stmt->execute();

    // Send confirmation email to consultant
    $to = $appointment_data['approved_by'];
    $subject = "Customer Accepted Rescheduled Appointment";
    
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
                border-bottom: 2px solid #2ecc71;
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
            <h2>Appointment Confirmed</h2>
        </div>
        
        <p>The customer has accepted the rescheduled appointment.</p>
        
        <div class="details">
            <h3>Appointment Details:</h3>
            <p><strong>Company:</strong> ' . htmlspecialchars($appointment_data['companyName']) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($appointment_data['bookingDate']) . '</p>
            <p><strong>Time:</strong> ' . htmlspecialchars($appointment_data['bookingTime']) . '</p>
            <p><strong>Test Location:</strong> ' . htmlspecialchars($appointment_data['test_location'] === 'our_location' ? 'Our Location' : 'Your Location') . '</p>
            ' . ($appointment_data['test_location'] === 'your_location' ? '<p><strong>Address:</strong> ' . htmlspecialchars($appointment_data['test_address']) . '</p>' : '') . '
        </div>
        
        <div class="footer">
            <p>Best regards,<br>Sky Smoke Check LLC Team</p>
        </div>
    </body>
    </html>';

    sendEmail($to, $subject, $message, true);

    // Show success message
    $message = "You have accepted the rescheduled appointment for " . $appointment_data['bookingDate'] . " at " . $appointment_data['bookingTime'];
    $icon_class = "accept-icon";
} else if ($action === 'deny') {
    // Update appointment status back to pending
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'pending'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    // Mark token as used
    $stmt = $conn->prepare("UPDATE appointment_tokens SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Send notification email to consultant
    $to = $appointment_data['approved_by'];
    $subject = "Customer Requested Different Time";
    
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
                border-bottom: 2px solid #e74c3c;
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
            <h2>Different Time Requested</h2>
        </div>
        
        <p>The customer has requested a different time for the appointment.</p>
        
        <div class="details">
            <h3>Original Appointment Details:</h3>
            <p><strong>Company:</strong> ' . htmlspecialchars($appointment_data['companyName']) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($appointment_data['bookingDate']) . '</p>
            <p><strong>Time:</strong> ' . htmlspecialchars($appointment_data['bookingTime']) . '</p>
        </div>
        
        <p>Please contact the customer to discuss alternative times.</p>
        
        <div class="footer">
            <p>Best regards,<br>Sky Smoke Check LLC Team</p>
        </div>
    </body>
    </html>';

    sendEmail($to, $subject, $message, true);

    // Show denial message
    $message = "You have requested a different time for the appointment on " . $appointment_data['bookingDate'] . " at " . $appointment_data['bookingTime'];
    $icon_class = "deny-icon";
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
    </style>
</head>
<body>
    <div class="response-container">
        <div class="response-icon">
            <i class="fas <?php echo $action === 'accept' ? 'fa-check-circle' : 'fa-times-circle'; ?> <?php echo $icon_class; ?>"></i>
        </div>
        <h2><?php echo $action === 'accept' ? 'Appointment Accepted' : 'Different Time Requested'; ?></h2>
        <p><?php echo $message; ?></p>
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">Return to Home</a>
        </div>
    </div>
</body>
</html> 