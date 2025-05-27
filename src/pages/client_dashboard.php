<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['accountType'] != 3) {
    header("Location: login.php");
    exit();
}

// Get user's name
$firstName = $_SESSION['first_name'];
$lastName = $_SESSION['last_name'];

// Get client's active vehicles count
$activeVehicles = 0;
$pendingTests = 0;
$completedTests = 0;
$pendingTestsDetails = [];

try {
    // Get client ID from email
    $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $client_id = $row['id'];
        
        // Get active vehicles count
        $stmt = $conn->prepare("
            SELECT COUNT(*) as active_count 
            FROM clientvehicles 
            WHERE company_id = ? AND status = 'active'
        ");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $activeVehicles = $row['active_count'];
        }

        // Get pending tests count and details
        $debug_info = array();
        $debug_info['client_id'] = $client_id;
        $debug_info['client_email'] = $_SESSION['email'];

        // First check if we can get client vehicles
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM clientvehicles WHERE company_id = ?");
        $check_stmt->bind_param("i", $client_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $client_vehicles_count = $check_result->fetch_assoc()['count'];
        $debug_info['total_client_vehicles'] = $client_vehicles_count;

        // Get sample of client vehicles for debugging
        $sample_stmt = $conn->prepare("SELECT vin FROM clientvehicles WHERE company_id = ? LIMIT 1");
        $sample_stmt->bind_param("i", $client_id);
        $sample_stmt->execute();
        $sample_result = $sample_stmt->get_result();
        if ($sample_row = $sample_result->fetch_assoc()) {
            $debug_info['sample_client_vin'] = $sample_row['vin'];
        }

        // Check vehicles table for pending tests
        $check_vehicles = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM vehicles 
            WHERE clean_truck_check_status = 'pending' 
            OR smoke_test_status = 'pending'
        ");
        $check_vehicles->execute();
        $vehicles_result = $check_vehicles->get_result();
        $total_pending_vehicles = $vehicles_result->fetch_assoc()['count'];
        $debug_info['total_pending_vehicles'] = $total_pending_vehicles;

        // Get sample of pending vehicles for debugging
        $sample_vehicles = $conn->prepare("
            SELECT vin, service_id, clean_truck_check_status, smoke_test_status 
            FROM vehicles 
            WHERE clean_truck_check_status = 'pending' 
            OR smoke_test_status = 'pending'
            LIMIT 1
        ");
        $sample_vehicles->execute();
        $sample_vehicles_result = $sample_vehicles->get_result();
        if ($sample_vehicle = $sample_vehicles_result->fetch_assoc()) {
            $debug_info['sample_pending_vehicle'] = $sample_vehicle;
        }

        // Get pending tests count and details with error checking
        try {
            $stmt = $conn->prepare("
                SELECT v.*, cv.plate_no, cv.year, cv.make, cv.model,
                       CASE 
                           WHEN v.service_id = 1 THEN 'Clean Truck Check'
                           WHEN v.service_id = 2 THEN 'Smoke Test'
                           WHEN v.service_id = 3 THEN 'Both Tests'
                       END as service_type
                FROM vehicles v
                JOIN clientvehicles cv ON v.vin COLLATE utf8mb4_unicode_ci = cv.vin COLLATE utf8mb4_unicode_ci
                WHERE cv.company_id = ? 
                AND cv.status = 'active'
                AND (
                    (v.service_id = 1 AND v.clean_truck_check_status = 'pending')
                    OR 
                    (v.service_id = 2 AND v.smoke_test_status = 'pending')
                    OR 
                    (v.service_id = 3 AND (v.clean_truck_check_status = 'pending' OR v.smoke_test_status = 'pending'))
                )
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $client_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if (!$result) {
                throw new Exception("Get result failed: " . $stmt->error);
            }
            
            $debug_info['query_result_count'] = $result->num_rows;
            
            if ($result->num_rows > 0) {
                $firstRow = $result->fetch_assoc();
                $debug_info['sample_data'] = $firstRow;
                $result->data_seek(0);
            }
            
            $pendingTests = $result->num_rows;
            $pendingTestsDetails = $result->fetch_all(MYSQLI_ASSOC);
            
            $debug_info['final_pending_count'] = $pendingTests;
            $debug_info['final_details_count'] = count($pendingTestsDetails);
            
        } catch (Exception $e) {
            $debug_info['error'] = $e->getMessage();
            $pendingTests = 0;
            $pendingTestsDetails = array();
        }

        // Log the debug information
        error_log("Debug Information: " . print_r($debug_info, true));

        // Get completed tests count (vehicles with completed tests)
        $stmt = $conn->prepare("
            SELECT v.*, cv.plate_no, cv.year, cv.make, cv.model,
                   CASE 
                       WHEN v.service_id = 1 THEN 'Clean Truck Check'
                       WHEN v.service_id = 2 THEN 'Smoke Test'
                       WHEN v.service_id = 3 THEN 'Both Tests'
                   END as service_type
            FROM vehicles v
            JOIN clientvehicles cv ON v.vin COLLATE utf8mb4_unicode_ci = cv.vin COLLATE utf8mb4_unicode_ci
            WHERE cv.company_id = ? 
            AND cv.status = 'active'
            AND (
                (v.service_id = 1 AND v.clean_truck_check_status != 'pending' AND v.clean_truck_check_status IS NOT NULL)
                OR 
                (v.service_id = 2 AND v.smoke_test_status != 'pending' AND v.smoke_test_status IS NOT NULL)
                OR 
                (v.service_id = 3 AND 
                    ((v.clean_truck_check_status != 'pending' AND v.clean_truck_check_status IS NOT NULL)
                    OR 
                    (v.smoke_test_status != 'pending' AND v.smoke_test_status IS NOT NULL))
                )
            )
        ");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $completedTests = $result->num_rows;
        $completedTestsDetails = $result->fetch_all(MYSQLI_ASSOC);

        // Add completed tests to debug info
        $debug_info['completed_tests_count'] = $completedTests;
        if ($completedTests > 0) {
            $debug_info['sample_completed_test'] = $completedTestsDetails[0];
        }

        // Update the completed tests modal to show details
        $debug_info['completed_tests_details'] = $completedTestsDetails;

        // Check for matching VINs between tables
        $vin_check = $conn->prepare("
            SELECT COUNT(*) as matching_count 
            FROM vehicles v 
            JOIN clientvehicles cv ON v.vin COLLATE utf8mb4_unicode_ci = cv.vin COLLATE utf8mb4_unicode_ci 
            WHERE cv.company_id = ?
        ");
        $vin_check->bind_param("i", $client_id);
        $vin_check->execute();
        $vin_result = $vin_check->get_result();
        $matching_vins = $vin_result->fetch_assoc()['matching_count'];
        $debug_info['matching_vins_count'] = $matching_vins;

        // Get sample of matching VINs
        $sample_matching = $conn->prepare("
            SELECT v.vin as vehicle_vin, cv.vin as client_vin, 
                   v.service_id, v.clean_truck_check_status, v.smoke_test_status
            FROM vehicles v 
            JOIN clientvehicles cv ON v.vin COLLATE utf8mb4_unicode_ci = cv.vin COLLATE utf8mb4_unicode_ci 
            WHERE cv.company_id = ?
            LIMIT 1
        ");
        $sample_matching->bind_param("i", $client_id);
        $sample_matching->execute();
        $sample_matching_result = $sample_matching->get_result();
        if ($matching_sample = $sample_matching_result->fetch_assoc()) {
            $debug_info['sample_matching_vin'] = $matching_sample;
        }

        // Get active vehicles with details
        $stmt = $conn->prepare("
            SELECT cv.*, 
                   v.service_id,
                   v.clean_truck_check_status,
                   v.smoke_test_status,
                   CASE 
                       WHEN v.service_id = 1 THEN 'Clean Truck Check'
                       WHEN v.service_id = 2 THEN 'Smoke Test'
                       WHEN v.service_id = 3 THEN 'Both Tests'
                   END as service_type
            FROM clientvehicles cv
            LEFT JOIN vehicles v ON cv.vin COLLATE utf8mb4_unicode_ci = v.vin COLLATE utf8mb4_unicode_ci
            WHERE cv.company_id = ? 
            AND cv.status = 'active'
        ");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $activeVehiclesDetails = $result->fetch_all(MYSQLI_ASSOC);
        
        // Add active vehicles to debug info
        $debug_info['active_vehicles_details'] = $activeVehiclesDetails;
    }
} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Sky Smoke Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            position: relative;
        }

        .welcome-section {
            background: url('../assets/images/truck-bg.gif') center/cover;
            color: white;
            padding: 100px 0;
            position: relative;
            margin-bottom: 50px;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-message {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .welcome-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-icon {
            font-size: 2.5rem;
            color: var(--secondary-color);
            margin-bottom: 20px;
        }

        .card-title {
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 15px;
        }

        .card-text {
            color: #666;
            line-height: 1.6;
        }

        .btn-custom {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            background: var(--primary-color);
            transform: scale(1.05);
        }

        .truck-animation {
            position: fixed;
            bottom: -50px;
            right: 20px;
            width: 400px;
            height: 200px;
            background: url('../assets/images/truck-animation.gif') no-repeat center/contain;
            animation: none;
            transform: scale(0.7);
            transform-origin: center center;
            z-index: 1000;
        }

        .stats-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary-color);
        }

        .stat-label {
            color: #666;
            margin-top: 10px;
        }

        .navbar {
            background: var(--primary-color);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            border-radius: 5px;
            margin: 0 5px;
        }

        .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            color: white !important;
            background: var(--secondary-color);
        }

        .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .dropdown-item {
            padding: 0.7rem 1.5rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
            color: var(--primary-color);
        }

        .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="client_dashboard.php">
                <i class="fas fa-truck"></i> Sky Smoke Check
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="client_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicle_management.php">
                            <i class="fas fa-truck"></i> Vehicles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-calendar-alt"></i> Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-file-alt"></i> Reports
                        </a>
                    </li>
                </ul>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)); ?>
                    </div>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Welcome Section -->
    <section class="welcome-section">
        <div class="container">
            <div class="welcome-content text-center">
                <h1 class="welcome-message">Welcome to the Sky</h1>
                <p class="welcome-subtitle">One stop solution to all sky clean truck and smog. Forever forget the worry of forgetting with any of the compliance.</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Stats Section -->
        <div class="stats-section">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item" style="cursor: pointer;" onclick="showActiveVehicles()">
                        <div class="stat-number"><?php echo $activeVehicles; ?></div>
                        <div class="stat-label">Active Vehicles</div>
                        <?php if ($activeVehicles > 0): ?>
                        <small class="text-muted">Click to view details</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item" style="cursor: pointer;" onclick="showPendingTests()">
                        <div class="stat-number"><?php echo $pendingTests; ?></div>
                        <div class="stat-label">Pending Tests</div>
                        <?php if ($pendingTests > 0): ?>
                        <small class="text-muted">Click to view details</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item" style="cursor: pointer;" onclick="showCompletedTests()">
                        <div class="stat-number"><?php echo $completedTests; ?></div>
                        <div class="stat-label">Completed Tests</div>
                        <?php if ($completedTests > 0): ?>
                        <small class="text-muted">Click to view details</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="dashboard-card p-4 text-center" style="cursor: pointer;" onclick="window.location.href='vehicle_management.php'">
                    <i class="fas fa-truck card-icon"></i>
                    <h3 class="card-title">Vehicle Management</h3>
                    <p class="card-text">Manage your fleet of vehicles and track their compliance status.</p>
                    <button class="btn btn-custom mt-3">View Vehicles</button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card p-4 text-center">
                    <i class="fas fa-calendar-alt card-icon"></i>
                    <h3 class="card-title">Appointments</h3>
                    <p class="card-text">Schedule and manage your testing appointments.</p>
                    <button class="btn btn-custom mt-3">Schedule Test</button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card p-4 text-center">
                    <i class="fas fa-file-alt card-icon"></i>
                    <h3 class="card-title">Reports</h3>
                    <p class="card-text">Access your test results and compliance reports.</p>
                    <button class="btn btn-custom mt-3">View Reports</button>
                </div>
            </div>
        </div>

        <!-- Pending Tests Modal -->
        <div class="modal fade" id="pendingTestsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Pending Tests Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($pendingTestsDetails)): ?>
                            <div class="alert alert-info">No pending tests found.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Plate Number</th>
                                        <th>Vehicle</th>
                                        <th>VIN</th>
                                        <th>Service Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTestsDetails as $test): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($test['plate_no']); ?></td>
                                        <td><?php echo htmlspecialchars($test['year'] . ' ' . $test['make'] . ' ' . $test['model']); ?></td>
                                        <td><?php echo htmlspecialchars($test['vin']); ?></td>
                                        <td><?php echo htmlspecialchars($test['service_type']); ?></td>
                                        <td>
                                            <?php 
                                            if ($test['service_id'] == 1) {
                                                echo 'Clean Truck Check: ' . ucfirst($test['clean_truck_check_status']);
                                            } elseif ($test['service_id'] == 2) {
                                                echo 'Smoke Test: ' . ucfirst($test['smoke_test_status']);
                                            } else {
                                                echo 'Clean Truck: ' . ucfirst($test['clean_truck_check_status']) . '<br>';
                                                echo 'Smoke Test: ' . ucfirst($test['smoke_test_status']);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Completed Tests Modal -->
        <div class="modal fade" id="completedTestsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Completed Tests Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($completedTestsDetails)): ?>
                            <div class="alert alert-info">No completed tests found.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Plate Number</th>
                                        <th>Vehicle</th>
                                        <th>VIN</th>
                                        <th>Service Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedTestsDetails as $test): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($test['plate_no']); ?></td>
                                        <td><?php echo htmlspecialchars($test['year'] . ' ' . $test['make'] . ' ' . $test['model']); ?></td>
                                        <td><?php echo htmlspecialchars($test['vin']); ?></td>
                                        <td><?php echo htmlspecialchars($test['service_type']); ?></td>
                                        <td>
                                            <?php 
                                            if ($test['service_id'] == 1) {
                                                echo 'Clean Truck Check: ' . ucfirst($test['clean_truck_check_status']);
                                            } elseif ($test['service_id'] == 2) {
                                                echo 'Smoke Test: ' . ucfirst($test['smoke_test_status']);
                                            } else {
                                                echo 'Clean Truck: ' . ucfirst($test['clean_truck_check_status']) . '<br>';
                                                echo 'Smoke Test: ' . ucfirst($test['smoke_test_status']);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Active Vehicles Modal -->
        <div class="modal fade" id="activeVehiclesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Active Vehicles Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($activeVehiclesDetails)): ?>
                            <div class="alert alert-info">No active vehicles found.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Plate Number</th>
                                        <th>Vehicle</th>
                                        <th>VIN</th>
                                        <th>Service Type</th>
                                        <th>Test Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeVehiclesDetails as $vehicle): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vehicle['plate_no']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['vin']); ?></td>
                                        <td>
                                            <?php 
                                            if ($vehicle['service_id'] == 1) {
                                                echo 'Clean Truck Check';
                                            } elseif ($vehicle['service_id'] == 2) {
                                                echo 'Smoke Test';
                                            } elseif ($vehicle['service_id'] == 3) {
                                                echo 'Both Tests';
                                            } else {
                                                echo 'Not Assigned';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($vehicle['service_id'] == 1) {
                                                echo 'Clean Truck: ' . ucfirst($vehicle['clean_truck_check_status'] ?? 'Not Started');
                                            } elseif ($vehicle['service_id'] == 2) {
                                                echo 'Smoke Test: ' . ucfirst($vehicle['smoke_test_status'] ?? 'Not Started');
                                            } elseif ($vehicle['service_id'] == 3) {
                                                echo 'Clean Truck: ' . ucfirst($vehicle['clean_truck_check_status'] ?? 'Not Started') . '<br>';
                                                echo 'Smoke Test: ' . ucfirst($vehicle['smoke_test_status'] ?? 'Not Started');
                                            } else {
                                                echo 'No Tests Assigned';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Schedule Test Modal -->
        <div class="modal fade" id="scheduleTestModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Schedule Test</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="scheduleTestForm" method="POST" action="process_test_schedule.php">
                        <div class="modal-body">
                            <!-- Step 1: Vehicle Selection -->
                            <div id="step1" class="schedule-step">
                                <h6 class="mb-3">Step 1: Select Vehicle</h6>
                                <div class="form-group">
                                    <label class="form-label required-field">Select Vehicle</label>
                                    <select class="form-control" name="vehicle_id" id="vehicleSelect" required>
                                        <option value="">Choose a vehicle...</option>
                                        <?php foreach ($activeVehiclesDetails as $vehicle): ?>
                                        <option value="<?php echo $vehicle['id']; ?>" 
                                                data-vin="<?php echo htmlspecialchars($vehicle['vin']); ?>"
                                                data-year="<?php echo htmlspecialchars($vehicle['year']); ?>"
                                                data-make="<?php echo htmlspecialchars($vehicle['make']); ?>"
                                                data-model="<?php echo htmlspecialchars($vehicle['model']); ?>"
                                                data-plate="<?php echo htmlspecialchars($vehicle['plate_no']); ?>">
                                            <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_no'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="showStep2()">Next</button>
                                </div>
                            </div>

                            <!-- Step 2: Service Selection -->
                            <div id="step2" class="schedule-step" style="display: none;">
                                <h6 class="mb-3">Step 2: Select Service</h6>
                                <div class="form-group">
                                    <label class="form-label required-field">Service Type</label>
                                    <select class="form-control" name="service_type" id="serviceSelect" required onchange="toggleTestLocation(this)">
                                        <option value="">Select Service</option>
                                        <?php 
                                        $services = $conn->query("SELECT * FROM services ORDER BY name");
                                        while ($service = $services->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $service['id']; ?>" 
                                                data-price="<?php echo $service['price']; ?>">
                                            <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <!-- Test Location Section (for SMOG test or Both services) -->
                                <div id="testLocationSection" class="mt-3" style="display: none;">
                                    <div class="form-group">
                                        <label class="form-label required-field">Test Date</label>
                                        <input type="date" class="form-control" name="test_date" id="testDate" 
                                               min="<?php echo date('Y-m-d'); ?>" onchange="updateTimeSlots(this)">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label class="form-label required-field">Test Time</label>
                                        <select class="form-control" name="test_time" id="testTime" disabled>
                                            <option value="">Select Time</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Smog Check Section (for Clean Truck Check) -->
                                <div id="smogCheckSection" class="mt-3" style="display: none;">
                                    <div class="form-group">
                                        <label class="form-label">SMOG Check Status</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="smog_check_completed" value="yes" id="smogCompleted">
                                            <label class="form-check-label" for="smogCompleted">
                                                SMOG Check Completed
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="smog_check_completed" value="no" id="smogNotCompleted">
                                            <label class="form-check-label" for="smogNotCompleted">
                                                SMOG Check Not Completed
                                            </label>
                                        </div>
                                    </div>
                                    <div id="smogDetails" class="mt-3" style="display: none;">
                                        <div class="form-group">
                                            <label class="form-label">Verification Status</label>
                                            <select class="form-control" name="smog_check_verified">
                                                <option value="yes">Verified</option>
                                                <option value="no">Not Verified</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="smogPending" class="mt-3" style="display: none;">
                                        <div class="form-group">
                                            <label class="form-label">Reason for Pending</label>
                                            <input type="text" class="form-control" name="smog_check_pending_reason">
                                        </div>
                                        <div class="form-group mt-3">
                                            <label class="form-label">Expected Completion Date</label>
                                            <input type="date" class="form-control" name="smog_check_expected_date" 
                                                   min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end mt-3">
                                    <button type="button" class="btn btn-secondary me-2" onclick="showStep1()">Back</button>
                                    <button type="submit" class="btn btn-primary">Schedule Test</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add truck animation at the bottom -->
    <div class="truck-animation"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add this at the beginning of your script section
    document.addEventListener('DOMContentLoaded', function() {
        // Show all vehicles by default
        filterVehicles('all');

        // Add click handler for Schedule Test button
        const scheduleTestBtn = document.querySelector('.dashboard-card:nth-child(2) .btn-custom');
        if (scheduleTestBtn) {
            scheduleTestBtn.addEventListener('click', function() {
                const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleTestModal'));
                scheduleModal.show();
            });
        }
    });

    function showPendingTests() {
        var pendingTestsModal = new bootstrap.Modal(document.getElementById('pendingTestsModal'));
        pendingTestsModal.show();
    }

    function showCompletedTests() {
        var completedTestsModal = new bootstrap.Modal(document.getElementById('completedTestsModal'));
        completedTestsModal.show();
    }

    function showActiveVehicles() {
        var activeVehiclesModal = new bootstrap.Modal(document.getElementById('activeVehiclesModal'));
        activeVehiclesModal.show();
    }

    function showStep1() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';
    }

    function showStep2() {
        const vehicleSelect = document.getElementById('vehicleSelect');
        if (!vehicleSelect.value) {
            alert('Please select a vehicle first');
            return;
        }
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
    }

    function toggleTestLocation(selectElement) {
        const serviceId = selectElement.value;
        const testLocationSection = document.getElementById('testLocationSection');
        const smogCheckSection = document.getElementById('smogCheckSection');
        
        // Show smog check section only for Clean Truck Check (1)
        if (serviceId === '1') {
            smogCheckSection.style.display = 'block';
            testLocationSection.style.display = 'none';
        } else {
            smogCheckSection.style.display = 'none';
            // Show test location section for SMOG test (2) or Both services (3)
            if (serviceId === '2' || serviceId === '3') {
                testLocationSection.style.display = 'block';
            } else {
                testLocationSection.style.display = 'none';
            }
        }
    }

    // Handle SMOG check radio buttons
    document.querySelectorAll('input[name="smog_check_completed"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const smogDetails = document.getElementById('smogDetails');
            const smogPending = document.getElementById('smogPending');
            
            if (this.value === 'yes') {
                smogDetails.style.display = 'block';
                smogPending.style.display = 'none';
            } else {
                smogDetails.style.display = 'none';
                smogPending.style.display = 'block';
            }
        });
    });

    // Update time slots when date is selected
    function updateTimeSlots(dateInput) {
        const timeSelect = document.getElementById('testTime');
        const selectedDate = dateInput.value;
        const serviceSelect = document.getElementById('serviceSelect');
        const serviceId = serviceSelect.value;

        // Only proceed if service is SMOG test (2) or Both services (3)
        if (serviceId !== '2' && serviceId !== '3') {
            timeSelect.innerHTML = '<option value="">Select Time</option>';
            timeSelect.disabled = true;
            return;
        }

        if (!selectedDate) {
            timeSelect.innerHTML = '<option value="">Select Time</option>';
            timeSelect.disabled = true;
            return;
        }

        // Show loading state
        timeSelect.innerHTML = '<option value="">Loading available times...</option>';
        timeSelect.disabled = true;

        // Fetch available time slots
        fetch(`get_available_times.php?date=${selectedDate}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">Select Time</option>';
                if (data.available_slots && data.available_slots.length > 0) {
                    data.available_slots.forEach(slot => {
                        options += `<option value="${slot.value}">${slot.display}</option>`;
                    });
                    timeSelect.disabled = false;
                } else {
                    options += '<option value="" disabled>No available times for this date</option>';
                    timeSelect.disabled = true;
                }
                timeSelect.innerHTML = options;
            })
            .catch(error => {
                console.error('Error:', error);
                timeSelect.innerHTML = '<option value="">Error loading times</option>';
                timeSelect.disabled = true;
            });
    }
    </script>
</body>
</html> 