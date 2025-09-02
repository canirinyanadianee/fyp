<?php
require_once 'includes/db.php';

echo "<h2>Creating/Fixing Blood Requests Table</h2>";

// Drop and recreate the blood_requests table with correct structure
$sql = "DROP TABLE IF EXISTS blood_requests";
if ($conn->query($sql)) {
    echo "Old table dropped successfully<br>";
} else {
    echo "Error dropping table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    quantity_ml INT NOT NULL,
    urgency ENUM('routine', 'urgent', 'emergency') DEFAULT 'routine',
    patient_name VARCHAR(100),
    status ENUM('pending', 'approved', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Blood requests table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert some sample data for testing
$samples = [
    ['hospital_id' => 1, 'blood_type' => 'O+', 'quantity_ml' => 900, 'urgency' => 'urgent', 'patient_name' => 'John Doe', 'status' => 'pending'],
    ['hospital_id' => 1, 'blood_type' => 'A+', 'quantity_ml' => 450, 'urgency' => 'routine', 'patient_name' => 'Jane Smith', 'status' => 'completed'],
    ['hospital_id' => 1, 'blood_type' => 'B-', 'quantity_ml' => 450, 'urgency' => 'emergency', 'patient_name' => 'Bob Wilson', 'status' => 'approved']
];

foreach ($samples as $sample) {
    $sql = "INSERT INTO blood_requests (hospital_id, blood_type, quantity_ml, urgency, patient_name, status) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isisss", $sample['hospital_id'], $sample['blood_type'], $sample['quantity_ml'], 
                      $sample['urgency'], $sample['patient_name'], $sample['status']);
    if ($stmt->execute()) {
        echo "Sample request added: " . $sample['patient_name'] . " - " . $sample['blood_type'] . "<br>";
    } else {
        echo "Error adding sample: " . $stmt->error . "<br>";
    }
}

echo "<h3>Table structure:</h3>";
$result = $conn->query('DESCRIBE blood_requests');
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . $row['Key'] . "</td></tr>";
    }
    echo "</table>";
}

echo "<br><a href='hospital/index.php'>Go to Hospital Dashboard</a>";
?>
