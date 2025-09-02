<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

// start with logged in user id
$user_id = $_SESSION['user_id'];

// Resolve hospital id (use hospitals.id where hospitals.user_id = ?)
$hosp_q = "SELECT id FROM hospitals WHERE user_id = ? LIMIT 1";
$hosp_stmt = $conn->prepare($hosp_q);
$hosp_stmt->bind_param('i', $user_id);
$hosp_stmt->execute();
$hosp_res = $hosp_stmt->get_result();
$hosp_row = $hosp_res->fetch_assoc();
$hospital_id = $hosp_row['id'] ?? $user_id; // fallback to user_id for backward compatibility

// Helper: safe number formatting that tolerates nulls (prevents deprecation warnings)
function fmt_number($val, $decimals = 0) {
    if ($val === null || $val === '') {
        $val = 0;
    }
    // ensure numeric type
    return number_format((float)$val, (int)$decimals);
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$blood_type_filter = $_GET['blood_type'] ?? '';
$procedure_filter = $_GET['purpose'] ?? '';

// Build WHERE clause for filters
$where_conditions = ["hospital_id = ?"];
$params = [$hospital_id];
$param_types = "i";

if (!empty($blood_type_filter)) {
    $where_conditions[] = "blood_type = ?";
    $params[] = $blood_type_filter;
    $param_types .= "s";
}

if (!empty($procedure_filter)) {
    $where_conditions[] = "purpose = ?";
    $params[] = $procedure_filter;
    $param_types .= "s";
}

$where_conditions[] = "usage_date BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;
$param_types .= "ss";

$where_clause = implode(" AND ", $where_conditions);

// Get usage statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_procedures,
        SUM(quantity_ml) as total_quantity,
        AVG(quantity_ml) as avg_quantity,
        blood_type,
        purpose
    FROM blood_usage 
    WHERE $where_clause
    GROUP BY blood_type, purpose
    ORDER BY total_quantity DESC
";

$stats_stmt = $conn->prepare($stats_query);
if ($stats_stmt) {
    $stats_stmt->bind_param($param_types, ...$params);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $usage_stats = [];
    while ($row = $stats_result->fetch_assoc()) {
        $usage_stats[] = $row;
    }
} else {
    $usage_stats = [];
}

// Get overall summary
$summary_query = "
    SELECT 
        COUNT(*) as total_procedures,
        SUM(quantity_ml) as total_quantity,
        COUNT(DISTINCT blood_type) as blood_types_used,
        COUNT(DISTINCT purpose) as procedure_types
    FROM blood_usage 
    WHERE $where_clause
";

$summary_stmt = $conn->prepare($summary_query);
$summary = ['total_procedures' => 0, 'total_quantity' => 0, 'blood_types_used' => 0, 'procedure_types' => 0];
if ($summary_stmt) {
    $summary_stmt->bind_param($param_types, ...$params);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary = $summary_result->fetch_assoc();
}

// Get detailed usage records
$usage_query = "
    SELECT * FROM blood_usage 
    WHERE $where_clause
    ORDER BY usage_date DESC
";

$usage_stmt = $conn->prepare($usage_query);
$usage_records = [];
if ($usage_stmt) {
    $usage_stmt->bind_param($param_types, ...$params);
    $usage_stmt->execute();
    $usage_result = $usage_stmt->get_result();
    while ($row = $usage_result->fetch_assoc()) {
        $usage_records[] = $row;
    }
}

// Get blood type usage for chart
$chart_query = "
    SELECT blood_type, SUM(quantity_ml) as total_quantity 
    FROM blood_usage 
    WHERE $where_clause
    GROUP BY blood_type 
    ORDER BY total_quantity DESC
";

$chart_stmt = $conn->prepare($chart_query);
$chart_data = [];
if ($chart_stmt) {
    $chart_stmt->bind_param($param_types, ...$params);
    $chart_stmt->execute();
    $chart_result = $chart_stmt->get_result();
    while ($row = $chart_result->fetch_assoc()) {
        $chart_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usage Reports - Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Include header -->
    <?php include '../includes/hospital_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-light sidebar py-3">
                <h6 class="text-muted mb-3">HOSPITAL MENU</h6>
                <div class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-chart-bar"></i> Blood Inventory
                    </a>
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-plus-circle"></i> Request Blood
                    </a>
                    <a class="nav-link" href="blood_requests.php">
                        <i class="fas fa-list"></i> Blood Requests
                    </a>
                    <a class="nav-link" href="record_usage.php">
                        <i class="fas fa-chart-line"></i> Record Usage
                    </a>
                    <a class="nav-link active" href="usage_reports.php">
                        <i class="fas fa-file-alt"></i> Usage Reports
                    </a>
                    <a class="nav-link" href="ai_predictions.php">
                        <i class="fas fa-robot"></i> AI Predictions
                    </a>
                    <a class="nav-link" href="nearby_bloodbanks.php">
                        <i class="fas fa-map-marker-alt"></i> Nearby Blood Banks
                    </a>
                    <a class="nav-link" href="staff_management.php">
                        <i class="fas fa-users"></i> Staff Management
                    </a>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Usage Reports</h1>
                    <div>
                        <button class="btn btn-outline-primary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <button class="btn btn-outline-success" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="blood_type" class="form-label">Blood Type</label>
                                <select class="form-select" id="blood_type" name="blood_type">
                                    <option value="">All Types</option>
                                    <option value="A+" <?php echo ($blood_type_filter === 'A+') ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($blood_type_filter === 'A-') ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($blood_type_filter === 'B+') ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($blood_type_filter === 'B-') ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($blood_type_filter === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($blood_type_filter === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($blood_type_filter === 'O+') ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($blood_type_filter === 'O-') ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="purpose" class="form-label">Procedure</label>
                                <select class="form-select" id="purpose" name="purpose">
                                    <option value="">All Procedures</option>
                                    <option value="surgery" <?php echo ($procedure_filter === 'surgery') ? 'selected' : ''; ?>>Surgery</option>
                                    <option value="trauma" <?php echo ($procedure_filter === 'trauma') ? 'selected' : ''; ?>>Trauma</option>
                                    <option value="transfusion" <?php echo ($procedure_filter === 'transfusion') ? 'selected' : ''; ?>>Transfusion</option>
                                    <option value="emergency" <?php echo ($procedure_filter === 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                                    <option value="routine" <?php echo ($procedure_filter === 'routine') ? 'selected' : ''; ?>>Routine</option>
                                    <option value="other" <?php echo ($procedure_filter === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo fmt_number($summary['total_procedures']); ?></h3>
                                <p class="card-text">Total Procedures</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo fmt_number($summary['total_quantity']); ?> ml</h3>
                                <p class="card-text">Total Blood Used</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo $summary['blood_types_used']; ?></h3>
                                <p class="card-text">Blood Types Used</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo $summary['procedure_types']; ?></h3>
                                <p class="card-text">Procedure Types</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Usage Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Blood Usage by Type</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="usageChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Statistics -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Usage Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($usage_stats)): ?>
                                    <p class="text-muted">No usage data found for the selected period.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Blood Type</th>
                                                    <th>Procedure</th>
                                                    <th>Count</th>
                                                    <th>Total (ml)</th>
                                                    <th>Avg (ml)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usage_stats as $stat): ?>
                                                    <tr>
                                                        <td><span class="badge bg-primary"><?php echo $stat['blood_type']; ?></span></td>
                                                        <td><?php echo ucfirst($stat['purpose']); ?></td>
                                                        <td><?php echo $stat['total_procedures']; ?></td>
                                                        <td><?php echo fmt_number($stat['total_quantity']); ?></td>
                                                        <td><?php echo fmt_number($stat['avg_quantity'], 0); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Records -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Detailed Usage Records</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($usage_records)): ?>
                            <p class="text-muted">No usage records found for the selected period.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Blood Type</th>
                                            <th>Quantity</th>
                                            <th>Patient</th>
                                            <th>Procedure</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usage_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($record['usage_date'])); ?></td>
                                                <td><span class="badge bg-primary"><?php echo $record['blood_type']; ?></span></td>
                                                <td><?php echo fmt_number($record['quantity_ml']); ?> ml</td>
                                                <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                                                <td><?php echo ucfirst($record['purpose']); ?></td>
                                                <td>
                                                    <?php if (!empty($record['notes'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($record['notes'], 0, 50)); ?><?php echo strlen($record['notes']) > 50 ? '...' : ''; ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configuration
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        if (chartData.length > 0) {
            const ctx = document.getElementById('usageChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.map(item => item.blood_type),
                    datasets: [{
                        data: chartData.map(item => item.total_quantity),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed.toLocaleString() + ' ml';
                                }
                            }
                        }
                    }
                }
            });
        }

        function printReport() {
            window.print();
        }

        function exportReport() {
            // Simple CSV export
            let csv = 'Date,Blood Type,Quantity (ml),Patient,Procedure,Notes\n';
            <?php foreach ($usage_records as $record): ?>
                csv += '<?php echo date("Y-m-d", strtotime($record["usage_date"])); ?>,<?php echo $record["blood_type"]; ?>,<?php echo $record["quantity_ml"]; ?>,"<?php echo str_replace('"', '""', $record["patient_name"]); ?>",<?php echo $record["purpose"]; ?>,"<?php echo str_replace('"', '""', $record["notes"] ?? ""); ?>"\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'blood_usage_report_<?php echo $start_date; ?>_to_<?php echo $end_date; ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
