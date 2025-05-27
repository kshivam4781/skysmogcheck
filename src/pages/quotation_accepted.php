<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Accepted - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #28a745;
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container text-center">
            <div class="success-icon">✓</div>
            <h2 class="mb-4">Quotation Accepted Successfully</h2>
            <p class="mb-4">The quotation has been accepted and the appointment has been confirmed. A confirmation email has been sent to the customer.</p>
            <a href="admin_welcome.php" class="btn btn-primary">Return to Dashboard</a>
        </div>
    </div>
</body>
</html> 