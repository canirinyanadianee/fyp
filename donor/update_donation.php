<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donation_id = intval($_POST['donation_id'] ?? 0);
    $quantity_ml = intval($_POST['quantity_ml'] ?? 0);
    if ($donation_id > 0 && $quantity_ml > 0) {
        $stmt = $conn->prepare("UPDATE blood_donations SET quantity_ml = ?, status = 'completed' WHERE id = ?");
        $stmt->bind_param("ii", $quantity_ml, $donation_id);
        $stmt->execute();
        $stmt->close();
    }
}
header('Location: donation_history.php');
exit();