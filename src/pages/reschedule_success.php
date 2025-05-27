<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Request Sent - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .success-container {
            max-width: 800px;
            margin: 4rem auto;
            padding: 2rem;
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            color: #f39c12;
            margin-bottom: 1.5rem;
        }

        .appointment-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }

        .detail-row {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }
    </style>
</head>
<body>
    <main class="success-container">
        <div class="success-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h1>Reschedule Request Sent!</h1>
        <p class="lead">The customer has been notified of the proposed changes and will respond shortly.</p>
        
        <div class="appointment-details">
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span>Waiting for customer approval</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Next Steps:</span>
                <span>The customer will receive an email with the proposed changes and can either accept them or request a different time.</span>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="calendar.php" class="btn btn-primary">Return to Calendar</a>
        </div>
    </main>
</body>
</html> 