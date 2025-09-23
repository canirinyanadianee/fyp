<?php
require_once 'includes/config.php';
$page_title = "About - " . APP_NAME;
include 'includes/header.php';
?>

<!-- About Section -->
<section class="py-5 mt-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-5 fw-bold mb-4">About Our Blood Management System</h1>
                <p class="lead">Connecting donors with those in need through technology.</p>
                <p>Our blood management system is designed to streamline the process of blood donation and distribution, ensuring that life-saving blood reaches those who need it most, when they need it.</p>
                
                <div class="mt-4">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0 text-primary me-3">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div>
                            <h5>Our Mission</h5>
                            <p class="mb-0">To save lives by making blood donation and distribution more efficient and accessible.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0 text-primary me-3">
                            <i class="fas fa-bullseye fa-2x"></i>
                        </div>
                        <div>
                            <h5>Our Vision</h5>
                            <p class="mb-0">A world where no one dies waiting for a blood transfusion.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <img src="assets/img/about-hero.jpg" alt="About Us" class="img-fluid rounded-3 shadow">
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-6 fw-bold">Our Team</h2>
            <p class="lead text-muted">The people behind the mission</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <img src="assets/img/team-1.jpg" class="card-img-top" alt="Team Member">
                    <div class="card-body text-center">
                        <h5 class="card-title">John Doe</h5>
                        <p class="text-muted">Founder & CEO</p>
                    </div>
                </div>
            </div>
            <!-- Add more team members as needed -->
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
