<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get appointment ID and instructions
$appointment_id = $_POST['appointment_id'] ?? null;
$instructions = $_POST['instructions'] ?? '';

if (!$appointment_id) {
    echo json_encode(['error' => 'No appointment ID provided']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Handle file upload
    $attachment_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/clean_truck_attachments/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $filename = 'clean_truck_' . $appointment_id . '_' . time() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;

        // Move uploaded file
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            $attachment_path = $filepath;
        }
    }

    // Update the attachment path in the database
    if ($attachment_path) {
        $stmt = $conn->prepare("
            UPDATE clean_truck_checks 
            SET smog_check_attachment = ? 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("si", $attachment_path, $appointment_id);
        $stmt->execute();
    }

    // Get client, appointment, and consultant details
    $stmt = $conn->prepare("
        SELECT 
            a.email,
            a.Name as client_name,
            ctc.clean_truck_completed_date,
            ctc.next_clean_truck_due_date,
            c.firstName as consultant_first_name,
            c.lastName as consultant_last_name,
            c.email as consultant_email,
            c.phone as consultant_phone
        FROM clean_truck_checks ctc
        JOIN appointments a ON ctc.appointment_id = a.id
        LEFT JOIN accounts c ON ctc.user_id = c.idaccounts
        WHERE ctc.appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        // Prepare HTML email content
        $html_message = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; }
                .consultant-info { background-color: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .button { display: inline-block; padding: 10px 20px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Clean Truck Check Completed</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($data['client_name']) . ',</p>
                    
                    <p>We are pleased to inform you that your Clean Truck Check has been completed successfully.</p>
                    
                    <h3>Service Details:</h3>
                    <ul>
                        <li><strong>Completion Date:</strong> ' . date('F j, Y', strtotime($data['clean_truck_completed_date'])) . '</li>
                        <li><strong>Next Due Date:</strong> ' . date('F j, Y', strtotime($data['next_clean_truck_due_date'])) . '</li>
                    </ul>';

        if ($instructions) {
            $html_message .= '
                    <h3>Additional Instructions:</h3>
                    <p>' . nl2br(htmlspecialchars($instructions)) . '</p>';
        }

        $html_message .= '
                    <p>Please find attached your Clean Truck Check certificate for your records.</p>
                    
                    <div class="consultant-info">
                        <h3>Your Consultant Information:</h3>
                        <p><strong>Name:</strong> ' . htmlspecialchars($data['consultant_first_name'] . ' ' . $data['consultant_last_name']) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($data['consultant_email']) . '</p>
                        <p><strong>Phone:</strong> ' . htmlspecialchars($data['consultant_phone']) . '</p>
                        <p>Feel free to reach out to your consultant for any questions or concerns.</p>
                    </div>
                    
                    <p>We will send you a reminder when your next Clean Truck Check is due.</p>
                    
                    <p>Thank you for choosing Sky Smoke Check LLC. We appreciate your business!</p>
                </div>
                <div class="footer">
                    <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                </div>
            </div>
        </body>
        </html>';

        // Send email using email helper
        try {
            $result = sendEmail(
                $data['email'],  // to
                'Clean Truck Check Completed - Sky Smoke Check LLC',  // subject
                $html_message,  // message
                true,  // isHTML
                $attachment_path ? [['path' => $attachment_path]] : [],  // attachments
                []  // cc
            );

            if ($result) {
                $conn->commit();
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Failed to send email");
            }
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            throw new Exception("Failed to send email: " . $e->getMessage());
        }
    } else {
        throw new Exception("Client information not found");
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?> 