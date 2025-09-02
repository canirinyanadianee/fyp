<?php
/**
 * Run AI/ML Database Migrations
 * 
 * This script executes the SQL commands in create_ml_tables.sql
 * to set up the required tables for the ML features.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Check if running from command line or web
$isCli = php_sapi_name() === 'cli';

function log_message($message) {
    global $isCli;
    $timestamp = date('Y-m-d H:i:s');
    if ($isCli) {
        echo "[$timestamp] $message\n";
    } else {
        echo "<p>[$timestamp] $message</p>\n";
    }
}

// Start output buffering for web
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>\n";
}

try {
    log_message("Starting AI/ML database migration...");
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/create_ml_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split into individual queries
    $queries = array_filter(
        array_map('trim', 
            preg_split("/;\s*(?=CREATE|INSERT|UPDATE|DELETE|ALTER|DROP)/i", $sql)
        )
    );
    
    // Execute each query
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($queries as $query) {
        if (empty(trim($query))) {
            continue;
        }
        
        try {
            $result = $conn->query($query);
            if ($result === false) {
                throw new Exception($conn->error);
            }
            log_message(" Executed: " . substr($query, 0, 100) . (strlen($query) > 100 ? '...' : ''));
            $successCount++;
        } catch (Exception $e) {
            // Ignore "table already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                $errorCount++;
                $errorMsg = " Error in query: " . $e->getMessage();
                $errors[] = $errorMsg;
                log_message($errorMsg);
            } else {
                log_message(" " . $e->getMessage());
            }
        }
    }
    
    // Verify tables were created
    $requiredTables = [
        'ml_blood_demand_data',
        'ml_inventory_data',
        'ml_demand_predictions',
        'ml_expiry_predictions',
        'ml_models'
    ];
    
    $missingTables = [];
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missingTables[] = $table;
        }
    }
    
    // Output summary
    log_message("\nMigration Summary:");
    log_message("----------------");
    log_message("Successful queries: $successCount");
    log_message("Errors: $errorCount");
    
    if (!empty($missingTables)) {
        log_message("\nWARNING: The following required tables are missing: " . implode(', ', $missingTables));
    } else {
        log_message("\nAll required tables were created successfully!");
    }
    
    if (!empty($errors)) {
        log_message("\nError details:" . implode("\n", array_slice($errors, 0, 5)));
        if (count($errors) > 5) {
            log_message("... and " . (count($errors) - 5) . " more errors");
        }
    }
    
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    if (!$isCli) {
        echo "</pre>";
    }
    exit(1);
}

if (!$isCli) {
    echo "</pre>";
}

exit(0);
