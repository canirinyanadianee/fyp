<?php
// Record Donation page for Blood Banks
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has bloodbank role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit;
}

// Get blood bank details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM blood_banks WHERE user_id = $user_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $blood_bank = $result->fetch_assoc();
    $blood_bank_id = $blood_bank['id'];
} else {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Define blood types
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donor_id = (int)($_POST['donor_id'] ?? 0);
    $blood_type = sanitize_input($_POST['blood_type'] ?? '');
    $quantity_ml = (int)($_POST['quantity_ml'] ?? 0);
    $donation_date = sanitize_input($_POST['donation_date'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $status = sanitize_input($_POST['status'] ?? 'collected');

    // Validate inputs
    if ($donor_id <= 0) {
        $error = 'Please select a valid donor';
    } else if (empty($blood_type) || !in_array($blood_type, $blood_types)) {
        $error = 'Please select a valid blood type';
    } else if ($quantity_ml <= 0) {
        $error = 'Please enter a valid quantity (greater than 0)';
    } else if (empty($donation_date)) {
        $error = 'Please select a donation date';
    } else {
        // Record the donation
        $sql = "INSERT INTO blood_donations (donor_id, blood_bank_id, blood_type, quantity_ml, donation_date, health_notes, status)
                VALUES ($donor_id, $blood_bank_id, '$blood_type', $quantity_ml, '$donation_date', '$notes', '$status')";
        
        if ($conn->query($sql)) {
            // Update blood inventory
            $update_sql = "INSERT INTO blood_inventory (blood_bank_id, blood_type, quantity_ml, expiry_date)
                          VALUES ($blood_bank_id, '$blood_type', $quantity_ml, DATE_ADD(CURDATE(), INTERVAL 42 DAY))
                          ON DUPLICATE KEY UPDATE quantity_ml = quantity_ml + $quantity_ml";
            
            if ($conn->query($update_sql)) {
                $success = 'Donation recorded successfully';
                
                // Get the donation ID for reference
                $donation_id = $conn->insert_id;
                
                // Update donor last donation date and increment donation count
                $conn->query("UPDATE donors SET last_donation_date = '$donation_date', donation_count = donation_count + 1 WHERE id = $donor_id");
            } else {
                $error = 'Error updating inventory: ' . $conn->error;
            }
        } else {
            $error = 'Error recording donation: ' . $conn->error;
        }
    }
}

// Get donors list
$donors_sql = "SELECT d.id, d.first_name, d.last_name, d.blood_type, d.last_donation_date, u.email, d.phone
              FROM donors d
              JOIN users u ON d.user_id = u.id
              ORDER BY d.last_name, d.first_name";
$donors_result = $conn->query($donors_sql);
$donors = [];

if ($donors_result && $donors_result->num_rows > 0) {
    while ($row = $donors_result->fetch_assoc()) {
        $donors[] = $row;
    }
}

$page_title = "Record Donation";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="inventory.php">Blood Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donors.php">Donors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="donations.php">Donations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transfers.php">Hospital Transfers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h2 class="mb-3"><i class="fas fa-hand-holding-medical me-2"></i> Record New Donation</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="donations.php">Donations</a></li>
                        <li class="breadcrumb-item active">Record Donation</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="donor_id" class="form-label">Select Donor</label>
                                <select class="form-select donor-select" id="donor_id" name="donor_id" required>
                                    <option value="">Select a donor</option>
                                    <?php foreach ($donors as $donor): ?>
                                        <option value="<?php echo $donor['id']; ?>" 
                                                data-blood-type="<?php echo htmlspecialchars($donor['blood_type']); ?>"
                                                data-email="<?php echo htmlspecialchars($donor['email'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($donor['phone'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?> 
                                            (<?php echo htmlspecialchars($donor['blood_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="blood_type" class="form-label">Blood Type</label>
                                <select class="form-select" id="blood_type" name="blood_type" required>
                                    <option value="">Select blood type</option>
                                    <?php foreach ($blood_types as $type): ?>
                                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quantity_ml" class="form-label">Quantity (ml)</label>
                                <input type="number" class="form-control" id="quantity_ml" name="quantity_ml" min="100" max="1000" value="450" required>
                                <div class="form-text">Standard donation is about 450ml.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="donation_date" class="form-label">Donation Date</label>
                                <input type="date" class="form-control" id="donation_date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="approved" selected>Approved</option>
                                    <option value="pending">Pending</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            
                            <div class="text-end">
                                <a href="donations.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Record Donation</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Donor Information</h5>
                    </div>
                    <div class="card-body" id="donor-info">
                        <p class="text-muted mb-0">Select a donor to view information</p>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Eligibility Check</h5>
                    </div>
                    <div class="card-body" id="eligibility-info">
                        <p class="text-muted mb-0">Donor eligibility status will appear here</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.donor-select').select2({
                placeholder: "Select a donor",
                allowClear: true
            });
            
            // Update blood type when donor is selected
            $('#donor_id').change(function() {
                var selectedOption = $(this).find('option:selected');
                var bloodType = selectedOption.data('blood-type');
                
                if (bloodType) {
                    $('#blood_type').val(bloodType);
                    
                    // Show donor info
                    var donorId = $(this).val();
                    if (donorId) {
                        // In a real implementation, you would make an AJAX call to get donor details
                        // For now, we'll just show the name and blood type
                        var donorName = selectedOption.text();
                        var email = selectedOption.data('email') || '-';
                        var phone = selectedOption.data('phone') || '-';
                        $('#donor-info').html('<p><strong>Name:</strong> ' + donorName + '</p>' +
                                             '<p><strong>Blood Type:</strong> ' + bloodType + '</p>' +
                                             '<p><strong>Email:</strong> ' + email + '</p>' +
                                             '<p><strong>Phone:</strong> ' + phone + '</p>');
                        
                        // Check eligibility (in a real implementation, this would be an AJAX call)
                        $('#eligibility-info').html('<div class="alert alert-success mb-0"><i class="fas fa-check-circle me-2"></i> This donor is eligible to donate.</div>');
                    }
                }
            });
        });
    </script>
</body>
</html>
