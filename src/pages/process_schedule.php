<?php
session_start();
require_once '../config/db_connection.php';

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token');
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone number
function validate_phone($phone) {
    return preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/', $phone);
}

// Function to validate VIN
function validate_vin($vin) {
    return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin);
}

// Function to validate date
function validate_date($date) {
    $today = date('Y-m-d');
    return $date >= $today;
}

    // Initialize error array
    $errors = [];

    // Validate required fields
$required_fields = [
    'company' => 'Company Name',
    'email' => 'Email Address',
    'phone' => 'Phone Number',
    'preferred_date' => 'Preferred Date',
    'preferred_time' => 'Preferred Time',
    'test_location' => 'Test Location'
];

foreach ($required_fields as $field => $label) {
    if (empty($_POST[$field])) {
        $errors[] = "$label is required";
    }
}

// Validate email
if (!empty($_POST['email']) && !validate_email($_POST['email'])) {
    $errors[] = "Invalid email format";
}

// Validate phone
if (!empty($_POST['phone']) && !validate_phone($_POST['phone'])) {
    $errors[] = "Invalid phone number format. Please use XXX-XXX-XXXX format";
}

// Validate date
if (!empty($_POST['preferred_date']) && !validate_date($_POST['preferred_date'])) {
    $errors[] = "Preferred date cannot be in the past";
}

// Validate VIN numbers
if (isset($_POST['vin']) && is_array($_POST['vin'])) {
    foreach ($_POST['vin'] as $vin) {
        if (!validate_vin($vin)) {
            $errors[] = "Invalid VIN number format";
            break;
        }
    }
}

// Validate test location
if ($_POST['test_location'] === 'your_location' && empty($_POST['test_address'])) {
    $errors[] = "Test address is required when selecting 'At Your Location'";
    }

// If there are errors, redirect back to the form
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: schedule.php');
    exit;
}

// Sanitize all input data
$company = sanitize_input($_POST['company']);
$name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
$email = sanitize_input($_POST['email']);
$phone = sanitize_input($_POST['phone']);
$preferred_date = sanitize_input($_POST['preferred_date']);
$preferred_time = sanitize_input($_POST['preferred_time']);
$test_location = sanitize_input($_POST['test_location']);
$test_address = isset($_POST['test_address']) ? sanitize_input($_POST['test_address']) : '';
$special_instructions = isset($_POST['special_instructions']) ? sanitize_input($_POST['special_instructions']) : '';

// Prepare vehicle data
$vehicles = [];
if (isset($_POST['vehicle_year']) && is_array($_POST['vehicle_year'])) {
    for ($i = 0; $i < count($_POST['vehicle_year']); $i++) {
        $vehicles[] = [
            'year' => sanitize_input($_POST['vehicle_year'][$i]),
            'make' => sanitize_input($_POST['make'][$i]),
            'vin' => sanitize_input($_POST['vin'][$i]),
            'license_plate' => sanitize_input($_POST['license_plate'][$i])
        ];
            }
        }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // If there are no errors, proceed with database insertion
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();

            // Insert appointment
            $stmt = $conn->prepare("INSERT INTO appointments (
                Name, email, phone, companyName, bookingDate, bookingTime, 
                test_location, test_address, special_instructions, number_of_vehicles
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $number_of_vehicles = count($vehicles);
            $stmt->bind_param(
                "sssssssssi",
                $name, $email, $phone, $company, $preferred_date, $preferred_time,
                $test_location, $test_address, $special_instructions, $number_of_vehicles
            );

            if ($stmt->execute()) {
                $appointment_id = $stmt->insert_id;
                
                // Insert vehicle information
                $vehicle_stmt = $conn->prepare("INSERT INTO vehicles (
                    appointment_id, vehYear, vehMake, vin, plateNo
                ) VALUES (?, ?, ?, ?, ?)");
                
                // Process each vehicle
                foreach ($vehicles as $vehicle) {
                    $vehicle_stmt->bind_param(
                        "issss",
                        $appointment_id,
                        $vehicle['year'],
                        $vehicle['make'],
                        $vehicle['vin'],
                        $vehicle['license_plate']
                    );
                    $vehicle_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();

                // Send confirmation email
                $to = $email;
                $subject = "Appointment Request Received - Sky Smoke Check LLC";
                
                // Create HTML email message
                $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                            .content { padding: 20px; }
                            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; }
                            .advantages { margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
                            .advantage-item { margin: 10px 0; }
                            .advantage-icon { color: #28a745; margin-right: 10px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Appointment Request Received</h2>
                            </div>
                            <div class='content'>
                                <p>Dear " . htmlspecialchars($name) . ",</p>
                                <p>Thank you for choosing Sky Smoke Check LLC for your smoke testing needs. Your appointment request has been received and will be processed shortly.</p>
                                
                                <h3>Your Appointment Details:</h3>
                                <ul>
                                    <li>Date: " . htmlspecialchars($preferred_date) . "</li>
                                    <li>Time: " . htmlspecialchars($preferred_time) . "</li>
                                    <li>Number of Vehicles: " . count($vehicles) . "</li>
                                    <li>Location: " . ($test_location === 'our_location' ? 'Our Facility' : 'Your Location') . "</li>
                                </ul>

                                <div class='advantages'>
                                    <h3>Why Choose Sky Smoke Check LLC?</h3>
                                    <div class='advantage-item'>
                                        <i class='fas fa-check-circle advantage-icon'></i>
                                        <strong>Expert Technicians:</strong> Our certified professionals ensure accurate and reliable testing.
                                    </div>
                                    <div class='advantage-item'>
                                        <i class='fas fa-check-circle advantage-icon'></i>
                                        <strong>Quick Service:</strong> We complete tests efficiently without compromising quality.
                                    </div>
                                    <div class='advantage-item'>
                                        <i class='fas fa-check-circle advantage-icon'></i>
                                        <strong>Mobile Testing:</strong> We come to your location for your convenience.
                                    </div>
                                    <div class='advantage-item'>
                                        <i class='fas fa-check-circle advantage-icon'></i>
                                        <strong>Competitive Pricing:</strong> We offer the best value for professional smoke testing services.
                                    </div>
                                </div>

                                <p>You will receive a confirmation email once your appointment is approved by our team.</p>
                                
                                <p style='text-align: center; margin-top: 20px;'>
                                    <a href='http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/generate_quotation.php?id=" . $appointment_id . "' 
                                       style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                                        View Your Quotation
                                    </a>
                                </p>
                            </div>
                            <div class='footer'>
                                <p>For any questions, please contact us at (555) 123-4567</p>
                                <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

                require_once('../includes/email_helper.php');
                if (sendEmail($to, $subject, $message)) {
                    // Redirect to quotation generation
                    header("Location: generate_quotation.php?id=" . $appointment_id);
                    exit();
                } else {
                    // If email fails, still redirect to quotation but show error
                    header("Location: generate_quotation.php?id=" . $appointment_id . "&email_error=1");
                    exit();
                }
            } else {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            error_log("Database error: " . $e->getMessage());
            $errors[] = "An error occurred while processing your request. Please try again later.";
        }
    }

    // If there are errors, store them in session and redirect back to form
    if (!empty($errors)) {
        $_SESSION['schedule_errors'] = $errors;
        $_SESSION['schedule_form_data'] = $_POST;
        header("Location: schedule.php");
        exit();
    }
} else {
    // If not a POST request, redirect to schedule page
    header("Location: schedule.php");
    exit();
}
?> 