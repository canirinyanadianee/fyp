<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /fyp1/login.php');
        exit();
    }
}

// Check if user has admin role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Require admin access
function requireAdminAccess() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /fyp1/unauthorized.php');
        exit();
    }
}
?>
