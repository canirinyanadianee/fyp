<?php
// Database configuration
require_once 'includes/config.php';

// Create connection without database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db(DB_NAME);

// Import main database schema
$sql = file_get_contents('database.sql');
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Main schema imported successfully<br>";
} else {
    echo "Error importing main schema: " . $conn->error . "<br>";
}

// Import migrations
$migrations = [
    '20250901_create_ai_tables.sql',
    '20250918_add_blood_bank_features.sql',
    'fix_missing_tables.sql'
];

foreach ($migrations as $migration) {
    $path = 'migrations/' . $migration;
    if (file_exists($path)) {
        $sql = file_get_contents($path);
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
            echo "Applied migration: $migration<br>";
        } else {
            echo "Error applying migration $migration: " . $conn->error . "<br>";
        }
    } else {
        echo "Migration file not found: $migration<br>";
    }
}

echo "<br>Database setup completed. <a href='bloodbank/'>Go to Blood Bank Dashboard</a>";

$conn->close();
?>
