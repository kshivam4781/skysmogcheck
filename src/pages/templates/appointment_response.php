<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Response - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .response-container {
            max-width: 600px;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .response-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        .accept-icon {
            color: #2ecc71;
        }
        .deny-icon {
            color: #e74c3c;
        }
        .response-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #2c3e50;
        }
        .btn-home {
            padding: 0.5rem 2rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="response-container">
        <i class="fas <?php echo $action === 'accept' ? 'fa-check-circle' : 'fa-times-circle'; ?> response-icon <?php echo $icon_class; ?>"></i>
        <div class="response-message">
            <?php echo $message; ?>
        </div>
        <a href="index.php" class="btn btn-primary btn-home">Return to Home</a>
    </div>
</body>
</html> 