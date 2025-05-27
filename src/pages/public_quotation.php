<?php
session_start();
require_once '../config/db_connection.php';

// Get current date for date inputs
$current_date = date('Y-m-d');

// Fetch services
$services = $conn->query("SELECT * FROM services ORDER BY name");

// Fetch consultants
$consultants = $conn->query("SELECT * FROM accounts WHERE accountType = 2 ORDER BY firstName, lastName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Quotation - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
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
        .required-field::after {
            content: " *";
            color: red;
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
        .subsection-title {
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .discount-option {
            margin-bottom: 15px;
        }
        .discount-input {
            margin-top: 10px;
            padding-left: 20px;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
    </style>
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
                <li><a href="public_quotation.php" class="cta-button">Schedule Test</a></li>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container mt-4">
            <div class="quotation-container">
                <div class="quotation-header">
                    <h1 class="quotation-title">Request a Quotation</h1>
                    <p class="quotation-subtitle">Sky Smoke Check LLC</p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="process_quotation.php" id="quotationForm">
                    <!-- Customer Information -->
                    <section class="quotation-section">
                        <h3>Customer Information</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="company" class="form-label required-field">Company Name</label>
                                    <input type="text" id="company" name="company" class="form-control" required 
                                           value="ABC Trucking Company">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label required-field">Contact Name</label>
                                    <input type="text" id="name" name="name" class="form-control" required 
                                           value="John Smith">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label required-field">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" required 
                                           value="john.smith@abctrucking.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-label required-field">Phone</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" required 
                                           pattern="\(\d{3}\) \d{3}-\d{4}" 
                                           placeholder="(123) 456-7890"
                                           value="(555) 123-4567">
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Vehicle Information -->
                    <section class="quotation-section">
                        <h3>Vehicle Information</h3>
                        <div id="vehicles-container">
                            <!-- First vehicle entry -->
                            <div class="vehicle-entry">
                                <div class="vehicle-header">
                                    <div class="vehicle-count">Vehicle 1</div>
                                </div>

                                <!-- Service Information -->
                                <div class="vehicle-subsection mb-3">
                                    <h5 class="subsection-title">Service Information</h5>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="service_type" class="form-label required-field">Service Type</label>
                                                <select id="service_type" name="service_type[]" class="form-control" required onchange="toggleTestLocation(this)">
                                                    <option value="">Select Service</option>
                                                    <?php 
                                                    $services->data_seek(0);
                                                    while ($service = $services->fetch_assoc()): 
                                                    ?>
                                                        <option value="<?php echo $service['id']; ?>" 
                                                                data-price="<?php echo $service['price']; ?>"
                                                                <?php echo ($service['id'] == 1) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
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
                                                       min="1980" max="<?php echo date('Y'); ?>"
                                                       value="2020">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="make" class="form-label required-field">Make</label>
                                                <input type="text" id="make" name="make[]" class="form-control" required
                                                       value="Freightliner">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="vin" class="form-label required-field">VIN Number</label>
                                                <input type="text" id="vin" name="vin[]" class="form-control" required 
                                                       pattern="[A-HJ-NPR-Z0-9]{17}" 
                                                       title="Please enter a valid 17-character VIN number"
                                                       value="1HGCM82633A123456">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="license_plate" class="form-label required-field">License Plate</label>
                                                <input type="text" id="license_plate" name="license_plate[]" class="form-control" required
                                                       value="ABC123">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Smog Check Status Section (initially hidden) -->
                                <div class="smog-check-section" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                                    <h5>Smog Check Status</h5>
                                    <div class="form-group mb-3">
                                        <label class="form-label required-field">Has the vehicle's smog check been completed?</label>
                                        <div class="d-flex gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="smog_check_completed[]" value="yes" checked>
                                                <label class="form-check-label">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="smog_check_completed[]" value="no">
                                                <label class="form-check-label">No</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="smog-check-pending-details" style="display: none;">
                                        <div class="form-group">
                                            <label class="form-label required-field">Please provide the following information:</label>
                                            <div class="mb-3">
                                                <label class="form-label">Reason why smoke test is pending:</label>
                                                <textarea class="form-control" name="smog_check_pending_reason[]" rows="2" placeholder="Enter reason for pending smoke test"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">When do you expect to complete smoke test:</label>
                                                <input type="date" class="form-control" name="smog_check_expected_date[]" min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Test Location Section (initially hidden) -->
                                <div class="test-location-section" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                                    <h5>Test Location Details</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="test_date[]">Test Date *</label>
                                                <input type="date" class="form-control" name="test_date[]" min="<?php echo date('Y-m-d'); ?>" onchange="updateTimeSlots(this)">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="test_time[]">Test Time <span class="text-danger">*</span></label>
                                                <select class="form-control" name="test_time[]">
                                                    <option value="">Select Time</option>
                                                </select>
                                                <input type="hidden" name="test_time_required[]" value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="test_location[]">Test Location *</label>
                                                <select class="form-control" name="test_location[]" onchange="toggleCustomLocation(this)">
                                                    <option value="">Select Location</option>
                                                    <option value="our_location">Our Location: 121 E 11th St, Tracy, CA 95376</option>
                                                    <option value="your_location">Your Location</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6 custom-location-input" style="display: none;">
                                            <div class="form-group">
                                                <label for="custom_location[]">Your Location Address *</label>
                                                <input type="text" class="form-control" name="custom_location[]" placeholder="Enter your location address">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-secondary" onclick="addVehicle()">
                                <i class="fas fa-plus me-2"></i> Add Another Vehicle
                            </button>
                        </div>
                    </section>

                    <!-- Additional Information -->
                    <section class="quotation-section">
                        <h3>Additional Notes</h3>
                        <div class="form-group">
                            <label for="special_instructions" class="form-label">Special Instructions</label>
                            <textarea id="special_instructions" name="special_instructions" class="form-control" rows="4">Please schedule the service during morning hours if possible.</textarea>
                        </div>
                    </section>

                    <div class="button-container">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
    <script>
        let vehicleCount = 1;
        // Add cache for time slots
        const timeSlotCache = new Map();

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
                                <select id="service_type_${vehicleCount}" name="service_type[]" class="form-control" required onchange="toggleTestLocation(this)">
                                    <option value="">Select Service</option>
                                    <?php 
                                    $services->data_seek(0);
                                    while ($service = $services->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $service['id']; ?>" 
                                                data-price="<?php echo $service['price']; ?>">
                                            <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
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
                                       min="1980" max="<?php echo date('Y'); ?>">
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
                </div>

                <!-- Smog Check Status Section (initially hidden) -->
                <div class="smog-check-section" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <h5>Smog Check Status</h5>
                    <div class="form-group mb-3">
                        <label class="form-label required-field">Has the vehicle's smog check been completed?</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="smog_check_completed[]" value="yes" checked>
                                <label class="form-check-label">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="smog_check_completed[]" value="no">
                                <label class="form-check-label">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="smog-check-pending-details" style="display: none;">
                        <div class="form-group">
                            <label class="form-label required-field">Please provide the following information:</label>
                            <div class="mb-3">
                                <label class="form-label">Reason why smoke test is pending:</label>
                                <textarea class="form-control" name="smog_check_pending_reason[]" rows="2" placeholder="Enter reason for pending smoke test"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">When do you expect to complete smoke test:</label>
                                <input type="date" class="form-control" name="smog_check_expected_date[]" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Test Location Section (initially hidden) -->
                <div class="test-location-section" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <h5>Test Location Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="test_date_${vehicleCount}">Test Date *</label>
                                <input type="date" class="form-control" name="test_date[]" min="<?php echo date('Y-m-d'); ?>" onchange="updateTimeSlots(this)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="test_time_${vehicleCount}">Test Time <span class="text-danger">*</span></label>
                                <select class="form-control" name="test_time[]">
                                    <option value="">Select Time</option>
                                </select>
                                <input type="hidden" name="test_time_required[]" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="test_location_${vehicleCount}">Test Location *</label>
                                <select class="form-control" name="test_location[]" onchange="toggleCustomLocation(this)">
                                    <option value="">Select Location</option>
                                    <option value="our_location">Our Location: 121 E 11th St, Tracy, CA 95376</option>
                                    <option value="your_location">Your Location</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 custom-location-input" style="display: none;">
                            <div class="form-group">
                                <label for="custom_location_${vehicleCount}">Your Location Address *</label>
                                <input type="text" class="form-control" name="custom_location[]" placeholder="Enter your location address">
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('vehicles-container').appendChild(newVehicle);

            // Add event listener to remove button
            newVehicle.querySelector('.remove-vehicle').addEventListener('click', function() {
                newVehicle.remove();
            });

            // Add event listener for date change
            const dateInput = newVehicle.querySelector('input[type="date"]');
            dateInput.addEventListener('change', function() {
                updateTimeSlots(this);
            });
        }

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });

        // Form submission handler
        document.getElementById('quotationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate test time selections only for SMOG test (2) and Both services (3)
            const testTimeSelects = document.querySelectorAll('select[name="test_time[]"]');
            const testTimeRequired = document.querySelectorAll('input[name="test_time_required[]"]');
            const serviceTypes = document.querySelectorAll('select[name="service_type[]"]');
            let isValid = true;

            testTimeSelects.forEach((select, index) => {
                const serviceId = serviceTypes[index].value;
                // Only validate if service is SMOG test (2) or Both services (3)
                if ((serviceId === '2' || serviceId === '3') && testTimeRequired[index].value === "1" && !select.value) {
                    isValid = false;
                    select.classList.add('is-invalid');
                } else {
                    select.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please select a test time for all SMOG test appointments.'
                });
                return;
            }
            
            // Show loading state
            Swal.fire({
                title: 'Generating Quotation',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const formData = new FormData(this);
                
                // Set default values for service_id = 3 (Both services)
                serviceTypes.forEach((select, index) => {
                    if (select.value === '3') {
                        formData.set(`smog_check_completed[${index}]`, 'no');
                        formData.set(`smog_check_verified[${index}]`, 'no');
                        formData.set(`smog_check_pending_reason[${index}]`, '');
                        formData.set(`smog_check_expected_date[${index}]`, '');
                    }
                });
                
                // Store form data in a global variable for later use
                window.quotationFormData = formData;
                
                const response = await fetch('process_quotation.php?preview=true', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to generate quotation');
                }

                if (data.success && data.pdf_data) {
                    // Create PDF viewer container
                    const container = document.querySelector('.quotation-container');
                    container.innerHTML = `
                        <div class="quotation-header">
                            <h1 class="quotation-title">Your Quotation</h1>
                            <p class="quotation-subtitle">Sky Smoke Check LLC</p>
                        </div>
                        <div class="pdf-container" style="height: 800px; margin-bottom: 20px;">
                            <iframe id="pdfViewer" style="width: 100%; height: 100%; border: none;"></iframe>
                        </div>
                        <div class="button-container">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                                <i class="fas fa-arrow-left me-2"></i> Back to Home
                            </button>
                            <button type="button" class="btn btn-primary" onclick="submitQuotation()">
                                <i class="fas fa-paper-plane me-2"></i> Submit Quotation
                            </button>
                        </div>
                    `;

                    // Load PDF in iframe
                    const pdfViewer = document.getElementById('pdfViewer');
                    pdfViewer.src = 'data:application/pdf;base64,' + data.pdf_data;
                    
                    // Close the loading dialog
                    Swal.close();
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'An error occurred while generating the quotation'
                });
            }
        });

        function submitQuotation() {
            if (!window.quotationFormData) {
                Swal.fire({
                    title: 'Error!',
                    text: 'No quotation data found. Please generate a quotation first.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Submitting Quotation',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('process_quotation.php?submit=true', {
                method: 'POST',
                body: window.quotationFormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Store the appointment ID in the session
                    fetch('store_appointment_id.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ appointment_id: data.appointment_id })
                    })
                    .then(() => {
                        window.location.href = 'quotation_success.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to submit quotation',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while submitting the quotation',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        function toggleTestLocation(selectElement) {
            const vehicleEntry = selectElement.closest('.vehicle-entry');
            const testLocationSection = vehicleEntry.querySelector('.test-location-section');
            const smogCheckSection = vehicleEntry.querySelector('.smog-check-section');
            const serviceId = selectElement.value;
            const dateInput = testLocationSection.querySelector('input[type="date"]');
            const timeSelect = testLocationSection.querySelector('select[name="test_time[]"]');
            
            // Show smog check section only for Clean Truck Check (1)
            if (serviceId === '1') {
                smogCheckSection.style.display = 'block';
            } else {
                smogCheckSection.style.display = 'none';
            }
            
            // Show test location section for SMOG test (2) or Both services (3)
            if (serviceId === '2' || serviceId === '3') {
                testLocationSection.style.display = 'block';
                
                // Make test location fields required
                dateInput.setAttribute('required', 'required');
                if (dateInput.value) {
                    timeSelect.setAttribute('required', 'required');
                }
            } else {
                testLocationSection.style.display = 'none';
                
                // Remove required attribute from test location fields
                dateInput.removeAttribute('required');
                timeSelect.removeAttribute('required');
                timeSelect.disabled = true;
                timeSelect.innerHTML = '<option value="">Select Time</option>';
            }
        }

        function toggleCustomLocation(selectElement) {
            const vehicleEntry = selectElement.closest('.vehicle-entry');
            const customLocationInput = vehicleEntry.querySelector('.custom-location-input');
            
            if (selectElement.value === 'your_location') {
                customLocationInput.style.display = 'block';
                customLocationInput.querySelector('input').setAttribute('required', 'required');
            } else {
                customLocationInput.style.display = 'none';
                customLocationInput.querySelector('input').removeAttribute('required');
            }
        }

        // Add date validation and time slot update
        document.addEventListener('DOMContentLoaded', function() {
            // Add date validation to ensure future dates only
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        alert('Please select a future date');
                        this.value = '';
                    } else {
                        updateTimeSlots(this);
                    }
                });
            });
        });

        function updateTimeSlots(dateInput) {
            const vehicleEntry = dateInput.closest('.vehicle-entry');
            const timeSelect = vehicleEntry.querySelector('select[name="test_time[]"]');
            const timeRequiredInput = vehicleEntry.querySelector('input[name="test_time_required[]"]');
            const serviceSelect = vehicleEntry.querySelector('select[name="service_type[]"]');
            const selectedDate = dateInput.value;
            const serviceId = serviceSelect.value;

            // Only proceed if service is SMOG test (2) or Both services (3)
            if (serviceId !== '2' && serviceId !== '3') {
                timeSelect.innerHTML = '<option value="">Select Time</option>';
                timeSelect.disabled = true;
                if (timeRequiredInput) {
                    timeRequiredInput.value = "0";
                }
                return;
            }

            if (!selectedDate) {
                timeSelect.innerHTML = '<option value="">Select Time</option>';
                timeSelect.disabled = true;
                if (timeRequiredInput) {
                    timeRequiredInput.value = "0";
                }
                return;
            }

            // Check cache first
            if (timeSlotCache.has(selectedDate)) {
                updateTimeSelectWithData(timeSelect, timeRequiredInput, timeSlotCache.get(selectedDate));
                return;
            }

            // Show loading state
            timeSelect.innerHTML = '<option value="">Loading available times...</option>';
            timeSelect.disabled = true;
            if (timeRequiredInput) {
                timeRequiredInput.value = "0";
            }

            // Fetch available time slots
            fetch(`get_available_appointment_time.php?date=${selectedDate}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Cache the results
                        timeSlotCache.set(selectedDate, data);
                        updateTimeSelectWithData(timeSelect, timeRequiredInput, data);
                    } else {
                        timeSelect.innerHTML = '<option value="">Error loading times</option>';
                        timeSelect.disabled = true;
                        if (timeRequiredInput) {
                            timeRequiredInput.value = "0";
                        }
                    }
                })
                .catch(error => {
                    timeSelect.innerHTML = '<option value="">Error loading times</option>';
                    timeSelect.disabled = true;
                    if (timeRequiredInput) {
                        timeRequiredInput.value = "0";
                    }
                });
        }

        // Helper function to update time select with data
        function updateTimeSelectWithData(timeSelect, timeRequiredInput, data) {
            let options = '<option value="">Select Time</option>';
            if (data.available_slots && data.available_slots.length > 0) {
                data.available_slots.forEach(slot => {
                    options += `<option value="${slot.value}">${slot.display}</option>`;
                });
                timeSelect.disabled = false;
                if (timeRequiredInput) {
                    timeRequiredInput.value = "1";
                }
            } else {
                options += '<option value="" disabled>No available times for this date</option>';
                timeSelect.disabled = true;
                if (timeRequiredInput) {
                    timeRequiredInput.value = "0";
                }
            }
            timeSelect.innerHTML = options;
        }

        // Clear cache when form is submitted
        document.getElementById('quotationForm').addEventListener('submit', function() {
            timeSlotCache.clear();
        });

        // Add event listener for service type changes
        document.addEventListener('change', function(e) {
            if (e.target.matches('select[name="service_type[]"]')) {
                const vehicleEntry = e.target.closest('.vehicle-entry');
                const testLocationSection = vehicleEntry.querySelector('.test-location-section');
                const serviceId = e.target.value;
                
                // Show test location section only for SMOG test (2) or Both services (3)
                if (serviceId === '2' || serviceId === '3') {
                    testLocationSection.style.display = 'block';
                } else {
                    testLocationSection.style.display = 'none';
                    // Reset and disable time select
                    const timeSelect = testLocationSection.querySelector('select[name="test_time[]"]');
                    const timeRequiredInput = testLocationSection.querySelector('input[name="test_time_required[]"]');
                    timeSelect.innerHTML = '<option value="">Select Time</option>';
                    timeSelect.disabled = true;
                    timeRequiredInput.value = "0";
                }
            }
        });

        // Add event listener for smog check completion radio buttons
        document.addEventListener('change', function(e) {
            if (e.target.matches('input[name="smog_check_completed[]"]')) {
                const vehicleEntry = e.target.closest('.vehicle-entry');
                const pendingDetails = vehicleEntry.querySelector('.smog-check-pending-details');
                const textarea = pendingDetails.querySelector('textarea[name="smog_check_pending_reason[]"]');
                const dateInput = pendingDetails.querySelector('input[name="smog_check_expected_date[]"]');
                
                if (e.target.value === 'no') {
                    pendingDetails.style.display = 'block';
                    textarea.setAttribute('required', 'required');
                    dateInput.setAttribute('required', 'required');
                } else {
                    pendingDetails.style.display = 'none';
                    textarea.removeAttribute('required');
                    dateInput.removeAttribute('required');
                }
            }
        });
    </script>
</body>
</html> 