<?php
session_start();
require_once '../config/db_connection.php';

// Get appointment ID and token from URL
$appointment_id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$appointment_id || !$token) {
    header("Location: index.php");
    exit();
}

// Get current domain
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$domain = $protocol . $_SERVER['HTTP_HOST'];
$base_url = $domain . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/');

// Verify token and get appointment details
$stmt = $conn->prepare("
    SELECT at.*, a.* 
    FROM appointment_tokens at 
    JOIN appointments a ON at.appointment_id = a.id 
    WHERE at.token = ? AND at.appointment_id = ? AND at.used = 0
");
$stmt->bind_param("si", $token, $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment_data = $result->fetch_assoc();

if (!$appointment_data) {
    header("Location: index.php?error=invalid_token");
    exit();
}

// Get vehicle details
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$vehicles_result = $stmt->get_result();
$vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update appointment details
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET bookingDate = ?, 
            bookingTime = ?, 
            test_location = ?, 
            test_address = ?, 
            special_instructions = ?,
            status = 'pending'
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sssssi",
        $_POST['bookingDate'],
        $_POST['bookingTime'],
        $_POST['test_location'],
        $_POST['test_address'],
        $_POST['special_instructions'],
        $appointment_id
    );
    $stmt->execute();

    // Update vehicle details
    $vehicle_stmt = $conn->prepare("
        UPDATE vehicles 
        SET vehYear = ?, 
            vehMake = ?, 
            vin = ?, 
            plateNo = ? 
        WHERE appointment_id = ? AND id = ?
    ");

    foreach ($_POST['vehicles'] as $vehicle_id => $vehicle_data) {
        $vehicle_stmt->bind_param(
            "ssssii",
            $vehicle_data['year'],
            $vehicle_data['make'],
            $vehicle_data['vin'],
            $vehicle_data['plateNo'],
            $appointment_id,
            $vehicle_id
        );
        $vehicle_stmt->execute();
    }

    // Send email to customer for approval
    $to = $appointment_data['email'];
    $subject = "Updated Smoke Check Appointment Request";
    
    $message = '<!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                color: #2c3e50;
                border-bottom: 2px solid #3498db;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .details {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                margin: 10px 5px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                color: #7f8c8d;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Updated Smoke Check Appointment Request</h2>
        </div>
        
        <p>Dear ' . htmlspecialchars($appointment_data['Name']) . ',</p>
        
        <p>Your smoke check appointment has been updated by our consultant. Please review the changes below:</p>
        
        <div class="details">
            <h3>Updated Appointment Details:</h3>
            <p><strong>Company:</strong> ' . htmlspecialchars($appointment_data['companyName']) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($_POST['bookingDate']) . '</p>
            <p><strong>Time:</strong> ' . htmlspecialchars($_POST['bookingTime']) . '</p>
            <p><strong>Test Location:</strong> ' . htmlspecialchars($_POST['test_location'] === 'our_location' ? 'Our Location' : 'Your Location') . '</p>
            ' . ($_POST['test_location'] === 'your_location' ? '<p><strong>Address:</strong> ' . htmlspecialchars($_POST['test_address']) . '</p>' : '') . '
            <p><strong>Number of Vehicles:</strong> ' . count($vehicles) . '</p>
        </div>
        
        <p>Please review the changes and respond by clicking one of the buttons below:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $base_url . '/process_customer_response.php?action=accept&token=' . $token . '&id=' . $appointment_id . '" class="button accept">Accept Changes</a>
            <a href="' . $base_url . '/process_customer_response.php?action=deny&token=' . $token . '&id=' . $appointment_id . '" class="button deny">Request Different Time</a>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>Sky Smoke Check LLC Team</p>
        </div>
    </body>
    </html>';

    require_once('../includes/email_helper.php');
    sendEmail($to, $subject, $message, true);

    // Mark token as used
    $stmt = $conn->prepare("UPDATE appointment_tokens SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    header("Location: reschedule_success.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .reschedule-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .vehicle-card {
            background: #f8f9fa;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <main class="reschedule-container">
        <h1>Reschedule Appointment</h1>
        <p class="lead">Please review and update the appointment details as needed.</p>

        <form method="POST" action="">
            <!-- Appointment Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Appointment Details</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bookingDate" class="form-label">Date</label>
                                <input type="date" class="form-control" id="bookingDate" name="bookingDate" 
                                       value="<?php echo htmlspecialchars($appointment_data['bookingDate']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bookingTime" class="form-label">Time</label>
                                <input type="time" class="form-control" id="bookingTime" name="bookingTime" 
                                       value="<?php echo htmlspecialchars($appointment_data['bookingTime']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Test Location</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="test_location" id="ourLocation" 
                                   value="our_location" <?php echo $appointment_data['test_location'] === 'our_location' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ourLocation">
                                Our Location (121 E 11th St, Tracy, CA 95376)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="test_location" id="yourLocation" 
                                   value="your_location" <?php echo $appointment_data['test_location'] === 'your_location' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="yourLocation">
                                Your Location
                            </label>
                        </div>
                    </div>

                    <div class="mb-3" id="addressField" style="display: none;">
                        <label for="test_address" class="form-label">Test Address</label>
                        <textarea class="form-control" id="test_address" name="test_address" rows="3"><?php echo htmlspecialchars($appointment_data['test_address']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="special_instructions" class="form-label">Special Instructions</label>
                        <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"><?php echo htmlspecialchars($appointment_data['special_instructions']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Vehicle Information</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($vehicles as $index => $vehicle): ?>
                    <div class="vehicle-card">
                        <h4>Vehicle <?php echo $index + 1; ?></h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Year</label>
                                    <input type="text" class="form-control" name="vehicles[<?php echo $vehicle['id']; ?>][year]" 
                                           value="<?php echo htmlspecialchars($vehicle['vehYear']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Make</label>
                                    <input type="text" class="form-control" name="vehicles[<?php echo $vehicle['id']; ?>][make]" 
                                           value="<?php echo htmlspecialchars($vehicle['vehMake']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">VIN</label>
                                    <input type="text" class="form-control" name="vehicles[<?php echo $vehicle['id']; ?>][vin]" 
                                           value="<?php echo htmlspecialchars($vehicle['vin']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Plate Number</label>
                                    <input type="text" class="form-control" name="vehicles[<?php echo $vehicle['id']; ?>][plateNo]" 
                                           value="<?php echo htmlspecialchars($vehicle['plateNo']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary">Send to Customer for Approval</button>
            </div>
        </form>
    </main>

    <script>
        // Show/hide address field based on test location selection
        document.querySelectorAll('input[name="test_location"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('addressField').style.display = 
                    this.value === 'your_location' ? 'block' : 'none';
            });
        });

        // Trigger change event on page load
        document.querySelector('input[name="test_location"]:checked').dispatchEvent(new Event('change'));
    </script>
</body>
</html> 