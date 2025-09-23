<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

// Simple utilization: completed requests over last 30 days by blood type
$days = 30;
$start = (new DateTime('today'))->modify("-$days days")->format('Y-m-d');
$end = (new DateTime('today'))->format('Y-m-d');

try {
  $sql = "SELECT blood_type, COUNT(*) as requests
          FROM blood_requests
          WHERE status = 'completed' AND DATE(request_date) BETWEEN ? AND ?
          GROUP BY blood_type ORDER BY blood_type";
  $stmt = $conn->prepare($sql); $stmt->execute([$start, $end]);
  $rows = $stmt->fetchAll();
} catch (Exception $e) { $rows = []; }

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Utilization Rate (Last 30 days)</h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light"><tr><th>Blood Type</th><th class="text-end">Completed Requests</th></tr></thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><span class="badge bg-danger"><?php echo htmlspecialchars($r['blood_type']); ?></span></td>
                <td class="text-end"><?php echo (int)$r['requests']; ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center text-muted py-4">No data available.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
