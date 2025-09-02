<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// simple admin guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// handle approval form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_transfer'])) {
    $transfer_id = intval($_POST['transfer_id']);
    $blood_bank_id = intval($_POST['blood_bank_id']);
    $stmt = $conn->prepare("UPDATE blood_transfers SET blood_bank_id = ?, status = 'approved', updated_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $blood_bank_id, $transfer_id);
        $stmt->execute();
    }
    header('Location: ai_dashboard.php');
    exit();
}

// fetch anomalies
$an = $conn->query("SELECT * FROM ai_anomalies ORDER BY created_at DESC LIMIT 50");
$anomalies = $an ? $an->fetch_all(MYSQLI_ASSOC) : [];

// fetch predictions
$preds = $conn->query("SELECT * FROM ai_predictions ORDER BY created_at DESC LIMIT 100");
$predictions = $preds ? $preds->fetch_all(MYSQLI_ASSOC) : [];

// fetch proposed transfers
$prop = $conn->query("SELECT * FROM blood_transfers WHERE proposal_origin = 'ml_service' AND status = 'requested' ORDER BY created_at DESC LIMIT 50");
$proposals = $prop ? $prop->fetch_all(MYSQLI_ASSOC) : [];

// fetch blood banks for assignment
$bbs = $conn->query("SELECT id, name FROM blood_banks ORDER BY name");
$blood_banks = $bbs ? $bbs->fetch_all(MYSQLI_ASSOC) : [];

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>AI Dashboard - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="container mt-4">
    <h3>AI Dashboard</h3>

    <h5 class="mt-4">Anomalies</h5>
    <div class="list-group">
        <?php foreach ($anomalies as $a): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong><?php echo htmlspecialchars($a['anomaly_type']); ?></strong>
                        <div class="text-muted small"><?php echo htmlspecialchars($a['created_at']); ?></div>
                        <div><?php echo htmlspecialchars(json_encode($a['details'])); ?></div>
                    </div>
                    <div>
                        <span class="badge bg-<?php echo $a['score'] > 2 ? 'danger' : 'secondary'; ?>"><?php echo $a['score']; ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h5 class="mt-4">Predictions</h5>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>ID</th><th>Blood Type</th><th>Prediction</th><th>Confidence</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($predictions as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['blood_type']); ?></td>
                    <td><pre style="white-space:pre-wrap;max-width:400px"><?php echo htmlspecialchars($p['prediction']); ?></pre></td>
                    <td><?php echo htmlspecialchars($p['confidence']); ?></td>
                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h5 class="mt-4">Proposed Transfers (from ML)</h5>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>ID</th><th>Blood Type</th><th>Qty</th><th>Notes</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($proposals as $pr): ?>
                <tr>
                    <td><?php echo $pr['id']; ?></td>
                    <td><?php echo htmlspecialchars($pr['blood_type']); ?></td>
                    <td><?php echo $pr['quantity_ml']; ?></td>
                    <td><?php echo htmlspecialchars($pr['notes']); ?></td>
                    <td><?php echo htmlspecialchars($pr['created_at']); ?></td>
                    <td>
                        <form method="post" class="d-flex">
                            <input type="hidden" name="transfer_id" value="<?php echo $pr['id']; ?>">
                            <select name="blood_bank_id" class="form-select form-select-sm me-2" style="width:200px">
                                <?php foreach ($blood_banks as $bb): ?>
                                    <option value="<?php echo $bb['id']; ?>"><?php echo htmlspecialchars($bb['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button name="approve_transfer" class="btn btn-sm btn-success">Approve & Assign</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
