<?php
session_start();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Sky Smoke Check LLC</title>
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
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        .fc-event {
            cursor: pointer;
            font-size: 0.9em;
            padding: 2px 4px;
        }
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 10px;
        }
        .fc .fc-toolbar-title {
            font-size: 1.2em;
        }
        .fc .fc-button {
            padding: 0.3em 0.6em;
            font-size: 0.9em;
        }
        .fc .fc-daygrid-day {
            min-height: 50px;
        }
        .fc .fc-daygrid-day-number {
            font-size: 0.9em;
            padding: 4px;
        }
        .fc .fc-daygrid-event {
            margin-top: 2px;
        }
        .modal-content {
            border-radius: 8px;
        }
        .vehicle-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .vehicle-card h6 {
            margin-bottom: 10px;
        }
        .vehicle-card p {
            margin-bottom: 5px;
        }
        .vehicle-card .btn {
            margin-top: 10px;
        }
        .section {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .quotation-section {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        .file-attachment {
            display: flex;
            align-items: center;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .file-attachment i {
            font-size: 1.2rem;
        }
        .file-attachment a {
            color: #0d6efd;
            text-decoration: none;
        }
        .file-attachment a:hover {
            text-decoration: underline;
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
        .status-legend {
            max-width: 800px;
            margin: 0 auto;
        }
        .status-legend .card {
            border: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-legend .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        /* Calendar Event Status Colors */
        .fc-event {
            border: none;
            padding: 2px 4px;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: rgb(201, 214, 226);
            color: black;
        }

        /* Completed */
        .fc-event[data-status="completed"] {
            background-color: #28a745;
            color: white;
        }

        /* Cancelled */
        .fc-event[data-status="cancelled"] {
            background-color: #dc3545;
            color: white;
            text-decoration: line-through;
            opacity: 0.8;
        }

        /* Status buttons for non-completed/cancelled events */
        .fc-event[data-status="pending"] .status-badge,
        .fc-event[data-status="confirmed"] .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-left: 5px;
        }

        .fc-event[data-status="pending"] .status-badge {
            background-color: #ffc107;
            color: #000;
        }

        .fc-event[data-status="confirmed"] .status-badge {
            background-color: #17a2b8;
            color: white;
        }

        /* Hover effects */
        .fc-event:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Event dot indicator */
        .fc-event-dot {
            display: none;
        }

        /* Event title */
        .fc-event-title {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Event time */
        .fc-event-time {
            font-size: 0.8em;
            opacity: 0.9;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            display: inline-flex;
            align-items: center;
        }
        .status-badge:before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-default {
            background-color: #3788d8;
            color: white;
        }
        .status-default:before {
            background-color: #3788d8;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-pending:before {
            background-color: #ffc107;
        }
        .status-confirmed {
            background-color: #28a745;
            color: white;
        }
        .status-confirmed:before {
            background-color: #28a745;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        .status-cancelled:before {
            background-color: #dc3545;
        }
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .fc .fc-day-sat, .fc .fc-day-sun {
            background-color: #f8f9fa;
        }
        .fc .fc-day-sat:hover, .fc .fc-day-sun:hover {
            background-color: #e9ecef;
        }
        .fc .fc-daygrid-day.fc-day-today {
            background-color: #e3f2fd;
        }
        .fc .fc-button-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .fc .fc-button-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .fc .fc-event {
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 0.9em;
        }
        .fc .fc-event:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .fc .fc-toolbar-title {
            font-size: 1.5em;
            font-weight: 600;
        }
        .fc .fc-col-header-cell {
            padding: 8px 0;
            background-color: #f8f9fa;
        }
        .fc .fc-daygrid-day-number {
            padding: 4px;
            font-weight: 500;
        }
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
                        <a href="create_quotation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create_quotation.php' ? 'active' : ''; ?>" style="background-color: #17a2b8; color: white;">
                            <i class="fas fa-file-invoice-dollar"></i> Create Quotation
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

        <div class="main-content" id="mainContent">
            <div class="container-fluid">
                <!-- Status Legend -->
                <div class="status-legend mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Appointment Status</h5>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="status-item">
                                    <span class="status-dot" style="background-color: #3788d8;"></span>
                                    <span class="status-text">Default</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-dot" style="background-color: #ffc107;"></span>
                                    <span class="status-text">Pending</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-dot" style="background-color: #28a745;"></span>
                                    <span class="status-text">Confirmed</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-dot" style="background-color: #dc3545;"></span>
                                    <span class="status-text">Cancelled</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>

            <!-- Event Modal -->
            <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="eventModalLabel">Add New Event</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="eventForm">
                                <div class="mb-3">
                                    <label for="eventTitle" class="form-label">Event Title</label>
                                    <input type="text" class="form-control" id="eventTitle" required>
                                </div>
                                <div class="mb-3">
                                    <label for="eventStart" class="form-label">Start Time</label>
                                    <input type="datetime-local" class="form-control" id="eventStart" required>
                                </div>
                                <div class="mb-3">
                                    <label for="eventEnd" class="form-label">End Time</label>
                                    <input type="datetime-local" class="form-control" id="eventEnd" required>
                                </div>
                                <div class="mb-3">
                                    <label for="eventDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="eventDescription" rows="3"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="saveEvent">Save Event</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Side Peek for Appointment Details -->
            <div class="side-peek" id="appointmentSidePeek">
                <div class="side-peek-header">
                    <h5 class="side-peek-title">Appointment Details</h5>
                    <div class="side-peek-actions">
                        <button class="btn btn-sm btn-outline-primary" id="editAppointment">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" id="deleteAppointment">
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
                    <div class="appointment-details">
                        <!-- Appointment Number -->
                        <div class="section mb-4">
                            <h6 class="section-title">Appointment Number</h6>
                            <p id="appointmentNumber" class="mb-0"></p>
                        </div>

                        <!-- Appointment Title -->
                        <div class="section mb-4">
                            <h4 id="appointmentTitle" class="text-primary"></h4>
                        </div>

                        <!-- Date & Time Section -->
                        <div class="section mb-4">
                            <h6 class="section-title">Date & Time</h6>
                            <p id="appointmentDateTime" class="mb-0"></p>
                        </div>

                        <!-- Company Information Section -->
                        <div class="section mb-4">
                            <h6 class="section-title">Company Information</h6>
                            <p id="companyName" class="mb-0"></p>
                        </div>

                        <!-- Test Location Section -->
                        <div class="section mb-4">
                            <h6 class="section-title">Test Location</h6>
                            <p id="testAddress" class="mb-0"></p>
                        </div>

                        <!-- Contact Details Section -->
                        <div class="section mb-4">
                            <h6 class="section-title">Contact Details</h6>
                            <p id="contactName" class="mb-0"></p>
                            <p id="contactEmail" class="mb-0"></p>
                            <p id="contactPhone" class="mb-0"></p>
                        </div>

                        <!-- Vehicle Information Section -->
                        <div class="section mb-4">
                            <h6 class="section-title">Vehicle Information</h6>
                            <div id="vehicleGrid" class="row g-3">
                                <!-- Vehicle cards will be inserted here -->
                            </div>
                        </div>

                        <!-- Quotation Section -->
                        <div class="section mb-4">
                            <h6 class="section-title">Quotation</h6>
                            <div id="quotationContent" class="quotation-section">
                                <div class="file-attachment">
                                    <i class="fas fa-file-pdf text-danger"></i>
                                    <a href="#" id="quotationLink" class="ms-2" target="_blank">
                                        <span id="quotationFileName"></span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Consultant Section -->
                        <div class="section">
                            <h6 class="section-title">Assigned Consultant</h6>
                            <p id="consultantName" class="mb-0"></p>
                        </div>

                        <!-- Action Buttons Section -->
                        <div class="section mt-4">
                            <div class="d-grid gap-2">
                                <button class="btn btn-info" id="openUpdateFile" style="display: none;">
                                    <i class="fas fa-file-alt"></i> Open File
                                </button>
                                <button class="btn btn-primary" id="markAsCompleted">
                                    <i class="fas fa-check-circle"></i> Mark as Completed
                                </button>
                            </div>
                        </div>
                    </div>
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

            // Initialize calendar
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: 'get_events.php',
                editable: true,
                selectable: true,
                eventDidMount: function(info) {
                    // Add status as data attribute for styling
                    if (info.event.extendedProps.status) {
                        info.el.setAttribute('data-status', info.event.extendedProps.status);
                        
                        // Add status badge for non-completed/cancelled events
                        if (info.event.extendedProps.status !== 'completed' && 
                            info.event.extendedProps.status !== 'cancelled') {
                            const statusBadge = document.createElement('span');
                            statusBadge.className = 'status-badge';
                            statusBadge.textContent = info.event.extendedProps.status.charAt(0).toUpperCase() + 
                                                    info.event.extendedProps.status.slice(1);
                            info.el.querySelector('.fc-event-title').appendChild(statusBadge);
                        }
                    }
                },
                select: function(info) {
                    document.getElementById('eventForm').reset();
                    document.getElementById('eventStart').value = info.startStr.substring(0, 16);
                    document.getElementById('eventEnd').value = info.endStr.substring(0, 16);
                    var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
                    eventModal.show();
                },
                eventClick: function(info) {
                    if (info.event.extendedProps.appointment_id) {
                        console.log('Fetching details for appointment:', info.event.extendedProps.appointment_id);
                        fetch(`get_appointment_details.php?appointment_id=${info.event.extendedProps.appointment_id}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.error) {
                                    console.error('Error from server:', data.error);
                                    alert('Error: ' + data.error);
                                    return;
                                }

                                const appointment = data.appointment;
                                const vehicles = data.vehicles;
                                const consultant = data.consultant;

                                // Debug logging for raw data
                                console.log('Raw Appointment Data:', appointment);
                                console.log('Raw Vehicles Data:', vehicles);
                                console.log('Raw Consultant Data:', consultant);

                                if (!appointment) {
                                    console.error('No appointment data received');
                                    alert('Error: No appointment data found');
                                    return;
                                }

                                // Update modal content
                                document.getElementById('appointmentNumber').textContent = `#${appointment.id}`;
                                document.getElementById('appointmentTitle').textContent = info.event.title;
                                document.getElementById('appointmentDateTime').textContent = 
                                    `${appointment.bookingDate} at ${appointment.bookingTime}`;
                                document.getElementById('companyName').textContent = appointment.companyName;
                                document.getElementById('testAddress').textContent = appointment.test_address;
                                document.getElementById('contactName').textContent = appointment.Name;
                                document.getElementById('contactEmail').textContent = appointment.email;
                                document.getElementById('contactPhone').textContent = appointment.phone;
                                
                                if (consultant) {
                                    document.getElementById('consultantName').textContent = 
                                        `${consultant.firstName} ${consultant.lastName}`;
                                } else {
                                    document.getElementById('consultantName').textContent = 'Not assigned';
                                }

                                // Update vehicle grid
                                const vehicleGrid = document.getElementById('vehicleGrid');
                                vehicleGrid.innerHTML = '';
                                
                                if (vehicles && vehicles.length > 0) {
                                    vehicles.forEach(vehicle => {
                                        const vehicleCard = document.createElement('div');
                                        vehicleCard.className = 'col-12';
                                        vehicleCard.innerHTML = `
                                            <div class="vehicle-card">
                                                <h6>Vehicle Details</h6>
                                                <p><strong>Make:</strong> ${vehicle.vehMake || 'N/A'}</p>
                                                <p><strong>Year:</strong> ${vehicle.vehYear || 'N/A'}</p>
                                                <p><strong>VIN:</strong> ${vehicle.vin || 'N/A'}</p>
                                                <p><strong>Plate Number:</strong> ${vehicle.plateNo || 'N/A'}</p>
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <span class="badge ${vehicle.smoke_test_status === 'passed' ? 'bg-success' : 
                                                        vehicle.smoke_test_status === 'failed' ? 'bg-danger' : 'bg-warning'}">
                                                        Status: ${vehicle.smoke_test_status || 'Pending'}
                                                    </span>
                                                    ${vehicle.smoke_test_status === 'pending' ? 
                                                        `<button class="btn btn-sm btn-primary update-smoke-test" 
                                                            data-vehicle-id="${vehicle.id}"
                                                            data-appointment-id="${appointment.id}">
                                                            <i class="fas fa-smog"></i> Update Smoke Test
                                                        </button>` :
                                                        `<button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="fas fa-smog"></i> Test Completed
                                                        </button>`
                                                    }
                                                </div>
                                            </div>
                                        `;
                                        vehicleGrid.appendChild(vehicleCard);
                                    });
                                } else {
                                    vehicleGrid.innerHTML = '<p>No vehicles found for this appointment.</p>';
                                }

                                // Update quotation link
                                const appointmentId = info.event.extendedProps.appointment_id;
                                const quotationFileName = `quotation_${appointmentId}.pdf`;
                                const quotationLink = document.getElementById('quotationLink');
                                const quotationFileNameSpan = document.getElementById('quotationFileName');
                                
                                // Check if file exists
                                fetch(`check_quotation.php?filename=${quotationFileName}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.exists) {
                                            quotationLink.href = `download_quotation.php?filename=${quotationFileName}`;
                                            quotationFileNameSpan.textContent = `Quotation_${appointmentId}.pdf`;
                                            quotationLink.style.display = 'inline';
                                        } else {
                                            quotationFileNameSpan.textContent = 'No quotation available';
                                            quotationLink.style.display = 'none';
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error checking quotation:', error);
                                        quotationFileNameSpan.textContent = 'Error checking quotation';
                                        quotationLink.style.display = 'none';
                                    });

                                // Show side peek and overlay
                                const sidePeek = document.getElementById('appointmentSidePeek');
                                sidePeek.classList.add('active');
                                let overlay = createOverlay();
                                overlay.classList.add('active');

                                // Store appointment ID for delete operation
                                sidePeek.dataset.appointmentId = appointmentId;

                                // Show mark as completed button only if:
                                // 1. Calendar event status is not completed or cancelled (including null/empty)
                                // 2. Appointment status is not completed or cancelled (including null/empty)
                                // 3. All vehicles have a non-pending smoke test status
                                const calendarEventStatus = info.event.extendedProps.status || '';
                                const isEligibleCalendarStatus = !['completed', 'cancelled'].includes(calendarEventStatus.toLowerCase());
                                const isEligibleAppointmentStatus = !['completed', 'cancelled'].includes((appointment.status || '').toLowerCase());
                                const allVehiclesHaveResult = vehicles.length > 0 && vehicles.every(vehicle => 
                                    vehicle.smoke_test_status && vehicle.smoke_test_status !== 'pending'
                                );
                                
                                // Debug logging
                                console.log('Debug - Button Visibility Conditions:');
                                console.log('Calendar Event Status:', calendarEventStatus);
                                console.log('Is Eligible Calendar Status:', isEligibleCalendarStatus);
                                console.log('Appointment Status:', appointment.status);
                                console.log('Is Eligible Appointment Status:', isEligibleAppointmentStatus);
                                console.log('Vehicles:', vehicles);
                                console.log('All Vehicles Have Result:', allVehiclesHaveResult);

                                // Update the open/update file button based on smoke test status
                                const openUpdateButton = document.getElementById('openUpdateFile');
                                const markCompletedButton = document.getElementById('markAsCompleted');
                                
                                // Check if any vehicle has a pending smoke test
                                const hasPendingTest = vehicles.some(vehicle => vehicle.smoke_test_status === 'pending');
                                
                                if (hasPendingTest) {
                                    openUpdateButton.style.display = 'block';
                                    openUpdateButton.innerHTML = '<i class="fas fa-file-alt"></i> Open File';
                                    openUpdateButton.onclick = function() {
                                        window.location.href = `view_appointment.php?id=${appointment.id}`;
                                    };
                                } else if (vehicles.some(vehicle => ['passed', 'failed', 'warmup'].includes(vehicle.smoke_test_status))) {
                                    openUpdateButton.style.display = 'block';
                                    openUpdateButton.innerHTML = '<i class="fas fa-file-alt"></i> Update File';
                                    openUpdateButton.onclick = function() {
                                        window.location.href = `view_appointment.php?id=${appointment.id}`;
                                    };
                                } else {
                                    openUpdateButton.style.display = 'none';
                                }

                                // Hide mark as completed button if either appointment or calendar event is completed
                                const isAppointmentCompleted = appointment.status && appointment.status.toLowerCase() === 'completed';
                                const isCalendarEventCompleted = calendarEventStatus.toLowerCase() === 'completed';
                                const allVehiclesHaveResults = vehicles.length > 0 && vehicles.every(vehicle => vehicle.result !== null);

                                if (isAppointmentCompleted && isCalendarEventCompleted) {
                                    markCompletedButton.style.display = 'none';
                                } else if (!allVehiclesHaveResults) {
                                    markCompletedButton.style.display = 'none';
                                } else {
                                    markCompletedButton.style.display = 'block';
                                }

                                // Debug logging
                                console.log('Button Visibility Check:');
                                console.log('Calendar Event Status:', calendarEventStatus);
                                console.log('Is Calendar Event Completed:', isCalendarEventCompleted);
                                console.log('Appointment Status:', appointment.status);
                                console.log('Is Appointment Completed:', isAppointmentCompleted);
                                console.log('Vehicles:', vehicles);
                                console.log('All Vehicles Have Results:', allVehiclesHaveResults);
                                console.log('Final Button Display:', markCompletedButton.style.display);

                                // Add event listener for mark as completed button
                                if (markCompletedButton) {
                                    // Remove any existing event listeners
                                    const newButton = markCompletedButton.cloneNode(true);
                                    markCompletedButton.parentNode.replaceChild(newButton, markCompletedButton);
                                    
                                    newButton.addEventListener('click', function() {
                                        const appointmentId = document.getElementById('appointmentSidePeek').dataset.appointmentId;
                                        if (!appointmentId) {
                                            alert('Error: No appointment ID found');
                                            return;
                                        }

                                        // Create confirmation modal
                                        const modal = document.createElement('div');
                                        modal.className = 'modal fade';
                                        modal.id = 'completeConfirmationModal';
                                        modal.innerHTML = `
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Mark Appointment as Completed</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to mark this appointment as completed?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="button" class="btn btn-primary" id="confirmComplete">Yes, Mark as Completed</button>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                        document.body.appendChild(modal);

                                        const modalInstance = new bootstrap.Modal(modal);
                                        modalInstance.show();

                                        document.getElementById('confirmComplete').addEventListener('click', function() {
                                            const confirmButton = this;
                                            confirmButton.disabled = true;
                                            confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait...';

                                            fetch('update_appointment_status.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                },
                                                body: JSON.stringify({
                                                    appointment_id: appointmentId,
                                                    status: 'completed'
                                                })
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    calendar.refetchEvents();
                                                    sidePeek.classList.remove('active', 'fullscreen');
                                                    removeOverlay();
                                                    
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
                                                                        <p>Appointment has been marked as completed successfully.</p>
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

                                                    successModal.addEventListener('hidden.bs.modal', function () {
                                                        successModal.remove();
                                                    });
                                                } else {
                                                    throw new Error(data.message || 'Unknown error occurred');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error:', error);
                                                alert('Error marking appointment as completed: ' + error.message);
                                            })
                                            .finally(() => {
                                                modalInstance.hide();
                                                modal.remove();
                                            });
                                        });

                                        modal.addEventListener('hidden.bs.modal', function () {
                                            modal.remove();
                                        });
                                    });
                                }
                                
                                // Debug logging for button states
                                console.log('Button States:');
                                console.log('Open/Update Button Display:', openUpdateButton.style.display);
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error fetching appointment details: ' + error.message);
                            });
                    }
                }
            });
            calendar.render();

            // Handle saving events
            document.getElementById('saveEvent').addEventListener('click', function() {
                var eventData = {
                    title: document.getElementById('eventTitle').value,
                    start: document.getElementById('eventStart').value,
                    end: document.getElementById('eventEnd').value,
                    description: document.getElementById('eventDescription').value
                };

                fetch('save_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(eventData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        calendar.refetchEvents();
                        bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                    } else {
                        alert('Error saving event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving event');
                });
            });

            const sidePeek = document.getElementById('appointmentSidePeek');
            const toggleFullScreenBtn = document.getElementById('toggleFullScreen');
            const closeSidePeekBtn = document.getElementById('closeSidePeek');
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

            // Toggle fullscreen
            toggleFullScreenBtn.addEventListener('click', function() {
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

            // Close side peek
            closeSidePeekBtn.addEventListener('click', function() {
                sidePeek.classList.remove('active', 'fullscreen');
                removeOverlay();
                toggleFullScreenBtn.querySelector('i').classList.remove('fa-compress');
                toggleFullScreenBtn.querySelector('i').classList.add('fa-expand');
            });

            // Close on overlay click
            document.addEventListener('click', function(e) {
                if (overlay && e.target === overlay) {
                    sidePeek.classList.remove('active', 'fullscreen');
                    removeOverlay();
                    toggleFullScreenBtn.querySelector('i').classList.remove('fa-compress');
                    toggleFullScreenBtn.querySelector('i').classList.add('fa-expand');
                }
            });

            // Handle edit appointment
            document.getElementById('editAppointment').addEventListener('click', function() {
                const appointmentId = document.getElementById('appointmentSidePeek').dataset.appointmentId;
                if (!appointmentId) {
                    alert('Error: No appointment ID found');
                    return;
                }
                window.location.href = `edit_appointment.php?id=${appointmentId}`;
            });

            // Handle delete appointment
            document.getElementById('deleteAppointment').addEventListener('click', function() {
                const appointmentId = document.getElementById('appointmentSidePeek').dataset.appointmentId;
                if (!appointmentId) {
                    alert('Error: No appointment ID found');
                    return;
                }

                // Create confirmation modal
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.id = 'deleteConfirmationModal';
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Cancel Appointment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel this appointment?</p>
                                <div class="mb-3">
                                    <label for="cancellationReason" class="form-label">Reason for Cancellation (optional):</label>
                                    <textarea class="form-control" id="cancellationReason" rows="3"></textarea>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sendEmailNotification">
                                    <label class="form-check-label" for="sendEmailNotification">
                                        Send notification email to client
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Appointment</button>
                                <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel Appointment</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);

                // Show modal
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();

                // Handle confirmation
                document.getElementById('confirmCancel').addEventListener('click', function() {
                    const sendEmail = document.getElementById('sendEmailNotification').checked;
                    const reason = document.getElementById('cancellationReason').value;
                    const confirmButton = this;

                    // Disable button and show loading state
                    confirmButton.disabled = true;
                    confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait...';

                    fetch('delete_appointment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ 
                            id: appointmentId,
                            send_email: sendEmail,
                            reason: reason
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            calendar.refetchEvents();
                            sidePeek.classList.remove('active', 'fullscreen');
                            removeOverlay();
                            
                            // Show success message
                            const successMessage = sendEmail ? 
                                'Appointment has been cancelled and notification email has been sent to the client.' : 
                                'Appointment has been cancelled successfully.';
                            
                            // Create and show success modal
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
                                                <p>${successMessage}</p>
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
                            });
                        } else {
                            alert('Error cancelling appointment: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error cancelling appointment');
                    })
                    .finally(() => {
                        modalInstance.hide();
                        modal.remove();
                        // Reset button state
                        confirmButton.disabled = false;
                        confirmButton.textContent = 'Yes, Cancel Appointment';
                    });
                });

                // Remove modal when closed
                modal.addEventListener('hidden.bs.modal', function () {
                    modal.remove();
                });
            });

            // Add smoke test update handler
            document.addEventListener('click', function(e) {
                if (e.target.closest('.update-smoke-test')) {
                    const button = e.target.closest('.update-smoke-test');
                    const vehicleId = button.dataset.vehicleId;
                    const appointmentId = button.dataset.appointmentId;
                    
                    // Create modal for smoke test update
                    const modal = document.createElement('div');
                    modal.className = 'modal fade';
                    modal.id = 'smokeTestModal';
                    modal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Update Smoke Test Status</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="smokeTestForm">
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
                                                <input class="form-check-input" type="checkbox" id="sendReminder" name="sendReminder">
                                                <label class="form-check-label" for="sendReminder">
                                                    Send automatic reminder before next smoke test
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Failed Options -->
                                        <div id="failedOptions" class="mb-3" style="display: none;">
                                            <div class="mb-3">
                                                <label for="errorCode" class="form-label">Error Code</label>
                                                <input type="text" class="form-control" id="errorCode" name="errorCode" placeholder="Enter error code">
                                            </div>
                                        </div>

                                        <!-- Warm Up Options -->
                                        <div id="warmupOptions" class="mb-3" style="display: none;">
                                            <div class="mb-3">
                                                <label for="warmupCycles" class="form-label">Number of Warm Up Cycles Required</label>
                                                <select class="form-select" id="warmupCycles" name="warmupCycles">
                                                    <option value="">Select number of cycles</option>
                                                    ${Array.from({length: 5}, (_, i) => `<option value="${i + 1}">${i + 1}</option>`).join('')}
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Notes (Optional)</label>
                                            <textarea class="form-control" id="notes" rows="3"></textarea>
                                        </div>

                                        <!-- File Attachment -->
                                        <div class="mb-3">
                                            <label for="testAttachment" class="form-label">Test Result Attachment (Optional)</label>
                                            <input type="file" class="form-control" id="testAttachment" accept=".pdf,.jpg,.jpeg,.png">
                                            <small class="text-muted">Supported formats: PDF, JPG, PNG (Max size: 5MB)</small>
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
                        const status = document.querySelector('input[name="status"]:checked')?.value;
                        const notes = document.getElementById('notes').value;
                        const sendReminder = document.getElementById('sendReminder')?.checked;
                        const errorCode = document.getElementById('errorCode')?.value;
                        const warmupCycles = document.getElementById('warmupCycles')?.value;
                        const attachment = document.getElementById('testAttachment').files[0];
                        
                        if (!status) {
                            alert('Please select a test status');
                            return;
                        }

                        if (status === 'failed' && !errorCode) {
                            alert('Please enter the error code');
                            return;
                        }

                        if (status === 'warmup' && !warmupCycles) {
                            alert('Please select the number of warm up cycles');
                            return;
                        }

                        // Get the submit button
                        const submitButton = this;
                        const originalButtonText = submitButton.innerHTML;
                        
                        // Disable button and show loading state
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait...';

                        // Create FormData for file upload
                        const formData = new FormData();
                        formData.append('vehicle_id', vehicleId);
                        formData.append('appointment_id', appointmentId);
                        formData.append('status', status);
                        formData.append('notes', notes);
                        formData.append('send_reminder', sendReminder);
                        formData.append('error_code', errorCode);
                        formData.append('warmup_cycles', warmupCycles);
                        if (attachment) {
                            formData.append('attachment', attachment);
                        }
                        
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
                                // Update the status badge
                                const badge = button.previousElementSibling;
                                badge.className = `badge ${status === 'passed' ? 'bg-success' : 
                                    status === 'failed' ? 'bg-danger' : 'bg-warning'}`;
                                badge.textContent = `Status: ${status}`;
                                
                                // Replace the update button with a disabled completed button
                                const buttonContainer = button.parentElement;
                                buttonContainer.innerHTML = `
                                    <button class="btn btn-sm btn-secondary" disabled>
                                        <i class="fas fa-smog"></i> Test Completed
                                    </button>
                                `;
                                
                                // Close modal
                                modalInstance.hide();
                                modal.remove();
                                
                                // Show success message with Bootstrap modal
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
                                });
                            } else {
                                throw new Error(data.message || 'Unknown error occurred');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            // Show error message with Bootstrap modal
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
                                                <p>Error updating smoke test status: ${error.message}</p>
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
            });

            // Add filter functionality
            const statusFilter = document.getElementById('statusFilter');
            const applyFilter = document.getElementById('applyFilter');
            const resetFilter = document.getElementById('resetFilter');
            let calendarEvents = []; // Store all events

            // Store all events when calendar is initialized
            calendar.on('eventSources', function() {
                calendarEvents = calendar.getEvents();
            });

            // Apply filter
            applyFilter.addEventListener('click', function() {
                const selectedStatus = statusFilter.value;
                calendar.removeAllEvents();
                
                if (selectedStatus === 'all') {
                    calendarEvents.forEach(event => calendar.addEvent(event));
                } else {
                    calendarEvents.forEach(event => {
                        if (event.extendedProps.status === selectedStatus) {
                            calendar.addEvent(event);
                        }
                    });
                }
            });

            // Reset filter
            resetFilter.addEventListener('click', function() {
                statusFilter.value = 'all';
                calendar.removeAllEvents();
                calendarEvents.forEach(event => calendar.addEvent(event));
            });

            // Modify event rendering to include status badge
            calendar.setOption('eventDidMount', function(info) {
                const status = info.event.extendedProps.status;
                const statusBadge = document.createElement('span');
                statusBadge.className = `status-badge status-${status}`;
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                
                const eventContent = info.el.querySelector('.fc-event-title');
                if (eventContent) {
                    eventContent.appendChild(statusBadge);
                }
            });

            // Add weekend highlighting
            calendar.setOption('dayCellClassNames', function(arg) {
                if (arg.date.getDay() === 0 || arg.date.getDay() === 6) {
                    return ['weekend-day'];
                }
                return [];
            });
        });
    </script>
</body>
</html> 