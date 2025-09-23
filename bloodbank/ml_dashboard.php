<?php
// ML Dashboard for Blood Bank
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/ml_helper.php';

// Check if user is logged in and is a blood bank
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: login.php');
    exit();
}

$blood_bank_id = $_SESSION['blood_bank_id'] ?? 0;
$mlHelper = getMLHelper();

// Get ML predictions
$shortage_predictions = $mlHelper->getShortagePredictions($blood_bank_id, 7);
$demand_forecast = $mlHelper->getDemandForecast($blood_bank_id, null, 30);
$storage_recommendations = $mlHelper->getStorageRecommendations($blood_bank_id);

// Set page title
$page_title = "AI-Powered Blood Bank Analytics";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-brain text-primary me-2"></i>AI Analytics Dashboard
        </h1>
        <div>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-1"></i> Refresh Data
            </button>
        </div>
    </div>

    <!-- Shortage Predictions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Blood Shortage Predictions (Next 7 Days)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($shortage_predictions['success']) && $shortage_predictions['success']): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Blood Type</th>
                                        <th>Current Inventory</th>
                                        <th>Daily Usage</th>
                                        <th>Days of Supply</th>
                                        <th>Risk Level</th>
                                        <th>Projected Shortage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shortage_predictions['data'] as $prediction): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $prediction['risk_level'] === 'Critical' ? 'danger' : ($prediction['risk_level'] === 'High' ? 'warning' : 'info') ?>">
                                                    <?= htmlspecialchars($prediction['blood_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= number_format($prediction['current_inventory_ml']) ?> ml</td>
                                            <td><?= number_format($prediction['avg_daily_usage_ml'], 1) ?> ml/day</td>
                                            <td><?= number_format($prediction['days_of_supply'], 1) ?> days</td>
                                            <td>
                                                <span class="badge bg-<?= $prediction['risk_level'] === 'Critical' ? 'danger' : ($prediction['risk_level'] === 'High' ? 'warning' : 'info') ?>">
                                                    <?= htmlspecialchars($prediction['risk_level']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($prediction['projected_shortage_date']): ?>
                                                    <?= date('M j, Y', strtotime($prediction['projected_shortage_date'])) ?>
                                                    (in <?= $prediction['days_to_safety_stock'] ?> days)
                                                <?php else: ?>
                                                    No immediate risk
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $shortage_predictions['error'] ?? 'Unable to load shortage predictions. Please try again later.' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Demand Forecast -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Demand Forecast (Next 30 Days)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($demand_forecast['success']) && $demand_forecast['success']): ?>
                        <div id="demandForecastChart" style="height: 300px;"></div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const data = <?= json_encode($demand_forecast['data']) ?>;
                                const chart = new ApexCharts(document.querySelector("#demandForecastChart"), {
                                    series: [{
                                        name: 'Forecasted Demand',
                                        data: data.map(d => d.yhat)
                                    }, {
                                        name: 'Lower Bound',
                                        data: data.map(d => d.yhat_lower)
                                    }, {
                                        name: 'Upper Bound',
                                        data: data.map(d => d.yhat_upper)
                                    }],
                                    chart: {
                                        type: 'area',
                                        height: 300,
                                        zoom: { enabled: false },
                                        toolbar: { show: false }
                                    },
                                    dataLabels: { enabled: false },
                                    stroke: { curve: 'smooth' },
                                    xaxis: {
                                        categories: data.map(d => d.ds),
                                        type: 'datetime',
                                        labels: { datetimeUTC: false }
                                    },
                                    tooltip: {
                                        x: { format: 'dd MMM yyyy' }
                                    },
                                    colors: ['#4e73df', '#e74a3b', '#1cc88a'],
                                    fill: {
                                        type: 'gradient',
                                        gradient: {
                                            shadeIntensity: 1,
                                            opacityFrom: 0.7,
                                            opacityTo: 0.3,
                                            stops: [0, 100]
                                        }
                                    }
                                });
                                chart.render();
                            });
                        </script>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $demand_forecast['error'] ?? 'Unable to load demand forecast. Please try again later.' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Storage Recommendations -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-warehouse text-success me-2"></i>
                        Storage Optimization
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($storage_recommendations['success']) && $storage_recommendations['success']): ?>
                        <div class="list-group">
                            <?php foreach ($storage_recommendations['recommendations'] as $rec): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <i class="fas fa-<?= $rec['type'] === 'warning' ? 'exclamation-triangle text-warning' : 'info-circle text-primary' ?> me-2"></i>
                                            <?= htmlspecialchars($rec['title']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            Priority: 
                                            <span class="badge bg-<?= $rec['priority'] === 'high' ? 'danger' : ($rec['priority'] === 'medium' ? 'warning' : 'info') ?>">
                                                <?= ucfirst($rec['priority']) ?>
                                            </span>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($rec['message']) ?></p>
                                    <?php if (!empty($rec['action'])): ?>
                                        <small>
                                            <a href="#" class="text-primary">
                                                <i class="fas fa-lightbulb me-1"></i>
                                                <?= $rec['action'] ?>
                                            </a>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $storage_recommendations['error'] ?? 'Unable to load storage recommendations. Please try again later.' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
