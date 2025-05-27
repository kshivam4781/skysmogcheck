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

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? null;

if (!$appointment_id) {
    $_SESSION['error_message'] = "No appointment ID provided.";
    header("Location: calendar_events.php");
    exit();
}

// Get appointment details
$stmt = $conn->prepare("
    SELECT 
        a.*,
        ce.id as calendar_event_id,
        ce.start_time,
        ce.end_time,
        ce.status as calendar_status,
        v.id as vehicle_id,
        v.vehYear,
        v.vehMake,
        v.plateNo,
        v.vin,
        v.smoke_test_status,
        v.clean_truck_check_status,
        v.result as smoke_test_result,
        v.next_due_date,
        v.clean_truck_check_next_date,
        v.error_code,
        v.warm_up,
        v.smoke_test_notes,
        v.attachment_path,
        v.service_id
    FROM appointments a
    LEFT JOIN calendar_events ce ON a.id = ce.appointment_id
    LEFT JOIN vehicles v ON ce.vehid = v.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    $_SESSION['error_message'] = "Appointment not found.";
    header("Location: calendar_events.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - Sky Smoke Check LLC</title>
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
            background-color: #2c3e50;
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
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1001;
            border-radius: 0 5px 5px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
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
            border-bottom: 2px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #ecf0f1;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar-header p {
            margin: 5px 0 0;
            color: #bdc3c7;
            font-size: 0.9rem;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        .sidebar-menu a {
            color: #ecf0f1;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
            color: #3498db;
        }
        .sidebar-menu a.active {
            background-color: #3498db;
            color: white;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .sidebar-menu a.active i {
            color: white;
        }
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            color: #bdc3c7;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 2px solid rgba(255,255,255,0.1);
            background-color: rgba(0,0,0,0.1);
        }
        .sidebar-footer .sidebar-menu a {
            padding: 10px 15px;
            font-size: 0.9rem;
        }
        .sidebar-footer .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.05);
        }
        .sidebar-footer .sidebar-menu a.logout-link {
            color: #e74c3c;
        }
        .sidebar-footer .sidebar-menu a.logout-link:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }
        .sidebar-footer .sidebar-menu a.logout-link i {
            color: #e74c3c;
        }
        .edit-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #cce5ff; color: #004085; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
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
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Appointment</h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" id="toggleEdit">
                            <i class="fas fa-edit"></i> Enable Editing
                        </button>
                        <a href="calendar_events.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Calendar
                        </a>
                    </div>
                </div>

                <div class="edit-container">
                    <form id="editAppointmentForm">
                        <!-- Company Information Section -->
                        <div class="section">
                            <h4 class="section-title">Company Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Company Name</label>
                                        <input type="text" class="form-control" name="companyName" value="<?php echo htmlspecialchars($appointment['companyName']); ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Contact Person</label>
                                        <input type="text" class="form-control" name="contactPerson" value="<?php echo htmlspecialchars($appointment['Name']); ?>" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($appointment['phone']); ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($appointment['email']); ?>" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Appointment Details Section -->
                        <div class="section">
                            <h4 class="section-title">Appointment Details</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Appointment ID</label>
                                        <p class="form-control-static"><?php echo htmlspecialchars($appointment['id']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <p class="form-control-static">
                                            <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="appointmentDate" 
                                            value="<?php echo date('Y-m-d', strtotime($appointment['start_time'])); ?>" 
                                            min="<?php echo date('Y-m-d'); ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Time</label>
                                        <select class="form-select" name="appointmentTime" disabled>
                                            <?php
                                            // Generate time slots from 9:00 AM to 5:30 PM
                                            $start = strtotime('09:00');
                                            $end = strtotime('17:30');
                                            $interval = 30 * 60; // 30 minutes in seconds
                                            
                                            // Get booked slots for the selected date
                                            $selected_date = date('Y-m-d', strtotime($appointment['start_time']));
                                            $stmt = $conn->prepare("
                                                SELECT start_time 
                                                FROM calendar_events 
                                                WHERE DATE(start_time) = ? 
                                                AND status = 'confirmed'
                                                AND appointment_id != ?
                                            ");
                                            $stmt->bind_param("si", $selected_date, $appointment_id);
                                            $stmt->execute();
                                            $booked_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            $booked_times = array_map(function($slot) {
                                                return date('H:i', strtotime($slot['start_time']));
                                            }, $booked_slots);

                                            // Generate time options
                                            for ($time = $start; $time <= $end; $time += $interval) {
                                                $time_str = date('h:i A', $time);
                                                $time_value = date('H:i', $time);
                                                $selected = ($time_value === date('H:i', strtotime($appointment['start_time']))) ? 'selected' : '';
                                                
                                                // Only show available time slots
                                                if (!in_array($time_value, $booked_times)) {
                                                    echo "<option value='$time_value' $selected>$time_str</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <select class="form-select" name="testLocation" disabled>
                                            <option value="our_location" <?php echo $appointment['test_location'] === 'our_location' ? 'selected' : ''; ?>>Our Location</option>
                                            <option value="your_location" <?php echo $appointment['test_location'] === 'your_location' ? 'selected' : ''; ?>>Client Location</option>
                                        </select>
                                        <input type="text" class="form-control mt-2" name="testAddress" 
                                            value="<?php echo htmlspecialchars($appointment['test_address']); ?>" 
                                            placeholder="Enter address for client location" 
                                            <?php echo $appointment['test_location'] === 'our_location' ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Information Section -->
                        <div class="section">
                            <h4 class="section-title">Vehicle Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Year</label>
                                        <input type="text" class="form-control" name="vehYear" value="<?php echo htmlspecialchars($appointment['vehYear']); ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Make</label>
                                        <input type="text" class="form-control" name="vehMake" value="<?php echo htmlspecialchars($appointment['vehMake']); ?>" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Plate Number</label>
                                        <input type="text" class="form-control" name="plateNo" value="<?php echo htmlspecialchars($appointment['plateNo']); ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">VIN</label>
                                        <input type="text" class="form-control" name="vin" value="<?php echo htmlspecialchars($appointment['vin']); ?>" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Service Requested</label>
                                        <p class="form-control-static">
                                            <?php
                                            $service_id = $appointment['service_id'];
                                            switch($service_id) {
                                                case 1:
                                                    echo '<span class="badge bg-info">Clean Truck Check</span>';
                                                    break;
                                                case 2:
                                                    echo '<span class="badge bg-warning">Smog Test</span>';
                                                    break;
                                                case 3:
                                                    echo '<span class="badge bg-primary">Clean Truck Check & Smog Test</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">Unknown Service</span>';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Test Results Section -->
                        <div class="section">
                            <h4 class="section-title">Test Results</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Smoke Test Status</label>
                                        <p class="form-control-static">
                                            <span class="status-badge status-<?php echo strtolower($appointment['smoke_test_status']); ?>">
                                                <?php echo ucfirst($appointment['smoke_test_status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Clean Truck Check Status</label>
                                        <p class="form-control-static">
                                            <span class="status-badge status-<?php echo strtolower($appointment['clean_truck_check_status']); ?>">
                                                <?php echo ucfirst($appointment['clean_truck_check_status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php if ($appointment['smoke_test_result']): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Test Result</label>
                                        <p class="form-control-static">
                                            <span class="status-badge status-<?php echo strtolower($appointment['smoke_test_result']); ?>">
                                                <?php echo ucfirst($appointment['smoke_test_result']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Next Due Date</label>
                                        <p class="form-control-static">
                                            <?php echo $appointment['next_due_date'] ? date('F j, Y', strtotime($appointment['next_due_date'])) : 'N/A'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pricing Section -->
                        <div class="section">
                            <h4 class="section-title">Pricing Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Total Price</label>
                                        <p class="form-control-static">$<?php echo number_format($appointment['total_price'], 2); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Discount</label>
                                        <p class="form-control-static">
                                            <?php
                                            if ($appointment['discount_type'] === 'percentage') {
                                                echo $appointment['discount_percentage'] . '% off';
                                            } elseif ($appointment['discount_type'] === 'amount') {
                                                echo '$' . number_format($appointment['discount_amount'], 2) . ' off';
                                            } else {
                                                echo 'No discount';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="onSiteChargesRow" style="display: none;">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">On-Site Test Charges</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" class="form-control" name="onSiteCharges" 
                                                value="<?php echo $appointment['onsitecharges'] ?? 0; ?>" 
                                                placeholder="Enter on-site charges" disabled>
                                        </div>
                                        <small class="text-muted">Additional charges for testing at client location</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="section">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="calendar_events.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Calendar
                                </a>
                                <button type="button" class="btn btn-primary" id="toggleEdit">
                                    <i class="fas fa-edit"></i> Enable Editing
                                </button>
                                <button type="submit" class="btn btn-success" id="saveChanges" disabled>
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                    </div>
                    <p class="text-center" id="successMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="admin_welcome.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (required for Bootstrap) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleEditBtn = document.getElementById('toggleEdit');
            const saveChangesBtn = document.getElementById('saveChanges');
            const form = document.getElementById('editAppointmentForm');
            const locationSelect = form.querySelector('select[name="testLocation"]');
            const addressInput = form.querySelector('input[name="testAddress"]');
            const onSiteChargesRow = document.getElementById('onSiteChargesRow');
            const onSiteChargesInput = form.querySelector('input[name="onSiteCharges"]');
            let isEditing = false;

            // Function to handle location change
            function handleLocationChange() {
                const isClientLocation = locationSelect.value === 'your_location';
                addressInput.disabled = !isClientLocation || !isEditing;
                onSiteChargesRow.style.display = isClientLocation ? 'block' : 'none';
                onSiteChargesInput.disabled = !isClientLocation || !isEditing;
            }

            // Toggle edit mode
            toggleEditBtn.addEventListener('click', function() {
                isEditing = !isEditing;
                
                // Enable/disable all form fields
                const formFields = form.querySelectorAll('input:not([type="hidden"]), select');
                formFields.forEach(field => {
                    // Don't enable price fields
                    if (!field.name.includes('totalPrice') && !field.name.includes('discount')) {
                        field.disabled = !isEditing;
                    }
                });
                
                // Enable/disable save button
                saveChangesBtn.disabled = !isEditing;
                
                // Update button appearance
                toggleEditBtn.innerHTML = isEditing ? 
                    '<i class="fas fa-times"></i> Cancel Editing' : 
                    '<i class="fas fa-edit"></i> Enable Editing';
                toggleEditBtn.classList.toggle('btn-primary');
                toggleEditBtn.classList.toggle('btn-secondary');
                
                // Update location-dependent fields
                handleLocationChange();
            });

            // Function to toggle edit mode
            function toggleEditMode(enable) {
                isEditing = enable;
                
                // Enable/disable all form fields
                const formFields = form.querySelectorAll('input:not([type="hidden"]), select');
                formFields.forEach(field => {
                    // Don't enable price fields
                    if (!field.name.includes('totalPrice') && !field.name.includes('discount')) {
                        field.disabled = !enable;
                    }
                });
                
                // Enable/disable save button
                saveChangesBtn.disabled = !enable;
                
                // Update button appearance
                toggleEditBtn.innerHTML = enable ? 
                    '<i class="fas fa-times"></i> Cancel Editing' : 
                    '<i class="fas fa-edit"></i> Enable Editing';
                toggleEditBtn.classList.toggle('btn-primary', enable);
                toggleEditBtn.classList.toggle('btn-secondary', !enable);
                
                // Update location-dependent fields
                handleLocationChange();
            }

            // Handle location type change
            locationSelect.addEventListener('change', handleLocationChange);

            // Initial setup
            handleLocationChange();

            // Phone number formatting function
            function formatPhoneNumber(input) {
                // Remove all non-numeric characters
                let number = input.value.replace(/\D/g, '');
                
                // Limit to 10 digits
                number = number.substring(0, 10);
                
                // Format the number
                if (number.length > 0) {
                    if (number.length <= 3) {
                        number = '(' + number;
                    } else if (number.length <= 6) {
                        number = '(' + number.substring(0, 3) + ') ' + number.substring(3);
                    } else {
                        number = '(' + number.substring(0, 3) + ') ' + number.substring(3, 6) + '-' + number.substring(6);
                    }
                }
                
                // Update input value
                input.value = number;
            }

            // Add phone number formatting to the phone input
            const phoneInput = form.querySelector('input[name="phone"]');
            phoneInput.addEventListener('input', function() {
                formatPhoneNumber(this);
            });

            // Format phone number on page load
            if (phoneInput.value) {
                formatPhoneNumber(phoneInput);
            }

            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!isEditing) return;

                // Disable submit button and show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                // Collect form data
                const formData = new FormData(this);
                
                // Add appointment ID
                formData.append('appointment_id', '<?php echo $appointment_id; ?>');

                // Collect vehicle data
                const vehicles = {};
                document.querySelectorAll('.vehicle-row').forEach((row, index) => {
                    const vehicleId = row.dataset.vehicleId;
                    vehicles[vehicleId] = {
                        year: row.querySelector('[name="vehYear"]').value,
                        make: row.querySelector('[name="vehMake"]').value,
                        vin: row.querySelector('[name="vin"]').value,
                        plateNo: row.querySelector('[name="plateNo"]').value
                    };
                });
                formData.append('vehicles', JSON.stringify(vehicles));

                // Send AJAX request
                fetch('update_appointment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the total price display
                        const totalPriceElement = document.querySelector('.form-control-static');
                        if (totalPriceElement) {
                            totalPriceElement.textContent = '$' + data.new_total_price;
                        }
                        
                        // Show success message
                        document.getElementById('successMessage').textContent = data.message;
                        
                        // Show success modal
                        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                        
                        // Reset form and disable edit mode
                        form.reset();
                        toggleEditMode(false);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the appointment');
                })
                .finally(() => {
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });

            // Add event listener for date selection
            const appointmentDateInput = document.querySelector('input[name="appointmentDate"]');
            if (appointmentDateInput) {
                appointmentDateInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    if (selectedDate) {
                        // Fetch available time slots
                        fetch('update_appointment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `appointment_date=${selectedDate}&get_available_slots=1&appointment_id=<?php echo $appointment_id; ?>`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const timeSelect = document.querySelector('select[name="appointmentTime"]');
                                if (timeSelect) {
                                    const currentValue = timeSelect.value;
                                    
                                    // Clear existing options
                                    timeSelect.innerHTML = '';
                                    
                                    // Add available time slots
                                    data.available_slots.forEach(slot => {
                                        const option = document.createElement('option');
                                        option.value = slot;
                                        option.textContent = new Date(`2000-01-01 ${slot}`).toLocaleTimeString('en-US', {
                                            hour: 'numeric',
                                            minute: '2-digit',
                                            hour12: true
                                        });
                                        option.disabled = false;
                                        timeSelect.appendChild(option);
                                    });
                                    
                                    // Restore previous selection if still available
                                    if (currentValue && data.available_slots.includes(currentValue)) {
                                        timeSelect.value = currentValue;
                                    }
                                }
                            }
                        })
                        .catch(error => console.error('Error fetching time slots:', error));
                    }
                });
            }
        });

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
    </script>
</body>
</html> 