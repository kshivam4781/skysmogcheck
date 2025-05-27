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

// Get all calendar events with related information
$stmt = $conn->prepare("
    SELECT 
        ce.*,
        a.companyName,
        a.Name as contactName,
        a.phone,
        a.test_address,
        a.test_location,
        a.status as appointment_status,
        a.total_price,
        a.discount_type,
        a.discount_amount,
        a.discount_percentage,
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
        acc.firstName as consultant_first_name,
        acc.lastName as consultant_last_name
    FROM calendar_events ce
    LEFT JOIN appointments a ON ce.appointment_id = a.id
    LEFT JOIN vehicles v ON ce.vehid = v.id
    LEFT JOIN accounts acc ON ce.user_id = acc.idaccounts
    ORDER BY ce.start_time DESC
");
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique users and statuses for filters
$users = array_unique(array_column($events, 'user_id'));
$statuses = array_unique(array_column($events, 'status'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Events - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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
        .events-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .event-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .event-card:hover {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .event-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .event-status {
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
        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .event-detail {
            display: flex;
            align-items: center;
        }
        .event-detail i {
            margin-right: 8px;
            color: #6c757d;
            width: 20px;
        }
        .event-description {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
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
        .calendar-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 20px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .view-toggle {
            margin-bottom: 20px;
        }
        
        .view-toggle .btn {
            margin-right: 10px;
        }
        
        .fc {
            font-size: 0.9em;
        }
        
        .fc .fc-toolbar {
            margin-bottom: 1em;
        }
        
        .fc .fc-toolbar-title {
            font-size: 1.2em;
        }
        
        .fc .fc-button {
            padding: 0.3em 0.6em;
            font-size: 0.85em;
        }
        
        .fc .fc-daygrid-day {
            min-height: 40px;
        }
        
        .fc .fc-daygrid-day-number {
            font-size: 0.85em;
            padding: 2px 4px;
        }
        
        .fc .fc-daygrid-event {
            margin-top: 1px;
            padding: 1px 2px;
        }
        
        .fc-event-title {
            font-size: 0.8em;
            padding: 0 2px;
        }
        
        .fc-event-time {
            font-size: 0.75em;
            padding: 0 2px;
        }
        
        .fc .fc-daygrid-more-link {
            font-size: 0.75em;
        }
        
        .fc .fc-col-header-cell {
            padding: 4px 0;
        }
        
        .fc .fc-col-header-cell-cushion {
            padding: 4px;
            font-size: 0.85em;
        }
        
        .fc .fc-daygrid-day-frame {
            padding: 2px;
        }
        
        .fc .fc-scrollgrid {
            border-width: 1px;
        }
        
        .fc .fc-scrollgrid-section > * {
            border-width: 1px;
        }
        
        .fc .fc-daygrid-day-frame {
            min-height: 30px;
        }
        
        .fc .fc-daygrid-event-harness {
            margin-top: 1px;
        }
        
        .fc .fc-daygrid-day-events {
            padding: 1px;
        }

        /* Side Peek Styles */
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
                <h2 class="mb-4">Calendar Events</h2>
                
                <!-- Filter Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="appointmentIdFilter" class="form-label">Appointment ID</label>
                                <input type="text" class="form-control" id="appointmentIdFilter" placeholder="Enter ID">
                            </div>
                            <div class="col-md-3">
                                <label for="userFilter" class="form-label">User</label>
                                <select class="form-select" id="userFilter">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user; ?>">User <?php echo $user; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="dateRangeFilter" class="form-label">Date Range</label>
                                <select class="form-select" id="dateRangeFilter">
                                    <option value="all">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="viewToggle" class="form-label">View</label>
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-primary active" data-view="calendar">
                                        <i class="fas fa-calendar"></i> Calendar
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" data-view="list">
                                        <i class="fas fa-list"></i> List
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Custom Date Range (initially hidden) -->
                        <div class="row g-3 mt-2" id="customDateRange" style="display: none;">
                            <div class="col-md-6">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate">
                            </div>
                            <div class="col-md-6">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Calendar View -->
                <div class="calendar-container calendar-view" id="calendarView">
                    <div id="calendar"></div>
                </div>
                
                <!-- List View -->
                <div class="events-container list-view" id="listView" style="display: none;">
                    <div class="table-responsive">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <select class="form-select form-select-sm me-2" id="pageSize" style="width: auto;">
                                    <option value="10">10 per page</option>
                                    <option value="25">25 per page</option>
                                    <option value="50">50 per page</option>
                                    <option value="100">100 per page</option>
                                </select>
                                <span class="text-muted" id="listInfo"></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="text" class="form-control form-control-sm me-2" id="searchInput" placeholder="Search...">
                                <button class="btn btn-sm btn-outline-secondary" id="clearFilters">
                                    <i class="fas fa-times"></i> Clear Filters
                                </button>
                            </div>
                        </div>
                        <div class="table-loading" style="display: none;">
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Date & Time</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="listViewBody">
                                <!-- Table body will be populated dynamically -->
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <button class="btn btn-sm btn-outline-primary" id="prevPage" disabled>
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                                <span class="mx-2" id="pageInfo"></span>
                                <button class="btn btn-sm btn-outline-primary" id="nextPage">
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div class="text-muted" id="totalInfo"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Peek for Event Details -->
    <div class="side-peek" id="eventSidePeek">
        <div class="side-peek-header">
            <h5 class="side-peek-title">Event Details</h5>
            <div class="side-peek-actions">
                <a href="#" class="btn btn-sm btn-outline-primary" id="editAppointmentBtn">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="#" class="btn btn-sm btn-outline-primary" id="edButton">
                    ED
                </a>
                <button class="btn btn-sm btn-outline-danger" id="cancelAppointmentBtn">
                    <i class="fas fa-ban"></i>
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
            <div class="event-details">
                <!-- Basic Information Section -->
                <div class="section mb-4">
                    <h6 class="section-title">Basic Information</h6>
                    <div class="mb-3">
                        <h4 id="eventTitle" class="text-primary mb-2"></h4>
                        <p id="appointmentId" class="text-muted mb-0">Appointment ID: <span></span></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Date & Time</h6>
                        <p id="eventDateTime" class="mb-0"></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Status</h6>
                        <p id="eventStatus" class="mb-0"></p>
                    </div>
                </div>

                <!-- Company & Contact Section -->
                <div class="section mb-4">
                    <h6 class="section-title">Company & Contact</h6>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Company</h6>
                        <p id="companyName" class="mb-0"></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Contact Person</h6>
                        <p id="contactName" class="mb-0"></p>
                        <p id="contactPhone" class="mb-0"></p>
                    </div>
                </div>

                <!-- Location Section -->
                <div class="section mb-4">
                    <h6 class="section-title">Location Details</h6>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Test Location</h6>
                        <p id="testAddress" class="mb-0"></p>
                    </div>
                </div>

                <!-- Vehicle Information Section -->
                <div class="section mb-4">
                    <h6 class="section-title">Vehicle Information</h6>
                    <div id="vehicleGrid" class="row g-3">
                        <!-- Vehicle details will be populated here -->
                    </div>
                </div>

                <!-- Test Results Section -->
                <div class="section mb-4">
                    <h6 class="section-title">Test Results</h6>
                    <div id="testResults">
                        <!-- Test results will be populated here -->
                    </div>
                    <!-- Update Result Form -->
                    <div id="updateResultForm" style="display: none;">
                        <form id="resultUpdateForm" class="mt-3">
                            <div class="mb-3">
                                <label for="result" class="form-label">Result</label>
                                <select class="form-select" id="result" name="result" required>
                                    <option value="">Select Result</option>
                                    <option value="pass">Pass</option>
                                    <option value="fail">Fail</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Update Result</button>
                                <button type="button" class="btn btn-secondary" id="cancelUpdateForm">Cancel</button>
                            </div>
                        </form>
                    </div>
                    <!-- Edit Status Button -->
                    <div class="mt-3" id="editStatusButton" style="display: none;">
                        <button class="btn btn-outline-primary" id="showEditStatusForm">
                            <i class="fas fa-edit"></i> Edit Status
                        </button>
                    </div>
                </div>

                <!-- Pricing Section -->
                <div class="section mb-4">
                    <h6 class="section-title">Pricing Information</h6>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Total Price</h6>
                        <p id="totalPrice" class="mb-0"></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Discount</h6>
                        <p id="discountInfo" class="mb-0"></p>
                    </div>
                </div>

                <!-- Action Buttons Section -->
                <div class="section mt-4">
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary" id="editAppointmentBtnBottom">
                            <i class="fas fa-edit"></i> Edit Appointment
                        </a>
                        <button class="btn btn-outline-danger" id="cancelAppointmentBtnBottom">
                            <i class="fas fa-ban"></i> Cancel Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal fade" id="cancelAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="cancelAppointmentForm">
                        <input type="hidden" id="cancelAppointmentId" name="appointment_id">
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                            <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notifyClient" class="form-label">Notification</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notifyClient" name="notify_client" checked>
                                <label class="form-check-label" for="notifyClient">
                                    Notify client about cancellation
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelAppointment">Cancel Appointment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
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

        // Helper functions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function filterByDate(eventDate, dateRange, startDate, endDate) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            switch (dateRange) {
                case 'today':
                    return eventDate.toDateString() === today.toDateString();
                case 'week':
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekStart.getDate() + 6);
                    return eventDate >= weekStart && eventDate <= weekEnd;
                case 'month':
                    return eventDate.getMonth() === today.getMonth() && 
                           eventDate.getFullYear() === today.getFullYear();
                case 'custom':
                    if (!startDate || !endDate) return true;
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    end.setHours(23, 59, 59, 999);
                    return eventDate >= start && eventDate <= end;
                default:
                    return true;
            }
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatDateTime(start, end) {
            const startDate = new Date(start);
            const endDate = end ? new Date(end) : null;
            
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            
            let formatted = startDate.toLocaleString('en-US', options);
            if (endDate) {
                formatted += ` - ${endDate.toLocaleString('en-US', options)}`;
            }
            
            return formatted;
        }

        function getStatusColor(status) {
            switch (status.toLowerCase()) {
                case 'completed': return 'success';
                case 'confirmed': return 'info';
                case 'pending': return 'warning';
                case 'cancelled': return 'danger';
                default: return 'secondary';
            }
        }

        function capitalizeFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
        }

        // Event details and side peek functionality
        let overlay = null;

        function createOverlay() {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'overlay';
                document.body.appendChild(overlay);
            }
            return overlay;
        }

        function removeOverlay() {
            if (overlay) {
                overlay.remove();
                overlay = null;
            }
        }

        function showEventDetails(event) {
            const sidePeek = document.getElementById('eventSidePeek');
            if (!sidePeek) return;

            const overlay = createOverlay();
            
            // Populate side peek content
            const appointmentIdSpan = document.querySelector('#appointmentId span');
            if (appointmentIdSpan) {
                appointmentIdSpan.textContent = event.extendedProps.appointment_id || 'N/A';
            }

            const eventTitle = document.getElementById('eventTitle');
            if (eventTitle) {
                eventTitle.textContent = event.title;
            }

            const eventDateTime = document.getElementById('eventDateTime');
            if (eventDateTime) {
                eventDateTime.textContent = `${event.start.toLocaleString()} - ${event.end ? event.end.toLocaleString() : 'N/A'}`;
            }

            const companyName = document.getElementById('companyName');
            if (companyName) {
                companyName.textContent = event.extendedProps.company || 'N/A';
            }

            const testAddress = document.getElementById('testAddress');
            if (testAddress) {
                testAddress.textContent = event.extendedProps.description ? 
                    event.extendedProps.description.replace('Test Address: ', '') : 'N/A';
            }

            const contactName = document.getElementById('contactName');
            if (contactName) {
                contactName.textContent = event.extendedProps.contact || 'N/A';
            }

            const contactPhone = document.getElementById('contactPhone');
            if (contactPhone) {
                contactPhone.textContent = event.extendedProps.phone || 'N/A';
            }
            
            // Set status with appropriate styling
            const statusElement = document.getElementById('eventStatus');
            if (statusElement) {
                const status = event.extendedProps.status || 'N/A';
                statusElement.innerHTML = `<span class="status-badge status-${status.toLowerCase()}">${capitalizeFirst(status)}</span>`;
            }

            // Populate vehicle information
            const vehicleGrid = document.getElementById('vehicleGrid');
            if (vehicleGrid) {
                vehicleGrid.innerHTML = `
                    <div class="col-12">
                        <div class="vehicle-card">
                            <h6>Vehicle Details</h6>
                            <p><strong>Year:</strong> ${event.extendedProps.vehYear || 'N/A'}</p>
                            <p><strong>Make:</strong> ${event.extendedProps.vehMake || 'N/A'}</p>
                            <p><strong>Plate Number:</strong> ${event.extendedProps.plateNo || 'N/A'}</p>
                            <p><strong>VIN:</strong> ${event.extendedProps.vin || 'N/A'}</p>
                            <p><strong>Smoke Test Status:</strong> ${event.extendedProps.smoke_test_status || 'N/A'}</p>
                            <p><strong>Clean Truck Check Status:</strong> ${event.extendedProps.clean_truck_check_status || 'N/A'}</p>
                        </div>
                    </div>
                `;
            }

            // Populate pricing information
            const totalPrice = document.getElementById('totalPrice');
            if (totalPrice) {
                totalPrice.textContent = event.extendedProps.total_price ? 
                    `$${event.extendedProps.total_price}` : 'N/A';
            }
            
            const discountInfo = document.getElementById('discountInfo');
            if (discountInfo) {
                let discountText = 'No discount';
                if (event.extendedProps.discount_type) {
                    if (event.extendedProps.discount_type === 'percentage') {
                        discountText = `${event.extendedProps.discount_percentage}% off`;
                    } else if (event.extendedProps.discount_type === 'amount') {
                        discountText = `$${event.extendedProps.discount_amount} off`;
                    }
                }
                discountInfo.textContent = discountText;
            }

            // Show side peek and overlay
            sidePeek.classList.add('active');
            overlay.classList.add('active');

            // Store both event ID and appointment ID
            sidePeek.dataset.eventId = event.id;
            sidePeek.dataset.appointmentId = event.extendedProps.appointment_id;
            
            // Show/hide action buttons based on appointment status
            const status = event.extendedProps.status || 'N/A';
            const cancelButton = document.getElementById('cancelAppointmentBtn');
            const cancelButtonBottom = document.getElementById('cancelAppointmentBtnBottom');
            const editButton = document.getElementById('editAppointmentBtn');
            const editButtonBottom = document.getElementById('editAppointmentBtnBottom');
            
            if (cancelButton) {
                cancelButton.style.display = (status === 'cancelled' || status === 'completed') ? 'none' : 'block';
            }
            if (cancelButtonBottom) {
                cancelButtonBottom.style.display = (status === 'cancelled' || status === 'completed') ? 'none' : 'block';
            }
            if (editButton) {
                editButton.style.display = (status === 'cancelled' || status === 'completed') ? 'none' : 'block';
            }
            if (editButtonBottom) {
                editButtonBottom.style.display = (status === 'cancelled' || status === 'completed') ? 'none' : 'block';
            }

            // Update the edit button hrefs
            if (editButton) {
                editButton.href = 'edit_appointment.php?id=' + event.extendedProps.appointment_id;
            }
            if (editButtonBottom) {
                editButtonBottom.href = 'edit_appointment.php?id=' + event.extendedProps.appointment_id;
            }
            const edButton = document.getElementById('edButton');
            if (edButton) {
                edButton.href = 'edit_appointment.php?id=' + event.extendedProps.appointment_id;
            }

            // Initialize side peek buttons
            initializeSidePeekButtons(sidePeek);
        }

        function initializeSidePeekButtons(sidePeek) {
            // Toggle fullscreen button
            const toggleFullScreenBtn = document.getElementById('toggleFullScreen');
            if (toggleFullScreenBtn) {
                toggleFullScreenBtn.addEventListener('click', function() {
                    sidePeek.classList.toggle('fullscreen');
                    const icon = this.querySelector('i');
                    if (icon) {
                        if (sidePeek.classList.contains('fullscreen')) {
                            icon.classList.remove('fa-expand');
                            icon.classList.add('fa-compress');
                        } else {
                            icon.classList.remove('fa-compress');
                            icon.classList.add('fa-expand');
                        }
                    }
                });
            }

            // Close side peek button
            const closeSidePeekBtn = document.getElementById('closeSidePeek');
            if (closeSidePeekBtn) {
                closeSidePeekBtn.addEventListener('click', function() {
                    closeSidePeek(sidePeek);
                });
            }

            // Close on overlay click
            const overlay = document.querySelector('.overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidePeek(sidePeek);
                    }
                });
            }

            // Edit appointment buttons
            const editButtons = [
                document.getElementById('editAppointmentBtn'),
                document.getElementById('editAppointmentBtnBottom')
            ];
            editButtons.forEach(button => {
                if (button) {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const appointmentId = sidePeek.dataset.appointmentId;
                        if (appointmentId) {
                            window.location.href = `edit_appointment.php?id=${appointmentId}`;
                        }
                    });
                }
            });

            // Cancel appointment buttons
            const cancelButtons = [
                document.getElementById('cancelAppointmentBtn'),
                document.getElementById('cancelAppointmentBtnBottom')
            ];
            cancelButtons.forEach(button => {
                if (button) {
                    button.addEventListener('click', function() {
                handleAppointmentCancellation(this);
            });
                }
            });
        }

        function closeSidePeek(sidePeek) {
            if (!sidePeek) return;
            
            sidePeek.classList.remove('active', 'fullscreen');
            removeOverlay();
            
            // Reset fullscreen button icon
            const toggleFullScreenBtn = document.getElementById('toggleFullScreen');
            if (toggleFullScreenBtn) {
                const icon = toggleFullScreenBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-compress');
                    icon.classList.add('fa-expand');
                }
            }
        }

            function handleAppointmentCancellation(button) {
            const sidePeek = document.getElementById('eventSidePeek');
            if (!sidePeek) return;

            const appointmentId = sidePeek.dataset.appointmentId;
                if (!appointmentId) {
                    showErrorModal('Error: No appointment ID found');
                    return;
                }

                // Show confirmation modal
                const confirmModal = document.createElement('div');
                confirmModal.className = 'modal fade';
                confirmModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirm Cancellation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel this appointment?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                                <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel Appointment</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(confirmModal);
                
                const modal = new bootstrap.Modal(confirmModal);
                modal.show();

                // Handle confirmation
            const confirmButton = confirmModal.querySelector('#confirmCancel');
            if (confirmButton) {
                confirmButton.addEventListener('click', function() {
                    // Create form data
                    const formData = new FormData();
                    formData.append('appointment_id', appointmentId);
                    formData.append('reason', 'Cancelled via cancel button');
                    formData.append('notify_client', '0');

                    // Show loading state
                    const originalButtonText = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    // Send cancellation request
                    fetch('cancel_appointment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Close the confirmation modal
                            modal.hide();
                            
                            // Show success modal
                            showSuccessModal('Appointment cancelled successfully', () => {
                                // Close the side peek
                                closeSidePeek(sidePeek);
                                
                                // Reload the page to show updated status
                                window.location.reload();
                            });
                        } else {
                            throw new Error(data.message || 'Failed to cancel appointment');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showErrorModal('Error cancelling appointment: ' + error.message);
                    })
                    .finally(() => {
                        // Reset button state
                        button.disabled = false;
                        button.innerHTML = originalButtonText;
                    });
                });
            }

                // Remove modal when closed
                confirmModal.addEventListener('hidden.bs.modal', function() {
                    confirmModal.remove();
                });
            }

            function showSuccessModal(message, callback) {
                const successModal = document.createElement('div');
                successModal.className = 'modal fade';
                successModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Success</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                    <p class="mt-3">${message}</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(successModal);
                
                const modal = new bootstrap.Modal(successModal);
                modal.show();
                
                // Handle callback after modal is closed
                successModal.addEventListener('hidden.bs.modal', function() {
                    successModal.remove();
                    if (typeof callback === 'function') {
                        callback();
                    }
                });
            }

            function showErrorModal(message) {
                const errorModal = document.createElement('div');
                errorModal.className = 'modal fade';
                errorModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Error</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fas fa-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                                    <p class="mt-3">${message}</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(errorModal);
                
                const modal = new bootstrap.Modal(errorModal);
                modal.show();
                
                // Remove modal when closed
                errorModal.addEventListener('hidden.bs.modal', function() {
                    errorModal.remove();
                });
            }

        // Apply filters function
        function applyFilters() {
            const userFilter = document.getElementById('userFilter')?.value || '';
            const statusFilter = document.getElementById('statusFilter')?.value || '';
            const dateRange = document.getElementById('dateRangeFilter')?.value || 'all';
            const startDate = document.getElementById('startDate')?.value || '';
            const endDate = document.getElementById('endDate')?.value || '';
            const appointmentIdFilter = document.getElementById('appointmentIdFilter')?.value || '';
            const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';

            // Show loading state
            const tableLoading = document.querySelector('.table-loading');
            const listViewBody = document.getElementById('listViewBody');
            if (tableLoading && listViewBody) {
                tableLoading.style.display = 'block';
                listViewBody.style.display = 'none';
            }

            // Get all events
            const events = <?php echo json_encode($events); ?>;
            
            // Filter events
            filteredEvents = events.filter(event => {
                // User filter
                const matchesUser = !userFilter || event.user_id == userFilter;
                
                // Status filter
                const matchesStatus = !statusFilter || event.status === statusFilter;
                
                // Date filter
                const eventDate = new Date(event.start_time);
                const matchesDate = filterByDate(eventDate, dateRange, startDate, endDate);
                
                // Appointment ID filter
                const matchesAppointmentId = !appointmentIdFilter || 
                    (event.appointment_id && event.appointment_id.toString().includes(appointmentIdFilter));
                
                // Search term filter
                const matchesSearch = !searchTerm || 
                    (event.title && event.title.toLowerCase().includes(searchTerm)) ||
                    (event.companyName && event.companyName.toLowerCase().includes(searchTerm)) ||
                    (event.contactName && event.contactName.toLowerCase().includes(searchTerm)) ||
                    (event.phone && event.phone.toString().includes(searchTerm)) ||
                    (event.description && event.description.toLowerCase().includes(searchTerm)) ||
                    (event.vehYear && event.vehYear.toString().includes(searchTerm)) ||
                    (event.vehMake && event.vehMake.toLowerCase().includes(searchTerm)) ||
                    (event.plateNo && event.plateNo.toLowerCase().includes(searchTerm)) ||
                    (event.vin && event.vin.toLowerCase().includes(searchTerm));

                return matchesUser && matchesStatus && matchesDate && matchesAppointmentId && matchesSearch;
            });

            // Update calendar view if it exists
            const calendar = document.querySelector('#calendar')?.__fullCalendar;
            if (calendar) {
                calendar.getEvents().forEach(event => {
                    const eventData = events.find(e => e.id == event.id);
                    if (eventData) {
                        const matchesUser = !userFilter || eventData.user_id == userFilter;
                        const matchesStatus = !statusFilter || eventData.status === statusFilter;
                        const matchesDate = filterByDate(new Date(eventData.start_time), dateRange, startDate, endDate);
                        const matchesAppointmentId = !appointmentIdFilter || 
                            (eventData.appointment_id && eventData.appointment_id.toString().includes(appointmentIdFilter));
                        const matchesSearch = !searchTerm || 
                            (eventData.title && eventData.title.toLowerCase().includes(searchTerm)) ||
                            (eventData.companyName && eventData.companyName.toLowerCase().includes(searchTerm)) ||
                            (eventData.contactName && eventData.contactName.toLowerCase().includes(searchTerm)) ||
                            (eventData.phone && eventData.phone.toString().includes(searchTerm)) ||
                            (eventData.description && eventData.description.toLowerCase().includes(searchTerm)) ||
                            (eventData.vehYear && eventData.vehYear.toString().includes(searchTerm)) ||
                            (eventData.vehMake && eventData.vehMake.toLowerCase().includes(searchTerm)) ||
                            (eventData.plateNo && eventData.plateNo.toLowerCase().includes(searchTerm)) ||
                            (eventData.vin && eventData.vin.toLowerCase().includes(searchTerm));

                        event.setProp('display', matchesUser && matchesStatus && matchesDate && matchesAppointmentId && matchesSearch ? 'auto' : 'none');
                    }
                });
            }

            // Update list view if it's visible
            if (document.getElementById('listView')?.style.display !== 'none') {
                currentPage = 1; // Reset to first page when filtering
                updateListView();
            }

            // Hide loading state
            if (tableLoading && listViewBody) {
                tableLoading.style.display = 'none';
                listViewBody.style.display = '';
            }
        }

        // Initialize event listeners
        function initializeEventListeners() {
            // View toggle buttons
            const viewButtons = document.querySelectorAll('[data-view]');
            if (viewButtons.length > 0) {
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const view = this.dataset.view;
                        viewButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');
                    
                        if (view === 'calendar') {
                            document.getElementById('calendarView').style.display = 'block';
                            document.getElementById('listView').style.display = 'none';
                } else {
                            document.getElementById('calendarView').style.display = 'none';
                            document.getElementById('listView').style.display = 'block';
                            if (filteredEvents.length === 0) {
                                initializeListView();
                            }
                        }
                    });
                });
            }

            // Filter inputs
            const filterInputs = {
                'userFilter': 'change',
                'statusFilter': 'change',
                'dateRangeFilter': 'change',
                'startDate': 'change',
                'endDate': 'change',
                'appointmentIdFilter': 'input',
                'searchInput': 'input'
            };

            Object.entries(filterInputs).forEach(([id, eventType]) => {
                const element = document.getElementById(id);
                if (element) {
                    if (id === 'searchInput') {
                        element.addEventListener(eventType, debounce(applyFilters, 300));
                } else {
                        element.addEventListener(eventType, applyFilters);
                    }
                }
            });

            // Clear filters button
            const clearFiltersBtn = document.getElementById('clearFilters');
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function() {
                    const filters = {
                        'userFilter': '',
                        'statusFilter': '',
                        'dateRangeFilter': 'all',
                        'searchInput': '',
                        'appointmentIdFilter': '',
                        'startDate': '',
                        'endDate': ''
                    };

                    Object.entries(filters).forEach(([id, value]) => {
                        const element = document.getElementById(id);
                        if (element) {
                            element.value = value;
                        }
                    });

                    const customRange = document.getElementById('customDateRange');
                    if (customRange) {
                        customRange.style.display = 'none';
                    }

                    applyFilters();
                });
            }

            // Date range filter
            const dateRangeFilter = document.getElementById('dateRangeFilter');
            if (dateRangeFilter) {
                dateRangeFilter.addEventListener('change', function() {
                    const customRange = document.getElementById('customDateRange');
                    if (customRange) {
                        customRange.style.display = this.value === 'custom' ? 'flex' : 'none';
                    }
                    if (this.value !== 'custom') {
                        applyFilters();
                    }
                });
            }

            // Pagination controls
            const pageSize = document.getElementById('pageSize');
            if (pageSize) {
                pageSize.addEventListener('change', function() {
                    pageSize = parseInt(this.value);
                    currentPage = 1;
                    updateListView();
                });
            }

            const prevPage = document.getElementById('prevPage');
            if (prevPage) {
                prevPage.addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        updateListView();
                    }
                });
            }

            const nextPage = document.getElementById('nextPage');
            if (nextPage) {
                nextPage.addEventListener('click', function() {
                    const totalPages = Math.ceil(filteredEvents.length / pageSize);
                    if (currentPage < totalPages) {
                        currentPage++;
                        updateListView();
                    }
                });
            }
        }

        // Initialize everything when the DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            
            // Initialize calendar if it exists
            const calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                initializeCalendar(calendarEl);
            }
        });

        // Initialize calendar
        function initializeCalendar(calendarEl) {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: <?php 
                    $calendar_events = array_map(function($event) {
                        $color = '';
                        switch($event['status']) {
                            case 'completed': $color = '#28a745'; break;
                            case 'confirmed': $color = '#17a2b8'; break;
                            case 'pending': $color = '#ffc107'; break;
                            case 'cancelled': $color = '#dc3545'; break;
                        }
                        
                        return [
                            'id' => $event['id'],
                            'title' => $event['title'],
                            'start' => $event['start_time'],
                            'end' => $event['end_time'],
                            'backgroundColor' => $color,
                            'borderColor' => $color,
                            'extendedProps' => [
                                'appointment_id' => $event['appointment_id'],
                                'user_id' => $event['user_id'],
                                'status' => $event['status'],
                                'company' => $event['companyName'],
                                'contact' => $event['contactName'],
                                'phone' => $event['phone'],
                                'description' => $event['description'],
                                'vehid' => $event['vehid'],
                                'vehYear' => $event['vehYear'],
                                'vehMake' => $event['vehMake'],
                                'plateNo' => $event['plateNo'],
                                'vin' => $event['vin'],
                                'smoke_test_status' => $event['smoke_test_status'],
                                'clean_truck_check_status' => $event['clean_truck_check_status'],
                                'smoke_test_result' => $event['smoke_test_result'],
                                'next_due_date' => $event['next_due_date'],
                                'clean_truck_check_next_date' => $event['clean_truck_check_next_date'],
                                'error_code' => $event['error_code'],
                                'warm_up' => $event['warm_up'],
                                'smoke_test_notes' => $event['smoke_test_notes'],
                                'attachment_path' => $event['attachment_path'],
                                'consultant' => $event['consultant_first_name'] . ' ' . $event['consultant_last_name'],
                                'appointment_status' => $event['appointment_status'],
                                'total_price' => $event['total_price'],
                                'discount_type' => $event['discount_type'],
                                'discount_amount' => $event['discount_amount'],
                                'discount_percentage' => $event['discount_percentage']
                            ]
                        ];
                    }, $events);
                    echo json_encode($calendar_events);
                ?>,
                eventClick: function(info) {
                    showEventDetails(info.event);
                }
            });
            calendar.render();

            // Store calendar instance for later use
            calendarEl.__fullCalendar = calendar;
        }

        // Initialize list view
        let currentPage = 1;
        let pageSize = 10;
        let filteredEvents = [];
        let searchTimeout;

        function initializeListView() {
            const events = <?php echo json_encode($events); ?>;
            filteredEvents = [...events];
            updateListView();
        }

        function updateListView() {
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            const pageEvents = filteredEvents.slice(start, end);
            
            const tbody = document.getElementById('listViewBody');
            tbody.innerHTML = '';
            
            pageEvents.forEach(event => {
                const tr = document.createElement('tr');
                tr.dataset.user = event.user_id;
                tr.dataset.status = event.status;
                tr.dataset.date = event.start_time;
                tr.dataset.appointmentId = event.appointment_id;
                tr.dataset.company = event.companyName;
                tr.dataset.contact = event.contactName;
                
                tr.innerHTML = `
                    <td>${escapeHtml(event.title)}</td>
                    <td>${formatDateTime(event.start_time, event.end_time)}</td>
                    <td>${escapeHtml(event.companyName || 'N/A')}</td>
                    <td>${escapeHtml(event.contactName || 'N/A')}</td>
                    <td>${escapeHtml(event.description ? event.description.replace('Test Address: ', '') : 'N/A')}</td>
                    <td>
                        <span class="badge bg-${getStatusColor(event.status)}">
                            ${capitalizeFirst(event.status)}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-event" data-event-id="${event.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary edit-event" data-event-id="${event.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-event" data-event-id="${event.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Update pagination info
            const totalPages = Math.ceil(filteredEvents.length / pageSize);
            document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
            document.getElementById('totalInfo').textContent = `Showing ${start + 1}-${Math.min(end, filteredEvents.length)} of ${filteredEvents.length} events`;
            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages;

            // Reattach event listeners
            attachEventListeners();
        }

        // Attach event listeners to list view buttons
        function attachEventListeners() {
            document.querySelectorAll('.view-event').forEach(button => {
                button.addEventListener('click', function() {
                    const eventId = this.dataset.eventId;
                    const event = calendar.getEventById(eventId);
                    if (event) {
                        showEventDetails(event);
                    }
                });
            });

            document.querySelectorAll('.edit-event').forEach(button => {
                button.addEventListener('click', function() {
                    const eventId = this.dataset.eventId;
                    const event = calendar.getEventById(eventId);
                    if (event && event.extendedProps.appointment_id) {
                        window.location.href = `edit_appointment.php?id=${event.extendedProps.appointment_id}`;
                    }
                });
            });

            document.querySelectorAll('.delete-event').forEach(button => {
                button.addEventListener('click', function() {
                    const eventId = this.dataset.eventId;
                    if (!eventId) {
                        alert('Error: No event ID found');
                        return;
                    }
                    showDeleteConfirmation(eventId);
                });
            });
        }

        // Update calendar view
        function updateCalendarView() {
            calendar.getEvents().forEach(event => {
                const matchesUser = !document.getElementById('userFilter').value || event.extendedProps.user_id == document.getElementById('userFilter').value;
                const matchesStatus = !document.getElementById('statusFilter').value || event.extendedProps.status === document.getElementById('statusFilter').value;
                const matchesDate = filterByDate(event.start, document.getElementById('dateRangeFilter').value, 
                    document.getElementById('startDate').value, document.getElementById('endDate').value);
                const matchesAppointmentId = !document.getElementById('appointmentIdFilter').value || 
                    (event.extendedProps.appointment_id && event.extendedProps.appointment_id.toString().includes(document.getElementById('appointmentIdFilter').value));
                const matchesSearch = !document.getElementById('searchInput').value.toLowerCase() || 
                    event.title.toLowerCase().includes(document.getElementById('searchInput').value.toLowerCase()) ||
                    (event.extendedProps.company && event.extendedProps.company.toLowerCase().includes(document.getElementById('searchInput').value.toLowerCase())) ||
                    (event.extendedProps.contact && event.extendedProps.contact.toLowerCase().includes(document.getElementById('searchInput').value.toLowerCase()));

                event.setProp('display', matchesUser && matchesStatus && matchesDate && matchesAppointmentId && matchesSearch ? 'auto' : 'none');
            });
        }

        // Initialize list view when switching to it
        document.querySelector('[data-view="list"]').addEventListener('click', function() {
            if (filteredEvents.length === 0) {
                initializeListView();
            }
        });
    </script>
</body>
</html> 