<?php
require_once '../includes/db.php';

header('Content-Type: text/plain');

echo "Checking database tables...\n\n";

// List all tables in the database
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    echo "Tables in the database:\n";
    while ($row = $tables->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
    echo "\n";
} else {
    die("Error getting tables: " . $conn->error . "\n");
}

// Check donors table
check_table_structure('donors');

// Check blood_donations table
check_table_structure('blood_donations');

function check_table_structure($tableName) {
    global $conn;
    
    echo "\nStructure of table '$tableName':\n";
    
    $result = $conn->query("SHOW COLUMNS FROM $tableName");
    if ($result) {
        while ($column = $result->fetch_assoc()) {
            echo str_pad($column['Field'], 20) . 
                 str_pad($column['Type'], 20) . 
                 ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\t" .
                 ($column['Key'] ?: '') . "\t" .
                 ($column['Default'] !== null ? "DEFAULT " . $column['Default'] : '') . "\t" .
                 $column['Extra'] . "\n";
        }
    } else {
        echo "Table '$tableName' does not exist or there was an error: " . $conn->error . "\n";
    }
}

$conn->close();
?>
