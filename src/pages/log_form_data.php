<?php
// Set headers to allow JSON response
header('Content-Type: application/json');

try {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if ($data === null) {
        throw new Exception('Invalid JSON data received');
    }

    // Format the log message
    $logMessage = "\n\n=== FORM DATA BEING SENT TO CREATE_ACCOUNT.PHP ===\n";
    $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $logMessage .= "Data:\n";
    $logMessage .= print_r($data, true);
    $logMessage .= "\n=== END OF FORM DATA ===\n";

    // Write to error log
    error_log($logMessage, 3, './logs/php_errors.log');

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Form data logged successfully'
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in log_form_data.php: " . $e->getMessage(), 3, './logs/php_errors.log');
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 