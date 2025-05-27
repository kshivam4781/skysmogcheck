<?php
session_start();
require_once '../includes/contact_functions.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If there are no errors, save the message
    if (empty($errors)) {
        $result = saveContactMessage($name, $email, $phone, $subject, $message);
        
        if ($result['success']) {
            $_SESSION['contact_success'] = $result['message'];
        } else {
            $_SESSION['contact_error'] = $result['message'];
        }
    } else {
        $_SESSION['contact_errors'] = $errors;
        $_SESSION['contact_form_data'] = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message
        ];
    }
    
    // Redirect back to the contact page
    header('Location: contact.php');
    exit();
} else {
    // If not a POST request, redirect to contact page
    header('Location: contact.php');
    exit();
}
?>