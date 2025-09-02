<?php
/**
 * Simple ML Tables Migration Script
 * 
 * Run this script to create the required ML tables and insert sample data.
 * Access via: http://localhost/fyp1/migrations/run_simple_migration.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain');
echo "Starting ML Tables Migration...\n\n";

try {
    // Read the SQL file
    $sqlFile = __DIR__ . '/create_ml_tables_simple.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Execute multi query
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }
    
    if ($conn->error) {
        throw new Exception("SQL Error: " . $conn->error);
    }
    
    // Verify tables were created
    $tables = ['ml_demand_predictions', 'ml_inventory_data', 'ml_blood_demand_data'];
    $missing = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        echo "✅ Successfully created all ML tables!\n";
        
        // Show sample data
        $result = $conn->query("SELECT * FROM ml_demand_predictions WHERE prediction_date = CURDATE()");
        if ($result->num_rows > 0) {
            echo "\nSample Predictions:\n";
            echo str_repeat("-", 50) . "\n";
            echo sprintf("%-5s | %-8s | %-12s | %-13s\n", "Type", "7-Day", "30-Day", "Confidence");
            echo str_repeat("-", 50) . "\n";
            
            while ($row = $result->fetch_assoc()) {
                echo sprintf("%-5s | %-8d | %-12d | %.0f%%\n",
                    $row['blood_type'],
                    $row['predicted_demand_7d'],
                    $row['predicted_demand_30d'],
                    $row['confidence'] * 100
                );
            }
        }
    } else {
        echo "❌ Error: The following tables were not created: " . implode(", ", $missing) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration complete. You can now refresh your blood bank dashboard.";
?>
