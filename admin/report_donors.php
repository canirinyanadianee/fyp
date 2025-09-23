<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

// Stats by blood type and overall counts
$stats = [];
try {
  $stmt = $conn->query("SELECT blood_type, COUNT(*) as total FROM donors GROUP BY blood_type ORDER BY blood_type");
  $stats = $stmt->fetchAll();
} catch (Exception $e) { $stats = []; }

$totalDonors = 0; foreach ($stats as $s) { $totalDonors += (int)$s['total']; }

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-users me-2"></i>Donor Statistics</h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Total Donors</div>
          <div class="fs-4 fw-semibold"><?php echo (int)$totalDonors; ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light"><tr><th>Blood Type</th><th class="text-end">Donors</th></tr></thead>
          <tbody>
            <?php if (!empty($stats)): foreach ($stats as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['blood_type']); ?></td>
                <td class="text-end"><?php echo (int)$r['total']; ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center text-muted py-4">No donor data available.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
