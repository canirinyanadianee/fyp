<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "ML Insights Dashboard";
include '../includes/header.php';

// Include database connection
require_once '../includes/db.php';

// Get ML predictions data (placeholder - integrate with your ML models)
try {
    // Get predicted shortages (example data)
    $predicted_shortages = [
        ['blood_type' => 'O-', 'days_until_shortage' => 3, 'confidence' => 0.85],
        ['blood_type' => 'B+', 'days_until_shortage' => 5, 'confidence' => 0.72],
        ['blood_type' => 'AB-', 'days_until_shortage' => 7, 'confidence' => 0.68]
    ];
    
    // Get recent anomalies (example data)
    $recent_anomalies = [
        ['type' => 'Unusual Request Pattern', 'location' => 'City Hospital', 'severity' => 'High', 'timestamp' => '2023-06-01 14:30:00'],
        ['type' => 'Inventory Discrepancy', 'location' => 'Central Blood Bank', 'severity' => 'Medium', 'timestamp' => '2023-06-01 10:15:00']
    ];
    
    // Get forecast data (example data)
    $forecast_data = [
        'labels' => ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'demand' => [120, 140, 160, 180, 200, 220, 240],
        'supply' => [150, 155, 158, 165, 170, 175, 180]
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching ML data: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">AI/ML Insights Dashboard</h1>
            </div>

            <!-- Predicted Shortages -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Predicted Blood Shortages</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Blood Type</th>
                                    <th>Days Until Shortage</th>
                                    <th>Confidence Level</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predicted_shortages as $shortage): ?>
                                <tr>
                                    <td><span class="badge bg-danger"><?php echo $shortage['blood_type']; ?></span></td>
                                    <td><?php echo $shortage['days_until_shortage']; ?> days</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $shortage['confidence'] > 0.7 ? 'success' : 'warning'; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $shortage['confidence'] * 100; ?>%" 
                                                 aria-valuenow="<?php echo $shortage['confidence'] * 100; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($shortage['confidence'] * 100, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">View Details</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Anomaly Detection -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Anomaly Detection Alerts</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_anomalies)): ?>
                        <div class="list-group">
                            <?php foreach ($recent_anomalies as $anomaly): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <span class="badge bg-<?php echo $anomaly['severity'] === 'High' ? 'danger' : 'warning'; ?> me-2">
                                                <?php echo $anomaly['severity']; ?>
                                            </span>
                                            <?php echo $anomaly['type']; ?>
                                        </h6>
                                        <small><?php echo $anomaly['timestamp']; ?></small>
                                    </div>
                                    <p class="mb-1">Location: <?php echo $anomaly['location']; ?></p>
                                    <small>ML Model: Isolation Forest</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i> No critical anomalies detected in the last 24 hours.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Demand Forecasting -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">6-Month Blood Demand Forecast</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="forecastChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Forecast Chart
const forecastCtx = document.getElementById('forecastChart').getContext('2d');
const forecastChart = new Chart(forecastCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($forecast_data['labels']); ?>,
        datasets: [
            {
                label: 'Projected Demand',
                data: <?php echo json_encode($forecast_data['demand']); ?>,
                borderColor: 'rgba(220, 53, 69, 0.8)',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Projected Supply',
                data: <?php echo json_encode($forecast_data['supply']); ?>,
                borderColor: 'rgba(25, 135, 84, 0.8)',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Blood Supply vs Demand Forecast',
                font: {
                    size: 16
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Units'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Month'
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
