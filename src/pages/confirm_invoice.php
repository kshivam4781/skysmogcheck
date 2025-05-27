<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
require_once __DIR__ . '/../config/db_connection.php';

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$appointment_id = $_GET['id'];

// Fetch appointment details
$stmt = $conn->prepare("
    SELECT a.*, c.firstName as consultant_first_name, c.lastName as consultant_last_name, c.email as consultant_email
    FROM appointments a
    LEFT JOIN accounts c ON a.approved_by = c.idaccounts
    WHERE a.id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header("Location: index.php");
    exit();
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Update appointment status
    $stmt = $conn->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    
    // Redirect to thank you page
    header("Location: thank_you.php?id=" . $appointment_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Invoice - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .invoice-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .invoice-title {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .pdf-container {
            width: 100%;
            height: 800px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            overflow: hidden;
            margin: 20px 0;
        }
        .pdf-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .button-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 20px;
        }
        .btn-confirm {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 10px 30px;
            font-size: 1.1rem;
        }
        .btn-confirm:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1 class="invoice-title">Invoice #<?php echo $appointment_id; ?></h1>
            <p class="text-muted">Sky Smoke Check LLC</p>
        </div>
        
        <div class="pdf-container">
            <iframe src="../temp/Quotation_<?php echo $appointment_id; ?>.pdf" type="application/pdf"></iframe>
        </div>

        <div class="button-container">
            <form method="POST" action="">
                <button type="submit" name="confirm" class="btn btn-confirm">
                    <i class="fas fa-check-circle me-2"></i> Confirm Invoice
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 