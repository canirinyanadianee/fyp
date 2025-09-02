<?php
require_once 'includes/db.php';

echo "Blood Requests Data:\n";
$result = $conn->query('SELECT * FROM blood_requests');
while($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Hospital: {$row['hospital_id']}, Blood Type: {$row['blood_type']}, Status: {$row['status']}\n";
}
?>
