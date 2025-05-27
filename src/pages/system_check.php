<?php
echo "<h2>System Check</h2>";

// Check PHP Version
echo "<h3>1. PHP Configuration</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PHP Extensions Loaded:<br>";
$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'session'];
foreach ($required_extensions as $ext) {
    echo "- " . $ext . ": " . (extension_loaded($ext) ? "✓ Loaded" : "✗ Not Loaded") . "<br>";
}

// Check Database Connection
echo "<h3>2. Database Connection</h3>";
try {
    require_once '../config/db_connection.php';
    if ($conn) {
        echo "✓ Database connection successful<br>";
        
        // Check if table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Users table exists<br>";
            
            // Check table structure
            $stmt = $conn->query("DESCRIBE users");
            echo "Table Structure:<br>";
            while ($row = $stmt->fetch()) {
                echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
            }
        } else {
            echo "✗ Users table does not exist<br>";
        }
    }
} catch(PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
}

// Check File Permissions
echo "<h3>3. File Permissions</h3>";
$files_to_check = [
    '../config/db_connection.php',
    'login_handler.php',
    'welcome.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✓ " . $file . " exists<br>";
        echo "Permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "<br>";
    } else {
        echo "✗ " . $file . " does not exist<br>";
    }
}

// Check Session Configuration
echo "<h3>4. Session Configuration</h3>";
echo "Session Save Path: " . session_save_path() . "<br>";
echo "Session Status: " . session_status() . "<br>";

// Check Apache Configuration
echo "<h3>5. Server Information</h3>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "PHP Handler: " . php_sapi_name() . "<br>";
?> 