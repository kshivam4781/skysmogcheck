<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['accountType'] != 3) {
    header("Location: login.php");
    exit();
}

// Get client ID
$stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$client_id = $client['id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Validate required fields
                $required_fields = ['fleet_number', 'vin', 'plate_no', 'year', 'make', 'model'];
                $is_valid = true;
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        $is_valid = false;
                        break;
                    }
                }

                if ($is_valid) {
                    // Check if vehicle with same VIN already exists
                    $check_stmt = $conn->prepare("SELECT id FROM clientvehicles WHERE vin = ? AND company_id = ?");
                    $check_stmt->bind_param("si", $_POST['vin'], $client_id);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        // Add new vehicle
                        $stmt = $conn->prepare("
                            INSERT INTO clientvehicles (
                                company_id, fleet_number, vin, plate_no, year, make, model,
                                smog_due_date, smog_last_date, clean_truck_due_date, clean_truck_last_date,
                                status, added_by, notes, created_at, updated_at
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?,
                                NULL, NULL, NULL, NULL,
                                'active', ?, NULL, NOW(), NOW()
                            )
                        ");
                        $stmt->bind_param(
                            "isssssss",
                            $client_id,
                            $_POST['fleet_number'],
                            $_POST['vin'],
                            $_POST['plate_no'],
                            $_POST['year'],
                            $_POST['make'],
                            $_POST['model'],
                            $_SESSION['email']
                        );
                        $stmt->execute();
                    }
                }
                // Redirect to prevent form resubmission
                header("Location: vehicle_management.php");
                exit();

            case 'edit':
                // Validate required fields
                $required_fields = ['vehicle_id', 'fleet_number', 'vin', 'plate_no', 'year', 'make', 'model'];
                $is_valid = true;
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        $is_valid = false;
                        break;
                    }
                }

                if ($is_valid) {
                    // Update existing vehicle
                    $stmt = $conn->prepare("
                        UPDATE clientvehicles 
                        SET fleet_number = ?, plate_no = ?, vin = ?, year = ?, make = ?, model = ?
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->bind_param(
                        "ssssssii",
                        $_POST['fleet_number'],
                        $_POST['plate_no'],
                        $_POST['vin'],
                        $_POST['year'],
                        $_POST['make'],
                        $_POST['model'],
                        $_POST['vehicle_id'],
                        $client_id
                    );
                    $stmt->execute();
                }
                // Redirect to prevent form resubmission
                header("Location: vehicle_management.php");
                exit();

            case 'delete':
                if (isset($_POST['vehicle_id'])) {
                    // Soft delete vehicle (update status to inactive)
                    $stmt = $conn->prepare("
                        UPDATE clientvehicles 
                        SET status = 'inactive' 
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->bind_param("ii", $_POST['vehicle_id'], $client_id);
                    $stmt->execute();
                }
                // Redirect to prevent form resubmission
                header("Location: vehicle_management.php");
                exit();
        }
    }
}

// Get all vehicles for the client
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
    ORDER BY cv.created_at DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management - Sky Smoke Check</title>
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

        .header-section {
            background: url('../assets/images/truck-bg.gif') center/cover;
            color: white;
            padding: 60px 0;
            position: relative;
            margin-bottom: 50px;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .header-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .vehicle-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            background: white;
            overflow: hidden;
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .action-buttons {
            opacity: 0;
            transition: opacity 0.3s ease;
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 5px;
            border-radius: 8px;
        }

        .vehicle-card:hover .action-buttons {
            opacity: 1;
        }

        .btn-add-vehicle {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-add-vehicle:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .vehicle-status {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #2ecc71;
            color: white;
        }

        .status-inactive {
            background: #e74c3c;
            color: white;
        }

        .status-sold {
            background: #f1c40f;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-right: 5px;
        }

        .test-status {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .test-status-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .test-status-label {
            font-weight: bold;
            color: var(--primary-color);
            margin-right: 10px;
            min-width: 120px;
        }

        .test-status-value {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }

        .status-pending {
            background: #f1c40f;
            color: white;
        }

        .status-completed {
            background: #2ecc71;
            color: white;
        }

        .status-not-started {
            background: #95a5a6;
            color: white;
        }

        .vehicle-details {
            padding: 20px;
        }

        .vehicle-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .vehicle-info {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .vehicle-info strong {
            color: var(--primary-color);
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

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            border-radius: 0 0 15px 15px;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
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
                        <a class="nav-link" href="client_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="vehicle_management.php">
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
                        <?php echo strtoupper(substr($client['firstName'] ?? '', 0, 1) . substr($client['lastName'] ?? '', 0, 1)); ?>
                    </div>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? '')); ?>
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

    <!-- Header Section -->
    <section class="header-section">
        <div class="container">
            <div class="header-content text-center">
                <h1 class="header-title">Vehicle Management</h1>
                <p class="header-subtitle">Manage your fleet and track compliance status</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary">Your Fleet</h2>
            <button class="btn btn-add-vehicle" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                <i class="fas fa-plus"></i> Add New Vehicle
            </button>
        </div>

        <!-- Status Filter Buttons -->
        <div class="mb-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="filterVehicles('all')">All</button>
                <button type="button" class="btn btn-outline-success" onclick="filterVehicles('active')">Active</button>
                <button type="button" class="btn btn-outline-danger" onclick="filterVehicles('inactive')">Inactive</button>
            </div>
        </div>

        <!-- Vehicle Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="vehicleGrid">
            <?php foreach ($vehicles as $vehicle): ?>
            <div class="col vehicle-item" data-status="<?php echo strtolower($vehicle['status']); ?>">
                <div class="vehicle-card">
                    <div class="vehicle-status status-<?php echo strtolower($vehicle['status']); ?>">
                        <?php echo ucfirst($vehicle['status']); ?>
                    </div>
                    <div class="vehicle-details">
                        <div class="vehicle-title">
                            <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                        </div>
                        <div class="vehicle-info">
                            <strong>Fleet Number:</strong> <?php echo htmlspecialchars($vehicle['fleet_number']); ?><br>
                            <strong>Plate Number:</strong> <?php echo htmlspecialchars($vehicle['plate_no']); ?><br>
                            <strong>VIN:</strong> <?php echo htmlspecialchars($vehicle['vin']); ?><br>
                            
                            <div class="test-status">
                                <strong>Service Type:</strong> 
                                <?php 
                                if ($vehicle['service_id'] == 1) {
                                    echo '<span class="status-badge status-active">Clean Truck Check</span>';
                                } elseif ($vehicle['service_id'] == 2) {
                                    echo '<span class="status-badge status-active">Smoke Test</span>';
                                } elseif ($vehicle['service_id'] == 3) {
                                    echo '<span class="status-badge status-active">Both Tests</span>';
                                } else {
                                    echo '<span class="status-badge status-not-started">Not Assigned</span>';
                                }
                                ?>
                                <br><br>
                                <strong>Test Status:</strong><br>
                                <?php 
                                if ($vehicle['service_id'] == 1) {
                                    echo '<div class="test-status-item">';
                                    echo '<span class="test-status-label">Clean Truck:</span>';
                                    echo '<span class="test-status-value status-' . strtolower($vehicle['clean_truck_check_status'] ?? 'not-started') . '">';
                                    echo ucfirst($vehicle['clean_truck_check_status'] ?? 'Not Started');
                                    echo '</span></div>';
                                } elseif ($vehicle['service_id'] == 2) {
                                    echo '<div class="test-status-item">';
                                    echo '<span class="test-status-label">Smoke Test:</span>';
                                    echo '<span class="test-status-value status-' . strtolower($vehicle['smoke_test_status'] ?? 'not-started') . '">';
                                    echo ucfirst($vehicle['smoke_test_status'] ?? 'Not Started');
                                    echo '</span></div>';
                                } elseif ($vehicle['service_id'] == 3) {
                                    echo '<div class="test-status-item">';
                                    echo '<span class="test-status-label">Clean Truck:</span>';
                                    echo '<span class="test-status-value status-' . strtolower($vehicle['clean_truck_check_status'] ?? 'not-started') . '">';
                                    echo ucfirst($vehicle['clean_truck_check_status'] ?? 'Not Started');
                                    echo '</span></div>';
                                    echo '<div class="test-status-item">';
                                    echo '<span class="test-status-label">Smoke Test:</span>';
                                    echo '<span class="test-status-value status-' . strtolower($vehicle['smoke_test_status'] ?? 'not-started') . '">';
                                    echo ucfirst($vehicle['smoke_test_status'] ?? 'Not Started');
                                    echo '</span></div>';
                                } else {
                                    echo '<div class="test-status-item">';
                                    echo '<span class="test-status-value status-not-started">No Tests Assigned</span>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-outline-primary" onclick="editVehicle(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteVehicle(<?php echo $vehicle['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add truck animation -->
    <div class="truck-animation"></div>

    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Fleet Number</label>
                            <input type="text" class="form-control" name="fleet_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plate Number</label>
                            <input type="text" class="form-control" name="plate_no" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">VIN</label>
                            <input type="text" class="form-control" name="vin" required maxlength="17">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <input type="number" class="form-control" name="year" required min="1900" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Make</label>
                            <input type="text" class="form-control" name="make" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div class="modal fade" id="editVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                        <div class="mb-3">
                            <label class="form-label">Fleet Number</label>
                            <input type="text" class="form-control" name="fleet_number" id="edit_fleet_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plate Number</label>
                            <input type="text" class="form-control" name="plate_no" id="edit_plate_no" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">VIN</label>
                            <input type="text" class="form-control" name="vin" id="edit_vin" required maxlength="17">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <input type="number" class="form-control" name="year" id="edit_year" required min="1900" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Make</label>
                            <input type="text" class="form-control" name="make" id="edit_make" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" id="edit_model" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Vehicle Form -->
    <form id="deleteVehicleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editVehicle(vehicle) {
        document.getElementById('edit_vehicle_id').value = vehicle.id;
        document.getElementById('edit_fleet_number').value = vehicle.fleet_number;
        document.getElementById('edit_plate_no').value = vehicle.plate_no;
        document.getElementById('edit_vin').value = vehicle.vin;
        document.getElementById('edit_year').value = vehicle.year;
        document.getElementById('edit_make').value = vehicle.make;
        document.getElementById('edit_model').value = vehicle.model;
        
        new bootstrap.Modal(document.getElementById('editVehicleModal')).show();
    }

    function deleteVehicle(vehicleId) {
        if (confirm('Are you sure you want to delete this vehicle?')) {
            document.getElementById('delete_vehicle_id').value = vehicleId;
            document.getElementById('deleteVehicleForm').submit();
        }
    }

    function filterVehicles(status) {
        // Update active button state
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Filter vehicles
        const vehicles = document.querySelectorAll('.vehicle-item');
        vehicles.forEach(vehicle => {
            if (status === 'all' || vehicle.dataset.status === status) {
                vehicle.style.display = '';
            } else {
                vehicle.style.display = 'none';
            }
        });
    }

    // Add this to your existing JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Show all vehicles by default
        filterVehicles('all');
    });
    </script>
</body>
</html> 