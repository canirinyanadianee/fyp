<?php
// bloodbank/alerts.php
session_start();
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

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
$page_title = 'AI Alerts & Donor Notifications';
$breadcrumbs = ['Alerts' => ''];

// Determine shortages: prefer ML predictions if table exists, otherwise use inventory thresholds
$shortages = [];
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'ml_demand_predictions'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        // Fetch today's predictions and mark high demand
        $ml = $conn->query("SELECT blood_type, predicted_demand_7d, predicted_demand_30d, confidence FROM ml_demand_predictions WHERE prediction_date = CURDATE() ORDER BY blood_type");
        while ($row = $ml->fetch_assoc()) {
            // Simple heuristic: predicted_demand_7d > 0 with decent confidence triggers suggestion
            if ((int)$row['predicted_demand_7d'] > 0 && (float)$row['confidence'] >= 0.5) {
                $shortages[] = [
                    'source' => 'ml',
                    'blood_type' => $row['blood_type'],
                    'score' => (float)$row['confidence'],
                    'detail' => "Predicted demand next 7d: {$row['predicted_demand_7d']} units"
                ];
            }
        }
    }
} catch (Exception $e) {
    // ignore ML errors and fallback to inventory
}

if (empty($shortages)) {
    // Inventory-based fallback
    $blood_types = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    $low_threshold = 500; $urgent_threshold = 200; // ml
    foreach ($blood_types as $bt) {
        $res = $conn->query("SELECT COALESCE(SUM(quantity_ml),0) AS qty FROM blood_inventory WHERE blood_bank_id = $blood_bank_id AND blood_type = '$bt' AND status = 'available'");
        $qty = (int)($res->fetch_assoc()['qty'] ?? 0);
        if ($qty <= $urgent_threshold) {
            $shortages[] = [
                'source' => 'inventory',
                'blood_type' => $bt,
                'score' => 0.9,
                'detail' => 'Urgent shortage (<=200ml on hand)'
            ];
        } elseif ($qty <= $low_threshold) {
            $shortages[] = [
                'source' => 'inventory',
                'blood_type' => $bt,
                'score' => 0.6,
                'detail' => 'Low stock (<=500ml on hand)'
            ];
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-bell text-danger me-2"></i>AI Alerts & Suggested Actions</span>
    <a href="alerts.php" class="btn btn-sm btn-outline-secondary">Refresh</a>
  </div>
  <div class="card-body">
    <?php if (empty($shortages)): ?>
      <div class="alert alert-info mb-0">No current alerts detected by AI or inventory thresholds.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Blood Type</th>
              <th>Reason</th>
              <th>Confidence</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($shortages as $s): ?>
              <tr>
                <td><span class="badge bg-danger"><?php echo htmlspecialchars($s['blood_type']); ?></span></td>
                <td class="text-muted"><?php echo htmlspecialchars($s['detail']); ?> <?php echo $s['source']==='ml' ? '(AI)' : '(Inventory)'; ?></td>
                <td>
                  <div class="progress" style="height: 20px; max-width: 180px;">
                    <?php $pct = (int)round(min(max($s['score']*100, 0), 100));
                          $color = $pct > 80 ? 'bg-success' : ($pct > 60 ? 'bg-info' : 'bg-warning'); ?>
                    <div class="progress-bar <?php echo $color; ?>" role="progressbar" style="width: <?php echo $pct; ?>%"><?php echo $pct; ?>%</div>
                  </div>
                </td>
                <td class="text-end">
                  <form method="post" action="send_notifications.php" class="d-inline">
                    <input type="hidden" name="blood_type" value="<?php echo htmlspecialchars($s['blood_type']); ?>">
                    <input type="hidden" name="strategy" value="city"> <!-- city | all -->
                    <button type="submit" class="btn btn-sm btn-primary">
                      <i class="fas fa-paper-plane me-1"></i> Notify Matching Donors
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-muted small">Notifications target matching donors (by blood type) in your bank's city when possible.</div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
