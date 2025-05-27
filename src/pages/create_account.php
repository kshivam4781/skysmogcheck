<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Log received data
error_log("\n\n=== CREATE_ACCOUNT.PHP START ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Raw POST data:");
error_log(print_r($_POST, true));
error_log("Raw GET data:");
error_log(print_r($_GET, true));
error_log("Session data:");
error_log(print_r($_SESSION, true));

// Get account data from POST
$appointment_id = $_POST['appointment_id'] ?? '';
$company_name = $_POST['company_name'] ?? '';
$contact_person = $_POST['contact_person'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$dot_number = $_POST['dot_number'] ?? '';
$company_address = $_POST['company_address'] ?? '';
$company_type = $_POST['company_type'] ?? '';
$password = $_POST['password'] ?? '';

error_log("Extracted POST values:");
error_log("appointment_id: " . $appointment_id);
error_log("company_name: " . $company_name);
error_log("contact_person: " . $contact_person);
error_log("email: " . $email);
error_log("phone: " . $phone);
error_log("dot_number: " . $dot_number);
error_log("company_type: " . $company_type);

// Get vehicles data
$vehicles_data = isset($_POST['vehicles_data']) ? json_decode($_POST['vehicles_data'], true) : [];
error_log("Decoded vehicles data:");
error_log(print_r($vehicles_data, true));

// Log the data being processed
$logData = [
    'appointment_id' => $appointment_id,
    'company_name' => $company_name,
    'contact_person' => $contact_person,
    'email' => $email,
    'phone' => $phone,
    'dot_number' => $dot_number,
    'company_type' => $company_type,
    'vehicles_count' => count($vehicles_data)
];
error_log("Processed data for database insertion:");
error_log(print_r($logData, true));

// Function to generate a random token
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("Starting database transaction...");
        // Start transaction
        $conn->begin_transaction();

        // Insert into clients table
        error_log("Inserting into clients table...");
        $stmt = $conn->prepare("
            INSERT INTO clients (
                company_name, contact_person_name, email, phone, 
                dot_number, company_address, status, type, 
                created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
        ");

        $stmt->bind_param(
            "ssssssss",
            $company_name,
            $contact_person,
            $email,
            $phone,
            $dot_number,
            $company_address,
            $company_type,
            $email
        );

        $stmt->execute();
        $client_id = $conn->insert_id;
        error_log("Client created with ID: " . $client_id);

        // Insert into accounts table
        error_log("Inserting into accounts table...");
        $stmt = $conn->prepare("
            INSERT INTO accounts (
                firstName, lastName, email, phone, 
                accountType, createdOn, passwordHas, status, associated_id
            ) VALUES (?, NULL, ?, ?, 3, NOW(), ?, 'pending', ?)
        ");

        $password_hash = hashPassword($password);
        $stmt->bind_param(
            "ssssi",
            $contact_person,
            $email,
            $phone,
            $password_hash,
            $client_id
        );

        $stmt->execute();
        $account_id = $conn->insert_id;
        error_log("Account created with ID: " . $account_id);

        // Insert vehicles into clientvehicles table
        error_log("Inserting vehicles into clientvehicles table...");
        $stmt = $conn->prepare("
            INSERT INTO clientvehicles (
                company_id, fleet_number, vin, plate_no, 
                year, make, model, smog_due_date, clean_truck_due_date, 
                status, added_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
        ");

        foreach ($vehicles_data as $vehicle) {
            error_log("Processing vehicle: " . print_r($vehicle, true));
            
            // Convert "Not applicable" dates to NULL
            $smog_due_date = ($vehicle['smog_test_date'] === 'Not applicable' || empty($vehicle['smog_test_date'])) ? null : $vehicle['smog_test_date'];
            $clean_truck_due_date = ($vehicle['clean_truck_date'] === 'Not applicable' || empty($vehicle['clean_truck_date'])) ? null : $vehicle['clean_truck_date'];
            $fleet_number = $vehicle['fleet_number'] ?? '';
            $plate_no = $vehicle['plate_no'] ?? '';
            
            if (empty($plate_no)) {
                throw new Exception("Vehicle plate number is required");
            }
            
            $stmt->bind_param(
                "isssssssss",
                $client_id,
                $fleet_number,
                $vehicle['vin'],
                $plate_no,
                $vehicle['year'],
                $vehicle['make'],
                $vehicle['model'],
                $smog_due_date,
                $clean_truck_due_date,
                $email
            );
            $stmt->execute();
        }

        // Generate and store confirmation token
        error_log("Generating confirmation token...");
        $token = generateToken();
        $stmt = $conn->prepare("
            INSERT INTO appointment_tokens (
                token, consultant_email, appointment_id
            ) VALUES (?, ?, ?)
        ");
        $stmt->bind_param("ssi", $token, $email, $client_id);
        $stmt->execute();

        // Commit transaction
        error_log("Committing transaction...");
        $conn->commit();
        error_log("Transaction committed successfully");

        // Send confirmation email
        error_log("Sending confirmation email...");
        $to = $email;
        $subject = "Confirm Your Sky Smoke Check Account";
        
        // Create email content
        $confirmation_link = "http://" . $_SERVER['HTTP_HOST'] . "/Test/src/pages/confirm_account.php?token=" . $token;
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to Sky Smoke Check</title>
            <style>
                @keyframes float {
                    0% { transform: translateY(0px); }
                    50% { transform: translateY(-10px); }
                    100% { transform: translateY(0px); }
                }
                .cloud-icon {
                    animation: float 3s ease-in-out infinite;
                    color: #17a2b8;
                    font-size: 48px;
                    margin: 20px 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                    background-color: #f8f9fa;
                    border-radius: 10px;
                }
                .header {
                    text-align: center;
                    padding: 20px;
                    background-color: #17a2b8;
                    color: white;
                    border-radius: 10px 10px 0 0;
                }
                .content {
                    padding: 30px;
                    background-color: white;
                    border-radius: 0 0 10px 10px;
                }
                .button {
                    display: inline-block;
                    padding: 12px 30px;
                    background-color: #17a2b8;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #6c757d;
                    font-size: 12px;
                }
                .welcome-text {
                    font-size: 24px;
                    color: #2c3e50;
                    margin: 20px 0;
                }
                .message {
                    color: #6c757d;
                    line-height: 1.6;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Sky Smoke Check!</h1>
                </div>
                <div class='content'>
                    <div style='text-align: center;'>
                        <i class='fas fa-cloud cloud-icon'></i>
                    </div>
                    <h2 class='welcome-text'>Welcome to the Sky Family! 🎉</h2>
                    <p class='message'>
                        Thank you for creating an account with Sky Smoke Check LLC. We're excited to have you on board and look forward to helping you with all your trucking needs!
                    </p>
                    <p class='message'>
                        To complete your registration and activate your account, please click the button below:
                    </p>
                    <div style='text-align: center;'>
                        <a href='{$confirmation_link}' class='button'>Confirm Your Account</a>
                    </div>
                    <p class='message'>
                        If you did not create this account, please ignore this email.
                    </p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                    <p>121 E 11th St, Tracy, CA 95376<br>Phone: (555) 123-4567</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Send email using email helper
        $email_sent = sendEmail($to, $subject, $message);

        if ($email_sent) {
            error_log("Confirmation email sent successfully");
            error_log("=== CREATE_ACCOUNT.PHP END ===");
            $_SESSION['success_message'] = "Account created successfully! Please check your email to confirm your account.";
            header("Location: account_created.php");
            exit();
        } else {
            throw new Exception("Failed to send confirmation email");
        }

    } catch (Exception $e) {
        error_log("Error in create_account.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("=== CREATE_ACCOUNT.PHP END ===");
        
        // Rollback transaction on error
        $conn->rollback();
        
        // Map specific error messages to user-friendly messages
        $error_message = $e->getMessage();
        $user_friendly_message = "An error occurred while creating your account. ";
        
        if (strpos($error_message, "Duplicate entry") !== false) {
            if (strpos($error_message, "email") !== false) {
                $user_friendly_message .= "This email address is already registered.";
            } elseif (strpos($error_message, "dot_number") !== false) {
                $user_friendly_message .= "This DOT number is already registered.";
            } else {
                $user_friendly_message .= "Some of the information you provided is already in use.";
            }
        } elseif (strpos($error_message, "Vehicle plate number is required") !== false) {
            $user_friendly_message .= "Please provide a license plate number for all vehicles.";
        } elseif (strpos($error_message, "Failed to send confirmation email") !== false) {
            $user_friendly_message .= "We couldn't send the confirmation email. Please try again later.";
        } else {
            $user_friendly_message .= "Please try again later or contact support if the problem persists.";
        }
        
        // Return JSON response with user-friendly error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $user_friendly_message
        ]);
        exit();
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("=== CREATE_ACCOUNT.PHP END ===");
    
    // Return JSON response for invalid method
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Invalid request. Please try again."
    ]);
    exit();
}
?>