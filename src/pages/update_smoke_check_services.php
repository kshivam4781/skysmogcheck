<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['appointment_id'])) {
    echo json_encode(['error' => 'Invalid data']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update calendar_events status
    $stmt = $conn->prepare("
        UPDATE calendar_events 
        SET 
            status = ?,
            updated_at = NOW()
        WHERE appointment_id = ?
        AND title LIKE '%Smoke Check%'
    ");
    
    $stmt->bind_param("si", $data['status'], $data['appointment_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update calendar_events record");
    }

    // Update vehicles table with smoke test status
    if ($data['status'] === 'completed') {
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET 
                smoke_test_status = 'passed',
                smoke_test_notes = ?,
                updated_at = NOW()
            WHERE appointment_id = ?
        ");
        
        $stmt->bind_param("si", $data['notes'], $data['appointment_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update vehicles record");
        }

        // Check if all vehicles for this appointment have passed both checks
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_vehicles,
                   SUM(CASE WHEN clean_truck_check_status = 'passed' AND smoke_test_status = 'passed' THEN 1 ELSE 0 END) as passed_vehicles
            FROM vehicles 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("i", $data['appointment_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicle_status = $result->fetch_assoc();

        // If all vehicles have passed both checks, update appointment status
        if ($vehicle_status['total_vehicles'] > 0 && 
            $vehicle_status['total_vehicles'] == $vehicle_status['passed_vehicles']) {
            
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'completed' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $data['appointment_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update appointment status");
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?> 