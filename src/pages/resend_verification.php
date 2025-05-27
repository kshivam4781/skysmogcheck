<?php
session_start();
require_once '../config/db_connection.php';
require_once '../helpers/email_helper.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit();
}

try {
    // Get user details
    $stmt = $conn->prepare("SELECT firstName, lastName, email, status FROM accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    $user = $result->fetch_assoc();

    if ($user['status'] === 'active') {
        echo json_encode(['success' => false, 'message' => 'Account is already active']);
        exit();
    }

    // Check for existing unused token
    $stmt = $conn->prepare("
        SELECT token 
        FROM appointment_tokens 
        WHERE consultant_email = ? AND used = 0 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $token = $row['token'];
    } else {
        // No unused token found
        echo json_encode([
            'success' => false,
            'message' => 'Your verification token has expired. Please contact support for assistance.'
        ]);
        exit();
    }

    // Update token expiry
    $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Store token in database
    $stmt = $conn->prepare("UPDATE accounts SET verification_token = ?, token_expiry = ? WHERE email = ?");
    $stmt->bind_param("sss", $token, $expiry, $email);
    $stmt->execute();

    // Prepare email content
    $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . "/Test/src/pages/verify_account.php?token=" . $token;
    
    $emailContent = "
        <h2>Welcome to Sky Smoke Check!</h2>
        <p>Dear {$user['firstName']} {$user['lastName']},</p>
        <p>Thank you for registering with us. To complete your registration and activate your account, please click the link below:</p>
        <p><a href='{$verificationLink}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Your Account</a></p>
        <p>This link will expire in 24 hours.</p>
        <p>If you did not request this verification, please ignore this email.</p>
        <p>Best regards,<br>Sky Smoke Check Team</p>
    ";

    // Send verification email
    $emailSent = sendEmail(
        $user['email'],
        'Verify Your Sky Smoke Check Account',
        $emailContent
    );

    if ($emailSent) {
        echo json_encode(['success' => true, 'message' => 'Verification email sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email']);
    }

} catch (Exception $e) {
    error_log("Error in resend_verification.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
}
?> 