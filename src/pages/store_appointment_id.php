<?php
session_start();

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['appointment_id'])) {
    $_SESSION['last_appointment_id'] = $data['appointment_id'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No appointment ID provided']);
}
?> 