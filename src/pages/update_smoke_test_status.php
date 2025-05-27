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

if (!$data || !isset($data['vehicle_id']) || !isset($data['status'])) {
    echo json_encode(['error' => 'Invalid data']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get vehicle and appointment information
    $stmt = $conn->prepare("
        SELECT v.*, a.id as appointment_id, a.number_of_vehicles, a.status as appointment_status
        FROM vehicles v
        LEFT JOIN appointments a ON v.appointment_id = a.id
        WHERE v.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare vehicle query: {$conn->error}");
    }
    
    $stmt->bind_param("i", $data['vehicle_id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute vehicle query: {$stmt->error}");
    }
    
    $result = $stmt->get_result();
    $vehicle = $result->fetch_assoc();

    if (!$vehicle) {
        throw new Exception('Vehicle not found');
    }

    // Update calendar_events
    $stmt = $conn->prepare("
        UPDATE calendar_events 
        SET status = 'completed', 
            updated_at = NOW() 
        WHERE vehid = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare calendar_events update: {$conn->error}");
    }
    
    $stmt->bind_param("i", $data['vehicle_id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update calendar_events: {$stmt->error}");
    }

    // Update vehicles table
    $stmt = $conn->prepare("
        UPDATE vehicles 
        SET smoke_test_status = ?,
            smoke_test_notes = ?,
            attachment_path = ?,
            next_due_date = DATE_ADD(NOW(), INTERVAL 6 MONTH)
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare vehicles update: {$conn->error}");
    }
    
    $notes = $data['notes'] ?? null;
    $attachment = $data['attachment_path'] ?? null;
    
    $stmt->bind_param("sssi", 
        $data['status'],
        $notes,
        $attachment,
        $data['vehicle_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update vehicles: {$stmt->error}");
    }

    // Handle service_id specific logic
    if ($vehicle['service_id'] == 2 || $vehicle['service_id'] == 3) {
        if ($vehicle['appointment_id']) {
            // Check if all vehicles in the appointment are completed
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total_vehicles,
                       SUM(CASE 
                           WHEN service_id = 1 AND clean_truck_check_status != 'pending' THEN 1
                           WHEN service_id = 2 AND smoke_test_status != 'pending' THEN 1
                           WHEN service_id = 3 AND clean_truck_check_status != 'pending' AND smoke_test_status != 'pending' THEN 1
                           ELSE 0
                       END) as completed_vehicles
                FROM vehicles
                WHERE appointment_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare appointment check query: {$conn->error}");
            }
            
            $stmt->bind_param("i", $vehicle['appointment_id']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute appointment check query: {$stmt->error}");
            }
            
            $result = $stmt->get_result();
            $vehicle_status = $result->fetch_assoc();

            // Update appointment status if all vehicles are completed
            if ($vehicle_status['total_vehicles'] == $vehicle_status['completed_vehicles']) {
                $stmt = $conn->prepare("
                    UPDATE appointments 
                    SET status = 'completed' 
                    WHERE id = ?
                ");
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare appointment update: {$conn->error}");
                }
                
                $stmt->bind_param("i", $vehicle['appointment_id']);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update appointment status: {$stmt->error}");
                }
            }
        }
    }

    // Handle service_id = 3 specific logic
    if ($vehicle['service_id'] == 3) {
        error_log("Starting clean_truck_checks update for service_id = 3");
        error_log("Vehicle data: " . print_r($vehicle, true));
        error_log("Input data: " . print_r($data, true));

        // First check if record exists in clean_truck_checks
        $stmt = $conn->prepare("
            SELECT id FROM clean_truck_checks 
            WHERE vehicle_id = ? AND appointment_id = ?
        ");
        
        if (!$stmt) {
            error_log("Failed to prepare clean_truck_checks check query: " . $conn->error);
            throw new Exception("Failed to prepare clean_truck_checks check query: {$conn->error}");
        }
        
        $stmt->bind_param("ii", $data['vehicle_id'], $vehicle['appointment_id']);
        if (!$stmt->execute()) {
            error_log("Failed to execute clean_truck_checks check query: " . $stmt->error);
            throw new Exception("Failed to execute clean_truck_checks check query: {$stmt->error}");
        }
        
        $result = $stmt->get_result();
        $check_exists = $result->fetch_assoc();
        error_log("Check exists result: " . print_r($check_exists, true));

        if (!$check_exists) {
            error_log("No existing record found, preparing to insert new record");
            // Insert new record if it doesn't exist
            $stmt = $conn->prepare("
                INSERT INTO clean_truck_checks 
                (appointment_id, service_id, vin_number, plate_number, vehicle_make, 
                 vehicle_year, smog_check_completed, smog_check_verified, 
                 smog_check_status, vehicle_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'yes', 'yes', 'confirmed', ?, NOW(), NOW())
            ");
            
            if (!$stmt) {
                error_log("Failed to prepare clean_truck_checks insert: " . $conn->error);
                throw new Exception("Failed to prepare clean_truck_checks insert: {$conn->error}");
            }
            
            $bind_params = array(
                $vehicle['appointment_id'],
                $vehicle['service_id'],
                $vehicle['vin'],
                $vehicle['plateNo'],
                $vehicle['vehMake'],
                $vehicle['vehYear'],
                $data['vehicle_id']
            );
            error_log("Insert bind parameters: " . print_r($bind_params, true));
            
            $stmt->bind_param("iisssii", 
                $bind_params[0],
                $bind_params[1],
                $bind_params[2],
                $bind_params[3],
                $bind_params[4],
                $bind_params[5],
                $bind_params[6]
            );
            
            if (!$stmt->execute()) {
                error_log("Failed to insert clean_truck_checks: " . $stmt->error);
                throw new Exception("Failed to insert clean_truck_checks: {$stmt->error}");
            }
            error_log("Successfully inserted new clean_truck_checks record");
        } else {
            error_log("Existing record found, preparing to update");
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE clean_truck_checks 
                SET smog_check_status = 'confirmed',
                    smog_check_completed = 'yes',
                    smog_check_verified = 'yes',
                    updated_at = NOW()
                WHERE vehicle_id = ? AND appointment_id = ?
            ");
            
            if (!$stmt) {
                error_log("Failed to prepare clean_truck_checks update: " . $conn->error);
                throw new Exception("Failed to prepare clean_truck_checks update: {$conn->error}");
            }
            
            $stmt->bind_param("ii", $data['vehicle_id'], $vehicle['appointment_id']);
            if (!$stmt->execute()) {
                error_log("Failed to update clean_truck_checks: " . $stmt->error);
                throw new Exception("Failed to update clean_truck_checks: {$stmt->error}");
            }
            error_log("Successfully updated clean_truck_checks record");
        }
    } else {
        error_log("Skipping clean_truck_checks update - service_id is not 3. Current service_id: " . $vehicle['service_id']);
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