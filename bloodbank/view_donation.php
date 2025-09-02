<?php
/**
 * Blood Bank - View Donation Details
 * Displays detailed information about a specific blood donation
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a blood bank
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'bloodbank') {
    // Redirect to login page with error message
    $_SESSION['error'] = "You must be logged in as a blood bank to access this page";
    header("Location: ../login.php");
    exit();
}

// Get blood bank ID
$bloodbank_id = $_SESSION['user_id'];

// Check if donation ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Missing donation ID";
    header("Location: index.php");
    exit();
}

$donation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Validate donation ID
if (!$donation_id) {
    $_SESSION['error'] = "Invalid donation ID";
    header("Location: index.php");
    exit();
}

// Get donation details with donor information
$donation_query = "SELECT d.*, 
                  CONCAT(dr.first_name, ' ', dr.last_name) AS donor_name, 
                  dr.email AS donor_email,
                  dr.blood_type, dr.weight_kg, dr.height_cm, dr.medical_conditions,
                  dr.allergies, dr.medications
                  FROM blood_donations d
                  JOIN donors dr ON d.donor_id = dr.id
                  WHERE d.id = ? AND d.blood_bank_id = ?";
$stmt = $conn->prepare($donation_query);
$stmt->bind_param("ii", $donation_id, $bloodbank_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Donation not found or not authorized";
    header("Location: index.php");
    exit();
}

$donation = $result->fetch_assoc();

// Include header
$page_title = "View Donation";
include_once '../includes/admin_header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="record_donation.php">Donations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">View Donation</li>
                </ol>
            </nav>
            <h2 class="page-title"><i class="fas fa-file-medical-alt me-2"></i>Donation Details</h2>
        </div>
        <div class="col-auto">
            <a href="record_donation.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Donations
            </a>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Donation Information -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-hand-holding-medical me-2"></i>Donation Information</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <th width="30%">Donation ID</th>
                                    <td>#<?php echo $donation['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <td><?php echo date('F d, Y', strtotime($donation['donation_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Time</th>
                                    <td><?php echo date('h:i A', strtotime($donation['donation_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Blood Type</th>
                                    <td><span class="badge bg-danger"><?php echo $donation['blood_type']; ?></span></td>
                                </tr>
                                <tr>
                                    <th>Quantity</th>
                                    <td><?php echo $donation['quantity_ml']; ?> ml</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <?php if ($donation['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($donation['status'] === 'in_testing'): ?>
                                            <span class="badge bg-warning">In Testing</span>
                                        <?php elseif ($donation['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($donation['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Notes</th>
                                    <td><?php echo !empty($donation['notes']) ? nl2br(htmlspecialchars($donation['notes'])) : '<em class="text-muted">No notes provided</em>'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($donation['status'] === 'in_testing'): ?>
                        <div class="mt-3">
                            <h6 class="mb-3">Update Donation Status</h6>
                            <div class="d-flex">
                                <a href="update_donation_status.php?id=<?php echo $donation_id; ?>&status=completed" class="btn btn-success me-2">
                                    <i class="fas fa-check-circle me-1"></i> Mark as Completed
                                </a>
                                <a href="update_donation_status.php?id=<?php echo $donation_id; ?>&status=rejected" class="btn btn-danger">
                                    <i class="fas fa-times-circle me-1"></i> Reject Donation
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Donor Information -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Donor Information</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-circle bg-primary me-3">
                            <?php echo strtoupper(substr($donation['donor_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($donation['donor_name']); ?></h5>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($donation['donor_email']); ?></p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <th width="30%">Blood Type</th>
                                    <td><span class="badge bg-danger"><?php echo $donation['blood_type']; ?></span></td>
                                </tr>
                                <tr>
                                    <th>Weight</th>
                                    <td><?php echo $donation['weight_kg']; ?> kg</td>
                                </tr>
                                <tr>
                                    <th>Height</th>
                                    <td><?php echo $donation['height_cm']; ?> cm</td>
                                </tr>
                                <tr>
                                    <th>Medical Conditions</th>
                                    <td><?php echo !empty($donation['medical_conditions']) ? nl2br(htmlspecialchars($donation['medical_conditions'])) : '<em class="text-muted">None reported</em>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Allergies</th>
                                    <td><?php echo !empty($donation['allergies']) ? nl2br(htmlspecialchars($donation['allergies'])) : '<em class="text-muted">None reported</em>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Medications</th>
                                    <td><?php echo !empty($donation['medications']) ? nl2br(htmlspecialchars($donation['medications'])) : '<em class="text-muted">None reported</em>'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <a href="find_donor.php?id=<?php echo $donation['donor_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-user me-1"></i> View Full Donor Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Previous Donations from this Donor -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Previous Donations from this Donor</h5>
        </div>
        <div class="card-body">
            <?php
            // Get previous donations from the same donor
            $prev_donations_query = "SELECT id, donation_date, quantity_ml, status 
                                  FROM blood_donations 
                                  WHERE donor_id = ? AND blood_bank_id = ? AND id != ?
                                  ORDER BY donation_date DESC 
                                  LIMIT 5";
            $stmt = $conn->prepare($prev_donations_query);
            $stmt->bind_param("iii", $donation['donor_id'], $bloodbank_id, $donation_id);
            $stmt->execute();
            $prev_result = $stmt->get_result();
            ?>

            <?php if ($prev_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Donation ID</th>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($prev = $prev_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $prev['id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($prev['donation_date'])); ?></td>
                                    <td><?php echo $prev['quantity_ml']; ?> ml</td>
                                    <td>
                                        <?php if ($prev['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($prev['status'] === 'in_testing'): ?>
                                            <span class="badge bg-warning">In Testing</span>
                                        <?php elseif ($prev['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($prev['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_donation.php?id=<?php echo $prev['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No previous donations found from this donor.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .avatar-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: bold;
    }
</style>

<?php
// Include footer
include_once '../includes/admin_footer.php';
?>
