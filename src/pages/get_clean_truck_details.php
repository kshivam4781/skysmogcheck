<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get appointment ID from request
$appointment_id = isset($_GET['id']) ? $_GET['id'] : null;
$vehicle_id = isset($_GET['vehicle_id']) ? $_GET['vehicle_id'] : null;

if (!$appointment_id) {
    echo json_encode(['error' => 'No appointment ID provided']);
    exit();
}

try {
    // Prepare the query to get fresh data
    $query = "
        SELECT 
            ctc.*,
            a.companyName,
            a.Name as contactName,
            a.email,
            a.phone,
            v.service_id,
            v.id as vehicle_id,
            v.vin as vin_number,
            v.plateNo as plate_number,
            v.vehMake as vehicle_make,
            v.vehYear as vehicle_year,
            a.created_at as appointment_date,
            c.firstName as consultant_first_name,
            c.lastName as consultant_last_name,
            c.email as consultant_email,
            c.phone as consultant_phone
        FROM clean_truck_checks ctc
        JOIN appointments a ON ctc.appointment_id = a.id
        LEFT JOIN accounts c ON ctc.user_id = c.idaccounts
        LEFT JOIN vehicles v ON ctc.vehicle_id = v.id
        WHERE ctc.appointment_id = ? 
        AND v.id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $appointment_id, $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        // Add debugging
        error_log("Vehicle ID from query: " . ($data['vehicle_id'] ?? 'null'));
        error_log("Service ID from query: " . ($data['service_id'] ?? 'null'));
        error_log("Raw data: " . print_r($data, true));
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Record not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?> 