<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

// Users by role and recent logins if available
try {
  $byRole = $conn->query("SELECT role, COUNT(*) total FROM users GROUP BY role ORDER BY role")->fetchAll();
} catch (Exception $e) { $byRole = []; }

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-user-shield me-2"></i>User Activity</h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light"><tr><th>Role</th><th class="text-end">Users</th></tr></thead>
          <tbody>
            <?php if (!empty($byRole)): foreach ($byRole as $r): ?>
              <tr>
                <td class="text-capitalize"><?php echo htmlspecialchars($r['role']); ?></td>
                <td class="text-end"><?php echo (int)$r['total']; ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center text-muted py-4">No user data available.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
