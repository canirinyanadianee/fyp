<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "Testing database connection...\n";
echo "Database Host: " . DB_HOST . "\n";
echo "Database Name: " . DB_NAME . "\n";
echo "Database User: " . DB_USER . "\n";

if ($conn) {
    echo "Database connection successful!\n";
    
    // Test if tables exist
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "Tables in database:\n";
        while ($row = $result->fetch_array()) {
            echo "- " . $row[0] . "\n";
        }
    }
} else {
    echo "Database connection failed!\n";
}
?>
