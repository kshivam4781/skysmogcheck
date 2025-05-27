<?php
session_start();

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['vehicles']) && is_array($data['vehicles'])) {
    // Store vehicles in session
    $_SESSION['validated_vehicles'] = $data['vehicles'];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Vehicles stored successfully'
    ]);
} else {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'No vehicles data provided'
    ]);
}
?> 