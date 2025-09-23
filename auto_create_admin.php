<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "blood_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Admin credentials
$email = 'canirinyanadiane@gmail.com';
$plain_password = 'inyana2003';
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
$username = 'canirinyanadiane';

// Check if user already exists
$check_sql = "SELECT id FROM users WHERE email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$result = $check_stmt->get_result();

header('Content-Type: text/plain');

// Determine if users.is_active column exists (compat with older schemas)
$hasIsActive = false;
try {
    if ($q = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'")) {
        $hasIsActive = ($q->num_rows > 0);
        $q->close();
    }
} catch (Exception $e) {
    // ignore
}

echo "Attempting to create admin user...\n";

if ($result->num_rows > 0) {
    echo "✅ Admin user already exists with email: " . htmlspecialchars($email) . "\n";
} else {
    // Insert new admin user (with or without is_active depending on schema)
    if ($hasIsActive) {
        $insert_sql = "INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $username, $email, $hashed_password);
    } else {
        $insert_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $username, $email, $hashed_password);
    }
    
    if ($insert_stmt->execute()) {
        echo "✅ Admin user created successfully!\n";
        echo "Email: " . htmlspecialchars($email) . "\n";
        echo "Password: [hidden for security]\n";
    } else {
        echo "❌ Error creating admin user: " . $conn->error . "\n";
    }
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?>
