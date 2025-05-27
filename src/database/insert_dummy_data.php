<?php
require_once '../config/db_connection.php';

// Read the SQL file
$sql = file_get_contents('dummy_news.sql');

// Execute the SQL
if ($conn->multi_query($sql)) {
    echo "Dummy data inserted successfully!\n";
} else {
    echo "Error inserting dummy data: " . $conn->error . "\n";
}

// Close the connection
$conn->close();
?> 