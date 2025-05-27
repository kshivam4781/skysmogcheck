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

if (!$appointment_id) {
    echo json_encode(['error' => 'No appointment ID provided']);
    exit();
}

try {
    // Prepare the query to get fresh data
    $query = "
        SELECT 
            ce.*,
            a.companyName,
            a.Name as contactName,
            a.email,
            a.phone,
            a.service_id,
            a.created_at as appointment_date,
            c.firstName as consultant_first_name,
            c.lastName as consultant_last_name,
            c.email as consultant_email,
            c.phone as consultant_phone,
            v.vin_number,
            v.plate_number,
            v.vehicle_make,
            v.vehicle_year,
            v.smoke_test_status,
            v.smoke_test_notes
        FROM calendar_events ce
        JOIN appointments a ON ce.appointment_id = a.id
        LEFT JOIN accounts c ON ce.user_id = c.idaccounts
        LEFT JOIN vehicles v ON ce.appointment_id = v.appointment_id
        WHERE ce.appointment_id = ?
        AND ce.title LIKE '%Smoke Check%'
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
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