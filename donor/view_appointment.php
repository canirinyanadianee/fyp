<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}

$appointment = null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $sql = "SELECT a.*, b.name AS blood_bank, b.address AS bank_address, b.city, b.state
            FROM appointments a
            LEFT JOIN blood_banks b ON a.blood_bank_id = b.id
            WHERE a.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $appointment = $res->fetch_assoc();
        $stmt->close();
    }
}

require_once '../includes/donor_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>View Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hero { border-radius:.75rem; overflow:hidden }
        .card-hero .hero { padding:1.5rem; background:linear-gradient(90deg,#6a11cb,#2575fc); color:#fff }
        .meta { font-size:.95rem }
        .muted { color:#6c757d }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="card card-hero shadow-sm">
        <div class="hero d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Appointment Details</h4>
                <small class="text-white-50">View or manage your appointment</small>
            </div>
            <div>
                <a href="appointments.php" class="btn btn-light btn-sm"><i class="fas fa-chevron-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$appointment): ?>
                <div class="alert alert-warning">Appointment not found. <a href="appointments.php">Return to appointments</a>.</div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="fw-semibold"><?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></h5>
                        <p class="muted mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($appointment['blood_bank']); ?></p>
                        <p class="muted small mb-2"><?php echo htmlspecialchars(trim(($appointment['bank_address'] ?? '') . ' ' . ($appointment['city'] ?? '') . ' ' . ($appointment['state'] ?? ''))); ?></p>

                        <p class="mb-1"><strong>Status:</strong>
                            <?php $s = $appointment['status'] ?? 'pending';
                                $cls = $s === 'confirmed' ? 'bg-success' : ($s === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark'); ?>
                            <span class="badge <?php echo $cls; ?> py-2 px-3"><?php echo ucfirst($s); ?></span>
                        </p>

                        <hr>
                        <h6 class="mb-2">Notes</h6>
                        <p class="muted small"><?php echo htmlspecialchars($appointment['notes'] ?? 'No additional notes.'); ?></p>
                    </div>
                    <div class="col-md-4">
                        <div class="d-grid gap-2">
                            <a href="reschedule_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-secondary"><i class="fas fa-calendar-alt me-1"></i> Reschedule</a>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal" data-id="<?php echo $appointment['id']; ?>"><i class="fas fa-times me-1"></i> Cancel Appointment</button>
                            <a href="donation_history.php" class="btn btn-outline-primary">View Donation History</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="cancel_appointment.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to cancel this appointment? This will also update your donation history.</p>
                        <input type="hidden" name="appointment_id" id="modal_appointment_id" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Yes, Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var input = document.getElementById('modal_appointment_id');
            if (input) input.value = id;
        });
    }
});
</script>

<?php require_once '../includes/donor_footer.php'; ?>

</body>
</html>
