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

// Set timezone to PST
date_default_timezone_set('America/Los_Angeles');

// Get current date and time in PST
$current_date = date('Y-m-d');
$current_time = date('H:i');

// Get all consultants (accountType = 2 for consultants)
$stmt = $conn->prepare("
    SELECT idaccounts, firstName, lastName, email, phone 
    FROM accounts 
    WHERE accountType = 2 
    ORDER BY firstName, lastName
");
$stmt->execute();
$consultants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch active services
$services_stmt = $conn->prepare("SELECT id, name, price FROM services WHERE is_active = 1");
$services_stmt->execute();
$services = $services_stmt->get_result();

// Get all booked times for the selected date
$booked_times = [];
if (isset($_POST['preferred_date'])) {
    $selected_date = $_POST['preferred_date'];
    $stmt = $conn->prepare("
        SELECT TIME(start_time) as time_slot
        FROM calendar_events 
        WHERE DATE(start_time) = ? 
        AND status = 'confirmed'
    ");
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $booked_times[] = $row['time_slot'];
    }
}

// Function to generate available time slots
function generateTimeSlots($booked_times) {
    $slots = [];
    $start_time = strtotime('09:00');
    $end_time = strtotime('17:30');
    $interval = 30 * 60; // 30 minutes interval
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_value = date('H:i', $time);
        if (!in_array($time_value, $booked_times)) {
            $slots[] = [
                'value' => $time_value,
                'display' => date('g:i A', $time)
            ];
        }
    }
    return $slots;
}

// Generate initial time slots
$available_slots = generateTimeSlots($booked_times);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quotation - Sky Smoke Check LLC</title>
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
            max-width: 800px;
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
        .quotation-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .quotation-section h3 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .price-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .price-total {
            font-size: 1.2rem;
            font-weight: 600;
            color: #28a745;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }
        .vehicle-entry {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        .vehicle-subsection {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .subsection-title {
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .vehicle-count {
            font-weight: 600;
            color: #2c3e50;
        }
        .remove-vehicle {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .remove-vehicle:not([disabled]) {
            opacity: 1;
            cursor: pointer;
        }
        .vehicle-actions {
            text-align: right;
        }
        .btn-primary {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-primary:hover {
            background-color: #138496;
            border-color: #117a8b;
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
        .discount-options {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .discount-option {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .discount-option:hover {
            background: #e9ecef;
        }
        .discount-input {
            margin-top: 10px;
        }
        .discount-input input {
            max-width: 100px;
        }
        .custom-discount {
            padding: 15px;
            background: #e9ecef;
            border-radius: 5px;
        }
        .discount-input .input-group {
            max-width: 150px;
        }
        .discount-input .input-group-text {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
        .form-check-input {
            width: 1rem;
            height: 1rem;
            margin-top: 0.25rem;
            border-radius: 50%;
            border: 1px solid #dee2e6;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #fff;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: #17a2b8;
            border-color: #17a2b8;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='2' fill='%23fff'/%3e%3c/svg%3e");
        }
        .form-check-input:focus {
            border-color: #17a2b8;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-check-label {
            margin-bottom: 0;
            cursor: pointer;
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
                <div class="quotation-container">
                    <div class="quotation-header">
                        <h1 class="quotation-title">Smog Check Quotation</h1>
                        <p class="quotation-subtitle">Sky Smoke Check LLC</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="quotationForm" onsubmit="return false;">
                        <!-- Add a hidden field to help with debugging -->
                        <input type="hidden" name="form_submitted" value="1">
                        <!-- Customer Information -->
                        <section class="quotation-section">
                            <h3>Customer Information</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company" class="form-label required-field">Company Name</label>
                                        <input type="text" id="company" name="company" class="form-control" required value="Not A Test LLC">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="form-label">Contact Name</label>
                                        <input type="text" id="name" name="name" class="form-control" value="Mr Test Doe">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label required-field">Email</label>
                                        <input type="email" id="email" name="email" class="form-control" required value="john.doe@example.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone" class="form-label required-field">Phone</label>
                                        <input type="tel" id="phone" name="phone" class="form-control" required 
                                               pattern="\(\d{3}\) \d{3}-\d{4}" 
                                               placeholder="(123) 456-7890" value="(123) 456-7890">
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Vehicle Information -->
                        <section class="quotation-section">
                            <h3>Vehicle Information</h3>
                            <div id="vehicle-container">
                                <div class="vehicle-entry">
                                    <div class="vehicle-header">
                                        <div class="vehicle-count">Vehicle 1</div>
                                        <button type="button" class="btn btn-danger btn-sm remove-vehicle" disabled>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Service Information -->
                                    <div class="vehicle-subsection mb-3">
                                        <h5 class="subsection-title">Service Information</h5>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="service_type" class="form-label required-field">Service Type</label>
                                                    <select id="service_type" name="service_type[]" class="form-control" required>
                                                        <option value="">Select Service</option>
                                                        <?php while ($service = $services->fetch_assoc()): ?>
                                                            <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                                                                <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3 smog-check-question" style="display: none;">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="form-label">Has the vehicle's smog check been completed?</label>
                                                    <div class="d-flex gap-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="smog_check_completed_1" value="yes" required>
                                                            <label class="form-check-label">Yes</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="smog_check_completed_1" value="no" required>
                                                            <label class="form-check-label">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mt-3 smog-check-details" style="display: none;">
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label class="form-label required-field">Please provide the following information:</label>
                                                            <textarea class="form-control" name="smog_check_pending_details[]" rows="3" 
                                                                      placeholder="1. Reason why the smoke test is pending&#10;2. When do you expect to complete the smoke test"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vehicle Information -->
                                    <div class="vehicle-subsection mb-3">
                                        <h5 class="subsection-title">Vehicle Information</h5>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="vehicle_year" class="form-label required-field">Vehicle Year</label>
                                                    <input type="number" id="vehicle_year" name="vehicle_year[]" class="form-control" required 
                                                           min="1980" max="<?php echo date('Y'); ?>" value="2020">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="make" class="form-label required-field">Make</label>
                                                    <input type="text" id="make" name="make[]" class="form-control" required value="Toyota">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="vin" class="form-label required-field">VIN Number</label>
                                                    <input type="text" id="vin" name="vin[]" class="form-control" required 
                                                           pattern="[A-HJ-NPR-Z0-9]{17}" 
                                                           title="Please enter a valid 17-character VIN number" value="1HGCM82633A123456">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="license_plate" class="form-label required-field">License Plate</label>
                                                    <input type="text" id="license_plate" name="license_plate[]" class="form-control" value="ABC123">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Test Information -->
                                    <div class="vehicle-subsection">
                                        <h5 class="subsection-title">Test Information</h5>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="test_date" class="form-label required-field">Test Date</label>
                                                    <input type="date" id="test_date" name="test_date[]" class="form-control" required 
                                                           min="<?php echo $current_date; ?>" value="<?php echo $current_date; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="test_time" class="form-label required-field">Test Time</label>
                                                    <select id="test_time" name="test_time[]" class="form-control" required>
                                                        <option value="">Select Time</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="test_location" class="form-label required-field">Test Location</label>
                                                    <select id="test_location" name="test_location[]" class="form-control" required>
                                                        <option value="our_location" selected>121 E 11th St, Tracy, CA 95376</option>
                                                        <option value="your_location">At Your Location</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3 test-address-row" style="display: none;">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label for="test_address" class="form-label required-field">Test Address</label>
                                                    <textarea id="test_address" name="test_address[]" class="form-control" rows="2" 
                                                              placeholder="Please provide the complete address where the test will be conducted"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="vehicle-actions mt-3">
                                <button type="button" class="btn btn-primary" id="add-vehicle">
                                    <i class="fas fa-plus"></i> Add Vehicle
                                </button>
                            </div>
                        </section>

                        <!-- Price Summary -->
                        <section class="quotation-section">
                            <h3>Price Summary</h3>
                            <div class="price-summary">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Vehicle</th>
                                                <th>License Plate</th>
                                                <th>Service</th>
                                                <th class="text-end">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="price-summary-body">
                                            <!-- Vehicle rows will be added here dynamically -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                                <td class="text-end" id="subtotal-price">$0.00</td>
                                            </tr>
                                            <tr id="discount-row" style="display: none;">
                                                <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                                <td class="text-end text-danger" id="discount-amount">-$0.00</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td colspan="3" class="text-end"><strong>Total Price:</strong></td>
                                                <td class="text-end" id="total-price">$0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <p class="mb-0"><strong>Number of Vehicles:</strong> <span id="vehicle-count">0</span></p>
                                </div>
                            </div>
                        </section>

                        <!-- Discount Options -->
                        <section class="quotation-section">
                            <h3>Discount Options</h3>
                            <div class="discount-options">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="noDiscount" value="none" checked>
                                                <label class="form-check-label" for="noDiscount">
                                                    No Discount
                                                </label>
                                            </div>
                                        </div>
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="smallFleetDiscount" value="small_fleet">
                                                <label class="form-check-label" for="smallFleetDiscount">
                                                    Small Fleet Size Discount (5%)
                                                </label>
                                            </div>
                                            <div class="discount-input" style="display: none;">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="small_fleet_discount" value="5" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="mediumFleetDiscount" value="medium_fleet">
                                                <label class="form-check-label" for="mediumFleetDiscount">
                                                    Medium Fleet Size Discount
                                                </label>
                                            </div>
                                            <div class="discount-input" style="display: none;">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="medium_fleet_discount" value="10" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="largeFleetDiscount" value="large_fleet">
                                                <label class="form-check-label" for="largeFleetDiscount">
                                                    Large Fleet Size Discount
                                                </label>
                                            </div>
                                            <div class="discount-input" style="display: none;">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="large_fleet_discount" value="10" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="returningCustomerDiscount" value="returning_customer">
                                                <label class="form-check-label" for="returningCustomerDiscount">
                                                    Returning Customer Discount
                                                </label>
                                            </div>
                                            <div class="discount-input" style="display: none;">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="returning_customer_discount" value="10" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="referralDiscount" value="referral">
                                                <label class="form-check-label" for="referralDiscount">
                                                    Referral Program Discount
                                                </label>
                                            </div>
                                            <div class="discount-input" style="display: none;">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="referral_discount" value="10" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="discount-option">
                                            <div class="form-check">
                                                <input class="form-check-input discount-radio" type="radio" name="discount_type" id="firstTimeDiscount" value="first_time">
                                                <label class="form-check-label" for="firstTimeDiscount">
                                                    First Time Discount (5%)
                                                </label>
                                            </div>
                                            <div class="discount-input" style="display: none;">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="first_time_discount" value="5" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="custom-discount mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input discount-radio" type="radio" name="discount_type" id="customDiscount" value="custom">
                                        <label class="form-check-label" for="customDiscount">
                                            Custom Discount
                                        </label>
                                    </div>
                                    <div class="discount-input" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="input-group mb-2">
                                                    <input type="number" class="form-control custom-discount-percentage" name="custom_discount_percentage" value="0" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="input-group mb-2">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control custom-discount-amount" name="custom_discount_amount" value="0" min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Additional Information -->
                        <section class="quotation-section">
                            <h3>Additional Notes</h3>
                            <div class="form-group">
                                <label for="special_instructions" class="form-label">Special Instructions</label>
                                <textarea id="special_instructions" name="special_instructions" class="form-control" rows="4"></textarea>
                            </div>
                        </section>

                        <!-- Consultant Assignment -->
                        <section class="quotation-section">
                            <h3>Consultant Assignment</h3>
                            <div class="form-group">
                                <label for="consultant_id" class="form-label required-field">Assigned Consultant</label>
                                <select class="form-control" id="consultant_id" name="consultant_id" required>
                                    <option value="">Select Consultant</option>
                                    <?php foreach ($consultants as $consultant): ?>
                                        <option value="<?php echo $consultant['idaccounts']; ?>">
                                            <?php echo htmlspecialchars($consultant['firstName'] . ' ' . $consultant['lastName']); ?> 
                                            (<?php echo htmlspecialchars($consultant['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </section>

                        <div class="button-container">
                            <a href="admin_welcome.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                            <button type="button" class="btn btn-primary" onclick="submitQuotation()">
                                <i class="fas fa-file-invoice-dollar me-2"></i> Create Quotation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        // Handle discount radio buttons
        const discountRadios = document.querySelectorAll('.discount-radio');
        discountRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Hide all discount inputs
                document.querySelectorAll('.discount-input').forEach(input => {
                    input.style.display = 'none';
                });
                
                // Show the selected discount input
                if (this.value !== 'none') {
                    const discountInput = this.closest('.discount-option, .custom-discount');
                    if (discountInput) {
                        discountInput.style.display = 'block';
                    }
                }
                
                updateTotalPrice();
            });
        });

        // Add vehicle functionality
        let vehicleCount = 1;
        const vehicleContainer = document.getElementById('vehicle-container');
        const addVehicleBtn = document.getElementById('add-vehicle');

        // Function to check if service requires test information
        function requiresTestInfo(serviceId) {
            // Service IDs that require test information
            const testRequiredServices = ['2', '3']; // 2 is Smog Check and 3 is Both Services
            return testRequiredServices.includes(serviceId);
        }

        // Function to toggle test information visibility
        function toggleTestInfo(vehicleEntry, serviceId) {
            const testSubsection = vehicleEntry.querySelector('.vehicle-subsection:last-child');
            if (requiresTestInfo(serviceId)) {
                testSubsection.style.display = 'block';
            } else {
                testSubsection.style.display = 'none';
            }
        }

        // Function to check if service is Clean Truck Check
        function isCleanTruckCheck(serviceId) {
            return serviceId === '1'; // Assuming 1 is Clean Truck Check
        }

        // Function to toggle smog check question
        function toggleSmogCheckQuestion(vehicleEntry, serviceId) {
            const questionDiv = vehicleEntry.querySelector('.smog-check-question');
            if (isCleanTruckCheck(serviceId)) {
                questionDiv.style.display = 'block';
            } else {
                questionDiv.style.display = 'none';
            }
        }

        // Function to toggle smog check details
        function toggleSmogCheckDetails(vehicleEntry, isNoSelected) {
            const detailsDiv = vehicleEntry.querySelector('.smog-check-details');
            const textarea = detailsDiv.querySelector('textarea');
            if (isNoSelected) {
                detailsDiv.style.display = 'block';
                textarea.required = true;
            } else {
                detailsDiv.style.display = 'none';
                textarea.required = false;
                textarea.value = '';
            }
        }

        // Update the service type change event listener
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'service_type[]') {
                const vehicleEntry = e.target.closest('.vehicle-entry');
                toggleTestInfo(vehicleEntry, e.target.value);
                toggleSmogCheckQuestion(vehicleEntry, e.target.value);
                updateTotalPrice();
            }
        });

        // Add event listener for radio button changes
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name.startsWith('smog_check_completed_')) {
                const vehicleEntry = e.target.closest('.vehicle-entry');
                toggleSmogCheckDetails(vehicleEntry, e.target.value === 'no');
            }
        });

        // Update the addVehicle function
        function addVehicle() {
            vehicleCount++;
            const newVehicle = document.createElement('div');
            newVehicle.className = 'vehicle-entry';
            newVehicle.innerHTML = `
                <div class="vehicle-header">
                    <div class="vehicle-count">Vehicle ${vehicleCount}</div>
                    <button type="button" class="btn btn-danger btn-sm remove-vehicle">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <!-- Service Information -->
                <div class="vehicle-subsection mb-3">
                    <h5 class="subsection-title">Service Information</h5>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="service_type_${vehicleCount}" class="form-label required-field">Service Type</label>
                                <select id="service_type_${vehicleCount}" name="service_type[]" class="form-control" required>
                                    <option value="">Select Service</option>
                                    <?php 
                                    $services->data_seek(0);
                                    while ($service = $services->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                                            <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3 smog-check-question" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label">Has the vehicle's smog check been completed?</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="smog_check_completed_${vehicleCount}" value="yes" required>
                                        <label class="form-check-label">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="smog_check_completed_${vehicleCount}" value="no" required>
                                        <label class="form-check-label">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3 smog-check-details" style="display: none;">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-label required-field">Please provide the following information:</label>
                                        <textarea class="form-control" name="smog_check_pending_details[]" rows="3" 
                                                  placeholder="1. Reason why the smoke test is pending&#10;2. When do you expect to complete the smoke test"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Information -->
                <div class="vehicle-subsection mb-3">
                    <h5 class="subsection-title">Vehicle Information</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="vehicle_year_${vehicleCount}" class="form-label required-field">Vehicle Year</label>
                                <input type="number" id="vehicle_year_${vehicleCount}" name="vehicle_year[]" class="form-control" required 
                                       min="1980" max="<?php echo date('Y'); ?>" value="2020">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="make_${vehicleCount}" class="form-label required-field">Make</label>
                                <input type="text" id="make_${vehicleCount}" name="make[]" class="form-control" required value="Toyota">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="vin_${vehicleCount}" class="form-label required-field">VIN Number</label>
                                <input type="text" id="vin_${vehicleCount}" name="vin[]" class="form-control" required 
                                       pattern="[A-HJ-NPR-Z0-9]{17}" 
                                       title="Please enter a valid 17-character VIN number" value="1HGCM82633A123456">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="license_plate_${vehicleCount}" class="form-label required-field">License Plate</label>
                                <input type="text" id="license_plate_${vehicleCount}" name="license_plate[]" class="form-control" value="ABC123">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Information -->
                <div class="vehicle-subsection">
                    <h5 class="subsection-title">Test Information</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="test_date_${vehicleCount}" class="form-label required-field">Test Date</label>
                                <input type="date" id="test_date_${vehicleCount}" name="test_date[]" class="form-control" required 
                                       min="<?php echo $current_date; ?>" value="<?php echo $current_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="test_time_${vehicleCount}" class="form-label required-field">Test Time</label>
                                <select id="test_time_${vehicleCount}" name="test_time[]" class="form-control" required>
                                    <option value="">Select Time</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="test_location_${vehicleCount}" class="form-label required-field">Test Location</label>
                                <select id="test_location_${vehicleCount}" name="test_location[]" class="form-control" required>
                                    <option value="our_location" selected>121 E 11th St, Tracy, CA 95376</option>
                                    <option value="your_location">At Your Location</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3 test-address-row" style="display: none;">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="test_address_${vehicleCount}" class="form-label required-field">Test Address</label>
                                <textarea id="test_address_${vehicleCount}" name="test_address[]" class="form-control" rows="2" 
                                          placeholder="Please provide the complete address where the test will be conducted"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            vehicleContainer.appendChild(newVehicle);
            
            // Add event listener to the new remove button
            const removeBtn = newVehicle.querySelector('.remove-vehicle');
            removeBtn.addEventListener('click', function() {
                removeVehicle(this);
            });
            
            // Add event listener to the new service type select
            const serviceSelect = newVehicle.querySelector('select[name="service_type[]"]');
            serviceSelect.addEventListener('change', function() {
                updateTotalPrice();
                toggleTestInfo(newVehicle, this.value);
                toggleSmogCheckQuestion(newVehicle, this.value);
            });
            
            // Initial visibility checks
            toggleTestInfo(newVehicle, serviceSelect.value);
            toggleSmogCheckQuestion(newVehicle, serviceSelect.value);
            
            // Add event listener for radio button changes in the new vehicle
            const radioButtons = newVehicle.querySelectorAll('input[name="smog_check_completed[]"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    toggleSmogCheckDetails(newVehicle, this.value === 'no');
                });
            });
            
            // Add the time slots to the new vehicle
            const timeSelect = newVehicle.querySelector('select[name="test_time[]"]');
            timeSelect.innerHTML = '<option value="">Select Time</option>';
            
            // Fetch available time slots for today
            const today = new Date().toISOString().split('T')[0];
            fetch(`get_available_times.php?date=${today}`)
                .then(response => response.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        data.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot.value;
                            option.textContent = slot.display;
                            timeSelect.appendChild(option);
                        });
                    } else {
                        console.error('Invalid response format:', data);
                        timeSelect.innerHTML = '<option value="">Error loading times</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    timeSelect.innerHTML = '<option value="">Error loading times</option>';
                });
            
            updateTotalPrice();
        }

        function removeVehicle(button) {
            const vehicleEntry = button.closest('.vehicle-entry');
            vehicleEntry.remove();
            vehicleCount--;
            
            // Update vehicle numbers
            const vehicles = document.querySelectorAll('.vehicle-entry');
            vehicles.forEach((vehicle, index) => {
                vehicle.querySelector('.vehicle-count').textContent = `Vehicle ${index + 1}`;
            });
            
            // Disable remove button if only one vehicle remains
            if (vehicleCount === 1) {
                document.querySelector('.remove-vehicle').disabled = true;
            }
            
            updateTotalPrice();
        }

        // Add event listener for add vehicle button
        addVehicleBtn.addEventListener('click', addVehicle);

        // Add event listeners to all remove buttons
        document.querySelectorAll('.remove-vehicle').forEach(button => {
            button.addEventListener('click', function() {
                removeVehicle(this);
            });
        });

        // Function to update total price with discounts
        function updateTotalPrice() {
            let total = 0;
            const priceSummaryBody = document.getElementById('price-summary-body');
            priceSummaryBody.innerHTML = ''; // Clear existing rows
            
            // Get all vehicle entries
            const vehicleEntries = document.querySelectorAll('.vehicle-entry');
            
            vehicleEntries.forEach((entry, index) => {
                const licensePlate = entry.querySelector('input[name="license_plate[]"]').value;
                const serviceSelect = entry.querySelector('select[name="service_type[]"]');
                const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
                
                if (selectedOption && selectedOption.value) {
                    const serviceName = selectedOption.text.split(' - ')[0];
                    const servicePrice = parseFloat(selectedOption.dataset.price);
                    total += servicePrice;
                    
                    // Add row to price summary table
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>Vehicle ${index + 1}</td>
                        <td>${licensePlate}</td>
                        <td>${serviceName}</td>
                        <td class="text-end">$${servicePrice.toFixed(2)}</td>
                    `;
                    priceSummaryBody.appendChild(row);
                }
            });
            
            // Update vehicle count
            document.getElementById('vehicle-count').textContent = vehicleEntries.length;
            
            // Update subtotal
            document.getElementById('subtotal-price').textContent = '$' + total.toFixed(2);
            
            // Handle discounts
            const discountRow = document.getElementById('discount-row');
            const selectedDiscount = document.querySelector('input[name="discount_type"]:checked');
            
            if (selectedDiscount && selectedDiscount.value !== 'none') {
                const discountContainer = selectedDiscount.closest('.discount-option, .custom-discount');
                let discountAmount = 0;
                
                if (selectedDiscount.value === 'custom') {
                    const percentageInput = discountContainer.querySelector('.custom-discount-percentage');
                    const amountInput = discountContainer.querySelector('.custom-discount-amount');
                    
                    if (percentageInput && parseFloat(percentageInput.value) > 0) {
                        discountAmount = (total * parseFloat(percentageInput.value)) / 100;
                    } else if (amountInput && parseFloat(amountInput.value) > 0) {
                        discountAmount = parseFloat(amountInput.value);
                    }
                } else {
                    const discountInput = discountContainer.querySelector('.discount-input input:not(.custom-discount-amount)');
                    if (discountInput) {
                        discountAmount = (total * parseFloat(discountInput.value)) / 100;
                    }
                }
                
                discountRow.style.display = '';
                document.getElementById('discount-amount').textContent = '-$' + discountAmount.toFixed(2);
                total -= discountAmount;
            } else {
                discountRow.style.display = 'none';
            }
            
            document.getElementById('total-price').textContent = '$' + total.toFixed(2);
        }

        // Add event listeners for all inputs that should trigger price updates
        document.addEventListener('DOMContentLoaded', function() {
            // Existing event listeners...
            
            // Add event listeners for license plate changes
            document.querySelectorAll('input[name="license_plate[]"]').forEach(input => {
                input.addEventListener('input', updateTotalPrice);
            });
            
            // Add event listeners for discount inputs
            document.querySelectorAll('.discount-input input').forEach(input => {
                input.addEventListener('input', updateTotalPrice);
            });
            
            // Initial price update
            updateTotalPrice();
        });

        // Handle test location change for each vehicle
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'test_location[]') {
                const vehicleEntry = e.target.closest('.vehicle-entry');
                const addressRow = vehicleEntry.querySelector('.test-address-row');
                if (e.target.value === 'your_location') {
                    addressRow.style.display = 'block';
                } else {
                    addressRow.style.display = 'none';
                }
            }
        });

        // Function to format time for display
        function formatTime(timeValue) {
            const [hours, minutes] = timeValue.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes));
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // Update time options when date changes
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'test_date[]') {
                const vehicleEntry = e.target.closest('.vehicle-entry');
                const timeSelect = vehicleEntry.querySelector('select[name="test_time[]"]');
                const selectedDate = e.target.value;
                
                // Clear existing options
                timeSelect.innerHTML = '<option value="">Select Time</option>';
                
                // Fetch available time slots for the selected date
                fetch(`get_available_times.php?date=${selectedDate}`)
                    .then(response => response.json())
                    .then(data => {
                        if (Array.isArray(data)) {
                            data.forEach(slot => {
                                const option = document.createElement('option');
                                option.value = slot.value;
                                option.textContent = slot.display;
                                timeSelect.appendChild(option);
                            });
                        } else {
                            console.error('Invalid response format:', data);
                            timeSelect.innerHTML = '<option value="">Error loading times</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        timeSelect.innerHTML = '<option value="">Error loading times</option>';
                    });
            }
        });

        // Update the initial vehicle's test information visibility
        document.addEventListener('DOMContentLoaded', function() {
            const initialVehicle = document.querySelector('.vehicle-entry');
            const initialServiceSelect = initialVehicle.querySelector('select[name="service_type[]"]');
            toggleTestInfo(initialVehicle, initialServiceSelect.value);
            toggleSmogCheckQuestion(initialVehicle, initialServiceSelect.value);
        });

        // Initialize time slots for the first vehicle on page load
        document.addEventListener('DOMContentLoaded', function() {
            const firstTimeSelect = document.querySelector('select[name="test_time[]"]');
            if (firstTimeSelect) {
                const today = new Date().toISOString().split('T')[0];
                fetch(`get_available_times.php?date=${today}`)
                    .then(response => response.json())
                    .then(data => {
                        if (Array.isArray(data)) {
                            data.forEach(slot => {
                                const option = document.createElement('option');
                                option.value = slot.value;
                                option.textContent = slot.display;
                                firstTimeSelect.appendChild(option);
                            });
                        } else {
                            console.error('Invalid response format:', data);
                            firstTimeSelect.innerHTML = '<option value="">Error loading times</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        firstTimeSelect.innerHTML = '<option value="">Error loading times</option>';
                    });
            }
        });

        // Add form submission function
        function submitQuotation() {
            console.log('Submit quotation function called');
            
            // Get all vehicle entries
            const vehicleEntries = document.querySelectorAll('.vehicle-entry');
            console.log('Number of vehicle entries found:', vehicleEntries.length);
            
            const vehicles = [];
            
            // Collect data for each vehicle
            vehicleEntries.forEach((entry, index) => {
                console.log(`Processing vehicle ${index + 1}`);
                
                const vehicle = {
                    make: entry.querySelector('input[name="make[]"]').value,
                    model: entry.querySelector('input[name="model[]"]')?.value || '',
                    vehicle_year: entry.querySelector('input[name="vehicle_year[]"]').value,
                    vin: entry.querySelector('input[name="vin[]"]').value,
                    license_plate: entry.querySelector('input[name="license_plate[]"]').value,
                    service_type: entry.querySelector('select[name="service_type[]"]').value,
                    smog_check_completed: entry.querySelector(`input[name="smog_check_completed_${index + 1}"]:checked`)?.value || 'no',
                    smog_check_pending_details: entry.querySelector('textarea[name="smog_check_pending_details[]"]')?.value || '',
                    test_date: entry.querySelector('input[name="test_date[]"]').value,
                    test_time: entry.querySelector('select[name="test_time[]"]').value || '',
                    test_location: entry.querySelector('select[name="test_location[]"]').value,
                    test_address: entry.querySelector('textarea[name="test_address[]"]')?.value || ''
                };
                
                console.log(`Vehicle ${index + 1} data:`, vehicle);
                vehicles.push(vehicle);
            });

            // Get discount values
            const selectedDiscount = document.querySelector('input[name="discount_type"]:checked');
            const discountType = selectedDiscount ? selectedDiscount.value : 'none';
            let discountAmount = 0;
            let customDiscountPercentage = 0;
            let customDiscountAmount = 0;

            console.log('Selected discount type:', discountType);

            if (discountType === 'small_fleet') {
                const input = document.querySelector('input[name="small_fleet_discount"]');
                discountAmount = input ? parseFloat(input.value) || 0 : 0;
                console.log('Small fleet discount amount:', discountAmount);
            } else if (discountType === 'medium_fleet') {
                const input = document.querySelector('input[name="medium_fleet_discount"]');
                discountAmount = input ? parseFloat(input.value) || 0 : 0;
                console.log('Medium fleet discount amount:', discountAmount);
            } else if (discountType === 'large_fleet') {
                const input = document.querySelector('input[name="large_fleet_discount"]');
                discountAmount = input ? parseFloat(input.value) || 0 : 0;
                console.log('Large fleet discount amount:', discountAmount);
            } else if (discountType === 'returning_customer') {
                const input = document.querySelector('input[name="returning_customer_discount"]');
                discountAmount = input ? parseFloat(input.value) || 0 : 0;
                console.log('Returning customer discount amount:', discountAmount);
            } else if (discountType === 'referral') {
                const input = document.querySelector('input[name="referral_discount"]');
                discountAmount = input ? parseFloat(input.value) || 0 : 0;
                console.log('Referral discount amount:', discountAmount);
            } else if (discountType === 'first_time') {
                const input = document.querySelector('input[name="first_time_discount"]');
                discountAmount = input ? parseFloat(input.value) || 0 : 0;
                console.log('First time discount amount:', discountAmount);
            } else if (discountType === 'custom') {
                const percentageInput = document.querySelector('input[name="custom_discount_percentage"]');
                const amountInput = document.querySelector('input[name="custom_discount_amount"]');
                customDiscountPercentage = percentageInput ? parseFloat(percentageInput.value) || 0 : 0;
                customDiscountAmount = amountInput ? parseFloat(amountInput.value) || 0 : 0;
                console.log('Custom discount values:', { customDiscountPercentage, customDiscountAmount });
            }

            // Get form data
            const formData = {
                companyName: document.getElementById('company').value,
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                bookingDate: document.getElementById('test_date').value,
                bookingTime: document.getElementById('test_time')?.value || '',
                test_location: document.getElementById('test_location').value,
                test_address: document.getElementById('test_address')?.value || '',
                special_instructions: document.getElementById('special_instructions').value,
                number_of_vehicles: vehicleEntries.length,
                status: 'pending',
                approved_by: '',
                total_price: calculateTotalPrice(),
                discount_type: discountType,
                discount_amount: discountAmount,
                custom_discount_percentage: customDiscountPercentage,
                custom_discount_amount: customDiscountAmount,
                consultant_id: document.getElementById('consultant_id').value,
                vehicles: vehicles.map(vehicle => ({
                    ...vehicle,
                    service_id: parseInt(vehicle.service_type)
                }))
            };

            console.log('Form data being sent:', formData);

            // Validate required fields
            const requiredFields = ['companyName', 'email', 'phone', 'bookingDate', 'test_location', 'consultant_id'];
            const missingFields = requiredFields.filter(field => !formData[field]);
            
            if (missingFields.length > 0) {
                console.error('Missing required fields:', missingFields);
                Swal.fire({
                    title: 'Error!',
                    text: `Missing required fields: ${missingFields.join(', ')}`,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Validate vehicles
            if (vehicles.length === 0) {
                console.error('No vehicles added');
                Swal.fire({
                    title: 'Error!',
                    text: 'Please add at least one vehicle',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Validate each vehicle
            const vehicleErrors = [];
            vehicles.forEach((vehicle, index) => {
                const requiredVehicleFields = ['make', 'vehicle_year', 'vin', 'license_plate', 'service_type'];
                const missingVehicleFields = requiredVehicleFields.filter(field => !vehicle[field]);
                
                if (missingVehicleFields.length > 0) {
                    vehicleErrors.push(`Vehicle ${index + 1} missing: ${missingVehicleFields.join(', ')}`);
                }
            });

            if (vehicleErrors.length > 0) {
                console.error('Vehicle validation errors:', vehicleErrors);
                Swal.fire({
                    title: 'Error!',
                    text: vehicleErrors.join('\n'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Send the data to the server
            console.log('Sending data to server...');
            fetch('save_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                console.log('Server response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Server data:', data);
                if (data.success) {
                    console.log('Success response received');
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        Swal.fire({
                            title: 'Success!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'admin_welcome.php';
                            }
                        });
                    }
                } else {
                    console.error('Error response received:', data.message);
                    Swal.fire({
                        title: 'Error!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while saving the appointment: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Function to calculate total price
        function calculateTotalPrice() {
            console.log('Calculating total price...');
            let totalPrice = 0;
            
            // Get all vehicle entries
            const vehicleEntries = document.querySelectorAll('.vehicle-entry');
            console.log('Number of vehicles for price calculation:', vehicleEntries.length);
            
            // Calculate price for each vehicle
            vehicleEntries.forEach((entry, index) => {
                const serviceSelect = entry.querySelector('select[name="service_type[]"]');
                const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
                
                if (selectedOption && selectedOption.value) {
                    const servicePrice = parseFloat(selectedOption.dataset.price) || 0;
                    console.log(`Vehicle ${index + 1} service price:`, servicePrice);
                    totalPrice += servicePrice;
                }
            });
            
            console.log('Subtotal before discount:', totalPrice);
            
            // Apply discount if any
            const selectedDiscount = document.querySelector('input[name="discount_type"]:checked');
            if (selectedDiscount && selectedDiscount.value !== 'none') {
                const discountContainer = selectedDiscount.closest('.discount-option, .custom-discount');
                let discountAmount = 0;
                
                if (selectedDiscount.value === 'custom') {
                    const percentageInput = discountContainer.querySelector('.custom-discount-percentage');
                    const amountInput = discountContainer.querySelector('.custom-discount-amount');
                    
                    if (percentageInput && parseFloat(percentageInput.value) > 0) {
                        discountAmount = (totalPrice * parseFloat(percentageInput.value)) / 100;
                        console.log('Custom percentage discount:', discountAmount);
                    } else if (amountInput && parseFloat(amountInput.value) > 0) {
                        discountAmount = parseFloat(amountInput.value);
                        console.log('Custom fixed discount:', discountAmount);
                    }
                } else {
                    const discountInput = discountContainer.querySelector('.discount-input input:not(.custom-discount-amount)');
                    if (discountInput) {
                        discountAmount = (totalPrice * parseFloat(discountInput.value)) / 100;
                        console.log('Standard discount:', discountAmount);
                    }
                }
                
                totalPrice -= discountAmount;
                console.log('Total after discount:', totalPrice);
            }
            
            return totalPrice;
        }

        // Function to update price display
        function updatePriceDisplay() {
            const totalPrice = calculateTotalPrice();
            document.getElementById('total_price').textContent = `$${totalPrice.toFixed(2)}`;
        }

        // Add event listeners for price updates
        document.getElementById('service_id').addEventListener('change', updatePriceDisplay);
        document.getElementById('number_of_vehicles').addEventListener('change', updatePriceDisplay);
        document.getElementById('discount_type').addEventListener('change', updatePriceDisplay);
        document.getElementById('discount_amount').addEventListener('input', updatePriceDisplay);
        document.getElementById('custom_discount_percentage').addEventListener('input', updatePriceDisplay);
        document.getElementById('custom_discount_amount').addEventListener('input', updatePriceDisplay);

        // Initialize price display
        updatePriceDisplay();

        // Add this function at the top of your JavaScript section
        function logToConsole(message, data) {
            console.log(`[${new Date().toISOString()}] ${message}:`, data);
        }

        // Update the calculateTotal function
        function calculateTotal() {
            let total = 0;
            let discountAmount = 0;
            let customDiscountPercentage = 0;
            let customDiscountAmount = 0;
            let discountType = $('#discount_type').val();

            logToConsole('Discount Type Selected', discountType);

            // Calculate subtotal
            $('.vehicle-row').each(function() {
                const price = parseFloat($(this).find('.service-price').val()) || 0;
                total += price;
            });

            logToConsole('Subtotal before discount', total);

            // Calculate discount
            if (discountType === 'percentage') {
                discountAmount = parseFloat($('#discount_amount').val()) || 0;
                logToConsole('Percentage Discount Amount', discountAmount);
                total = total - (total * (discountAmount / 100));
            } else if (discountType === 'custom') {
                customDiscountPercentage = parseFloat($('#custom_discount_percentage').val()) || 0;
                customDiscountAmount = parseFloat($('#custom_discount_amount').val()) || 0;
                logToConsole('Custom Discount Values', {
                    percentage: customDiscountPercentage,
                    amount: customDiscountAmount
                });
                
                if (customDiscountPercentage > 0) {
                    total = total - (total * (customDiscountPercentage / 100));
                } else {
                    total = total - customDiscountAmount;
                }
            }

            logToConsole('Final Total after discount', total);

            // Update the total display
            $('#total_price').val(total.toFixed(2));
            $('#total_display').text('$' + total.toFixed(2));
        }

        // Update the form submission
        $('#quotationForm').on('submit', function(e) {
            e.preventDefault();
            
            // Log form data before submission
            const formData = new FormData(this);
            const formDataObj = {};
            formData.forEach((value, key) => formDataObj[key] = value);
            
            logToConsole('Form Data being submitted', formDataObj);

            // Add logging for discount values specifically
            logToConsole('Discount Values', {
                type: $('#discount_type').val(),
                amount: $('#discount_amount').val(),
                custom_percentage: $('#custom_discount_percentage').val(),
                custom_amount: $('#custom_discount_amount').val()
            });

            $.ajax({
                url: 'save_appointment.php',
                type: 'POST',
                data: JSON.stringify(formDataObj),
                contentType: 'application/json',
                success: function(response) {
                    logToConsole('Server Response', response);
                    if (response.success) {
                        window.location.href = response.redirect_url;
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    logToConsole('Ajax Error', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    alert('Error submitting form: ' + error);
                }
            });
        });

        // Add logging to discount type change handler
        $('#discount_type').on('change', function() {
            const selectedType = $(this).val();
            logToConsole('Discount Type Changed', selectedType);
            
            if (selectedType === 'percentage') {
                $('#discount_amount').prop('disabled', false);
                $('#custom_discount_percentage, #custom_discount_amount').prop('disabled', true);
                logToConsole('Enabled percentage discount input');
            } else if (selectedType === 'custom') {
                $('#discount_amount').prop('disabled', true);
                $('#custom_discount_percentage, #custom_discount_amount').prop('disabled', false);
                logToConsole('Enabled custom discount inputs');
            } else {
                $('#discount_amount, #custom_discount_percentage, #custom_discount_amount').prop('disabled', true);
                logToConsole('Disabled all discount inputs');
            }
            calculateTotal();
        });

        // Add logging to discount amount change handlers
        $('#discount_amount, #custom_discount_percentage, #custom_discount_amount').on('change', function() {
            logToConsole('Discount Value Changed', {
                field: this.id,
                value: $(this).val()
            });
            calculateTotal();
        });
    </script>
</body>
</html> 