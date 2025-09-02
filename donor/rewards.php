<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/donor_header.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
$donor_id = $donor ? $donor['id'] : 0;

$total_donations = $conn->query("SELECT COUNT(*) as total FROM blood_donations WHERE donor_id = $donor_id")->fetch_assoc()['total'] ?? 0;
$total_ml = $conn->query("SELECT SUM(quantity_ml) as total_ml FROM blood_donations WHERE donor_id = $donor_id")->fetch_assoc()['total_ml'] ?? 0;
$lives_saved = ceil(($total_ml ?: 0) / 450);
$streak = $conn->query("SELECT COUNT(*) as streak FROM blood_donations WHERE donor_id = $donor_id AND donation_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)")->fetch_assoc()['streak'] ?? 0;

// simple badges
$badges = [];
if ($total_donations >= 1) $badges[] = ['name' => 'First Donation', 'icon' => 'fa-medal', 'color' => 'text-success'];
if ($total_donations >= 5) $badges[] = ['name' => '5 Donations', 'icon' => 'fa-award', 'color' => 'text-primary'];
if ($total_donations >= 10) $badges[] = ['name' => '10 Donations', 'icon' => 'fa-trophy', 'color' => 'text-warning'];
if ($streak >= 3) $badges[] = ['name' => 'Consistent Donor', 'icon' => 'fa-heart', 'color' => 'text-danger'];
?>

<style>
    body {
        background: #5f676eff;
    }
    .reward-card {
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.2s;
        width: 60%;
        font: size 3px;
         padding: 0.3rem;
    }
    .reward-card:hover {
        transform: translateY(-3px);
    }
    .stat-box {
        border-radius: 12px;
        padding: 1.5rem;
        color: #fff;
        font-weight: bold;
        font-size: 1.4rem;
        width: 20%;
       
        
    }
    .bg-gradient-red { background: linear-gradient(135deg, #1e0808ff, #3c0810ff); }
    .bg-gradient-blue { background: linear-gradient(135deg, #4dabf7, #1e90ff); }
    .bg-gradient-green { background: linear-gradient(135deg, #51cf66, #2f9e44); }
    .bg-gradient-orange { background: linear-gradient(135deg, #ffa94d, #ff922b); }
    .badge-box {
        min-width: 80px;
        padding: 12px;
    }
    .progress {
        height: 20px;
        border-radius: 12px;
    }
    .progress-bar {
        font-weight: bold;
    }
</style>
<center>
<div class="container my-4">
    <div class="reward-card p-4">
        <h4 class="mb-3"><i class="fas fa-award text-warning me-2"></i> Rewards & Badges</h4>
        <p class="text-muted">Your achievements for donating blood and helping save lives.</p>

        <!-- Stats Section -->
        <div class="row text-center mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-box bg-gradient-red">
                    <?php echo $total_donations; ?><br>
                    <small>Total Donations</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box bg-gradient-blue">
                    <?php echo number_format($total_ml ?: 0); ?> ml<br>
                    <small>Blood Donated</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box bg-gradient-green">
                    <?php echo $lives_saved; ?><br>
                    <small>Lives Saved</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box bg-gradient-orange">
                    <?php echo $streak; ?><br>
                    <small>Donation Streak</small>
                </div>
            </div>
        </div>

        <!-- Badges Section -->
        <h5 class="mb-2">🏅 Earned Badges</h5>
        <div class="d-flex flex-wrap mb-4">
            <?php if (empty($badges)): ?>
                <div class="text-muted">No badges yet. Donate to start earning rewards.</div>
            <?php else: ?>
                <?php foreach ($badges as $b): ?>
                    <div class="badge-box text-center me-3 mb-3">
                        <div class="fs-1 <?php echo $b['color']; ?>"><i class="fas <?php echo $b['icon']; ?>"></i></div>
                        <div><?php echo htmlspecialchars($b['name']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Progress Section -->
        <h5 class="mb-2">📈 Progress</h5>
        <p>Donations until next reward (5 donations): <strong><?php echo max(0, 5 - $total_donations); ?></strong></p>
        <div class="progress mb-4">
            <div class="progress-bar bg-success" role="progressbar"
                 style="width: <?php echo min(100, ($total_donations / 5) * 100); ?>%;"
                 aria-valuenow="<?php echo $total_donations; ?>" aria-valuemin="0" aria-valuemax="5">
                 <?php echo $total_donations; ?>/5
            </div>
        </div>

        <a href="donation_history.php" class="btn btn-primary">
            <i class="fas fa-history me-2"></i> View Donation History
        </a>
    </div>
</div>
</center>
<?php require_once '../includes/donor_footer.php'; ?>
