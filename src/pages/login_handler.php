<?php
session_start();
require_once '../config/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validation
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "All fields are required";
        header("Location: login.php");
        exit();
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT firstName, lastName, email, passwordHas, accountType, status FROM accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['login_error'] = "Invalid email or password";
        header("Location: login.php");
        exit();
    }

    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['passwordHas'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['email']; // Using email as user identifier
        $_SESSION['first_name'] = $user['firstName'];
        $_SESSION['last_name'] = $user['lastName'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['accountType'] = $user['accountType'];
        
        // Handle different account types
        if ($user['accountType'] == 4) {
            // Admin redirect
            header("Location: welcome.php");
            exit();
        } elseif ($user['accountType'] == 3) {
            // Client account handling
            if ($user['status'] === 'active') {
                // Active client - redirect to client dashboard
                header("Location: client_dashboard.php");
                exit();
            } else {
                // Pending client - show verification message
                $_SESSION['pending_verification'] = true;
                $_SESSION['pending_email'] = $user['email'];
                header("Location: login.php");
                exit();
            }
        } else {
            // Other account types
            header("Location: admin_welcome.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Invalid email or password";
        header("Location: login.php");
        exit();
    }
}

// If somehow we get here without processing
$_SESSION['login_error'] = "Invalid request";
header("Location: login.php");
exit();
?>