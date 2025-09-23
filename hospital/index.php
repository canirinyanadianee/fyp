<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get blood request statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_requests
    FROM blood_requests 
    WHERE hospital_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Resolve hospital_id from hospitals table for AI queries
// Note: older schema may not include latitude/longitude columns. Select id only and keep lat/lon null
$hstmt = $conn->prepare("SELECT id FROM hospitals WHERE user_id = ? LIMIT 1");
$hstmt->bind_param('i', $user_id);
$hstmt->execute();
$hres = $hstmt->get_result();
$hrow = $hres->fetch_assoc();
$hospital_id = $hrow['id'] ?? $user_id;
$hospital_lat = null;
$hospital_lon = null;

// Get blood inventory with detailed information
$inventory_query = "SELECT 
    blood_type,
    SUM(quantity_ml) as total_quantity,
    FLOOR(SUM(quantity_ml) / 450) as unit_count
    FROM blood_inventory 
    WHERE expiry_date > CURDATE()
    GROUP BY blood_type
    ORDER BY blood_type";
$inventory_stmt = $conn->prepare($inventory_query);
$inventory_stmt->execute();
$inventory_result = $inventory_stmt->get_result();
$inventory_data = [];
while ($row = $inventory_result->fetch_assoc()) {
    $inventory_data[] = $row;
}

// Create complete blood type array with all types
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$inventory = [];
foreach ($blood_types as $type) {
    $found = false;
    foreach ($inventory_data as $item) {
        if ($item['blood_type'] === $type) {
            $inventory[$type] = $item;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $inventory[$type] = [
            'blood_type' => $type,
            'total_quantity' => 0,
            'unit_count' => 0
        ];
    }
}

// Get recent blood requests
$recent_requests_query = "SELECT br.* 
    FROM blood_requests br 
    WHERE br.hospital_id = ? 
    ORDER BY br.created_at DESC 
    LIMIT 5";
$recent_stmt = $conn->prepare($recent_requests_query);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_requests = [];
while ($row = $recent_result->fetch_assoc()) {
    $recent_requests[] = $row;
}

// Mock AI predictions data (in real app, this would come from AI service)
// Load persisted ai_predictions/ai_forecasts for this hospital (if any)
$predictions = [];
$pf_stmt = $conn->prepare("SELECT blood_type, prediction, confidence, target_date FROM ai_predictions WHERE hospital_id = ? ORDER BY created_at DESC LIMIT 100");
if ($pf_stmt) {
    $pf_stmt->bind_param('i', $hospital_id);
    $pf_stmt->execute();
    $pf_res = $pf_stmt->get_result();
    while ($prow = $pf_res->fetch_assoc()) {
        $bt = $prow['blood_type'];
        if (!isset($predictions[$bt])) {
            $predictions[$bt] = ['current' => $inventory[$bt]['total_quantity'], 'predicted' => 0, 'confidence' => 0];
        }
        // prediction column stored as JSON; keep last seen
        $pred_data = json_decode($prow['prediction'], true);
        if ($pred_data) {
            $predictions[$bt]['predicted'] = $pred_data['predicted_usage'] ?? ($predictions[$bt]['predicted'] ?? 0);
        }
        $predictions[$bt]['confidence'] = $prow['confidence'] ?? $predictions[$bt]['confidence'];
    }
}
// fill missing types with defaults
foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt) {
    if (!isset($predictions[$bt])) {
        $predictions[$bt] = ['current' => $inventory[$bt]['total_quantity'], 'predicted' => 0, 'confidence' => 0];
    }
}

// Load 7-day aggregated forecasts (sum across blood types) for chart
$forecast_labels = [];
$forecast_values = [];
$fc_stmt = $conn->prepare("SELECT forecast_date, IFNULL(SUM(predicted_quantity_ml),0) as total_pred FROM ai_forecasts WHERE hospital_id = ? AND forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY) GROUP BY forecast_date ORDER BY forecast_date");
if ($fc_stmt) {
    $fc_stmt->bind_param('i', $hospital_id);
    $fc_stmt->execute();
    $fc_res = $fc_stmt->get_result();
    while ($frow = $fc_res->fetch_assoc()) {
        $forecast_labels[] = date('M j', strtotime($frow['forecast_date']));
        $forecast_values[] = (int)$frow['total_pred'];
    }
}

// Calculate usage statistics
$this_month_query = "SELECT SUM(quantity_ml) as total FROM blood_requests WHERE hospital_id = ? AND status = 'completed'";
$this_month_stmt = $conn->prepare($this_month_query);
$this_month_stmt->bind_param("i", $user_id);
$this_month_stmt->execute();
$this_month_result = $this_month_stmt->get_result();
$this_month_usage = $this_month_result->fetch_assoc()['total'] ?? 0;

$last_month_query = "SELECT SUM(quantity_ml) as total FROM blood_requests WHERE hospital_id = ? AND status = 'completed'";
$last_month_stmt = $conn->prepare($last_month_query);
$last_month_stmt->bind_param("i", $user_id);
$last_month_stmt->execute();
$last_month_result = $last_month_stmt->get_result();
$last_month_usage = $last_month_result->fetch_assoc()['total'] ?? 0;

// Mock chart data for blood usage by type
$chart_data = [
    'A+' => 700, 'A-' => 750, 'B+' => 800, 'B-' => 850,
    'AB+' => 400, 'AB-' => 1250, 'O+' => 600, 'O-' => 500
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Blood Management System | Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Advanced dashboard design: glassmorphism, micro-interactions, animations */
        :root{
            --bg-1: #f4f7fb;
            --bg-2: #eef3f7;
            --card: rgba(255,255,255,0.85);
            --muted: #6b7280;
            --accent-1: #0ea5a4; /* teal */
            --accent-2: #0f766e; /* darker */
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --glass-border: rgba(255,255,255,0.6);
            --radius: 16px;
            --shadow-lg: 0 12px 40px rgba(15,23,42,0.08);
            --smooth: cubic-bezier(.2,.9,.2,1);
            --chart-1: #06b6d4;
            --chart-2: #06b6a4;
            --chart-3: #60a5fa;
        }

        html,body{height:100%}
        body{
            font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
            background: linear-gradient(180deg,var(--bg-1) 0%, var(--bg-2) 100%);
            color: #071133; margin:0; padding:0; -webkit-font-smoothing:antialiased;
        }

        /* Header */
        .header{
            background: linear-gradient(90deg,var(--accent-1),var(--accent-2));
            color: #fff; padding:18px 0; box-shadow:0 8px 30px rgba(15,23,42,0.08);
        }
        .header .navbar-brand{font-weight:800; letter-spacing:.3px}
        .header .nav-link{color:rgba(255,255,255,0.95)!important}

        /* Page layout */
        .dashboard-content{padding:28px 22px}
        .main-wrapper{display:flex; gap:28px; align-items:flex-start}
        .sidebar{width:280px; flex-shrink:0}
        .content-area{flex:1}
        .user-profile{width:340px; flex-shrink:0}

        /* Reusable glass card */
        .glass-card{background:var(--card); backdrop-filter: blur(8px) saturate(120%); -webkit-backdrop-filter: blur(8px) saturate(120%); border-radius:var(--radius); border:1px solid var(--glass-border); box-shadow:var(--shadow-lg); overflow:hidden}
        .glass-card .card-body{padding:18px}
        .card-animate{transform:translateY(0); transition:transform .45s var(--smooth), box-shadow .45s var(--smooth)}
        .card-animate:hover{transform:translateY(-8px); box-shadow:0 20px 50px rgba(15,23,42,0.12)}

        /* Sidebar */
        .sidebar-nav{padding:18px}
        .sidebar-nav .nav-link{display:flex; align-items:center; gap:12px; color:var(--muted); padding:10px 14px; border-radius:10px; margin-bottom:10px}
        .sidebar-nav .nav-link i{width:20px; text-align:center}
        .sidebar-nav .nav-link.active, .sidebar-nav .nav-link:hover{background:linear-gradient(90deg, rgba(14,165,164,0.08), rgba(15,118,110,0.04)); color:var(--accent-2); font-weight:700}

        /* Top stats */
        .stats-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:18px}
        .stat{padding:14px; border-radius:12px; display:flex; align-items:center; gap:12px}
        .stat .icon{width:44px; height:44px; border-radius:10px; display:grid; place-items:center; color:#fff}
        .stat h4{margin:0; font-size:1rem}
        .stat p{margin:0; color:var(--muted); font-size:0.85rem}

        /* Blood cards grid */
        .blood-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px}
        .blood-card{padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12ngpx}
        .blood-card .chip{position:absolute; top:10px; right:5px; font-weight:700; font-size:0.8rem; padding:3rem .6rem; border-radius:99px}
        .blood-type{font-size:1.4rem; font-weight:800; color:var(--accent-2)}
        .blood-quantity{font-size:1rem; color:#0b1220}
        .blood-status{color:var(--muted); font-size:0.80rem}
        .status-badge{padding:.20rem ; border-radius:999px; font-weight:800}
        .status-good{background:linear-gradient(90deg,#10b981,#059669); color:#fff}
        .status-low{background:linear-gradient(90deg,#f59e0b,#f97316); color:#fff}
        .status-critical{background:linear-gradient(90deg,#ef4444,#dc2626); color:#fff}

        /* Predictions table */
        .predictions-table{overflow:hidden}
        .predictions-table .table{margin-bottom:0}
        .predictions-table .table th{background:transparent; color:var(--muted); font-weight:700}
        .confidence-bar{height:10px; background:linear-gradient(90deg,#f1f5f9,#f8fafc); border-radius:999px}
        .confidence-fill{height:100%; background:linear-gradient(90deg,var(--chart-1),var(--chart-2)); border-radius:999px}

        /* Chart container */
        .chart-container{padding:14px}

        /* Quick actions */
        .quick-actions{display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px}
        .action-btn{display:flex; align-items:center; gap:10px; justify-content:center; padding:12px; border-radius:10px; color:#fff; font-weight:800; box-shadow:0 8px 24px rgba(2,6,23,0.06)}
        .action-btn.primary{background:linear-gradient(90deg,var(--danger),#c0262c)}
        .action-btn.secondary{background:linear-gradient(90deg,var(--info),#06b6d4)}
        .action-btn.tertiary{background:linear-gradient(90deg,var(--accent-1),var(--accent-2))}

        /* Notifications */
        .notifications-panel{padding:12px}
        .notification-item{background:linear-gradient(180deg,#ffffff, #fbfdff); border-radius:10px}

        /* Profile */
        .profile-card{padding:18px; text-align:center}
        .profile-avatar{width:88px; height:88px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.125rem; background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff}

        /* Table tweaks */
        .table thead th{border-bottom:0; color:var(--muted); font-weight:700}
        .table-hover tbody tr:hover{background:linear-gradient(90deg, rgba(14,165,164,0.03), rgba(14,165,164,0.01))}

        /* Micro animations */
        .fade-in{opacity:0; transform:translateY(6px); animation:fadeIn .6s .12s forwards}
        @keyframes fadeIn{to{opacity:1; transform:none}}

        /* Floating action button */
        .fab{position:fixed; right:28px; bottom:28px; width:56px; height:56px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff; box-shadow:0 14px 40px rgba(15,23,42,0.12); z-index:1200}

        /* Responsive */
        @media (max-width: 1200px){
            .main-wrapper{flex-direction:column}
            .sidebar, .user-profile{width:100%}
            .quick-actions{grid-template-columns:repeat(2,1fr)}
        }

        @media (max-width: 768px){
            .header .nav-link{display:none}
            .dashboard-content{padding:16px}
            .quick-actions{grid-template-columns:1fr}
            .fab{right:16px; bottom:16px}
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <nav class="navbar navbar-expand-lg">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-tint me-2"></i>
                    AI Blood Management System | Hospital
                </a>
                <div class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="inventory.php">Available Inventory</a></li>
                    <li class="nav-item"><a class="nav-link active" href="blood_requests.php">Blood Requests</a></li>
                  
                    <li class="nav-item"><a class="nav-link" href="transfers.php">Transfer History</a></li>
                   
                    <a class="nav-link" href="nearby_bloodbanks.php">
                        <i class="fas fa-hospital me-1"></i>
                        Blood Banks
                    </a>
                    <div class="nav-link">
                        <label class="form-check-label me-2">Dark Mode</label>
                        <input class="form-check-input" type="checkbox" id="darkModeToggle">
                    </div>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($user['username'] ?? 'nityazo'); ?>
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-content">
        <div class="main-wrapper">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-nav">
                    <h6 class="mb-3 text-muted">WELCOME, <?php echo strtoupper($user['username'] ?? 'Diane Diane'); ?></h6>
                    <p class="small text-muted mb-3">Blood Management Dashboard | Today is <?php echo date('l, F j, Y'); ?></p>
                    
                    <div class="nav flex-column">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-chart-bar"></i>
                            Blood Inventory
                        </a>
                        <a class="nav-link" href="requests.php">
                            <i class="fas fa-plus-circle"></i>
                            Request Blood
                        </a>
                        <a class="nav-link" href="blood_requests.php">
                            <i class="fas fa-list"></i>
                            Blood Requests
                        </a>
                        <a class="nav-link" href="record_usage.php">
                            <i class="fas fa-chart-line"></i>
                            Record Usage
                        </a>
                        <a class="nav-link" href="usage_reports.php">
                            <i class="fas fa-file-alt"></i>
                            Usage Reports
                        </a>
                        <a class="nav-link" href="nearby_bloodbanks.php">
                            <i class="fas fa-map-marker-alt"></i>
                            Nearby Blood Banks
                        </a>
                        <a class="nav-link" href="ai_predictions.php">
                            <i class="fas fa-robot"></i>
                            AI Predictions
                        </a>
                      
                        <a class="nav-link" href="staff_management.php">
                            <i class="fas fa-users"></i>
                            Staff Management
                        </a>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            Critical Blood Types
                        </h6>
                        <?php foreach ($blood_types as $type): ?>
                            <?php if ($inventory[$type]['unit_count'] < 5): ?>
                                <div class="d-flex align-items-center justify-content-between mb-2 p-2 bg-light rounded">
                                    <span class="badge bg-danger"><?php echo $type; ?></span>
                                    <a href="requests.php?blood_type=<?php echo urlencode($type); ?>&urgency=urgent&quantity_ml=450" class="btn btn-sm btn-outline-danger">Request</a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="content-area">
                <!-- Blood Inventory Section -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tint text-danger"></i>
                        Blood Inventory
                    </h2>
                    <div>
                        <a href="#" class="view-all-btn me-3">Detailed View</a>
                        <a href="requests.php" class="btn btn-danger">Request Blood</a>
                    </div>
                </div>

                <div class="row mb-4">
                    <?php foreach ($blood_types as $type): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card blood-card">
                                <div class="blood-type <?php echo strpos($type, '+') !== false ? 'positive' : 'negative'; ?>">
                                    <?php echo $type; ?>
                                </div>
                                <div class="blood-quantity">
                                    <?php echo $inventory[$type]['total_quantity']; ?> ml
                                </div>
                                <div class="blood-status">
                                    Available
                                </div>
                                <div class="mb-3">
                                    <?php
                                    $units = $inventory[$type]['unit_count'];
                                    if ($units >= 10) {
                                        echo '<span class="status-badge status-good">Good Stock</span>';
                                    } elseif ($units >= 5) {
                                        echo '<span class="status-badge status-low">Low Stock</span>';
                                    } else {
                                        echo '<span class="status-badge status-critical">Critical</span>';
                                    }
                                    ?>
                                </div>
                                <a href="requests.php?blood_type=<?php echo urlencode($type); ?>&urgency=urgent&quantity_ml=450" class="btn btn-sm btn-danger">
                                    Request Urgently
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- AI Predictions Section -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-robot text-primary"></i>
                        AI Blood Usage Predictions (Next 7 Days)
                    </h2>
                    <a href="ai_predictions.php" class="view-all-btn">View Detailed Predictions</a>
                </div>

                <div class="card predictions-table mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Blood Type</th>
                                    <th>Current Stock</th>
                                    <th>Predicted Usage</th>
                                    <th>Status</th>
                                    <th>Confidence</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictions as $type => $data): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo $type; ?></span></td>
                                        <td><?php echo number_format($data['current']); ?> ml</td>
                                        <td><?php echo number_format($data['predicted']); ?> ml</td>
                                        <td>
                                            <span class="badge bg-danger">Shortage Predicted</span>
                                        </td>
                                        <td>
                                            <div><?php echo $data['confidence']; ?>% confidence</div>
                                            <div class="confidence-bar mt-1">
                                                <div class="confidence-fill" style="width: <?php echo $data['confidence']; ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="requests.php?blood_type=<?php echo urlencode($type); ?>&urgency=urgent&quantity_ml=450" class="btn btn-sm btn-danger">Request Blood</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Forecast chart (7-day) -->
                <div class="card mb-4">
                    <div class="card-header">7-day Forecast (sample)</div>
                    <div class="card-body">
                        <canvas id="forecastChart" height="120"></canvas>
                    </div>
                </div>

                <!-- Usage Chart -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5 class="mb-3">Blood Usage Statistics</h5>
                            <canvas id="usageChart" height="300"></canvas>
                            <div class="usage-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($this_month_usage); ?> ml</div>
                                    <div class="stat-label">This Month</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($last_month_usage); ?> ml</div>
                                    <div class="stat-label">Last Month</div>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <a href="#" class="btn btn-outline-primary">View Detailed Reports</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="quick-actions">
                            <a href="requests.php" class="action-btn primary" role="button">
                                <i class="fas fa-tint"></i>
                                Request Blood
                            </a>
                            <a href="record_usage.php" class="action-btn secondary" role="button">
                                <i class="fas fa-lock"></i>
                                Record Blood Usage
                            </a>
                            <a href="nearby_bloodbanks.php" class="action-btn tertiary" role="button">
                                <i class="fas fa-map-marker-alt"></i>
                                Find Nearby Blood Banks
                            </a>
                            <a href="ai_predictions.php" class="action-btn quaternary" role="button">
                                <i class="fas fa-robot"></i>
                                Run AI Prediction
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list text-info"></i>
                        Recent Blood Requests
                    </h2>
                    <a href="requests.php" class="view-all-btn">View All</a>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Blood Type</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Requested On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_requests)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-info-circle text-muted"></i>
                                            No recent requests found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_requests as $request): ?>
                                        <tr>
                                            <td>#<?php echo $request['id']; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $request['blood_type']; ?></span></td>
                                            <td><?php echo $request['quantity_ml']; ?> ml</td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'completed' => 'bg-success'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $status_class[$request['status']] ?? 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <a href="blood_requests.php?new_id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Profile Sidebar -->
            <div class="user-profile">
                <!-- Critical Notifications -->
                <div class="notifications-panel">
                    <h6 class="d-flex align-items-center justify-content-between mb-3">
                        <span>
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                            Critical Notifications
                        </span>
                        <a href="#" class="view-all-btn">View All</a>
                    </h6>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="fw-bold">No critical notifications at this time.</div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="notifications-panel">
                    <h6 class="d-flex align-items-center justify-content-between mb-3">
                        <span>
                            <i class="fas fa-clock text-warning"></i>
                            Pending Requests
                        </span>
                        <span class="badge bg-warning">0</span>
                    </h6>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="fw-bold">No pending requests at this time.</div>
                        </div>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['username'] ?? 'DD', 0, 2)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['username'] ?? 'Diane Diane'); ?></div>
                    <div class="profile-location">
                        <i class="fas fa-map-marker-alt"></i>
                        MIDUHA
                    </div>
                    
                    <div class="profile-contact">
                        <div class="contact-item">
                            <i class="fas fa-phone text-primary"></i>
                            <strong>Phone:</strong>
                            <span>078736352</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope text-primary"></i>
                            <strong>Email:</strong>
                            <span><?php echo htmlspecialchars($user['email'] ?? 'nityazo@gmail.com'); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-bed text-primary"></i>
                            <strong>Type:</strong>
                            <span>Hospital</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-bed text-primary"></i>
                            <strong>Beds:</strong>
                            <span>0</span>
                        </div>
                    </div>
                    
                    <a href="edit_profile.php" class="btn btn-outline-primary w-100">Edit Profile</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configuration
        const ctx = document.getElementById('usageChart').getContext('2d');
        const chartData = <?php echo json_encode(array_values($chart_data)); ?>;
        const chartLabels = <?php echo json_encode(array_keys($chart_data)); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Usage (ml)',
                    data: chartData,
                    backgroundColor: [
                        '#28a745', '#28a745', '#17a2b8', '#17a2b8',
                        '#fd7e14', '#dc3545', '#6f42c1', '#6f42c1'
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e9ecef'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Forecast chart (7-day)
        const fcLabels = <?php echo json_encode($forecast_labels); ?>;
        const fcValues = <?php echo json_encode($forecast_values); ?>;
        if (fcLabels && fcLabels.length > 0) {
            const fctx = document.getElementById('forecastChart').getContext('2d');
            new Chart(fctx, {
                type: 'line',
                data: {
                    labels: fcLabels,
                    datasets: [{
                        label: 'Predicted Demand (ml)',
                        data: fcValues,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.12)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // Dark mode toggle
        document.getElementById('darkModeToggle').addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
        });

        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
