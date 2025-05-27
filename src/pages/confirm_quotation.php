<?php
session_start();
require_once '../config/db_connection.php';

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : null;

if (!$token) {
    header("Location: login.php");
    exit();
}

// Verify token and get appointment details
$stmt = $conn->prepare("
    SELECT at.*, a.* 
    FROM appointment_tokens at 
    JOIN appointments a ON at.appointment_id = a.id 
    WHERE at.token = ? AND at.used = 0
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$quotation = $result->fetch_assoc();

if (!$quotation) {
    header("Location: login.php");
    exit();
}

// Handle confirmation
if (isset($_POST['confirm'])) {
    // Mark token as used
    $stmt = $conn->prepare("UPDATE appointment_tokens SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Update appointment status
    $stmt = $conn->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $quotation['appointment_id']);
    $stmt->execute();
    
    // Update calendar events status
    $stmt = $conn->prepare("UPDATE calendar_events SET status = 'confirmed' WHERE appointment_id = ?");
    $stmt->bind_param("i", $quotation['appointment_id']);
    $stmt->execute();
    
    $_SESSION['success_message'] = "Quotation has been confirmed successfully. Your appointment has been added to our calendar.<br><br>
    You may now close this window or visit our website at <a href='http://" . $_SERVER['HTTP_HOST'] . "'>" . $_SERVER['HTTP_HOST'] . "</a> for more information.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Quotation - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <style>
        .confirmation-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <h2 class="text-center mb-4">Confirm Quotation</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <h4>Quotation Details</h4>
                <p><strong>Company Name:</strong> <?php echo htmlspecialchars($quotation['companyName']); ?></p>
                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($quotation['bookingDate'])); ?></p>
                <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($quotation['bookingTime'])); ?></p>
                <p><strong>Total Price:</strong> $<?php echo number_format($quotation['total_price'], 2); ?></p>
            </div>

            <form method="post">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I confirm that I have reviewed the quotation and agree to the terms and conditions.
                    </label>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="confirm" class="btn btn-success">
                        <i class="fas fa-check me-2"></i> Confirm Quotation
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 