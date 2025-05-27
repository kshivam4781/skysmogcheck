<?php
session_start();
require_once '../config/db_connection.php';

// Get appointment ID from POST
$appointment_id = isset($_POST['appointment_id']) ? $_POST['appointment_id'] : '';

// Initialize variables
$vehicles = [];

// Fetch vehicle details if appointment_id is provided
if (!empty($appointment_id)) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                v.*,
                s.name as service_name,
                CASE 
                    WHEN v.service_id = 1 THEN 'Clean Truck Check'
                    WHEN v.service_id = 2 THEN 'Smog Test'
                    WHEN v.service_id = 3 THEN 'Both Services'
                END as service_type,
                CASE 
                    WHEN v.service_id = 1 OR v.service_id = 3 THEN a.created_at
                    ELSE NULL
                END as clean_truck_date,
                CASE 
                    WHEN v.service_id = 2 OR v.service_id = 3 THEN MAX(ce.start_time)
                    ELSE NULL
                END as smog_test_date
            FROM vehicles v
            LEFT JOIN services s ON v.service_id = s.id
            LEFT JOIN appointments a ON v.appointment_id = a.id
            LEFT JOIN calendar_events ce ON v.appointment_id = ce.appointment_id AND v.id = ce.vehid
            WHERE v.appointment_id = ?
            GROUP BY v.id
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Format dates for display
            $row['clean_truck_date'] = $row['clean_truck_date'] ? date('Y-m-d', strtotime($row['clean_truck_date'])) : 'Not applicable';
            $row['smog_test_date'] = $row['smog_test_date'] ? date('Y-m-d', strtotime($row['smog_test_date'])) : 'Not applicable';
            $vehicles[] = $row;
        }

        // Log the vehicles found
        error_log("Found " . count($vehicles) . " vehicles for appointment ID: " . $appointment_id);
    } catch (Exception $e) {
        error_log("Error fetching vehicle details: " . $e->getMessage());
    }
}

// Log received data
error_log("Received data in validate_vehicle.php: " . print_r($_POST, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate Vehicle Information - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
        main {
            margin-top: 100px;
            min-height: calc(100vh - 100px);
        }
        .validation-container {
            display: flex;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .vehicle-tabs {
            width: 250px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .vehicle-content {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .nav-pills .nav-link {
            color: #2c3e50;
            border-radius: 5px;
            margin-bottom: 10px;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-pills .nav-link.active {
            background-color: #17a2b8;
            color: white;
        }
        .nav-pills .nav-link:hover:not(.active) {
            background-color: #f8f9fa;
        }
        .vehicle-info {
            margin-bottom: 30px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-passed {
            background-color: #28a745;
            color: white;
        }
        .status-failed {
            background-color: #dc3545;
            color: white;
        }
        .status-warmup {
            background-color: #17a2b8;
            color: white;
        }
        .due-date {
            color: #dc3545;
            font-weight: 500;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .btn-validate {
            background-color: #17a2b8;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-validate:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }
        .btn-add-vehicle {
            background-color: #28a745;
            color: white;
            padding: 12px 15px;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-add-vehicle:hover {
            background-color: #218838;
            transform: translateY(-2px);
            color: white;
        }
        .clean-truck-date {
            border: 2px solid #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        .clean-truck-date:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .smog-test-date {
            border: 2px solid #17a2b8;
            background-color: rgba(23, 162, 184, 0.05);
        }
        .smog-test-date:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
        .btn-remove-vehicle {
            background: none;
            border: none;
            color: #dc3545;
            padding: 0;
            margin-left: auto;
            font-size: 0.875rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .btn-remove-vehicle:hover {
            color: #bd2130;
        }
        .nav-pills .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nav-link-container {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
            position: relative;
            padding-right: 30px;
        }
        .btn-remove-vehicle {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
            font-size: 12px;
        }
        .btn-remove-vehicle:hover {
            background: #bd2130;
            transform: translateY(-50%) scale(1.1);
        }
        .nav-pills .nav-link {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 0;
        }
    </style>
    <script>
        // Add this function at the beginning of your script section
        function showErrorModal(message) {
            // Create modal HTML if it doesn't exist
            if (!document.getElementById('errorModal')) {
                const modalHTML = `
                    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-danger mb-0">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <span id="errorModalMessage"></span>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }

            // Set the error message
            document.getElementById('errorModalMessage').textContent = message;

            // Show the modal
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        }

        // Modify the handleCreateAccount function to use the error modal
        function handleCreateAccount(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating Account...';
            submitBtn.disabled = true;

            fetch('create_account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success - redirect to login page
                    window.location.href = 'login.php';
                } else {
                    // Show error in modal
                    showErrorModal(data.message);
                    // Reset button state
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                // Show error in modal
                showErrorModal('Network error occurred. Please try again.');
                // Reset button state
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            });
        }
    </script>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                    <img src="../assets/images/logo.png" alt="Sky Smog Check Logo" class="nav-logo">
                    <h1>Sky Smog Check</h1>
                </a>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="news.php">News</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="user-actions">
                        <?php 
                        $email = $_SESSION['email'] ?? '';
                        $domain = substr(strrchr($email, "@"), 1);
                        $redirect_page = ($domain === 'skytransportsolutions.com') ? 'admin_welcome.php' : 'welcome.php';
                        ?>
                        <a href="<?php echo $redirect_page; ?>" class="user-icon" title="My Account">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <a href="logout.php" class="login-button">Logout</a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="login-button">Login</a></li>
                <?php endif; ?>
                <li><a href="schedule.php" class="cta-button">Schedule Test</a></li>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>

    <main>
        <div class="validation-container">
            <!-- Vehicle Tabs -->
            <div class="vehicle-tabs">
                <h4 class="mb-4">Vehicles</h4>
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <?php if (empty($vehicles)): ?>
                        <div class="alert alert-info">
                            No vehicles found for this appointment. Please add a vehicle.
                        </div>
                    <?php else: ?>
                        <?php foreach ($vehicles as $index => $vehicle): ?>
                        <div class="nav-link-container">
                            <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    id="v-pills-<?php echo $vehicle['id']; ?>-tab" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#v-pills-<?php echo $vehicle['id']; ?>" 
                                    type="button" 
                                    role="tab">
                                <i class="fas fa-truck"></i>
                                <?php 
                                $vehicleLabel = 'Vehicle ' . ($index + 1);
                                if (!empty($vehicle['plateNo'])) {
                                    $vehicleLabel .= ' (' . htmlspecialchars($vehicle['plateNo']) . ')';
                                } elseif (!empty($vehicle['vin'])) {
                                    $vehicleLabel .= ' (' . htmlspecialchars($vehicle['vin']) . ')';
                                }
                                echo $vehicleLabel;
                                ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="nav-link-container">
                        <button class="nav-link" 
                                id="v-pills-new-tab" 
                                data-bs-toggle="pill" 
                                data-bs-target="#v-pills-new" 
                                type="button" 
                                role="tab"
                                style="display: none;">
                            <i class="fas fa-truck"></i>
                            New Vehicle
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-add-vehicle w-100" onclick="addVehicle()">
                        <i class="fas fa-plus-circle me-2"></i>Add Vehicle
                    </button>
                </div>
            </div>

            <!-- Vehicle Content -->
            <div class="vehicle-content">
                <div class="tab-content" id="v-pills-tabContent">
                    <?php foreach ($vehicles as $index => $vehicle): ?>
                    <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                         id="v-pills-<?php echo $vehicle['id']; ?>" 
                         role="tabpanel">
                        <h3 class="mb-4">Vehicle Information</h3>
                        
                        <div class="vehicle-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">VIN Number</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($vehicle['vin']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">License Plate</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($vehicle['plateNo']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Make</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($vehicle['vehMake']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Year</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($vehicle['vehYear']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="service-info">
                            <h4 class="mb-3">Service Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Service Status</label>
                                        <div>
                                            <?php if ($vehicle['service_id'] == 1 || $vehicle['service_id'] == 3): ?>
                                            <span class="status-badge status-<?php echo strtolower($vehicle['clean_truck_check_status']); ?>">
                                                Clean Truck Check: <?php echo ucfirst($vehicle['clean_truck_check_status']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($vehicle['service_id'] == 2 || $vehicle['service_id'] == 3): ?>
                                            <span class="status-badge status-<?php echo strtolower($vehicle['smoke_test_status']); ?>">
                                                Smog Test: <?php echo ucfirst($vehicle['smoke_test_status']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Clean Truck Check Date</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($vehicle['clean_truck_date']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Smog Test Date</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($vehicle['smog_test_date']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($vehicle['smoke_test_notes'])): ?>
                        <div class="test-notes mt-4">
                            <h4 class="mb-3">Test Notes</h4>
                            <div class="alert alert-info">
                                <?php echo nl2br(htmlspecialchars($vehicle['smoke_test_notes'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2 mt-4">
                            <button type="button" class="btn btn-validate" onclick="validateVehicle(<?php echo $vehicle['id']; ?>)">
                                <i class="fas fa-check-circle me-2"></i>Validate Vehicle
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- New Vehicle Form -->
                    <div class="tab-pane fade" id="v-pills-new" role="tabpanel">
                        <h3 class="mb-4">Add New Vehicle</h3>
                        <form id="newVehicleForm" action="process_new_vehicle.php" method="POST">
                            <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment_id); ?>">
                            
                            <div class="vehicle-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">VIN Number</label>
                                            <input type="text" class="form-control" name="vin" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">License Plate</label>
                                            <input type="text" class="form-control" name="plateNo" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Vehicle Make</label>
                                            <input type="text" class="form-control" name="vehMake" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Vehicle Year</label>
                                            <input type="number" class="form-control" name="vehYear" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="service-info">
                                <h4 class="mb-3">Service Information</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Clean Truck Check Date</label>
                                            <input type="date" class="form-control clean-truck-date" name="clean_truck_date">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Smog Test Date</label>
                                            <input type="date" class="form-control smog-test-date" name="smog_test_date">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="button" class="btn btn-danger" onclick="removeNewVehicle()">
                                    <i class="fas fa-trash me-2"></i>Remove Vehicle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Hidden form for account creation -->
    <form id="accountCreationForm" action="create_account.php" method="POST">
        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment_id); ?>">
        <input type="hidden" name="company_name" id="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
        <input type="hidden" name="contact_person" id="contact_person" value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
        <input type="hidden" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        <input type="hidden" name="phone" id="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        <input type="hidden" name="dot_number" id="dot_number" value="<?php echo htmlspecialchars($_POST['dot_number'] ?? ''); ?>">
        <input type="hidden" name="company_address" id="company_address" value="<?php echo htmlspecialchars($_POST['company_address'] ?? ''); ?>">
        <input type="hidden" name="company_type" id="company_type" value="<?php echo htmlspecialchars($_POST['company_type'] ?? ''); ?>">
        <input type="hidden" name="password" id="password" value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>">
        <input type="hidden" name="vehicles_data" id="vehicles_data" value="">
    </form>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Confirm Account Creation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please review and accept our terms and conditions to proceed with account creation.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="termsCheckbox">
                        <label class="form-check-label" for="termsCheckbox">
                            I accept the <a href="terms.php" target="_blank">Terms and Conditions</a>, 
                            <a href="privacy.php" target="_blank">Privacy Policy</a>, and 
                            <a href="terms.php" target="_blank">Company Web Terms of Use</a>
                        </label>
                    </div>
                    <!-- Display account information for review -->
                    <div class="account-review" style="display: none;">
                        <h6 class="mb-3">Account Information</h6>
                        <div class="mb-2">
                            <strong>Company Name:</strong> 
                            <span id="review_company_name"><?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?></span>
                        </div>
                        <div class="mb-2">
                            <strong>Contact Person:</strong> 
                            <span id="review_contact_person"><?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?></span>
                        </div>
                        <div class="mb-2">
                            <strong>Email:</strong> 
                            <span id="review_email"><?php echo htmlspecialchars($_POST['email'] ?? ''); ?></span>
                        </div>
                        <div class="mb-2">
                            <strong>Phone:</strong> 
                            <span id="review_phone"><?php echo htmlspecialchars($_POST['phone'] ?? ''); ?></span>
                        </div>
                        <div class="mb-2">
                            <strong>DOT Number:</strong> 
                            <span id="review_dot_number"><?php echo htmlspecialchars($_POST['dot_number'] ?? ''); ?></span>
                        </div>
                        <div class="mb-2">
                            <strong>Company Type:</strong> 
                            <span id="review_company_type"><?php echo htmlspecialchars($_POST['company_type'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="proceedToAccount" disabled>Create Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Account Modal -->
    <div class="modal fade" id="createAccountModal" tabindex="-1" aria-labelledby="createAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAccountModalLabel">Create Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="createAccountError" style="display: none;"></div>
                    <form id="createAccountForm" onsubmit="handleCreateAccount(event)">
                        <!-- Form fields here -->
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="dot_number" class="form-label">DOT Number</label>
                            <input type="text" class="form-control" id="dot_number" name="dot_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_address" class="form-label">Company Address</label>
                            <textarea class="form-control" id="company_address" name="company_address" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="company_type" class="form-label">Company Type</label>
                            <select class="form-control" id="company_type" name="company_type" required>
                                <option value="">Select Company Type</option>
                                <option value="carrier">Carrier</option>
                                <option value="broker">Broker</option>
                                <option value="shipper">Shipper</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <input type="hidden" name="vehicles_data" id="vehicles_data">
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Sky Smoke Check LLC</h3>
                <p>Professional truck and vehicle smoke testing services.</p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="index.php#schedule">Book Appointment</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Legal</h3>
                <ul>
                    <li><a href="privacy.php">Privacy & Cookies Policy</a></li>
                    <li><a href="terms.php">Web Terms of Use</a></li>
                    <li><a href="#">Fraud Warning</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p>
                    <i class="fas fa-phone"></i> (555) 123-4567<br>
                    <i class="fas fa-envelope"></i> info@skysmoke.com<br>
                    <i class="fas fa-location-dot"></i> 121 E 11th St, Tracy, CA 95376
                </p>
            </div>
            
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <div class="copyright">
                    <p>&copy; <?php echo date('Y'); ?> Sky Smog Check. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Log the received data from register.php
        <?php
        $receivedData = [
            'appointment_id' => $_POST['appointment_id'] ?? 'Not received',
            'company_name' => $_POST['company_name'] ?? 'Not received',
            'contact_person' => $_POST['contact_person'] ?? 'Not received',
            'email' => $_POST['email'] ?? 'Not received',
            'phone' => $_POST['phone'] ?? 'Not received',
            'dot_number' => $_POST['dot_number'] ?? 'Not received',
            'company_type' => $_POST['company_type'] ?? 'Not received',
            'password' => isset($_POST['password']) ? '******' : 'Not received',
            'vehicles_count' => count($vehicles)
        ];
        
        error_log("Data received in validate_vehicle.php: " . print_r($receivedData, true));
        error_log("Vehicles found in validate_vehicle.php: " . print_r($vehicles, true));
        ?>

        function validateVehicle(vehicleId) {
            // Show the terms modal
            const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
            termsModal.show();
            
            // Store the vehicle ID for later use
            document.getElementById('termsModal').dataset.vehicleId = vehicleId;
        }

        // Handle terms checkbox change
        document.getElementById('termsCheckbox').addEventListener('change', function() {
            const proceedButton = document.getElementById('proceedToAccount');
            const accountReview = document.querySelector('.account-review');
            
            if (this.checked) {
                proceedButton.disabled = false;
                accountReview.style.display = 'block';
            } else {
                proceedButton.disabled = true;
                accountReview.style.display = 'none';
            }
        });

        // Handle proceed button click
        document.getElementById('proceedToAccount').addEventListener('click', function() {
            // Get all validated vehicles
            const vehicles = [];
            document.querySelectorAll('.tab-pane:not(#v-pills-new)').forEach(pane => {
                // Get all input fields in the vehicle info section
                const inputs = pane.querySelectorAll('.vehicle-info input');
                
                const vehicle = {
                    vin: inputs[0]?.value || '',
                    plate_no: inputs[1]?.value || '',
                    make: inputs[2]?.value || '',
                    year: inputs[3]?.value || '',
                    smog_test_date: pane.querySelector('input[value*="Smog Test Date"]')?.value || 'Not applicable',
                    clean_truck_date: pane.querySelector('input[value*="Clean Truck Check Date"]')?.value || 'Not applicable',
                    fleet_number: '',
                    model: ''
                };
                
                // Log each vehicle being processed
                console.log('Processing vehicle:', vehicle);
                
                // Only add vehicle if it has either VIN or plate number
                if (vehicle.vin || vehicle.plate_no) {
                    vehicles.push(vehicle);
                }
            });

            if (vehicles.length === 0) {
                alert('No vehicles found to validate. Please add at least one vehicle.');
                return;
            }

            // Log the data being sent
            console.log('Vehicles data being sent:', vehicles);

            // Set the vehicles data in the form
            const vehiclesDataInput = document.getElementById('vehicles_data');
            if (!vehiclesDataInput) {
                console.error('vehicles_data input not found!');
                return;
            }
            
            vehiclesDataInput.value = JSON.stringify(vehicles);

            // Get all form data
            const form = document.getElementById('accountCreationForm');
            if (!form) {
                console.error('accountCreationForm not found!');
                return;
            }

            // Create FormData object
            const formData = new FormData(form);
            
            // Log all form data being sent
            const logData = {
                appointment_id: formData.get('appointment_id'),
                company_name: formData.get('company_name'),
                contact_person: formData.get('contact_person'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                dot_number: formData.get('dot_number'),
                company_address: formData.get('company_address'),
                company_type: formData.get('company_type'),
                vehicles_data: formData.get('vehicles_data')
            };

            // Send log data to server
            fetch('log_form_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(logData)
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to log form data:', data.message);
                }
            })
            .catch(error => {
                console.error('Error logging form data:', error);
            });
            
            // Submit the form
            form.submit();
        });

        function addVehicle() {
            // Show the new vehicle tab
            const newTab = document.getElementById('v-pills-new-tab');
            const newContent = document.getElementById('v-pills-new');
            
            // Hide all other tabs
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-pane').forEach(content => {
                content.classList.remove('show', 'active');
            });
            
            // Show new vehicle tab
            newTab.style.display = 'flex';
            newTab.classList.add('active');
            newContent.classList.add('show', 'active');
        }

        function removeVehicle(vehicleId, event) {
            event.stopPropagation(); // Prevent tab switching
            
            if (confirm('Are you sure you want to remove this vehicle?')) {
                fetch('remove_vehicle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ vehicle_id: vehicleId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the tab and content
                        const tab = document.querySelector(`#v-pills-${vehicleId}-tab`);
                        const content = document.querySelector(`#v-pills-${vehicleId}`);
                        if (tab) tab.remove();
                        if (content) content.remove();
                        
                        // Update remaining vehicle numbers
                        updateVehicleNumbers();
                    } else {
                        alert('Error removing vehicle: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while removing the vehicle');
                });
            }
        }

        function updateVehicleNumbers() {
            const tabs = document.querySelectorAll('.nav-link-container');
            tabs.forEach((container, index) => {
                const tab = container.querySelector('button.nav-link');
                const icon = tab.querySelector('i');
                const text = document.createTextNode(` Vehicle ${index + 1}`);
                tab.innerHTML = '';
                tab.appendChild(icon);
                tab.appendChild(text);
                
                // Only add remove button if it's a new vehicle
                if (tab.dataset.isNew === 'true') {
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'btn-remove-vehicle';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    removeBtn.onclick = (e) => removeVehicle(tab.dataset.vehicleId, e);
                    container.appendChild(removeBtn);
                }
            });
        }

        // Form submission handler
        document.getElementById('newVehicleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(this);
            formData.append('is_new', '1'); // Add flag to indicate this is a new vehicle
            
            // Send AJAX request
            fetch('process_new_vehicle.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new vehicle tab
                    const navLinkContainer = document.createElement('div');
                    navLinkContainer.className = 'nav-link-container';
                    
                    const newTab = document.createElement('button');
                    newTab.className = 'nav-link';
                    newTab.id = `v-pills-${data.vehicle_id}-tab`;
                    newTab.dataset.bsToggle = 'pill';
                    newTab.dataset.bsTarget = `#v-pills-${data.vehicle_id}`;
                    newTab.dataset.isNew = 'true';
                    newTab.dataset.vehicleId = data.vehicle_id;
                    newTab.innerHTML = `
                        <i class="fas fa-truck"></i>
                        Vehicle ${document.querySelectorAll('.nav-link-container').length}
                    `;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'btn-remove-vehicle';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    removeBtn.onclick = (e) => removeVehicle(data.vehicle_id, e);
                    
                    navLinkContainer.appendChild(newTab);
                    navLinkContainer.appendChild(removeBtn);
                    
                    // Insert before the new vehicle tab container
                    const newVehicleTabContainer = document.querySelector('#v-pills-new-tab').parentElement;
                    newVehicleTabContainer.parentNode.insertBefore(navLinkContainer, newVehicleTabContainer);
                    
                    // Add new vehicle content
                    const newContent = document.createElement('div');
                    newContent.className = 'tab-pane fade show active';
                    newContent.id = `v-pills-${data.vehicle_id}`;
                    newContent.innerHTML = data.html;
                    
                    // Insert before the new vehicle form
                    const newVehicleForm = document.getElementById('v-pills-new');
                    newVehicleForm.parentNode.insertBefore(newContent, newVehicleForm);
                    
                    // Clear form
                    this.reset();
                    
                    // Hide new vehicle form
                    document.getElementById('v-pills-new-tab').style.display = 'none';
                    newVehicleForm.classList.remove('show', 'active');
                } else {
                    alert('Error adding vehicle: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the vehicle');
            });
        });

        // Update date fields based on service type selection
        document.querySelector('select[name="service_id"]').addEventListener('change', function() {
            const cleanTruckDate = document.querySelector('.clean-truck-date');
            const smogTestDate = document.querySelector('.smog-test-date');
            
            switch(this.value) {
                case '1': // Clean Truck Check only
                    cleanTruckDate.required = true;
                    smogTestDate.required = false;
                    smogTestDate.value = '';
                    break;
                case '2': // Smog Test only
                    cleanTruckDate.required = false;
                    smogTestDate.required = true;
                    cleanTruckDate.value = '';
                    break;
                case '3': // Both Services
                    cleanTruckDate.required = true;
                    smogTestDate.required = true;
                    break;
                default:
                    cleanTruckDate.required = false;
                    smogTestDate.required = false;
                    cleanTruckDate.value = '';
                    smogTestDate.value = '';
            }
        });

        function removeNewVehicle() {
            if (confirm('Are you sure you want to remove this vehicle?')) {
                // Hide the new vehicle form
                document.getElementById('v-pills-new-tab').style.display = 'none';
                document.getElementById('v-pills-new').classList.remove('show', 'active');
                
                // Reset the form
                document.getElementById('newVehicleForm').reset();
                
                // Activate the first vehicle tab if it exists
                const firstTab = document.querySelector('.nav-link:not([id="v-pills-new-tab"])');
                if (firstTab) {
                    firstTab.click();
                }
            }
        }
    </script>
</body>
</html> 