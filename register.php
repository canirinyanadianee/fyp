<?php
// Registration page
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    
    // Validate inputs
    if (empty($username)) {
        $error = 'Username is required';
    } else if (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long';
    } else if (empty($email)) {
        $error = 'Email is required';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else if (empty($password)) {
        $error = 'Password is required';
    } else if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else if (empty($role) || !in_array($role, ['bloodbank', 'hospital', 'donor'])) {
        $error = 'Please select a valid role';
    } else if (empty($full_name)) {
        $error = 'Full name is required';
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                // Create role-specific record
                switch ($role) {
                    case 'bloodbank':
                        $role_sql = "INSERT INTO blood_banks (user_id, name, license_number, contact_email) VALUES (?, ?, ?, ?)";
                        $role_stmt = $conn->prepare($role_sql);
                        $license_number = 'BB' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                        $role_stmt->bind_param("isss", $user_id, $full_name, $license_number, $email);
                        $role_stmt->execute();
                        break;
                    case 'hospital':
                        $role_sql = "INSERT INTO hospitals (user_id, name, license_number, contact_email) VALUES (?, ?, ?, ?)";
                        $role_stmt = $conn->prepare($role_sql);
                        $license_number = 'HOS' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                        $role_stmt->bind_param("isss", $user_id, $full_name, $license_number, $email);
                        $role_stmt->execute();
                        break;
                    case 'donor':
                        // For donors, we'll just create the user record for now
                        break;
                }
                // Registration succeeded — do NOT auto-login.
                // Send user to the login page with a flash message prompting them to sign in.
                $_SESSION['flash_success'] = 'Account created successfully. Please login to continue.';
                header('Location: login.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card register-card border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold text-dark">Create Account</h2>
                            <p class="text-muted">Join <?php echo APP_NAME; ?></p>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <br><a href="login.php" class="text-decoration-none">Click here to login</a>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                               placeholder="Enter full name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                               placeholder="Choose username" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="Enter email address" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Account Type *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select account type</option>
                                    <option value="donor" <?php echo (($_POST['role'] ?? '') === 'donor') ? 'selected' : ''; ?>>
                                        Blood Donor
                                    </option>
                                    <option value="bloodbank" <?php echo (($_POST['role'] ?? '') === 'bloodbank') ? 'selected' : ''; ?>>
                                        Blood Bank
                                    </option>
                                    <option value="hospital" <?php echo (($_POST['role'] ?? '') === 'hospital') ? 'selected' : ''; ?>>
                                        Hospital
                                    </option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password *</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Enter password" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirm password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-register btn-primary btn-lg text-white">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Already have an account? 
                                <a href="login.php" class="text-decoration-none">Sign in here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
