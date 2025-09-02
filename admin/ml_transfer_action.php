<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// require admin authentication - reuse existing admin session check if available
session_start();
// assume admin user id stored in session as admin_user_id or user_id
$admin_id = isset($_SESSION['admin_user_id']) ? $_SESSION['admin_user_id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['action'])) {
    header('Location: ml_transfer_reviews.php');
    exit;
}

$id = intval($_POST['id']);
$action = $_POST['action'];

if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE blood_transfers SET status='approved', approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=? AND status='proposed'");
    if ($stmt) {
        $stmt->bind_param('ii', $admin_id, $id);
        $stmt->execute();
    }
} else {
    $stmt = $conn->prepare("UPDATE blood_transfers SET status='rejected', updated_at=NOW() WHERE id=? AND status='proposed'");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
}

header('Location: ml_transfer_reviews.php');
exit;
