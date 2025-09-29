<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: bloodbank.php');
    exit;
}

$bankId = (int)($_POST['bloodbank_id'] ?? 0);
if ($bankId <= 0) {
    $_SESSION['error'] = 'Invalid blood bank ID.';
    header('Location: bloodbank.php');
    exit;
}

try {
    // Fetch blood bank and associated user
    $bank = fetchOne("SELECT id, user_id, name FROM blood_banks WHERE id = :id", [':id' => $bankId]);
    if (!$bank) {
        $_SESSION['error'] = 'Blood bank not found.';
        header('Location: bloodbank.php');
        exit;
    }

    $userId = (int)$bank['user_id'];

    // Detect users.is_active presence
    $hasIsActive = false;
    $colStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($colStmt) {
        $hasIsActive = ($colStmt->rowCount() > 0);
    }

    // Transactional delete/deactivate
    $conn->beginTransaction();

    // Remove inventory tied to this blood bank
    $stmt = $conn->prepare("DELETE FROM blood_inventory WHERE blood_bank_id = :id");
    $stmt->execute([':id' => $bankId]);

    // Optional cleanups (keep data history if desired). Uncomment if needed.
    // $conn->prepare("DELETE FROM blood_donations WHERE blood_bank_id = :id")->execute([':id' => $bankId]);
    // $conn->prepare("DELETE FROM blood_transfers WHERE blood_bank_id = :id")->execute([':id' => $bankId]);

    // Delete blood bank record
    $stmt = $conn->prepare("DELETE FROM blood_banks WHERE id = :id");
    $stmt->execute([':id' => $bankId]);

    // Deactivate associated user (do not delete account to preserve referential integrity)
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
    $_SESSION['success'] = 'Blood bank "' . htmlspecialchars($bank['name']) . '" deleted successfully.';
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = 'Failed to delete blood bank: ' . $e->getMessage();
}

header('Location: bloodbank.php');
exit;
