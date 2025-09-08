<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "ML Donor Matching";
include '../includes/header.php';

// Include database connection
require_once '../includes/db.php';

// Process form submission
$results = [];
$blood_type = '';   
$location = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blood_type = $_POST['blood_type'] ?? '';
    $location = $_POST['location'] ?? '';
    
    // Basic query to find matching donors by blood type
    $query = "SELECT d.*, 
             (SELECT COUNT(*) FROM blood_donations WHERE donor_id = d.id) as donation_count,
             (SELECT MAX(donation_date) FROM blood_donations WHERE donor_id = d.id) as last_donation
             FROM donors d 
             WHERE d.blood_type = ? 
             AND d.status = 'active'
             ORDER BY last_donation ASC, donation_count ASC";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('s', $blood_type);
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Database query error: " . $conn->error;
        error_log($error);
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Donor Matching</h1>
            </div>

            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Find Matching Donors</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="donorMatchForm">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Blood Type</label>
                                <select class="form-select" name="blood_type" required>
                                    <option value="">Select Blood Type</option>
                                    <option value="A+" <?= $blood_type === 'A+' ? 'selected' : '' ?>>A+</option>
                                    <option value="A-" <?= $blood_type === 'A-' ? 'selected' : '' ?>>A-</option>
                                    <option value="B+" <?= $blood_type === 'B+' ? 'selected' : '' ?>>B+</option>
                                    <option value="B-" <?= $blood_type === 'B-' ? 'selected' : '' ?>>B-</option>
                                    <option value="AB+" <?= $blood_type === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                    <option value="AB-" <?= $blood_type === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                    <option value="O+" <?= $blood_type === 'O+' ? 'selected' : '' ?>>O+</option>
                                    <option value="O-" <?= $blood_type === 'O-' ? 'selected' : '' ?>>O-</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Location (City)</label>
                                <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($location) ?>" required>
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Find Donors
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Matching Donors</h5>
                    <?php if (!empty($results)): ?>
                        <span class="badge bg-primary"><?= count($results) ?> donors found</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <?php if (empty($results)): ?>
                            <div class="alert alert-warning">
                                No matching donors found. Try different search criteria.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Blood Type</th>
                                            <th>Last Donation</th>
                                            <th>Total Donations</th>
                                            <th>Contact</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $donor): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></td>
                                                <td><span class="badge bg-danger"><?= $donor['blood_type'] ?></span></td>
                                                <td><?= $donor['last_donation'] ? date('M j, Y', strtotime($donor['last_donation'])) : 'Never' ?></td>
                                                <td><?= $donor['donation_count'] ?></td>
                                                <td>
                                                    <?= htmlspecialchars($donor['email']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($donor['phone']) ?></small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                                            onclick="viewDonor(<?= $donor['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="mailto:<?= htmlspecialchars($donor['email']) ?>" 
                                                       class="btn btn-sm btn-outline-success me-1">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                    <a href="tel:<?= htmlspecialchars($donor['phone']) ?>" 
                                                       class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>Enter search criteria to find matching donors</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function viewDonor(id) {
    // Open donor details in a modal or new page
    window.location.href = 'donor_details.php?id=' + id;
}

// Add autocomplete for location
$(function() {
    // Initialize location autocomplete
    $('input[name="location"]').autocomplete({
        source: function(request, response) {
            // TODO: Implement city/address autocomplete using a geocoding service
            // Example: Google Places API or OpenStreetMap Nominatim
            console.log('Searching for location:', request.term);
        },
        minLength: 3
    });
});
</script>

<?php include '../includes/footer.php'; ?>
