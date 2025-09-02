<?php
include 'includes/db.php';

echo "Database Connection Status:\n";
if ($conn) {
    echo "✓ Database connected successfully\n\n";
    
    // Check tables
    $result = $conn->query('SHOW TABLES');
    if ($result && $result->num_rows > 0) {
        echo "Tables in database:\n";
        while ($row = $result->fetch_array()) {
            echo "- " . $row[0] . "\n";
        }
        
        // Check if we have sample data
        echo "\nSample data check:\n";
        
        $user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        echo "- Users: $user_count records\n";
        
        $donor_count = $conn->query("SELECT COUNT(*) as count FROM donors")->fetch_assoc()['count'];
        echo "- Donors: $donor_count records\n";
        
        $bb_count = $conn->query("SELECT COUNT(*) as count FROM blood_banks")->fetch_assoc()['count'];
        echo "- Blood Banks: $bb_count records\n";
        
        $hospital_count = $conn->query("SELECT COUNT(*) as count FROM hospitals")->fetch_assoc()['count'];
        echo "- Hospitals: $hospital_count records\n";
        
    } else {
        echo "✗ No tables found in database\n";
        echo "Please import the database.sql file via phpMyAdmin\n";
    }
} else {
    echo "✗ Database connection failed\n";
}
?>
