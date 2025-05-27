<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 4) {
    header("Location: login.php");
    exit();
}

// Get total number of users
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM accounts");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total_users'];

// Get user counts by account type
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN accountType = 1 THEN 1 ELSE 0 END) as developers,
        SUM(CASE WHEN accountType = 2 THEN 1 ELSE 0 END) as consultants,
        SUM(CASE WHEN accountType = 3 THEN 1 ELSE 0 END) as users,
        SUM(CASE WHEN accountType = 4 THEN 1 ELSE 0 END) as admins
    FROM accounts
");
$stmt->execute();
$user_counts = $stmt->get_result()->fetch_assoc();

// Get all users with their details
$stmt = $conn->prepare("
    SELECT 
        a.*,
        CASE 
            WHEN a.accountType = 1 THEN 'Developer'
            WHEN a.accountType = 2 THEN 'Consultant'
            WHEN a.accountType = 3 THEN 'User'
            WHEN a.accountType = 4 THEN 'Admin'
        END as accountTypeName
    FROM accounts a
    ORDER BY a.createdOn DESC
");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Sky Smoke Check LLC</title>
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
        .kpi-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .kpi-card .kpi-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .kpi-card .kpi-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .kpi-card .kpi-label {
            color: #666;
            font-size: 1rem;
        }
        .user-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .user-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .user-table td {
            vertical-align: middle;
        }
        .account-type-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        .badge-admin {
            background-color: #6c757d;
            color: white;
        }
        .badge-user {
            background-color: #20c997;
            color: white;
        }
        .badge-developer {
            background-color: #0dcaf0;
            color: white;
        }
        .badge-consultant {
            background-color: #ffc107;
            color: #212529;
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
                <h3>Sky Smoke Check</h3>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="welcome.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="manage_news.php">
                        <i class="fas fa-newspaper"></i> Manage News
                    </a>
                </li>
                <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                <li>
                    <a href="#" onclick="showComingSoon('All Clients'); return false;">
                        <i class="fas fa-users"></i> All Clients
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('All Appointments'); return false;">
                        <i class="fas fa-calendar-alt"></i> All Appointments
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('Clean Truck Checks'); return false;">
                        <i class="fas fa-truck"></i> Clean Truck Checks
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('Smog Tests'); return false;">
                        <i class="fas fa-smog"></i> Smog Tests
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                    <li>
                        <a href="manage_users.php" class="active">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#" onclick="showComingSoon('Settings'); return false;">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="container-fluid">
                <h2 class="mb-4">User Management</h2>
                
                <!-- Add User Button -->
                <div class="mb-4">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </button>
                </div>

                <!-- KPI Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="kpi-value"><?php echo $total_users; ?></div>
                            <div class="kpi-label">Total Users</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-info">
                                <i class="fas fa-code"></i>
                            </div>
                            <div class="kpi-value"><?php echo $user_counts['developers']; ?></div>
                            <div class="kpi-label">Developers</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-success">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="kpi-value"><?php echo $user_counts['consultants']; ?></div>
                            <div class="kpi-label">Consultants</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card">
                            <div class="kpi-icon text-warning">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="kpi-value"><?php echo $user_counts['admins']; ?></div>
                            <div class="kpi-label">Admins</div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            All Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Account Type</th>
                                        <th>Created On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = $conn->query("SELECT * FROM accounts ORDER BY createdOn DESC");
                                    while ($row = $result->fetch_assoc()) {
                                        $accountTypeNames = [
                                            1 => "Developer",
                                            2 => "Consultant",
                                            3 => "User",
                                            4 => "Admin"
                                        ];
                                        $accountType = intval($row['accountType']);
                                        $accountTypeName = isset($accountTypeNames[$accountType]) ? $accountTypeNames[$accountType] : "Unknown";
                                        $isCurrentUser = ($_SESSION['user_id'] == $row['email']);
                                        $rowClass = $isCurrentUser ? 'table-secondary' : '';
                                        echo "<tr class='" . $rowClass . "'>";
                                        echo "<td>" .($isCurrentUser ? " <i class='fas fa-user text-primary' data-bs-toggle='tooltip' title='Current User'></i>" : "") . htmlspecialchars($row['firstName']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['lastName']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                                        echo "<td><span class='account-type-badge badge-" . strtolower($accountTypeName) . "'>" . htmlspecialchars($accountTypeName) . "</span></td>";
                                        echo "<td>" . date('M d, Y', strtotime($row['createdOn'])) . "</td>";
                                        echo "<td>";
                                        if (!$isCurrentUser) {
                                            echo "<button class='btn btn-danger btn-sm delete-user' 
                                                    data-email='" . htmlspecialchars($row['email']) . "'
                                                    data-name='" . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . "'>
                                                    <i class='fas fa-trash'></i> Delete
                                                  </button>";
                                        }
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" action="add_user_handler.php" method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="accountType" class="form-label">Account Type</label>
                            <select class="form-select" id="accountType" name="accountType" required>
                                <option value="">Select Account Type</option>
                                <option value="1">Developer (1)</option>
                                <option value="2">Consultant (2)</option>
                                <option value="3">User (3)</option>
                                <option value="4">Admin (4)</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="addUserForm" class="btn btn-primary">Add User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <?php echo isset($_SESSION['error_message']) ? $_SESSION['error_message'] : ''; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php echo isset($_SESSION['success_message']) ? $_SESSION['success_message'] : ''; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <span id="deleteUserName"></span>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Coming Soon Modal -->
    <div class="modal fade" id="comingSoonModal" tabindex="-1" aria-labelledby="comingSoonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="comingSoonModalLabel">Coming Soon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-tools fa-3x mb-3 text-primary"></i>
                    <h4 id="featureName"></h4>
                    <p class="mb-0">This feature is currently under development. Please check back later.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../scripts/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            let userToDelete = null;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));

            // Check localStorage for sidebar state
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Save state to localStorage
                localStorage.setItem('sidebarState', 
                    sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
                );
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

            // Show success/error message if exists
            <?php if (isset($_SESSION['success_message'])): ?>
                const successToast = new bootstrap.Toast(document.getElementById('successToast'));
                successToast.show();
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            // Delete user functionality
            document.querySelectorAll('.delete-user').forEach(button => {
                button.addEventListener('click', function() {
                    userToDelete = this.dataset.email;
                    document.getElementById('deleteUserName').textContent = this.dataset.name;
                    deleteModal.show();
                });
            });

            document.getElementById('confirmDelete').addEventListener('click', function() {
                if (userToDelete) {
                    fetch('delete_user_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'email=' + encodeURIComponent(userToDelete)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const successToast = new bootstrap.Toast(document.getElementById('successToast'));
                            document.querySelector('.toast-body').textContent = data.message;
                            successToast.show();
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                            document.querySelector('#errorModal .alert').textContent = data.message;
                            errorModal.show();
                        }
                    })
                    .catch(error => {
                        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        document.querySelector('#errorModal .alert').textContent = 'Error occurred while deleting user';
                        errorModal.show();
                    });
                    deleteModal.hide();
                }
            });
        });

        // Function to show coming soon message
        function showComingSoon(featureName) {
            const modal = new bootstrap.Modal(document.getElementById('comingSoonModal'));
            document.getElementById('featureName').textContent = featureName;
            modal.show();
        }
    </script>
</body>
</html> 