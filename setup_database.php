<?php
// Database setup script
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "Setting up database...\n";

// Create tables
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'bloodbank', 'hospital', 'donor') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blood_banks (
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
);

CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    registration_number VARCHAR(100) UNIQUE NOT NULL,
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    date_of_birth DATE,
    last_donation_date DATE,
    donation_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blood_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    blood_bank_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    quantity_ml INT NOT NULL,
    donation_date DATE NOT NULL,
    health_notes TEXT,
    status ENUM('pending', 'approved', 'rejected', 'collected', 'processed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_bank_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    quantity_ml INT NOT NULL,
    collection_date DATE,
    expiry_date DATE NOT NULL,
    status ENUM('available', 'reserved', 'expired', 'used', 'discarded') DEFAULT 'available',
    donor_id INT,
    donation_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    blood_bank_id INT,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    quantity_ml INT NOT NULL,
    urgency ENUM('routine', 'urgent', 'emergency') DEFAULT 'routine',
    patient_name VARCHAR(100),
    requested_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'fulfilled', 'rejected', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
";

// Execute table creation
$queries = explode(';', $sql);
foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        if ($conn->query($query)) {
            echo "Table created successfully\n";
        } else {
            echo "Error creating table: " . $conn->error . "\n";
        }
    }
}

// Insert sample data
$password = password_hash('admin123', PASSWORD_DEFAULT);

// Insert admin user
$conn->query("INSERT IGNORE INTO users (username, email, password, role) VALUES 
    ('admin', 'admin@bloodbank.com', '$password', 'admin')");

// Insert sample hospital user
$conn->query("INSERT IGNORE INTO users (username, email, password, role) VALUES 
    ('hospital1', 'hospital@example.com', '$password', 'hospital')");

// Insert sample blood bank user
$conn->query("INSERT IGNORE INTO users (username, email, password, role) VALUES 
    ('bloodbank1', 'bloodbank@example.com', '$password', 'bloodbank')");

// Get user IDs
$hospital_user = $conn->query("SELECT id FROM users WHERE username = 'hospital1'")->fetch_assoc();
$bloodbank_user = $conn->query("SELECT id FROM users WHERE username = 'bloodbank1'")->fetch_assoc();

if ($hospital_user && $bloodbank_user) {
    // Insert sample hospital
    $conn->query("INSERT IGNORE INTO hospitals (user_id, name, registration_number, contact_phone, contact_email, address, city, state) VALUES 
        ('{$hospital_user['id']}', 'General Hospital', 'HOS001', '123-456-7890', 'hospital@example.com', '123 Hospital St', 'Medical City', 'State')");

    // Insert sample blood bank
    $conn->query("INSERT IGNORE INTO blood_banks (user_id, name, license_number, contact_phone, contact_email, address, city, state) VALUES 
        ('{$bloodbank_user['id']}', 'Central Blood Bank', 'BB001', '123-456-7891', 'bloodbank@example.com', '456 Blood St', 'Medical City', 'State')");

    // Insert sample donors
    $conn->query("INSERT IGNORE INTO donors (first_name, last_name, email, phone, blood_type, date_of_birth) VALUES 
        ('John', 'Doe', 'john@example.com', '123-456-7892', 'O+', '1990-01-01'),
        ('Jane', 'Smith', 'jane@example.com', '123-456-7893', 'A+', '1985-05-15'),
        ('Bob', 'Johnson', 'bob@example.com', '123-456-7894', 'B+', '1992-03-20')");

    echo "Sample data inserted successfully\n";
}

echo "Database setup completed!\n";
echo "Login credentials:\n";
echo "Admin: admin / admin123\n";
echo "Hospital: hospital1 / admin123\n";
echo "Blood Bank: bloodbank1 / admin123\n";
?>
