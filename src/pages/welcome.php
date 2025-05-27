<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get total number of active clients
$stmt = $conn->prepare("SELECT COUNT(*) as total_clients FROM clients WHERE status = 'active'");
$stmt->execute();
$total_clients = $stmt->get_result()->fetch_assoc()['total_clients'];

// Get total number of active vehicles
$stmt = $conn->prepare("SELECT COUNT(*) as total_vehicles FROM clientvehicles WHERE status = 'active'");
$stmt->execute();
$total_vehicles = $stmt->get_result()->fetch_assoc()['total_vehicles'];

// Get upcoming smog tests due (within next 30 days)
$stmt = $conn->prepare("
    SELECT COUNT(*) as upcoming_smog 
    FROM clientvehicles 
    WHERE smog_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute();
$upcoming_smog = $stmt->get_result()->fetch_assoc()['upcoming_smog'];

// Get upcoming clean truck checks due (within next 30 days)
$stmt = $conn->prepare("
    SELECT COUNT(*) as upcoming_clean_truck 
    FROM clientvehicles 
    WHERE clean_truck_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status = 'active'
");
$stmt->execute();
$upcoming_clean_truck = $stmt->get_result()->fetch_assoc()['upcoming_clean_truck'];

// Get today's appointments
$stmt = $conn->prepare("
    SELECT COUNT(*) as today_appointments 
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    WHERE DATE(ce.start_time) = CURDATE()
    AND a.status != 'cancelled'
");
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['today_appointments'];

// Get pending appointments
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_appointments 
    FROM appointments 
    WHERE status = 'pending'
");
$stmt->execute();
$pending_appointments = $stmt->get_result()->fetch_assoc()['pending_appointments'];

// Get recent test results
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN result = 'pass' THEN 1 END) as passed_tests,
        COUNT(CASE WHEN result = 'fail' THEN 1 END) as failed_tests,
        COUNT(CASE WHEN result = 'warmup' THEN 1 END) as warmup_tests
    FROM vehicles 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$test_results = $stmt->get_result()->fetch_assoc();

// Get clean truck check results
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN clean_truck_status = 'completed' THEN 1 END) as completed_checks,
        COUNT(CASE WHEN clean_truck_status = 'failed' THEN 1 END) as failed_checks,
        COUNT(CASE WHEN clean_truck_status = 'pending' THEN 1 END) as pending_checks
    FROM clean_truck_checks 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$clean_truck_results = $stmt->get_result()->fetch_assoc();

// Get revenue data for the last 6 months
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(total_price) as revenue
    FROM appointments
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute();
$revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's appointments details
$stmt = $conn->prepare("
    SELECT a.*, ce.start_time, ce.end_time, c.company_name
    FROM appointments a
    JOIN calendar_events ce ON a.id = ce.appointment_id
    LEFT JOIN clients c ON a.companyid = c.id
    WHERE DATE(ce.start_time) = CURDATE()
    AND a.status != 'cancelled'
    ORDER BY ce.start_time ASC
");
$stmt->execute();
$today_appointments_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get vehicles due for service
$stmt = $conn->prepare("
    SELECT cv.*, c.company_name
    FROM clientvehicles cv
    JOIN clients c ON cv.company_id = c.id
    WHERE (cv.smog_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
           OR cv.clean_truck_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
    AND cv.status = 'active'
    ORDER BY LEAST(cv.smog_due_date, cv.clean_truck_due_date) ASC
    LIMIT 5
");
$stmt->execute();
$due_vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user efficiency metrics
$stmt = $conn->prepare("
    SELECT 
        acc.idaccounts,
        acc.firstName,
        acc.lastName,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
        COUNT(DISTINCT CASE WHEN v.result = 'pass' THEN v.id END) as passed_tests,
        COUNT(DISTINCT CASE WHEN v.result = 'fail' THEN v.id END) as failed_tests,
        COUNT(DISTINCT CASE WHEN ctc.clean_truck_status = 'completed' THEN ctc.id END) as completed_clean_truck_checks,
        COUNT(DISTINCT CASE WHEN ctc.clean_truck_status = 'failed' THEN ctc.id END) as failed_clean_truck_checks,
        COALESCE(SUM(a.total_price), 0) as total_revenue
    FROM accounts acc
    LEFT JOIN appointments a ON a.approved_by = acc.email
    LEFT JOIN vehicles v ON v.appointment_id = a.id
    LEFT JOIN clean_truck_checks ctc ON ctc.appointment_id = a.id
    WHERE acc.status = 'active'
    AND acc.accountType IN (1, 2, 3) -- Assuming these are the user types that perform services
    GROUP BY acc.idaccounts, acc.firstName, acc.lastName
    ORDER BY total_revenue DESC
");
$stmt->execute();
$user_metrics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user appointment distribution
$stmt = $conn->prepare("
    SELECT 
        a.firstName,
        a.lastName,
        COUNT(ce.appointment_id) as appointment_count,
        DATE_FORMAT(ce.start_time, '%Y-%m') as month
    FROM accounts a
    JOIN calendar_events ce ON ce.user_id = a.email
    WHERE ce.appointment_id IS NOT NULL
    AND ce.start_time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY a.firstName, a.lastName, DATE_FORMAT(ce.start_time, '%Y-%m')
    ORDER BY month DESC, appointment_count DESC
");
$stmt->execute();
$user_appointment_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .appointment-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .appointment-card h5 {
            margin-bottom: 10px;
            color: #333;
        }
        .appointment-card p {
            margin-bottom: 5px;
            color: #666;
        }
        .appointment-card .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #cce5ff; color: #004085; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
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
        }
        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .kpi-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .kpi-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .kpi-label {
            color: #666;
            font-size: 0.9rem;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            position: relative;
            height: 250px;
        }
        .chart-container .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .chart-container .expand-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }
        .chart-container .expand-btn:hover {
            color: #007bff;
        }
        .chart-modal .modal-dialog {
            max-width: 800px;
        }
        .chart-modal .modal-body {
            padding: 20px;
        }
        .chart-modal .chart-container {
            height: 500px;
        }
        .due-soon {
            color: #dc3545;
        }
        .upcoming {
            color: #ffc107;
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
                <h3>Sky Smoke Check</h3>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="welcome.php" class="active">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="manage_news.php">
                        <i class="fas fa-newspaper"></i> Manage News
                    </a>
                </li>
                <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                <li>
                    <a href="#" onclick="showComingSoon('All Clients'); return false;">
                        <i class="fas fa-users"></i> All Clients
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('All Appointments'); return false;">
                        <i class="fas fa-calendar-alt"></i> All Appointments
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('Clean Truck Checks'); return false;">
                        <i class="fas fa-truck"></i> Clean Truck Checks
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('Smog Tests'); return false;">
                        <i class="fas fa-smog"></i> Smog Tests
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                    <li>
                        <a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#" onclick="showComingSoon('Settings'); return false;">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="container-fluid">
                <h1 class="mb-4">Dashboard</h1>
                
                <!-- KPI Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-primary">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="kpi-value"><?php echo $total_clients; ?></div>
                            <div class="kpi-label">Active Clients</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-success">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="kpi-value"><?php echo $total_vehicles; ?></div>
                            <div class="kpi-label">Active Vehicles</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-warning">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="kpi-value"><?php echo $today_appointments; ?></div>
                            <div class="kpi-label">Today's Appointments</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-info">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="kpi-value"><?php echo $pending_appointments; ?></div>
                            <div class="kpi-label">Pending Appointments</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="mb-0">Test Results (Last 30 Days)</h5>
                                <button class="expand-btn" onclick="expandChart('testResultsChart')">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <canvas id="testResultsChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="mb-0">Clean Truck Check Results (Last 30 Days)</h5>
                                <button class="expand-btn" onclick="expandChart('cleanTruckChart')">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <canvas id="cleanTruckChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="mb-0">Revenue (Last 6 Months)</h5>
                                <button class="expand-btn" onclick="expandChart('revenueChart')">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- User Appointment Distribution Chart -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="mb-0">User Appointment Distribution (Last 6 Months)</h5>
                                <button class="expand-btn" onclick="expandChart('userDistributionChart')">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <canvas id="userDistributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Services and Today's Appointments -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Vehicles Due for Service</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Company</th>
                                                <th>Vehicle</th>
                                                <th>Due Date</th>
                                                <th>Service</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($due_vehicles as $vehicle): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($vehicle['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make']); ?></td>
                                                <td class="<?php echo strtotime($vehicle['smog_due_date']) <= strtotime('+7 days') ? 'due-soon' : 'upcoming'; ?>">
                                                    <?php 
                                                    $due_date = min(strtotime($vehicle['smog_due_date']), strtotime($vehicle['clean_truck_due_date']));
                                                    echo date('M d, Y', $due_date);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (strtotime($vehicle['smog_due_date']) <= strtotime('+7 days')) {
                                                        echo '<span class="badge bg-warning">Smog Due</span> ';
                                                    }
                                                    if (strtotime($vehicle['clean_truck_due_date']) <= strtotime('+7 days')) {
                                                        echo '<span class="badge bg-info">Clean Truck Due</span>';
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
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Today's Appointments</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Company</th>
                                                <th>Service</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($today_appointments_details as $appointment): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($appointment['start_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['company_name']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($appointment['service_id'] == 1) {
                                                        echo 'Smog Test';
                                                    } elseif ($appointment['service_id'] == 2) {
                                                        echo 'Clean Truck Check';
                                                    } else {
                                                        echo 'Both Services';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $appointment['status'] == 'confirmed' ? 'success' : 
                                                            ($appointment['status'] == 'pending' ? 'warning' : 'secondary');
                                                    ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Modal -->
    <div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chartModalLabel">Chart View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="chart-container" style="height: 500px;">
                        <canvas id="expandedChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Coming Soon Modal -->
    <div class="modal fade" id="comingSoonModal" tabindex="-1" aria-labelledby="comingSoonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="comingSoonModalLabel">Coming Soon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-tools fa-3x mb-3 text-primary"></i>
                    <h4 id="featureName"></h4>
                    <p class="mb-0">This feature is currently under development. Please check back later.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');

            // Check localStorage for sidebar state
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Save state to localStorage
                localStorage.setItem('sidebarState', 
                    sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
                );
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

            // Store chart instances
            const chartInstances = {};
            let expandedChart = null;

            // Function to expand chart
            window.expandChart = function(chartId) {
                const originalChart = chartInstances[chartId];
                if (!originalChart) return;

                const modal = new bootstrap.Modal(document.getElementById('chartModal'));
                const expandedCanvas = document.getElementById('expandedChart');
                
                // Set modal title based on chart type
                const modalTitle = document.getElementById('chartModalLabel');
                switch(chartId) {
                    case 'testResultsChart':
                        modalTitle.textContent = 'Test Results (Last 30 Days)';
                        break;
                    case 'cleanTruckChart':
                        modalTitle.textContent = 'Clean Truck Check Results (Last 30 Days)';
                        break;
                    case 'revenueChart':
                        modalTitle.textContent = 'Revenue (Last 6 Months)';
                        break;
                    case 'userDistributionChart':
                        modalTitle.textContent = 'User Appointment Distribution (Last 6 Months)';
                        break;
                }

                // Create new chart instance in modal
                expandedChart = new Chart(expandedCanvas, {
                    type: originalChart.config.type,
                    data: originalChart.config.data,
                    options: {
                        ...originalChart.config.options,
                        maintainAspectRatio: false,
                        responsive: true
                    }
                });

                modal.show();
            };

            // Clean up expanded chart when modal is closed
            document.getElementById('chartModal').addEventListener('hidden.bs.modal', function () {
                if (expandedChart) {
                    expandedChart.destroy();
                    expandedChart = null;
                }
            });

            // Test Results Chart
            chartInstances['testResultsChart'] = new Chart(document.getElementById('testResultsChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Passed', 'Failed', 'Warm-up'],
                    datasets: [{
                        data: [
                            <?php echo $test_results['passed_tests']; ?>,
                            <?php echo $test_results['failed_tests']; ?>,
                            <?php echo $test_results['warmup_tests']; ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Clean Truck Check Results Chart
            chartInstances['cleanTruckChart'] = new Chart(document.getElementById('cleanTruckChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Failed', 'Pending'],
                    datasets: [{
                        data: [
                            <?php echo $clean_truck_results['completed_checks']; ?>,
                            <?php echo $clean_truck_results['failed_checks']; ?>,
                            <?php echo $clean_truck_results['pending_checks']; ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Revenue Chart
            chartInstances['revenueChart'] = new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($revenue_data, 'month')); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode(array_column($revenue_data, 'revenue')); ?>,
                        borderColor: '#007bff',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // User Appointment Distribution Chart
            const userData = <?php echo json_encode($user_appointment_data); ?>;
            const months = [...new Set(userData.map(item => item.month))].sort();
            const users = [...new Set(userData.map(item => item.firstName + ' ' + item.lastName))];
            
            const datasets = users.map(user => {
                const data = months.map(month => {
                    const record = userData.find(item => 
                        item.month === month && 
                        (item.firstName + ' ' + item.lastName) === user
                    );
                    return record ? record.appointment_count : 0;
                });
                
                return {
                    label: user,
                    data: data,
                    borderColor: `hsl(${Math.random() * 360}, 70%, 50%)`,
                    fill: false
                };
            });

            chartInstances['userDistributionChart'] = new Chart(document.getElementById('userDistributionChart'), {
                type: 'line',
                data: {
                    labels: months.map(month => {
                        const date = new Date(month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Appointments'
                            }
                        }
                    }
                }
            });
        });

        // Function to show coming soon message
        function showComingSoon(featureName) {
            const modal = new bootstrap.Modal(document.getElementById('comingSoonModal'));
            document.getElementById('featureName').textContent = featureName;
            modal.show();
        }
    </script>
</body>
</html> 