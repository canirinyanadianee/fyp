<?php
require_once '../includes/db.php';

header('Content-Type: text/plain');

echo "Listing all tables in the database...\n\n";

// List all tables
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    echo "Tables in the database:\n";
    while ($row = $tables->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Error getting tables: " . $conn->error . "\n";
}

$conn->close();
?>
