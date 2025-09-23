<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<div class="alert alert-danger">Invalid donor ID</div>');
}

$donor_id = (int)$_GET['id'];

try {
    // Get donor details
    $query = "SELECT d.*, 
              (SELECT COUNT(*) FROM blood_donations WHERE donor_id = d.id) as donation_count,
              (SELECT MAX(donation_date) FROM blood_donations WHERE donor_id = d.id) as last_donation
              FROM donors d 
              WHERE d.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $donor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die('<div class="alert alert-warning">Donor not found</div>');
    }
    
    $donor = $result->fetch_assoc();
    
    // Format last donation date
    $last_donation = $donor['last_donation'] ? date('F j, Y', strtotime($donor['last_donation'])) : 'Never';
    
    // Generate blood type badge
    $blood_type_class = 'bg-primary';
    if (strpos($donor['blood_type'], 'O') === 0) $blood_type_class = 'bg-success';
    if (strpos($donor['blood_type'], 'AB') === 0) $blood_type_class = 'bg-info text-dark';
    
    // Generate blood category
    $blood_category = '';
    if (strpos($donor['blood_type'], 'O') === 0) $blood_category = 'Universal Donor (O)';
    elseif (strpos($donor['blood_type'], 'A') === 0) $blood_category = 'Type A Donor';
    elseif (strpos($donor['blood_type'], 'B') === 0) $blood_category = 'Type B Donor';
    elseif (strpos($donor['blood_type'], 'AB') === 0) $blood_category = 'Universal Recipient (AB)';
    
    // Generate address
    $address = [];
    if (!empty($donor['address'])) $address[] = $donor['address'];
    if (!empty($donor['city'])) $address[] = $donor['city'];
    if (!empty($donor['state'])) $address[] = $donor['state'];
    if (!empty($donor['postal_code'])) $address[] = $donor['postal_code'];
    $full_address = implode(', ', $address);
    
    // Output the donor details
    ?>
    <div class="row">
        <div class="col-md-4 text-center mb-4">
            <div class="avatar bg-primary text-white d-flex align-items-center justify-content-center rounded-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <?= strtoupper(substr($donor['first_name'], 0, 1) . substr($donor['last_name'], 0, 1)) ?>
            </div>
            <h4><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h4>
            <div class="mb-2">
                <span class="badge <?= $blood_type_class ?> fs-6"><?= $donor['blood_type'] ?></span>
                <span class="badge bg-secondary"><?= $blood_category ?></span>
            </div>
            <div class="text-muted">
                <i class="fas fa-id-card me-2"></i> ID: <?= $donor['id'] ?>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-phone me-2 text-primary"></i>
                                <div>
                                    <div class="text-muted small">Phone</div>
                                    <a href="tel:<?= htmlspecialchars($donor['phone']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($donor['phone'] ?? 'N/A') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-envelope me-2 text-primary"></i>
                                <div>
                                    <div class="text-muted small">Email</div>
                                    <a href="mailto:<?= htmlspecialchars($donor['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($donor['email'] ?? 'N/A') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-map-marker-alt me-2 mt-1 text-primary"></i>
                                <div>
                                    <div class="text-muted small">Address</div>
                                    <div><?= !empty($full_address) ? htmlspecialchars($full_address) : 'N/A' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Donation History</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Total Donations</div>
                            <h4 class="mb-0"><?= $donor['donation_count'] ?? 0 ?></h4>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Last Donation</div>
                            <h4 class="mb-0"><?= $last_donation ?></h4>
                        </div>
                        <div class="col-12">
                            <a href="donor_history.php?id=<?= $donor['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-history me-1"></i> View Full Donation History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading donor details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
