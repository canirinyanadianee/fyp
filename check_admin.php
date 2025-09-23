<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if admin user exists
$sql = "SELECT id, username, email, role, created_at FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$email = 'canirinyanadiane@gmail.com';
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/plain');

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "✅ Admin user found!\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Username: " . htmlspecialchars($user['username']) . "\n";
    echo "Email: " . htmlspecialchars($user['email']) . "\n";
    echo "Role: " . htmlspecialchars($user['role']) . "\n";
    echo "Created: " . $user['created_at'] . "\n";
} else {
    echo "❌ Admin user not found.\n";
    echo "Please run the SQL statement in phpMyAdmin to create the admin user.\n";
}

$stmt->close();
$conn->close();
?>
