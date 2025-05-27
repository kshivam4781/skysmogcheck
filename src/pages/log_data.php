<?php
// Get the raw POST data
$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true);

// Log the data
error_log("=== VALIDATE_VEHICLE DATA LOG ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Form Data:");
error_log(print_r($data['form_data'], true));
error_log("Vehicles Data:");
error_log(print_r($data['vehicles'], true));
error_log("=== END LOG ===");

// Send response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?> 