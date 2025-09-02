<?php
// Blood Bank Dashboard
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a blood bank
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
// Get blood bank info
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
$blood_bank_id = $bank['id'];

// Get summary stats
$total_donations = $conn->query("SELECT COUNT(*) as total FROM blood_donations WHERE blood_bank_id = $blood_bank_id")->fetch_assoc()['total'];
$total_inventory = $conn->query("SELECT SUM(quantity_ml) as total FROM blood_inventory WHERE blood_bank_id = $blood_bank_id")->fetch_assoc()['total'];
$pending_transfers = $conn->query("SELECT COUNT(*) as total FROM blood_transfers WHERE blood_bank_id = $blood_bank_id AND status = 'requested'")->fetch_assoc()['total'];
$donor_count = $conn->query("SELECT COUNT(DISTINCT donor_id) as total FROM blood_donations WHERE blood_bank_id = $blood_bank_id")->fetch_assoc()['total'];

// Get ML predictions
$predictions = [];
try {
    // First check if the table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'ml_demand_predictions'");
    
    if ($tableCheck && $tableCheck->num_rows > 0) {
        // Table exists, fetch predictions
        $prediction_result = $conn->query("
            SELECT * FROM ml_demand_predictions 
            WHERE prediction_date = CURDATE()
            ORDER BY blood_type
        ");

        if ($prediction_result) {
            while ($row = $prediction_result->fetch_assoc()) {
                $predictions[] = $row;
            }
        }
    } else {
        // Table doesn't exist, log it for debugging
        error_log("ML predictions table does not exist. Please run the migration script.");
    }
} catch (Exception $e) {
    // Log the error but don't break the page
    error_log("Error fetching ML predictions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Blood Bank Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style id="theme-style">
            body {
                background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
                min-height: 100vh;
            }
            .dashboard-hero {
                background: linear-gradient(120deg, #667eea 0%, #764ba2 100%);
                border-radius: 1.5rem;
                color: #fff;
                box-shadow: 0 8px 32px rgba(102,126,234,0.12);
                padding: 2.5rem 2rem 2rem 2rem;
                margin-bottom: 2.5rem;
                position: relative;
                overflow: hidden;
            }
            .dashboard-hero .fa-warehouse {
                font-size: 4rem;
                opacity: 0.2;
                position: absolute;
                right: 2rem;
                top: 2rem;
            }
            .dashboard-card {
                border-radius: 1rem;
                box-shadow: 0 2px 12px rgba(0,0,0,0.07);
                transition: transform 0.2s, box-shadow 0.2s;
                background: linear-gradient(135deg, #fff 60%, #e0e7ff 100%);
            }
            .dashboard-card:hover {
                transform: translateY(-7px) scale(1.04);
                box-shadow: 0 8px 32px rgba(102,126,234,0.18);
                background: linear-gradient(135deg, #e0e7ff 0%, #fff 100%);
            }
            .dashboard-icon {
                font-size: 2.5rem;
                margin-bottom: 0.5rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border-radius: 50%;
                width: 3.5rem;
                height: 3.5rem;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 0.5rem auto;
                box-shadow: 0 2px 8px rgba(102,126,234,0.12);
            }
            .quick-links .card {
                transition: box-shadow 0.2s, background 0.2s, transform 0.2s;
                border-radius: 1rem;
                background: #fff;
            }
            .quick-links .card:hover {
                box-shadow: 0 4px 24px rgba(99,102,241,0.18);
                background: #e0e7ff;
                transform: scale(1.04);
            }
            .navbar-brand { font-weight: bold; letter-spacing: 1px; }
            .footer { background: #23272b; color: #e0e0e0; padding: 2rem 0 1rem 0; margin-top: 3rem; }
            .footer a { color: #e0e0e0; text-decoration: underline; }
            body.dark-mode { background: #181a1b !important; color: #e0e0e0 !important; }
            body.dark-mode .navbar, body.dark-mode .card, body.dark-mode .dashboard-card, body.dark-mode .feature-card, body.dark-mode .modal-content, body.dark-mode .footer { background-color: #23272b !important; color: #e0e0e0 !important; }
            body.dark-mode .table, body.dark-mode .table-bordered, body.dark-mode .table-light { color: #e0e0e0 !important; background-color: #23272b !important; }
            body.dark-mode .bg-white, body.dark-mode .bg-light, body.dark-mode .bg-primary, body.dark-mode .bg-info, body.dark-mode .bg-warning, body.dark-mode .bg-danger, body.dark-mode .bg-success, body.dark-mode .bg-secondary, body.dark-mode .bg-dark { background-color: #23272b !important; color: #e0e0e0 !important; }
            body.dark-mode .btn, body.dark-mode .btn-primary, body.dark-mode .btn-outline-primary, body.dark-mode .btn-light, body.dark-mode .btn-outline-light { color: #e0e0e0 !important; }
        </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top mb-4">
            <div class="container">
                <a class="navbar-brand text-primary" href="index.php"><i class="fas fa-warehouse me-2"></i><?php echo htmlspecialchars($bank['name']); ?> Blood Bank</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavBB" aria-controls="navbarNavBB" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavBB">
                    <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="fas fa-warehouse me-1 text-info"></i>Manage Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="donors.php"><i class="fas fa-users me-1 text-success"></i>View Donors</a></li>
                        <li class="nav-item"><a class="nav-link" href="record_donation.php"><i class="fas fa-tint me-1 text-danger"></i>Record Donation</a></li>
                        <li class="nav-item"><a class="nav-link" href="transfers.php"><i class="fas fa-exchange-alt me-1 text-warning"></i>Process Transfers</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-1 text-primary"></i>Reports & Insights</a></li>
                        <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-1 text-secondary"></i>Profile</a></li>
                        <li class="nav-item"><a class="nav-link" href="change_password.php"><i class="fas fa-key me-1 text-secondary"></i>Change Password</a></li>
                        <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-1 text-danger"></i>Logout</a></li>
                    </ul>
                    <div class="d-flex align-items-center">
                        <button id="theme-toggle" class="btn btn-outline-secondary me-2" title="Toggle light/dark mode"><i class="fas fa-moon"></i></button>
                    </div>
                </div>
            </div>
</nav>
<div class="container py-4">
    <!-- Hero Section -->
    <div class="dashboard-hero mb-5 position-relative">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold mb-2">Welcome to Your Blood Bank Dashboard</h1>
                <p class="lead mb-0">Monitor inventory, process donations, and manage hospital requests with AI-powered insights and a beautiful, modern interface.</p>
            </div>
            <div class="col-lg-4 text-end d-none d-lg-block">
                <i class="fas fa-warehouse"></i>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4 justify-content-center">
        <div class="col-md-3">
            <div class="card dashboard-card text-center p-3">
                <div class="dashboard-icon text-danger"><i class="fas fa-tint"></i></div>
                <h5>Total Donations</h5>
                <p class="display-6 fw-bold mb-0"><?php echo $total_donations; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card text-center p-3">
                <div class="dashboard-icon text-info"><i class="fas fa-warehouse"></i></div>
                <h5>Total Inventory (ml)</h5>
                <p class="display-6 fw-bold mb-0"><?php echo $total_inventory ? $total_inventory : 0; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card text-center p-3">
                <div class="dashboard-icon text-warning"><i class="fas fa-exchange-alt"></i></div>
                <h5>Pending Transfers</h5>
                <p class="display-6 fw-bold mb-0"><?php echo $pending_transfers; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card text-center p-3">
                <div class="dashboard-icon text-success"><i class="fas fa-users"></i></div>
                <h5>Unique Donors</h5>
                <p class="display-6 fw-bold mb-0"><?php echo $donor_count; ?></p>
            </div>
        </div>
    </div>
    <!-- Inventory Table with Status -->
    <div class="card my-4">
        <div class="card-header bg-primary text-white">Blood Inventory Status</div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Blood Type</th>
                        <th>Quantity (ml)</th>
                        <th>Earliest Expiry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                $low_threshold = 500; $urgent_threshold = 200;
                $notify_donors = [];
                foreach ($blood_types as $type) {
                    $row = $conn->query("SELECT SUM(quantity_ml) as qty, MIN(expiry_date) as expiry FROM blood_inventory WHERE blood_bank_id = $blood_bank_id AND blood_type = '$type'")->fetch_assoc();
                    $qty = (int)($row['qty'] ?? 0);
                    $expiry = $row['expiry'] ?? '-';
                    $status = 'Normal'; $badge = 'success';
                    if ($qty <= $urgent_threshold) { $status = 'Urgent'; $badge = 'danger'; $notify_donors[] = $type; }
                    else if ($qty <= $low_threshold) { $status = 'Low'; $badge = 'warning'; }
                    echo "<tr><td>$type</td><td>$qty</td><td>$expiry</td><td><span class='badge bg-$badge'>$status</span></td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- ML Predictions Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-chart-line text-primary me-2"></i>Demand Forecast
                <small class="text-muted ms-2">(Next 7 & 30 days)</small>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($predictions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Blood Type</th>
                                <th class="text-end">Next 7 Days</th>
                                <th class="text-end">Next 30 Days</th>
                                <th class="text-end">Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($predictions as $pred): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-danger"><?= htmlspecialchars($pred['blood_type']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($pred['predicted_demand_7d']) ?> units
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($pred['predicted_demand_30d']) ?> units
                                    </td>
                                    <td class="text-end">
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                                $confidence = $pred['confidence'] * 100;
                                                $color = $confidence > 80 ? 'bg-success' : ($confidence > 60 ? 'bg-info' : 'bg-warning');
                                            ?>
                                            <div class="progress-bar <?= $color ?>" role="progressbar" 
                                                 style="width: <?= $confidence ?>%" 
                                                 aria-valuenow="<?= $confidence ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?= round($confidence) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-info-circle"></i> Predictions are updated daily at 2 AM
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    No demand predictions available. The prediction service runs daily at 2 AM.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Donor Notification for Low Stock -->
    <?php 
    // Handle notify action
    if (isset($_GET['notify_blood_type'])) {
        $notify_type = $_GET['notify_blood_type'];
        // Simulate AI notification (replace with real ML/AI call as needed)
        // For example: require_once '../includes/ai_connector.php'; ai_notify_donors($notify_type, $blood_bank_id);
        echo '<div class="alert alert-success">AI-powered notification sent to eligible donors for blood type <strong>' . htmlspecialchars($notify_type) . '</strong>.</div>';
    }
    ?>
    <?php if (!empty($notify_donors)): ?>
    <div class="alert alert-danger">
        <strong>Critical Stock Alert!</strong> The following blood types are in urgent need:
        <ul class="mb-0">
            <?php foreach ($notify_donors as $type): ?>
                <li><?php echo $type; ?> - <a href="?notify_blood_type=<?php echo urlencode($type); ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Send AI notification to eligible donors for <?php echo $type; ?>?');">Notify Donors</a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <!-- Pending Transfers Section -->
    <div class="card my-4">
        <div class="card-header bg-warning text-dark">Pending Hospital Transfer Requests</div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr><th>Hospital</th><th>Blood Type</th><th>Quantity</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $transfers = $conn->query("SELECT t.*, h.name as hospital_name FROM blood_transfers t JOIN hospitals h ON t.hospital_id = h.id WHERE t.blood_bank_id = $blood_bank_id AND t.status = 'requested' ORDER BY t.transfer_date DESC LIMIT 5");
                if ($transfers && $transfers->num_rows > 0) {
                    while ($tr = $transfers->fetch_assoc()) {
                        echo "<tr><td>{$tr['hospital_name']}</td><td>{$tr['blood_type']}</td><td>{$tr['quantity_ml']}</td><td>{$tr['transfer_date']}</td><td><span class='badge bg-warning'>{$tr['status']}</span></td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center'>No pending transfers</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- AI Insights Placeholder -->
    <div class="card my-4">
        <div class="card-header bg-info text-white">AI Insights</div>
        <div class="card-body">
            <p>AI-generated insights about donation patterns will appear here. (Coming soon!)</p>
        </div>
    </div>
    <!-- Quick Access cards removed; navbar links are now the only navigation. -->
    </div>
        <!-- Footer -->
        <footer class="footer mt-5">
            <div class="container text-center">
                <div class="mb-2">
                    <i class="fas fa-heartbeat text-danger fa-2x"></i>
                </div>
                <div class="mb-2">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($bank['name']); ?> Blood Bank. Powered by AI Blood Management System.</div>
                <div>
                    <a href="mailto:info@bloodbank.com">Contact Support</a> |
                    <a href="../index.php">Main Site</a>
                </div>
            </div>
        </footer>
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
                // Light/Dark mode toggle
                const themeToggle = document.getElementById('theme-toggle');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                function setTheme(dark) {
                        if (dark) {
                                document.body.classList.add('dark-mode');
                                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                                localStorage.setItem('theme', 'dark');
                        } else {
                                document.body.classList.remove('dark-mode');
                                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                                localStorage.setItem('theme', 'light');
                        }
                }
                // On load
                const savedTheme = localStorage.getItem('theme');
                setTheme(savedTheme === 'dark' || (!savedTheme && prefersDark));
                themeToggle.addEventListener('click', function() {
                        setTheme(!document.body.classList.contains('dark-mode'));
                });
        </script>
</body>
</html>
