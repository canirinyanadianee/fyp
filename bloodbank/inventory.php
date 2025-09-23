<?php
// Blood Bank Inventory Management
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
$blood_bank_id = $bank['id'];

// Define thresholds for status indicators (in ml)
$thresholds = [
    'critical' => 500,   // Below this is Urgent
    'low' => 1000,       // Below this is Low
    'normal' => 1000     // Above this is Normal
];

// Get filter parameters
$blood_type_filter = $_GET['blood_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build base query with status calculation
$query = "SELECT *, 
    CASE 
        WHEN quantity_ml <= {$thresholds['critical']} THEN 'Urgent' 
        WHEN quantity_ml <= {$thresholds['low']} THEN 'Low' 
        ELSE 'Normal' 
    END as status_level
    FROM blood_inventory 
    WHERE blood_bank_id = $blood_bank_id";

// Add filters
if ($blood_type_filter) {
    $type = $conn->real_escape_string($blood_type_filter);
    $query .= " AND blood_type = '$type'";
}
if ($status_filter) {
    $status = $conn->real_escape_string($status_filter);
    $query .= " AND CASE 
        WHEN quantity_ml <= {$thresholds['critical']} THEN 'Urgent' 
        WHEN quantity_ml <= {$thresholds['low']} THEN 'Low' 
        ELSE 'Normal' 
    END = '$status'";
}

$query .= " ORDER BY status_level, blood_type";

// Handle update, add, delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        $id = intval($_POST['id']);
        $qty = intval($_POST['quantity_ml']);
        $expiry = $_POST['expiry_date'];
        $conn->query("UPDATE blood_inventory SET quantity_ml = $qty, expiry_date = '$expiry' WHERE id = $id AND blood_bank_id = $blood_bank_id");
    } elseif (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM blood_inventory WHERE id = $id AND blood_bank_id = $blood_bank_id");
    } elseif (isset($_POST['add'])) {
        $type = $conn->real_escape_string($_POST['blood_type']);
        $qty = intval($_POST['quantity_ml']);
        $expiry = $_POST['expiry_date'];
        $conn->query("INSERT INTO blood_inventory (blood_bank_id, blood_type, quantity_ml, expiry_date) VALUES ($blood_bank_id, '$type', $qty, '$expiry')");
    }
    header('Location: inventory.php');
    exit();
}

$inventory = $conn->query($query);
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Inventory - <?php echo htmlspecialchars($bank['name']); ?> Blood Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url('image.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }
        .status-urgent { background-color: #f8d7da; }
        .status-low { background-color: #fff3cd; }
        .status-normal { background-color: #d1e7dd; }
        .expiry-soon { color: #ffc107; font-weight: bold; }
        .expiry-expired { color: #dc3545; text-decoration: line-through; font-weight: bold; }
    
<style>
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
    <h2 class="mb-4">
        <i class="fas fa-tint text-danger me-2"></i>Manage Blood Inventory
    </h2>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-2"></i>Filters
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($blood_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo ($blood_type_filter === $type) ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Urgent" <?php echo ($status_filter === 'Urgent') ? 'selected' : ''; ?>>Urgent (≤500ml)</option>
                        <option value="Low" <?php echo ($status_filter === 'Low') ? 'selected' : ''; ?>>Low (≤1000ml)</option>
                        <option value="Normal" <?php echo ($status_filter === 'Normal') ? 'selected' : ''; ?>>Normal (>1000ml)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="inventory.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list-ul me-2"></i>Blood Inventory
            </h5>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                <i class="fas fa-plus me-1"></i> Add New
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Blood Type</th>
                        <th>Quantity (ml)</th>
                        <th>Status</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($inventory && $inventory->num_rows > 0): ?>
                    <?php while ($row = $inventory->fetch_assoc()): 
                        $expiry_date = new DateTime($row['expiry_date']);
                        $today = new DateTime();
                        $days_left = $today->diff($expiry_date)->days;
                        $expiry_class = '';
                        
                        if ($expiry_date < $today) {
                            $expiry_class = 'expiry-expired';
                            $expiry_text = 'Expired';
                        } elseif ($days_left <= 14) {
                            $expiry_class = 'expiry-soon';
                            $expiry_text = $days_left . ' days';
                        } else {
                            $expiry_text = $days_left . ' days';
                        }
                        
                        $status_class = strtolower(str_replace(' ', '-', $row['status_level']));
                        include __DIR__ . '/includes/header.php';
                    ?>
                    <tr class="status-<?php echo $status_class; ?>">
                        <td><?php echo htmlspecialchars($row['blood_type']); ?></td>
                        <td><?php echo number_format($row['quantity_ml']); ?> ml</td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $row['status_level'] === 'Urgent' ? 'danger' : 
                                    ($row['status_level'] === 'Low' ? 'warning' : 'success'); 
                            ?>">
                                <?php echo $row['status_level']; ?>
                            </span>
                        </td>
                        <td class="<?php echo $expiry_class; ?>">
                            <?php echo $row['expiry_date']; ?>
                        </td>
                        <td class="<?php echo $expiry_class; ?>">
                            <?php echo $expiry_text; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary" 
                                onclick="editInventory(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this inventory item?')">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No inventory items found</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <a href="index.php" class="btn btn-link mt-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<!-- Add/Edit Inventory Modal -->
<div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="inventoryId">
                    <div class="mb-3">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" id="bloodType" class="form-select" required>
                            <?php foreach ($blood_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity (ml)</label>
                        <input type="number" name="quantity_ml" id="quantity" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiryDate" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update" id="saveBtn" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add New Inventory Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" class="form-select" required>
                            <?php foreach ($blood_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity (ml)</label>
                        <input type="number" name="quantity_ml" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add" class="btn btn-primary">Add Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editInventory(item) {
    document.getElementById('modalTitle').textContent = 'Edit Inventory';
    document.getElementById('inventoryId').value = item.id;
    document.getElementById('bloodType').value = item.blood_type;
    document.getElementById('quantity').value = item.quantity_ml;
    document.getElementById('expiryDate').value = item.expiry_date;
    document.getElementById('saveBtn').name = 'update';
    
    var modal = new bootstrap.Modal(document.getElementById('inventoryModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize add new button
    document.querySelector('[data-bs-target="#addInventoryModal"]').addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Add New Inventory';
        document.querySelector('#inventoryModal form').reset();
        document.getElementById('inventoryId').value = '';
        document.getElementById('saveBtn').name = 'add';
    });
    
    // Set minimum date to today for expiry date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('expiryDate').min = today;
});
</script>
</body>
</html>
