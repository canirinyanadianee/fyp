<?php
// Main index page
require_once 'includes/config.php';

// Always show the welcome page, even if user is logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Saving Lives Through Technology</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style id="theme-style">
        .hero-section {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            background-image: url('4.png');
            min-height: 100vh;
            min-width:100vh;
            display: flex;
            align-items: center;
            color: black;
        }
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .stats-section {
            background-color: #f8f9fa;
            font-weight:bold;
        }
        body.dark-mode {
            background: #181a1b !important;
            color: #e0e0e0 !important;
            background-color:bold;
            
        }
        body.dark-mode .navbar, body.dark-mode .card, body.dark-mode .register-card, body.dark-mode .feature-card, body.dark-mode .stats-section, body.dark-mode .modal-content {
            background-color: #23272b !important;
            color: #e0e0e0 !important;
            background-color:bold;
            
        }
        body.dark-mode .navbar-light .navbar-nav .nav-link, body.dark-mode .navbar-light .navbar-brand {
            color: #e0e0e0 !important;
            background-color:bold;
            
        }
        body.dark-mode .table, body.dark-mode .table-bordered, body.dark-mode .table-light {
            color: #e0e0e0 !important;
            background-color: #23272b !important;
            color:rgb(7, 91, 10) !important;
            background-color:rgb(2, 15, 28);
            /* font-weight:bold; */
        }
        body.dark-mode .bg-white, body.dark-mode .bg-light, body.dark-mode .bg-primary, body.dark-mode .bg-info, body.dark-mode .bg-warning, body.dark-mode .bg-danger, body.dark-mode .bg-success, body.dark-mode .bg-secondary, body.dark-mode .bg-dark {
            background-color: #23272b !important;
            color:rgb(7, 91, 10) !important;
            
            /* font-weight:bold; */
        }
        body.dark-mode .btn, body.dark-mode .btn-primary, body.dark-mode .btn-outline-primary, body.dark-mode .btn-light, body.dark-mode .btn-outline-light {
            color:rgb(7, 91, 10) !important;
            background-color:rgb(2, 15, 28);
            /* font-weight:bold; */
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
   <i class="fas fa-heartbeat text-danger me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-2">
                        <button id="theme-toggle" class="btn btn-outline-secondary" title="Toggle light/dark mode">
                            <i class="fas fa-moon"></i>
                        </button>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white ms-2 px-3" href="register.php">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                       Blood connect
                    </h1>
                    <p class="lead mb-4">
                    Design and Deployment of a Mobile Application for Enhancing Blood Donation and Management Services"
                    </p>
                    <div class="d-flex gap-3">
                        <a href="register.php" class="btn btn-light btn-lg">
                            <i class="fas fa-rocket me-2"></i>Get Started
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-heartbeat" style="font-size: 15rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Platform Features</h2>
                <p class="lead text-muted">Comprehensive solutions for all stakeholders</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-hospital fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Hospital Portal</h5>
                            <p class="card-text">
                                Request blood, track inventory, and manage transfers with real-time updates.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-3x text-danger mb-3"></i>
                            <h5 class="card-title">Blood Bank Management</h5>
                            <p class="card-text">
                                Complete donor management, inventory tracking, and donation processing.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-brain fa-3x text-success mb-3"></i>
                            <h5 class="card-title">AI Predictions</h5>
                            <p class="card-text">
                                Smart forecasting for blood usage patterns and inventory optimization.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-warning mb-3"></i>
                            <h5 class="card-title">Donor Portal</h5>
                            <p class="card-text">
                                Easy appointment booking, health tracking, and donation history.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="mb-3">
                        <i class="fas fa-heartbeat fa-2x text-danger"></i>
                    </div>
                    <h3 class="fw-bold">1000+</h3>
                    <p class="text-muted">Lives Saved</p>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <i class="fas fa-hospital fa-2x text-primary"></i>
                    </div>
                    <h3 class="fw-bold">50+</h3>
                    <p class="text-muted">Partner Hospitals</p>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <i class="fas fa-building fa-2x text-success"></i>
                    </div>
                    <h3 class="fw-bold">25+</h3>
                    <p class="text-muted">Blood Banks</p>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <i class="fas fa-users fa-2x text-warning"></i>
                    </div>
                    <h3 class="fw-bold">5000+</h3>
                    <p class="text-muted">Registered Donors</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="display-6 fw-bold mb-4">About Our System</h2>
                    <p class="lead mb-4">
                        Our system revolutionizes how blood banks, 
                        hospitals, and donors interact. With predictive analytics and real-time 
                        tracking, we ensure blood is available when and where it's needed most.
                    </p>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Real-time inventory management
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            AI-powered demand forecasting
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Seamless hospital integration
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Comprehensive donor management
                        </li>
                    </ul>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-brain" style="font-size: 10rem; color: #667eea; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-heartbeat me-2"></i>
                        <?php echo APP_NAME; ?>
                    </h5>
                    <p class="mb-3">
                        Revolutionizing blood management through AI and technology.
                        Saving lives, one donation at a time.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Quick Links</h6>
                            <ul class="list-unstyled">
                                <li><a href="register.php" class="text-white-50 text-decoration-none">Register</a></li>
                                <li><a href="login.php" class="text-white-50 text-decoration-none">Login</a></li>
                                <li><a href="#features" class="text-white-50 text-decoration-none">Features</a></li>
                                <li><a href="#about" class="text-white-50 text-decoration-none">About</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Contact Info</h6>
                            <p class="text-white-50 mb-1">
                                <i class="fas fa-envelope me-2"></i>
                                info@bloodbank.com
                            </p>
                            <p class="text-white-50 mb-1">
                                <i class="fas fa-phone me-2"></i>
                                +1 (555) 123-4567
                            </p>
                            <p class="text-white-50">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                123 Medical Center Drive
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Light/Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        function setTheme(dark) {
            if (dark) {
                document.body.classList.add('dark-mode');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                localStorage.setItem('theme', 'light');
            }
        }
        // On load
        const savedTheme = localStorage.getItem('theme');
        setTheme(savedTheme === 'dark' || (!savedTheme && prefersDark));
        themeToggle.addEventListener('click', function() {
            setTheme(!document.body.classList.contains('dark-mode'));
        });
    </script>
</body>
</html>
