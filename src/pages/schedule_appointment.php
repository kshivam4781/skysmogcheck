<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone to PST
date_default_timezone_set('America/Los_Angeles');

// Get current date and time in PST
$current_date = date('Y-m-d');
$current_time = date('H:i');

// Function to get booked times for a specific date
function getBookedTimes($conn, $date) {
    $booked_times = [];
    $stmt = $conn->prepare("SELECT TIME(start_time) as time FROM calendar_events WHERE DATE(start_time) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $booked_times[] = $row['time'];
    }
    return $booked_times;
}

// Get all possible time slots
$all_time_slots = [
    '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '11:30:00',
    '12:00:00', '12:30:00', '13:00:00', '13:30:00', '14:00:00', '14:30:00',
    '15:00:00', '15:30:00', '16:00:00', '16:30:00', '17:00:00', '17:30:00'
];

// Get booked times for current date
$booked_times = getBookedTimes($conn, $current_date);
$available_times = array_diff($all_time_slots, $booked_times);

// Get all consultants
$stmt = $conn->prepare("SELECT * FROM accounts WHERE accountType = 2 ORDER BY firstName, lastName");
$stmt->execute();
$consultants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $company_name = trim($_POST['company']);
    $contact_name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $test_location = $_POST['test_location'];
    $test_address = $test_location === 'our_location' ? '121 E 11th St, Tracy, CA 95376' : trim($_POST['test_address']);
    $booking_date = $_POST['preferred_date'];
    $booking_time = $_POST['preferred_time'];
    $consultant_id = $_POST['consultant_id'];
    $special_instructions = trim($_POST['special_instructions']);
    $approved_by = $_SESSION['user_id']; // Get the current user's email from session
    
    // Get vehicle information
    $vehicle_years = $_POST['vehicle_year'] ?? [];
    $vehicle_makes = $_POST['make'] ?? [];
    $vehicle_vins = $_POST['vin'] ?? [];
    $vehicle_plates = $_POST['license_plate'] ?? [];
    
    // Validate required fields
    if (empty($company_name) || empty($contact_name) || empty($email) || empty($phone) || 
        empty($test_location) || empty($booking_date) || empty($booking_time) || empty($consultant_id) ||
        empty($vehicle_years) || empty($vehicle_makes) || empty($vehicle_vins) || empty($vehicle_plates)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert into appointments table
        $number_of_vehicles = count($vehicle_years);
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                companyName, Name, email, phone, test_location, test_address, 
                bookingDate, bookingTime, approved_by, special_instructions, status,
                number_of_vehicles
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)
        ");
        
        $stmt->bind_param("ssssssssssi", 
            $company_name, $contact_name, $email, $phone, $test_location, $test_address,
            $booking_date, $booking_time, $approved_by, $special_instructions,
            $number_of_vehicles
        );
        
        if ($stmt->execute()) {
            $appointment_id = $conn->insert_id;
            
            // Insert vehicles
            $vehicle_stmt = $conn->prepare("
                INSERT INTO vehicles (appointment_id, vehYear, vehMake, vin, plateNo) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($vehicle_years as $index => $year) {
                $vehicle_stmt->bind_param("issss", 
                    $appointment_id, 
                    $year, 
                    $vehicle_makes[$index], 
                    $vehicle_vins[$index], 
                    $vehicle_plates[$index]
                );
                $vehicle_stmt->execute();
            }
            
            // Insert into calendar_events table
            $start_time = $booking_date . ' ' . $booking_time;
            $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' +1 hour'));
            
            $stmt = $conn->prepare("
                INSERT INTO calendar_events (
                    title, start_time, end_time, appointment_id, user_id, description, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')
            ");
            
            $title = $company_name . " - Smoke Test";
            $description = "Number of Vehicles: " . $number_of_vehicles . "\nTest Address: " . $test_address;
            $stmt->bind_param("sssiss", $title, $start_time, $end_time, $appointment_id, $consultant_id, $description);
            
            if ($stmt->execute()) {
                header("Location: admin_welcome.php?success=1");
                exit();
            } else {
                $error = "Error creating calendar event: " . $conn->error;
            }
        } else {
            $error = "Error creating appointment: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        /* Reuse styles from admin_welcome.php */
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
        /* Form styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-title {
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        /* Form section styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .form-section h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-subsection {
            background: white;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .form-subsection h3 {
            color: #495057;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 5px;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        .form-control {
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            padding: 10px 20px;
            font-weight: 500;
        }

        .alert {
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        /* Vehicle entry styles */
        .vehicle-entry {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            position: relative;
        }

        .remove-vehicle {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .remove-vehicle:hover {
            background: #c82333;
        }

        .vehicle-count {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        /* Test location styles */
        #address-container {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
        }

        /* Consultant assignment styles */
        .consultant-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        /* Button container styles */
        .button-container {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .add-vehicle-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            margin: 10px auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .add-vehicle-btn:hover {
            background: #218838;
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .add-vehicle-btn i {
            font-size: 1.2rem;
        }

        .vehicle-actions {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }

        /* Settings Dropdown Styles */
        .settings-dropdown {
            position: relative;
        }

        .settings-toggle {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .settings-toggle:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .settings-menu {
            position: absolute;
            bottom: 100%;
            left: 0;
            background-color: #343a40;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            min-width: 200px;
            display: none;
            z-index: 1000;
        }

        .settings-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .settings-menu a:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .settings-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .settings-divider {
            height: 1px;
            background-color: rgba(255,255,255,0.1);
            margin: 5px 0;
        }

        .logout-link {
            color: #dc3545;
        }

        .logout-link:hover {
            background-color: rgba(220,53,69,0.1);
        }

        @media (max-width: 768px) {
            .settings-menu {
                position: fixed;
                bottom: auto;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 80%;
                max-width: 300px;
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
                    <a href="calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> Calendar
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
                        <a href="schedule_appointment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schedule_appointment.php' ? 'active' : ''; ?>" style="background-color: #28a745; color: white;">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </a>
                    </li>
                    <li>
                        <a href="view_appointments.php">
                            <i class="fas fa-calendar-check"></i> View Appointments
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
                <div class="form-container">
                    <h2 class="form-title">Schedule New Appointment</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- Customer Information -->
                        <section class="form-section">
                            <h2>Customer Information</h2>
                            <div class="form-subsection">
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
                            </div>
                        </section>

                        <!-- Vehicle Information -->
                        <section class="form-section">
                            <h2>Vehicle Information</h2>
                            <div id="vehicle-container">
                                <div class="vehicle-entry">
                                    <div class="vehicle-count">Vehicle 1</div>
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
                            </div>
                            <div class="vehicle-actions">
                                <button type="button" class="add-vehicle-btn" id="add-vehicle" title="Add Another Vehicle">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </section>

                        <!-- Test Details -->
                        <section class="form-section">
                            <h2>Test Details</h2>
                            <div class="form-subsection">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="preferred_date" class="form-label required-field">Date</label>
                                            <input type="date" id="preferred_date" name="preferred_date" class="form-control" required 
                                                   min="<?php echo $current_date; ?>" value="<?php echo $current_date; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="preferred_time" class="form-label required-field">Time</label>
                                            <select id="preferred_time" name="preferred_time" class="form-control" required>
                                                <option value="">Select Time</option>
                                                <?php foreach ($available_times as $time): ?>
                                                    <?php 
                                                    $display_time = date('g:i A', strtotime($time));
                                                    $value = date('H:i', strtotime($time));
                                                    ?>
                                                    <option value="<?php echo $value; ?>">
                                                        <?php echo $display_time; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="test_location" class="form-label required-field">Test Location</label>
                                            <select id="test_location" name="test_location" class="form-control" required>
                                                <option value="our_location" selected>121 E 11th St, Tracy, CA 95376</option>
                                                <option value="your_location">At Your Location</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row" id="address-container" style="display: none;">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="test_address" class="form-label required-field">Test Address</label>
                                            <textarea id="test_address" name="test_address" class="form-control" rows="3" 
                                                      placeholder="Please provide the complete address where the test will be conducted"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Additional Information -->
                        <section class="form-section">
                            <h2>Additional Notes</h2>
                            <div class="form-subsection">
                                <div class="form-group">
                                    <label for="special_instructions" class="form-label">Special Instructions</label>
                                    <textarea id="special_instructions" name="special_instructions" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                        </section>

                        <!-- Consultant Assignment -->
                        <section class="consultant-section">
                            <h2>Consultant Assignment</h2>
                            <div class="form-group">
                                <label for="consultant_id" class="form-label required-field">Assigned Consultant</label>
                                <select class="form-control" id="consultant_id" name="consultant_id" required>
                                    <option value="">Select Consultant</option>
                                    <?php foreach ($consultants as $consultant): ?>
                                        <option value="<?php echo $consultant['email']; ?>">
                                            <?php echo htmlspecialchars($consultant['firstName'] . ' ' . $consultant['lastName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </section>

                        <div class="button-container">
                            <a href="admin_welcome.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-2"></i> Schedule Appointment
                            </button>
                        </div>
                    </form>
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

        document.addEventListener('DOMContentLoaded', function() {
            const vehicleContainer = document.getElementById('vehicle-container');
            const addVehicleButton = document.getElementById('add-vehicle');
            const testLocationSelect = document.getElementById('test_location');
            const addressContainer = document.getElementById('address-container');
            const testAddressField = document.getElementById('test_address');
            let vehicleCount = 1;

            // Function to update location options based on vehicle count
            function updateLocationOptions() {
                if (vehicleCount > 3) {
                    testLocationSelect.innerHTML = `
                        <option value="our_location">121 E 11th St, Tracy, CA 95376</option>
                        <option value="your_location">At Your Location</option>
                    `;
                } else {
                    testLocationSelect.innerHTML = `
                        <option value="our_location">121 E 11th St, Tracy, CA 95376</option>
                    `;
                    addressContainer.style.display = 'none';
                    testAddressField.removeAttribute('required');
                }
            }

            // Handle location selection change
            testLocationSelect.addEventListener('change', function() {
                if (this.value === 'your_location') {
                    addressContainer.style.display = 'block';
                    testAddressField.setAttribute('required', 'required');
                } else {
                    addressContainer.style.display = 'none';
                    testAddressField.removeAttribute('required');
                }
            });

            // Function to update vehicle numbers
            function updateVehicleNumbers() {
                const entries = vehicleContainer.querySelectorAll('.vehicle-entry');
                entries.forEach((entry, index) => {
                    entry.querySelector('.vehicle-count').textContent = `Vehicle ${index + 1}`;
                });
            }

            // Add vehicle button click handler
            addVehicleButton.addEventListener('click', function() {
                vehicleCount++;
                const newVehicleEntry = document.createElement('div');
                newVehicleEntry.className = 'vehicle-entry mt-3';
                newVehicleEntry.innerHTML = `
                    <div class="vehicle-count">Vehicle ${vehicleCount}</div>
                    <button type="button" class="remove-vehicle" title="Remove Vehicle">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="vehicle_year_${vehicleCount}" class="form-label required-field">Vehicle Year</label>
                                <input type="number" id="vehicle_year_${vehicleCount}" name="vehicle_year[]" class="form-control" required 
                                       min="1980" max="2024">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="make_${vehicleCount}" class="form-label required-field">Make</label>
                                <input type="text" id="make_${vehicleCount}" name="make[]" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="vin_${vehicleCount}" class="form-label required-field">VIN Number</label>
                                <input type="text" id="vin_${vehicleCount}" name="vin[]" class="form-control" required 
                                       pattern="[A-HJ-NPR-Z0-9]{17}" 
                                       title="Please enter a valid 17-character VIN number">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="license_plate_${vehicleCount}" class="form-label required-field">License Plate</label>
                                <input type="text" id="license_plate_${vehicleCount}" name="license_plate[]" class="form-control" required>
                            </div>
                        </div>
                    </div>
                `;
                vehicleContainer.appendChild(newVehicleEntry);

                // Add event listener for the remove button
                const removeButton = newVehicleEntry.querySelector('.remove-vehicle');
                removeButton.addEventListener('click', function() {
                    newVehicleEntry.remove();
                    vehicleCount--;
                    updateVehicleNumbers();
                });

                // Add vehicle year validation for the new vehicle
                const newVehicleYearInput = newVehicleEntry.querySelector('input[name="vehicle_year[]"]');
                newVehicleYearInput.addEventListener('change', function(e) {
                    const year = parseInt(e.target.value);
                    if (year < 2010) {
                        alert('We do not perform smog checks for vehicles older than 2010.');
                        e.target.value = '';
                        e.target.focus();
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function(e) {
                // Remove all non-digit characters
                let value = e.target.value.replace(/\D/g, '');
                
                // Format the phone number as (xxx) xxx-xxxx
                if (value.length > 0) {
                    if (value.length <= 3) {
                        value = '(' + value;
                    } else if (value.length <= 6) {
                        value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                    } else {
                        value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                    }
                }
                
                // Update the input value
                e.target.value = value;
            });

            // Email validation
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('blur', function(e) {
                const email = e.target.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    alert('Please enter a valid email address');
                    e.target.focus();
                }
            });

            // Vehicle year validation
            const vehicleYearInputs = document.querySelectorAll('input[name="vehicle_year[]"]');
            vehicleYearInputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    const year = parseInt(e.target.value);
                    if (year < 2010) {
                        alert('We do not perform smog checks for vehicles older than 2010.');
                        e.target.value = '';
                        e.target.focus();
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const preferredDate = document.getElementById('preferred_date');
            const preferredTime = document.getElementById('preferred_time');

            preferredDate.addEventListener('change', function() {
                const selectedDate = this.value;
                
                // Fetch available times for the selected date
                fetch('get_available_times.php?date=' + selectedDate)
                    .then(response => response.json())
                    .then(data => {
                        // Clear existing options
                        preferredTime.innerHTML = '<option value="">Select Time</option>';
                        
                        // Add available time slots
                        data.forEach(time => {
                            const option = document.createElement('option');
                            const displayTime = new Date('2000-01-01 ' + time).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            option.value = time;
                            option.textContent = displayTime;
                            preferredTime.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error:', error));
            });
        });

        // Settings dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const settingsToggle = document.getElementById('settingsToggle');
            const settingsMenu = document.getElementById('settingsMenu');

            settingsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                settingsMenu.style.display = settingsMenu.style.display === 'block' ? 'none' : 'block';
            });

            // Close settings menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!settingsToggle.contains(e.target) && !settingsMenu.contains(e.target)) {
                    settingsMenu.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html> 