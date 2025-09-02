<?php
require_once 'includes/db.php';

echo "Blood Inventory Table Structure:\n";
$result = $conn->query('DESCRIBE blood_inventory');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\nSample data:\n";
$sample = $conn->query('SELECT * FROM blood_inventory LIMIT 3');
if ($sample && $sample->num_rows > 0) {
    while($row = $sample->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No data or error: " . $conn->error . "\n";
}
?>
