<?php
require_once 'includes/db.php';

echo "<h2>Creating/Fixing Blood Banks Table</h2>";

// Check if table exists
$check = $conn->query("SHOW TABLES LIKE 'blood_banks'");
if ($check->num_rows == 0) {
    echo "Blood banks table doesn't exist. Creating it...<br>";
    
    $sql = "CREATE TABLE blood_banks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        license_number VARCHAR(100) UNIQUE NOT NULL,
        contact_phone VARCHAR(20),
        contact_email VARCHAR(100),
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        postal_code VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "Blood banks table created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
    
    // Insert some sample blood banks
    $samples = [
        ['user_id' => 2, 'name' => 'Central Blood Bank', 'license_number' => 'BB001', 'contact_phone' => '123-456-7890', 'city' => 'City Center'],
        ['user_id' => 3, 'name' => 'Regional Blood Center', 'license_number' => 'BB002', 'contact_phone' => '123-456-7891', 'city' => 'Downtown'],
        ['user_id' => 4, 'name' => 'Emergency Blood Services', 'license_number' => 'BB003', 'contact_phone' => '123-456-7892', 'city' => 'Uptown']
    ];
    
    foreach ($samples as $sample) {
        $sql = "INSERT INTO blood_banks (user_id, name, license_number, contact_phone, city) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $sample['user_id'], $sample['name'], $sample['license_number'], 
                          $sample['contact_phone'], $sample['city']);
        if ($stmt->execute()) {
            echo "Sample blood bank added: " . $sample['name'] . "<br>";
        } else {
            echo "Error adding sample: " . $stmt->error . "<br>";
        }
    }
} else {
    echo "Blood banks table already exists<br>";
}

echo "<h3>Current table structure:</h3>";
$result = $conn->query('DESCRIBE blood_banks');
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . $row['Key'] . "</td></tr>";
    }
    echo "</table>";
}

echo "<h3>Blood Banks Data:</h3>";
$result = $conn->query('SELECT * FROM blood_banks');
if ($result && $result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>License</th><th>Phone</th><th>City</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['name'] . "</td><td>" . $row['license_number'] . "</td><td>" . ($row['contact_phone'] ?? 'N/A') . "</td><td>" . ($row['city'] ?? 'N/A') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "No blood banks found<br>";
}

echo "<br><a href='hospital/requests.php'>Go to Hospital Requests</a>";
?>
