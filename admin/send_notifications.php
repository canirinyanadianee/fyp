<?php
// admin/send_notifications.php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // provides $conn (PDO)

// Basic CSRF guard could be added here if tokens are in forms

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = 'Invalid request method.';
        header('Location: donors.php');
        exit();
    }

    $rawIds = trim($_POST['donor_ids'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $sendEmail = isset($_POST['send_email']);
    $sendSms = isset($_POST['send_sms']);

    if ($rawIds === '' || $message === '') {
        $_SESSION['error'] = 'Please select at least one donor and provide a message.';
        header('Location: donors.php');
        exit();
    }

    // Parse donor IDs
    $ids = array_filter(array_map(function($v){
        $n = (int)trim($v);
        return $n > 0 ? $n : null;
    }, explode(',', $rawIds)));
    $ids = array_values(array_unique($ids));

    if (count($ids) === 0) {
        $_SESSION['error'] = 'No valid donor IDs provided.';
        header('Location: donors.php');
        exit();
    }

    // Detect if ai_notifications.status exists (schema compatibility)
    $hasStatus = false;
    try {
        $stmtCols = $conn->query("SHOW COLUMNS FROM ai_notifications LIKE 'status'");
        if ($stmtCols) {
            $hasStatus = ($stmtCols->rowCount() > 0);
        }
    } catch (Exception $e) {
        $hasStatus = false;
    }

    // Prepare insert statement
    if ($hasStatus) {
        $sql = "INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, status, created_at, read_status, action_taken) 
                VALUES ('donor', :entity_id, :message, NULL, :urgency, 'pending', NOW(), 0, 0)";
    } else {
        $sql = "INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, urgency_level, created_at, read_status, action_taken) 
                VALUES ('donor', :entity_id, :message, NULL, :urgency, NOW(), 0, 0)";
    }

    $stmt = $conn->prepare($sql);

    // Decide urgency based on simple keyword check in subject/message
    $urgency = 'medium';
    $text = strtolower($subject . ' ' . $message);
    if (strpos($text, 'urgent') !== false || strpos($text, 'immediate') !== false || strpos($text, 'critical') !== false) {
        $urgency = 'high';
    } elseif (strpos($text, 'reminder') !== false || strpos($text, 'please') !== false) {
        $urgency = 'low';
    }

    $inserted = 0;
    foreach ($ids as $id) {
        $stmt->execute([
            ':entity_id' => $id,
            ':message' => $message,
            ':urgency' => $urgency,
        ]);
        $inserted++;
    }

    // Optionally send emails immediately
    $emailsSent = 0;
    $emailsTried = 0;
    if ($sendEmail) {
        // Fetch recipient emails
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sqlEmails = "SELECT u.email, d.first_name, d.last_name
                      FROM donors d
                      JOIN users u ON d.user_id = u.id
                      WHERE d.id IN ($placeholders)";
        $stmtEmails = $conn->prepare($sqlEmails);
        $stmtEmails->execute($ids);
        $recipients = $stmtEmails->fetchAll();

        // Build email content
        $emailSubject = $subject !== '' ? $subject : (APP_NAME . ' Notification');
        $htmlBody  = '<div style="font-family: Arial, sans-serif; line-height:1.6;">';
        $htmlBody .= '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
        $htmlBody .= '<hr><small>This message was sent by ' . htmlspecialchars(APP_NAME) . '.</small>';
        $htmlBody .= '</div>';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'no-reply@localhost') . "\r\n";

        foreach ($recipients as $r) {
            $to = $r['email'] ?? '';
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $emailsTried++;
                if (@mail($to, $emailSubject, $htmlBody, $headers)) {
                    $emailsSent++;
                }
            }
        }
    }

    // Placeholder for SMS integration
    if ($sendSms) {
        // In production, integrate with an SMS provider (e.g., Twilio). Left as a no-op.
    }

    $extra = '';
    if ($sendEmail) {
        $extra = " Emails sent: {$emailsSent}/{$emailsTried}.";
    }
    $_SESSION['success'] = "Notifications queued for admin review: {$inserted} recipient(s)." . $extra;
    header('Location: donors.php');
    exit();

} catch (Exception $ex) {
    error_log('[send_notifications] ' . $ex->getMessage());
    $_SESSION['error'] = 'Failed to send notifications: ' . $ex->getMessage();
    header('Location: donors.php');
    exit();
}
