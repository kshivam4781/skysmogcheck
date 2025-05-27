<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? null;
if (!$appointment_id) {
    // Get the referrer URL or default to calendar.php
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'calendar.php';
    header("Location: " . $referrer);
    exit();
}

// Fetch appointment details
$stmt = $conn->prepare("
    SELECT a.*, ce.created_at as confirmed_date,
    CONCAT(acc.firstName, ' ', acc.lastName) as consultant_name,
    acc.email as consultant_email
    FROM appointments a
    LEFT JOIN calendar_events ce ON a.id = ce.appointment_id
    LEFT JOIN accounts acc ON a.approved_by = acc.email
    WHERE a.id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

// Fetch vehicles
$stmt = $conn->prepare("
    SELECT v.*, 
           GROUP_CONCAT(DISTINCT CASE 
               WHEN v.service_id = 1 THEN 'Clean Truck Check'
               WHEN v.service_id = 2 THEN 'Smog Test'
               WHEN v.service_id = 3 THEN 'Smog Test and Clean Truck Check'
               ELSE 'Unknown Service'
           END) as services,
           GROUP_CONCAT(DISTINCT ctc.clean_truck_status) as service_statuses,
           GROUP_CONCAT(DISTINCT ctc.smog_check_status) as smog_statuses,
           GROUP_CONCAT(DISTINCT ctc.smog_check_pending_reason) as service_notes
    FROM vehicles v
    LEFT JOIN clean_truck_checks ctc ON v.id = ctc.vehicle_id
    WHERE v.appointment_id = ?
    GROUP BY v.id
    ORDER BY v.id ASC
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch calendar event status
$stmt = $conn->prepare("
    SELECT status FROM calendar_events WHERE appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$calendar_event = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Sky Smoke Check LLC</title>
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
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 10px;
        }
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s;
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
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .dashboard-container {
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 15px 20px;
        }
        .card-body {
            padding: 20px;
        }
        .info-label {
            color: #6c757d;
            font-weight: 500;
        }
        .info-value {
            color: #2c3e50;
        }
        .vehicle-table th {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
            font-size: 0.9rem;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-completed {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-default {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        .consultant-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
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
            <main class="container mt-5">
                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-container">
                            <div class="header-section">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2 class="mb-0">Appointment Details</h2>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                        <?php if ($calendar_event): ?>
                                            <span class="status-badge status-<?php echo strtolower($calendar_event['status']); ?>">
                                                Calendar: <?php echo ucfirst($calendar_event['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo htmlspecialchars($appointment['companyName']); ?></h4>
                                        <p class="text-muted mb-0">Appointment #<?php echo $appointment['id']; ?></p>
                                    </div>
                                    <div class="text-end">
                                        <p class="mb-0"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($appointment['bookingDate'])); ?></p>
                                        <p class="mb-0"><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['bookingTime'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <!-- Back Button -->
                                <div class="col-12 mb-3">
                                    <a href="javascript:history.back()" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Go Back
                                    </a>
                                </div>
                                <!-- Appointment Details Card -->
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="mb-0">Appointment Information</h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <p class="mb-2">
                                                        <span class="info-label">Appointment Number:</span>
                                                        <span class="info-value">#<?php echo htmlspecialchars($appointment['id']); ?></span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="info-label">Created Date:</span>
                                                        <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['created_at'])); ?></span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="info-label">Confirmed Date:</span>
                                                        <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['confirmed_date'])); ?></span>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-2">
                                                        <span class="info-label">Company Name:</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($appointment['companyName']); ?></span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="info-label">Contact Name:</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($appointment['Name']); ?></span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="info-label">Contact Email:</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($appointment['email']); ?></span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="info-label">Contact Phone:</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Vehicles Table -->
                                            <div class="table-responsive">
                                                <table class="table table-bordered vehicle-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Make</th>
                                                            <th>Year</th>
                                                            <th>VIN</th>
                                                            <th>Plate Number</th>
                                                            <th>Services</th>
                                                            <th>Status</th>
                                                            <th>Notes</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($vehicles as $vehicle): 
                                                            $services = explode(',', $vehicle['services'] ?? '');
                                                            $service_statuses = explode(',', $vehicle['service_statuses'] ?? '');
                                                            $service_notes = explode(',', $vehicle['service_notes'] ?? '');
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($vehicle['vehMake']); ?></td>
                                                            <td><?php echo htmlspecialchars($vehicle['vehYear']); ?></td>
                                                            <td><?php echo htmlspecialchars($vehicle['vin']); ?></td>
                                                            <td><?php echo htmlspecialchars($vehicle['plateNo']); ?></td>
                                                            <td>
                                                                <?php if (!empty($services[0])): ?>
                                                                    <ul class="list-unstyled mb-0">
                                                                        <?php foreach ($services as $index => $service): ?>
                                                                            <li>
                                                                                <strong><?php echo htmlspecialchars($service); ?>:</strong>
                                                                                <?php if (strpos($service, 'Clean Truck') !== false): ?>
                                                                                    <span class="status-badge status-<?php echo strtolower($service_statuses[$index] ?? 'pending'); ?>">
                                                                                        <?php echo ucfirst($service_statuses[$index] ?? 'Pending'); ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                                <?php if (strpos($service, 'Smog') !== false): ?>
                                                                                    <span class="status-badge status-<?php echo strtolower($smog_statuses[$index] ?? 'pending'); ?>">
                                                                                        <?php echo ucfirst($smog_statuses[$index] ?? 'Pending'); ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php else: ?>
                                                                    <span class="text-muted">No services assigned</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $status_class = '';
                                                                $status_text = '';
                                                                switch($vehicle['smoke_test_status']) {
                                                                    case 'pending':
                                                                        $status_class = 'status-pending';
                                                                        $status_text = 'Pending';
                                                                        break;
                                                                    case 'passed':
                                                                        $status_class = 'status-passed';
                                                                        $status_text = 'Passed';
                                                                        break;
                                                                    case 'failed':
                                                                        $status_class = 'status-failed';
                                                                        $status_text = 'Failed';
                                                                        break;
                                                                    case 'warmup':
                                                                        $status_class = 'status-warmup';
                                                                        $status_text = 'Warm Up Required';
                                                                        break;
                                                                }
                                                                ?>
                                                                <span class="status-badge <?php echo $status_class; ?>">
                                                                    <?php echo $status_text; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($service_notes[0])): ?>
                                                                    <ul class="list-unstyled mb-0">
                                                                        <?php foreach ($service_notes as $index => $note): ?>
                                                                            <?php if (!empty($note)): ?>
                                                                                <li>
                                                                                    <strong><?php echo htmlspecialchars($services[$index] ?? ''); ?>:</strong>
                                                                                    <?php echo htmlspecialchars($note); ?>
                                                                                </li>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php else: ?>
                                                                    <span class="text-muted">No notes</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" 
                                                                        onclick="editVehicleDetails(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['result'] ?? ''); ?>', '<?php echo htmlspecialchars($vehicle['smoke_test_notes'] ?? ''); ?>', '<?php echo htmlspecialchars($vehicle['error_code'] ?? ''); ?>', '<?php echo htmlspecialchars($vehicle['warm_up'] ?? ''); ?>', <?php echo htmlspecialchars(json_encode($services)); ?>, <?php echo htmlspecialchars(json_encode($service_statuses)); ?>, <?php echo htmlspecialchars(json_encode($service_notes)); ?>)">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php if ($vehicle['smoke_test_status'] === 'warmup' || $vehicle['result'] === 'warmup'): ?>
                                                        <tr>
                                                            <td colspan="8" class="bg-light">
                                                                <strong>Warm Up Details:</strong>
                                                                <div class="ms-3">
                                                                    <p class="mb-0">Cycles Required: <?php echo htmlspecialchars($vehicle['warm_up'] ?? 'N/A'); ?></p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if ($vehicle['smoke_test_status'] === 'failed' || $vehicle['result'] === 'fail'): ?>
                                                        <tr>
                                                            <td colspan="8" class="bg-light">
                                                                <strong>Failure Details:</strong>
                                                                <div class="ms-3">
                                                                    <p class="mb-0">Error Code: <?php echo htmlspecialchars($vehicle['error_code'] ?? 'N/A'); ?></p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sidebar -->
                                <div class="col-md-4">
                                    <!-- Consultant Card -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Assigned Consultant</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($appointment['consultant_name']): ?>
                                                <div class="consultant-card">
                                                    <h6 class="mb-2"><?php echo htmlspecialchars($appointment['consultant_name']); ?></h6>
                                                    <p class="mb-1">
                                                        <i class="fas fa-envelope me-2"></i>
                                                        <?php echo htmlspecialchars($appointment['consultant_email']); ?>
                                                    </p>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted">No consultant assigned</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
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

        function editVehicleDetails(vehicleId, currentResult, currentNotes, currentErrorCode, currentWarmUp, currentServices, currentServiceStatuses, currentServiceNotes) {
            // Create modal for editing vehicle details
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'editVehicleModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Vehicle Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editVehicleForm">
                                <div class="mb-3">
                                    <label for="result" class="form-label">Smoke Test Result</label>
                                    <select class="form-select" id="result" name="result" onchange="toggleAdditionalFields()">
                                        <option value="">Select Result</option>
                                        <option value="pass" ${currentResult === 'pass' ? 'selected' : ''}>Pass</option>
                                        <option value="fail" ${currentResult === 'fail' ? 'selected' : ''}>Fail</option>
                                        <option value="warmup" ${currentResult === 'warmup' ? 'selected' : ''}>Warm Up Required</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Smoke Test Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3">${currentNotes}</textarea>
                                </div>
                                <div id="errorCodeField" class="mb-3" style="display: none;">
                                    <label for="error_code" class="form-label">Error Code</label>
                                    <input type="text" class="form-control" id="error_code" name="error_code" value="${currentErrorCode}">
                                </div>
                                <div id="warmUpField" class="mb-3" style="display: none;">
                                    <label for="warm_up" class="form-label">Warm Up Cycles Required</label>
                                    <select class="form-select" id="warm_up" name="warm_up">
                                        <option value="">Select number of cycles</option>
                                        ${Array.from({length: 5}, (_, i) => `
                                            <option value="${i + 1}" ${currentWarmUp == (i + 1) ? 'selected' : ''}>${i + 1}</option>
                                        `).join('')}
                                    </select>
                                </div>
                                <hr>
                                <h6 class="mb-3">Service Details</h6>
                                <div id="servicesContainer">
                                    ${currentServices.map((service, index) => `
                                        <div class="service-item mb-3 p-3 border rounded">
                                            <h6 class="mb-2">${service}</h6>
                                            ${service.includes('Clean Truck') ? `
                                                <div class="mb-2">
                                                    <label class="form-label">Clean Truck Status</label>
                                                    <select class="form-select service-status" name="clean_truck_status_${index}">
                                                        <option value="pending" ${currentServiceStatuses[index] === 'pending' ? 'selected' : ''}>Pending</option>
                                                        <option value="confirmed" ${currentServiceStatuses[index] === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                                        <option value="completed" ${currentServiceStatuses[index] === 'completed' ? 'selected' : ''}>Completed</option>
                                                        <option value="cancelled" ${currentServiceStatuses[index] === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                                    </select>
                                                </div>
                                            ` : ''}
                                            ${service.includes('Smog') ? `
                                                <div class="mb-2">
                                                    <label class="form-label">Smog Check Status</label>
                                                    <select class="form-select service-status" name="smog_check_status_${index}">
                                                        <option value="pending" ${currentServiceStatuses[index] === 'pending' ? 'selected' : ''}>Pending</option>
                                                        <option value="confirmed" ${currentServiceStatuses[index] === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                                        <option value="completed" ${currentServiceStatuses[index] === 'completed' ? 'selected' : ''}>Completed</option>
                                                        <option value="cancelled" ${currentServiceStatuses[index] === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                                    </select>
                                                </div>
                                            ` : ''}
                                            <div class="mb-2">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control service-notes" name="service_notes_${index}" rows="2">${currentServiceNotes[index] || ''}</textarea>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                                <div class="mb-3">
                                    <label for="attachment" class="form-label">Attachment</label>
                                    <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Supported formats: PDF, JPG, PNG (Max size: 5MB)</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveVehicleDetails(${vehicleId}, ${JSON.stringify(currentServices)}, ${JSON.stringify(currentServiceStatuses)}, ${JSON.stringify(currentServiceNotes)})">Save Changes</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();

            // Show/hide additional fields based on current result
            toggleAdditionalFields();

            // Remove modal when closed
            modal.addEventListener('hidden.bs.modal', function () {
                modal.remove();
            });
        }

        function toggleAdditionalFields() {
            const result = document.getElementById('result').value;
            const errorCodeField = document.getElementById('errorCodeField');
            const warmUpField = document.getElementById('warmUpField');

            if (result === 'fail') {
                errorCodeField.style.display = 'block';
                warmUpField.style.display = 'none';
            } else if (result === 'warmup') {
                errorCodeField.style.display = 'none';
                warmUpField.style.display = 'block';
            } else {
                errorCodeField.style.display = 'none';
                warmUpField.style.display = 'none';
            }
        }

        function saveVehicleDetails(vehicleId, currentServices, currentServiceStatuses, currentServiceNotes) {
            const result = document.getElementById('result').value;
            const notes = document.getElementById('notes').value;
            const errorCode = document.getElementById('error_code').value;
            const warmUp = document.getElementById('warm_up').value;
            const attachment = document.getElementById('attachment').files[0];
            const submitButton = document.querySelector('#editVehicleModal .btn-primary');
            
            // Get service details
            const cleanTruckStatuses = currentServices.map((service, index) => 
                service.includes('Clean Truck') ? 
                    document.querySelector(`select[name="clean_truck_status_${index}"]`)?.value || 'pending' : 
                    null
            ).filter(status => status !== null);

            const smogCheckStatuses = currentServices.map((service, index) => 
                service.includes('Smog') ? 
                    document.querySelector(`select[name="smog_check_status_${index}"]`)?.value || 'pending' : 
                    null
            ).filter(status => status !== null);

            const serviceNotes = currentServices.map((_, index) => 
                document.querySelector(`textarea[name="service_notes_${index}"]`).value
            );
            
            // Disable button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

            // Create FormData object for file upload
            const formData = new FormData();
            formData.append('vehicle_id', vehicleId);
            formData.append('result', result);
            formData.append('notes', notes);
            formData.append('error_code', errorCode);
            formData.append('warm_up', warmUp);
            formData.append('services', JSON.stringify(currentServices));
            formData.append('clean_truck_statuses', JSON.stringify(cleanTruckStatuses));
            formData.append('smog_check_statuses', JSON.stringify(smogCheckStatuses));
            formData.append('service_notes', JSON.stringify(serviceNotes));
            if (attachment) {
                formData.append('attachment', attachment);
            }

            // Send update to server
            fetch('update_vehicle_result.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('editVehicleModal')).hide();
                    
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
                                        <p>Vehicle details have been updated successfully.</p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload()">OK</button>
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
                    });
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
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
                                    <p>Error updating vehicle details: ${error.message}</p>
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
                submitButton.textContent = 'Save Changes';
            });
        }
    </script>
</body>
</html> 