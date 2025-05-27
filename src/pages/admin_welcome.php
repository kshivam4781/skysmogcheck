<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has consultant access
if (!isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    $_SESSION['error_message'] = "Access restricted. Please contact your administrator.";
    header("Location: login.php");
    exit();
}

// Get total unique clients
$stmt = $conn->prepare("SELECT COUNT(DISTINCT companyName) as total_clients FROM appointments");
$stmt->execute();
$total_clients = $stmt->get_result()->fetch_assoc()['total_clients'];

// Get total smoke tests completed
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_tests 
    FROM vehicles 
    WHERE smoke_test_status != 'pending'
");
$stmt->execute();
$total_tests = $stmt->get_result()->fetch_assoc()['total_tests'];

// Get pending tasks (less than 2 days overdue)
$stmt = $conn->prepare("
    SELECT a.*, ce.start_time, ce.end_time 
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    WHERE DATE(ce.start_time) < CURDATE()
    AND DATE(ce.start_time) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    AND a.status != 'completed'
    ORDER BY ce.start_time ASC
");
$stmt->execute();
$pending_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get critical tasks (more than 2 days overdue)
$stmt = $conn->prepare("
    SELECT a.*, ce.start_time, ce.end_time 
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    WHERE DATE(ce.start_time) < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    AND a.status != 'completed'
    ORDER BY ce.start_time ASC
");
$stmt->execute();
$critical_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set timezone to PST
date_default_timezone_set('America/Los_Angeles');

// Get yesterday's events (PST)
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt = $conn->prepare("
    SELECT a.*, ce.start_time, ce.end_time, ce.status, a.Name as contactName 
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    WHERE DATE(ce.start_time) = ?
    ORDER BY ce.start_time ASC
");
$stmt->bind_param("s", $yesterday);
$stmt->execute();
$yesterday_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's events (PST)
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT a.*, ce.start_time, ce.end_time, ce.status 
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    WHERE DATE(ce.start_time) = ?
    ORDER BY ce.start_time ASC
");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get tomorrow's events (PST)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $conn->prepare("
    SELECT a.*, ce.start_time, ce.end_time, ce.status 
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    WHERE DATE(ce.start_time) = ?
    ORDER BY ce.start_time ASC
");
$stmt->bind_param("s", $tomorrow);
$stmt->execute();
$tomorrow_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sky Smoke Check LLC</title>
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
        .dashboard-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .event-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .event-card:hover {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .event-time {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .event-company {
            font-weight: 600;
            color: #2c3e50;
        }
        .event-location {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .day-header {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
            color: #2c3e50;
        }
        .no-events {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }
        .task-tile {
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .task-tile:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
        }
        .pending-tile {
            background-color: #fff8e1;
            border: 2px solid #ffc107;
        }
        .critical-tile {
            background-color: #fff5f5;
            border: 2px solid #dc3545;
        }
        .task-count {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .pending-count {
            color: #856404;
        }
        .critical-count {
            color: #dc3545;
        }
        .task-label {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .pending-label {
            color: #856404;
        }
        .critical-label {
            color: #dc3545;
        }
        .task-list-item {
            border-left: 4px solid;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .task-list-item:hover {
            transform: translateX(5px);
        }
        .pending-list-item {
            border-left-color: #ffc107;
        }
        .critical-list-item {
            border-left-color: #dc3545;
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
        /* Add logout link style */
        .logout-link {
            color: #dc3545;
        }

        .logout-link:hover {
            background-color: rgba(220,53,69,0.1);
            color: #dc3545;
        }

        /* Add status badge styles */
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
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .event-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .event-item:hover {
            background-color: #f8f9fa;
        }
        .tooltip {
            font-size: 0.875rem;
        }
        .tooltip-inner {
            max-width: 300px;
            text-align: left;
            padding: 0.5rem;
        }
        .event-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .event-list::-webkit-scrollbar {
            width: 6px;
        }
        .event-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .event-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .event-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .event-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        .event-item:last-child {
            border-bottom: none;
        }
        .event-item:hover {
            background-color: #f8f9fa;
        }
        /* Collapsible table styles */
        .collapsible-table {
            margin-bottom: 20px;
        }
        
        .table-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #dee2e6;
        }
        
        .table-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        .table-header i {
            transition: transform 0.3s ease;
        }
        
        .table-header.collapsed i {
            transform: rotate(-90deg);
        }
        
        .table-content {
            transition: max-height 0.3s ease-out;
            overflow: hidden;
            max-height: 1000px; /* Adjust based on your content */
        }
        
        .table-content.collapsed {
            max-height: 0;
        }
        
        .table-content table {
            margin-top: 15px;
            width: 100%;
        }
        .event-count {
            font-size: 0.9rem;
            color: #6c757d;
            margin-left: 5px;
        }
        
        .table-header.collapsed .event-count {
            color: #fff;
        }

        /* Add styles for the collapsible card */
        .card-body {
            transition: max-height 0.3s ease-out;
            overflow: hidden;
        }
        
        .card-body.collapsed {
            max-height: 0;
            padding: 0 !important;
        }
        
        .card-header i {
            transition: transform 0.3s ease;
        }
        
        .card-header.collapsed i {
            transform: rotate(-90deg);
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
                            <i class="fas fa-bell"></i> Reminder
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="container-fluid">
                <h2 class="mb-4">Dashboard Overview</h2>
                
                <!-- Tasks Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="task-tile pending-tile" data-bs-toggle="modal" data-bs-target="#pendingTasksModal">
                            <div class="task-count pending-count"><?php echo count($pending_tasks); ?></div>
                            <div class="task-label pending-label">
                                <i class="fas fa-clock me-2"></i>
                                Pending Tasks
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="task-tile critical-tile" data-bs-toggle="modal" data-bs-target="#criticalTasksModal">
                            <div class="task-count critical-count"><?php echo count($critical_tasks); ?></div>
                            <div class="task-label critical-label">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Critical Tasks
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Tasks Modal -->
                <div class="modal fade" id="pendingTasksModal" tabindex="-1" aria-labelledby="pendingTasksModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-white">
                                <h5 class="modal-title" id="pendingTasksModalLabel">
                                    <i class="fas fa-clock me-2"></i>
                                    Pending Tasks
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (empty($pending_tasks)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">No Pending Tasks</h5>
                                        <p class="text-muted">All tasks are up to date!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($pending_tasks as $task): ?>
                                        <div class="task-list-item pending-list-item p-3" onclick="window.location.href='view_appointment.php?id=<?php echo $task['id']; ?>'">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['companyName']); ?></h6>
                                                    <p class="mb-0 text-muted">
                                                        <i class="far fa-calendar-alt me-2"></i>
                                                        <?php echo date('M d, Y', strtotime($task['start_time'])); ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            </div>
                                            <p class="mb-0 mt-2">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?php echo htmlspecialchars($task['test_address']); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Tasks Modal -->
                <div class="modal fade" id="criticalTasksModal" tabindex="-1" aria-labelledby="criticalTasksModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="criticalTasksModalLabel">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Critical Tasks
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (empty($critical_tasks)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">No Critical Tasks</h5>
                                        <p class="text-muted">All tasks are up to date!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($critical_tasks as $task): ?>
                                        <div class="task-list-item critical-list-item p-3" onclick="window.location.href='view_appointment.php?id=<?php echo $task['id']; ?>'">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['companyName']); ?></h6>
                                                    <p class="mb-0 text-muted">
                                                        <i class="far fa-calendar-alt me-2"></i>
                                                        <?php echo date('M d, Y', strtotime($task['start_time'])); ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-danger">Critical</span>
                                            </div>
                                            <p class="mb-0 mt-2">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?php echo htmlspecialchars($task['test_address']); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-building stat-icon text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?php echo $total_clients; ?></div>
                                        <div class="stat-label">Total Clients</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-smog stat-icon text-success"></i>
                                    </div>
                                    <div>
                                        <div class="stat-number"><?php echo $total_tests; ?></div>
                                        <div class="stat-label">Smoke Tests Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-truck stat-icon text-warning"></i>
                                    </div>
                                    <div>
                                        <?php
                                        // Get total pending clean truck checks
                                        $stmt = $conn->prepare("
                                            SELECT COUNT(*) as pending_checks 
                                            FROM clean_truck_checks 
                                            WHERE clean_truck_status = 'pending'
                                        ");
                                        $stmt->execute();
                                        $pending_clean_truck_checks = $stmt->get_result()->fetch_assoc()['pending_checks'];
                                        ?>
                                        <div class="stat-number"><?php echo $pending_clean_truck_checks; ?></div>
                                        <div class="stat-label">Pending Clean Truck Checks</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-smoking stat-icon text-danger"></i>
                                    </div>
                                    <div>
                                        <?php
                                        // Get total pending smoke tests
                                        $stmt = $conn->prepare("
                                            SELECT COUNT(*) as pending_smoke_tests 
                                            FROM calendar_events 
                                            WHERE status = 'confirmed'
                                        ");
                                        $stmt->execute();
                                        $pending_smoke_tests = $stmt->get_result()->fetch_assoc()['pending_smoke_tests'];
                                        ?>
                                        <div class="stat-number"><?php echo $pending_smoke_tests; ?></div>
                                        <div class="stat-label">Pending Smoke Tests</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events Section -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <!-- Recent Clean Truck Checks -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleTable('recentChecks')">
                                <h5 class="card-title mb-0">Recent Clean Truck Checks</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="card-body p-0" id="recentChecks">
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Company</th>
                                                <th>Vehicle</th>
                                                <th>Status</th>
                                                <th>Next Due Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $conn->prepare("
                                                SELECT 
                                                    ctc.*,
                                                    a.companyName,
                                                    v.vehMake,
                                                    v.vehYear,
                                                    v.plateNo
                                                FROM clean_truck_checks ctc
                                                JOIN appointments a ON ctc.appointment_id = a.id
                                                JOIN vehicles v ON ctc.vehicle_id = v.id
                                                ORDER BY ctc.created_at DESC
                                                LIMIT 10
                                            ");
                                            $stmt->execute();
                                            $recent_checks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            
                                            foreach ($recent_checks as $check):
                                            ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($check['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($check['companyName']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($check['vehYear'] . ' ' . $check['vehMake']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($check['plateNo']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $check['clean_truck_status'] === 'completed' ? 'success' : 
                                                                ($check['clean_truck_status'] === 'failed' ? 'danger' : 
                                                                ($check['clean_truck_status'] === 'cancelled' ? 'secondary' : 'warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($check['clean_truck_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($check['next_clean_truck_due_date']) {
                                                            echo date('M d, Y', strtotime($check['next_clean_truck_due_date']));
                                                        } else {
                                                            echo '<span class="text-muted">Not set</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Vehicle Services -->
                        <div class="card">
                            <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleTable('upcomingServices')">
                                <h5 class="card-title mb-0">Upcoming Vehicle Services</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="card-body p-0" id="upcomingServices">
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>VIN</th>
                                                <th>Plate No</th>
                                                <th>Company</th>
                                                <th>Contact</th>
                                                <th>Service</th>
                                                <th>Due Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $conn->prepare("
                                                SELECT 
                                                    v.vin,
                                                    v.plateNo,
                                                    a.companyName,
                                                    a.Name as contactName,
                                                    a.email,
                                                    a.phone,
                                                    v.next_due_date,
                                                    v.clean_truck_check_next_date
                                                FROM vehicles v
                                                JOIN appointments a ON v.appointment_id = a.id
                                                WHERE 
                                                    (v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH))
                                                    OR 
                                                    (v.clean_truck_check_next_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH))
                                                ORDER BY 
                                                    LEAST(COALESCE(v.next_due_date, '9999-12-31'), 
                                                          COALESCE(v.clean_truck_check_next_date, '9999-12-31'))
                                            ");
                                            $stmt->execute();
                                            $upcoming_services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            
                                            foreach ($upcoming_services as $service):
                                                $service_type = '';
                                                $due_date = '';
                                                
                                                if ($service['next_due_date'] && strtotime($service['next_due_date']) <= strtotime('+1 month')) {
                                                    $service_type = 'SMOG Test';
                                                    $due_date = $service['next_due_date'];
                                                }
                                                if ($service['clean_truck_check_next_date'] && strtotime($service['clean_truck_check_next_date']) <= strtotime('+1 month')) {
                                                    if ($service_type) {
                                                        $service_type .= '<br>Clean Truck Check Certificate';
                                                        $due_date .= '<br>' . $service['clean_truck_check_next_date'];
                                                    } else {
                                                        $service_type = 'Clean Truck Check Certificate';
                                                        $due_date = $service['clean_truck_check_next_date'];
                                                    }
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($service['vin']); ?></td>
                                                    <td><?php echo htmlspecialchars($service['plateNo']); ?></td>
                                                    <td><?php echo htmlspecialchars($service['companyName']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($service['contactName']); ?><br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($service['email']); ?><br>
                                                            <?php echo htmlspecialchars($service['phone']); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo $service_type; ?></td>
                                                    <td><?php echo $due_date; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Events Section -->
                    <div class="col-md-4">
                        <!-- Yesterday's Events -->
                        <div class="collapsible-table">
                            <div class="table-header collapsed" onclick="toggleTable('yesterdayEvents')" style="background-color: #003366;">
                                <h3 style="color: #fff;">Yesterday's Events <span class="event-count">(<?php echo count($yesterday_events); ?>)</span></h3>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="table-content collapsed" id="yesterdayEvents">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Company</th>
                                            <th>Contact</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($yesterday_events as $event): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($event['start_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($event['companyName']); ?></td>
                                                <td><?php echo htmlspecialchars($event['contactName']); ?></td>
                                                <td><?php echo htmlspecialchars($event['phone']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $event['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($event['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($yesterday_events)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No events for yesterday</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Today's Events -->
                        <div class="collapsible-table">
                            <div class="table-header collapsed" onclick="toggleTable('todayEvents')" style="background-color: #003366;">
                                <h3 style="color: #fff;">Today's Events <span class="event-count">(<?php echo count($today_events); ?>)</span></h3>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="table-content collapsed" id="todayEvents">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Company</th>
                                            <th>Contact</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($today_events as $event): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($event['start_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($event['companyName']); ?></td>
                                                <td><?php echo htmlspecialchars($event['Name']); ?></td>
                                                <td><?php echo htmlspecialchars($event['phone']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $event['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($event['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($today_events)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No events for today</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tomorrow's Events -->
                        <div class="collapsible-table">
                            <div class="table-header collapsed" onclick="toggleTable('tomorrowEvents')" style="background-color: #003366;">
                                <h3 style="color: #fff;">Tomorrow's Events <span class="event-count">(<?php echo count($tomorrow_events); ?>)</span></h3>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="table-content collapsed" id="tomorrowEvents">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Company</th>
                                            <th>Contact</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tomorrow_events as $event): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($event['start_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($event['companyName']); ?></td>
                                                <td><?php echo htmlspecialchars($event['Name']); ?></td>
                                                <td><?php echo htmlspecialchars($event['phone']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $event['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($event['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($tomorrow_events)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No events for tomorrow</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true,
                    placement: 'left'
                });
            });
        });

        function toggleTable(tableId) {
            const header = document.querySelector(`#${tableId}`).previousElementSibling;
            const content = document.getElementById(tableId);
            
            if (tableId === 'recentChecks') {
                header.classList.toggle('collapsed');
                content.classList.toggle('collapsed');
            } else {
                header.classList.toggle('collapsed');
                content.classList.toggle('collapsed');
                
                if (header.classList.contains('collapsed')) {
                    header.style.backgroundColor = '#003366';
                    header.querySelector('h3').style.color = '#fff';
                } else {
                    header.style.backgroundColor = '#f8f9fa';
                    header.querySelector('h3').style.color = '#333';
                }
            }
        }
    </script>
</body>
</html> 