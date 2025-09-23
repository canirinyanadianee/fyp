<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'bloodbank') {
    header('Location: index.php');
    exit();
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            // Prepare and execute query
            $stmt = $pdo->prepare("SELECT u.*, b.id as blood_bank_id, b.name as blood_bank_name 
                                 FROM users u 
                                 LEFT JOIN blood_banks b ON u.id = b.user_id 
                                 WHERE u.username = :username AND u.role = 'bloodbank' AND u.is_active = 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['blood_bank_id'] = $user['blood_bank_id'];
                $_SESSION['blood_bank_name'] = $user['blood_bank_name'];
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                $updateStmt->execute([':id' => $user['id']]);
                
                // Redirect to dashboard
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Staff Login - Blood Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        
        .form-signin {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
        
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        
        .form-signin input[type="text"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo i {
            font-size: 3.5rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background-color: #dc3545;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .login-body {
            background-color: white;
            padding: 2rem;
        }
    </style>
</head>
<body class="text-center">
    <main class="form-signin">
        <div class="login-card">
            <div class="login-header">
                <h3><i class="fas fa-warehouse"></i></h3>
                <h4>Blood Bank Staff Login</h4>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                        <label for="username">Username</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                    </div>
                    
                    <button class="w-100 btn btn-lg btn-danger" type="submit">Sign in</button>
                    
                    <div class="mt-3">
                        <a href="../forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                </form>
                
                <hr class="my-4">
                <p class="text-muted">
                    <a href="../index.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Back to Home
                    </a>
                </p>
            </div>
        </div>
    </main>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
