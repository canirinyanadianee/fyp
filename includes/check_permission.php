<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header('Location: ../login.php');
    exit();
}

// Function to check if user has specific role
function has_permission($required_role) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Define role hierarchy (lower number = higher privilege)
    $role_hierarchy = [
        'admin' => 3,
        'hospital' => 2,
        'bloodbank' => 2,
        'donor' => 1
    ];
    
    $user_role = strtolower($_SESSION['role']);
    $required_level = $role_hierarchy[strtolower($required_role)] ?? 0;
    $user_level = $role_hierarchy[$user_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Function to restrict access to specific roles
function require_permission($required_role) {
    if (!has_permission($required_role)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header('Location: ../unauthorized.php');
        exit();
    }
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
}

// Function to check if user is hospital staff
function is_hospital() {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'hospital';
}

// Function to check if user is blood bank staff
function is_bloodbank() {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'bloodbank';
}

// Function to check if user is a donor
function is_donor() {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'donor';
}
?>
