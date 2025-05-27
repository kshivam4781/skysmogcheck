<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in and has consultant access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    $_SESSION['error_message'] = "Access restricted. Please contact your administrator.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Prepare client data
        $companyName = $_POST['companyName'];
        $contactName = $_POST['contactName'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $dotNumber = $_POST['dotNumber'];
        $companyAddress = $_POST['companyAddress'];
        $clientType = $_POST['clientType'];
        $description = $_POST['description'] ?? null;
        $createdBy = $_SESSION['email'] ?? 'system';
        $status = 'active'; // Default status for new clients

        // Insert client information
        $stmt = $conn->prepare("INSERT INTO clients (
            company_name, 
            contact_person_name, 
            email, 
            phone, 
            dot_number, 
            company_address, 
            status, 
            description, 
            created_by, 
            type, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->bind_param(
            "ssssssssss",
            $companyName,
            $contactName,
            $email,
            $phone,
            $dotNumber,
            $companyAddress,
            $status,
            $description,
            $createdBy,
            $clientType
        );
        
        $stmt->execute();
        $clientId = $conn->insert_id;

        // Debug log
        error_log("Client ID: " . $clientId);
        error_log("Vehicles data: " . print_r($_POST['vehicles'], true));

        // Insert vehicle information
        if (isset($_POST['vehicles']) && is_array($_POST['vehicles'])) {
            $vehicleStmt = $conn->prepare("INSERT INTO clientvehicles (
                company_id,
                fleet_number,
                vin,
                plate_no,
                year,
                make,
                model,
                smog_due_date,
                clean_truck_due_date,
                status,
                added_by,
                notes,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())");
            
            foreach ($_POST['vehicles'] as $vehicle) {
                // Debug log for each vehicle
                error_log("Processing vehicle: " . print_r($vehicle, true));

                $fleetNumber = $vehicle['fleet_number'];
                $vin = $vehicle['vin'];
                $plateNo = $vehicle['plate_no'];
                $year = $vehicle['year'];
                $make = $vehicle['make'];
                $model = $vehicle['model'];
                $smogDueDate = !empty($vehicle['smog_due_date']) ? $vehicle['smog_due_date'] : null;
                $cleanTruckDueDate = !empty($vehicle['clean_truck_due_date']) ? $vehicle['clean_truck_due_date'] : null;
                $notes = $vehicle['notes'] ?? null;

                // Debug log for processed values
                error_log("Processed values - Make: " . $make . ", Model: " . $model);
                
                $vehicleStmt->bind_param(
                    "issssisssss",
                    $clientId,
                    $fleetNumber,
                    $vin,
                    $plateNo,
                    $year,
                    $make,
                    $model,
                    $smogDueDate,
                    $cleanTruckDueDate,
                    $createdBy,
                    $notes
                );

                if (!$vehicleStmt->execute()) {
                    error_log("Error executing vehicle insert: " . $vehicleStmt->error);
                    throw new Exception("Error inserting vehicle: " . $vehicleStmt->error);
                }
            }
        }

        // Commit transaction
        $conn->commit();

        $_SESSION['success_message'] = "New client added successfully!";
        header("Location: all_clients.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error in process_new_client.php: " . $e->getMessage());
        $_SESSION['error_message'] = "Error adding new client: " . $e->getMessage();
        header("Location: all_clients.php");
        exit();
    }
} else {
    header("Location: all_clients.php");
    exit();
}
?>