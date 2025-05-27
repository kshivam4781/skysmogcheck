<?php
session_start();
require_once '../config/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$appointment_id = $_GET['appointment_id'] ?? null;

if (!$appointment_id) {
    echo json_encode(['error' => 'No appointment ID provided']);
    exit();
}

try {
    // Get appointment details
    $stmt = $conn->prepare("
        SELECT a.*, ce.user_id as consultant_id 
        FROM appointments a 
        JOIN calendar_events ce ON a.id = ce.appointment_id 
        WHERE a.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $appointment_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if (!$appointment) {
        echo json_encode(['error' => 'Appointment not found']);
        exit();
    }

    // Get vehicle details
    $vehicle_stmt = $conn->prepare("
        SELECT * FROM vehicles 
        WHERE appointment_id = ?
    ");
    
    if (!$vehicle_stmt) {
        throw new Exception("Vehicle prepare failed: " . $conn->error);
    }
    
    $vehicle_stmt->bind_param("i", $appointment_id);
    if (!$vehicle_stmt->execute()) {
        throw new Exception("Vehicle execute failed: " . $vehicle_stmt->error);
    }
    
    $vehicle_result = $vehicle_stmt->get_result();
    $vehicles = [];
    while ($vehicle = $vehicle_result->fetch_assoc()) {
        $vehicles[] = $vehicle;
    }

    // Get consultant details
    $consultant_stmt = $conn->prepare("
        SELECT firstName, lastName FROM accounts 
        WHERE email = ?
    ");
    
    if (!$consultant_stmt) {
        throw new Exception("Consultant prepare failed: " . $conn->error);
    }
    
    $consultant_stmt->bind_param("i", $appointment['consultant_id']);
    if (!$consultant_stmt->execute()) {
        throw new Exception("Consultant execute failed: " . $consultant_stmt->error);
    }
    
    $consultant_result = $consultant_stmt->get_result();
    $consultant = $consultant_result->fetch_assoc();

    echo json_encode([
        'appointment' => $appointment,
        'vehicles' => $vehicles,
        'consultant' => $consultant
    ]);
} catch (Exception $e) {
    error_log("Error in get_appointment_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 