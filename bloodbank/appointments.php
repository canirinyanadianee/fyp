<?php
// bloodbank/appointments.php
session_start();
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

// Auth: must be blood bank user
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}

// Load bank
$user_id = (int)$_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
if (!$bank) {
    $_SESSION['error'] = 'Blood bank profile not found for this account.';
    header('Location: index.php');
    exit();
}
$blood_bank_id = (int)$bank['id'];

$page_title = 'Manage Appointments';
$breadcrumbs = ['Appointments' => ''];

// Filters
$status_filter = $_GET['status'] ?? 'pending';
$valid_status = ['pending','approved','rejected','confirmed','cancelled','completed'];
if (!in_array($status_filter, $valid_status, true)) {
    $status_filter = 'pending';
}

// Fetch appointments for this bank
$sql = "SELECT a.*, d.first_name, d.last_name, d.blood_type 
        FROM appointments a 
        JOIN donors d ON a.donor_id = d.id
        WHERE a.blood_bank_id = ? " . ($status_filter ? "AND a.status = ?" : '') . "
        ORDER BY a.appointment_date DESC";
$stmt = $conn->prepare($sql);
if ($status_filter) {
    $stmt->bind_param('is', $blood_bank_id, $status_filter);
} else {
    $stmt->bind_param('i', $blood_bank_id);
}
$stmt->execute();
$appointments = $stmt->get_result();
$stmt->close();

include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="far fa-calendar-alt me-2"></i>Appointments (<?php echo htmlspecialchars(ucfirst($status_filter)); ?>)</span>
    <div>
      <a href="?status=pending" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='pending'?' active':''; ?>">Pending</a>
      <a href="?status=approved" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='approved'?' active':''; ?>">Approved</a>
      <a href="?status=rejected" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='rejected'?' active':''; ?>">Rejected</a>
      <a href="?status=confirmed" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='confirmed'?' active':''; ?>">Confirmed</a>
      <a href="?status=cancelled" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='cancelled'?' active':''; ?>">Cancelled</a>
      <a href="?status=completed" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='completed'?' active':''; ?>">Completed</a>
    </div>
  </div>
  <div class="card-body">
    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Donor</th>
            <th>Blood Type</th>
            <th>Date & Time</th>
            <th>Status</th>
            <th>Reason</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($appointments && $appointments->num_rows > 0): ?>
          <?php while ($row = $appointments->fetch_assoc()): ?>
            <?php
              $status = $row['status'];
              $badge = 'secondary';
              if ($status === 'pending') $badge = 'warning text-dark';
              elseif (in_array($status, ['approved','confirmed','completed'], true)) $badge = 'success';
              elseif ($status === 'rejected') $badge = 'danger';
              elseif ($status === 'cancelled') $badge = 'secondary';
            ?>
            <tr>
              <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
              <td><span class="badge bg-danger"><?php echo htmlspecialchars($row['blood_type']); ?></span></td>
              <td><?php echo date('M d, Y H:i', strtotime($row['appointment_date'])); ?></td>
              <td><span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($status); ?></span></td>
              <td class="text-muted small"><?php echo htmlspecialchars($row['decision_reason'] ?? $row['notes'] ?? ''); ?></td>
              <td class="text-end">
                <?php if ($status === 'pending'): ?>
                  <form method="post" action="appointment_action.php" class="d-inline">
                    <input type="hidden" name="appointment_id" value="<?php echo (int)$row['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Approve</button>
                  </form>
                  <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo (int)$row['id']; ?>">
                    <i class="fas fa-times me-1"></i>Reject
                  </button>
                <?php else: ?>
                  <span class="text-muted">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No appointments found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="appointment_action.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="appointment_id" id="reject_appt_id" value="">
        <input type="hidden" name="action" value="reject">
        <div class="mb-3">
          <label class="form-label">Reason (visible to donor)</label>
          <textarea class="form-control" name="reason" rows="3" placeholder="Provide a brief reason for rejection" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const rejectModal = document.getElementById('rejectModal');
  if (rejectModal) {
    rejectModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const apptId = button.getAttribute('data-id');
      document.getElementById('reject_appt_id').value = apptId;
    });
  }
</script>
