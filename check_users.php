<?php
require_once 'includes/db.php';

echo "Users table structure:\n";
$result = $conn->query('DESCRIBE users');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
