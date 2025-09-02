<?php
require_once 'includes/db.php';

echo "<h2>Fixing Blood Banks Table Structure</h2>";

// Check if blood_banks table exists
$check_table = $conn->query("SHOW TABLES LIKE 'blood_banks'");
if ($check_table->num_rows == 0) {
    echo "Blood banks table doesn't exist. Creating it...<br>";
    
    $create_sql = "CREATE TABLE blood_banks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        license_number VARCHAR(100) UNIQUE,
        contact_phone VARCHAR(20),
        contact_email VARCHAR(100),
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        postal_code VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_sql)) {
        echo "Blood banks table created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Blood banks table exists. Checking columns...<br>";
    
    // Check if contact_phone column exists
    $check_phone = $conn->query("SHOW COLUMNS FROM blood_banks LIKE 'contact_phone'");
    if ($check_phone->num_rows == 0) {
        echo "Adding contact_phone column...<br>";
        $add_phone = "ALTER TABLE blood_banks ADD COLUMN contact_phone VARCHAR(20)";
        if ($conn->query($add_phone)) {
            echo "contact_phone column added successfully<br>";
        } else {
            echo "Error adding contact_phone column: " . $conn->error . "<br>";
        }
    } else {
        echo "contact_phone column already exists<br>";
    }
    
    // Check if city column exists
    $check_city = $conn->query("SHOW COLUMNS FROM blood_banks LIKE 'city'");
    if ($check_city->num_rows == 0) {
        echo "Adding city column...<br>";
        $add_city = "ALTER TABLE blood_banks ADD COLUMN city VARCHAR(100)";
        if ($conn->query($add_city)) {
            echo "city column added successfully<br>";
        } else {
            echo "Error adding city column: " . $conn->error . "<br>";
        }
    } else {
        echo "city column already exists<br>";
    }
}

// Check current data
$count_result = $conn->query("SELECT COUNT(*) as count FROM blood_banks");
$count = $count_result->fetch_assoc()['count'];

if ($count == 0) {
    echo "<br>No blood banks found. Adding sample data...<br>";
    
    // Insert sample blood banks
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
    echo "<br>Found $count blood banks in the database<br>";
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
$result = $conn->query('SELECT id, name, license_number, contact_phone, city FROM blood_banks');
if ($result && $result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>License</th><th>Phone</th><th>City</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . ($row['license_number'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['contact_phone'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['city'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No blood banks found<br>";
}

echo "<br><strong>Table is now ready!</strong><br>";
echo "<a href='hospital/requests.php'>Test Hospital Requests Page</a>";
?>
