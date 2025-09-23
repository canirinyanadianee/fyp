<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

$type = ($_GET['type'] ?? 'daily') === 'monthly' ? 'monthly' : 'daily';
$range = $_GET['range'] ?? 'this_month';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$today = new DateTime('today');
switch ($range) {
  case 'today': $from = clone $today; $to = clone $today; break;
  case 'yesterday': $from = (clone $today)->modify('-1 day'); $to = (clone $today)->modify('-1 day'); break;
  case 'this_week': $from = (clone $today)->modify('monday this week'); $to = (clone $today)->modify('sunday this week'); break;
  case 'last_week': $from = (clone $today)->modify('monday last week'); $to = (clone $today)->modify('sunday last week'); break;
  case 'last_month': $from = (clone $today)->modify('first day of last month'); $to = (clone $today)->modify('last day of last month'); break;
  default: $from = (clone $today)->modify('first day of this month'); $to = (clone $today)->modify('last day of this month');
}
if (($range === 'custom') && $start && $end) {
  $from = new DateTime($start); $to = new DateTime($end);
}
$fromS = $from->format('Y-m-d');
$toS = $to->format('Y-m-d');

// Query donations grouped
try {
  if ($type === 'monthly') {
    $sql = "SELECT DATE_FORMAT(donation_date, '%Y-%m') as label, COUNT(*) as total
            FROM blood_donations
            WHERE DATE(donation_date) BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
            ORDER BY label";
  } else {
    $sql = "SELECT DATE(donation_date) as label, COUNT(*) as total
            FROM blood_donations
            WHERE DATE(donation_date) BETWEEN ? AND ?
            GROUP BY DATE(donation_date)
            ORDER BY label";
  }
  $stmt = $conn->prepare($sql); $stmt->execute([$fromS, $toS]);
  $rows = $stmt->fetchAll();
} catch (Exception $e) { $rows = []; }

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-calendar-<?php echo $type==='monthly'?'alt':'day'; ?> me-2"></i><?php echo ucfirst($type); ?> Donations Report</h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <form class="row gy-2 gx-3 align-items-end mb-3" method="get">
    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
    <div class="col-md-3">
      <label class="form-label">Date Range</label>
      <select name="range" class="form-select">
        <option value="today" <?php echo $range==='today'?'selected':''; ?>>Today</option>
        <option value="yesterday" <?php echo $range==='yesterday'?'selected':''; ?>>Yesterday</option>
        <option value="this_week" <?php echo $range==='this_week'?'selected':''; ?>>This Week</option>
        <option value="last_week" <?php echo $range==='last_week'?'selected':''; ?>>Last Week</option>
        <option value="this_month" <?php echo $range==='this_month'?'selected':''; ?>>This Month</option>
        <option value="last_month" <?php echo $range==='last_month'?'selected':''; ?>>Last Month</option>
        <option value="custom" <?php echo $range==='custom'?'selected':''; ?>>Custom</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Start</label>
      <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($fromS); ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">End</label>
      <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($toS); ?>">
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light"><tr><th>Period</th><th class="text-end">Donations</th></tr></thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['label']); ?></td>
                <td class="text-end"><?php echo (int)$r['total']; ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center text-muted py-4">No data for selected range.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
