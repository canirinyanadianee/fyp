<?php
require_once '../includes/db.php';

header('Content-Type: text/plain');
echo "Checking donors table structure...\n\n";

// Check if donors table exists
$result = $conn->query("SHOW TABLES LIKE 'donors'");
if ($result->num_rows === 0) {
    die("Error: The 'donors' table does not exist in the database.\n");
}

echo "Donors table exists. Checking columns...\n\n";

// Get column information
$columns = $conn->query("SHOW COLUMNS FROM donors");
if ($columns) {
    echo "Columns in 'donors' table:\n";
    echo str_pad("Field", 20) . str_pad("Type", 20) . "Null\tKey\tDefault\tExtra\n";
    echo str_repeat("-", 80) . "\n";
    
    while ($col = $columns->fetch_assoc()) {
        echo str_pad($col['Field'], 20) . 
             str_pad($col['Type'], 20) . 
             $col['Null'] . "\t" . 
             ($col['Key'] ?: '\t') . "\t" . 
             ($col['Default'] !== null ? $col['Default'] : 'NULL') . "\t" . 
             $col['Extra'] . "\n";
    }
} else {
    echo "Error getting column information: " . $conn->error . "\n";
}

// Check for any active status columns
$result = $conn->query("SHOW COLUMNS FROM donors WHERE Field LIKE '%active%' OR Field LIKE '%status%'");
if ($result->num_rows > 0) {
    echo "\nActive/Status related columns found:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "\nNo active/status related columns found.\n";
}

$conn->close();
?>
