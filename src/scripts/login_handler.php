<?php
session_start();
require_once '../config/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Debug information
    error_log("Login attempt for email: " . $email);
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found: " . print_r($user, true));
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                error_log("Login successful for user: " . $user['email']);
                header("Location: welcome.php");
                exit();
            } else {
                $error = "Invalid password";
                error_log("Invalid password for user: " . $email);
            }
        } else {
            $error = "User not found";
            error_log("User not found: " . $email);
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}

// If there's an error, redirect back to login with error message
if (isset($error)) {
    $_SESSION['login_error'] = $error;
    header("Location: login.html");
    exit();
}
?> 