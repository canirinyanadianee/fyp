<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

// Latest 200 activity logs
try {
  $rows = $conn->query("SELECT id, user_id, action, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 200")->fetchAll();
} catch (Exception $e) { $rows = []; }

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Audit Logs</h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light"><tr><th>#</th><th>User</th><th>Action</th><th>Description</th><th>At</th></tr></thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td>#<?php echo (int)$r['id']; ?></td>
                <td><?php echo (int)$r['user_id']; ?></td>
                <td><?php echo htmlspecialchars($r['action']); ?></td>
                <td class="small" style="max-width:520px; white-space:normal;"><?php echo htmlspecialchars($r['description']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No logs found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
