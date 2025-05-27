<?php
session_start();
require_once '../config/db_connection.php';

// Get appointment ID from URL
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : '';

// Initialize variables
$company = '';
$name = '';
$email = '';
$phone = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = $_POST['company_name'] ?? '';
    $name = $_POST['contact_person'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
} else {
    // Fetch appointment details if appointment_id is provided
    if (!empty($appointment_id)) {
        try {
            $stmt = $conn->prepare("
                SELECT a.companyName, a.Name, a.email, a.phone 
                FROM appointments a 
                WHERE a.id = ? 
                ORDER BY a.created_at DESC 
                LIMIT 1
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                $company = $row['companyName'];
                $name = $row['Name'];
                $email = $row['email'];
                $phone = $row['phone'];
            }
        } catch (Exception $e) {
            error_log("Error fetching appointment details: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
        main {
            margin-top: 100px;
            min-height: calc(100vh - 100px);
        }
        .register-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .form-control:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
        .btn-register {
            background-color: #17a2b8;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 5px;
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
        <div class="container">
            <div class="register-form">
                <h2 class="form-title">Create Your Account</h2>
                <form action="validate_vehicle.php" method="POST" id="registerForm">
                    <?php if (!empty($appointment_id)): ?>
                    <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment_id); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="company" class="form-label">Company Name</label>
                        <input type="text" class="form-control" id="company" name="company_name" 
                            value="<?php echo htmlspecialchars($company); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="name" name="contact_person" 
                            value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                            value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                            value="<?php echo htmlspecialchars($phone); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="dot_number" class="form-label">DOT Number</label>
                        <input type="text" class="form-control" id="dot_number" name="dot_number" 
                            placeholder="Enter your DOT number">
                    </div>

                    <div class="mb-3">
                        <label for="company_type" class="form-label">Company Type</label>
                        <select class="form-select" id="company_type" name="company_type" required>
                            <option value="">Select Company Type</option>
                            <option value="IRP">IRP</option>
                            <option value="local">Local</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-register">
                            <i class="fas fa-truck me-2"></i>Next: Validate Vehicle Information
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate passwords match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }

            // Log the data being sent
            console.log('Data being sent to validate_vehicle.php:');
            const formData = new FormData(this);
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Submit the form
            this.submit();
        });
    </script>
</body>
</html> 