<?php
// /c:/xampp/htdocs/fyp1/donor/cancel_appointment.php
// Cancel appointment logic (safe, minimal, uses prepared statements)

session_start();

// Require your DB config. Adjust path if your config is elsewhere.
// The main config lives in the includes folder at the project root.
require_once __DIR__ . '/../includes/config.php';

// Helper redirect + flash
function redirect_back($default = 'appointments.php') {
    $back = $_SERVER['HTTP_REFERER'] ?? $default;
    header('Location: ' . $back);
    exit;
}

// Ensure DB connection ($conn) exists. Try to create if config provided constants.
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (defined('DB_HOST') && defined('DB_USER')) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS ?? '', DB_NAME ?? '');
    } else {
        // Config missing - fail loudly
        $_SESSION['flash_error'] = 'Database not configured.';
        redirect_back();
    }
}
if ($conn->connect_error) {
    $_SESSION['flash_error'] = 'Database connection error.';
    redirect_back();
}

// Only accept POST for state-changing action
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    redirect_back();
}

// Basic auth check - adjust session key to your app
$donor_id = $_SESSION['donor_id'] ?? $_SESSION['user_id'] ?? null;
if (!$donor_id) {
    $_SESSION['flash_error'] = 'You must be logged in to cancel appointments.';
    redirect_back();
}

// CSRF token check if your app uses tokens (optional)
// if (!isset($_POST['token']) || $_POST['token'] !== ($_SESSION['token'] ?? '')) {
//     $_SESSION['flash_error'] = 'Invalid request token.';
//     redirect_back();
// }

$appt_id = 0;
if (isset($_POST['appointment_id'])) {
    $appt_id = intval($_POST['appointment_id']);
} elseif (isset($_POST['id'])) {
    $appt_id = intval($_POST['id']);
}
$reason  = isset($_POST['reason']) ? trim($_POST['reason']) : null;

if ($appt_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid appointment id.';
    redirect_back();
}

// Verify appointment belongs to this donor and is cancellable
$sql = "SELECT id, donor_id, status, appointment_date FROM appointments WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['flash_error'] = 'Database error.';
    redirect_back();
}
$stmt->bind_param('i', $appt_id);
$stmt->execute();
$res = $stmt->get_result();
$appointment = $res->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['flash_error'] = 'Appointment not found.';
    redirect_back();
}
if ((int)$appointment['donor_id'] !== (int)$donor_id) {
    $_SESSION['flash_error'] = 'You do not have permission to cancel this appointment.';
    redirect_back();
}
if (in_array(strtolower($appointment['status']), ['cancelled','completed','attended'])) {
    $_SESSION['flash_error'] = 'Appointment cannot be cancelled.';
    redirect_back();
}

// Perform cancellation
$updateSql = "UPDATE appointments SET status = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?";
$stm2 = $conn->prepare($updateSql);
if (!$stm2) {
    $_SESSION['flash_error'] = 'Database error (update).';
    redirect_back();
}
$newStatus = 'cancelled';
$stm2->bind_param('ssi', $newStatus, $reason, $appt_id);
$ok = $stm2->execute();
$stm2->close();

if (!$ok) {
    $_SESSION['flash_error'] = 'Failed to cancel appointment. Try again later.';
    redirect_back();
}

// Also attempt to update a matching pending blood_donations record (best-effort)
try {
    if (!empty($appointment['appointment_date'])) {
        $upd = $conn->prepare("UPDATE blood_donations SET status = ? WHERE donor_id = ? AND donation_date = ? AND status = ? LIMIT 1");
        if ($upd) {
            $cancelled = 'cancelled';
            $pending = 'pending';
            $donation_date = $appointment['appointment_date'];
            $upd->bind_param('siss', $cancelled, $appointment['donor_id'], $donation_date, $pending);
            // execute but don't treat failure as fatal for appointment cancellation
            @$upd->execute();
            $upd->close();
        }
    }
} catch (Exception $e) {
    // ignore donation update errors
}

// Optionally: send notification to admin/clinic here (not implemented)

// Success flash and redirect back
$_SESSION['flash_success'] = 'Appointment cancelled successfully.';
redirect_back();