<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// Auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}

$errors = [];
$success = '';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid appointment ID.';
    header('Location: appointments.php');
    exit();
}

// Load appointment
$stmt = $conn->prepare("SELECT a.*, b.name AS blood_bank FROM appointments a LEFT JOIN blood_banks b ON a.blood_bank_id=b.id WHERE a.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$appointment = $res->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['flash_error'] = 'Appointment not found.';
    header('Location: appointments.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
$donor_id = $donor ? $donor['id'] : 0;
if ((int)$appointment['donor_id'] !== (int)$donor_id) {
    $_SESSION['flash_error'] = 'You do not have permission to edit this appointment.';
    header('Location: appointments.php');
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    if (empty($date) || empty($time)) {
        $errors[] = 'Date and time are required.';
    } else {
        $new_datetime = $date . ' ' . $time;
        $up = $conn->prepare("UPDATE appointments SET appointment_date = ?, updated_at = NOW() WHERE id = ?");
        $up->bind_param('si', $new_datetime, $id);
        if ($up->execute()) {
            // Update linked pending blood_donations if present (match by donor_id and donation_date approx)
            $upd2 = $conn->prepare("UPDATE blood_donations SET donation_date = ? WHERE donor_id = ? AND donation_date = ? LIMIT 1");
            $old_date = $appointment['appointment_date'];
            $upd2->bind_param('sis', $new_datetime, $donor_id, $old_date);
            $upd2->execute();
            $upd2->close();

            $up->close();
            $success = 'Appointment rescheduled successfully.';
            // refresh appointment data
            $appointment['appointment_date'] = $new_datetime;
        } else {
            $errors[] = 'Failed to update appointment. Try again.';
        }
    }
}

require_once '../includes/donor_header.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reschedule Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius:.75rem }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="mb-3">Reschedule Appointment</h4>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Time</label>
                        <input type="time" name="time" class="form-control" value="<?php echo date('H:i', strtotime($appointment['appointment_date'])); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Blood Bank</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($appointment['blood_bank']); ?>" disabled>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
