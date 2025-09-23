<?php
// bloodbank/request_action.php
session_start();
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'bloodbank') {
    $_SESSION['error'] = 'Unauthorized.';
    header('Location: requests_inbox.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
if (!$bank) {
    $_SESSION['error'] = 'Blood bank profile not found.';
    header('Location: requests_inbox.php');
    exit();
}
$blood_bank_id = (int)$bank['id'];
$bank_name = $bank['name'] ?? 'Blood Bank';
$bank_city = trim($bank['city'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: requests_inbox.php');
    exit();
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if ($request_id <= 0 || !in_array($action, ['approve','reject'], true)) {
    $_SESSION['error'] = 'Invalid parameters.';
    header('Location: requests_inbox.php');
    exit();
}

// Fetch the request and target hospital
$stmt = $conn->prepare("SELECT br.*, h.name AS hospital_name FROM blood_requests br JOIN hospitals h ON br.hospital_id = h.id WHERE br.id = ? LIMIT 1");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    $_SESSION['error'] = 'Hospital request not found.';
    header('Location: requests_inbox.php');
    exit();
}

if ($req['status'] !== 'pending') {
    $_SESSION['error'] = 'Only pending requests can be acted on.';
    header('Location: requests_inbox.php');
    exit();
}

$hospital_id = (int)$req['hospital_id'];
$blood_type = $req['blood_type'];
$requested_qty = (int)$req['quantity_ml'];

if ($action === 'reject') {
    if ($reason === '') {
        $_SESSION['error'] = 'Please provide a rejection reason.';
        header('Location: requests_inbox.php');
        exit();
    }
    $notes = trim((string)($req['notes'] ?? ''));
    $notes .= ($notes ? "\n" : '') . 'Rejected by ' . $bank_name . ' on ' . date('Y-m-d H:i') . '. Reason: ' . $reason;
    $upd = $conn->prepare("UPDATE blood_requests SET status = 'rejected', notes = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param('si', $notes, $request_id);
    if ($upd->execute()) {
        // Notify hospital
        if ($n = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, created_at, read_status, action_taken) VALUES ('hospital', ?, ?, ?, 'low', NOW(), 0, 0)")) {
            $msg = sprintf('%s rejected your request for %s (%d ml). Reason: %s', $bank_name, $blood_type, $requested_qty, $reason);
            $n->bind_param('iss', $hospital_id, $msg, $blood_type);
            $n->execute();
        }
        $_SESSION['success'] = 'Request rejected.';
    } else {
        $_SESSION['error'] = 'Failed to reject request.';
    }
    header('Location: requests_inbox.php');
    exit();
}

// Approve path
// Optionally check available inventory for this bank and blood type
$avail_qty = 0;
if ($q = $conn->prepare("SELECT COALESCE(SUM(quantity_ml),0) AS qty FROM blood_inventory WHERE blood_bank_id = ? AND blood_type = ? AND status = 'available'")) {
    $q->bind_param('is', $blood_bank_id, $blood_type);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $avail_qty = (int)($res['qty'] ?? 0);
}

$fulfill_qty = min($requested_qty, max($avail_qty, 0));
if ($fulfill_qty <= 0) {
    // Allow approval but warn partial (0). You may prefer to block instead.
    $fulfill_qty = 0;
}

$conn->begin_transaction();
try {
    // Update request to approved
    $notes = trim((string)($req['notes'] ?? ''));
    $notes .= ($notes ? "\n" : '') . 'Approved by ' . $bank_name . ' on ' . date('Y-m-d H:i') . '. Planned fulfillment: ' . $fulfill_qty . ' ml (requested ' . $requested_qty . ' ml).';
    $u1 = $conn->prepare("UPDATE blood_requests SET status = 'approved', notes = ?, updated_at = NOW() WHERE id = ?");
    $u1->bind_param('si', $notes, $request_id);
    if (!$u1->execute()) throw new Exception('Failed to update request');

    // Create transfer proposal/approval
    $status = ($fulfill_qty > 0) ? 'approved' : 'proposed';
    $req_type = 'hospital_request';
    $transfer_date = date('Y-m-d');
    $tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by, created_at) VALUES (?,?,?,?,?,?,?,?, 'requests_inbox', ?, NOW())");
    if (!$tr) throw new Exception('Failed to prepare transfer insert');
    $t_notes = 'Auto-generated from hospital request #' . $request_id . ' by ' . $bank_name . ' (' . $bank_city . ').';
    $proposed_by = 'bank_user_' . $user_id;
    $tr->bind_param('iisisisss', $blood_bank_id, $hospital_id, $blood_type, $fulfill_qty, $req_type, $status, $transfer_date, $t_notes, $proposed_by);
    if (!$tr->execute()) throw new Exception('Failed to create transfer');

    $conn->commit();

    // Notify hospital
    if ($n = $conn->prepare("INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, created_at, read_status, action_taken) VALUES ('hospital', ?, ?, ?, ?, NOW(), 0, 0)")) {
        $urg = ($req['urgency'] === 'emergency') ? 'high' : (($req['urgency'] === 'urgent') ? 'medium' : 'low');
        $msg = sprintf('%s approved your request for %s. Planned fulfillment: %d ml.', $bank_name, $blood_type, $fulfill_qty);
        $n->bind_param('isss', $hospital_id, $msg, $blood_type, $urg);
        $n->execute();
    }

    $_SESSION['success'] = 'Request approved successfully.';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Approval failed: ' . $e->getMessage();
}

header('Location: requests_inbox.php');
exit();
