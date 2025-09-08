<!-- Sidebar -->
<nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?> text-white" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'donors.php' ? 'active' : ''; ?> text-white" href="donors.php">
                    <i class="fas fa-users me-2"></i>Manage Donors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'hospitals.php' ? 'active' : ''; ?> text-white" href="hospitals.php">
                    <i class="fas fa-hospital me-2"></i>Hospitals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bloodbank.php' ? 'active' : ''; ?> text-white" href="bloodbank.php">
                    <i class="fas fa-tint me-2"></i>Blood Banks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?> text-white" href="inventory.php">
                    <i class="fas fa-boxes me-2"></i>Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ml_dashboard.php' ? 'active' : ''; ?> text-white" href="ml_dashboard.php">
                    <i class="fas fa-brain me-2"></i>AI/ML Insights
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?> text-white" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?> text-white" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
        </ul>
        
        <div class="position-sticky pt-3 mt-4">
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mb-1 text-muted">
                <span>ML Models</span>
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-white" href="ml_donor_matching.php">
                        <i class="fas fa-people-arrows me-2"></i>Donor Matching
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="ml_anomaly_detection.php">
                        <i class="fas fa-exclamation-triangle me-2"></i>Anomaly Detection
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="ml_forecasting.php">
                        <i class="fas fa-chart-line me-2"></i>Demand Forecasting
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
