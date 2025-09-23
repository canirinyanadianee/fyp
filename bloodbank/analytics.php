<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// // Check if user is logged in and has blood bank role
// if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'bloodbank_staff', 'bloodbank_manager'])) {
//     header('Location: /login.php');
//     exit();
// }

$bloodBankId = $_SESSION['user_id'];

// Get date range from query parameters (default to last 30 days)
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$bloodType = $_GET['blood_type'] ?? '';

// Get blood bank details
$stmt = $pdo->prepare("SELECT * FROM blood_banks WHERE user_id = ?");
$stmt->execute([$bloodBankId]);
$bloodBank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bloodBank) {
    die("Blood bank not found");
}

// Prepare base query conditions
$conditions = ["bd.blood_bank_id = :blood_bank_id"];
$params = [':blood_bank_id' => $bloodBank['id']];

if ($startDate && $endDate) {
    $conditions[] = "DATE(bd.donation_date) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
}

if ($bloodType) {
    $conditions[] = "bd.blood_type = :blood_type";
    $params[':blood_type'] = $bloodType;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get donation statistics
try {
    // First check if blood_screening table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'blood_screening'");
    $screeningTableExists = $tableCheck->rowCount() > 0;
    
    $donationStatsStmt = $pdo->prepare("
        SELECT 
            bd.blood_type,
            COUNT(*) as donation_count,
            SUM(bd.quantity_ml) as total_ml,
            COUNT(DISTINCT bd.donor_id) as unique_donors,
            AVG(bd.quantity_ml) as avg_donation_ml,
            " . ($screeningTableExists ? 
               "SUM(CASE WHEN bs.status = 'passed' THEN 1 ELSE 0 END) as passed_screening,
                SUM(CASE WHEN bs.status = 'failed' THEN 1 ELSE 0 END) as failed_screening" : 
               "0 as passed_screening, 0 as failed_screening") . "
        FROM blood_donations bd
        " . ($screeningTableExists ? "LEFT JOIN blood_screening bs ON bd.id = bs.donation_id" : "") . "
        $whereClause
        GROUP BY bd.blood_type
        ORDER BY bd.blood_type
    ");
    $donationStatsStmt->execute($params);
    $donationStats = $donationStatsStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
} catch (PDOException $e) {
    // If there's an error (like table doesn't exist), run a simpler query
    $donationStatsStmt = $pdo->prepare("
        SELECT 
            blood_type,
            COUNT(*) as donation_count,
            SUM(quantity_ml) as total_ml,
            COUNT(DISTINCT donor_id) as unique_donors,
            AVG(quantity_ml) as avg_donation_ml,
            0 as passed_screening,
            0 as failed_screening
        FROM blood_donations
        $whereClause
        GROUP BY blood_type
        ORDER BY blood_type
    ");
    $donationStatsStmt->execute($params);
    $donationStats = $donationStatsStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
}

// Get distribution statistics
$distributionStmt = $pdo->prepare("
    SELECT 
        bt.blood_type,
        COUNT(*) as transfer_count,
        SUM(bt.quantity_ml) as total_ml_distributed,
        COUNT(DISTINCT bt.hospital_id) as hospitals_served
    FROM blood_transfers bt
    WHERE bt.blood_bank_id = :blood_bank_id 
    AND bt.status = 'completed'
    " . ($startDate && $endDate ? "AND DATE(bt.transfer_date) BETWEEN :start_date AND :end_date" : "") . "
    " . ($bloodType ? "AND bt.blood_type = :blood_type" : "") . "
    GROUP BY bt.blood_type
    ORDER BY bt.blood_type
");
$distributionStmt->execute($params);
$distributionStats = $distributionStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

// Get wastage statistics
$wastageStmt = $pdo->prepare("
    SELECT 
        blood_type,
        COUNT(*) as wastage_count,
        SUM(quantity_ml) as total_ml_wasted,
        reason,
        DATE(recorded_at) as wastage_date
    FROM wastage_records wr
    JOIN blood_inventory bi ON wr.blood_inventory_id = bi.id
    WHERE bi.blood_bank_id = :blood_bank_id
    " . ($startDate && $endDate ? "AND DATE(wr.recorded_at) BETWEEN :start_date AND :end_date" : "") . "
    " . ($bloodType ? "AND bi.blood_type = :blood_type" : "") . "
    GROUP BY blood_type, reason, wastage_date
    ORDER BY wastage_date DESC
");
$wastageStmt->execute($params);
$wastageStats = $wastageStmt->fetchAll();

// Get top hospitals
$topHospitalsStmt = $pdo->prepare("
    SELECT 
        h.name as hospital_name,
        COUNT(bt.id) as request_count,
        SUM(bt.quantity_ml) as total_ml_requested
    FROM blood_transfers bt
    JOIN hospitals h ON bt.hospital_id = h.id
    WHERE bt.blood_bank_id = :blood_bank_id
    AND bt.status = 'completed'
    " . ($startDate && $endDate ? "AND DATE(bt.transfer_date) BETWEEN :start_date AND :end_date" : "") . "
    GROUP BY h.id, h.name
    ORDER BY total_ml_requested DESC
    LIMIT 5
");
$topHospitalsStmt->execute($params);
$topHospitals = $topHospitalsStmt->fetchAll();

// Get blood types for filter dropdown
$bloodTypesStmt = $pdo->query("SELECT DISTINCT blood_type FROM blood_inventory ORDER BY blood_type");
$bloodTypes = $bloodTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Include header
$pageTitle = "Analytics & Reports";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Analytics & Reports</h1>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Report Filters</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="blood_type" class="form-label">Blood Type</label>
                            <select class="form-select" id="blood_type" name="blood_type">
                                <option value="">All Types</option>
                                <?php foreach ($bloodTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo $bloodType === $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="analytics.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase">Total Donations</h6>
                            <h2 class="mb-0">
                                <?php echo array_sum(array_column($donationStats, 'donation_count')); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase">Total Distributed (ml)</h6>
                            <h2 class="mb-0">
                                <?php echo number_format(array_sum(array_column($distributionStats, 'total_ml_distributed'))); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase">Unique Donors</h6>
                            <h2 class="mb-0">
                                <?php echo array_sum(array_column($donationStats, 'unique_donors')); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase">Wastage (ml)</h6>
                            <h2 class="mb-0">
                                <?php echo number_format(array_sum(array_column($wastageStats, 'total_ml_wasted'))); ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Donation Statistics -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Donation Statistics by Blood Type</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($donationStats)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Donations</th>
                                                <th>Total (ml)</th>
                                                <th>Avg/Donation</th>
                                                <th>Passed Tests</th>
                                                <th>Failed Tests</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($donationStats as $type => $stats): ?>
                                                <tr>
                                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($type); ?></span></td>
                                                    <td><?php echo $stats['donation_count'] ?? 0; ?></td>
                                                    <td><?php echo number_format($stats['total_ml'] ?? 0); ?> ml</td>
                                                    <td><?php echo number_format($stats['avg_donation_ml'] ?? 0, 1); ?> ml</td>
                                                    <td class="text-success"><?php echo $stats['passed_screening'] ?? 0; ?></td>
                                                    <td class="text-danger"><?php echo $stats['failed_screening'] ?? 0; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No donation data available for the selected filters.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Distribution Statistics -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Distribution Statistics</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($distributionStats)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Transfers</th>
                                                <th>Total Distributed (ml)</th>
                                                <th>Hospitals Served</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($distributionStats as $type => $stats): ?>
                                                <tr>
                                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($type); ?></span></td>
                                                    <td><?php echo $stats['transfer_count'] ?? 0; ?></td>
                                                    <td><?php echo number_format($stats['total_ml_distributed'] ?? 0); ?> ml</td>
                                                    <td><?php echo $stats['hospitals_served'] ?? 0; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No distribution data available for the selected filters.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Hospitals and Wastage -->
            <div class="row">
                <!-- Top Hospitals -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Top Hospitals by Blood Requests</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($topHospitals)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Hospital</th>
                                                <th>Requests</th>
                                                <th>Total (ml)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topHospitals as $hospital): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($hospital['hospital_name']); ?></td>
                                                    <td><?php echo $hospital['request_count']; ?></td>
                                                    <td><?php echo number_format($hospital['total_ml_requested']); ?> ml</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No hospital data available for the selected filters.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Wastage -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Blood Wastage</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($wastageStats)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Blood Type</th>
                                                <th>Quantity (ml)</th>
                                                <th>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($wastageStats as $wastage): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($wastage['wastage_date'])); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($wastage['blood_type']); ?></span></td>
                                                    <td><?php echo number_format($wastage['total_ml_wasted']); ?> ml</td>
                                                    <td><?php echo ucfirst($wastage['reason']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No wastage recorded for the selected filters.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export Options -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h5 class="mb-3">Export Report</h5>
                    <a href="export_report.php?type=pdf&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" 
                       class="btn btn-danger me-2">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </a>
                    <a href="export_report.php?type=excel&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export as Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js for future chart implementations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Example chart initialization (can be expanded with real data)
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('donationChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($donationStats)); ?>,
                datasets: [{
                    label: 'Donations by Blood Type',
                    data: <?php echo json_encode(array_column($donationStats, 'donation_count')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
