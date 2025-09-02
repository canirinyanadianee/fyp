<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h1>Creating Hospital System Tables</h1>";

// Create blood_usage table
$create_blood_usage = "
CREATE TABLE IF NOT EXISTS blood_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    quantity_ml INT NOT NULL,
    patient_name VARCHAR(255) NOT NULL,
    procedure_type ENUM('surgery', 'trauma', 'transfusion', 'emergency', 'routine', 'other') NOT NULL,
    usage_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($create_blood_usage) === TRUE) {
    echo "<p>✓ blood_usage table created successfully</p>";
} else {
    echo "<p>✗ Error creating blood_usage table: " . $conn->error . "</p>";
}

// Create hospital_staff table
$create_hospital_staff = "
CREATE TABLE IF NOT EXISTS hospital_staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    hire_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($create_hospital_staff) === TRUE) {
    echo "<p>✓ hospital_staff table created successfully</p>";
} else {
    echo "<p>✗ Error creating hospital_staff table: " . $conn->error . "</p>";
}

// Add missing columns to blood_banks table if they don't exist
$add_rating_column = "
ALTER TABLE blood_banks 
ADD COLUMN IF NOT EXISTS rating DECIMAL(2,1) DEFAULT 4.0
";

if ($conn->query($add_rating_column) === TRUE) {
    echo "<p>✓ Added rating column to blood_banks table</p>";
} else {
    echo "<p>✗ Error adding rating column: " . $conn->error . "</p>";
}

$add_address_column = "
ALTER TABLE blood_banks 
ADD COLUMN IF NOT EXISTS address TEXT
";

if ($conn->query($add_address_column) === TRUE) {
    echo "<p>✓ Added address column to blood_banks table</p>";
} else {
    echo "<p>✗ Error adding address column: " . $conn->error . "</p>";
}

$add_email_column = "
ALTER TABLE blood_banks 
ADD COLUMN IF NOT EXISTS email VARCHAR(255)
";

if ($conn->query($add_email_column) === TRUE) {
    echo "<p>✓ Added email column to blood_banks table</p>";
} else {
    echo "<p>✗ Error adding email column: " . $conn->error . "</p>";
}

// Insert sample blood usage data
$sample_usage_data = [
    [1, 'A+', 500, 'John Smith', 'surgery', '2024-08-20', 'Cardiac surgery procedure'],
    [1, 'O-', 300, 'Mary Johnson', 'emergency', '2024-08-21', 'Emergency trauma case'],
    [1, 'B+', 250, 'David Wilson', 'transfusion', '2024-08-22', 'Routine blood transfusion'],
    [1, 'AB+', 400, 'Sarah Davis', 'surgery', '2024-08-23', 'Orthopedic surgery'],
    [1, 'O+', 350, 'Michael Brown', 'trauma', '2024-08-24', 'Vehicle accident victim']
];

$insert_usage_sql = "INSERT INTO blood_usage (hospital_id, blood_type, quantity_ml, patient_name, procedure_type, usage_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
$usage_stmt = $conn->prepare($insert_usage_sql);

if ($usage_stmt) {
    foreach ($sample_usage_data as $data) {
        $usage_stmt->bind_param("isisss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]);
        $usage_stmt->execute();
    }
    echo "<p>✓ Sample blood usage data inserted</p>";
} else {
    echo "<p>✗ Error preparing usage data insert: " . $conn->error . "</p>";
}

// Insert sample staff data
$sample_staff_data = [
    [1, 'Dr. James Anderson', 'j.anderson@hospital.com', '+1-555-0101', 'Doctor', 'Cardiology', '2023-01-15'],
    [1, 'Nurse Jennifer Lee', 'j.lee@hospital.com', '+1-555-0102', 'Nurse', 'Emergency', '2023-03-20'],
    [1, 'Dr. Robert Chen', 'r.chen@hospital.com', '+1-555-0103', 'Doctor', 'Surgery', '2023-06-10'],
    [1, 'Lisa Martinez', 'l.martinez@hospital.com', '+1-555-0104', 'Lab Technician', 'Laboratory', '2023-09-05'],
    [1, 'Mark Thompson', 'm.thompson@hospital.com', '+1-555-0105', 'Blood Bank Manager', 'Blood Bank', '2024-01-12']
];

$insert_staff_sql = "INSERT INTO hospital_staff (hospital_id, name, email, phone, role, department, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
$staff_stmt = $conn->prepare($insert_staff_sql);

if ($staff_stmt) {
    foreach ($sample_staff_data as $data) {
        $staff_stmt->bind_param("issssss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]);
        $staff_stmt->execute();
    }
    echo "<p>✓ Sample staff data inserted</p>";
} else {
    echo "<p>✗ Error preparing staff data insert: " . $conn->error . "</p>";
}

// Update blood_banks with complete information
$update_banks_sql = "
UPDATE blood_banks SET 
    address = CASE 
        WHEN id = 1 THEN '123 Medical Center Drive, Downtown District'
        WHEN id = 2 THEN '456 Health Plaza, Medical Quarter'
        WHEN id = 3 THEN '789 Emergency Boulevard, Central Area'
        ELSE address
    END,
    email = CASE 
        WHEN id = 1 THEN 'info@centralbloodbank.org'
        WHEN id = 2 THEN 'contact@citybloodcenter.org'
        WHEN id = 3 THEN 'emergency@quickbloodbank.org'
        ELSE email
    END,
    rating = CASE 
        WHEN id = 1 THEN 4.8
        WHEN id = 2 THEN 4.5
        WHEN id = 3 THEN 4.2
        ELSE rating
    END
WHERE id IN (1, 2, 3)
";

if ($conn->query($update_banks_sql) === TRUE) {
    echo "<p>✓ Blood banks updated with complete information</p>";
} else {
    echo "<p>✗ Error updating blood banks: " . $conn->error . "</p>";
}

echo "<h2>Database Setup Complete!</h2>";
echo "<p>All tables have been created and sample data has been inserted.</p>";
echo "<p><a href='hospital/index.php'>Go to Hospital Dashboard</a></p>";
?>
