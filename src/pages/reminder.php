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

// Handle reminder status toggle
if (isset($_POST['toggle_status']) && isset($_POST['reminder_id'])) {
    $stmt = $conn->prepare("UPDATE reminders SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
    $stmt->bind_param("i", $_POST['reminder_id']);
    $stmt->execute();
}

// Get all reminders with related information
$stmt = $conn->prepare("
    SELECT 
        r.*,
        a.companyName,
        v.plateNo,
        v.vehMake,
        v.vehYear,
        rm.title as message_title,
        rm.subject as message_subject
    FROM reminders r
    LEFT JOIN appointments a ON r.appointment_id = a.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    LEFT JOIN reminder_messages rm ON r.message_id = rm.id
    ORDER BY r.next_send ASC
");
$stmt->execute();
$reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminders - Sky Smoke Check LLC</title>
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
        .reminder-card {
            cursor: pointer;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
            border-left: 4px solid #17a2b8;
        }
        .reminder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .reminder-card.active {
            border-left-color: #28a745;
        }
        .reminder-card.inactive {
            border-left-color: #dc3545;
        }
        .reminder-card.recurring {
            border-left-color: #6f42c1;
        }
        .reminder-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .reminder-info i {
            width: 20px;
            color: #6c757d;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .filter-buttons {
            margin-bottom: 20px;
        }
        .filter-buttons .btn {
            margin-right: 10px;
        }
        .modal-reminder-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .modal-reminder-info i {
            width: 25px;
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
                <div class="dashboard-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Reminders</h2>
                        <div class="filter-buttons">
                            <button type="button" class="btn btn-outline-primary" id="showAll">
                                <i class="fas fa-list"></i> All
                            </button>
                            <button type="button" class="btn btn-outline-success" id="showActive">
                                <i class="fas fa-check-circle"></i> Active
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="showInactive">
                                <i class="fas fa-times-circle"></i> Inactive
                            </button>
                            <button type="button" class="btn btn-outline-info" id="showRecurring">
                                <i class="fas fa-sync"></i> Recurring
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <?php foreach ($reminders as $reminder): ?>
                            <div class="col-md-6 mb-4 reminder-card <?php echo $reminder['status']; ?> <?php echo $reminder['is_recurring'] ? 'recurring' : ''; ?>" 
                                 data-bs-toggle="modal" 
                                 data-bs-target="#reminderModal<?php echo $reminder['id']; ?>">
                                <div class="card">
                                    <div class="card-body">
                                        <span class="badge <?php echo $reminder['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?> status-badge">
                                            <?php echo ucfirst($reminder['status']); ?>
                                        </span>
                                        
                                        <h5 class="card-title"><?php echo htmlspecialchars($reminder['message_title']); ?></h5>
                                        
                                        <div class="reminder-info">
                                            <p class="mb-1">
                                                <i class="fas fa-building"></i>
                                                <strong>Company:</strong> <?php echo htmlspecialchars($reminder['companyName']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-truck"></i>
                                                <strong>Vehicle:</strong> 
                                                <?php echo htmlspecialchars($reminder['vehYear'] . ' ' . $reminder['vehMake'] . ' (' . $reminder['plateNo'] . ')'); ?>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-bell"></i>
                                                <strong>Type:</strong> <?php echo ucwords(str_replace('_', ' ', $reminder['reminder_type'])); ?>
                                            </p>
                                            <?php if ($reminder['is_recurring']): ?>
                                                <p class="mb-0">
                                                    <i class="fas fa-sync"></i>
                                                    <strong>Recurring:</strong> <?php echo ucwords(str_replace('_', ' ', $reminder['recurring_type'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="far fa-clock"></i>
                                                Next Send: <?php echo date('M d, Y h:i A', strtotime($reminder['next_send'])); ?>
                                            </small>
                                            <form method="POST" class="d-inline" onclick="event.stopPropagation();">
                                                <input type="hidden" name="reminder_id" value="<?php echo $reminder['id']; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $reminder['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                                    <i class="fas <?php echo $reminder['status'] === 'active' ? 'fa-times' : 'fa-check'; ?>"></i>
                                                    <?php echo $reminder['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each reminder -->
                            <div class="modal fade" id="reminderModal<?php echo $reminder['id']; ?>" tabindex="-1" aria-labelledby="reminderModalLabel<?php echo $reminder['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="reminderModalLabel<?php echo $reminder['id']; ?>">
                                                <?php echo htmlspecialchars($reminder['message_title']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="modal-reminder-info">
                                                <h6 class="mb-3">Reminder Details</h6>
                                                <p class="mb-2">
                                                    <i class="fas fa-building"></i>
                                                    <strong>Company:</strong> <?php echo htmlspecialchars($reminder['companyName']); ?>
                                                </p>
                                                <p class="mb-2">
                                                    <i class="fas fa-truck"></i>
                                                    <strong>Vehicle:</strong> 
                                                    <?php echo htmlspecialchars($reminder['vehYear'] . ' ' . $reminder['vehMake'] . ' (' . $reminder['plateNo'] . ')'); ?>
                                                </p>
                                                <p class="mb-2">
                                                    <i class="fas fa-bell"></i>
                                                    <strong>Type:</strong> <?php echo ucwords(str_replace('_', ' ', $reminder['reminder_type'])); ?>
                                                </p>
                                                <?php if ($reminder['is_recurring']): ?>
                                                    <p class="mb-2">
                                                        <i class="fas fa-sync"></i>
                                                        <strong>Recurring Type:</strong> <?php echo ucwords(str_replace('_', ' ', $reminder['recurring_type'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="mb-2">
                                                    <i class="fas fa-envelope"></i>
                                                    <strong>Subject:</strong> <?php echo htmlspecialchars($reminder['message_subject']); ?>
                                                </p>
                                            </div>

                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="far fa-clock"></i>
                                                    Next Send: <?php echo date('M d, Y h:i A', strtotime($reminder['next_send'])); ?>
                                                </small>
                                                <?php if ($reminder['last_run']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-history"></i>
                                                        Last Sent: <?php echo date('M d, Y h:i A', strtotime($reminder['last_run'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="reminder_id" value="<?php echo $reminder['id']; ?>">
                                                <button type="submit" name="toggle_status" class="btn <?php echo $reminder['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                                    <i class="fas <?php echo $reminder['status'] === 'active' ? 'fa-times' : 'fa-check'; ?>"></i>
                                                    <?php echo $reminder['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        });

        // Filter functionality
        document.getElementById('showAll').addEventListener('click', function() {
            document.querySelectorAll('.reminder-card').forEach(card => {
                card.style.display = 'block';
            });
        });

        document.getElementById('showActive').addEventListener('click', function() {
            document.querySelectorAll('.reminder-card').forEach(card => {
                card.style.display = card.classList.contains('active') ? 'block' : 'none';
            });
        });

        document.getElementById('showInactive').addEventListener('click', function() {
            document.querySelectorAll('.reminder-card').forEach(card => {
                card.style.display = card.classList.contains('inactive') ? 'block' : 'none';
            });
        });

        document.getElementById('showRecurring').addEventListener('click', function() {
            document.querySelectorAll('.reminder-card').forEach(card => {
                card.style.display = card.classList.contains('recurring') ? 'block' : 'none';
            });
        });
    </script>
</body>
</html> 