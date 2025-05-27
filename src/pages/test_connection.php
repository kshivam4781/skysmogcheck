<?php
require_once '../config/db_connection.php';

try {
    if ($conn) {
        echo "Database connection successful!";
    } else {
        echo "Database connection failed!";
    }
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?> 