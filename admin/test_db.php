<?php
require_once '../includes/db.php';

echo "<h1>Database Connection Test</h1>";

// Test database connection
if ($conn->connect_error) {
    die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
}
echo "<p style='color:green'>✓ Database connection successful!</p>";

// Check if donors table exists
$result = $conn->query("SHOW TABLES LIKE 'donors'");
if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Donors table exists</p>";
    
    // Count donors
    $count = $conn->query("SELECT COUNT(*) as count FROM donors")->fetch_assoc();
    echo "<p>Total donors: " . $count['count'] . "</p>";
} else {
    echo "<p style='color:red'>✗ Donors table does not exist</p>";
}

$conn->close();
?>
