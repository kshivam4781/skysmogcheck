<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Function to generate a random token
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $company_name = $_POST['company_name'] ?? '';
        $contact_person = $_POST['contact_person'] ?? '';
        $email = $_POST['reg_email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $dot_number = $_POST['dot_number'] ?? '';
        $company_type = $_POST['company_type'] ?? '';
        $password = $_POST['reg_password'] ?? '';
        $confirm_password = $_POST['reg_confirm_password'] ?? '';

        // Validate password match
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }

        // Start transaction
        $conn->begin_transaction();

        // Insert into clients table
        $stmt = $conn->prepare("
            INSERT INTO clients (
                company_name, contact_person_name, email, phone, 
                dot_number, status, type, 
                created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
        ");

        $stmt->bind_param(
            "sssssss",
            $company_name,
            $contact_person,
            $email,
            $phone,
            $dot_number,
            $company_type,
            $email
        );

        $stmt->execute();
        $client_id = $conn->insert_id;

        // Insert into accounts table
        $stmt = $conn->prepare("
            INSERT INTO accounts (
                firstName, lastName, email, phone, 
                accountType, createdOn, passwordHas, status, associated_id
            ) VALUES (?, NULL, ?, ?, 3, NOW(), ?, 'pending', ?)
        ");

        $password_hash = hashPassword($password);
        $stmt->bind_param(
            "ssssi",
            $contact_person,
            $email,
            $phone,
            $password_hash,
            $client_id
        );

        $stmt->execute();

        // Generate and store confirmation token
        $token = generateToken();
        $stmt = $conn->prepare("
            INSERT INTO appointment_tokens (
                token, consultant_email, appointment_id
            ) VALUES (?, ?, ?)
        ");
        $stmt->bind_param("ssi", $token, $email, $client_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Send confirmation email
        $subject = "Confirm Your Sky Smoke Check Account";
        $confirmation_link = "http://" . $_SERVER['HTTP_HOST'] . "/Test/src/pages/confirm_account.php?token=" . $token;
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to Sky Smoke Check</title>
            <style>
                @keyframes float {
                    0% { transform: translateY(0px); }
                    50% { transform: translateY(-10px); }
                    100% { transform: translateY(0px); }
                }
                .cloud-icon {
                    animation: float 3s ease-in-out infinite;
                    color: #17a2b8;
                    font-size: 48px;
                    margin: 20px 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                    background-color: #f8f9fa;
                    border-radius: 10px;
                }
                .header {
                    text-align: center;
                    padding: 20px;
                    background-color: #17a2b8;
                    color: white;
                    border-radius: 10px 10px 0 0;
                }
                .content {
                    padding: 30px;
                    background-color: white;
                    border-radius: 0 0 10px 10px;
                }
                .button {
                    display: inline-block;
                    padding: 12px 30px;
                    background-color: #17a2b8;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #6c757d;
                    font-size: 12px;
                }
                .welcome-text {
                    font-size: 24px;
                    color: #2c3e50;
                    margin: 20px 0;
                }
                .message {
                    color: #6c757d;
                    line-height: 1.6;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Sky Smoke Check!</h1>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <i class='fas fa-cloud cloud-icon'></i>
                    </div>
                    <h2 class='welcome-text'>Welcome to the Sky Family! 🎉</h2>
                    <p class='message'>
                        Thank you for creating an account with Sky Smoke Check LLC. We're excited to have you on board and look forward to helping you with all your trucking needs!
                    </p>
                    <p class='message'>
                        To complete your registration and activate your account, please click the button below:
                    </p>
                    <div style='text-align: center;'>
                        <a href='{$confirmation_link}' class='button'>Confirm Your Account</a>
                    </div>
                    <p class='message'>
                        If you did not create this account, please ignore this email.
                    </p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                    <p>121 E 11th St, Tracy, CA 95376<br>Phone: (555) 123-4567</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Send email
        $email_sent = sendEmail($email, $subject, $message, true);

        if ($email_sent) {
            $_SESSION['register_success'] = true;
            $_SESSION['success_message'] = "Registration successful! Please check your email to confirm your account.";
            header("Location: login.php");
            exit();
        } else {
            throw new Exception("Failed to send confirmation email");
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            $conn->rollback();
        }
        
        $_SESSION['register_error'] = $e->getMessage();
        header("Location: login.php?tab=register");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?> 