<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/get_vehicles_error.log');
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// If appointment_id and vehicle_id are provided, return detailed info for that vehicle and appointment
if (isset($_GET['appointment_id']) && isset($_GET['vehicle_id'])) {
    $appointment_id = $_GET['appointment_id'];
    $vehicle_id = $_GET['vehicle_id'];

    // Validate input types
    if (!is_numeric($appointment_id) || !is_numeric($vehicle_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid appointment_id or vehicle_id']);
        exit();
    }
    $appointment_id = (int)$appointment_id;
    $vehicle_id = (int)$vehicle_id;

    $query = "
        SELECT 
            ce.appointment_id,
            ce.start_time,
            ce.status as event_status,
            ce.description as location_instructions,
            a.companyName,
            a.Name as contact_name,
            a.email as contact_email,
            a.phone as contact_phone,
            a.special_instructions,
            a.total_price,
            a.discount_type,
            a.discount_percentage,
            a.discount_amount,
            v.id as vehicle_id,
            v.vehYear,
            v.vehMake,
            v.vin,
            v.plateNo,
            v.smoke_test_status,
            v.smoke_test_notes,
            v.error_code,
            v.warm_up,
            v.attachment_path,
            acc.firstName as consultant_first_name,
            acc.lastName as consultant_last_name,
            acc.email as consultant_email
        FROM calendar_events ce
        JOIN appointments a ON ce.appointment_id = a.id
        LEFT JOIN vehicles v ON ce.vehid = v.id
        LEFT JOIN accounts acc ON ce.user_id = acc.email
        WHERE ce.appointment_id = ? AND ce.vehid = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database error (prepare)']);
        exit();
    }
    $stmt->bind_param('ii', $appointment_id, $vehicle_id);
    if (!$stmt->execute()) {
        error_log('MySQL error: ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database error (execute)']);
        exit();
    }
    $result = $stmt->get_result();
    $vehicleData = $result->fetch_assoc();

    if (!$vehicleData) {
        http_response_code(404);
        echo json_encode(['error' => 'Vehicle not found for this appointment']);
        exit();
    }

    // Format start_time
    $vehicleData['start_time_formatted'] = isset($vehicleData['start_time']) ? date('M d, Y h:i A', strtotime($vehicleData['start_time'])) : '';
    $vehicleData['consultant_name'] = trim(($vehicleData['consultant_first_name'] ?? '') . ' ' . ($vehicleData['consultant_last_name'] ?? ''));

    header('Content-Type: application/json');
    echo json_encode($vehicleData);
    exit();
}

// Otherwise, fall back to company-based vehicle list
if (!isset($_GET['company'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Company name is required']);
    exit();
}

$companyName = $_GET['company'];

// Get vehicle information
$vehicleQuery = "SELECT DISTINCT 
    cv.fleet_number,
    cv.vin,
    cv.plate_no,
    cv.year,
    cv.make,
    cv.model,
    cv.smog_due_date,
    cv.clean_truck_due_date,
    cv.status,
    COALESCE(
        (SELECT MAX(ce.start_time)
        FROM calendar_events ce
        WHERE ce.vehid = cv.id
        AND ce.status = 'completed'
        LIMIT 1),
        (SELECT MAX(ctc.clean_truck_completed_date)
        FROM clean_truck_checks ctc
        WHERE ctc.vin_number COLLATE utf8mb4_unicode_ci = cv.vin COLLATE utf8mb4_unicode_ci
        AND ctc.clean_truck_status = 'completed'
        LIMIT 1)
    ) as last_appointment
FROM clientvehicles cv
WHERE cv.company_id IN (
    SELECT id FROM clients WHERE company_name COLLATE utf8mb4_unicode_ci = ?
)
AND cv.status = 'active'
ORDER BY cv.fleet_number";

$stmt = $conn->prepare($vehicleQuery);
if (!$stmt) {
    error_log('Prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Database error (prepare)']);
    exit();
}
$stmt->bind_param("s", $companyName);
if (!$stmt->execute()) {
    error_log('MySQL error: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'Database error (execute)']);
    exit();
}
$result = $stmt->get_result();
$vehicles = $result->fetch_all(MYSQLI_ASSOC);

// Return the vehicle data as JSON
header('Content-Type: application/json');
echo json_encode($vehicles);
?> 