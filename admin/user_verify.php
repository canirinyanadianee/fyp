<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect('user_management.php', 'Invalid user ID.', 'danger');
}

// Detect schema: is_active vs status
$hasIsActive = false;
if ($result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'")) {
    $hasIsActive = ($result->num_rows > 0);
    $result->free();
}

if ($hasIsActive) {
    $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->bind_param('i', $id);
}

if ($stmt && $stmt->execute()) {
    log_activity($_SESSION['user_id'] ?? 0, 'verify_user', 'Verified user ID ' . $id);
    redirect('user_management.php', 'User verified successfully.', 'success');
} else {
    $err = $conn->error;
    redirect('user_management.php', 'Failed to verify user: ' . $err, 'danger');
}
