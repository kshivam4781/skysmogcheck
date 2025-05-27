<?php
session_start();
require_once '../config/db_connection.php';

// Get parameters from URL
$token = $_GET['token'] ?? '';
$appointment_id = $_GET['id'] ?? '';

if (!$token || !$appointment_id) {
    die("Invalid request parameters");
}

try {
    // Verify token and get appointment details
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            ce.user_id as consultant_id,
            acc.firstName as consultant_first_name,
            acc.lastName as consultant_last_name,
            acc.email as consultant_email,
            acc.phone as consultant_phone,
            v.*,
            ctc.*
        FROM appointments a
        JOIN calendar_events ce ON a.id = ce.appointment_id
        JOIN accounts acc ON ce.user_id = acc.email
        LEFT JOIN vehicles v ON a.id = v.appointment_id
        LEFT JOIN clean_truck_checks ctc ON a.id = ctc.appointment_id
        JOIN appointment_tokens at ON a.id = at.appointment_id
        WHERE at.token = ? 
        AND a.id = ?
        AND at.used = 0
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("si", $token, $appointment_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $appointment_data = $result->fetch_assoc();

    if (!$appointment_data) {
        throw new Exception("Invalid or expired token");
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .appointment-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 2rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .section-title {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .info-row {
            margin-bottom: 0.5rem;
        }
        .label {
            font-weight: bold;
            color: #495057;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
        }
        .status-pending { background-color: #ffeeba; color: #856404; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #cce5ff; color: #004085; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="appointment-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Appointment Details</h1>
            <span class="status-badge status-<?php echo strtolower($appointment_data['status']); ?>">
                <?php echo ucfirst($appointment_data['status']); ?>
            </span>
        </div>

        <!-- Appointment Information -->
        <div class="section">
            <h2 class="section-title">Appointment Information</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="label">Appointment ID:</span>
                        <span><?php echo htmlspecialchars($appointment_data['appointment_id']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Company:</span>
                        <span><?php echo htmlspecialchars($appointment_data['companyName']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date:</span>
                        <span><?php echo htmlspecialchars($appointment_data['bookingDate']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Time:</span>
                        <span><?php echo htmlspecialchars($appointment_data['bookingTime']); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="label">Test Location:</span>
                        <span><?php echo htmlspecialchars($appointment_data['test_location'] === 'our_location' ? 'Our Location' : 'Your Location'); ?></span>
                    </div>
                    <?php if ($appointment_data['test_location'] === 'your_location'): ?>
                    <div class="info-row">
                        <span class="label">Address:</span>
                        <span><?php echo htmlspecialchars($appointment_data['test_address']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="label">Number of Vehicles:</span>
                        <span><?php echo htmlspecialchars($appointment_data['number_of_vehicles']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Price:</span>
                        <span>$<?php echo number_format($appointment_data['total_price'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="section">
            <h2 class="section-title">Contact Information</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span><?php echo htmlspecialchars($appointment_data['Name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span><?php echo htmlspecialchars($appointment_data['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone:</span>
                        <span><?php echo htmlspecialchars($appointment_data['phone']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicle Information -->
        <div class="section">
            <h2 class="section-title">Vehicle Information</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="label">Year:</span>
                        <span><?php echo htmlspecialchars($appointment_data['vehYear']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Make:</span>
                        <span><?php echo htmlspecialchars($appointment_data['vehMake']); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="label">VIN:</span>
                        <span><?php echo htmlspecialchars($appointment_data['vin']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Plate Number:</span>
                        <span><?php echo htmlspecialchars($appointment_data['plateNo']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultant Information -->
        <div class="section">
            <h2 class="section-title">Consultant Information</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span><?php echo htmlspecialchars($appointment_data['consultant_first_name'] . ' ' . $appointment_data['consultant_last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span><?php echo htmlspecialchars($appointment_data['consultant_email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone:</span>
                        <span><?php echo htmlspecialchars($appointment_data['consultant_phone']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($appointment_data['special_instructions']): ?>
        <!-- Special Instructions -->
        <div class="section">
            <h2 class="section-title">Special Instructions</h2>
            <p><?php echo nl2br(htmlspecialchars($appointment_data['special_instructions'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <?php if ($appointment_data['status'] === 'pending'): ?>
            <button onclick="acceptChanges()" class="btn btn-success me-2">
                <i class="fas fa-check-circle me-2"></i>Accept Changes
            </button>
            <?php endif; ?>
            <a href="tel:+12095551234" class="btn btn-primary me-2">
                <i class="fas fa-phone me-2"></i>Call Sky Smog Check
            </a>
            <a href="mailto:info@skysmogcheck.com" class="btn btn-info me-2">
                <i class="fas fa-envelope me-2"></i>Send Email
            </a>
            <a href="index.php" class="btn btn-secondary">Return to Home</a>
        </div>
    </div>

    <script>
    function acceptChanges() {
        if (confirm('Are you sure you want to accept these changes?')) {
            fetch('process_updated_appointment_response.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=accept&token=<?php echo $token; ?>&id=<?php echo $appointment_id; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Appointment confirmed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    }
    </script>
</body>
</html> 