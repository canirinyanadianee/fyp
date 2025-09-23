<?php
// bloodbank/send_notifications.php
session_start();
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'bloodbank') {
    $_SESSION['error'] = 'Unauthorized.';
    header('Location: alerts.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
if (!$bank) {
    $_SESSION['error'] = 'Blood bank profile not found.';
    header('Location: alerts.php');
    exit();
}
$blood_bank_id = (int)$bank['id'];
$bank_city = trim($bank['city'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: alerts.php');
    exit();
}

$blood_type = $_POST['blood_type'] ?? '';
$strategy = $_POST['strategy'] ?? 'city'; // 'city' or 'all'

$valid_types = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
if (!in_array($blood_type, $valid_types, true)) {
    $_SESSION['error'] = 'Invalid blood type.';
    header('Location: alerts.php');
    exit();
}

// Select matching donors
if ($strategy === 'city' && $bank_city !== '') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, city FROM donors WHERE blood_type = ? AND city = ?");
    $stmt->bind_param('ss', $blood_type, $bank_city);
} else {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, city FROM donors WHERE blood_type = ?");
    $stmt->bind_param('s', $blood_type);
}

$stmt->execute();
$res = $stmt->get_result();
$targets = [];
while ($d = $res->fetch_assoc()) { $targets[] = $d; }
$stmt->close();

if (empty($targets)) {
    $_SESSION['error'] = 'No matching donors found for notification.';
    header('Location: alerts.php');
    exit();
}

// Insert notifications (default as pending for admin review)
$message = sprintf('Urgent need for %s blood at %s. Please consider donating or booking an appointment today.', $blood_type, $bank['name'] ?? 'our blood bank');
$urgency = 'high';

// Try with status column first; fallback to legacy insert if column not present
$ins = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, status, created_at, read_status, action_taken) VALUES ('donor', ?, ?, ?, ?, 'pending', NOW(), 0, 0)");
if (!$ins) {
    // fallback (older schema without status)
    $ins = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, created_at, read_status, action_taken) VALUES ('donor', ?, ?, ?, ?, NOW(), 0, 0)");
}

if ($ins) {
    $count = 0;
    foreach ($targets as $t) {
        $entity_id = (int)$t['id'];
        $msg = $message;
        $bt = $blood_type;
        $urg = $urgency;
        // bind dynamically based on number of params
        if ($ins->param_count === 4) {
            $ins->bind_param('isss', $entity_id, $msg, $bt, $urg);
        } else {
            // status included already in SQL; same bind layout
            $ins->bind_param('isss', $entity_id, $msg, $bt, $urg);
        }
        if ($ins->execute()) { $count++; }
    }
    $ins->close();
} else {
    $_SESSION['error'] = 'Notification insert failed: ' . $conn->error;
    header('Location: alerts.php');
    exit();
}

$_SESSION['success'] = "Notifications sent to $count donor(s) with blood type $blood_type" . ($strategy==='city' && $bank_city ? " in $bank_city." : '.');

// Optionally: integrate with a push service here (FCM/OneSignal). For now, we store in ai_notifications and donors see them at donor/notifications.php

header('Location: alerts.php');
exit();
