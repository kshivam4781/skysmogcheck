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

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build query
$query = "SELECT * FROM appointments WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (id LIKE ? OR companyName LIKE ? OR Name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= "sssss";
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY $sort_by $sort_order";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Appointments - Sky Smoke Check LLC</title>
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
        .dashboard-container {
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        .appointment-card {
            transition: transform 0.2s;
        }
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                <h2 class="mb-4">All Appointments</h2>
                
                <!-- Filter Section -->
                <div class="dashboard-container mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search by ID, Company, Name, Email, or Phone" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="sort_by">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Sort by Date Created</option>
                                <option value="bookingDate" <?php echo $sort_by === 'bookingDate' ? 'selected' : ''; ?>>Sort by Booking Date</option>
                                <option value="companyName" <?php echo $sort_by === 'companyName' ? 'selected' : ''; ?>>Sort by Company Name</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="sort_order">
                                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="all_appointments.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Appointments Grid -->
                <div class="dashboard-container">
                    <div class="row">
                        <?php foreach ($appointments as $appointment): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card appointment-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Appointment #<?php echo $appointment['id']; ?></h5>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($appointment['companyName']); ?></h6>
                                    <p class="card-text">
                                        <strong>Contact:</strong> <?php echo htmlspecialchars($appointment['Name']); ?><br>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($appointment['email']); ?><br>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['phone']); ?><br>
                                        <strong>Date:</strong> <?php echo date('F j, Y', strtotime($appointment['bookingDate'])); ?><br>
                                        <strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['bookingTime'])); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <?php if ($appointment['status'] === 'pending'): ?>
                                                <button class="btn btn-success btn-sm accept-appointment" data-id="<?php echo $appointment['id']; ?>">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button class="btn btn-danger btn-sm delete-appointment" data-id="<?php echo $appointment['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            Created: <?php echo date('M j, Y', strtotime($appointment['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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
            // Handle Accept Appointment
            document.querySelectorAll('.accept-appointment').forEach(button => {
                button.addEventListener('click', function() {
                    const appointmentId = this.dataset.id;
                    if (confirm('Are you sure you want to accept this appointment?')) {
                        // Show loading state
                        const originalButtonText = this.innerHTML;
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

                        // Create form data
                        const formData = new FormData();
                        formData.append('appointment_id', appointmentId);

                        fetch('process_appointment_accept.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message
                                const successAlert = document.createElement('div');
                                successAlert.className = 'alert alert-success alert-dismissible fade show';
                                successAlert.innerHTML = `
                                    ${data.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                `;
                                document.querySelector('.container-fluid').insertBefore(successAlert, document.querySelector('.dashboard-container'));
                                
                                // Reload the page after a short delay
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                throw new Error(data.message || 'Failed to accept appointment');
                            }
                        })
                        .catch(error => {
                            // Show error message
                            const errorAlert = document.createElement('div');
                            errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                            errorAlert.innerHTML = `
                                ${error.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            document.querySelector('.container-fluid').insertBefore(errorAlert, document.querySelector('.dashboard-container'));
                            
                            // Reset button state
                            this.disabled = false;
                            this.innerHTML = originalButtonText;
                        });
                    }
                });
            });

            // Handle Delete Appointment
            document.querySelectorAll('.delete-appointment').forEach(button => {
                button.addEventListener('click', function() {
                    const appointmentId = this.dataset.id;
                    if (confirm('Are you sure you want to delete this appointment? This action cannot be undone.')) {
                        fetch('process_appointment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete&appointment_id=${appointmentId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Appointment deleted successfully');
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while processing your request');
                        });
                    }
                });
            });
        });
    </script>
</body>
</html> 