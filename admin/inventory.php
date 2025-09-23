<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();
$page_title = "Inventory Prediction | " . APP_NAME;

// Get all blood banks
$query = "SELECT b.*, u.email, u.status as active 
          FROM blood_banks b 
          JOIN users u ON b.user_id = u.id
          ORDER BY b.name";
$bloodBanksStmt = $conn->query($query);
$bloodBanks = $bloodBanksStmt->fetchAll();

// Prediction granularity: hourly over a recent lookback window (default 72h)
$lookbackHours = isset($_GET['hours']) ? max(24, min(336, (int)$_GET['hours'])) : 72; // between 24h and 14d

// Get historical inventory data for predictions (last N hours, bucketed hourly)
$historical_query = "
    SELECT 
        bi.blood_bank_id,
        DATE_FORMAT(bi.last_updated, '%Y-%m-%d %H:00:00') as hour_bucket,
        bi.blood_type,
        AVG(bi.quantity_ml) as avg_quantity,
        COUNT(*) as record_count
    FROM blood_inventory bi
    WHERE bi.last_updated >= DATE_SUB(NOW(), INTERVAL {$lookbackHours} HOUR)
    GROUP BY bi.blood_bank_id, hour_bucket, bi.blood_type
    ORDER BY bi.blood_bank_id, hour_bucket DESC, bi.blood_type
";

$historical_result = $conn->query($historical_query);
$historical_data = [];
while ($row = $historical_result->fetch()) {
    $historical_data[$row['blood_bank_id']][$row['blood_type']][] = $row;
}

// Generate predictions based on historical data with improved seasonality and trend analysis
function generateInventoryPredictions($blood_bank_id, $blood_type, $historical_data) {
    if (!isset($historical_data[$blood_bank_id][$blood_type])) {
        return null; // Not enough historical data
    }
    
    $data = $historical_data[$blood_bank_id][$blood_type];
    $data_points = count($data);
    
    if ($data_points < 2) {
        return null; // Need at least 2 data points for trend analysis
    }
    
    // Calculate weighted moving average with more weight to recent data
    $weighted_sum = 0;
    $weight_total = 0;
    $quantities = [];
    $hours = [];
    
    foreach ($data as $i => $record) {
        $weight = $i + 1; // Linear weighting (more recent = higher weight)
        $weighted_sum += $record['avg_quantity'] * $weight;
        $weight_total += $weight;
        $quantities[] = $record['avg_quantity'];
        $hours[] = $record['hour_bucket'];
    }
    
    $weighted_avg = $weighted_sum / $weight_total;
    
    // Calculate trend (simple linear regression)
    $n = count($quantities);
    $sum_x = 0; $sum_y = 0; $sum_xy = 0; $sum_x2 = 0;
    
    foreach($quantities as $i => $y) {
        $x = $i + 1; // Time period (1, 2, 3, ...)
        $sum_x += $x;
        $sum_y += $y;
        $sum_xy += $x * $y;
        $sum_x2 += $x * $x;
    }
    
    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
    $intercept = ($sum_y - $slope * $sum_x) / $n;
    
    // Predict next period using trend
    $next_period = $n + 1;
    $trend_prediction = $slope * $next_period + $intercept;
    
    // Hour-of-day seasonality factors (0-23). Typically daytime activity is higher.
    $current_hour = (int)date('G'); // 0-23
    $hourly_factors = [
        0=>0.90, 1=>0.88, 2=>0.87, 3=>0.87, 4=>0.88, 5=>0.90,
        6=>0.93, 7=>0.96, 8=>1.00, 9=>1.03, 10=>1.06, 11=>1.08,
        12=>1.10, 13=>1.10, 14=>1.08, 15=>1.06, 16=>1.04, 17=>1.02,
        18=>1.02, 19=>1.04, 20=>1.03, 21=>1.00, 22=>0.96, 23=>0.93
    ];
    $seasonal_factor = $hourly_factors[$current_hour] ?? 1.0;
    
    // Combine weighted average, trend, and seasonality
    $base_prediction = ($weighted_avg * 0.6) + ($trend_prediction * 0.4);
    $predicted_quantity = round($base_prediction * $seasonal_factor);
    
    // Calculate confidence (0-100%)
    $variance = 0;
    foreach($quantities as $q) {
        $variance += pow($q - $weighted_avg, 2);
    }
    $std_dev = $data_points > 1 ? sqrt($variance / ($data_points - 1)) : 0;
    $cv = $weighted_avg > 0 ? ($std_dev / $weighted_avg) : 0; // Coefficient of variation
    
    // Higher confidence for more data points and lower variance
    $base_confidence = min(100, 60 + ($data_points * 1.5));
    $variance_penalty = min(30, $cv * 50); // Up to 30% penalty for high variance
    $confidence = max(50, $base_confidence - $variance_penalty);
    
    return [
        'blood_type' => $blood_type,
        'current_avg' => round($weighted_avg),
        'predicted_quantity' => $predicted_quantity,
        'confidence' => round($confidence),
        'trend' => $slope > 0 ? 'up' : ($slope < 0 ? 'down' : 'stable'),
        'data_points' => $data_points,
        'seasonal_factor' => $seasonal_factor
    ];
}

// Get current inventory levels
$current_inventory = [];
$inventory_query = "
    SELECT blood_bank_id, blood_type, SUM(quantity_ml) as total_quantity
    FROM blood_inventory
    WHERE expiry_date > CURDATE()
    GROUP BY blood_bank_id, blood_type
";
$inventory_result = $conn->query($inventory_query);
while ($row = $inventory_result->fetch()) {
    $current_inventory[$row['blood_bank_id']][$row['blood_type']] = $row['total_quantity'];
}

// Generate predictions for all blood banks and blood types
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$all_predictions = [];

foreach ($bloodBanks as $bank) {
    $bank_id = $bank['id'];
    $all_predictions[$bank_id] = [
        'bank_info' => $bank,
        'predictions' => []
    ];
    
    foreach ($blood_types as $blood_type) {
        $prediction = generateInventoryPredictions($bank_id, $blood_type, $historical_data);
        if ($prediction) {
            $current_qty = $current_inventory[$bank_id][$blood_type] ?? 0;
            $prediction['current_quantity'] = $current_qty;
            $prediction['difference'] = $prediction['predicted_quantity'] - $current_qty;
            $all_predictions[$bank_id]['predictions'][$blood_type] = $prediction;
        }
    }
    
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Blood Bank Inventory Predictions</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Predictions are based on the last <?php echo (int)$lookbackHours; ?> hours of inventory data,
                bucketed hourly, with trend and hour-of-day adjustments.
                <div class="mt-2">
                    <form method="get" class="d-inline-flex align-items-center gap-2">
                        <label class="form-label mb-0">Lookback (hours):</label>
                        <input type="number" class="form-control form-control-sm" style="width:100px" name="hours" min="24" max="336" value="<?php echo (int)$lookbackHours; ?>">
                        <button class="btn btn-sm btn-primary" type="submit">Apply</button>
                    </form>
                </div>
            </div>

            <?php foreach ($all_predictions as $bank_id => $data): 
                if (empty($data['predictions'])) continue;
                $bank = $data['bank_info'];
            ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo htmlspecialchars($bank['name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($bank['email']); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr class="prediction-row" data-bank-name="<?php echo htmlspecialchars($bank['name']); ?>" style="cursor: pointer;" data-bs-toggle="tooltip" title="Click to copy prediction details">
                                        <th>Blood Type</th>
                                        <th>Current Quantity (ml)</th>
                                        <th>Predicted Need (ml)</th>
                                        <th>Difference</th>
                                        <th>Confidence</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['predictions'] as $prediction): 
                                        $diff = $prediction['difference'];
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        if ($diff > 0) {
                                            $status_class = 'text-danger';
                                            $status_text = 'Low Stock';
                                            if ($diff > 500) { // ml threshold
                                                $status_class = 'text-warning';
                                                $status_text = 'Critical Low';
                                            }
                                        } else {
                                            $status_class = 'text-success';
                                            $status_text = 'Adequate';
                                        }
                                    ?>
                                        <tr class="prediction-row" data-bank-name="<?php echo htmlspecialchars($bank['name']); ?>" style="cursor: pointer;" data-bs-toggle="tooltip" title="Click to copy prediction details">
                                            <td>
                                                <span class="badge bg-primary"><?php echo $prediction['blood_type']; ?></span>
                                                <?php if (isset($prediction['trend'])): ?>
                                                    <span class="ms-1" data-bs-toggle="tooltip" title="Trend: <?php echo ucfirst($prediction['trend']); ?>">
                                                        <?php if ($prediction['trend'] === 'up'): ?>
                                                            <i class="fas fa-arrow-up text-danger"></i>
                                                        <?php elseif ($prediction['trend'] === 'down'): ?>
                                                            <i class="fas fa-arrow-down text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-arrows-alt-h text-muted"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($prediction['current_quantity']); ?> ml</td>
                                            <td>
                                                <?php echo number_format($prediction['predicted_quantity']); ?> ml
                                                <?php if (isset($prediction['seasonal_factor']) && abs($prediction['seasonal_factor'] - 1.0) > 0.02): ?>
                                                    <span class="small text-muted ms-1" data-bs-toggle="tooltip" title="Seasonal adjustment: <?php echo round(($prediction['seasonal_factor'] - 1) * 100); ?>%">
                                                        <i class="fas fa-info-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $prediction['difference'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php if ($prediction['difference'] > 0): ?>
                                                    <i class="fas fa-arrow-up"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-arrow-down"></i>
                                                <?php endif; ?>
                                                <?php echo number_format(abs($prediction['difference'])); ?> ml
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                        <div class="progress-bar bg-<?php echo $prediction['confidence'] > 80 ? 'success' : ($prediction['confidence'] > 60 ? 'warning' : 'danger'); ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $prediction['confidence']; ?>%" 
                                                             aria-valuenow="<?php echo $prediction['confidence']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <span class="small"><?php echo $prediction['confidence']; ?>%</span>
                                                </div>
                                                <?php if (isset($prediction['data_points'])): ?>
                                                    <div class="small text-muted">
                                                        <?php echo $prediction['data_points']; ?> data points
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $status_class; ?> align-middle">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas <?php echo $status_class === 'text-success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-1"></i>
                                                    <span><?php echo $status_text; ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        Last updated: <?php echo date('F j, Y, g:i a'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($all_predictions)): ?>
                <div class="alert alert-warning">
                    No prediction data available. Ensure there is sufficient historical inventory data.
                </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<style>
    .tooltip-inner {
        max-width: 300px;
        padding: 0.5rem 1rem;
        color: #fff;
        text-align: left;
        background-color: #0d6efd;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }
    .progress {
        position: relative;
    }
    .progress-bar {
        position: relative;
        overflow: visible;
        color: #000;
        font-weight: 500;
    }
    .progress-bar::after {
        content: '';
        position: absolute;
        right: -2px;
        top: -2px;
        bottom: -2px;
        width: 1px;
        background-color: rgba(0,0,0,0.1);
    }
</style>

<script>
    // Initialize Bootstrap tooltips
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover',
                placement: 'top',
                html: true,
                container: 'body'
            });
        });

        // Add click event to copy prediction data
        document.querySelectorAll('.prediction-row').forEach(row => {
            row.addEventListener('click', function() {
                const bankName = this.getAttribute('data-bank-name');
                const bloodType = this.querySelector('td:first-child').textContent.trim();
                const current = this.querySelector('td:nth-child(2)').textContent.trim();
                const predicted = this.querySelector('td:nth-child(3)').textContent.trim();
                const difference = this.querySelector('td:nth-child(4)').textContent.trim();
                const confidence = this.querySelector('.progress-bar').style.width;
                
                const textToCopy = `Blood Bank: ${bankName}\n` +
                                 `Blood Type: ${bloodType}\n` +
                                 `Current: ${current}\n` +
                                 `Predicted: ${predicted}\n` +
                                 `Difference: ${difference}\n` +
                                 `Confidence: ${confidence}`;
                
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const tooltip = bootstrap.Tooltip.getInstance(this);
                    const originalTitle = this.getAttribute('data-bs-original-title');
                    this.setAttribute('data-bs-original-title', 'Copied to clipboard!');
                    tooltip.show();
                    
                    setTimeout(() => {
                        this.setAttribute('data-bs-original-title', originalTitle);
                        tooltip.hide();
                    }, 2000);
                });
            });
        });

        // Auto-refresh the page every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    });
</script>

<?php include '../includes/footer.php'; ?>
