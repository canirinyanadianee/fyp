<?php
require_once 'includes/db_connect.php';

// Start session and simulate a logged-in user
session_start();
$_SESSION['user_id'] = 1; // Assuming user ID 1 is a blood bank user

// Get database connection
require_once 'includes/db_connect.php';

// Query to check blood banks
$query = "SELECT * FROM blood_banks WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$bloodBank = $stmt->fetch(PDO::FETCH_ASSOC);

// Output results
echo "<h2>Debug Information</h2>";
echo "<p>Session User ID: " . htmlspecialchars($_SESSION['user_id']) . "</p>";

echo "<h3>Blood Bank Information</h3>";
if ($bloodBank) {
    echo "<pre>";
    print_r($bloodBank);
    echo "</pre>";
} else {
    echo "<p>No blood bank found for user ID: " . htmlspecialchars($_SESSION['user_id']) . "</p>";
    
    // Show all blood banks for debugging
    echo "<h3>All Blood Banks in Database</h3>";
    $allBanks = $pdo->query("SELECT * FROM blood_banks")->fetchAll(PDO::FETCH_ASSOC);
    if (count($allBanks) > 0) {
        echo "<pre>";
        print_r($allBanks);
        echo "</pre>";
    } else {
        echo "<p>No blood banks found in the database.</p>";
    }
}

// Show all users for reference
echo "<h3>All Users (for reference)</h3>";
$users = $pdo->query("SELECT id, username, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
if (count($users) > 0) {
    echo "<table border='1'>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found in the database.</p>";
}
?>
