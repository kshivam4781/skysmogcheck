<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$appointment_id = $_GET['id'];

// Fetch appointment details
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .thank-you-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .thank-you-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        .thank-you-title {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .thank-you-message {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        .btn-home {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
            padding: 10px 30px;
            font-size: 1.1rem;
        }
        .btn-home:hover {
            background-color: #138496;
            border-color: #117a8b;
            color: white;
        }
    </style>
</head>
<body>
    <div class="thank-you-container">
        <i class="fas fa-check-circle thank-you-icon"></i>
        <h1 class="thank-you-title">Thank You!</h1>
        <p class="thank-you-message">
            Your invoice has been confirmed. We appreciate your business and look forward to serving you.
            <br><br>
            A confirmation email has been sent to <?php echo htmlspecialchars($appointment['email']); ?>.
        </p>
        <a href="index.php" class="btn btn-home">
            <i class="fas fa-home me-2"></i> Return to Home
        </a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 