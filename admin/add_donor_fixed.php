<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

$pageTitle = 'Add New Donor';

// Add custom CSS for the form
$customCSS = "
    .form-section {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        margin-bottom: 2rem;
        padding: 1.5rem;
    }
    .form-section h5 {
        color: #4e73df;
        font-weight: 600;
        border-bottom: 1px solid #e3e6f0;
        padding-bottom: 0.75rem;
        margin-bottom: 1.5rem;
    }
    .form-label {
        font-weight: 500;
        color: #5a5c69;
    }
    .form-control, .form-select {
        border-radius: 0.35rem;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d3e2;
    }
    .form-control:focus, .form-select:focus {
        border-color: #bac8f3;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
        padding: 0.5rem 1.5rem;
        font-weight: 500;
    }
    .btn-outline-secondary {
        padding: 0.5rem 1.5rem;
    }
    .required-field::after {
        content: ' *';
        color: #e74a3b;
    }";


// Ensure session is started before using $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $required = ['first_name', 'last_name', 'email', 'phone', 'blood_type', 'gender', 'dob', 'address'];
        $errors = [];

        foreach ($required as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate email
        if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }

        // Validate phone number (basic validation)
        if (!preg_match('/^[0-9+\-\s()]{10,20}$/', $_POST['phone'] ?? '')) {
            $errors[] = 'Please enter a valid phone number';
        }

        // Validate date of birth
        $dob = null;
        try {
            $dob = new DateTime($_POST['dob'] ?? '');
        } catch (Exception $e) {
            $errors[] = 'Invalid date of birth format';
        }
        $minAgeDate = new DateTime('-18 years');
        if ($dob && $dob > $minAgeDate) {
            $errors[] = 'Donor must be at least 18 years old';
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'donor', 1)");
                $username = strtolower($_POST['first_name'] . '.' . $_POST['last_name'] . rand(100, 999));
                $password = password_hash('password123', PASSWORD_DEFAULT);
                $stmt->bind_param('sss', $username, $_POST['email'], $password);
                $stmt->execute();
                $user_id = $conn->insert_id;

                $stmt = $conn->prepare("
                    INSERT INTO donors (
                        user_id, first_name, last_name, email, phone, blood_type,
                        gender, dob, address, city, state, postal_code,
                        country, health_notes, last_donation_date, is_eligible, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW())
                ");
                $stmt->bind_param(
                    'isssssssssssss',
                    $user_id,
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['blood_type'],
                    $_POST['gender'],
                    $_POST['dob'],
                    $_POST['address'],
                    $_POST['city'] ?? '',
                    $_POST['state'] ?? '',
                    $_POST['postal_code'] ?? '',
                    $_POST['country'] ?? '',
                    $_POST['health_notes'] ?? ''
                );
                $stmt->execute();
                $donor_id = $conn->insert_id;

                $conn->commit();
                $_SESSION['success'] = 'Donor added successfully';
                header('Location: donors.php');
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Error adding donor: ' . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $errors[] = 'An error occurred: ' . $e->getMessage();
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-gray-800">Add New Donor</h1>
                <a href="donors.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Donors
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> Please fix the following issues:
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Donor Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="add_donor_fixed.php" id="addDonorForm" class="needs-validation" novalidate>
                        <!-- Personal Information Section -->
                        <div class="form-section mb-4">
                            <h5><i class="fas fa-user-circle me-2"></i>Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required 
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                                    <div class="invalid-feedback">Please enter first name</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                                    <div class="invalid-feedback">Please enter last name</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label required-field">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" required
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="phone" class="form-label required-field">Phone</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="phone" name="phone" required
                                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid phone number</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="blood_type" class="form-label required-field">Blood Type</label>
                                    <select class="form-select" id="blood_type" name="blood_type" required>
                                        <option value="">Select Blood Type</option>
                                        <option value="A+" <?= ($_POST['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= ($_POST['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= ($_POST['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= ($_POST['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= ($_POST['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= ($_POST['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                        <option value="O+" <?= ($_POST['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= ($_POST['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                    </select>
                                    <div class="invalid-feedback">Please select blood type</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="gender" class="form-label required-field">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                        <option value="Prefer not to say" <?= ($_POST['gender'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                    </select>
                                    <div class="invalid-feedback">Please select gender</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="dob" class="form-label required-field">Date of Birth</label>
                                    <input type="date" class="form-control" id="dob" name="dob" required
                                           max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                           value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                                    <div class="invalid-feedback">Donor must be at least 18 years old</div>
                                    <div class="form-text">Minimum age: 18 years</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address Information Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-address-card me-2"></i>Address Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="address" class="form-label required-field">Street Address</label>
                                    <input type="text" class="form-control" id="address" name="address" required
                                           value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                                    <div class="invalid-feedback">Please enter street address</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city"
                                           value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="state" name="state"
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code"
                                           value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country"
                                           value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="health_notes" class="form-label">Health Notes</label>
                                <textarea class="form-control" id="health_notes" name="health_notes" rows="3"><?= htmlspecialchars($_POST['health_notes'] ?? '') ?></textarea>
                                <div class="form-text">Any relevant health information or notes about the donor.</div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">Reset</button>
                            <button type="submit" class="btn btn-primary">Add Donor</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Phone number formatting
    document.getElementById('phone').addEventListener('input', function (e) {
        var x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
        e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
    
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
