<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 4) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone = trim($_POST['phone']);
    $accountType = intval($_POST['accountType']);

    // Validate input
    $errors = [];
    
    if (empty($firstName)) {
        $errors[] = "First name is required";
    }
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    if ($accountType < 1 || $accountType > 4) {
        $errors[] = "Invalid account type";
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT idaccounts FROM accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists";
    }

    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO accounts (firstName, lastName, email, passwordHas, phone, accountType, createdOn) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sssssi", $firstName, $lastName, $email, $hashedPassword, $phone, $accountType);

        if ($stmt->execute()) {
            // Get account type name
            $accountTypeNames = [
                1 => "Developer",
                2 => "Consultant",
                3 => "User",
                4 => "Admin"
            ];
            $accountTypeName = $accountTypeNames[$accountType];

            // Prepare email content
            $to = $email;
            $subject = "Welcome to Sky Smoke Check LLC";
            $message = "
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
                    <div class='header'>
                        <h2>Welcome to Sky Smoke Check LLC!</h2>
                    </div>
                    
                    <p>Dear {$firstName} {$lastName},</p>
                    
                    <p>Your account has been successfully created with the following details:</p>
                    
                    <div class='details'>
                        <p><strong>Email:</strong> {$email}</p>
                        <p><strong>Password:</strong> {$password}</p>
                        <p><strong>Account Type:</strong> {$accountTypeName}</p>
                    </div>
                    
                    <p>You can now login to our system at: <a href='https://skysmogcheckllc.com'>skysmogcheckllc.com</a></p>
                    <p>Please change your password after your first login for security purposes.</p>
                    
                    <div class='footer'>
                        <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                    </div>
                </body>
                </html>
            ";

            if (sendEmail($to, $subject, $message, true)) {
                $_SESSION['success_message'] = "User added successfully and welcome email sent";
            } else {
                $_SESSION['success_message'] = "User added successfully but email could not be sent";
            }
        } else {
            $_SESSION['error_message'] = "Error adding user: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }

    // Redirect back to manage users page
    header("Location: manage_users.php");
    exit();
}

// If not POST request, redirect to manage users page
header("Location: manage_users.php");
exit();
?>