<?php
// Prevent any output before JSON response
ob_start();

session_start();
require_once '../config/db_connection.php';

// Enable error reporting but log to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $additionalData = []) {
    ob_clean(); // Clear any output buffer
    header('Content-Type: application/json');
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $additionalData);
    
    logData('Sending JSON response', $response);
    
    $jsonResponse = json_encode($response);
    if ($jsonResponse === false) {
        logData('JSON encode error', json_last_error_msg());
        $errorResponse = json_encode([
            'success' => false,
            'message' => 'Error encoding response: ' . json_last_error_msg()
        ]);
        echo $errorResponse;
    } else {
        echo $jsonResponse;
    }
    exit();
}

// Function to log data with timestamp
function logData($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= "\nData: " . print_r($data, true);
    }
    error_log($logMessage . "\n", 3, '../logs/php_errors.log');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logData('User not logged in');
    sendJsonResponse(false, 'User not logged in');
}

// Get JSON data from request
$json = file_get_contents('php://input');
logData('Received raw JSON data', $json);

$data = json_decode($json, true);

if (!$data) {
    logData('JSON decode error', json_last_error_msg());
    sendJsonResponse(false, 'Invalid data received: ' . json_last_error_msg());
}

// Log received data
logData('Received parsed data', $data);

try {
    // Start transaction
    logData('Starting database transaction');
    $conn->begin_transaction();

    // Prepare the SQL statement for appointments
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            companyName, Name, email, phone, bookingDate, bookingTime,
            test_location, test_address, special_instructions, number_of_vehicles,
            status, approved_by, total_price, discount_type,
            discount_amount, custom_discount_percentage, custom_discount_amount,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, ?, NOW()
        )
    ");

    if (!$stmt) {
        logData('Prepare failed for appointments', $conn->error);
        throw new Exception("Prepare failed for appointments: " . $conn->error);
    }

    // Handle discount values
    $discount_amount = isset($data['discount_amount']) ? $data['discount_amount'] : 0;
    $custom_discount_percentage = isset($data['custom_discount_percentage']) ? $data['custom_discount_percentage'] : 0;
    $custom_discount_amount = isset($data['custom_discount_amount']) ? $data['custom_discount_amount'] : 0;
    $discount_type = isset($data['discount_type']) ? $data['discount_type'] : 'none';

    // Log the data being bound
    logData('Binding appointment data', [
        'companyName' => $data['companyName'],
        'name' => $data['name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'bookingDate' => $data['bookingDate'],
        'bookingTime' => $data['bookingTime'],
        'test_location' => $data['test_location'],
        'test_address' => $data['test_address'],
        'special_instructions' => $data['special_instructions'],
        'number_of_vehicles' => $data['number_of_vehicles'],
        'status' => 'confirmed',
        'approved_by' => $_SESSION['user_id'],
        'total_price' => $data['total_price'],
        'discount_type' => $discount_type,
        'discount_amount' => $discount_amount,
        'custom_discount_percentage' => $custom_discount_percentage,
        'custom_discount_amount' => $custom_discount_amount
    ]);

    // Bind parameters
    $stmt->bind_param(
        "sssssssssisdsddd",
        $data['companyName'],
        $data['name'],
        $data['email'],
        $data['phone'],
        $data['bookingDate'],
        $data['bookingTime'],
        $data['test_location'],
        $data['test_address'],
        $data['special_instructions'],
        $data['number_of_vehicles'],
        $_SESSION['user_id'],
        $data['total_price'],
        $discount_type,
        $discount_amount,
        $custom_discount_percentage,
        $custom_discount_amount
    );

    // Execute the statement
    if (!$stmt->execute()) {
        logData('Execute failed for appointments', $stmt->error);
        throw new Exception("Execute failed for appointments: " . $stmt->error);
    }

    // Get the ID of the inserted appointment
    $appointment_id = $conn->insert_id;
    logData('Appointment created with ID', $appointment_id);

    // Process each vehicle based on its service type
    foreach ($data['vehicles'] as $vehicle) {
        // Insert into vehicles table for all vehicles
        $vehicle_stmt = $conn->prepare("
            INSERT INTO vehicles (
                appointment_id, vehYear, vehMake, vin, plateNo,
                created_at, clean_truck_check_status, clean_truck_check_next_date, error_code,
                warm_up, smoke_test_notes, smoke_test_status, service_id
            ) VALUES (
                ?, ?, ?, ?, ?, NOW(), ?, NULL, NULL, 0, NULL, 'pending', ?
            )
        ");

        if (!$vehicle_stmt) {
            throw new Exception("Prepare failed for vehicles: " . $conn->error);
        }

        $clean_truck_status = ($vehicle['service_id'] == 1 || $vehicle['service_id'] == 3) ? 'pending' : NULL;

        $vehicle_stmt->bind_param(
            "iissssi",
            $appointment_id,
            $vehicle['vehicle_year'],
            $vehicle['make'],
            $vehicle['vin'],
            $vehicle['license_plate'],
            $clean_truck_status,
            $vehicle['service_id']
        );

        if (!$vehicle_stmt->execute()) {
            throw new Exception("Execute failed for vehicles: " . $vehicle_stmt->error);
        }

        // Get the ID of the inserted vehicle
        $vehicle_id = $conn->insert_id;
        $vehicle_stmt->close();

        // Handle Clean Truck Check specific data
        if ($vehicle['service_id'] == 1 || $vehicle['service_id'] == 3) {
            $clean_truck_stmt = $conn->prepare("
                INSERT INTO clean_truck_checks (
                    appointment_id, service_id, vin_number, plate_number,
                    vehicle_make, vehicle_year, smog_check_completed,
                    smog_check_verified, smog_check_pending_reason,
                    smog_check_status, clean_truck_status, user_id, vehicle_id
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 'no', ?, 'pending', 'pending', ?, ?
                )
            ");

            if (!$clean_truck_stmt) {
                throw new Exception("Prepare failed for clean truck check: " . $conn->error);
            }

            $clean_truck_stmt->bind_param(
                "iisssisssi",
                $appointment_id,
                $vehicle['service_id'],
                $vehicle['vin'],
                $vehicle['license_plate'],
                $vehicle['make'],
                $vehicle['vehicle_year'],
                $vehicle['smog_check_completed'],
                $vehicle['smog_check_pending_details'],
                $data['consultant_id'],
                $vehicle_id
            );

            if (!$clean_truck_stmt->execute()) {
                throw new Exception("Execute failed for clean truck check: " . $clean_truck_stmt->error);
            }

            $clean_truck_stmt->close();
        }

        // Handle Smog Test specific data
        if ($vehicle['service_id'] == 2 || $vehicle['service_id'] == 3) {
            // Get consultant email from accounts table
            $consultant_stmt = $conn->prepare("
                SELECT email 
                FROM accounts 
                WHERE idaccounts = ?
            ");
            
            if (!$consultant_stmt) {
                throw new Exception("Prepare failed for consultant lookup: " . $conn->error);
            }

            $consultant_stmt->bind_param("i", $data['consultant_id']);
            
            if (!$consultant_stmt->execute()) {
                throw new Exception("Execute failed for consultant lookup: " . $consultant_stmt->error);
            }

            $result = $consultant_stmt->get_result();
            $consultant = $result->fetch_assoc();
            $consultant_stmt->close();

            if (!$consultant) {
                throw new Exception("Consultant not found");
            }

            // Insert into calendar_events table with vehid
            $calendar_stmt = $conn->prepare("
                INSERT INTO calendar_events (
                    appointment_id, user_id, title, start_time, end_time,
                    description, created_at, status, vehid
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, NOW(), 'confirmed', ?
                )
            ");

            if (!$calendar_stmt) {
                throw new Exception("Prepare failed for calendar events: " . $conn->error);
            }

            // Prepare calendar event data
            $title = $data['companyName'] . ' Smog test';
            
            // Use vehicle-specific date and time
            $start_time = $vehicle['test_date'] . ' ' . $vehicle['test_time'];
            $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' +30 minutes'));
            
            // Format description based on test location
            $test_address = $vehicle['test_location'] === 'our_location' 
                ? '121 E 11th St, Tracy, CA 95376'
                : ($vehicle['test_address'] ?? '');
                
            $special_instructions = $data['special_instructions'] ?? '';
            
            $description = "Test address: " . $test_address;
            if (!empty($special_instructions)) {
                $description .= "\nInstruction: " . $special_instructions;
            }

            $calendar_stmt->bind_param(
                "isssssi",
                $appointment_id,
                $consultant['email'],
                $title,
                $start_time,
                $end_time,
                $description,
                $vehicle_id
            );

            if (!$calendar_stmt->execute()) {
                throw new Exception("Execute failed for calendar events: " . $calendar_stmt->error);
            }

            $calendar_stmt->close();
        }
    }

    // Commit transaction
    logData('Committing transaction');
    $conn->commit();
    logData('Transaction committed successfully');

    // Return success response
    sendJsonResponse(true, 'Appointment created successfully', [
        'appointment_id' => $appointment_id,
        'redirect_url' => 'view_quotation.php?id=' . $appointment_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    logData('Error occurred, rolling back transaction', [
        'error_message' => $e->getMessage(),
        'stack_trace' => $e->getTraceAsString()
    ]);
    $conn->rollback();
    
    // Return error response
    sendJsonResponse(false, 'Error creating appointment: ' . $e->getMessage());
}

// Close the statement and connection
if (isset($stmt)) {
    $stmt->close();
}
if (isset($vehicle_stmt)) {
    $vehicle_stmt->close();
}
if (isset($clean_truck_stmt)) {
    $clean_truck_stmt->close();
}
if (isset($calendar_stmt)) {
    $calendar_stmt->close();
}
$conn->close();
logData('Database connection closed');
?> 