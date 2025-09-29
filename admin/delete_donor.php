<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: donors.php');
    exit;
}

$donorId = (int)($_POST['donor_id'] ?? 0);
if ($donorId <= 0) {
    $_SESSION['error'] = 'Invalid donor ID.';
    header('Location: donors.php');
    exit;
}

try {
    // Fetch donor and its user_id
    $donor = fetchOne("SELECT id, user_id, first_name, last_name FROM donors WHERE id = :id", [':id' => $donorId]);
    if (!$donor) {
        $_SESSION['error'] = 'Donor not found.';
        header('Location: donors.php');
        exit;
    }

    $userId = (int)$donor['user_id'];

    // Detect users.is_active
    $hasIsActive = false;
    $colStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($colStmt) {
        $hasIsActive = ($colStmt->rowCount() > 0);
    }

    // Transaction: clean dependent data, delete donor, deactivate user
    $conn->beginTransaction();

    // Remove donation rows referencing this donor (kept optional but common)
    $stmt = $conn->prepare("DELETE FROM blood_donations WHERE donor_id = :id");
    $stmt->execute([':id' => $donorId]);

    // Delete donor
    $stmt = $conn->prepare("DELETE FROM donors WHERE id = :id");
    $stmt->execute([':id' => $donorId]);

    // Deactivate user instead of hard delete
    if ($userId > 0) {
        if ($hasIsActive) {
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = :uid");
            $stmt->execute([':uid' => $userId]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = :uid");
            $stmt->execute([':uid' => $userId]);
        }
    }

    $conn->commit();
    $_SESSION['success'] = 'Donor "' . htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) . '" deleted successfully.';
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = 'Failed to delete donor: ' . $e->getMessage();
}

header('Location: donors.php');
exit;
