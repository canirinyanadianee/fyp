<?php
// hospital/transfers.php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Fetch transfer history from the database
$sql = "SELECT t.id, t.blood_type, t.quantity_ml, t.blood_bank_id, t.hospital_id, t.status, t.transfer_date
    FROM blood_transfers t
    ORDER BY t.transfer_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .page-header h2 {
            font-weight: 700;
            color: #2c3e50;
        }
        .card-custom {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 20px;
            background: #fff;
        }
        table {
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background: #0d6efd;
            color: #fff;
        }
        tbody tr:hover {
            background-color: #f1f5ff;
        }
        .badge {
            font-size: 0.85rem;
            padding: 0.5em 0.75em;
            border-radius: 8px;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-approved {
            background-color: #28a745;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
       /* Navbar Gradient Background */
.navbar-custom {
    background: linear-gradient(90deg, #ff4b2b, #ff416c);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.navbar-custom .navbar-brand {
    font-weight: 700;
    font-size: 1.2rem;
    color: #fff;
}

.navbar-custom .navbar-nav .nav-link {
    color: rgba(255, 255, 255, 0.9);
    margin-right: 1rem;
    transition: all 0.3s ease;
    font-weight: 500;
}

.navbar-custom .navbar-nav .nav-link:hover {
    color: #fff;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    padding: 6px 12px;
}

.navbar-custom .navbar-nav .nav-link.active {
    background: rgba(255,255,255,0.3);
    border-radius: 8px;
    color: #fff !important;
}

.form-check-input {
    cursor: pointer;
}

/* Dark Mode */
body.dark-mode {
    background-color: #1c1c1c;
    color: #e0e0e0;
}

body.dark-mode .navbar-custom {
    background: linear-gradient(90deg, #0f2027, #203a43, #2c5364);
}

body.dark-mode .navbar-custom .nav-link {
    color: #e0e0e0;
}
  
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="container-fluid dashboard-content">
    <div class="container-fluid">
            <nav class="navbar navbar-expand-lg">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-tint me-2"></i>
                    BloodConnect | Hospital
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

    <div class="container mt-5">
        <div class="page-header">
            <h2><i class="bi bi-arrow-left-right"></i> Transfer History</h2>
            <p class="text-muted">Overview of all past blood transfers</p>
        </div>
        <div class="card-custom">
            <?php if ($result && $result->num_rows > 0): ?>
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Blood Type</th>
                            <th>Quantity (ml)</th>
                            <th>Blood Bank ID</th>
                            <th>Hospital ID</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><span class="fw-bold text-danger"><?= htmlspecialchars($row['blood_type']) ?></span></td>
                                <td><?= htmlspecialchars($row['quantity_ml']) ?></td>
                                <td><?= htmlspecialchars($row['blood_bank_id']) ?></td>
                                <td><?= htmlspecialchars($row['hospital_id']) ?></td>
                                <td>
                                    <?php 
                                        $status = strtolower($row['status']);
                                        if ($status === "pending") {
                                            echo '<span class="badge badge-pending">Pending</span>';
                                        } elseif ($status === "approved") {
                                            echo '<span class="badge badge-approved">Approved</span>';
                                        } else {
                                            echo '<span class="badge badge-rejected">Rejected</span>';
                                        }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars(date("d M Y, H:i", strtotime($row['transfer_date']))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No transfer history found.</div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.js"></script>
</body>
</html>
