<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

$type = ($_GET['type'] ?? 'current') === 'expiring' ? 'expiring' : 'current';

try {
  if ($type === 'expiring') {
    $sql = "SELECT blood_type, SUM(quantity_ml) qty, MIN(expiry_date) next_expiry
            FROM blood_inventory
            WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            GROUP BY blood_type ORDER BY blood_type";
  } else {
    $sql = "SELECT blood_type, SUM(quantity_ml) qty
            FROM blood_inventory
            WHERE expiry_date > CURDATE()
            GROUP BY blood_type ORDER BY blood_type";
  }
  $rows = $conn->query($sql)->fetchAll();
} catch (Exception $e) { $rows = []; }

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
      <i class="fas fa-box-open me-2"></i><?php echo $type==='expiring'?'Expiring Soon':'Current Inventory'; ?>
    </h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?php echo $type==='current'?'active':''; ?>" href="report_inventory.php?type=current">Current</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $type==='expiring'?'active':''; ?>" href="report_inventory.php?type=expiring">Expiring Soon</a></li>
  </ul>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Blood Type</th>
              <th class="text-end">Quantity (ml)</th>
              <?php if ($type==='expiring'): ?><th>Next Expiry</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><span class="badge bg-danger"><?php echo htmlspecialchars($r['blood_type']); ?></span></td>
                <td class="text-end"><?php echo number_format((int)$r['qty']); ?></td>
                <?php if ($type==='expiring'): ?><td><?php echo htmlspecialchars($r['next_expiry']); ?></td><?php endif; ?>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="3" class="text-center text-muted py-4">No data found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
