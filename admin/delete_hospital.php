<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: hospitals.php');
    exit;
}

$hospitalId = (int)($_POST['hospital_id'] ?? 0);
if ($hospitalId <= 0) {
    $_SESSION['error'] = 'Invalid hospital ID.';
    header('Location: hospitals.php');
    exit;
}

try {
    // Fetch hospital and associated user
    $hospital = fetchOne("SELECT id, user_id, name FROM hospitals WHERE id = :id", [':id' => $hospitalId]);
    if (!$hospital) {
        $_SESSION['error'] = 'Hospital not found.';
        header('Location: hospitals.php');
        exit;
    }

    $userId = (int)$hospital['user_id'];

    // Detect users.is_active
    $hasIsActive = false;
    $colStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($colStmt) {
        $hasIsActive = ($colStmt->rowCount() > 0);
    }

    // Transactional cleanup
    $conn->beginTransaction();

    // Remove dependent data (keep others if you want historical records)
    $conn->prepare("DELETE FROM blood_requests WHERE hospital_id = :hid")->execute([':hid' => $hospitalId]);
    // Optional: remove usage/transfers/inventory tables if present
    try { $conn->prepare("DELETE FROM blood_transfers WHERE hospital_id = :hid")->execute([':hid' => $hospitalId]); } catch (Exception $e) {}
    try { $conn->prepare("DELETE FROM hospital_blood_inventory WHERE hospital_id = :hid")->execute([':hid' => $hospitalId]); } catch (Exception $e) {}
    try { $conn->prepare("DELETE FROM blood_usage WHERE hospital_id = :hid")->execute([':hid' => $hospitalId]); } catch (Exception $e) {}

    // Delete hospital
    $conn->prepare("DELETE FROM hospitals WHERE id = :hid")->execute([':hid' => $hospitalId]);

    // Deactivate user (do not delete to keep auditability)
    if ($userId > 0) {
        if ($hasIsActive) {
            $conn->prepare("UPDATE users SET is_active = 0 WHERE id = :uid")->execute([':uid' => $userId]);
        } else {
            $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = :uid")->execute([':uid' => $userId]);
        }
    }

    $conn->commit();
    $_SESSION['success'] = 'Hospital "' . htmlspecialchars($hospital['name']) . '" deleted successfully.';
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = 'Failed to delete hospital: ' . $e->getMessage();
}

header('Location: hospitals.php');
exit;
