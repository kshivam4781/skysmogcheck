<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get KPI data
// Total Clean Truck Checks
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM clean_truck_checks
");
$stmt->execute();
$total_checks = $stmt->get_result()->fetch_assoc()['total'];

// Total Pending Clean Truck Checks
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending 
    FROM clean_truck_checks 
    WHERE clean_truck_status = 'pending'
");
$stmt->execute();
$pending_checks = $stmt->get_result()->fetch_assoc()['pending'];

// Total Pending for Work (clean truck pending)
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_work 
    FROM clean_truck_checks 
    WHERE clean_truck_status = 'pending'
");
$stmt->execute();
$pending_work = $stmt->get_result()->fetch_assoc()['pending_work'];

// Critical Tasks (confirmed smoke check but pending clean truck for more than 3 days)
$stmt = $conn->prepare("
    SELECT COUNT(*) as critical_tasks 
    FROM clean_truck_checks 
    WHERE (smog_check_status = 'confirmed' OR smog_check_verified = 'yes')
    AND clean_truck_status = 'pending'
    AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
");
$stmt->execute();
$critical_tasks = $stmt->get_result()->fetch_assoc()['critical_tasks'];

// Get critical tasks details for modal
$stmt = $conn->prepare("
    SELECT 
        ctc.*,
        a.companyName,
        a.Name as clientName,
        a.email,
        a.phone,
        a.created_at as appointment_date
    FROM clean_truck_checks ctc
    JOIN appointments a ON ctc.appointment_id = a.id
    WHERE (ctc.smog_check_status = 'confirmed' OR ctc.smog_check_verified = 'yes')
    AND ctc.clean_truck_status = 'pending'
    AND ctc.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
    ORDER BY ctc.created_at ASC
");
$stmt->execute();
$critical_tasks_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build the query for the list
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
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " WHERE (
        a.companyName LIKE ? OR 
        ctc.vin_number LIKE ? OR 
        ctc.plate_number LIKE ? OR
        v.vin LIKE ? OR
        v.plateNo LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssss";
}

if (!empty($status)) {
    $query .= empty($search) ? " WHERE " : " AND ";
    $query .= "ctc.clean_truck_status = ?";
    $params[] = $status;
    $types .= "s";
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY a.created_at ASC";
        break;
    case 'company':
        $query .= " ORDER BY a.companyName ASC";
        break;
    case 'status':
        $query .= " ORDER BY ctc.clean_truck_status ASC";
        break;
    default: // newest
        $query .= " ORDER BY a.created_at DESC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$checks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug the results
error_log("Number of records found: " . count($checks));
foreach ($checks as $check) {
    error_log("Record ID: " . $check['id'] . ", Vehicle ID: " . ($check['vehicle_id'] ?? 'null'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Truck Checks - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
            margin-bottom: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar.collapsed {
            width: 0;
            padding: 20px 0;
        }
        .sidebar-toggle {
            position: fixed;
            left: 250px;
            top: 20px;
            background: #343a40;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1001;
        }
        .sidebar-toggle.collapsed {
            left: 0;
        }
        .sidebar-toggle i {
            transition: transform 0.3s;
        }
        .sidebar-toggle.collapsed i {
            transform: rotate(180deg);
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .sidebar-header {
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
        }
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s;
            margin-bottom: 5px;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .sidebar-menu a.active {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }
        .sidebar-menu a.active i {
            color: white;
        }
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: rgba(255,255,255,0.7);
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .site-footer {
            position: fixed;
            bottom: 0;
            left: 250px;
            right: 0;
            background-color: #f8f9fa;
            padding: 10px 0;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
            transition: all 0.3s;
        }
        .site-footer.expanded {
            left: 0;
        }
        .footer-bottom {
            text-align: center;
        }
        .copyright p {
            margin: 0;
            color: #6c757d;
        }
        .quotation-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .quotation-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .quotation-title {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .quotation-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .kpi-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            background: white;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .kpi-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .kpi-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .kpi-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .check-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .check-item {
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
            transition: background-color 0.3s ease;
        }
        .check-item:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .btn-primary {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-primary:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        .form-control:focus, .form-select:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23,162,184,0.25);
        }
        h2 {
            color: #2c3e50;
            font-weight: 600;
        }
        .text-muted {
            color: #6c757d !important;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.collapsed {
                transform: translateX(0);
                width: 250px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                left: 0;
            }
            .site-footer {
                left: 0;
            }
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            display: none;
        }
        .overlay.active {
            display: block;
        }
        .details-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: #f8f9fa;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
        }
        .details-sidebar.active {
            right: 0;
        }
        .details-header {
            padding: 20px;
            background: #2c3e50;
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .details-header h5 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .details-actions {
            display: flex;
            gap: 8px;
        }
        .details-actions button {
            background: none;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .details-actions button:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.3);
        }
        .details-actions button i {
            font-size: 0.9em;
        }
        .details-actions .btn-edit {
            color: #17a2b8;
            border-color: #17a2b8;
        }
        .details-actions .btn-edit:hover {
            background: #17a2b8;
            color: white;
        }
        .details-actions .btn-cancel {
            color: #dc3545;
            border-color: #dc3545;
        }
        .details-actions .btn-cancel:hover {
            background: #dc3545;
            color: white;
        }
        .details-actions .btn-expand {
            color: #6c757d;
            border-color: #6c757d;
        }
        .details-actions .btn-expand:hover {
            background: #6c757d;
            color: white;
        }
        .details-actions .btn-close {
            color: #6c757d;
            border-color: #6c757d;
        }
        .details-actions .btn-close:hover {
            background: #6c757d;
            color: white;
        }
        .details-content {
            padding: 20px;
        }
        .details-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .details-section h6 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
        }
        .details-item {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
        }
        .details-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 140px;
            padding-right: 10px;
        }
        .details-value {
            color: #6c757d;
            flex: 1;
        }
        .check-item {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .check-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .close-details {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 5px;
            line-height: 1;
            transition: all 0.3s ease;
        }
        .close-details:hover {
            color: #17a2b8;
            transform: rotate(90deg);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .contact-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        .contact-info i {
            color: #17a2b8;
            width: 20px;
        }
        .vehicle-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
        .service-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .service-status i {
            color: #17a2b8;
            width: 20px;
        }
        .service-status .status-value {
            font-weight: 500;
        }
        .service-status .status-value.verified {
            color: #28a745;
        }
        .service-status .status-value.not-verified {
            color: #dc3545;
        }
        .update-services-btn {
            margin-top: 15px;
            width: 100%;
            padding: 8px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .update-services-btn:hover {
            background-color: #138496;
        }
        .client-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .client-info h6 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .client-info p {
            margin: 0;
            color: #6c757d;
        }
        .service-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .service-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .service-checkbox label {
            margin: 0;
            cursor: pointer;
        }
        .appointment-date {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #17a2b8;
            font-weight: 500;
        }
        .appointment-date i {
            font-size: 1.2em;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        .action-btn {
            flex: 1;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .edit-btn {
            background-color: #17a2b8;
        }
        .edit-btn:hover {
            background-color: #138496;
        }
        .cancel-btn {
            background-color: #dc3545;
        }
        .cancel-btn:hover {
            background-color: #c82333;
        }
        .fullscreen-btn {
            position: absolute;
            top: 15px;
            right: 50px;
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s ease;
        }
        .fullscreen-btn:hover {
            color: #17a2b8;
            transform: scale(1.1);
        }
        .details-sidebar.fullscreen {
            width: 100%;
            right: 0;
        }
        .details-sidebar.fullscreen .details-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
        }
        .details-sidebar.fullscreen .details-section {
            max-width: 800px;
            margin: 0 auto 20px;
        }
        .details-sidebar.fullscreen .details-header {
            padding: 20px 40px;
        }
        .details-sidebar.fullscreen .close-details,
        .details-sidebar.fullscreen .fullscreen-btn {
            top: 20px;
        }
        .details-sidebar.fullscreen .fullscreen-btn {
            right: 60px;
        }
        .service-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            z-index: 1060;
            width: 90%;
            max-width: 500px;
            display: none;
        }
        .service-popup.active {
            display: block;
        }
        .service-popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .service-popup-header h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }
        .service-popup-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .service-popup-close:hover {
            color: #dc3545;
        }
        .service-popup-content {
            margin-bottom: 20px;
        }
        .service-popup-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        .service-checkbox.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .completion-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            z-index: 1070;
            width: 90%;
            max-width: 500px;
            display: none;
        }
        .completion-modal.active {
            display: block;
        }
        .file-upload {
            margin: 15px 0;
            padding: 20px;
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .file-upload:hover {
            border-color: #17a2b8;
        }
        .file-upload i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .file-upload p {
            margin: 0;
            color: #6c757d;
        }
        .file-name {
            margin-top: 10px;
            font-weight: 500;
            color: #2c3e50;
        }
        .instructions-textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin: 10px 0;
            resize: vertical;
        }
        .send-email-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .send-email-btn:hover {
            background-color: #218838;
        }
        .appointment-number {
            display: inline-block;
            background-color: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        /* Email Animation Styles */
        .email-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .email-animation.active {
            display: flex;
        }

        .email-container {
            position: relative;
            width: 300px;
            height: 200px;
        }

        .email-icon {
            position: absolute;
            width: 50px;
            height: 50px;
            background: #17a2b8;
            border-radius: 10px;
            animation: fly 2s ease-in-out infinite;
        }

        .email-icon::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 30px;
            height: 20px;
            background: white;
            border-radius: 3px;
        }

        .cloud {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.8;
        }

        .cloud-1 {
            width: 60px;
            height: 20px;
            top: 20px;
            left: 50px;
        }

        .cloud-2 {
            width: 40px;
            height: 15px;
            top: 50px;
            left: 150px;
        }

        .cloud-3 {
            width: 50px;
            height: 18px;
            top: 30px;
            left: 250px;
        }

        @keyframes fly {
            0% {
                transform: translateY(150px) translateX(0);
                opacity: 1;
            }
            50% {
                transform: translateY(50px) translateX(100px);
                opacity: 0.8;
            }
            100% {
                transform: translateY(-50px) translateX(200px);
                opacity: 0;
            }
        }

        /* Success Popup Styles */
        .success-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            text-align: center;
            display: none;
            z-index: 10000;
        }

        .success-popup.active {
            display: block;
        }

        .success-icon {
            color: #28a745;
            font-size: 48px;
            margin-bottom: 15px;
        }

        .success-message {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }

        .success-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .success-button:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Dashboard</h3>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="admin_welcome.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_welcome.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="calendar_events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar_events.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> Calendar
                    </a>
                </li>
                <li>
                    <a href="clean_truck_checks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clean_truck_checks.php' ? 'active' : ''; ?>">
                        <i class="fas fa-truck"></i> Clean Truck Check
                    </a>
                </li>
                <li>
                    <a href="smoke_checks.php">
                        <i class="fas fa-smoking"></i> Smoke Check
                    </a>
                </li>
                <li>
                    <a href="all_appointments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'all_appointments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All Appointments
                    </a>
                </li>
                <li>
                    <a href="all_clients.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'all_clients.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> All Clients
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="inquiries.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'inquiries.php' ? 'active' : ''; ?>">
                            <i class="fas fa-question-circle"></i> Inquiries
                        </a>
                    </li>
                    <li>
                        <a href="create_quotation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create_quotation.php' ? 'active' : ''; ?>" style="background-color: #17a2b8; color: white;">
                            <i class="fas fa-file-invoice-dollar"></i> Create Quotation
                        </a>
                    </li>
                    <li>
                        <a href="reminder.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reminder.php' ? 'active' : ''; ?>">
                            <i class="fas fa-bell"></i> Reminders
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="container-fluid">
                <h2 class="mb-4">Clean Truck Checks</h2>

                <!-- KPI Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-truck kpi-icon text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="kpi-number"><?php echo $total_checks; ?></div>
                                        <div class="kpi-label">Total Clean Truck Checks</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-clock kpi-icon text-warning"></i>
                                    </div>
                                    <div>
                                        <div class="kpi-number"><?php echo $pending_checks; ?></div>
                                        <div class="kpi-label">Pending Clean Truck Checks</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-tools kpi-icon text-info"></i>
                                    </div>
                                    <div>
                                        <div class="kpi-number"><?php echo $pending_work; ?></div>
                                        <div class="kpi-label">Pending for Work</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card kpi-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#criticalTasksModal">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-exclamation-triangle kpi-icon text-danger"></i>
                                    </div>
                                    <div>
                                        <div class="kpi-number"><?php echo $critical_tasks; ?></div>
                                        <div class="kpi-label">Critical Tasks</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search company, VIN, or plate..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="company" <?php echo $sort === 'company' ? 'selected' : ''; ?>>By Company</option>
                                <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>By Status</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>

                <!-- Clean Truck Checks List -->
                <div class="check-list">
                    <?php foreach ($checks as $check): ?>
                        <div class="check-item" onclick="showDetails(<?php echo htmlspecialchars(json_encode($check)); ?>)">
                            <div class="row">
                                <div class="col-md-1">
                                    <span class="appointment-number">#<?php echo htmlspecialchars($check['appointment_id']); ?></span>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($check['companyName']); ?></h6>
                                    <small class="text-muted">
                                        <?php 
                                        $service_text = '';
                                        switch($check['service_id']) {
                                            case 1:
                                                $service_text = 'Clean Truck Check';
                                                break;
                                            case 2:
                                                $service_text = 'Smog Test';
                                                break;
                                            case 3:
                                                $service_text = 'Smog Test and Clean Truck Check';
                                                break;
                                            default:
                                                $service_text = 'Unknown Service';
                                        }
                                        echo $service_text;
                                        ?>
                                    </small>
                                    <?php if (isset($check['vehicle_id']) && $check['vehicle_id']): ?>
                                        <br>
                                        <small class="text-muted">Vehicle ID: <?php echo htmlspecialchars($check['vehicle_id']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-1">VIN: <?php echo htmlspecialchars($check['vin_number']); ?></p>
                                    <p class="mb-0">Plate: <?php echo htmlspecialchars($check['plate_number']); ?></p>
                                </div>
                                <div class="col-md-2">
                                    <p class="mb-1">Make: <?php echo htmlspecialchars($check['vehicle_make']); ?></p>
                                    <p class="mb-0">Year: <?php echo htmlspecialchars($check['vehicle_year']); ?></p>
                                </div>
                                <div class="col-md-3 text-end">
                                    <span class="status-badge status-<?php echo strtolower($check['clean_truck_status']); ?>">
                                        <?php echo ucfirst($check['clean_truck_status']); ?>
                                    </span>
                                    <p class="mb-0 mt-2">
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($check['appointment_date'])); ?>
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Critical Tasks Modal -->
                <div class="modal fade" id="criticalTasksModal" tabindex="-1" aria-labelledby="criticalTasksModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="criticalTasksModalLabel">Critical Tasks Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (empty($critical_tasks_details)): ?>
                                    <p class="text-center">No critical tasks found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Client Name</th>
                                                    <th>Company</th>
                                                    <th>Contact</th>
                                                    <th>Vehicle</th>
                                                    <th>Days Pending</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($critical_tasks_details as $task): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($task['clientName']); ?></td>
                                                        <td><?php echo htmlspecialchars($task['companyName']); ?></td>
                                                        <td>
                                                            <div>Email: <?php echo htmlspecialchars($task['email']); ?></div>
                                                            <div>Phone: <?php echo htmlspecialchars($task['phone']); ?></div>
                                                        </td>
                                                        <td>
                                                            <div>VIN: <?php echo htmlspecialchars($task['vin_number']); ?></div>
                                                            <div>Plate: <?php echo htmlspecialchars($task['plate_number']); ?></div>
                                                            <div><?php echo htmlspecialchars($task['vehicle_make'] . ' ' . $task['vehicle_year']); ?></div>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $days_pending = ceil((time() - strtotime($task['created_at'])) / (60 * 60 * 24));
                                                            echo $days_pending . ' days';
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-pending">
                                                                <?php echo ucfirst($task['clean_truck_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add overlay div before the details sidebar -->
                <div class="overlay" id="sidebarOverlay"></div>

                <!-- Details Sidebar -->
                <div class="details-sidebar" id="detailsSidebar">
                    <div class="details-header">
                        <h5>Record Details</h5>
                        <div class="details-actions">
                            <button class="btn-edit" onclick="editService()">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-cancel" onclick="cancelService()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button class="btn-expand" onclick="toggleFullscreen()">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="btn-close" onclick="hideDetails()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="details-content">
                        <div class="details-section">
                            <h6><i class="fas fa-calendar-alt me-2"></i>Appointment Information</h6>
                            <div class="details-item">
                                <span class="details-label">Appointment ID:</span>
                                <span class="details-value" id="appointmentId"></span>
                            </div>
                            <div class="appointment-date">
                                <i class="fas fa-clock"></i>
                                <span id="appointmentDate"></span>
                            </div>
                        </div>

                        <div class="details-section">
                            <h6><i class="fas fa-building me-2"></i>Company Information</h6>
                            <div class="details-item">
                                <span class="details-label">Company Name:</span>
                                <span class="details-value" id="companyName"></span>
                            </div>
                            <div class="details-item">
                                <span class="details-label">Contact Person:</span>
                                <span class="details-value" id="contactName"></span>
                            </div>
                            <div class="contact-info">
                                <i class="fas fa-envelope"></i>
                                <span id="contactEmail"></span>
                            </div>
                            <div class="contact-info">
                                <i class="fas fa-phone"></i>
                                <span id="contactPhone"></span>
                            </div>
                        </div>

                        <div class="details-section">
                            <h6><i class="fas fa-user-tie me-2"></i>Consultant Information</h6>
                            <div class="details-item">
                                <span class="details-label">Name:</span>
                                <span class="details-value" id="consultantName"></span>
                            </div>
                            <div class="contact-info">
                                <i class="fas fa-envelope"></i>
                                <span id="consultantEmail"></span>
                            </div>
                            <div class="contact-info">
                                <i class="fas fa-phone"></i>
                                <span id="consultantPhone"></span>
                            </div>
                        </div>

                        <div class="details-section">
                            <h6><i class="fas fa-truck me-2"></i>Vehicle Information</h6>
                            <div class="vehicle-info">
                                <div class="details-item">
                                    <span class="details-label">VIN:</span>
                                    <span class="details-value" id="vinNumber"></span>
                                </div>
                                <div class="details-item">
                                    <span class="details-label">Plate Number:</span>
                                    <span class="details-value" id="plateNumber"></span>
                                </div>
                                <div class="details-item">
                                    <span class="details-label">Make:</span>
                                    <span class="details-value" id="vehicleMake"></span>
                                </div>
                                <div class="details-item">
                                    <span class="details-label">Year:</span>
                                    <span class="details-value" id="vehicleYear"></span>
                                </div>
                                <div class="details-item" id="vehicleIdContainer" style="display: none;">
                                    <span class="details-label">Vehicle ID:</span>
                                    <span class="details-value" id="vehicleId"></span>
                                </div>
                            </div>
                        </div>

                        <div class="details-section">
                            <h6><i class="fas fa-tasks me-2"></i>Service Status</h6>
                            <div class="service-status">
                                <i class="fas fa-check-circle"></i>
                                <span class="details-label">Smog Check:</span>
                                <span class="status-value" id="smokeCheckCompleted"></span>
                            </div>
                            <div class="service-status">
                                <i class="fas fa-check-double"></i>
                                <span class="details-label">Smog Check Verified:</span>
                                <span class="status-value" id="smogCheckVerified"></span>
                            </div>
                            <div class="service-status">
                                <i class="fas fa-truck-loading"></i>
                                <span class="details-label">Clean Truck:</span>
                                <span class="status-value" id="cleanTruckStatus"></span>
                            </div>
                            <button class="update-services-btn" onclick="openUpdateServicesModal()">
                                <i class="fas fa-edit"></i> Update Services
                            </button>
                        </div>

                        <!-- Replace the modal with popup -->
                        <div class="service-popup" id="servicePopup">
                            <div class="service-popup-header">
                                <h5>Update Services</h5>
                                <button class="service-popup-close" onclick="closeServicePopup()">&times;</button>
                            </div>
                            <div class="service-popup-content">
                                <div class="client-info">
                                    <h6>Client Information</h6>
                                    <p><strong>Client Name:</strong> <span id="popupClientName"></span></p>
                                    <p><strong>Vehicle Plate:</strong> <span id="popupVehiclePlate"></span></p>
                                </div>
                                <div class="service-checkbox">
                                    <input type="checkbox" id="popupSmogCheck" class="form-check-input">
                                    <label for="popupSmogCheck">Smog Check Completed</label>
                                </div>
                                <div class="service-checkbox" id="smogVerifiedCheckbox">
                                    <input type="checkbox" id="popupSmogVerified" class="form-check-input">
                                    <label for="popupSmogVerified">Smog Check Verified</label>
                                </div>
                                <div class="service-checkbox" id="cleanTruckCheckbox">
                                    <input type="checkbox" id="popupCleanTruck" class="form-check-input">
                                    <label for="popupCleanTruck">Clean Truck Check Completed</label>
                                </div>
                            </div>
                            <div class="service-popup-footer">
                                <button class="btn btn-secondary" onclick="closeServicePopup()">Cancel</button>
                                <button class="btn btn-primary" onclick="saveServiceUpdates()">Save Changes</button>
                            </div>
                        </div>

                        <!-- Completion Modal -->
                        <div class="completion-modal" id="completionModal">
                            <div class="service-popup-header">
                                <h5>Complete Service</h5>
                                <button class="service-popup-close" onclick="closeCompletionModal()">&times;</button>
                            </div>
                            <div class="service-popup-content">
                                <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload completion document</p>
                                    <span class="file-name" id="fileName">No file chosen</span>
                                    <input type="file" id="fileInput" style="display: none" onchange="updateFileName(this)">
                                </div>
                                <textarea class="instructions-textarea" id="completionInstructions" placeholder="Enter any instructions or notes for the client..."></textarea>
                            </div>
                            <div class="service-popup-footer">
                                <button class="btn btn-secondary" onclick="closeCompletionModal()">Cancel</button>
                                <button class="send-email-btn" onclick="sendCompletionEmail()">
                                    <i class="fas fa-paper-plane"></i> Send Email to Client
                                </button>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="action-btn edit-btn" onclick="editService()">
                                <i class="fas fa-edit"></i> Edit Service
                            </button>
                            <button class="action-btn cancel-btn" onclick="cancelService()">
                                <i class="fas fa-times"></i> Cancel Service
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mainContent = document.getElementById('mainContent');

            // Check for saved state in localStorage
            const isCollapsed = localStorage.getItem('sidebarState') === 'collapsed';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }

            sidebarToggle.addEventListener('click', function() {
                const isCurrentlyCollapsed = sidebar.classList.contains('collapsed');
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Save state to localStorage
                localStorage.setItem('sidebarState', isCurrentlyCollapsed ? 'expanded' : 'collapsed');
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else {
                    const isCollapsed = localStorage.getItem('sidebarState') === 'collapsed';
                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                        sidebarToggle.classList.add('collapsed');
                    } else {
                        sidebar.classList.remove('collapsed');
                        mainContent.classList.remove('expanded');
                        sidebarToggle.classList.remove('collapsed');
                    }
                }
            });
        });

        function showDetails(data) {
            // Fetch fresh data from the server
            fetch(`get_clean_truck_details.php?id=${data.appointment_id}&vehicle_id=${data.vehicle_id}`)
                .then(response => response.json())
                .then(freshData => {
                    // Add debugging
                    console.log('Received data:', freshData);
                    console.log('Vehicle ID:', freshData.vehicle_id);
                    console.log('Service ID:', freshData.service_id);

                    // Format date
                    const appointmentDate = new Date(freshData.appointment_date).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    // Update details in sidebar
                    document.getElementById('appointmentId').textContent = freshData.appointment_id;
                    document.getElementById('appointmentDate').textContent = appointmentDate;
                    document.getElementById('companyName').textContent = freshData.companyName;
                    document.getElementById('contactName').textContent = freshData.contactName;
                    document.getElementById('contactEmail').textContent = freshData.email;
                    document.getElementById('contactPhone').textContent = freshData.phone;
                    document.getElementById('consultantName').textContent = 
                        freshData.consultant_first_name ? `${freshData.consultant_first_name} ${freshData.consultant_last_name}` : 'Not assigned';
                    document.getElementById('consultantEmail').textContent = freshData.consultant_email || 'Not assigned';
                    document.getElementById('consultantPhone').textContent = freshData.consultant_phone || 'Not available';
                    document.getElementById('vinNumber').textContent = freshData.vin_number;
                    document.getElementById('plateNumber').textContent = freshData.plate_number;
                    document.getElementById('vehicleMake').textContent = freshData.vehicle_make;
                    document.getElementById('vehicleYear').textContent = freshData.vehicle_year;
                    
                    // Show/hide vehicle ID based on service_id
                    const vehicleIdContainer = document.getElementById('vehicleIdContainer');
                    console.log('Service ID check:', freshData.service_id);
                    console.log('Vehicle ID value:', freshData.vehicle_id);
                    
                    // Always show vehicle ID if it exists
                    if (freshData.vehicle_id) {
                        vehicleIdContainer.style.display = 'block';
                        document.getElementById('vehicleId').textContent = freshData.vehicle_id;
                        console.log('Setting vehicle ID to:', freshData.vehicle_id);
                    } else {
                        vehicleIdContainer.style.display = 'none';
                    }
                    
                    document.getElementById('smokeCheckCompleted').textContent = freshData.smog_check_completed === 'yes' ? 'Yes' : 'No';
                    document.getElementById('smogCheckVerified').textContent = freshData.smog_check_verified === 'yes' ? 'Verified' : 'Not Verified';
                    document.getElementById('smogCheckVerified').className = 'status-value ' + (freshData.smog_check_verified === 'yes' ? 'verified' : 'not-verified');
                    document.getElementById('cleanTruckStatus').textContent = freshData.clean_truck_status;

                    // Show sidebar and overlay
                    document.getElementById('detailsSidebar').classList.add('active');
                    document.getElementById('sidebarOverlay').classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching updated details');
                });
        }

        function hideDetails() {
            document.getElementById('detailsSidebar').classList.remove('active', 'fullscreen');
            document.getElementById('sidebarOverlay').classList.remove('active');
            // Reset expand button icon
            const expandBtn = document.querySelector('.btn-expand i');
            expandBtn.classList.remove('fa-compress');
            expandBtn.classList.add('fa-expand');
        }

        // Add click event listener for overlay
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('sidebarOverlay');
            overlay.addEventListener('click', hideDetails);
        });

        function toggleFullscreen() {
            const sidebar = document.getElementById('detailsSidebar');
            const expandBtn = document.querySelector('.btn-expand i');
            sidebar.classList.toggle('fullscreen');
            
            if (sidebar.classList.contains('fullscreen')) {
                expandBtn.classList.remove('fa-expand');
                expandBtn.classList.add('fa-compress');
            } else {
                expandBtn.classList.remove('fa-compress');
                expandBtn.classList.add('fa-expand');
            }
        }

        function editService() {
            const appointmentId = document.getElementById('appointmentId').textContent;
            window.location.href = `edit_clean_truck.php?id=${appointmentId}`;
        }

        function cancelService() {
            if (confirm('Are you sure you want to cancel this service?')) {
                const appointmentId = document.getElementById('appointmentId').textContent;
                fetch(`cancel_clean_truck.php?id=${appointmentId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Service cancelled successfully');
                        hideDetails();
                        location.reload();
                    } else {
                        alert('Failed to cancel service: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the service');
                });
            }
        }

        function updateCheckboxStates() {
            const smogCheck = document.getElementById('popupSmogCheck');
            const smogVerified = document.getElementById('popupSmogVerified');
            const cleanTruck = document.getElementById('popupCleanTruck');
            const smogVerifiedCheckbox = document.getElementById('smogVerifiedCheckbox');
            const cleanTruckCheckbox = document.getElementById('cleanTruckCheckbox');

            // Reset states
            smogVerifiedCheckbox.classList.remove('disabled');
            cleanTruckCheckbox.classList.remove('disabled');
            smogVerified.disabled = false;
            cleanTruck.disabled = false;

            // Update based on smog check
            if (!smogCheck.checked) {
                smogVerifiedCheckbox.classList.add('disabled');
                cleanTruckCheckbox.classList.add('disabled');
                smogVerified.disabled = true;
                cleanTruck.disabled = true;
                smogVerified.checked = false;
                cleanTruck.checked = false;
            } else {
                // If smog check is checked, enable smog verified
                smogVerifiedCheckbox.classList.remove('disabled');
                smogVerified.disabled = false;

                // If smog verified is checked, enable clean truck
                if (smogVerified.checked) {
                    cleanTruckCheckbox.classList.remove('disabled');
                    cleanTruck.disabled = false;
                } else {
                    cleanTruckCheckbox.classList.add('disabled');
                    cleanTruck.disabled = true;
                    cleanTruck.checked = false;
                }
            }
        }

        function openUpdateServicesModal() {
            const clientName = document.getElementById('contactName').textContent;
            const vehiclePlate = document.getElementById('plateNumber').textContent;
            const smogCheckCompleted = document.getElementById('smokeCheckCompleted').textContent === 'Yes';
            const smogCheckVerified = document.getElementById('smogCheckVerified').textContent === 'Verified';
            const cleanTruckStatus = document.getElementById('cleanTruckStatus').textContent === 'completed';

            document.getElementById('popupClientName').textContent = clientName;
            document.getElementById('popupVehiclePlate').textContent = vehiclePlate;
            document.getElementById('popupSmogCheck').checked = smogCheckCompleted;
            document.getElementById('popupSmogVerified').checked = smogCheckVerified;
            document.getElementById('popupCleanTruck').checked = cleanTruckStatus;

            // Show popup and overlay
            document.getElementById('servicePopup').classList.add('active');
            document.getElementById('sidebarOverlay').classList.add('active');

            // Update checkbox states after setting initial values
            updateCheckboxStates();
        }

        function closeServicePopup() {
            document.getElementById('servicePopup').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }

        function closeCompletionModal() {
            document.getElementById('completionModal').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }

        function saveServiceUpdates() {
            const appointmentId = document.getElementById('appointmentId').textContent;
            const vehicleId = document.getElementById('vehicleId').textContent;
            const smogCheckCompleted = document.getElementById('popupSmogCheck').checked;
            const smogCheckVerified = document.getElementById('popupSmogVerified').checked;
            const cleanTruckCompleted = document.getElementById('popupCleanTruck').checked;

            // Prepare the data object with the correct column names and values
            const data = {
                appointment_id: appointmentId,
                vehicle_id: vehicleId,
                smog_check_completed: smogCheckCompleted ? 'yes' : 'no',
                smog_check_status: smogCheckVerified ? 'confirmed' : 'pending',
                smog_check_verified: smogCheckVerified ? 'yes' : 'no',
                clean_truck_status: cleanTruckCompleted ? 'completed' : 'pending',
                update_timestamps: cleanTruckCompleted // Flag to indicate if we need to update timestamps
            };

            fetch('update_clean_truck_services.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Update the display in the sidebar
                    document.getElementById('smokeCheckCompleted').textContent = data.smog_check_completed === 'yes' ? 'Yes' : 'No';
                    document.getElementById('smogCheckVerified').textContent = data.smog_check_verified === 'yes' ? 'Verified' : 'Not Verified';
                    document.getElementById('smogCheckVerified').className = 'status-value ' + (data.smog_check_verified === 'yes' ? 'verified' : 'not-verified');
                    document.getElementById('cleanTruckStatus').textContent = data.clean_truck_status;

                    // Close the popup
                    closeServicePopup();

                    // If clean truck is completed, show completion modal
                    if (data.clean_truck_status === 'completed') {
                        document.getElementById('completionModal').classList.add('active');
                    } else {
                        // Show success message
                        alert('Services updated successfully');
                    }
                } else {
                    alert('Failed to update services: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating services');
            });
        }

        function updateFileName(input) {
            const fileName = input.files[0]?.name || 'No file chosen';
            document.getElementById('fileName').textContent = fileName;
        }

        function sendCompletionEmail() {
            const appointmentId = document.getElementById('appointmentId').textContent;
            const instructions = document.getElementById('completionInstructions').value;
            const fileInput = document.getElementById('fileInput');
            
            // Show email animation
            document.getElementById('emailAnimation').classList.add('active');
            
            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            formData.append('instructions', instructions);
            if (fileInput.files.length > 0) {
                formData.append('file', fileInput.files[0]);
            }

            fetch('send_completion_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Hide email animation
                document.getElementById('emailAnimation').classList.remove('active');
                
                if (data.success) {
                    // Show success popup
                    document.getElementById('successPopup').classList.add('active');
                    // Close the completion modal
                    closeCompletionModal();
                } else {
                    alert('Error sending email: ' + data.error);
                }
            })
            .catch(error => {
                // Hide email animation
                document.getElementById('emailAnimation').classList.remove('active');
                alert('Error sending email: ' + error);
            });
        }

        function closeSuccessPopup() {
            document.getElementById('successPopup').classList.remove('active');
        }

        // Add event listeners for checkbox changes
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('popupSmogCheck').addEventListener('change', updateCheckboxStates);
            document.getElementById('popupSmogVerified').addEventListener('change', updateCheckboxStates);
        });
    </script>
    <!-- Add these elements just before the closing body tag -->
    <div class="email-animation" id="emailAnimation">
        <div class="email-container">
            <div class="email-icon"></div>
            <div class="cloud cloud-1"></div>
            <div class="cloud cloud-2"></div>
            <div class="cloud cloud-3"></div>
        </div>
    </div>

    <div class="success-popup" id="successPopup">
        <i class="fas fa-check-circle success-icon"></i>
        <div class="success-message">Email sent successfully!</div>
        <button class="success-button" onclick="closeSuccessPopup()">OK</button>
    </div>
</body>
</html> 