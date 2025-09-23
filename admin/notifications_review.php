<?php
// admin/notifications_review.php
// Admin UI to review and approve/reject notifications before they are visible
session_start();
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

// Basic auth guard
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$admin_id = (int)$_SESSION['user_id'];

// Try to ensure required columns exist (best-effort, safe to ignore if already present)
// Adds: status, approved_by, approved_at
try {
    @$conn->query("ALTER TABLE ai_notifications ADD COLUMN status ENUM('pending','approved','rejected','sent') NOT NULL DEFAULT 'pending'");
} catch (Exception $e) {}
try {
    @$conn->query("ALTER TABLE ai_notifications ADD COLUMN approved_by INT NULL");
} catch (Exception $e) {}
try {
    @$conn->query("ALTER TABLE ai_notifications ADD COLUMN approved_at DATETIME NULL");
} catch (Exception $e) {}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0 && in_array($action, ['approve','reject','mark_sent'], true)) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE ai_notifications SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
            $stmt->bind_param('ii', $admin_id, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Notification approved.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE ai_notifications SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?");
            $stmt->bind_param('ii', $admin_id, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Notification rejected.';
        } elseif ($action === 'mark_sent') {
            $stmt = $conn->prepare("UPDATE ai_notifications SET status='sent' WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Notification marked as sent.';
        }
    } else {
        $_SESSION['error'] = 'Invalid action or ID.';
    }
    header('Location: notifications_review.php');
    exit();
}

// Filters
$status = $_GET['status'] ?? 'pending';
$valid_status = ['pending','approved','rejected','sent','all'];
if (!in_array($status, $valid_status, true)) $status = 'pending';
$entity_type = $_GET['entity_type'] ?? '';

$params = [];
$types = '';
$where = '1=1';
if ($status !== 'all') {
    $where .= " AND (status = ? OR status IS NULL)"; // older rows may have NULL
    $params[] = $status;
    $types .= 's';
}
if ($entity_type !== '') {
    $where .= " AND entity_type = ?";
    $params[] = $entity_type;
    $types .= 's';
}

$sql = "SELECT * FROM ai_notifications WHERE $where ORDER BY created_at DESC LIMIT 500";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Notifications Review</h3>
    <div>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select class="form-select" name="status">
        <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
        <option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Approved</option>
        <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rejected</option>
        <option value="sent" <?php echo $status==='sent'?'selected':''; ?>>Sent</option>
        <option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Entity Type</label>
      <select class="form-select" name="entity_type">
        <option value="" <?php echo $entity_type===''?'selected':''; ?>>All</option>
        <option value="donor" <?php echo $entity_type==='donor'?'selected':''; ?>>Donor</option>
        <option value="hospital" <?php echo $entity_type==='hospital'?'selected':''; ?>>Hospital</option>
        <option value="bloodbank" <?php echo $entity_type==='bloodbank'?'selected':''; ?>>Blood Bank</option>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Filter</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Entity</th>
          <th>Message</th>
          <th>Blood Type</th>
          <th>Urgency/Priority</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows && $rows->num_rows > 0): ?>
          <?php while ($n = $rows->fetch_assoc()): ?>
            <tr>
              <td>#<?php echo (int)$n['id']; ?></td>
              <td>
                <div class="fw-semibold text-capitalize"><?php echo htmlspecialchars($n['entity_type']); ?></div>
                <div class="text-muted small">ID: <?php echo (int)($n['entity_id'] ?? 0); ?></div>
              </td>
              <td class="small" style="max-width:480px; white-space:normal;">
                <?php echo htmlspecialchars($n['message']); ?>
              </td>
              <td><span class="badge bg-danger"><?php echo htmlspecialchars($n['blood_type'] ?? ''); ?></span></td>
              <td>
                <?php
                  $urg = $n['urgency_level'] ?? ($n['priority'] ?? '');
                  $cls = 'bg-secondary';
                  if ($urg === 'high') $cls = 'bg-danger';
                  elseif ($urg === 'medium') $cls = 'bg-warning text-dark';
                  elseif ($urg === 'low') $cls = 'bg-info';
                ?>
                <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($urg ?: 'n/a'); ?></span>
              </td>
              <td>
                <?php
                  $st = $n['status'] ?? 'pending';
                  $cls = 'bg-secondary';
                  if ($st === 'pending') $cls = 'bg-warning text-dark';
                  elseif ($st === 'approved') $cls = 'bg-success';
                  elseif ($st === 'rejected') $cls = 'bg-danger';
                  elseif ($st === 'sent') $cls = 'bg-primary';
                ?>
                <span class="badge <?php echo $cls; ?>"><?php echo ucfirst($st); ?></span>
              </td>
              <td><?php echo date('M d, Y H:i', strtotime($n['created_at'] ?? 'now')); ?></td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-sm btn-success" <?php echo ($n['status'] ?? 'pending')==='approved'?'disabled':''; ?>>
                    <i class="fas fa-check me-1"></i>Approve
                  </button>
                </form>
                <form method="post" class="d-inline ms-1">
                  <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn btn-sm btn-danger" <?php echo ($n['status'] ?? 'pending')==='rejected'?'disabled':''; ?>>
                    <i class="fas fa-times me-1"></i>Reject
                  </button>
                </form>
                <form method="post" class="d-inline ms-1">
                  <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                  <input type="hidden" name="action" value="mark_sent">
                  <button class="btn btn-sm btn-primary">
                    <i class="fas fa-paper-plane me-1"></i>Mark Sent
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No notifications found for the selected filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
