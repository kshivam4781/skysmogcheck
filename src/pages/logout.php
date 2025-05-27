<?php
session_start();

// Store the success message in session
$_SESSION['logout_message'] = "You have been logged out successfully.";

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to index.php
header("Location: ../pages/index.php");
exit();
?> 