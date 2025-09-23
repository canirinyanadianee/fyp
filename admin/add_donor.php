<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

$pageTitle = 'Add New Donor';

// Add custom CSS for the form
$customCSS = "
    /* Professional Form Styling */
    :root {
        --primary: #2c3e50;
        --primary-light: #34495e;
        --primary-lighter: #f8f9fa;
        --secondary: #95a5a6;
        --success: #27ae60;
        --danger: #e74c3c;
        --warning: #f39c12;
        --info: #3498db;
        --light: #f8f9fa;
        --dark: #2c3e50;
        --border-radius: 0.35rem;
        --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        --transition: all 0.2s ease-in-out;
        --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    
    body {
        font-family: var(--font-sans);
        background-color: #f5f7fa;
        color: #2c3e50;
        line-height: 1.6;
    }

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


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic validation
        $required = ['first_name', 'last_name', 'email', 'phone', 'blood_type', 'gender', 'dob', 'address'];
        $errors = [];
        foreach ($required as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        if (!preg_match('/^[0-9+\-\s()]{10,20}$/', $_POST['phone'] ?? '')) {
            $errors[] = 'Please enter a valid phone number';
        }
        $dob = new DateTime($_POST['dob']);
        $minAgeDate = new DateTime('-18 years');
        if ($dob > $minAgeDate) {
            $errors[] = 'Donor must be at least 18 years old';
        }

        // Email uniqueness (users.email may not exist in some schemas)
        if (empty($errors)) {
            $hasEmailCol = false;
            try { $hasEmailCol = $conn->query("SHOW COLUMNS FROM users LIKE 'email'")->rowCount() > 0; } catch (Exception $e) { $hasEmailCol = false; }
            if ($hasEmailCol) {
                $chk = $conn->prepare('SELECT id FROM users WHERE email = ?');
                $chk->execute([$_POST['email']]);
                if ($chk->fetch()) {
                    $errors[] = 'Email is already registered';
                }
            } else {
                // Fallback: ensure donors table doesn't already have this email
                try {
                    $chk2 = $conn->prepare('SELECT id FROM donors WHERE email = ?');
                    $chk2->execute([$_POST['email']]);
                    if ($chk2->fetch()) {
                        $errors[] = 'Email is already registered to another donor';
                    }
                } catch (Exception $e) {}
            }
        }

        if (empty($errors)) {
            $conn->beginTransaction();
            try {
                // Optional columns detection
                $hasIsActive = false; $hasStatus = false; $hasUsername = false; $hasEmail = false; $hasRole = false;
                try { $hasIsActive = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'")->rowCount() > 0; } catch (Exception $e) {}
                try { $hasStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'")->rowCount() > 0; } catch (Exception $e) {}
                try { $hasUsername = $conn->query("SHOW COLUMNS FROM users LIKE 'username'")->rowCount() > 0; } catch (Exception $e) {}
                try { $hasEmail = $conn->query("SHOW COLUMNS FROM users LIKE 'email'")->rowCount() > 0; } catch (Exception $e) {}
                try { $hasRole = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->rowCount() > 0; } catch (Exception $e) {}

                // Build users insert
                $username = strtolower(($_POST['first_name'] ?? 'donor') . '.' . ($_POST['last_name'] ?? 'user') . rand(100, 999));
                $tempPassword = bin2hex(random_bytes(4)); // 8 hex chars
                $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

                // Ensure we have at least one identifier column (email or username)
                if (!$hasEmail && !$hasUsername) {
                    throw new Exception("Users table has neither 'email' nor 'username' columns. Cannot create login.");
                }

                $cols = ['password'];
                $vals = [$passwordHash];
                $ph = ['?'];
                if ($hasEmail) { $cols[]='email'; $ph[]='?'; $vals[]=$_POST['email']; }
                if ($hasUsername) { $cols[]='username'; $ph[]='?'; $vals[]=$username; }
                if ($hasRole) { $cols[]='role'; $ph[]='?'; $vals[]='donor'; }
                if ($hasIsActive) { $cols[]='is_active'; $ph[]='?'; $vals[]=1; }
                if ($hasStatus) { $cols[]='status'; $ph[]='?'; $vals[]='active'; }

                $sqlUser = 'INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
                $stmtUser = $conn->prepare($sqlUser);
                $stmtUser->execute($vals);
                $user_id = (int)$conn->lastInsertId();

                // Helper to check column existence
                $hasCol = function(string $table, string $col) use ($conn): bool {
                    try {
                        $q = $conn->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                        $q->execute([$col]);
                        return $q->rowCount() > 0;
                    } catch (Exception $e) { return false; }
                };

                // Build donors insert dynamically based on available columns
                $dCols = ['user_id'];
                $dVals = [$user_id];
                $dPh   = ['?'];
                if ($hasCol('donors','first_name')) { $dCols[]='first_name'; $dVals[]=$_POST['first_name']; $dPh[]='?'; }
                if ($hasCol('donors','last_name')) { $dCols[]='last_name'; $dVals[]=$_POST['last_name']; $dPh[]='?'; }
                if ($hasCol('donors','email')) { $dCols[]='email'; $dVals[]=$_POST['email']; $dPh[]='?'; }
                if ($hasCol('donors','phone')) { $dCols[]='phone'; $dVals[]=$_POST['phone']; $dPh[]='?'; }
                if ($hasCol('donors','blood_type')) { $dCols[]='blood_type'; $dVals[]=$_POST['blood_type']; $dPh[]='?'; }
                if ($hasCol('donors','gender')) { $dCols[]='gender'; $dVals[]=$_POST['gender']; $dPh[]='?'; }
                if ($hasCol('donors','dob')) { $dCols[]='dob'; $dVals[]=$_POST['dob']; $dPh[]='?'; }
                if ($hasCol('donors','address')) { $dCols[]='address'; $dVals[]=$_POST['address']; $dPh[]='?'; }
                if ($hasCol('donors','city')) { $dCols[]='city'; $dVals[]=($_POST['city'] ?? ''); $dPh[]='?'; }
                if ($hasCol('donors','state')) { $dCols[]='state'; $dVals[]=($_POST['state'] ?? ''); $dPh[]='?'; }
                if ($hasCol('donors','postal_code')) { $dCols[]='postal_code'; $dVals[]=($_POST['postal_code'] ?? ''); $dPh[]='?'; }
                if ($hasCol('donors','country')) { $dCols[]='country'; $dVals[]=($_POST['country'] ?? ''); $dPh[]='?'; }
                if ($hasCol('donors','health_notes')) { $dCols[]='health_notes'; $dVals[]=($_POST['health_notes'] ?? ''); $dPh[]='?'; }
                if ($hasCol('donors','last_donation_date')) { $dCols[]='last_donation_date'; $dVals[]=null; $dPh[]='?'; }
                if ($hasCol('donors','is_eligible')) { $dCols[]='is_eligible'; $dVals[]=1; $dPh[]='?'; }
                if ($hasCol('donors','created_at')) { $dCols[]='created_at'; $dVals[]=date('Y-m-d H:i:s'); $dPh[]='?'; }

                $sqlDonor = 'INSERT INTO donors (' . implode(',', $dCols) . ') VALUES (' . implode(',', $dPh) . ')';
                $stmtDonor = $conn->prepare($sqlDonor);
                $stmtDonor->execute($dVals);

                $conn->commit();
                $_SESSION['success'] = 'Donor added successfully.';
                $_SESSION['info'] = 'Temporary password for the donor user: ' . htmlspecialchars($tempPassword) . ' (please share securely and have them change it after first login).';
                header('Location: donors.php');
                exit();
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $errors[] = 'Error adding donor: ' . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $errors[] = 'An error occurred: ' . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<!-- Add custom CSS to head -->
<style><?= $customCSS ?></style>

<div class="container-fluid">
    <div class="row">
        <!-- Include Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="donors.php" class="text-decoration-none">Donors</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Add New</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-1 text-gray-900">Add New Donor</h1>
                    <p class="text-muted mb-0">Register a new blood donor in the system</p>
                </div>
                <div class="d-flex">
                    <a href="donors.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                    <button type="submit" form="addDonorForm" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Donor
                    </button>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div>
                            <strong>Please fix the following errors:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-plus text-primary me-2"></i>
                        <h6 class="m-0">Donor Information</h6>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="add_donor.php" id="addDonorForm" class="needs-validation" novalidate>
                        <!-- Personal Information Section -->
                        <div class="form-section mb-4">
                            <h5 class="d-flex align-items-center">
                                <i class="fas fa-user-circle"></i>
                                <span>Personal Information</span>
                            </h5>
                            <div class="section-content">
                                <p class="text-muted mb-4 small">Please provide the donor's personal details. Fields marked with <span class="text-danger">*</span> are required.</p>
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
                        </div>
                        
                        <!-- Address Information Section -->
                        <div class="form-section">
                            <h5 class="d-flex align-items-center">
                                <i class="fas fa-address-card"></i>
                                <span>Address Information</span>
                            </h5>
                            <div class="section-content">
                                <p class="text-muted mb-4 small">Please provide the donor's contact information and address details.</p>
                            <div class="row g-3">
                            
                            <div class="col-12 mb-3">
                                <label for="address" class="form-label">Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address" name="address" required
                                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="state" name="state"
                                       value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code"
                                       value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country"
                                       value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
                            </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="health_notes" class="form-label">Health Notes</label>
                            <textarea class="form-control" id="health_notes" name="health_notes" rows="3"><?= htmlspecialchars($_POST['health_notes'] ?? '') ?></textarea>
                            <div class="form-text">Any relevant health information or notes about the donor.</div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
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
// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Phone number formatting
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            const x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Reset previous validation states
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
            
            // Check required fields
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add error message if not already present
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'This field is required';
                        field.parentNode.insertBefore(errorDiv, field.nextSibling);
                    }
                }
            });
            
            // Email validation
            const emailField = form.querySelector('input[type="email"]');
            if (emailField && emailField.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value)) {
                isValid = false;
                emailField.classList.add('is-invalid');
                
                // Add error message if not already present
                if (!emailField.nextElementSibling || !emailField.nextElementSibling.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'Please enter a valid email address';
                    emailField.parentNode.insertBefore(errorDiv, emailField.nextSibling);
                }
            }
            
            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
                
                // Remove any existing alert
                const existingAlert = form.parentNode.querySelector('.alert.alert-danger');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                // Show error message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger mt-3';
                alertDiv.role = 'alert';
                alertDiv.textContent = 'Please fill in all required fields correctly.';
                
                // Insert after the form
                form.parentNode.insertBefore(alertDiv, form.nextSibling);
                
                // Scroll to the first error
                const firstError = form.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            form.classList.add('was-validated');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
