<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Admin Dashboard | " . APP_NAME;
include '../includes/header.php';
// Ensure common helper functions (e.g., timeAgo) are available
require_once '../includes/functions.php';

// Database connection using PDO
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize stats with default values
$stats = [
    'total_donors' => 0,
    'total_blood_banks' => 0,
    'total_hospitals' => 0,     
    'total_blood_units' => 0,
    'pending_requests' => 0,
    'active_donations' => 0,
    'blood_units' => 0,
    'critical_alerts' => 0,
    'total_donations' => 0
];

try {
    // Fetch statistics with proper error handling
    $stats = [
        'total_donors' => (int)($db->query("SELECT COUNT(*) as count FROM donors")->fetch()['count'] ?? 0),
        'total_blood_banks' => (int)($db->query("SELECT COUNT(*) as count FROM blood_banks")->fetch()['count'] ?? 0),
        'total_hospitals' => (int)($db->query("SELECT COUNT(*) as count FROM hospitals")->fetch()['count'] ?? 0),
        'total_blood_units' => (int)($db->query("SELECT COALESCE(SUM(quantity_ml), 0) as total FROM blood_inventory WHERE status = 'available' AND expiry_date > CURDATE()")->fetch()['total'] ?? 0),
        'pending_requests' => (int)($db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending'")->fetch()['count'] ?? 0),
        'active_donations' => 0, // Default to 0 as the table might not exist
        'blood_units' => (int)($db->query("SELECT COALESCE(SUM(quantity_ml), 0) as total FROM blood_inventory WHERE status = 'available' AND expiry_date > CURDATE()")->fetch()['total'] ?? 0),
        'critical_alerts' => 0, // Default to 0 as the table might not exist
        'total_donations' => (int)($db->query("SELECT COUNT(*) as count FROM blood_donations")->fetch()['count'] ?? 0)
    ];
} catch (PDOException $e) {
    // Log error but continue with default values
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Get blood type distribution
$blood_types = $db->query("SELECT blood_type, COUNT(*) as count FROM donors GROUP BY blood_type")->fetchAll();
$blood_type_data = [];
foreach ($blood_types as $bt) {
    $blood_type_data[$bt['blood_type']] = $bt['count'];
}

// Get recent donations
$recent_donations = $db->query(
    "SELECT bd.*, d.first_name, d.last_name, bb.name as blood_bank_name 
     FROM blood_donations bd 
     JOIN donors d ON bd.donor_id = d.id 
     JOIN blood_banks bb ON bd.blood_bank_id = bb.id 
     ORDER BY bd.donation_date DESC LIMIT 5"
)->fetchAll();

// Get blood inventory status
$inventory = $db->query(
    "SELECT blood_type, SUM(quantity_ml) as total_ml 
     FROM blood_inventory 
     WHERE status = 'available' 
     GROUP BY blood_type"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent activities
$recent_activities = [];
$activities = $db->query(
    "SELECT 'donation' as type, CONCAT('New donation: ', d.first_name, ' ', d.last_name, ' (', bd.blood_type, ')') as description, 
            bd.donation_date as created_at, 'success' as color
     FROM blood_donations bd
     JOIN donors d ON bd.donor_id = d.id
     ORDER BY bd.donation_date DESC LIMIT 5"
)->fetchAll();

foreach ($activities as $activity) {
    $recent_activities[] = [
        'icon' => 'syringe',
        'title' => 'New Donation',
        'desc' => $activity['description'],
        'time' => timeAgo($activity['created_at']),
        'color' => 'success'
    ];
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h6 class="text-white">Admin Panel</h6>
                    <hr class="border-light">
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active text-white" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="Donors.php?table=donors">
                            <i class="fas fa-users me-2"></i>Donor Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="user_management.php">
                            <i class="fas fa-user-cog me-2"></i>User Management Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="system_monitoring.php">
                            <i class="fas fa-eye me-2"></i>System Monitoring
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="blood_request_workflow.php">
                            <i class="fas fa-tint me-2"></i>Blood Request Workflow
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="reports_analytics.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="notifications_center.php">
                            <i class="fas fa-bell me-2"></i>Notifications & Alerts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="ml_insights.php">
                            <i class="fas fa-robot me-2"></i>ML Insights
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="hospitals.php?table=hospitals">
                            <i class="fas fa-hospital me-2"></i>Hospitals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="bloodbanks.php?table=blood_banks">
                            <i class="fas fa-tint me-2"></i>Blood Banks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="ml_dashboard.php">
                            <i class="fas fa-brain me-2"></i>ML Insights
                            <?php if($stats['critical_alerts'] > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $stats['critical_alerts']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="notifications_review.php">
                            <i class="fas fa-bullhorn me-2"></i>Notifications Review
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="reports.php">
                            <i class="fas fa-file-alt me-2"></i>Reports & Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="settings.php">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-white bg-danger bg-opacity-75 rounded" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Admin Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-primary">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Central Dashboard Overview -->
            <div class="row mb-4">
                <div class="col-12 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Central Dashboard: Nationwide Blood Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="card bg-primary bg-opacity-10 border-0 h-100">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-1">Total Blood Units</h6>
                                            <h2 class="mb-0"><?php echo number_format($stats['total_blood_units']); ?></h2>
                                            <div class="form-text">Available nationwide</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning bg-opacity-10 border-0 h-100">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-1">Expiring Soon</h6>
                                            <h2 class="mb-0">--</h2>
                                            <div class="form-text">Units expiring in 7 days</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success bg-opacity-10 border-0 h-100">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-1">Supply vs. Demand</h6>
                                            <h2 class="mb-0">--</h2>
                                            <div class="form-text">Current month</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-danger bg-opacity-10 border-0 h-100">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-1">Shortage Alerts</h6>
                                            <h2 class="mb-0">--</h2>
                                            <div class="form-text">ML predicted</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="ml_dashboard.php" class="btn btn-outline-info me-2"><i class="fas fa-brain me-1"></i>View ML Insights</a>
                                <a href="reports.php" class="btn btn-outline-primary"><i class="fas fa-file-alt me-1"></i>View Reports</a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ML Features Quick Access -->
                <div class="col-md-12 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI & ML Features</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="ml_dashboard.php#anomaly" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Anomaly Detection
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="ml_dashboard.php#forecast" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-chart-line me-2"></i>Demand Forecast
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="ml_donor_matching.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-user-friends me-2"></i>Donor Matching
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="ml_dashboard.php#forecasting" class="btn btn-outline-info w-100">
                                        <i class="fas fa-bullseye me-2"></i>Demand Forecasting
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card bg-primary bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Donors</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_donors']); ?></h2>
                                </div>
                                <div class="bg-primary bg-opacity-25 p-3 rounded-circle">
                                    <i class="fas fa-users text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card bg-success bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Blood Banks</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_blood_banks']); ?></h2>
                                </div>
                                <div class="bg-success bg-opacity-25 p-3 rounded-circle">
                                    <i class="fas fa-hospital text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card bg-info bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Hospitals</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_hospitals']); ?></h2>
                                </div>
                                <div class="bg-info bg-opacity-25 p-3 rounded-circle">
                                    <i class="fas fa-ambulance text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card bg-warning bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Donations</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_donations']); ?></h2>
                                </div>
                                <div class="bg-warning bg-opacity-25 p-3 rounded-circle">
                                    <i class="fas fa-tint text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card bg-danger bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Blood Stock (ml)</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_blood_units']); ?></h2>
                                </div>
                                <div class="bg-danger bg-opacity-25 p-3 rounded-circle">
                                    <i class="fas fa-heartbeat text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card bg-purple bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Pending Requests</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['pending_requests']); ?></h2>
                                </div>
                                <div class="bg-purple bg-opacity-25 p-3 rounded-circle">
                                    <i class="fas fa-bell text-purple"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                    <div class="card bg-success bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <h6 class="text-muted">Active Donations</h6>
                            <h2><?php echo number_format($stats['active_donations']); ?></h2>
                            <span class="text-success small"><i class="fas fa-arrow-up"></i> 8% from last month</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <h6 class="text-muted">Blood Units</h6>
                            <h2><?php echo number_format((float)($stats['blood_units'] ?? 0)); ?></h2>
                            <span class="text-danger small"><i class="fas fa-arrow-down"></i> 5% needs attention</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-danger bg-opacity-10 border-0 h-100">
                        <div class="card-body">
                            <h6 class="text-muted">Critical Alerts</h6>
                            <h2><?php echo (int)($stats['critical_alerts'] ?? 0); ?></h2>
                            <a href="ml_dashboard.php" class="text-danger small">View Alerts</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <!-- Blood Type Distribution and Recent Donations -->
            <div class="row mb-4">
                <!-- Blood Type Distribution -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Blood Type Distribution</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="bloodTypeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Donations -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Recent Donations</h6>
                            <a href="donations.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_donations as $donation): ?>
                                <div class="list-group-item border-0">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-soft-<?php echo strtolower(str_replace('+', 'p', $donation['blood_type'])); ?> rounded p-2 me-3">
                                            <i class="fas fa-tint text-<?php echo strtolower(str_replace('+', 'p', $donation['blood_type'])); ?> fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?></h6>
                                            <p class="text-muted small mb-0">
                                                <?php echo $donation['quantity_ml']; ?>ml • 
                                                <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-soft-<?php echo strtolower($donation['status']); ?> text-<?php echo strtolower($donation['status']); ?>">
                                            <?php echo ucfirst($donation['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Blood Inventory and Recent Activities -->
            <div class="row">
                <!-- Blood Inventory -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0">Blood Inventory Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Blood Type</th>
                                            <th>Quantity (ml)</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        foreach ($blood_types as $type): 
                                            $quantity = $inventory[$type] ?? 0;
                                            $status = 'Critical';
                                            $status_class = 'danger';
                                            
                                            if ($quantity > 1000) {
                                                $status = 'Good';
                                                $status_class = 'success';
                                            } elseif ($quantity > 500) {
                                                $status = 'Low';
                                                $status_class = 'warning';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-soft-<?php echo strtolower(str_replace('+', 'p', $type)); ?> rounded p-2 me-3">
                                                        <i class="fas fa-tint text-<?php echo strtolower(str_replace('+', 'p', $type)); ?>"></i>
                                                    </div>
                                                    <span class="fw-medium"><?php echo $type; ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($quantity); ?> ml</td>
                                            <td><span class="badge bg-soft-<?php echo $status_class; ?> text-<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary">Details</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Recent Activities</h6>
                            <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="list-group-item border-0">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <div class="bg-soft-<?php echo $activity['color']; ?> rounded p-2 me-3">
                                                <i class="fas fa-<?php echo $activity['icon']; ?> text-<?php echo $activity['color']; ?>"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo $activity['title']; ?></h6>
                                            <p class="text-muted small mb-0"><?php echo $activity['desc']; ?></p>
                                            <small class="text-muted"><?php echo $activity['time']; ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Chart.js for Blood Type Distribution -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                // Blood Type Distribution Chart
                const bloodTypeCtx = document.getElementById('bloodTypeChart').getContext('2d');
                const bloodTypeChart = new Chart(bloodTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_keys($blood_type_data)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($blood_type_data)); ?>,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(199, 199, 199, 0.7)',
                                'rgba(83, 102, 255, 0.7)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            }
                        }
                    }
                });
            </script>
            
            <style>
                .bg-purple { background-color: #6f42c1; }
                .text-purple { color: #6f42c1; }
                .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1); }
                .bg-soft-success { background-color: rgba(25, 135, 84, 0.1); }
                .bg-soft-info { background-color: rgba(13, 202, 240, 0.1); }
                .bg-soft-warning { background-color: rgba(255, 193, 7, 0.1); }
                .bg-soft-danger { background-color: rgba(220, 53, 69, 0.1); }
                .bg-soft-purple { background-color: rgba(111, 66, 193, 0.1); }
                .bg-soft-ap { background-color: rgba(255, 99, 132, 0.1); }
                .bg-soft-an { background-color: rgba(54, 162, 235, 0.1); }
                .bg-soft-bp { background-color: rgba(255, 206, 86, 0.1); }
                .bg-soft-bn { background-color: rgba(75, 192, 192, 0.1); }
                .bg-soft-abp { background-color: rgba(153, 102, 255, 0.1); }
                .bg-soft-abn { background-color: rgba(255, 159, 64, 0.1); }
                .bg-soft-op { background-color: rgba(199, 199, 199, 0.1); }
                .bg-soft-on { background-color: rgba(83, 102, 255, 0.1); }
                .text-ap { color: #ff6384; }
                .text-an { color: #36a2eb; }
                .text-bp { color: #ffce56; }
                .text-bn { color: #4bc0c0; }
                .text-abp { color: #9966ff; }
                .text-abn { color: #ff9f40; }
                .text-op { color: #c7c7c7; }
                .text-on { color: #5366ff; }
            </style>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($stats['recent_activities'])): ?>
                                    <?php foreach ($stats['recent_activities'] as $activity): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <div class="bg-<?php echo $activity['color'] ?? 'primary'; ?> bg-opacity-10 p-2 rounded-circle">
                                                    <i class="fas fa-<?php echo $activity['icon'] ?? 'info-circle'; ?> text-<?php echo $activity['color'] ?? 'primary'; ?>"></i>
                                            </div>
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-0"><?php echo $activity['title']; ?></h6>
                                            <p class="text-muted mb-0 small"><?php echo $activity['desc']; ?> - <?php echo $activity['time']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="list_tables.php?table=donors&action=add" class="btn btn-primary text-start">
                                    <i class="fas fa-plus-circle me-2"></i> Add New Donor
                                </a>
                                <button class="btn btn-outline-secondary text-start">
                                    <i class="fas fa-file-import me-2"></i> Import Data
                                </button>
                                <a href="reports.php" class="btn btn-outline-secondary text-start">
                                    <i class="fas fa-file-export me-2"></i> Export Reports
                                </a>
                                <a href="ml_dashboard.php" class="btn btn-outline-secondary text-start">
                                    <i class="fas fa-bell me-2"></i> View Alerts
                                </a>
                                <a href="settings.php" class="btn btn-outline-secondary text-start">
                                    <i class="fas fa-cog me-2"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Blood Stock Levels -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Blood Stock Levels</h6>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="stockFilter" data-bs-toggle="dropdown">
                                        All Blood Banks
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#">All Blood Banks</a></li>
                                        <li><a class="dropdown-item" href="#">Main Center</a></li>
                                        <li><a class="dropdown-item" href="#">Regional Centers</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Blood Type</th>
                                            <th>Available Units</th>
                                            <th>Critical Level</th>
                                            <th>Status</th>
                                            <th>Last Updated</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $bloodTypes = [
                                            'A+' => ['units' => 45, 'critical' => 20, 'status' => 'safe'],
                                            'A-' => ['units' => 12, 'critical' => 10, 'status' => 'warning'],
                                            'B+' => ['units' => 38, 'critical' => 20, 'status' => 'safe'],
                                            'B-' => ['units' => 8, 'critical' => 10, 'status' => 'danger'],
                                            'AB+' => ['units' => 15, 'critical' => 15, 'status' => 'warning'],
                                            'AB-' => ['units' => 5, 'critical' => 8, 'status' => 'danger'],
                                            'O+' => ['units' => 62, 'critical' => 30, 'status' => 'safe'],
                                            'O-' => ['units' => 10, 'critical' => 15, 'status' => 'warning']
                                        ];
                                        
                                        foreach ($bloodTypes as $type => $data):
                                            $statusClass = [
                                                'safe' => 'success',
                                                'warning' => 'warning',
                                                'danger' => 'danger'
                                            ][$data['status']];
                                            
                                            $statusText = [
                                                'safe' => 'Adequate',
                                                'warning' => 'Low',
                                                'danger' => 'Critical'
                                            ][$data['status']];
                                            
                                            $progress = min(100, ($data['units'] / $data['critical']) * 100);
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $type; ?></td>
                                            <td><?php echo $data['units']; ?> units</td>
                                            <td><?php echo $data['critical']; ?> units</td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusClass; ?> bg-opacity-10 text-<?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>2 hours ago</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-plus"></i> Add Stock
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Blood Units</h5>
                            <h2 class="mb-0">5,678</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <h5 class="card-title">Hospitals</h5>
                                                                                                                                                                                                                                                                                                                                                                                                                                                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Critical Stock</h5>
                            <h2 class="mb-0">8</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">New donor registered</h6>
                                <small>5 min ago</small>
                            </div>
                            <p class="mb-1">John Doe (O+)</p>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Blood request approved</h6>
                                <small>1 hour ago</small>
                            </div>
                            <p class="mb-1">City Hospital - 2 units B+</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>