<?php
// Reports & Insights: Show all blood bank info
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

// Get all blood banks info
$banks = $conn->query("SELECT b.*, u.email as user_email FROM blood_banks b JOIN users u ON b.user_id = u.id ORDER BY b.name");

// Get all donors info
$donors = $conn->query("SELECT d.*, u.email as user_email FROM donors d JOIN users u ON d.user_id = u.id ORDER BY d.first_name, d.last_name");

// Get inventory size per blood bank
$inventory = $conn->query("SELECT b.name, 
    SUM(CASE WHEN i.status = 'available' THEN i.quantity_ml ELSE 0 END) as available,
    SUM(CASE WHEN i.status = 'reserved' THEN i.quantity_ml ELSE 0 END) as reserved,
    SUM(CASE WHEN i.status = 'expired' THEN i.quantity_ml ELSE 0 END) as expired,
    SUM(i.quantity_ml) as total_inventory
    FROM blood_banks b 
    LEFT JOIN blood_inventory i ON b.id = i.blood_bank_id 
    GROUP BY b.id ORDER BY b.name");

// Get all hospital transfer requests
$transfers = $conn->query("SELECT t.*, h.name as hospital_name, b.name as bank_name FROM blood_transfers t JOIN hospitals h ON t.hospital_id = h.id JOIN blood_banks b ON t.blood_bank_id = b.id ORDER BY t.transfer_date DESC");

// Get current blood bank information
$bank = [];
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'bloodbank') {
    $stmt = $conn->prepare("SELECT * FROM blood_banks WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $bank = $result->fetch_assoc();
}

// Set default bank name if not found
$bankName = isset($bank['name']) ? htmlspecialchars($bank['name']) : 'Blood Bank';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Blood Banks Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"><style>
<style>
.navbar{
    background-color:rgb(11, 5, 24); /* light green hover */ 
}
  .navbar .nav-link {
    transition: 0.3s ease;
    padding: 8px 14px;
    border-radius: 6px;
    color: #0d6efd; /* default blue */
  }
  .navbar .nav-link:hover {
    background-color:rgb(11, 5, 24); /* light green hover */
    color: #198754 !important; /* green on hover */
  }
  .navbar .nav-link.active {
    background-color: #198754 !important; /* green background */
    color: #fff !important;
  }
  .navbar .nav-link i {
    width: 18px;
    text-align: center;
  }
  
</style>
</head>
<body>
    <!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top mb-4">
                <a class="navbar-brand text-primary" href="index.php"><i class="fas fa-warehouse me-2"></i><?php echo $bankName; ?></a>
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
</nav>  <div class="container">
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-warehouse text-primary me-2"></i>All Blood Banks Information</h2>
    <h4 class="mt-4">Blood Banks</h4>
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>License #</th>
                <th>Address</th>
                <th>City</th>
                <th>State</th>
                <th>Postal Code</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Contact Email</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($banks && $banks->num_rows > 0): ?>
            <?php while ($b = $banks->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['license_number'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['address'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['city'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['state'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['postal_code'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['phone'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['user_email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($b['contact_email'] ?? ''); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="9" class="text-center">No blood banks found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4 class="mt-5">Donors</h4>
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Blood Type</th>
                <th>Phone</th>
                <th>Last Donation</th>
                <th>Donation Count</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($donors && $donors->num_rows > 0): ?>
            <?php while ($d = $donors->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['first_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['last_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['user_email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['blood_type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['phone'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['last_donation_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['donation_count'] ?? ''); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">No donors found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4 class="mt-5">Inventory Size & Status by Blood Bank</h4>
    <canvas id="inventoryChart" height="100"></canvas>
    <table class="table table-bordered table-striped mt-4">
        <thead class="table-light">
            <tr>
                <th>Blood Bank</th>
                <th>Available (ml)</th>
                <th>Reserved (ml)</th>
                <th>Expired (ml)</th>
                <th>Total Inventory (ml)</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $bankNames = [];
        $available = [];
        $reserved = [];
        $expired = [];
        $bankInventory = [];
        if ($inventory && $inventory->num_rows > 0): ?>
            <?php while ($i = $inventory->fetch_assoc()): 
                $bankNames[] = $i['name'] ?? '';
                $available[] = (int)($i['available'] ?? 0);
                $reserved[] = (int)($i['reserved'] ?? 0);
                $expired[] = (int)($i['expired'] ?? 0);
                $bankInventory[] = (int)($i['total_inventory'] ?? 0);
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($i['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($i['available'] ?? '0'); ?></td>
                    <td><?php echo htmlspecialchars($i['reserved'] ?? '0'); ?></td>
                    <td><?php echo htmlspecialchars($i['expired'] ?? '0'); ?></td>
                    <td><?php echo htmlspecialchars($i['total_inventory'] ?? '0'); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center">No inventory data found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('inventoryChart').getContext('2d');
        const inventoryChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($bankNames); ?>,
                datasets: [
                    {
                        label: 'Available',
                        data: <?php echo json_encode($available); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                    },
                    {
                        label: 'Reserved',
                        data: <?php echo json_encode($reserved); ?>,
                        backgroundColor: 'rgba(255, 206, 86, 0.7)'
                    },
                    {
                        label: 'Expired',
                        data: <?php echo json_encode($expired); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.7)'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true },
                    title: { display: true, text: 'Inventory Status by Blood Bank' }
                },
                scales: {
                    x: { stacked: true },
                    y: { beginAtZero: true, stacked: true }
                }
            }
        });
    </script>

    <h4 class="mt-5">Process Hospital Transfer Requests</h4>
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Blood Bank</th>
                <th>Hospital</th>
                <th>Blood Type</th>
                <th>Quantity (ml)</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($transfers && $transfers->num_rows > 0): ?>
            <?php while ($t = $transfers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($t['bank_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['hospital_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['blood_type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['quantity_ml'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['transfer_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['status'] ?? ''); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-center">No transfer requests found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <a href="index.php" class="btn btn-link mt-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
</body>
</html>
