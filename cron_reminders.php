<?php
// cron_reminders.php
// Run daily (e.g., via Windows Task Scheduler or cron) to queue donor reminders
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// We'll send reminders to donors who become eligible within the next N days (configurable)
$lookahead_days = 7; // send reminders for donors becoming eligible in next 7 days

$result = $conn->query("SELECT * FROM donors WHERE last_donation_date IS NOT NULL");
if (!$result) {
    echo "Failed to query donors: " . $conn->error . PHP_EOL;
    exit(1);
}

$counter = 0;
while ($donor = $result->fetch_assoc()) {
    $next = get_next_eligible_date_for_donor($donor);
    if (!$next) continue;
    $days = (int)floor((strtotime($next) - strtotime(date('Y-m-d'))) / 86400);
    if ($days <= $lookahead_days && $days >= 0) {
        $message = "Hi {$donor['first_name']}, you're eligible to donate on {$next}. Book your appointment now to help save lives.";
        if (schedule_donor_reminder($donor['id'], $message, $donor['blood_type'] ?? '', $conn, 'high')) {
            $counter++;
        }
    }
}

echo "Queued $counter reminders." . PHP_EOL;
?>
