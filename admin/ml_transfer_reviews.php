<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Simple admin page to list proposed transfers from ML and allow approve/reject
// Note: this file assumes the admin is already authenticated via existing admin session flow.

$q = $conn->query("SELECT bt.*, IFNULL(bb.name,'Unassigned') AS bank_name FROM blood_transfers bt LEFT JOIN blood_banks bb ON bt.blood_bank_id = bb.id WHERE bt.proposal_origin='ml_service' AND bt.status='proposed' ORDER BY bt.created_at DESC");
$rows = [];
if ($q) {
    while ($r = $q->fetch_assoc()) $rows[] = $r;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>ML Transfer Proposals</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="container mt-4">
    <h3>ML Proposed Transfers</h3>
    <table class="table table-striped">
        <thead><tr><th>ID</th><th>Blood Type</th><th>Quantity (ml)</th><th>Bank</th><th>Notes</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['id']) ?></td>
                <td><?= htmlspecialchars($r['blood_type']) ?></td>
                <td><?= htmlspecialchars($r['quantity_ml']) ?></td>
                <td><?= htmlspecialchars($r['bank_name']) ?></td>
                <td><?= htmlspecialchars($r['notes']) ?></td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
                <td>
                    <form method="post" action="ml_transfer_action.php" style="display:inline-block">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
                        <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                    </form>
                    <form method="post" action="ml_transfer_action.php" style="display:inline-block">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
                        <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
</body>
</html>
