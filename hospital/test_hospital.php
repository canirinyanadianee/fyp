<?php
// Simple test to verify hospital dashboard loads
echo "Testing hospital dashboard...\n";

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Test the inventory query specifically
echo "Testing inventory query...\n";

$inventory_sql = "SELECT blood_type, SUM(quantity_ml) as total_ml 
                  FROM blood_inventory 
                  WHERE expiry_date > CURDATE() AND status = 'available'
                  GROUP BY blood_type 
                  ORDER BY blood_type";

echo "Query: $inventory_sql\n";

$inventory_result = $conn->query($inventory_sql);

if ($inventory_result) {
    echo "✓ Query executed successfully\n";
    echo "Rows found: " . $inventory_result->num_rows . "\n";
    
    while ($row = $inventory_result->fetch_assoc()) {
        echo "- {$row['blood_type']}: {$row['total_ml']} ml\n";
    }
} else {
    echo "✗ Query failed: " . $conn->error . "\n";
}

echo "Test completed.\n";
?>
