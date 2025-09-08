<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Admin Dashboard";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active text-white" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="donors.php">
                            <i class="fas fa-users me-2"></i>Donors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="hospitals.php">
                            <i class="fas fa-hospital me-2"></i>Hospitals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="bloodbank.php">
                            <i class="fas fa-tint me-2"></i>Blood Banks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="ml_dashboard.php">
                            <i class="fas fa-brain me-2"></i>ML Dashboard
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Admin Dashboard</h1>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Donors</h5>
                            <h2 class="mb-0">1,234</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Blood Units</h5>
                            <h2 class="mb-0">5,678</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <h5 class="card-title">Hospitals</h5>
                            <h2 class="mb-0">42</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Critical Stock</h5>
                            <h2 class="mb-0">8</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">New donor registered</h6>
                                <small>5 min ago</small>
                            </div>
                            <p class="mb-1">John Doe (O+)</p>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Blood request approved</h6>
                                <small>1 hour ago</small>
                            </div>
                            <p class="mb-1">City Hospital - 2 units B+</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>