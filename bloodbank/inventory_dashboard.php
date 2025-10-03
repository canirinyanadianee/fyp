<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$bloodBankId = $_SESSION['user_id'];

// ============================
// Get blood bank details
// ============================
try {
    $stmt = $pdo->prepare("SELECT * FROM blood_banks WHERE user_id = ?");
    $stmt->execute([$bloodBankId]);
    $bloodBank = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bloodBank) {
        throw new Exception("Blood bank not found.");
    }
} catch (Exception $e) {
    die("Error loading blood bank: " . htmlspecialchars($e->getMessage()));
}

// ============================
// Get inventory summary
// ============================
try {
    $inventoryStmt = $pdo->prepare("
        SELECT 
            blood_type,
            SUM(CASE WHEN status = 'available' THEN quantity_ml ELSE 0 END) AS available_ml,
            SUM(CASE WHEN status = 'reserved' THEN quantity_ml ELSE 0 END) AS reserved_ml,
            COUNT(CASE WHEN expiry_date < CURDATE() + INTERVAL 7 DAY AND status = 'available' THEN 1 END) AS expiring_soon
        FROM blood_banks
        WHERE blood_bank_id = ?
        GROUP BY blood_type
        ORDER BY blood_type
    ");
    $inventoryStmt->execute([$bloodBank['blood_bank_id']]);
    $inventory = $inventoryStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
} catch (Exception $e) {
    $inventory = [];
    error_log("Inventory fetch error: " . $e->getMessage());
}

// ============================
// Get recent donations
// ============================
try {
    $donationsStmt = $pdo->prepare("
        SELECT d.*, 
               CONCAT(u.first_name, ' ', u.last_name) AS donor_name,
               u.blood_type
        FROM blood_donations d
        LEFT JOIN donors u ON d.donor_id = u.id
        WHERE d.blood_bank_id = ?
        ORDER BY d.donation_date DESC 
        LIMIT 5
    ");
    $donationsStmt->execute([$bloodBank['id']]);
    $recentDonations = $donationsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentDonations = [];
    error_log("Donations fetch error: " . $e->getMessage());
}

// ============================
// Get pending transfers
// ============================
try {
    $transfersStmt = $pdo->prepare("
        SELECT bt.*, h.name AS hospital_name 
        FROM blood_transfers bt
        JOIN hospitals h ON bt.hospital_id = h.id
        WHERE bt.blood_bank_id = ? 
          AND bt.status = 'requested'
        ORDER BY bt.id DESC
    ");
    $transfersStmt->execute([$bloodBank['id']]);
    $pendingTransfers = $transfersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingTransfers = [];
    error_log("Transfers fetch error: " . $e->getMessage());
}

// ============================
// Get expiring soon inventory
// ============================
try {
    $expiringStmt = $pdo->prepare("
        SELECT * 
        FROM blood_banks 
        WHERE blood_bank_id = ? 
          AND expiry_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
          AND status = 'available'
        ORDER BY expiry_date ASC
        LIMIT 5
    ");
    $expiringStmt->execute([$bloodBank['blood_bank_id']]);
    $expiringSoon = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expiringSoon = [];
    error_log("Expiring inventory fetch error: " . $e->getMessage());
}

// Page header
$pageTitle = "Inventory Dashboard";
include '../includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="inventory_dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="inventory.php">
                            <i class="fas fa-boxes me-2"></i>
                            Inventory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donations.php">
                            <i class="fas fa-heartbeat me-2"></i>
                            Donations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transfers.php">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Transfers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donors.php">
                            <i class="fas fa-users me-2"></i>
                            Donors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            Reports
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Inventory Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Units</h5>
                            <h2 class="card-text">
                                <?php 
                                    $total = 0;
                                    foreach ($inventory as $type => $data) {
                                        $total += $data['available_ml'] + $data['reserved_ml'];
                                    }
                                    echo number_format($total / 450, 1); // Assuming 450ml per unit
                                ?>
                            </h2>
                            <p class="card-text">Total blood units in stock</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Available</h5>
                            <h2 class="card-text">
                                <?php 
                                    $available = 0;
                                    foreach ($inventory as $type => $data) {
                                        $available += $data['available_ml'];
                                    }
                                    echo number_format($available / 450, 1);
                                ?>
                            </h2>
                            <p class="card-text">Units available</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Reserved</h5>
                            <h2 class="card-text">
                                <?php 
                                    $reserved = 0;
                                    foreach ($inventory as $type => $data) {
                                        $reserved += $data['reserved_ml'];
                                    }
                                    echo number_format($reserved / 450, 1);
                                ?>
                            </h2>
                            <p class="card-text">Units reserved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title">Expiring Soon</h5>
                            <h2 class="card-text">
                                <?php 
                                    $expiring = 0;
                                    foreach ($inventory as $type => $data) {
                                        $expiring += $data['expiring_soon'];
                                    }
                                    echo $expiring;
                                ?>
                            </h2>
                            <p class="card-text">Units expiring in 7 days</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Summary -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Blood Type Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Blood Type</th>
                                            <th>Available (ml)</th>
                                            <th>Reserved (ml)</th>
                                            <th>Expiring Soon</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory as $type => $data): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($type); ?></td>
                                            <td><?php echo number_format($data['available_ml']); ?></td>
                                            <td><?php echo number_format($data['reserved_ml']); ?></td>
                                            <td><?php echo $data['expiring_soon']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Donations</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($recentDonations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Donor</th>
                                                <th>Blood Type</th>
                                                <th>Amount (ml)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentDonations as $donation): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                <td>
                                                    <?php if (!empty($donation['donor_name'])): ?>
                                                        <?php echo htmlspecialchars($donation['donor_name']); ?>
                                                    <?php else: ?>
                                                        <?php echo isset($donation['donor_id']) ? 'Donor #' . htmlspecialchars($donation['donor_id']) : 'N/A'; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo !empty($donation['blood_type']) ? htmlspecialchars($donation['blood_type']) : 'N/A'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo isset($donation['quantity_ml']) ? number_format($donation['quantity_ml']) . ' ml' : 'N/A'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recentDonations)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent donations found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No recent donations found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Pending Transfers</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($pendingTransfers) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Hospital</th>
                                                <th>Blood Type</th>
                                                <th>Amount (ml)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingTransfers as $transfer): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    if (!empty($transfer['request_date'])) {
                                                        echo date('M d, Y', strtotime($transfer['request_date']));
                                                    } else {
                                                        echo '<span class="text-muted">N/A</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($transfer['hospital_name']); ?></td>
                                                <td><?php echo htmlspecialchars($transfer['blood_type']); ?></td>
                                                <td><?php echo $transfer['quantity_ml']; ?></td>
                                                <td><span class="badge bg-warning"><?php echo ucfirst($transfer['status']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No pending transfers.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Expiring Soon</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($expiringSoon) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Expiry Date</th>
                                                <th>Quantity (ml)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expiringSoon as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['blood_type']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></td>
                                                <td><?php echo $item['quantity_ml']; ?></td>
                                                <td><span class="badge bg-danger">Expiring</span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No inventory expiring in the next 7 days.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>