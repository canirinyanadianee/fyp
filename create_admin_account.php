<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Admin credentials
$email = 'canirinyanadiane@gmail.com';
$password = 'inyana2003@';
$username = explode('@', $email)[0]; // Use the part before @ as username

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Admin user already exists with email: $email\n";
        exit;
    }

    // Insert new admin user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->bind_param("sss", $username, $email, $hashedPassword);
    
    if ($stmt->execute()) {
        echo "Admin account created successfully!\n";
        echo "Username: $email\n";
        echo "Password: $password\n";
        echo "Role: admin\n";
        echo "IMPORTANT: Please delete this file after use for security reasons!\n";
    } else {
        echo "Error creating admin account: " . $conn->error . "\n";
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
