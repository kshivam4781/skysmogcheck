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

// Get company name from URL parameter
$companyName = isset($_GET['company']) ? $_GET['company'] : '';

// Query to get company details from both clients and appointments tables
$query = "WITH company_data AS (
    -- Get data from clients table
    SELECT 
        c.company_name,
        c.contact_person_name,
        c.email,
        c.phone,
        c.dot_number,
        c.company_address,
        c.type,
        c.status,
        c.created_at,
        c.created_by,
        (SELECT COUNT(*) FROM clientvehicles WHERE company_id = c.id AND status = 'active') as total_vehicles,
        'registered' as source
    FROM clients c
    WHERE c.company_name = ?

    UNION ALL

    -- Get data from appointments table
    SELECT 
        a.companyName as company_name,
        a.Name as contact_person_name,
        a.email,
        a.phone,
        NULL as dot_number,
        a.test_address as company_address,
        NULL as type,
        a.status,
        a.created_at,
        a.approved_by as created_by,
        a.number_of_vehicles as total_vehicles,
        'unregistered' as source
    FROM appointments a
    WHERE a.companyName = ?
    GROUP BY a.companyName, a.Name, a.email, a.phone, a.test_address, a.status, a.created_at, a.approved_by, a.number_of_vehicles
)
SELECT 
    company_name,
    contact_person_name,
    email,
    phone,
    dot_number,
    company_address,
    type,
    status,
    created_at,
    created_by,
    total_vehicles,
    source
FROM company_data
LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $companyName, $companyName);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();

// If no data found, redirect to all clients page
if (!$company) {
    $_SESSION['error_message'] = "Company not found.";
    header("Location: all_clients.php");
    exit();
}

// Get vehicles information based on company type
$vehicles_query = "";
if ($company['source'] === 'registered') {
    // For registered companies, combine data from clientvehicles and appointments
    $vehicles_query = "WITH registered_vehicles AS (
        -- Get vehicles from clientvehicles table
        SELECT 
            cv.id,
            cv.company_id,
            cv.fleet_number COLLATE utf8mb4_unicode_ci as fleet_number,
            cv.vin COLLATE utf8mb4_unicode_ci as vin,
            cv.plate_no COLLATE utf8mb4_unicode_ci as plate_no,
            cv.year,
            cv.make COLLATE utf8mb4_unicode_ci as make,
            cv.model COLLATE utf8mb4_unicode_ci as model,
            cv.smog_due_date,
            cv.smog_last_date,
            cv.clean_truck_due_date,
            cv.clean_truck_last_date,
            cv.status COLLATE utf8mb4_unicode_ci as status,
            cv.added_date,
            cv.added_by COLLATE utf8mb4_unicode_ci as added_by,
            cv.notes COLLATE utf8mb4_unicode_ci as notes,
            cv.created_at,
            cv.updated_at,
            'registered' COLLATE utf8mb4_unicode_ci as source
        FROM clientvehicles cv
        INNER JOIN clients c ON cv.company_id = c.id
        WHERE c.company_name COLLATE utf8mb4_unicode_ci = ?
        AND cv.status = 'active'
    ),
    appointment_vehicles AS (
        -- Get latest vehicle records from appointments
        SELECT 
            v.id,
            v.vehMake COLLATE utf8mb4_unicode_ci as make,
            v.vehYear as year,
            v.vin COLLATE utf8mb4_unicode_ci as vin,
            v.plateNo COLLATE utf8mb4_unicode_ci as plate_no,
            v.result COLLATE utf8mb4_unicode_ci as status,
            v.clean_truck_check_status COLLATE utf8mb4_unicode_ci as clean_truck_check_status,
            v.clean_truck_check_next_date,
            v.smoke_test_status COLLATE utf8mb4_unicode_ci as smoke_test_status,
            v.next_due_date,
            v.created_at,
            'appointment' COLLATE utf8mb4_unicode_ci as source,
            ROW_NUMBER() OVER (
                PARTITION BY v.vin, v.plateNo 
                ORDER BY v.created_at DESC
            ) as rn
        FROM appointments a
        INNER JOIN vehicles v ON a.id = v.appointment_id
        WHERE a.companyName COLLATE utf8mb4_unicode_ci = ?
        AND a.Name COLLATE utf8mb4_unicode_ci = ?
        AND a.email COLLATE utf8mb4_unicode_ci = ?
    )
    SELECT * FROM (
        -- Get registered vehicles with appointment data if available
        SELECT 
            COALESCE(rv.id, av.id) as id,
            rv.fleet_number,
            COALESCE(rv.vin, av.vin) as vin,
            COALESCE(rv.plate_no, av.plate_no) as plate_no,
            COALESCE(rv.year, av.year) as year,
            COALESCE(rv.make, av.make) as make,
            COALESCE(rv.model, 'N/A') as model,
            COALESCE(rv.smog_due_date, av.next_due_date) as smog_due_date,
            COALESCE(rv.clean_truck_due_date, av.clean_truck_check_next_date) as clean_truck_due_date,
            CASE 
                WHEN rv.status IS NOT NULL THEN rv.status
                WHEN av.status = 'pass' THEN 'active'
                WHEN av.status = 'fail' THEN 'inactive'
                ELSE 'pending'
            END COLLATE utf8mb4_unicode_ci as status,
            COALESCE(rv.added_date, av.created_at) as added_date,
            COALESCE(rv.added_by, 'System') as added_by,
            COALESCE(rv.notes, '') as notes,
            COALESCE(rv.created_at, av.created_at) as created_at,
            COALESCE(rv.updated_at, av.created_at) as updated_at,
            av.clean_truck_check_status,
            av.smoke_test_status,
            'registered' COLLATE utf8mb4_unicode_ci as source
        FROM registered_vehicles rv
        LEFT JOIN appointment_vehicles av ON 
            rv.vin = av.vin AND 
            rv.plate_no = av.plate_no AND 
            av.rn = 1

        UNION

        -- Get appointment vehicles that don't exist in registered vehicles
        SELECT 
            av.id,
            NULL as fleet_number,
            av.vin,
            av.plate_no,
            av.year,
            av.make,
            'N/A' COLLATE utf8mb4_unicode_ci as model,
            av.next_due_date as smog_due_date,
            av.clean_truck_check_next_date as clean_truck_due_date,
            CASE 
                WHEN av.status = 'pass' THEN 'active'
                WHEN av.status = 'fail' THEN 'inactive'
                ELSE 'pending'
            END COLLATE utf8mb4_unicode_ci as status,
            av.created_at as added_date,
            'System' COLLATE utf8mb4_unicode_ci as added_by,
            '' COLLATE utf8mb4_unicode_ci as notes,
            av.created_at,
            av.created_at as updated_at,
            av.clean_truck_check_status,
            av.smoke_test_status,
            'appointment' COLLATE utf8mb4_unicode_ci as source
        FROM appointment_vehicles av
        LEFT JOIN registered_vehicles rv ON 
            av.vin = rv.vin AND 
            av.plate_no = rv.plate_no
        WHERE rv.id IS NULL
        AND av.rn = 1
    ) combined_vehicles
    ORDER BY COALESCE(fleet_number, plate_no)";

    $stmt = $conn->prepare($vehicles_query);
    $stmt->bind_param("ssss", 
        $companyName,
        $companyName,
        $company['contact_person_name'],
        $company['email']
    );
} else {
    // For unregistered companies, get vehicles from appointments
    $vehicles_query = "SELECT 
        v.id,
        v.vehMake COLLATE utf8mb4_unicode_ci as make,
        v.vehYear as year,
        v.vin COLLATE utf8mb4_unicode_ci as vin,
        v.plateNo COLLATE utf8mb4_unicode_ci as plate_no,
        v.result COLLATE utf8mb4_unicode_ci as status,
        v.clean_truck_check_status COLLATE utf8mb4_unicode_ci as clean_truck_check_status,
        v.clean_truck_check_next_date,
        v.smoke_test_status COLLATE utf8mb4_unicode_ci as smoke_test_status,
        v.next_due_date,
        v.created_at,
        'appointment' COLLATE utf8mb4_unicode_ci as source,
        ROW_NUMBER() OVER (
            PARTITION BY v.vin, v.plateNo 
            ORDER BY v.created_at DESC
        ) as rn
    FROM appointments a
    INNER JOIN vehicles v ON a.id = v.appointment_id
    WHERE a.companyName COLLATE utf8mb4_unicode_ci = ?
    AND a.Name COLLATE utf8mb4_unicode_ci = ?
    AND a.email COLLATE utf8mb4_unicode_ci = ?
    HAVING rn = 1
    ORDER BY v.plateNo";
    
    $stmt = $conn->prepare($vehicles_query);
    $stmt->bind_param("sss", 
        $companyName,
        $company['contact_person_name'],
        $company['email']
    );
}

$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get appointments information
$appointments_query = "SELECT 
    app.*,
    GROUP_CONCAT(
        CONCAT(
            v.vehMake, ' ', v.vehYear, ' (', v.plateNo, ')',
            ' - Service: ',
            CASE 
                WHEN v.service_id = 1 THEN 'Clean Truck Check'
                WHEN v.service_id = 2 THEN 'Smog Test'
                WHEN v.service_id = 3 THEN 'Clean Truck Check & Smog Test'
                ELSE 'N/A'
            END,
            ' - Result: ', COALESCE(v.result, 'N/A'),
            ' - Clean Truck: ', COALESCE(v.clean_truck_check_status, 'N/A')
        ) SEPARATOR '|'
    ) as vehicle_details,
    GROUP_CONCAT(DISTINCT v.service_id) as service_types
FROM appointments app
LEFT JOIN vehicles v ON app.id = v.appointment_id
WHERE app.companyName COLLATE utf8mb4_unicode_ci = ?
GROUP BY app.id
ORDER BY app.created_at DESC";

$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("s", $companyName);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company_name); ?> - Sky Smoke Check LLC</title>
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
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .company-info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .company-info-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem;
        }
        .company-info-card .card-body {
            padding: 1.5rem;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .vehicles-table, .appointments-table {
            width: 100%;
            margin-bottom: 0;
        }
        .vehicles-table th, .appointments-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.5em 0.75em;
        }
        .back-button {
            margin-bottom: 1rem;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .logout-link {
            color: #dc3545;
        }
        .logout-link:hover {
            background-color: rgba(220,53,69,0.1);
        }
        /* New styles for tabs and image section */
        .company-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            padding: 2rem;
        }
        
        .company-logo {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        
        .company-logo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #dee2e6;
            cursor: pointer;
        }
        
        .company-logo-placeholder i {
            font-size: 3rem;
            color: #6c757d;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            border: none;
            color: #007bff;
        }
        
        .nav-tabs .nav-link.active {
            border: none;
            color: #007bff;
            border-bottom: 3px solid #007bff;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 2rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .info-card h6 {
            color: #6c757d;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-item label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.25rem;
            display: block;
        }
        
        .info-item span {
            color: #212529;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-badge.inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .company-info-grid,
        .company-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            margin-bottom: 0.5rem;
        }
        
        .info-item label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            display: block;
        }
        
        .info-item span {
            font-size: 1rem;
            color: #212529;
            font-weight: 500;
        }
        
        /* Add new styles for the updated layout */
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            height: 100%;
        }
        
        .info-card h6 {
            color: #495057;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .stat-item label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
        }
        
        .vehicles-table th,
        .appointments-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
        }
        
        .btn-group .btn i {
            font-size: 0.875rem;
        }
        
        /* Add new styles for the updated layout */
        .vehicle-detail {
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .vehicle-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
        }
        
        .badge.bg-info {
            background-color: #0dcaf0 !important;
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

                <div class="back-button">
                    <a href="all_clients.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Clients
                    </a>
                </div>

                <!-- Company Header with Logo -->
                <div class="company-header">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <?php if (isset($company['logo']) && $company['logo']): ?>
                                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" class="company-logo">
                            <?php else: ?>
                                <div class="company-logo-placeholder" data-bs-toggle="modal" data-bs-target="#uploadLogoModal">
                                    <i class="fas fa-building"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <h2 class="mb-0 me-3"><?php echo htmlspecialchars($company['company_name']); ?></h2>
                                        <?php if ($company['source'] === 'unregistered'): ?>
                                            <span class="badge bg-warning text-dark">Unregistered Client</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="company-info-grid">
                                        <div class="info-item">
                                            <label>Contact Person</label>
                                            <span><?php echo htmlspecialchars($company['contact_person_name'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Email</label>
                                            <span><?php echo htmlspecialchars($company['email'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Phone</label>
                                            <span><?php echo htmlspecialchars($company['phone'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="company-details-grid">
                                        <?php if ($company['source'] === 'registered'): ?>
                                        <div class="info-item">
                                            <label>DOT Number</label>
                                            <span><?php echo htmlspecialchars($company['dot_number'] ?? 'N/A'); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <label>Address</label>
                                            <span><?php echo htmlspecialchars($company['company_address'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Total Vehicles</label>
                                            <span><?php echo htmlspecialchars($company['total_vehicles'] ?? '0'); ?></span>
                                        </div>
                                        <?php if ($company['source'] === 'registered'): ?>
                                        <div class="info-item">
                                            <label>Type</label>
                                            <span><?php echo htmlspecialchars($company['type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Status</label>
                                            <span class="status-badge <?php echo strtolower($company['status'] ?? 'pending'); ?>">
                                                <?php echo htmlspecialchars($company['status'] ?? 'Pending'); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($company['source'] === 'registered'): ?>
                            <div class="row mt-3">
                                <div class="col-12 text-end">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editCompanyModal">
                                        <i class="fas fa-edit"></i> Edit Company
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs" id="companyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="fas fa-info-circle me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles" type="button" role="tab">
                            <i class="fas fa-truck me-2"></i>Vehicles
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                            <i class="fas fa-calendar me-2"></i>Appointments
                        </button>
                    </li>
                    <?php if ($company['source'] === 'registered'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                            <i class="fas fa-file-alt me-2"></i>Documents
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="companyTabsContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6><i class="fas fa-history me-2"></i>Registration History</h6>
                                    <div class="info-item">
                                        <label>Created On</label>
                                        <span><?php echo $company['created_at'] ? date('M d, Y', strtotime($company['created_at'])) : 'N/A'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Created By</label>
                                        <span><?php echo htmlspecialchars($company['created_by'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <h6><i class="fas fa-chart-line me-2"></i>Quick Stats</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="stat-item">
                                                <label>Total Vehicles</label>
                                                <span class="stat-value"><?php echo htmlspecialchars($company['total_vehicles'] ?? '0'); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-item">
                                                <label>Active Vehicles</label>
                                                <span class="stat-value"><?php 
                                                    $active_vehicles = array_filter($vehicles, function($v) { 
                                                        return $v['status'] === 'active'; 
                                                    });
                                                    echo count($active_vehicles);
                                                ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-6">
                                            <div class="stat-item">
                                                <label>Total Appointments</label>
                                                <span class="stat-value"><?php echo count($appointments); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-item">
                                                <label>Completed Services</label>
                                                <span class="stat-value"><?php 
                                                    $completed_services = array_filter($appointments, function($a) { 
                                                        return $a['status'] === 'completed'; 
                                                    });
                                                    echo count($completed_services);
                                                ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicles Tab -->
                    <div class="tab-pane fade" id="vehicles" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Vehicle Fleet</h5>
                            <?php if ($company['source'] === 'registered'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                <i class="fas fa-plus me-2"></i>Add Vehicle
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table vehicles-table">
                                <thead>
                                    <tr>
                                        <?php if ($company['source'] === 'registered'): ?>
                                        <th>Fleet Number</th>
                                        <?php endif; ?>
                                        <th>VIN</th>
                                        <th>Plate Number</th>
                                        <th>Year</th>
                                        <th>Make</th>
                                        <th>Model</th>
                                        <th>Smog Status</th>
                                        <th>Smog Due Date</th>
                                        <th>Clean Truck Status</th>
                                        <th>Clean Truck Due Date</th>
                                        <th>Last Service</th>
                                        <th>Source</th>
                                        <?php if ($company['source'] === 'registered'): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <tr>
                                            <?php if ($company['source'] === 'registered'): ?>
                                            <td><?php echo htmlspecialchars($vehicle['fleet_number'] ?? 'N/A'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($vehicle['vin']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['plate_no']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['make']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($vehicle['smoke_test_status'] ?? $vehicle['status']) {
                                                        'passed', 'active' => 'success',
                                                        'failed', 'inactive' => 'danger',
                                                        'warmup' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($vehicle['smoke_test_status'] ?? $vehicle['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $vehicle['smog_due_date'] ? date('M d, Y', strtotime($vehicle['smog_due_date'])) : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($vehicle['clean_truck_check_status']) {
                                                        'passed' => 'success',
                                                        'failed' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($vehicle['clean_truck_check_status'] ?? 'N/A')); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $vehicle['clean_truck_due_date'] ? date('M d, Y', strtotime($vehicle['clean_truck_due_date'])) : 'N/A'; ?></td>
                                            <td><?php echo $vehicle['created_at'] ? date('M d, Y', strtotime($vehicle['created_at'])) : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $vehicle['source'] === 'registered' ? 'primary' : 'info'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($vehicle['source'])); ?>
                                                </span>
                                            </td>
                                            <?php if ($company['source'] === 'registered'): ?>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editVehicleModal<?php echo $vehicle['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteVehicleModal<?php echo $vehicle['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Appointments Tab -->
                    <div class="tab-pane fade" id="appointments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Service History</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleAppointmentModal">
                                <i class="fas fa-calendar-plus me-2"></i>Schedule Appointment
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table appointments-table">
                                <thead>
                                    <tr>
                                        <th>Service Requested</th>
                                        <th>Service Type</th>
                                        <th>Vehicles</th>
                                        <th>Status</th>
                                        <th>Price</th>
                                        <th>Approved By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M d, Y h:i A', strtotime($appointment['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $service_types = explode(',', $appointment['service_types']);
                                                $unique_services = array_unique($service_types);
                                                foreach ($unique_services as $service_id) {
                                                    $service_name = match((int)$service_id) {
                                                        1 => 'Clean Truck Check',
                                                        2 => 'Smog Test',
                                                        3 => 'Clean Truck Check & Smog Test',
                                                        default => 'Unknown'
                                                    };
                                                    echo '<span class="badge bg-info me-1">' . htmlspecialchars($service_name) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $vehicle_details = explode('|', $appointment['vehicle_details']);
                                                foreach ($vehicle_details as $detail) {
                                                    echo '<div class="vehicle-detail">' . htmlspecialchars($detail) . '</div>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($appointment['status']) {
                                                        'completed' => 'success',
                                                        'confirmed' => 'info',
                                                        'cancelled' => 'danger',
                                                        default => 'warning'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                $<?php echo number_format($appointment['total_price'], 2); ?>
                                                <?php if ($appointment['discount_amount'] > 0): ?>
                                                    <br>
                                                    <small class="text-success">
                                                        -$<?php echo number_format($appointment['discount_amount'], 2); ?>
                                                        (<?php echo $appointment['discount_type']; ?>)
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['approved_by']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewAppointmentModal<?php echo $appointment['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($appointment['status'] !== 'completed'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#completeAppointmentModal<?php echo $appointment['id']; ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($company['source'] === 'registered'): ?>
                    <!-- Documents Tab -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Company Documents</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="fas fa-upload me-2"></i>Upload Document
                            </button>
                        </div>
                        <div class="row" id="documentsList">
                            <!-- Documents will be loaded here -->
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>

    <!-- Upload Logo Modal -->
    <div class="modal fade" id="uploadLogoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Company Logo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadLogoForm" action="upload_company_logo.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>">
                        <div class="mb-3">
                            <label class="form-label">Select Logo</label>
                            <input type="file" class="form-control" name="logo" accept="image/*" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="uploadLogoForm" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadDocumentForm" action="upload_company_document.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>">
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select class="form-select" name="document_type" required>
                                <option value="">Select Type</option>
                                <option value="insurance">Insurance</option>
                                <option value="registration">Registration</option>
                                <option value="permit">Permit</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <input type="file" class="form-control" name="document" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="uploadDocumentForm" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <div class="modal fade" id="editCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Company Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editCompanyForm" action="update_company.php" method="POST">
                        <input type="hidden" name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($company['contact_person_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($company['email']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($company['phone']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">DOT Number</label>
                                <input type="text" class="form-control" name="dot_number" value="<?php echo htmlspecialchars($company['dot_number']); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($company['company_address']); ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" required>
                                    <option value="IRP" <?php echo $company['type'] === 'IRP' ? 'selected' : ''; ?>>IRP</option>
                                    <option value="Local" <?php echo $company['type'] === 'Local' ? 'selected' : ''; ?>>Local</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $company['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $company['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editCompanyForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 