<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Reports & Analytics";
include '../includes/header.php';
require_once '../includes/db.php';

// Example: Fetch daily, weekly, monthly blood usage
$daily = $conn->query("SELECT DATE(donation_date) as day, SUM(quantity_ml) as total FROM blood_donations GROUP BY day ORDER BY day DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$monthly = $conn->query("SELECT DATE_FORMAT(donation_date, '%Y-%m') as month, SUM(quantity_ml) as total FROM blood_donations GROUP BY month ORDER BY month DESC LIMIT 12")->fetch_all(MYSQLI_ASSOC);

?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Reports & Analytics</h1>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Blood Usage - Daily</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Blood Used (ml)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily as $row): ?>
                            <tr>
                                <td><?php echo $row['day']; ?></td>
                                <td><?php echo $row['total']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Blood Usage - Monthly</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Blood Used (ml)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly as $row): ?>
                            <tr>
                                <td><?php echo $row['month']; ?></td>
                                <td><?php echo $row['total']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>
