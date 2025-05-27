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

// Get search and sort parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

// Cache configuration
$cache_dir = '../cache/';
$cache_file = $cache_dir . 'clients_data_' . md5($search . $sort . $order) . '.cache';
$cache_time = 300; // 5 minutes cache time

// Create cache directory if it doesn't exist
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

// Function to get cached data
function getCachedData($cache_file, $cache_time) {
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        return unserialize(file_get_contents($cache_file));
    }
    return false;
}

// Function to save data to cache
function saveToCache($cache_file, $data) {
    file_put_contents($cache_file, serialize($data));
}

// Try to get data from cache first
$clients = getCachedData($cache_file, $cache_time);

// If no cache exists or it's expired, query the database
if ($clients === false) {
    // Build the query for clients from both sources
    $query = "WITH client_data AS (
        -- Clients from appointments table (optimized)
        SELECT 
            a.companyName COLLATE utf8mb4_unicode_ci as company_name,
            a.Name COLLATE utf8mb4_unicode_ci as contact_name,
            a.email COLLATE utf8mb4_unicode_ci as contact_email,
            a.phone COLLATE utf8mb4_unicode_ci as contact_phone,
            COUNT(DISTINCT ap.id) as total_appointments,
            MAX(ap.created_at) as service_requested_date,
            'Not Registered' as registration_status,
            NULL as client_status,
            (
                SELECT COUNT(DISTINCT v.id)
                FROM vehicles v
                INNER JOIN appointments ap2 ON v.appointment_id = ap2.id
                WHERE ap2.companyName COLLATE utf8mb4_unicode_ci = a.companyName COLLATE utf8mb4_unicode_ci
            ) as vehicle_count,
            'appointments' as source
        FROM appointments a 
        LEFT JOIN appointments ap ON a.companyName COLLATE utf8mb4_unicode_ci = ap.companyName COLLATE utf8mb4_unicode_ci
        GROUP BY a.companyName, a.Name, a.email, a.phone

        UNION ALL

        -- Clients from clients table (optimized)
        SELECT 
            c.company_name COLLATE utf8mb4_unicode_ci,
            c.contact_person_name COLLATE utf8mb4_unicode_ci as contact_name,
            c.email COLLATE utf8mb4_unicode_ci as contact_email,
            c.phone COLLATE utf8mb4_unicode_ci as contact_phone,
            (
                SELECT COUNT(DISTINCT ap.id)
                FROM appointments ap
                WHERE ap.companyName COLLATE utf8mb4_unicode_ci = c.company_name COLLATE utf8mb4_unicode_ci
            ) as total_appointments,
            (
                SELECT MAX(ap.created_at)
                FROM appointments ap
                WHERE ap.companyName COLLATE utf8mb4_unicode_ci = c.company_name COLLATE utf8mb4_unicode_ci
            ) as service_requested_date,
            'Registered' as registration_status,
            c.status as client_status,
            (
                SELECT COUNT(DISTINCT cv.id)
                FROM clientvehicles cv
                WHERE cv.company_id = c.id
                AND cv.status = 'active'
            ) as vehicle_count,
            'clients' as source
        FROM clients c
    )
    SELECT 
        company_name,
        contact_name,
        contact_email,
        contact_phone,
        SUM(total_appointments) as total_appointments,
        MAX(service_requested_date) as service_requested_date,
        MAX(registration_status) as registration_status,
        MAX(client_status) as client_status,
        SUM(vehicle_count) as vehicle_count
    FROM client_data
    WHERE 1=1";

    // Add search condition if search term exists
    if (!empty($search)) {
        $query .= " AND (company_name LIKE ? OR contact_name LIKE ? OR contact_email LIKE ?)";
        $search_param = "%$search%";
    }

    $query .= " GROUP BY company_name, contact_name, contact_email, contact_phone";

    // Add sorting
    $allowed_sort_columns = ['name', 'appointments', 'service_requested_date'];
    $allowed_orders = ['asc', 'desc'];

    if (in_array($sort, $allowed_sort_columns) && in_array($order, $allowed_orders)) {
        $sort_column = $sort == 'name' ? 'company_name' : 
                      ($sort == 'appointments' ? 'total_appointments' : 'service_requested_date');
        $query .= " ORDER BY $sort_column $order";
    }

    // Add LIMIT for pagination
    $query .= " LIMIT ? OFFSET ?";

    // Get total records count for pagination
    $count_query = "WITH client_data AS (
        SELECT DISTINCT company_name, contact_name, contact_email, contact_phone
        FROM (
            SELECT 
                a.companyName COLLATE utf8mb4_unicode_ci as company_name,
                a.Name COLLATE utf8mb4_unicode_ci as contact_name,
                a.email COLLATE utf8mb4_unicode_ci as contact_email,
                a.phone COLLATE utf8mb4_unicode_ci as contact_phone
            FROM appointments a
            UNION ALL
            SELECT 
                c.company_name COLLATE utf8mb4_unicode_ci,
                c.contact_person_name COLLATE utf8mb4_unicode_ci,
                c.email COLLATE utf8mb4_unicode_ci,
                c.phone COLLATE utf8mb4_unicode_ci
            FROM clients c
        ) combined
        WHERE 1=1";

    if (!empty($search)) {
        $count_query .= " AND (company_name LIKE ? OR contact_name LIKE ? OR contact_email LIKE ?)";
    }

    $count_query .= ") SELECT COUNT(*) as total FROM client_data";

    // Prepare and execute count query
    $count_stmt = $conn->prepare($count_query);
    if (!empty($search)) {
        $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Prepare and execute main query
    $stmt = $conn->prepare($query);
    if (!empty($search)) {
        $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $records_per_page, $offset);
    } else {
        $stmt->bind_param("ii", $records_per_page, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $clients = $result->fetch_all(MYSQLI_ASSOC);

    // Save data to cache
    saveToCache($cache_file, $clients);
}

// Function to clear cache
function clearClientCache() {
    $cache_dir = '../cache/';
    if (file_exists($cache_dir)) {
        $files = glob($cache_dir . 'clients_data_*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// Check if this is a POST request for adding a new client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['companyName'])) {
    // Clear the cache when a new client is added
    clearClientCache();
}

// Function to get vehicle information for a company
function getCompanyVehicles($conn, $companyName) {
    $vehicleQuery = "SELECT DISTINCT 
        cv.fleet_number,
        cv.vin,
        cv.plate_no,
        cv.year,
        cv.make,
        cv.model,
        cv.smog_due_date,
        cv.clean_truck_due_date,
        cv.status,
        COALESCE(
            (SELECT MAX(ce.start_time)
            FROM calendar_events ce
            WHERE ce.vehid = cv.id
            AND ce.status = 'completed'
            LIMIT 1),
            (SELECT MAX(ctc.clean_truck_completed_date)
            FROM clean_truck_checks ctc
            WHERE ctc.vin_number = cv.vin
            AND ctc.clean_truck_status = 'completed'
            LIMIT 1)
        ) as last_appointment
    FROM clientvehicles cv
    WHERE cv.company_id IN (
        SELECT id FROM clients WHERE company_name = ?
    )
    AND cv.status = 'active'
    ORDER BY cv.fleet_number";

    $stmt = $conn->prepare($vehicleQuery);
    $stmt->bind_param("s", $companyName);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Clients - Sky Smoke Check LLC</title>
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
        .client-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            background: white;
        }
        .client-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
        }
        .client-header {
            background-color: #f8f9fa;
            border-radius: 10px 10px 0 0;
            padding: 15px;
        }
        .client-body {
            padding: 20px;
        }
        .client-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        .search-box input {
            padding-left: 40px;
            border-radius: 20px;
            border: 1px solid #dee2e6;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .sort-options {
            margin-bottom: 20px;
        }
        .sort-btn {
            border: none;
            background: none;
            color: #6c757d;
            padding: 5px 10px;
            margin-right: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .sort-btn:hover, .sort-btn.active {
            background-color: #007bff;
            color: white;
        }
        .sort-btn i {
            margin-left: 5px;
        }
        .no-clients {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .no-clients i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        .page-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .clients-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .clients-table thead th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        .clients-table tbody tr {
            transition: all 0.3s ease;
        }
        .clients-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .clients-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .clients-table .company-name {
            font-weight: 500;
            color: #2c3e50;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .clients-table .company-name:hover {
            color: #007bff;
        }
        .clients-table .contact-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .clients-table .contact-info i {
            width: 16px;
            margin-right: 5px;
            color: #007bff;
        }
        .clients-table .appointments-count {
            text-align: center;
            color: #007bff;
            font-weight: 600;
        }
        .clients-table .last-appointment {
            text-align: right;
            color: #6c757d;
        }
        .clients-table .consultant-info {
            color: #2c3e50;
            font-weight: 500;
        }
        .clients-table .consultant-count {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .vehicles-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .vehicles-table th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 2px solid #dee2e6;
        }
        .vehicles-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .vehicles-table tr:hover {
            background-color: #f8f9fa;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .modal-title {
            color: #2c3e50;
            font-weight: 600;
        }
        .no-vehicles {
            text-align: center;
            padding: 20px;
            color: #6c757d;
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
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>All Clients</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newClientModal">
                        <i class="fas fa-plus"></i> New Client
                    </button>
                </div>
                
                <!-- New Client Modal -->
                <div class="modal fade" id="newClientModal" tabindex="-1" aria-labelledby="newClientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="newClientModalLabel">Add New Client</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="newClientForm" action="process_new_client.php" method="POST">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="companyName" class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" id="companyName" name="companyName" value="Golden State Transport Inc." required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contactName" class="form-label">Contact Person Name *</label>
                                            <input type="text" class="form-control" id="contactName" name="contactName" value="Michael Rodriguez" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" value="m.rodriguez@goldenstatetransport.com" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone *</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" value="(415) 555-7890" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="dotNumber" class="form-label">DOT Number *</label>
                                            <input type="text" class="form-control" id="dotNumber" name="dotNumber" value="987654321" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="companyAddress" class="form-label">Company Address *</label>
                                            <textarea class="form-control" id="companyAddress" name="companyAddress" rows="2" required>789 Transport Plaza, Suite 200
San Francisco, CA 94105</textarea>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="clientType" class="form-label">Client Type *</label>
                                            <select class="form-select" id="clientType" name="clientType" required>
                                                <option value="">Select Type</option>
                                                <option value="IRP" selected>IRP</option>
                                                <option value="Local">Local</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="2">Premium transportation company specializing in refrigerated cargo and time-sensitive deliveries. Operating a modern fleet of temperature-controlled vehicles across the West Coast.</textarea>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="numVehicles" class="form-label">Number of Vehicles *</label>
                                            <input type="number" class="form-control" id="numVehicles" name="numVehicles" min="1" value="2" required>
                                        </div>
                                    </div>

                                    <div id="vehiclesContainer">
                                        <!-- Vehicle information fields will be dynamically added here -->
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Create Client</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Sort Section -->
                <div class="content-section">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchInput" 
                                       placeholder="Search clients..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="sort-options">
                                <button class="sort-btn <?php echo $sort == 'name' ? 'active' : ''; ?>" 
                                        data-sort="name" data-order="<?php echo $order == 'asc' ? 'desc' : 'asc'; ?>">
                                    Name
                                    <i class="fas fa-sort<?php echo $sort == 'name' ? '-' . ($order == 'asc' ? 'up' : 'down') : ''; ?>"></i>
                                </button>
                                <button class="sort-btn <?php echo $sort == 'appointments' ? 'active' : ''; ?>" 
                                        data-sort="appointments" data-order="<?php echo $order == 'asc' ? 'desc' : 'asc'; ?>">
                                    Appointments
                                    <i class="fas fa-sort<?php echo $sort == 'appointments' ? '-' . ($order == 'asc' ? 'up' : 'down') : ''; ?>"></i>
                                </button>
                                <button class="sort-btn <?php echo $sort == 'service_requested_date' ? 'active' : ''; ?>" 
                                        data-sort="service_requested_date" data-order="<?php echo $order == 'asc' ? 'desc' : 'asc'; ?>">
                                    Service Requested Date
                                    <i class="fas fa-sort<?php echo $sort == 'service_requested_date' ? '-' . ($order == 'asc' ? 'up' : 'down') : ''; ?>"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Clients Table -->
                    <div class="content-section">
                        <div class="table-responsive">
                            <table class="clients-table">
                                <thead>
                                    <tr>
                                        <th>Company Name</th>
                                        <th>Contact Details</th>
                                        <th class="text-center">Total Appointments</th>
                                        <th class="text-center">Vehicles</th>
                                        <th class="text-end">Service Requested Date</th>
                                        <th class="text-center">Registration Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="no-clients">
                                                    <i class="fas fa-users"></i>
                                                    <h4>No clients found</h4>
                                                    <p>Try adjusting your search criteria</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                            <tr>
                                                <td class="company-name" onclick="window.location.href='company_details.php?company=<?php echo urlencode($client['company_name']); ?>'">
                                                    <?php echo htmlspecialchars($client['company_name']); ?>
                                                </td>
                                                <td class="contact-info">
                                                    <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($client['contact_name']); ?></div>
                                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($client['contact_email']); ?></div>
                                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($client['contact_phone']); ?></div>
                                                </td>
                                                <td class="appointments-count"><?php echo $client['total_appointments']; ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-info" style="cursor: pointer;" onclick="showVehicles('<?php echo htmlspecialchars($client['company_name']); ?>')">
                                                        <i class="fas fa-truck"></i> <?php echo $client['vehicle_count']; ?> Vehicles
                                                    </span>
                                                </td>
                                                <td class="last-appointment">
                                                    <?php echo $client['service_requested_date'] ? date('M d, Y', strtotime($client['service_requested_date'])) : 'N/A'; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($client['registration_status'] === 'Registered'): ?>
                                                        <span class="badge bg-success">Registered</span>
                                                        <?php if ($client['client_status']): ?>
                                                            <br>
                                                            <small class="text-muted">Status: <?php echo ucfirst(htmlspecialchars($client['client_status'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Not Registered</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Client Added Successfully!</h4>
                    <p class="text-muted">The new client has been added to the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
    <script>
        // Check for success message and show modal
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success_message'])): ?>
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            // Sidebar toggle functionality
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

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    const searchValue = searchInput.value;
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('search', searchValue);
                    window.location.href = currentUrl.toString();
                }, 500);
            });

            // Sort functionality
            const sortButtons = document.querySelectorAll('.sort-btn');
            sortButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const sort = this.dataset.sort;
                    const order = this.dataset.order;
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('sort', sort);
                    currentUrl.searchParams.set('order', order);
                    window.location.href = currentUrl.toString();
                });
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

        function showVehicles(companyName) {
            // Show loading state
            document.getElementById('vehiclesTableBody').innerHTML = '<tr><td colspan="6" class="text-center">Loading...</td></tr>';
            
            // Update modal title
            document.getElementById('vehiclesModalLabel').textContent = companyName + ' - Vehicles';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('vehiclesModal'));
            modal.show();
            
            // Fetch vehicle data
            fetch(`get_vehicles.php?company=${encodeURIComponent(companyName)}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('vehiclesTableBody');
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="no-vehicles">No vehicles found for this company</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = data.map(vehicle => `
                        <tr>
                            <td>${vehicle.fleet_number}</td>
                            <td>${vehicle.vin}</td>
                            <td>${vehicle.make} ${vehicle.model}</td>
                            <td>${vehicle.year}</td>
                            <td>${vehicle.plate_no}</td>
                            <td>${vehicle.last_appointment ? new Date(vehicle.last_appointment).toLocaleDateString() : 'No appointments'}</td>
                        </tr>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('vehiclesTableBody').innerHTML = 
                        '<tr><td colspan="6" class="text-center text-danger">Error loading vehicle data</td></tr>';
                });
        }

        document.getElementById('numVehicles').addEventListener('change', function() {
            const numVehicles = parseInt(this.value);
            const container = document.getElementById('vehiclesContainer');
            container.innerHTML = ''; // Clear existing vehicle fields

            // Default vehicle data
            const defaultVehicles = [
                {
                    fleet_number: 'GST-2024-001',
                    vin: '1XKWD40X1PJ246810',
                    plate_no: 'GST2024',
                    year: '2024',
                    make: 'Kenworth',
                    model: 'T880',
                    smog_due_date: '2025-02-28',
                    clean_truck_due_date: '2025-01-31',
                    notes: 'New Kenworth T880 with PACCAR MX-13 engine and refrigerated trailer capacity'
                },
                {
                    fleet_number: 'GST-2023-002',
                    vin: '1XKWD40X1PJ135792',
                    plate_no: 'GST2023',
                    year: '2023',
                    make: 'Kenworth',
                    model: 'T680 Next Generation',
                    smog_due_date: '2024-12-15',
                    clean_truck_due_date: '2024-11-15',
                    notes: 'Kenworth T680 Next Generation with advanced telematics and driver assistance systems'
                },
                {
                    fleet_number: 'GST-2024-003',
                    vin: '1XKWD40X1PJ369852',
                    plate_no: 'GST2024B',
                    year: '2024',
                    make: 'Kenworth',
                    model: 'W990',
                    smog_due_date: '2025-03-15',
                    clean_truck_due_date: '2025-02-15',
                    notes: 'Kenworth W990 with premium sleeper cab and enhanced driver comfort features'
                }
            ];

            for (let i = 0; i < numVehicles; i++) {
                const vehicle = defaultVehicles[i] || {
                    fleet_number: '',
                    vin: '',
                    plate_no: '',
                    year: new Date().getFullYear(),
                    make: '',
                    model: '',
                    smog_due_date: '',
                    clean_truck_due_date: '',
                    notes: ''
                };

                const vehicleHtml = `
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Vehicle ${i + 1}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fleet Number *</label>
                                    <input type="text" class="form-control" name="vehicles[${i}][fleet_number]" value="${vehicle.fleet_number}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">VIN *</label>
                                    <input type="text" class="form-control" name="vehicles[${i}][vin]" value="${vehicle.vin}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Plate Number *</label>
                                    <input type="text" class="form-control" name="vehicles[${i}][plate_no]" value="${vehicle.plate_no}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Year *</label>
                                    <input type="number" class="form-control" name="vehicles[${i}][year]" min="1900" max="${new Date().getFullYear()}" value="${vehicle.year}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Make *</label>
                                    <select class="form-select" name="vehicles[${i}][make]" required>
                                        <option value="">Select Make</option>
                                        <option value="Kenworth" ${vehicle.make === 'Kenworth' ? 'selected' : ''}>Kenworth</option>
                                        <option value="Peterbilt" ${vehicle.make === 'Peterbilt' ? 'selected' : ''}>Peterbilt</option>
                                        <option value="Freightliner" ${vehicle.make === 'Freightliner' ? 'selected' : ''}>Freightliner</option>
                                        <option value="Volvo" ${vehicle.make === 'Volvo' ? 'selected' : ''}>Volvo</option>
                                        <option value="Mack" ${vehicle.make === 'Mack' ? 'selected' : ''}>Mack</option>
                                        <option value="Western Star" ${vehicle.make === 'Western Star' ? 'selected' : ''}>Western Star</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Model *</label>
                                    <input type="text" class="form-control" name="vehicles[${i}][model]" value="${vehicle.model}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Smog Due Date</label>
                                    <input type="date" class="form-control" name="vehicles[${i}][smog_due_date]" value="${vehicle.smog_due_date}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Clean Truck Due Date</label>
                                    <input type="date" class="form-control" name="vehicles[${i}][clean_truck_due_date]" value="${vehicle.clean_truck_due_date}">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="vehicles[${i}][notes]" rows="2">${vehicle.notes}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', vehicleHtml);
            }
        });

        // Trigger the change event to populate the initial vehicle fields
        document.getElementById('numVehicles').dispatchEvent(new Event('change'));
    </script>

    <!-- Vehicles Modal -->
    <div class="modal fade" id="vehiclesModal" tabindex="-1" aria-labelledby="vehiclesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehiclesModalLabel">Company Vehicles</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="vehicles-table">
                            <thead>
                                <tr>
                                    <th>Fleet Number</th>
                                    <th>VIN</th>
                                    <th>Make</th>
                                    <th>Year</th>
                                    <th>Plate Number</th>
                                    <th>Last Appointment</th>
                                </tr>
                            </thead>
                            <tbody id="vehiclesTableBody">
                                <!-- Vehicle data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 