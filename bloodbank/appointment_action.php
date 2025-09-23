<?php
// bloodbank/appointment_action.php
session_start();
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'bloodbank') {
    $_SESSION['error'] = 'Unauthorized.';
    header('Location: appointments.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
if (!$bank) {
    $_SESSION['error'] = 'Blood bank profile not found.';
    header('Location: appointments.php');
    exit();
}
$blood_bank_id = (int)$bank['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: appointments.php');
    exit();
}

$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if ($appointment_id <= 0 || !in_array($action, ['approve','reject'], true)) {
    $_SESSION['error'] = 'Invalid parameters.';
    header('Location: appointments.php');
    exit();
}

// Verify appointment belongs to this bank and is pending
$stmt = $conn->prepare("SELECT id, donor_id, blood_bank_id, status FROM appointments WHERE id = ? AND blood_bank_id = ? LIMIT 1");
$stmt->bind_param('ii', $appointment_id, $blood_bank_id);
$stmt->execute();
$res = $stmt->get_result();
$appt = $res->fetch_assoc();
$stmt->close();

if (!$appt) {
    $_SESSION['error'] = 'Appointment not found.';
    header('Location: appointments.php');
    exit();
}
if ($appt['status'] !== 'pending') {
    $_SESSION['error'] = 'Only pending appointments can be acted on.';
    header('Location: appointments.php');
    exit();
}

if ($action === 'approve') {
    $new_status = 'approved';
    $stmt2 = $conn->prepare("UPDATE appointments SET status = ?, decided_by = ?, decided_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt2->bind_param('sii', $new_status, $user_id, $appointment_id);
    $ok = $stmt2->execute();
    $stmt2->close();
    if ($ok) {
        $_SESSION['success'] = 'Appointment approved.';
    } else {
        $_SESSION['error'] = 'Failed to approve appointment.';
    }
} elseif ($action === 'reject') {
    if ($reason === '') {
        $_SESSION['error'] = 'Please provide a rejection reason.';
        header('Location: appointments.php');
        exit();
    }
    $new_status = 'rejected';
    $stmt2 = $conn->prepare("UPDATE appointments SET status = ?, decision_reason = ?, decided_by = ?, decided_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt2->bind_param('ssii', $new_status, $reason, $user_id, $appointment_id);
    $ok = $stmt2->execute();
    $stmt2->close();
    if ($ok) {
        $_SESSION['success'] = 'Appointment rejected and donor will see the reason.';
    } else {
        $_SESSION['error'] = 'Failed to reject appointment.';
    }
}

header('Location: appointments.php');
exit();
