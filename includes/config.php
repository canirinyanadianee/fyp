<?php
// Blood Management System Configuration
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Application Settings
define('APP_NAME', 'AI-Powered Blood Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/fyp/');
define('ADMIN_EMAIL', 'admin@bloodbank.com');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'blood_management');

// System Settings
define('DEFAULT_TIMEZONE', 'America/New_York');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('DONATION_INTERVAL_DAYS', 56); // 8 weeks between donations

// Blood Types
define('BLOOD_TYPES', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);

// Blood Inventory Thresholds (in ml)
define('CRITICAL_THRESHOLD', 500);
define('LOW_THRESHOLD', 1000);
define('NORMAL_THRESHOLD', 2000);

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// AI Service Settings
define('AI_SERVICE_URL', 'http://localhost:5000');
define('AI_MONITORING_ENABLED', true);

// Security Settings
define('CSRF_TOKEN_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);

// Email Settings (for notifications)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
?>
