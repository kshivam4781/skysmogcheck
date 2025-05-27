<?php
session_start();
require_once '../config/db_connection.php';

// Get the selected language from the session or default to English
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';

// Handle language change
if (isset($_POST['lang'])) {
    $_SESSION['lang'] = $_POST['lang'];
    $lang = $_POST['lang'];
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Language strings
$strings = [
    'en' => [
        'title' => 'Schedule a Smoke Test',
        'subtitle' => 'Fill out the form below to schedule your smoke test appointment',
        'name' => 'Full Name',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'company' => 'Company Name',
        'vehicle' => 'Vehicle Information',
        'date' => 'Preferred Date',
        'time' => 'Preferred Time',
        'location' => 'Test Location',
        'notes' => 'Additional Notes',
        'submit' => 'Schedule Appointment',
        'language' => 'Language'
    ],
    'pa' => [
        'title' => 'ਸਮੋਕ ਟੈਸਟ ਸ਼ੈਡਿਊਲ ਕਰੋ',
        'subtitle' => 'ਆਪਣੀ ਸਮੋਕ ਟੈਸਟ ਅਪਾਇੰਟਮੈਂਟ ਸ਼ੈਡਿਊਲ ਕਰਨ ਲਈ ਹੇਠਾਂ ਦਿੱਤੇ ਫਾਰਮ ਨੂੰ ਭਰੋ',
        'name' => 'ਪੂਰਾ ਨਾਮ',
        'email' => 'ਈਮੇਲ ਪਤਾ',
        'phone' => 'ਫੋਨ ਨੰਬਰ',
        'company' => 'ਕੰਪਨੀ ਦਾ ਨਾਮ',
        'vehicle' => 'ਵਾਹਨ ਦੀ ਜਾਣਕਾਰੀ',
        'date' => 'ਪਸੰਦੀਦਾ ਤਾਰੀਖ',
        'time' => 'ਪਸੰਦੀਦਾ ਸਮਾਂ',
        'location' => 'ਟੈਸਟ ਟਿਕਾਣਾ',
        'notes' => 'ਵਾਧੂ ਨੋਟਸ',
        'submit' => 'ਅਪਾਇੰਟਮੈਂਟ ਸ਼ੈਡਿਊਲ ਕਰੋ',
        'language' => 'ਭਾਸ਼ਾ'
    ]
];

$current_strings = $strings[$lang];

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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get all available time slots
$all_time_slots = [
    '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '11:30:00',
    '12:00:00', '12:30:00', '13:00:00', '13:30:00', '14:00:00', '14:30:00',
    '15:00:00', '15:30:00', '16:00:00', '16:30:00', '17:00:00', '17:30:00'
];

// Get booked times for tomorrow (default date)
$default_date = date('Y-m-d', strtotime('+1 day'));
$booked_times = getBookedTimes($conn, $default_date);
$available_times = array_diff($all_time_slots, $booked_times);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_strings['title']; ?> - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/schedule.css">
    <style>
        .language-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .language-switcher select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php" style="text-decoration: none;">
                <h1>Sky Smoke Check LLC</h1>
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

    <main class="schedule-container">
        <div class="language-switcher">
            <form method="post" action="">
                <select name="lang" onchange="this.form.submit()">
                    <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="pa" <?php echo $lang === 'pa' ? 'selected' : ''; ?>>ਪੰਜਾਬੀ</option>
                </select>
            </form>
        </div>

        <div class="schedule-header">
            <h1><?php echo $current_strings['title']; ?></h1>
            <p><?php echo $current_strings['subtitle']; ?></p>
        </div>

        <form id="scheduleForm" action="process_schedule.php" method="POST" class="schedule-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <!-- Customer Information -->
            <section class="form-section">
                <h2>Customer Information</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="company"><?php echo $current_strings['company']; ?> *</label>
                            <input type="text" id="company" name="company" class="form-control" required 
                                   value="Test Company Inc.">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name"><?php echo $current_strings['name']; ?></label>
                            <input type="text" id="name" name="name" class="form-control"
                                   value="John Doe">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email"><?php echo $current_strings['email']; ?> *</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                   value="shivamssing96@gmail.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone"><?php echo $current_strings['phone']; ?> *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" required 
                                   pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" 
                                   placeholder="123-456-7890"
                                   value="123-456-7890">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Vehicle Information -->
            <section class="form-section">
                <h2><?php echo $current_strings['vehicle']; ?></h2>
                <div id="vehicle-container">
                    <div class="vehicle-entry">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="vehicle_year">Vehicle Year *</label>
                                    <input type="number" id="vehicle_year" name="vehicle_year[]" class="form-control" required min="1980" max="2024"
                                           value="2020">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="make">Make *</label>
                                    <input type="text" id="make" name="make[]" class="form-control" required
                                           value="Toyota">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="vin">VIN Number *</label>
                                    <input type="text" id="vin" name="vin[]" class="form-control" required 
                                           pattern="[A-HJ-NPR-Z0-9]{17}" 
                                           title="Please enter a valid 17-character VIN number"
                                           value="1HGCM82633A123456">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="license_plate">License Plate *</label>
                                    <input type="text" id="license_plate" name="license_plate[]" class="form-control" required
                                           value="ABC123">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="button" class="btn btn-secondary" id="add-vehicle">Add Another Vehicle</button>
                    </div>
                </div>
            </section>

            <!-- Test Details -->
            <section class="form-section">
                <h2>Test Details</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="preferred_date"><?php echo $current_strings['date']; ?> *</label>
                            <input type="date" id="preferred_date" name="preferred_date" class="form-control" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="preferred_time"><?php echo $current_strings['time']; ?> *</label>
                            <select id="preferred_time" name="preferred_time" class="form-control" required>
                                <option value="">Select Time</option>
                                <?php foreach ($available_times as $time): ?>
                                    <?php 
                                    $display_time = date('g:i A', strtotime($time));
                                    $value = date('H:i', strtotime($time));
                                    ?>
                                    <option value="<?php echo $value; ?>" <?php echo $value === '09:00' ? 'selected' : ''; ?>>
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
                            <label for="test_location"><?php echo $current_strings['location']; ?> *</label>
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
                            <label for="test_address">Test Address *</label>
                            <textarea id="test_address" name="test_address" class="form-control" rows="3" 
                                      placeholder="Please provide the complete address where the test will be conducted"
                                      value="123 Test Street, Test City, CA 12345">123 Test Street, Test City, CA 12345</textarea>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Additional Information -->
            <section class="form-section">
                <h2><?php echo $current_strings['notes']; ?></h2>
                <div class="form-group">
                    <label for="special_instructions"><?php echo $current_strings['notes']; ?></label>
                    <textarea id="special_instructions" name="special_instructions" class="form-control" rows="4"
                              value="This is a test appointment">This is a test appointment</textarea>
                </div>
            </section>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $current_strings['submit']; ?></button>
            </div>
        </form>
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
                    <li><a href="#schedule">Book Appointment</a></li>
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
                    <p>&copy; 2024 Sky Smoke Check LLC. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
    <script>
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

            // Function to create remove button
            function createRemoveButton(vehicleElement) {
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-danger btn-sm remove-vehicle';
                removeButton.innerHTML = '<i class="fas fa-trash"></i> Remove Vehicle';
                removeButton.style.marginTop = '10px';
                removeButton.style.marginBottom = '10px';
                
                removeButton.addEventListener('click', function() {
                    vehicleElement.remove();
                    vehicleCount--;
                    updateLocationOptions();
                });
                
                return removeButton;
            }

            addVehicleButton.addEventListener('click', function() {
                vehicleCount++;
                const newVehicle = document.createElement('div');
                newVehicle.className = 'vehicle-entry mt-3';
                newVehicle.innerHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="vehicle_year_${vehicleCount}">Vehicle Year *</label>
                                <input type="number" id="vehicle_year_${vehicleCount}" name="vehicle_year[]" class="form-control" required min="1980" max="2024">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="make_${vehicleCount}">Make *</label>
                                <input type="text" id="make_${vehicleCount}" name="make[]" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="vin_${vehicleCount}">VIN Number *</label>
                                <input type="text" id="vin_${vehicleCount}" name="vin[]" class="form-control" required 
                                       pattern="[A-HJ-NPR-Z0-9]{17}" 
                                       title="Please enter a valid 17-character VIN number">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="license_plate_${vehicleCount}">License Plate *</label>
                                <input type="text" id="license_plate_${vehicleCount}" name="license_plate[]" class="form-control" required>
                            </div>
                        </div>
                    </div>
                `;
                
                // Add remove button to the new vehicle entry
                const removeButton = createRemoveButton(newVehicle);
                newVehicle.appendChild(removeButton);
                
                vehicleContainer.appendChild(newVehicle);
                updateLocationOptions();
            });

            // Initialize location options
            updateLocationOptions();

            // Form validation
            const form = document.getElementById('scheduleForm');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });

            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function(e) {
                let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
                e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2] + (x[3] ? '-' + x[3] : '');
            });

            // VIN validation
            const vinInputs = document.querySelectorAll('input[name="vin[]"]');
            vinInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    e.target.value = e.target.value.toUpperCase();
                });
            });

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
    </script>
</body>
</html> 