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

if (!$data || !isset($data['appointment_id']) || !isset($data['vehicle_id'])) {
    echo json_encode(['error' => 'Invalid data - missing required fields']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Prepare the base update query for clean_truck_checks
    $query = "
        UPDATE clean_truck_checks 
        SET 
            smog_check_completed = ?,
            smog_check_status = ?,
            smog_check_verified = ?,
            clean_truck_status = ?,
            updated_at = NOW()";

    // Add timestamp fields if clean truck is completed
    if ($data['update_timestamps']) {
        $query .= ",
            clean_truck_completed_date = NOW(),
            next_clean_truck_due_date = DATE_ADD(NOW(), INTERVAL 6 MONTH)";
    }

    $query .= " WHERE appointment_id = ? AND vehicle_id = ?";

    $stmt = $conn->prepare($query);
    
    // Bind parameters
    $stmt->bind_param(
        "ssssii",
        $data['smog_check_completed'],
        $data['smog_check_status'],
        $data['smog_check_verified'],
        $data['clean_truck_status'],
        $data['appointment_id'],
        $data['vehicle_id']
    );

    // Execute the update
    if (!$stmt->execute()) {
        throw new Exception("Failed to update clean_truck_checks record");
    }

    // If clean truck is completed, update the vehicles table
    if ($data['update_timestamps']) {
        // Update the vehicles table directly with vehicle_id
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET 
                clean_truck_check_status = 'passed',
                clean_truck_check_next_date = DATE_ADD(NOW(), INTERVAL 6 MONTH)
            WHERE appointment_id = ?
            AND id = ?
        ");
        $stmt->bind_param("ii", $data['appointment_id'], $data['vehicle_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update vehicles record");
        }

        // Get the service_id of the current vehicle
        $stmt = $conn->prepare("
            SELECT service_id, smoke_test_status 
            FROM vehicles 
            WHERE id = ? AND appointment_id = ?
        ");
        $stmt->bind_param("ii", $data['vehicle_id'], $data['appointment_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicle_data = $result->fetch_assoc();

        if ($vehicle_data) {
            $should_update_appointment = false;

            if ($vehicle_data['service_id'] == 1) {
                // Check all vehicles with the same appointment_id
                $stmt = $conn->prepare("
                    SELECT 
                        service_id,
                        clean_truck_check_status,
                        smoke_test_status
                    FROM vehicles 
                    WHERE appointment_id = ?
                ");
                $stmt->bind_param("i", $data['appointment_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $all_vehicles = $result->fetch_all(MYSQLI_ASSOC);

                $all_completed = true;
                foreach ($all_vehicles as $vehicle) {
                    if ($vehicle['service_id'] == 1 && $vehicle['clean_truck_check_status'] != 'passed') {
                        $all_completed = false;
                        break;
                    } elseif ($vehicle['service_id'] == 2 && $vehicle['smoke_test_status'] == 'pending') {
                        $all_completed = false;
                        break;
                    } elseif ($vehicle['service_id'] == 3 && 
                             ($vehicle['clean_truck_check_status'] != 'passed' || $vehicle['smoke_test_status'] == 'pending')) {
                        $all_completed = false;
                        break;
                    }
                }
                $should_update_appointment = $all_completed;

            } elseif ($vehicle_data['service_id'] == 3 && $vehicle_data['smoke_test_status'] != 'pending') {
                // Check all vehicles with the same appointment_id
                $stmt = $conn->prepare("
                    SELECT 
                        service_id,
                        clean_truck_check_status,
                        smoke_test_status
                    FROM vehicles 
                    WHERE appointment_id = ?
                ");
                $stmt->bind_param("i", $data['appointment_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $all_vehicles = $result->fetch_all(MYSQLI_ASSOC);

                $all_completed = true;
                foreach ($all_vehicles as $vehicle) {
                    if ($vehicle['service_id'] == 1 && $vehicle['clean_truck_check_status'] != 'passed') {
                        $all_completed = false;
                        break;
                    } elseif ($vehicle['service_id'] == 2 && $vehicle['smoke_test_status'] == 'pending') {
                        $all_completed = false;
                        break;
                    } elseif ($vehicle['service_id'] == 3 && 
                             ($vehicle['clean_truck_check_status'] != 'passed' || $vehicle['smoke_test_status'] == 'pending')) {
                        $all_completed = false;
                        break;
                    }
                }
                $should_update_appointment = $all_completed;
            }

            // Update appointment status if all conditions are met
            if ($should_update_appointment) {
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
    }

    // Update vehicles table when smog check is verified
    if ($data['smog_check_verified'] === 'yes') {
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET 
                result = 'pass',
                next_due_date = DATE_ADD(NOW(), INTERVAL 6 MONTH),
                smoke_test_status = 'passed'
            WHERE id = ?
            AND appointment_id = ?
        ");
        $stmt->bind_param("ii", $data['vehicle_id'], $data['appointment_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update vehicles record for smog check verification");
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