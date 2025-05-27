<?php
require_once __DIR__ . '/../config/db_connection.php';

// Function to check if a reminder already exists
function reminderExists($conn, $appointment_id, $reminder_type, $vehicle_id = null) {
    $sql = "
        SELECT id FROM reminders 
        WHERE reminder_type = ? 
        AND status = 'active'
    ";
    
    if ($appointment_id) {
        $sql .= " AND appointment_id = ?";
    }
    if ($vehicle_id) {
        $sql .= " AND vehicle_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($appointment_id && $vehicle_id) {
        $stmt->bind_param("sii", $reminder_type, $appointment_id, $vehicle_id);
    } elseif ($appointment_id) {
        $stmt->bind_param("si", $reminder_type, $appointment_id);
    } elseif ($vehicle_id) {
        $stmt->bind_param("si", $reminder_type, $vehicle_id);
    } else {
        $stmt->bind_param("s", $reminder_type);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Get appointments within next 2 days
$stmt = $conn->prepare("
    SELECT 
        ce.*,
        v.id as vehicle_id
    FROM calendar_events ce
    JOIN vehicles v ON ce.vehid = v.id
    WHERE ce.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)
    AND ce.status = 'confirmed'
");
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get vehicles needing smog test reminders
$smogStmt = $conn->prepare("
    SELECT 
        v.*
    FROM vehicles v
    WHERE v.service_id IN (2, 3)
    AND v.smoke_test_status != 'pending'
    AND v.next_due_date IS NOT NULL
    AND v.next_due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 90 DAY)
");
$smogStmt->execute();
$smogVehicles = $smogStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get vehicles needing clean truck check reminders
$cleanTruckStmt = $conn->prepare("
    SELECT 
        v.*
    FROM vehicles v
    WHERE v.service_id IN (1, 3)
    AND v.clean_truck_check_status != 'pending'
    AND v.clean_truck_check_next_date IS NOT NULL
    AND v.clean_truck_check_next_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
");
$cleanTruckStmt->execute();
$cleanTruckVehicles = $cleanTruckStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare statement for inserting reminders
$insertStmt = $conn->prepare("
    INSERT INTO reminders (
        appointment_id,
        vehicle_id,
        reminder_type,
        send_date,
        title,
        message_id,
        last_run,
        next_send,
        created_at,
        status,
        is_recurring,
        recurring_type
    ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, NOW(), 'active', 1, ?)
");

// Process each appointment
foreach ($appointments as $appointment) {
    // Check if reminder already exists
    if (!reminderExists($conn, $appointment['appointment_id'], 'appointment')) {
        // Calculate next send time (24 hours before appointment)
        $nextSend = date('Y-m-d H:i:s', strtotime($appointment['start_time'] . ' -24 hours'));
        
        // Bind parameters for the insert
        $reminderType = 'appointment';
        $title = $appointment['title'] . " Reminder";
        $messageId = 3; // ID of the appointment reminder template
        $recurringType = 'appointment_same_day';
        
        $insertStmt->bind_param(
            "iissssss",
            $appointment['appointment_id'],
            $appointment['vehicle_id'],
            $reminderType,
            $appointment['start_time'],
            $title,
            $messageId,
            $nextSend,
            $recurringType
        );
        
        try {
            $insertStmt->execute();
            echo "Created appointment reminder for appointment ID: " . $appointment['appointment_id'] . "\n";
        } catch (Exception $e) {
            echo "Error creating appointment reminder for appointment ID: " . $appointment['appointment_id'] . " - " . $e->getMessage() . "\n";
        }
    } else {
        echo "Appointment reminder already exists for appointment ID: " . $appointment['appointment_id'] . "\n";
    }
}

// Process each vehicle needing smog test reminder
foreach ($smogVehicles as $vehicle) {
    // Check if reminder already exists for this vehicle
    if (!reminderExists($conn, null, 'smog_test', $vehicle['id'])) {
        // Calculate next send time (90 days before due date)
        $nextSend = date('Y-m-d H:i:s', strtotime($vehicle['next_due_date'] . ' -90 days'));
        
        // Bind parameters for the insert
        $reminderType = 'smog_test';
        $title = "Smog Test Reminder - Vehicle ID: " . $vehicle['id'];
        $messageId = 2; // ID of the smog test reminder template
        $recurringType = 'smog_test_3months';
        
        $insertStmt->bind_param(
            "iissssss",
            $vehicle['appointment_id'],
            $vehicle['id'],
            $reminderType,
            $vehicle['next_due_date'],
            $title,
            $messageId,
            $nextSend,
            $recurringType
        );
        
        try {
            $insertStmt->execute();
            echo "Created smog test reminder for vehicle ID: " . $vehicle['id'] . "\n";
        } catch (Exception $e) {
            echo "Error creating smog test reminder for vehicle ID: " . $vehicle['id'] . " - " . $e->getMessage() . "\n";
        }
    } else {
        echo "Smog test reminder already exists for vehicle ID: " . $vehicle['id'] . "\n";
    }
}

// Process each vehicle needing clean truck check reminder
foreach ($cleanTruckVehicles as $vehicle) {
    // Check if reminder already exists for this vehicle
    if (!reminderExists($conn, null, 'clean_truck', $vehicle['id'])) {
        // Calculate next send time (30 days before due date)
        $nextSend = date('Y-m-d H:i:s', strtotime($vehicle['clean_truck_check_next_date'] . ' -30 days'));
        
        // Bind parameters for the insert
        $reminderType = 'clean_truck';
        $title = "Clean Truck Check - Vehicle ID: " . $vehicle['id'];
        $messageId = 1; // ID of the clean truck check reminder template
        $recurringType = 'clean_truck_6months';
        
        $insertStmt->bind_param(
            "iissssss",
            $vehicle['appointment_id'],
            $vehicle['id'],
            $reminderType,
            $vehicle['clean_truck_check_next_date'],
            $title,
            $messageId,
            $nextSend,
            $recurringType
        );
        
        try {
            $insertStmt->execute();
            echo "Created clean truck check reminder for vehicle ID: " . $vehicle['id'] . "\n";
        } catch (Exception $e) {
            echo "Error creating clean truck check reminder for vehicle ID: " . $vehicle['id'] . " - " . $e->getMessage() . "\n";
        }
    } else {
        echo "Clean truck check reminder already exists for vehicle ID: " . $vehicle['id'] . "\n";
    }
}

// Close statements
$stmt->close();
$smogStmt->close();
$cleanTruckStmt->close();
$insertStmt->close();

echo "Reminder creation process completed.\n";
?> 