<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// // Check if user is logged in and has blood bank role
// if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'bloodbank_staff', 'bloodbank_manager'])) {
//     header('Location: /login.php');
//     exit();
// }

$bloodBankId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $donationId = $_POST['donation_id'];
        $hivTest = isset($_POST['hiv_test']) ? 1 : 0;
        $hepatitisBTest = isset($_POST['hepatitis_b_test']) ? 1 : 0;
        $hepatitisCTest = isset($_POST['hepatitis_c_test']) ? 1 : 0;
        $syphilisTest = isset($_POST['syphilis_test']) ? 1 : 0;
        $testNotes = $_POST['test_notes'];
        
        // Determine overall status (fails if any test is positive)
        $status = ($hivTest || $hepatitisBTest || $hepatitisCTest || $syphilisTest) ? 'failed' : 'passed';
        
        // Insert screening results
        $stmt = $pdo->prepare("
            INSERT INTO blood_screening 
            (donation_id, hiv_test, hepatitis_b_test, hepatitis_c_test, syphilis_test, test_notes, tested_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $donationId,
            $hivTest,
            $hepatitisBTest,
            $hepatitisCTest,
            $syphilisTest,
            $testNotes,
            $_SESSION['user_id'],
            $status
        ]);
        
        // Update donation status
        $updateStmt = $pdo->prepare("
            UPDATE blood_donations 
            SET status = ? 
            WHERE id = ? AND blood_bank_id = ?
        ");
        $finalStatus = $status === 'passed' ? 'processed' : 'discarded';
        $updateStmt->execute([$finalStatus, $donationId, $bloodBankId]);
        
        // If passed, add to inventory
        if ($status === 'passed') {
            // Get donation details
            $donationStmt = $pdo->prepare("
                SELECT blood_type, quantity_ml 
                FROM blood_donations 
                WHERE id = ? AND blood_bank_id = ?
            ");
            $donationStmt->execute([$donationId, $bloodBankId]);
            $donation = $donationStmt->fetch();
            
            if ($donation) {
                $expiryDate = date('Y-m-d', strtotime('+42 days')); // 6 weeks expiry for whole blood
                
                // Check if inventory exists for this blood type
                $inventoryStmt = $pdo->prepare("
                    SELECT id, quantity_ml 
                    FROM blood_inventory 
                    WHERE blood_bank_id = ? AND blood_type = ? AND status = 'available'
                    ORDER BY expiry_date ASC
                    LIMIT 1
                ");
                $inventoryStmt->execute([$bloodBankId, $donation['blood_type']]);
                $inventory = $inventoryStmt->fetch();
                
                if ($inventory) {
                    // Update existing inventory
                    $updateInventoryStmt = $pdo->prepare("
                        UPDATE blood_inventory 
                        SET quantity_ml = quantity_ml + ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateInventoryStmt->execute([$donation['quantity_ml'], $inventory['id']]);
                } else {
                    // Create new inventory record
                    $insertInventoryStmt = $pdo->prepare("
                        INSERT INTO blood_inventory 
                        (blood_bank_id, blood_type, quantity_ml, expiry_date, status)
                        VALUES (?, ?, ?, ?, 'available')
                    
                    ");
                    $insertInventoryStmt->execute([
                        $bloodBankId,
                        $donation['blood_type'],
                        $donation['quantity_ml'],
                        $expiryDate
                    ]);
                }
                
                // Record the addition to inventory
                $logStmt = $pdo->prepare("
                    INSERT INTO inventory_logs 
                    (blood_bank_id, blood_type, quantity_ml, action, notes, performed_by)
                    VALUES (?, ?, ?, 'add', 'Added from donation #?', ?)
                
                ");
                $logStmt->execute([
                    $bloodBankId,
                    $donation['blood_type'],
                    $donation['quantity_ml'],
                    $donationId,
                    $_SESSION['user_id']
                ]);
            }
        }
        
        $pdo->commit();
        $message = "Screening results recorded successfully. Blood marked as " . strtoupper($status) . ".";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error recording screening results: " . $e->getMessage();
    }
}

// Get pending donations for screening
$pendingStmt = $pdo->prepare("
    SELECT bd.*, d.first_name, d.last_name, d.blood_type
    FROM blood_donations bd
    JOIN donors d ON bd.donor_id = d.id
    WHERE bd.blood_bank_id = ? AND bd.status = 'collected'
    ORDER BY bd.donation_date ASC
");
$pendingStmt->execute([$bloodBankId]);
$pendingDonations = $pendingStmt->fetchAll();

// Get recent screenings
$recentScreeningsStmt = $pdo->prepare("
    SELECT bs.*, bd.donation_date, d.first_name, d.last_name, d.blood_type,
           u.username as tested_by_name
    FROM blood_screening bs
    JOIN blood_donations bd ON bs.donation_id = bd.id
    JOIN donors d ON bd.donor_id = d.id
    JOIN users u ON bs.tested_by = u.id
    WHERE bd.blood_bank_id = ?
    ORDER BY bs.test_date DESC
    LIMIT 10
");
$recentScreeningsStmt->execute([$bloodBankId]);
$recentScreenings = $recentScreeningsStmt->fetchAll();

// Include header
$pageTitle = "Blood Screening";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Blood Donation Screening</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Pending Donations for Screening -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Pending Screening</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($pendingDonations) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($pendingDonations as $donation): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($donation['blood_type']); ?></span>
                                                </h6>
                                                <small><?php echo date('M j, Y', strtotime($donation['donation_date'])); ?></small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php echo number_format($donation['quantity_ml']); ?> ml
                                                </small>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#screeningModal" 
                                                        data-donation-id="<?php echo $donation['id']; ?>"
                                                        data-donor-name="<?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?>"
                                                        data-blood-type="<?php echo htmlspecialchars($donation['blood_type']); ?>">
                                                    Record Screening
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No donations pending screening.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Screenings -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Screenings</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($recentScreenings) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Donor</th>
                                                <th>Blood Type</th>
                                                <th>Status</th>
                                                <th>Tested By</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentScreenings as $screening): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($screening['first_name'] . ' ' . $screening['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($screening['blood_type']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $screening['status'] === 'passed' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($screening['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($screening['tested_by_name']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($screening['test_date'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">No screening records found.</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="screening_logs.php" class="btn btn-sm btn-outline-primary">View All Screenings</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Screening Modal -->
<div class="modal fade" id="screeningModal" tabindex="-1" aria-labelledby="screeningModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="screeningModalLabel">Record Screening Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="donation_id" id="donationId">
                    
                    <div class="mb-3">
                        <label class="form-label">Donor</label>
                        <input type="text" class="form-control" id="donorName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Blood Type</label>
                        <input type="text" class="form-control" id="bloodType" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Test Results</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hiv_test" id="hivTest" value="1">
                            <label class="form-check-label text-danger" for="hivTest">
                                HIV Positive
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hepatitis_b_test" id="hepatitisBTest" value="1">
                            <label class="form-check-label text-danger" for="hepatitisBTest">
                                Hepatitis B Positive
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hepatitis_c_test" id="hepatitisCTest" value="1">
                            <label class="form-check-label text-danger" for="hepatitisCTest">
                                Hepatitis C Positive
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="syphilis_test" id="syphilisTest" value="1">
                            <label class="form-check-label text-danger" for="syphilisTest">
                                Syphilis Positive
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="testNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="testNotes" name="test_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Results</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Initialize modal with donation data
document.getElementById('screeningModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const donationId = button.getAttribute('data-donation-id');
    const donorName = button.getAttribute('data-donor-name');
    const bloodType = button.getAttribute('data-blood-type');
    
    const modal = this;
    modal.querySelector('#donationId').value = donationId;
    modal.querySelector('#donorName').value = donorName;
    modal.querySelector('#bloodType').value = bloodType;
    
    // Reset form
    modal.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    modal.querySelector('#testNotes').value = '';
});
</script>
