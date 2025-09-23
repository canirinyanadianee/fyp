<?php
require_once 'includes/config.php';
$page_title = "Features - " . APP_NAME;
include 'includes/header.php';
?>

<!-- Features Section -->
<section class="py-5 mt-5">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold">Our Features</h1>
            <p class="lead text-muted">Discover what makes our blood management system unique</p>
        </div>
        
        <div class="row g-4">
            <!-- Feature 1 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary bg-gradient text-white rounded-3 mb-3 p-3">
                            <i class="fas fa-tint fa-2x"></i>
                        </div>
                        <h3>Blood Inventory</h3>
                        <p class="text-muted">Real-time tracking of blood stock levels across all blood banks.</p>
                    </div>
                </div>
            </div>
            
            <!-- Feature 2 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-gradient text-white rounded-3 mb-3 p-3">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                        <h3>Donor Matching</h3>
                        <p class="text-muted">Efficient matching of donors with blood requests.</p>
                    </div>
                </div>
            </div>
            
            <!-- Feature 3 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-warning bg-gradient text-white rounded-3 mb-3 p-3">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <h3>Analytics</h3>
                        <p class="text-muted">Comprehensive reports and analytics for better decision making.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
