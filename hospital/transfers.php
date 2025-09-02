<?php
// hospital/transfers.php
// Show transfer history for the hospital
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
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Transfer History</h2>
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="table table-bordered table-striped">
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
                            <td><?= htmlspecialchars($row['blood_type']) ?></td>
                            <td><?= htmlspecialchars($row['quantity_ml']) ?></td>
                            <td><?= htmlspecialchars($row['blood_bank_id']) ?></td>
                            <td><?= htmlspecialchars($row['hospital_id']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['transfer_date']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No transfer history found.</div>
        <?php endif; ?>
    </div>
</body>
</html>
