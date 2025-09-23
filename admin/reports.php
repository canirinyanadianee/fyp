<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn as PDO

// Date filters
$range = $_GET['range'] ?? 'this_month';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

// Resolve start/end
$today = new DateTime('today');
switch ($range) {
    case 'today':
        $start = clone $today; $end = clone $today; break;
    case 'yesterday':
        $start = (clone $today)->modify('-1 day'); $end = (clone $today)->modify('-1 day'); break;
    case 'this_week':
        $start = (clone $today)->modify('monday this week'); $end = (clone $today)->modify('sunday this week'); break;
    case 'last_week':
        $start = (clone $today)->modify('monday last week'); $end = (clone $today)->modify('sunday last week'); break;
    case 'last_month':
        $start = (clone $today)->modify('first day of last month'); $end = (clone $today)->modify('last day of last month'); break;
    case 'this_month':
    default:
        $start = (clone $today)->modify('first day of this month'); $end = (clone $today)->modify('last day of this month'); break;
}
if ($range === 'custom' && $customStart && $customEnd) {
    $start = new DateTime($customStart);
    $end = new DateTime($customEnd);
}

$startStr = $start->format('Y-m-d');
$endStr = $end->format('Y-m-d');

// KPIs
function fetchOneVal($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? array_values($row)[0] : 0;
}

$totalDonations = fetchOneVal($conn, "SELECT COUNT(*) FROM blood_donations WHERE DATE(donation_date) BETWEEN ? AND ?", [$startStr, $endStr]);
$donorsCount = fetchOneVal($conn, "SELECT COUNT(*) FROM donors", []);
$banksCount = fetchOneVal($conn, "SELECT COUNT(*) FROM blood_banks", []);
$hospitalsCount = fetchOneVal($conn, "SELECT COUNT(*) FROM hospitals", []);
$inventoryUnits = fetchOneVal($conn, "SELECT COALESCE(SUM(quantity_ml),0) FROM blood_inventory WHERE expiry_date > CURDATE()", []);

// Donations trend (by day)
$donationTrend = [];
try {
    $stmt = $conn->prepare("SELECT DATE(donation_date) d, COUNT(*) c FROM blood_donations WHERE DATE(donation_date) BETWEEN ? AND ? GROUP BY DATE(donation_date) ORDER BY d");
    $stmt->execute([$startStr, $endStr]);
    $donationTrend = $stmt->fetchAll();
} catch (Exception $e) {}

// Inventory by blood type
$inventoryByType = [];
try {
    $stmt = $conn->query("SELECT blood_type bt, COALESCE(SUM(quantity_ml),0) qty FROM blood_inventory WHERE expiry_date > CURDATE() GROUP BY blood_type ORDER BY bt");
    $inventoryByType = $stmt->fetchAll();
} catch (Exception $e) {}

// Requests breakdown
$requestsBreakdown = [];
try {
    $stmt = $conn->prepare("SELECT status st, COUNT(*) c FROM blood_requests WHERE DATE(request_date) BETWEEN ? AND ? GROUP BY status");
    $stmt->execute([$startStr, $endStr]);
    $requestsBreakdown = $stmt->fetchAll();
} catch (Exception $e) {}

include '../includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Main Dashboard Card -->
            <div class="card shadow-lg border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white d-flex align-items-center justify-content-between">
                    <h4 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Reports Dashboard</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Analyze activity between <strong><?php echo htmlspecialchars($startStr); ?></strong> and <strong><?php echo htmlspecialchars($endStr); ?></strong>.</p>

                    <!-- KPI Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Donations</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int)$totalDonations; ?></div>
                                        </div>
                                        <div class="text-primary"><i class="fas fa-hand-holding-medical fa-2x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Donors</div>
                                            <div class="fs-4 fw-semibold"><?php echo (int)$donorsCount; ?></div>
                                        </div>
                                        <div class="text-success"><i class="fas fa-users fa-2x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Units In Inventory (ml)</div>
                                            <div class="fs-4 fw-semibold"><?php echo number_format((int)$inventoryUnits); ?></div>
                                        </div>
                                        <div class="text-danger"><i class="fas fa-boxes fa-2x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-muted small">Facilities</div>
                                            <div class="fs-5 fw-semibold"><?php echo (int)$banksCount; ?> Banks · <?php echo (int)$hospitalsCount; ?> Hospitals</div>
                                        </div>
                                        <div class="text-secondary"><i class="fas fa-hospital fa-2x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form class="row gy-2 gx-3 align-items-end mb-4" method="get">
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
                            <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($startStr); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End</label>
                            <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($endStr); ?>">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
                        </div>
                    </form>

                    <!-- Charts -->
                    <div class="row g-4 mb-4">
                        <div class="col-lg-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>Donations Trend</div>
                                <div class="card-body"><canvas id="donationsTrend"></canvas></div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light fw-bold"><i class="fas fa-vials me-2 text-danger"></i>Inventory by Blood Type</div>
                                <div class="card-body"><canvas id="inventoryByType"></canvas></div>
                            </div>
                        </div>
                        <!-- <div class="col-lg-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light fw-bold"><i class="fas fa-notes-medical me-2 text-success"></i>Requests Breakdown</div>
                                <div class="card-body"><canvas id="requestsBreakdown"></canvas></div>
                            </div>
                        </div> -->
                    </div>

                    <!-- Report Categories -->
                    <div class="row g-4">
                        <!-- Donation Reports -->
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm border-0 hover-shadow">
                                <div class="card-header bg-light fw-bold">
                                    <i class="fas fa-hand-holding-medical me-2 text-danger"></i> Donation Reports
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item border-0">
                                            <a href="report_donations.php?type=daily" class="text-decoration-none">
                                                <i class="fas fa-calendar-day me-2 text-primary"></i> Daily Donations
                                            </a>
                                        </li>
                                        <li class="list-group-item border-0">
                                            <a href="report_donations.php?type=monthly" class="text-decoration-none">
                                                <i class="fas fa-calendar-alt me-2 text-success"></i> Monthly Donations
                                            </a>
                                        </li>
                                        <li class="list-group-item border-0">
                                            <a href="report_donors.php" class="text-decoration-none">
                                                <i class="fas fa-users me-2 text-warning"></i> Donor Statistics
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Reports -->
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm border-0 hover-shadow">
                                <div class="card-header bg-light fw-bold">
                                    <i class="fas fa-boxes me-2 text-info"></i> Inventory Reports
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item border-0">
                                            <a href="report_inventory.php?type=current" class="text-decoration-none">
                                                <i class="fas fa-box-open me-2 text-primary"></i> Current Inventory
                                            </a>
                                        </li>
                                        <li class="list-group-item border-0">
                                            <a href="report_inventory.php?type=expiring" class="text-decoration-none">
                                                <i class="fas fa-clock me-2 text-danger"></i> Expiring Soon
                                            </a>
                                        </li>
                                        <li class="list-group-item border-0">
                                            <a href="report_utilization.php" class="text-decoration-none">
                                                <i class="fas fa-chart-pie me-2 text-success"></i> Utilization Rate
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- System Reports -->
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm border-0 hover-shadow">
                                <div class="card-header bg-light fw-bold">
                                    <i class="fas fa-cogs me-2 text-secondary"></i> System Reports
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item border-0">
                                            <a href="report_audit.php" class="text-decoration-none">
                                                <i class="fas fa-clipboard-list me-2 text-info"></i> Audit Logs
                                            </a>
                                        </li>
                                        <li class="list-group-item border-0">
                                            <a href="report_users.php" class="text-decoration-none">
                                                <i class="fas fa-user-shield me-2 text-primary"></i> User Activity
                                            </a>
                                        </li>
                                        <li class="list-group-item border-0">
                                            <a href="report_system.php" class="text-decoration-none">
                                                <i class="fas fa-server me-2 text-dark"></i> System Performance
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Report Form -->
                    <div class="card shadow-sm border-0 mt-5">
                        <div class="card-header bg-gradient-light fw-bold">
                            <i class="fas fa-file-alt me-2 text-primary"></i> Generate Custom Report
                        </div>
                        <div class="card-body">
                            <form id="customReportForm" action="#" onsubmit="event.preventDefault(); window.print();">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Report Type</label>
                                        <select class="form-select" required>
                                            <option value="">Select Report Type</option>
                                            <option value="donation_summary">Donation Summary</option>
                                            <option value="inventory_status">Inventory Status</option>
                                            <option value="user_activity">User Activity</option>
                                            <option value="custom_query">Custom Query</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Date Range</label>
                                        <select class="form-select">
                                            <option value="today">Today</option>
                                            <option value="yesterday">Yesterday</option>
                                            <option value="this_week" selected>This Week</option>
                                            <option value="last_week">Last Week</option>
                                            <option value="this_month">This Month</option>
                                            <option value="last_month">Last Month</option>
                                            <option value="custom">Custom Range</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Format</label>
                                        <select class="form-select">
                                            <option value="html" selected>Web View</option>
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                            <option value="csv">CSV</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="includeCharts">
                                            <label class="form-check-label" for="includeCharts">Include Charts</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="includeDetails" checked>
                                            <label class="form-check-label" for="includeDetails">Include Detailed Data</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-file-export me-2"></i> Generate Report
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-print me-2"></i> Print Preview
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const donationLabels = <?php echo json_encode(array_map(fn($r)=>$r['d'], $donationTrend)); ?>;
const donationValues = <?php echo json_encode(array_map(fn($r)=>(int)$r['c'], $donationTrend)); ?>;
const invLabels = <?php echo json_encode(array_map(fn($r)=>$r['bt'], $inventoryByType)); ?>;
const invValues = <?php echo json_encode(array_map(fn($r)=>(int)$r['qty'], $inventoryByType)); ?>;
const reqLabels = <?php echo json_encode(array_map(fn($r)=>($r['st'] ?? 'unknown'), $requestsBreakdown)); ?>;
const reqValues = <?php echo json_encode(array_map(fn($r)=>(int)$r['c'], $requestsBreakdown)); ?>;

new Chart(document.getElementById('donationsTrend'), {
  type: 'line',
  data: { labels: donationLabels, datasets: [{ label: 'Donations', data: donationValues, borderColor: 'rgba(13,110,253,0.9)', backgroundColor: 'rgba(13,110,253,0.1)', tension: 0.3, fill: true }]},
  options: { responsive: true, scales: { y: { beginAtZero: true }}}
});

new Chart(document.getElementById('inventoryByType'), {
  type: 'bar',
  data: { labels: invLabels, datasets: [{ label: 'Quantity (ml)', data: invValues, backgroundColor: 'rgba(220,53,69,0.7)'}]},
  options: { indexAxis: 'y', responsive: true, scales: { x: { beginAtZero: true }}}
});

new Chart(document.getElementById('requestsBreakdown'), {
  type: 'doughnut',
  data: { labels: reqLabels, datasets: [{ data: reqValues, backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6c757d'] }]},
  options: { responsive: true, plugins: { legend: { position: 'bottom' }}}
});
</script>

<?php include '../includes/footer.php'; ?>
