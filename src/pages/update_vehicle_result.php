<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

// Get POST data
$vehicle_id = $_POST['vehicle_id'] ?? null;
$result = $_POST['result'] ?? null;
$notes = $_POST['notes'] ?? null;
$error_code = $_POST['error_code'] ?? null;
$warm_up = $_POST['warm_up'] ?? null;
$services = json_decode($_POST['services'] ?? '[]', true);
$service_statuses = json_decode($_POST['service_statuses'] ?? '[]', true);
$service_notes = json_decode($_POST['service_notes'] ?? '[]', true);

// Validate required fields
if (!$vehicle_id) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update vehicle details
    $stmt = $conn->prepare("
        UPDATE vehicles 
        SET result = ?, 
            smoke_test_notes = ?, 
            error_code = ?, 
            warm_up = ?,
            smoke_test_status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $result, $notes, $error_code, $warm_up, $result, $vehicle_id);
    $stmt->execute();

    // Update service details
    if (!empty($services)) {
        // First, delete existing service records
        $stmt = $conn->prepare("DELETE FROM clean_truck_checks WHERE vehicle_id = ?");
        $stmt->bind_param("i", $vehicle_id);
        $stmt->execute();

        // Then insert new service records
        $stmt = $conn->prepare("
            INSERT INTO clean_truck_checks 
            (vehicle_id, service_type, clean_truck_status, notes, user_id) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($services as $index => $service) {
            $status = $service_statuses[$index] ?? 'pending';
            $note = $service_notes[$index] ?? '';
            $stmt->bind_param("issss", $vehicle_id, $service, $status, $note, $_SESSION['user_id']);
            $stmt->execute();
        }
    }

    // Handle file upload if present
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only PDF, JPG, and PNG files are allowed.');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds the maximum limit of 5MB.');
        }

        $upload_dir = '../uploads/vehicle_attachments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('vehicle_') . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Update vehicle record with attachment path
            $stmt = $conn->prepare("UPDATE vehicles SET attachment = ? WHERE id = ?");
            $stmt->bind_param("si", $file_name, $vehicle_id);
            $stmt->execute();
        }
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 