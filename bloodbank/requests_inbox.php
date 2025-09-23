<?php
// bloodbank/requests_inbox.php
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

$user_id = (int)$_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
if (!$bank) {
    $_SESSION['error'] = 'Blood bank profile not found for this account.';
    header('Location: index.php');
    exit();
}
$blood_bank_id = (int)$bank['id'];
$bank_city = trim($bank['city'] ?? '');

$page_title = 'Hospital Requests Inbox';
$breadcrumbs = ['Hospital Requests' => ''];

// Filters
$status_filter = $_GET['status'] ?? 'pending';
$valid_status = ['pending','approved','rejected','completed'];
if (!in_array($status_filter, $valid_status, true)) $status_filter = 'pending';

// Fetch hospital requests in same city (basic routing). If bank has no city, show all pending.
$params = [];
$sql = "SELECT br.*, h.name AS hospital_name, h.city AS hospital_city
        FROM blood_requests br
        JOIN hospitals h ON br.hospital_id = h.id
        WHERE br.status = ?";
$params[] = $status_filter;
$types = 's';
if ($bank_city !== '') {
    $sql .= " AND (h.city = ? OR h.city IS NULL OR h.city = '')";
    $params[] = $bank_city;
    $types .= 's';
}
$sql .= " ORDER BY br.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $requests = $stmt->get_result();
    $stmt->close();
} else {
    $requests = false;
}

include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-inbox me-2 text-primary"></i>Hospital Requests (<?php echo htmlspecialchars(ucfirst($status_filter)); ?>)</span>
    <div>
      <a href="?status=pending" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='pending'?' active':''; ?>">Pending</a>
      <a href="?status=approved" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='approved'?' active':''; ?>">Approved</a>
      <a href="?status=rejected" class="btn btn-sm btn-outline-secondary<?php echo $status_filter==='rejected'?' active':''; ?>">Rejected</a>
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
            <th>#</th>
            <th>Hospital</th>
            <th>Blood Type</th>
            <th>Quantity</th>
            <th>Urgency</th>
            <th>Status</th>
            <th>Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($requests && $requests->num_rows > 0): ?>
          <?php while ($r = $requests->fetch_assoc()): ?>
            <?php
              $urg_class = 'bg-secondary';
              if ($r['urgency'] === 'urgent') $urg_class = 'bg-warning text-dark';
              if ($r['urgency'] === 'emergency') $urg_class = 'bg-danger';

              $status_class = 'bg-secondary';
              if ($r['status'] === 'pending') $status_class = 'bg-warning text-dark';
              if ($r['status'] === 'approved') $status_class = 'bg-success';
              if ($r['status'] === 'rejected') $status_class = 'bg-danger';
              if ($r['status'] === 'completed') $status_class = 'bg-info';
            ?>
            <tr>
              <td>#<?php echo (int)$r['id']; ?></td>
              <td>
                <strong><?php echo htmlspecialchars($r['hospital_name'] ?? 'Hospital'); ?></strong>
                <div class="text-muted small"><?php echo htmlspecialchars($r['hospital_city'] ?? ''); ?></div>
              </td>
              <td><span class="badge bg-primary"><?php echo htmlspecialchars($r['blood_type']); ?></span></td>
              <td><?php echo number_format((int)$r['quantity_ml']); ?> ml</td>
              <td><span class="badge <?php echo $urg_class; ?>"><?php echo ucfirst($r['urgency']); ?></span></td>
              <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($r['status']); ?></span></td>
              <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
              <td class="text-end">
                <?php if ($r['status'] === 'pending'): ?>
                  <form method="post" action="request_action.php" class="d-inline approve-form" onsubmit="return confirm('Approve this hospital request?');">
                    <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success approve-btn"><i class="fas fa-check me-1"></i>Approve</button>
                  </form>
                  <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo (int)$r['id']; ?>">
                    <i class="fas fa-times me-1"></i>Reject
                  </button>
                <?php else: ?>
                  <span class="text-muted">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No matching hospital requests.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="request_action.php" class="modal-content" id="rejectForm">
      <div class="modal-header">
        <h5 class="modal-title">Reject Hospital Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="request_id" id="reject_req_id" value="">
        <input type="hidden" name="action" value="reject">
        <div class="mb-3">
          <label class="form-label">Reason (visible to hospital)</label>
          <textarea class="form-control" name="reason" id="rejectReason" rows="3" placeholder="Provide a brief reason" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-danger" id="rejectSubmit" disabled>Reject</button>
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
      const reqId = button.getAttribute('data-id');
      document.getElementById('reject_req_id').value = reqId;
      // reset state
      const reason = document.getElementById('rejectReason');
      const submitBtn = document.getElementById('rejectSubmit');
      if (reason) reason.value = '';
      if (submitBtn) submitBtn.disabled = true;
    });
  }

  // Enable reject submit only when reason has content
  const reasonEl = document.getElementById('rejectReason');
  const rejectSubmit = document.getElementById('rejectSubmit');
  if (reasonEl && rejectSubmit) {
    reasonEl.addEventListener('input', function(){
      rejectSubmit.disabled = this.value.trim().length === 0;
    });
  }

  // Prevent double submit on approve forms
  document.querySelectorAll('.approve-form').forEach(function(form){
    form.addEventListener('submit', function(){
      const btn = this.querySelector('.approve-btn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Approving...';
      }
    });
  });
</script>
