<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get KPI data
// Total Smoke Checks
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM calendar_events
");
$stmt->execute();
$total_checks = $stmt->get_result()->fetch_assoc()['total'];

// Total Pending Smoke Checks
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending 
    FROM calendar_events 
    WHERE status = 'pending'
");
$stmt->execute();
$pending_checks = $stmt->get_result()->fetch_assoc()['pending'];

// Total Pending for Work (smoke check pending)
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_work 
    FROM calendar_events 
    WHERE status = 'pending'
");
$stmt->execute();
$pending_work = $stmt->get_result()->fetch_assoc()['pending_work'];

// Critical Tasks (confirmed smoke check but pending for more than 3 days)
$stmt = $conn->prepare("
    SELECT COUNT(*) as critical_tasks 
    FROM calendar_events 
    WHERE status = 'pending'
    AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
");
$stmt->execute();
$critical_tasks = $stmt->get_result()->fetch_assoc()['critical_tasks'];

// Get critical tasks details for modal
$stmt = $conn->prepare("
    SELECT 
        ce.*,
        a.companyName,
        a.Name as clientName,
        a.email,
        a.phone,
        a.created_at as appointment_date
    FROM calendar_events ce
    JOIN appointments a ON ce.appointment_id = a.id
    WHERE ce.status = 'pending'
    AND ce.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$critical_tasks_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get distinct statuses from calendar_events
$stmt = $conn->prepare("SELECT DISTINCT status FROM calendar_events ORDER BY status");
$stmt->execute();
$statuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get distinct consultants
$stmt = $conn->prepare("
    SELECT DISTINCT 
        CONCAT(acc.firstName, ' ', acc.lastName) as consultant_name,
        acc.email as consultant_email,
        CONCAT(acc.firstName, ' ', acc.lastName, ' (', acc.email, ')') as consultant_display
    FROM calendar_events ce 
    JOIN accounts acc ON ce.user_id = acc.email 
    ORDER BY consultant_name
");
$stmt->execute();
$consultants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$consultant = isset($_GET['consultant']) ? $_GET['consultant'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build the query for the list
$query = "
    SELECT 
        ce.id as event_id,
        ce.appointment_id,
        ce.start_time,
        ce.description,
        ce.status as event_status,
        a.companyName,
        a.Name as contactName,
        a.email,
        a.phone,
        a.number_of_vehicles,
        a.status as appointment_status,
        a.approved_by,
        a.total_price,
        v.id as vehicle_id,
        v.vehYear,
        v.vehMake,
        v.vin,
        v.plateNo,
        v.created_at as vehicle_created_at,
        CONCAT(acc.firstName, ' ', acc.lastName) as consultant_name,
        acc.email as consultant_email
    FROM calendar_events ce
    JOIN appointments a ON ce.appointment_id = a.id
    LEFT JOIN vehicles v ON ce.vehid = v.id
    LEFT JOIN accounts acc ON ce.user_id = acc.email
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (
        a.companyName LIKE ? OR 
        v.vin LIKE ? OR
        v.plateNo LIKE ? OR
        CONCAT(acc.firstName, ' ', acc.lastName) LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($status)) {
    $query .= " AND ce.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($consultant)) {
    $query .= " AND acc.email = ?";
    $params[] = $consultant;
    $types .= "s";
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $query .= " AND DATE(ce.start_time) = CURDATE()";
            break;
        case 'tomorrow':
            $query .= " AND DATE(ce.start_time) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $query .= " AND YEARWEEK(ce.start_time) = YEARWEEK(CURDATE())";
            break;
        case 'this_month':
            $query .= " AND MONTH(ce.start_time) = MONTH(CURDATE()) AND YEAR(ce.start_time) = YEAR(CURDATE())";
            break;
    }
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY ce.created_at ASC";
        break;
    case 'company':
        $query .= " ORDER BY a.companyName ASC";
        break;
    case 'status':
        $query .= " ORDER BY ce.status ASC";
        break;
    case 'consultant':
        $query .= " ORDER BY consultant_name ASC";
        break;
    default: // newest
        $query .= " ORDER BY ce.created_at DESC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$checks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smoke Checks - Sky Smoke Check LLC</title>
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
        .dashboard-container {
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        .kpi-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
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
        }
        .kpi-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .check-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .check-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .check-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .appointment-number {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .details-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        .details-sidebar.active {
            right: 0;
        }
        .details-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .details-content {
            padding: 20px;
        }
        .details-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .details-section h6 {
            margin-bottom: 15px;
            color: #495057;
        }
        .details-label {
            font-weight: 600;
            color: #495057;
        }
        .status-value {
            color: #6c757d;
        }
        .service-status {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .service-status i {
            margin-right: 10px;
            color: #6c757d;
        }
        .update-services-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 15px;
        }
        .update-services-btn:hover {
            background: #0056b3;
        }
        .service-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            z-index: 1100;
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
        }
        .service-popup-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        .service-popup-content {
            margin-bottom: 20px;
        }
        .client-info {
            margin-bottom: 20px;
        }
        .service-checkbox {
            margin-bottom: 15px;
        }
        .service-checkbox.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
        }
        .completion-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            z-index: 1100;
            display: none;
        }
        .completion-modal.active {
            display: block;
        }
        .completion-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .completion-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        .completion-modal-content {
            margin-bottom: 20px;
        }
        .completion-modal-footer {
            text-align: right;
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
            .details-sidebar {
                width: 100%;
                right: -100%;
            }
        }
        .side-peek {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1050;
            display: flex;
            flex-direction: column;
        }
        
        .side-peek.active {
            right: 0;
        }
        
        .side-peek.fullscreen {
            right: 0;
            width: 100%;
            z-index: 1051;
        }
        
        .side-peek-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .side-peek-title {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .side-peek-actions {
            display: flex;
            gap: 5px;
        }
        
        .side-peek-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .section {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1049;
            display: none;
        }
        
        .overlay.active {
            display: block;
        }

        .appointment-datetime {
            font-size: 0.9rem;
        }
        .appointment-datetime .date {
            font-weight: 600;
            color: #495057;
        }
        .appointment-datetime .time {
            color: #6c757d;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        .value {
            font-size: 1rem;
            color: #212529;
        }
        .vehicle-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .vehicle-card:last-child {
            margin-bottom: 0;
        }
        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .vehicle-title {
            font-weight: 600;
            color: #495057;
        }
        .vehicle-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .vehicle-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .vehicle-statuses {
            display: flex;
            gap: 5px;
        }
        .vehicle-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: white;
        }
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        .text-danger {
            color: #dc3545;
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
                    <a href="clean_truck_checks.php">
                        <i class="fas fa-truck"></i> Clean Truck Check
                    </a>
                </li>
                <li>
                    <a href="smoke_checks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'smoke_checks.php' ? 'active' : ''; ?>">
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
                <h2 class="mb-4">Smoke Checks</h2>

                <!-- KPI Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card kpi-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-smoking kpi-icon text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="kpi-number"><?php echo $total_checks; ?></div>
                                        <div class="kpi-label">Total Smoke Checks</div>
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
                                        <div class="kpi-label">Pending Smoke Checks</div>
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
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="search" placeholder="Search company, VIN, plate, or consultant..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option['status']); ?>" <?php echo $status === $status_option['status'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(htmlspecialchars($status_option['status'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="consultant">
                                <option value="">All Consultants</option>
                                <?php foreach ($consultants as $consultant_option): ?>
                                    <option value="<?php echo htmlspecialchars($consultant_option['consultant_email']); ?>" <?php echo $consultant === $consultant_option['consultant_email'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($consultant_option['consultant_display']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="date_filter">
                                <option value="">All Dates</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="company" <?php echo $sort === 'company' ? 'selected' : ''; ?>>By Company</option>
                                <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>By Status</option>
                                <option value="consultant" <?php echo $sort === 'consultant' ? 'selected' : ''; ?>>By Consultant</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Smoke Checks List -->
                <div class="check-list">
                    <!-- Table Header -->
                    <div class="check-item header-row" style="background-color: #f8f9fa; cursor: default;">
                        <div class="row">
                            <div class="col-md-1">
                                <strong>ID</strong>
                            </div>
                            <div class="col-md-2">
                                <strong>Company</strong>
                            </div>
                            <div class="col-md-3">
                                <strong>Description</strong>
                            </div>
                            <div class="col-md-2">
                                <strong>Date & Time</strong>
                            </div>
                            <div class="col-md-2">
                                <strong>Consultant</strong>
                            </div>
                            <div class="col-md-2">
                                <strong>Status</strong>
                            </div>
                        </div>
                    </div>
                    <?php 
                    // Create an array of colors for consultants
                    $consultantColors = [
                        'primary' => '#007bff',
                        'success' => '#28a745',
                        'info' => '#17a2b8',
                        'warning' => '#ffc107',
                        'danger' => '#dc3545',
                        'secondary' => '#6c757d'
                    ];
                    $consultantColorMap = [];
                    $colorIndex = 0;
                    ?>
                    <?php foreach ($checks as $check): 
                        // Assign a color to each unique consultant
                        if (!isset($consultantColorMap[$check['consultant_name']])) {
                            $consultantColorMap[$check['consultant_name']] = array_values($consultantColors)[$colorIndex % count($consultantColors)];
                            $colorIndex++;
                        }
                    ?>
                        <div class="check-item" onclick="showDetails(<?php echo htmlspecialchars(json_encode($check)); ?>)">
                            <div class="row">
                                <div class="col-md-1">
                                    <span class="appointment-number">#<?php echo htmlspecialchars($check['appointment_id']); ?></span>
                                    <?php if (isset($check['vehicle_id']) && $check['vehicle_id']): ?>
                                        <br>
                                        <small class="text-muted">Veh ID: <?php echo htmlspecialchars($check['vehicle_id']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($check['companyName']); ?></h6>
                                    <small class="text-muted">
                                        Vehicles: <?php echo htmlspecialchars($check['number_of_vehicles']); ?>
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-0"><?php echo htmlspecialchars($check['description']); ?></p>
                                </div>
                                <div class="col-md-2">
                                    <div class="appointment-datetime">
                                        <div class="date"><?php echo date('M d, Y', strtotime($check['start_time'])); ?></div>
                                        <div class="time"><?php echo date('h:i A', strtotime($check['start_time'])); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="consultant-badge" style="background-color: <?php echo isset($check['consultant_name']) ? $consultantColorMap[$check['consultant_name']] : '#6c757d'; ?>; color: white; padding: 3px 8px; border-radius: 4px; display: inline-block; font-size: 0.8rem;">
                                        <?php echo isset($check['consultant_name']) ? htmlspecialchars($check['consultant_name']) : 'No Consultant Assigned'; ?>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex justify-content-end">
                                        <span class="badge bg-<?php 
                                            echo $check['event_status'] === 'completed' ? 'success' : 
                                                ($check['event_status'] === 'cancelled' ? 'danger' : 
                                                ($check['event_status'] === 'confirmed' ? 'info' : 'warning')); 
                                        ?>" style="font-size: 1rem; padding: 8px 12px;">
                                            <?php echo ucfirst($check['event_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Peek for Check Details -->
    <div class="side-peek" id="checkSidePeek">
        <div class="side-peek-header">
            <h5 class="side-peek-title">Appointment Details</h5>
            <div class="side-peek-actions">
                <button class="btn btn-sm btn-outline-primary" id="editCheck">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" id="deleteCheck">
                    <i class="fas fa-trash"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="toggleFullScreen">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="closeSidePeek">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="side-peek-body">
            <!-- Appointment Information -->
            <div class="section mb-4">
                <h6 class="section-title">Appointment Information</h6>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Appointment #:</span>
                        <span class="value" id="appointmentNumber"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Date & Time:</span>
                        <span class="value" id="appointmentDateTime"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Status:</span>
                        <span class="value" id="appointmentStatus"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Location:</span>
                        <span class="value" id="appointmentLocation"></span>
                    </div>
                    <div class="info-item full-width">
                        <span class="label">Special Instructions:</span>
                        <span class="value" id="specialInstructions"></span>
                    </div>
                </div>
            </div>

            <!-- Company Information -->
            <div class="section mb-4">
                <h6 class="section-title">Company Information</h6>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Company Name:</span>
                        <span class="value" id="companyName"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contact Person:</span>
                        <span class="value" id="contactName"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email:</span>
                        <span class="value" id="contactEmail"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Phone:</span>
                        <span class="value" id="contactPhone"></span>
                    </div>
                </div>
            </div>

            <!-- Financial Information -->
            <div class="section mb-4">
                <h6 class="section-title">Financial Information</h6>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Total Price:</span>
                        <span class="value" id="totalPrice"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Discount:</span>
                        <span class="value" id="discountInfo"></span>
                    </div>
                </div>
            </div>

            <!-- Consultant Information -->
            <div class="section mb-4">
                <h6 class="section-title">Consultant Information</h6>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Consultant:</span>
                        <span class="value" id="consultantName"></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email:</span>
                        <span class="value" id="consultantEmail"></span>
                    </div>
                </div>
            </div>

            <!-- Vehicles Information -->
            <div class="section mb-4">
                <h6 class="section-title">Vehicles Information</h6>
                <div id="vehiclesList">
                    <!-- Vehicles will be dynamically added here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        // Show details function
        function showDetails(data) {
            const sidePeek = document.getElementById('checkSidePeek');
            const overlay = document.getElementById('overlay');
            
            // Fetch vehicle details
            fetch(`get_vehicles.php?appointment_id=${data.appointment_id}&vehicle_id=${data.vehicle_id}`)
                .then(response => response.json())
                .then(vehicleData => {
                    if (vehicleData.error) {
                        console.error('Error:', vehicleData.error);
                        return;
                    }

                    // Populate appointment information
                    document.getElementById('appointmentNumber').textContent = '#' + vehicleData.appointment_id;
                    document.getElementById('appointmentDateTime').textContent = vehicleData.start_time_formatted;
                    document.getElementById('appointmentStatus').textContent = vehicleData.event_status;
                    document.getElementById('appointmentLocation').textContent = vehicleData.location_instructions;
                    document.getElementById('specialInstructions').textContent = vehicleData.special_instructions || 'N/A';

                    // Populate company information
                    document.getElementById('companyName').textContent = vehicleData.companyName;
                    document.getElementById('contactName').textContent = vehicleData.contact_name;
                    document.getElementById('contactEmail').textContent = vehicleData.contact_email;
                    document.getElementById('contactPhone').textContent = vehicleData.contact_phone;

                    // Populate financial information
                    document.getElementById('totalPrice').textContent = '$' + parseFloat(vehicleData.total_price).toFixed(2);
                    let discountInfo = '';
                    if (vehicleData.discount_type) {
                        discountInfo = `${vehicleData.discount_type}: `;
                        if (vehicleData.discount_percentage) {
                            discountInfo += `${vehicleData.discount_percentage}%`;
                        } else if (vehicleData.discount_amount) {
                            discountInfo += `$${parseFloat(vehicleData.discount_amount).toFixed(2)}`;
                        }
                    } else {
                        discountInfo = 'No discount';
                    }
                    document.getElementById('discountInfo').textContent = discountInfo;

                    // Populate consultant information
                    document.getElementById('consultantName').textContent = vehicleData.consultant_name || 'Not Assigned';
                    document.getElementById('consultantEmail').textContent = vehicleData.consultant_email || 'N/A';

                    // Populate vehicle information
                    const vehiclesList = document.getElementById('vehiclesList');
                    vehiclesList.innerHTML = `
                        <div class="vehicle-card">
                            <div class="vehicle-header">
                                <div class="vehicle-title">${vehicleData.vehYear} ${vehicleData.vehMake}</div>
                                <div class="vehicle-statuses">
                                    <span class="vehicle-status bg-${getStatusColor(vehicleData.event_status)}">
                                        ${vehicleData.event_status}
                                    </span>
                                </div>
                            </div>
                            <div class="vehicle-details">
                                <div class="info-item">
                                    <span class="label">Vehicle ID:</span>
                                    <span class="value">${vehicleData.vehicle_id}</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">VIN:</span>
                                    <span class="value">${vehicleData.vin}</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Plate:</span>
                                    <span class="value">${vehicleData.plateNo}</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Service Type:</span>
                                    <span class="value">${vehicleData.service_type}</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Location:</span>
                                    <span class="value">${vehicleData.location_instructions || 'N/A'}</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Date & Time:</span>
                                    <span class="value">${vehicleData.start_time_formatted}</span>
                                </div>
                                <div class="info-item">
                                    <span class="label">Special Instructions:</span>
                                    <span class="value">${vehicleData.special_instructions || 'N/A'}</span>
                                </div>
                                <div class="info-item full-width mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="label">Smoke Test Status:</span>
                                            <span class="value ms-2 badge bg-${getSmokeTestStatusColor(vehicleData.smoke_test_status)}">
                                                ${vehicleData.smoke_test_status || 'Not Tested'}
                                            </span>
                                        </div>
                                        <button class="btn btn-primary btn-sm" onclick="updateSmokeTest(${vehicleData.vehicle_id})">
                                            Update Smoke Test
                                        </button>
                                    </div>
                                </div>
                                ${vehicleData.smoke_test_notes ? `
                                    <div class="info-item full-width mt-2">
                                        <span class="label">Test Notes:</span>
                                        <span class="value">${vehicleData.smoke_test_notes}</span>
                                    </div>
                                ` : ''}
                                ${vehicleData.error_code ? `
                                    <div class="info-item full-width mt-2">
                                        <span class="label">Error Code:</span>
                                        <span class="value text-danger">${vehicleData.error_code}</span>
                                    </div>
                                ` : ''}
                                ${vehicleData.warm_up ? `
                                    <div class="info-item full-width mt-2">
                                        <span class="label">Warm Up Cycles:</span>
                                        <span class="value">${vehicleData.warm_up}</span>
                                    </div>
                                ` : ''}
                                ${vehicleData.attachment_path ? `
                                    <div class="info-item full-width mt-2">
                                        <span class="label">Test Result:</span>
                                        <a href="${vehicleData.attachment_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            View Attachment
                                        </a>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                })
                .catch(error => console.error('Error fetching vehicle details:', error));

            // Show side peek and overlay
            sidePeek.classList.add('active');
            overlay.classList.add('active');
        }

        function formatDateTime(dateTimeStr) {
            const date = new Date(dateTimeStr);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function getStatusColor(status) {
            if (!status) return 'secondary';
            switch (status.toLowerCase()) {
                case 'completed': return 'success';
                case 'cancelled': return 'danger';
                case 'confirmed': return 'info';
                case 'pending': return 'warning';
                default: return 'secondary';
            }
        }

        function getSmokeTestStatusColor(status) {
            if (!status) return 'secondary';
            switch (status.toLowerCase()) {
                case 'passed': return 'success';
                case 'failed': return 'danger';
                case 'warmup': return 'warning';
                case 'pending': return 'info';
                default: return 'secondary';
            }
        }

        function updateSmokeTest(vehicleId) {
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Smoke Test</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="smokeTestForm">
                                <input type="hidden" name="vehicle_id" value="${vehicleId}">
                                <div class="mb-3">
                                    <label class="form-label">Test Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" id="statusPassed" value="passed">
                                        <label class="form-check-label" for="statusPassed">Passed</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" id="statusFailed" value="failed">
                                        <label class="form-check-label" for="statusFailed">Failed</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" id="statusWarmup" value="warmup">
                                        <label class="form-check-label" for="statusWarmup">Warm Up Required</label>
                                    </div>
                                </div>

                                <!-- Passed Options -->
                                <div id="passedOptions" class="mb-3" style="display: none;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sendReminder" name="send_reminder">
                                        <label class="form-check-label" for="sendReminder">
                                            Send automatic reminder before next smoke test
                                        </label>
                                    </div>
                                </div>

                                <!-- Failed Options -->
                                <div id="failedOptions" class="mb-3" style="display: none;">
                                    <div class="mb-3">
                                        <label for="errorCode" class="form-label">Error Code</label>
                                        <input type="text" class="form-control" id="errorCode" name="error_code">
                                    </div>
                                </div>

                                <!-- Warm Up Options -->
                                <div id="warmupOptions" class="mb-3" style="display: none;">
                                    <div class="mb-3">
                                        <label for="warmupCycles" class="form-label">Number of Warm Up Cycles</label>
                                        <select class="form-select" id="warmupCycles" name="warmup_cycles">
                                            <option value="">Select cycles</option>
                                            <option value="1">1 cycle</option>
                                            <option value="2">2 cycles</option>
                                            <option value="3">3 cycles</option>
                                            <option value="4">4 cycles</option>
                                            <option value="5">5 cycles</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="testAttachment" class="form-label">Test Results Attachment</label>
                                    <input type="file" class="form-control" id="testAttachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Max file size: 5MB. Allowed formats: PDF, JPG, PNG</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="saveSmokeTest">Save</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
            
            // Show/hide options based on selected status
            const statusRadios = modal.querySelectorAll('input[name="status"]');
            statusRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('passedOptions').style.display = 
                        this.value === 'passed' ? 'block' : 'none';
                    document.getElementById('failedOptions').style.display = 
                        this.value === 'failed' ? 'block' : 'none';
                    document.getElementById('warmupOptions').style.display = 
                        this.value === 'warmup' ? 'block' : 'none';
                });
            });
            
            // Handle save button click
            document.getElementById('saveSmokeTest').addEventListener('click', function() {
                const form = document.getElementById('smokeTestForm');
                const formData = new FormData(form);
                
                // Validate form
                const status = formData.get('status');
                if (!status) {
                    alert('Please select a test status');
                    return;
                }

                if (status === 'failed' && !formData.get('error_code')) {
                    alert('Please enter the error code');
                    return;
                }

                if (status === 'warmup' && !formData.get('warmup_cycles')) {
                    alert('Please select the number of warm up cycles');
                    return;
                }

                // Get the submit button
                const submitButton = this;
                const originalButtonText = submitButton.innerHTML;
                
                // Disable button and show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait...';
                
                // Send update to server
                fetch('update_smoke_test.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Close modal
                        modalInstance.hide();
                        modal.remove();
                        
                        // Show success message
                        const successModal = document.createElement('div');
                        successModal.className = 'modal fade';
                        successModal.innerHTML = `
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Success</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="text-center">
                                            <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                                            <p>Smoke test status has been updated successfully.</p>
                                            <p>Notification email has been sent to the client.</p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(successModal);
                        const successModalInstance = new bootstrap.Modal(successModal);
                        successModalInstance.show();

                        // Remove modal when closed
                        successModal.addEventListener('hidden.bs.modal', function () {
                            successModal.remove();
                            // Refresh the page to show updated status
                            window.location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Failed to update smoke test status');
                    }
                })
                .catch(error => {
                    // Show error message
                    const errorModal = document.createElement('div');
                    errorModal.className = 'modal fade';
                    errorModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Error</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="text-center">
                                        <i class="fas fa-exclamation-circle text-danger mb-3" style="font-size: 3rem;"></i>
                                        <p>${error.message}</p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(errorModal);
                    const errorModalInstance = new bootstrap.Modal(errorModal);
                    errorModalInstance.show();

                    // Remove modal when closed
                    errorModal.addEventListener('hidden.bs.modal', function () {
                        errorModal.remove();
                    });
                })
                .finally(() => {
                    // Reset button state
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
            });

            // Remove modal when closed
            modal.addEventListener('hidden.bs.modal', function () {
                modal.remove();
            });
        }

        // Close side peek
        document.getElementById('closeSidePeek').addEventListener('click', function() {
            const sidePeek = document.getElementById('checkSidePeek');
            const overlay = document.getElementById('overlay');
            sidePeek.classList.remove('active', 'fullscreen');
            overlay.classList.remove('active');
        });

        // Toggle fullscreen
        document.getElementById('toggleFullScreen').addEventListener('click', function() {
            const sidePeek = document.getElementById('checkSidePeek');
            sidePeek.classList.toggle('fullscreen');
            const icon = this.querySelector('i');
            if (sidePeek.classList.contains('fullscreen')) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
            } else {
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
            }
        });

        // Close on overlay click
        document.getElementById('overlay').addEventListener('click', function() {
            const sidePeek = document.getElementById('checkSidePeek');
            sidePeek.classList.remove('active', 'fullscreen');
            this.classList.remove('active');
            document.getElementById('toggleFullScreen').querySelector('i').classList.remove('fa-compress');
            document.getElementById('toggleFullScreen').querySelector('i').classList.add('fa-expand');
        });

        // Handle edit check
        document.getElementById('editCheck').addEventListener('click', function() {
            const appointmentId = document.getElementById('appointmentNumber').textContent.replace('#', '');
            if (!appointmentId) {
                alert('Error: No appointment ID found');
                return;
            }
            window.location.href = `edit_appointment.php?id=${appointmentId}`;
        });

        // Handle delete check
        document.getElementById('deleteCheck').addEventListener('click', function() {
            const appointmentId = document.getElementById('appointmentNumber').textContent.replace('#', '');
            if (!appointmentId) {
                alert('Error: No appointment ID found');
                return;
            }

            if (confirm('Are you sure you want to delete this check?')) {
                fetch('delete_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ appointment_id: appointmentId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting check: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting check');
                });
            }
        });
    </script>
</body>
</html> 