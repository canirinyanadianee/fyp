<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Machine Learning Insights";
include '../includes/header.php';
require_once '../includes/db.php';

// Example: Fetch ML predictions from Flask API (replace with real API call)
$ml_predictions = [
    ['type' => 'Shortage Forecast', 'blood_type' => 'O-', 'confidence' => 0.92, 'recommendation' => 'Mobilize O- donors in Central region'],
    ['type' => 'Overstock Alert', 'blood_type' => 'A+', 'confidence' => 0.78, 'recommendation' => 'Reduce A+ collection in North region'],
];

?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Machine Learning Insights</h1>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Predictions & Recommendations</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Blood Type</th>
                                <th>Confidence</th>
                                <th>Recommendation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ml_predictions as $ml): ?>
                            <tr>
                                <td><?php echo $ml['type']; ?></td>
                                <td><?php echo $ml['blood_type']; ?></td>
                                <td><?php echo number_format($ml['confidence'] * 100, 1); ?>%</td>
                                <td><?php echo htmlspecialchars($ml['recommendation']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>
