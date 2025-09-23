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
    try {
        // Validate input
        $blood_type = trim($_POST['blood_type'] ?? '');
        $location = trim($_POST['location'] ?? '');
        
        if (empty($blood_type)) {
            throw new Exception('Please select a blood type');
        }
        
        if (empty($location)) {
            throw new Exception('Please enter a location');
        }
        
        // Basic query to find matching donors by blood type
        $query = "SELECT d.*, 
                 (SELECT COUNT(*) FROM  blood_donations WHERE donor_id = d.id) as donation_count,
                 (SELECT MAX(donation_date) FROM blood_donations WHERE donor_id = d.id) as last_donation,
                 CASE 
                     WHEN d.blood_type LIKE 'O%' THEN 'Universal Donor (O)'
                     WHEN d.blood_type LIKE 'A%' THEN 'Type A Donor'
                     WHEN d.blood_type LIKE 'B%' THEN 'Type B Donor'
                     WHEN d.blood_type LIKE 'AB%' THEN 'Universal Recipient (AB)'
                     ELSE 'Other'
                 END as blood_category
                 FROM donors d 
                 WHERE d.blood_type = ?
                 ORDER BY last_donation ASC, donation_count ASC";
        
        if (!($stmt = $conn->prepare($query))) {
            throw new Exception('Failed to prepare query: ' . $conn->error);
        }
        
        if (!$stmt->bind_param('s', $blood_type)) {
            throw new Exception('Failed to bind parameters: ' . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Query execution failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result === false) {
            throw new Exception('Failed to get result set: ' . $stmt->error);
        }
        
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log('Donor Matching Error: ' . $e->getMessage());
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
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
                                            <th>Donor Details</th>
                                            <th>Blood Type</th>
                                            <th>Category</th>
                                            <th>Last Donation</th>
                                            <th>Total Donations</th>
                                            <th>Contact</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $donor): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="avatar bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px;">
                                                                <?= strtoupper(substr($donor['first_name'], 0, 1) . substr($donor['last_name'], 0, 1)) ?>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h6>
                                                            <small class="text-muted">
                                                                <?php 
                                                                $address = [];
                                                                if (!empty($donor['city'])) $address[] = $donor['city'];
                                                                if (!empty($donor['state'])) $address[] = $donor['state'];
                                                                echo htmlspecialchars(implode(', ', $address));
                                                                ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?= $donor['blood_type'] ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $categoryClass = 'bg-primary';
                                                    if (strpos($donor['blood_category'], 'Universal Donor') !== false) {
                                                        $categoryClass = 'bg-success';
                                                    } elseif (strpos($donor['blood_category'], 'Universal Recipient') !== false) {
                                                        $categoryClass = 'bg-info text-dark';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $categoryClass ?>">
                                                        <?= $donor['blood_category'] ?>
                                                    </span>
                                                </td>
                                                <td><?= $donor['last_donation'] ? date('M j, Y', strtotime($donor['last_donation'])) : 'Never' ?></td>
                                                <td><?= $donor['donation_count'] ?></td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <?php if (!empty($donor['phone'])): ?>
                                                            <a href="tel:<?= htmlspecialchars($donor['phone']) ?>" class="text-decoration-none">
                                                                <i class="fas fa-phone me-2"></i> 
                                                                <span class="fw-medium"><?= htmlspecialchars($donor['phone']) ?></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted"><i class="fas fa-phone me-2"></i> N/A</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($donor['email'])): ?>
                                                            <a href="mailto:<?= htmlspecialchars($donor['email']) ?>" class="text-decoration-none mt-1 d-block">
                                                                <i class="fas fa-envelope me-2"></i> 
                                                                <small><?= htmlspecialchars($donor['email']) ?></small>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted small mt-1 d-block"><i class="fas fa-envelope me-2"></i> N/A</span>
                                                        <?php endif; ?>
                                                    </div>
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

<!-- Donor Details Modal -->
<div class="modal fade" id="donorModal" tabindex="-1" aria-labelledby="donorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="donorModalLabel">Donor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="donorDetails">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center my-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading donor details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let donorModal = new bootstrap.Modal(document.getElementById('donorModal'));

function viewDonor(id) {
    // Show loading state
    document.getElementById('donorDetails').innerHTML = `
        <div class="text-center my-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading donor details...</p>
        </div>`;
    
    // Show the modal
    donorModal.show();
    
    // Fetch donor details via AJAX
    fetch(`get_donor_details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('donorDetails').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('donorDetails').innerHTML = `
                <div class="alert alert-danger">
                    Failed to load donor details. Please try again.
                </div>`;
        });
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
