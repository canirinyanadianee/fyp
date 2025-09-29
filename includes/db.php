<?php
// Database connection for Blood Management System
$host = "localhost";
$username = "root";
$password = "";
$database = "blood_management";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Include common functions
require_once __DIR__ . '/functions.php';

// Ensure activity_logs table exists (lazy creation)
function ensure_activity_logs_table_exists() {
    global $conn;
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $conn->query(
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

// Function to redirect with message
function redirect($url, $message = '', $message_type = 'info') {
    if ($message) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $message_type; // success, info, warning, danger
    }
    header("Location: $url");
    exit();
}

// Function to log system activities
function log_activity($user_id, $action, $description) {
    global $conn;
    $user_id = (int)$user_id;
    $action = sanitize_input($action, $conn);
    $description = sanitize_input($description, $conn);

    // Create table if missing, then attempt insert. Swallow errors to avoid fatal UI breaks.
    ensure_activity_logs_table_exists();
    $sql = "INSERT INTO activity_logs (user_id, action, description) VALUES ($user_id, '$action', '$description')";
    if (!$conn->query($sql)) {
        // Optional: write to PHP error log
        error_log('log_activity insert failed: ' . $conn->error);
    }
}
?>
