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
if (!$donor) {
    echo '<div class="alert alert-warning">No donor profile found. Please complete your <a href="profile.php">profile</a>.</div>';
    require_once '../includes/donor_footer.php';
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $health_details = sanitize_input($_POST['health_details'] ?? '');
    if (strlen($health_details) > 2000) $errors[] = 'Health information text too long.';

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE donors SET health_conditions = ? WHERE id = ?");
        $stmt->bind_param('si', $health_details, $donor['id']);
        if ($stmt->execute()) {
            $success = 'Health information updated successfully.';
            $donor = $conn->query("SELECT * FROM donors WHERE id = " . (int)$donor['id'])->fetch_assoc();
        } else {
            $errors[] = 'Failed to update health information. Please try again.';
        }
        $stmt->close();
    }
}
?>

<div class="container py-5">
    <div class="card shadow-lg rounded-4 border-0">
        <div class="card-header bg-gradient-danger text-white py-3 px-4">
            <h4 class="mb-0 fw-bold"><i class="fas fa-notes-medical me-2"></i>Health Information</h4>
        </div>
        <div class="card-body p-4">
            <p class="text-muted">Keep your health information up to date so we can verify your eligibility and ensure safe donations.</p>

            <?php if ($success): ?>
                <div class="alert alert-success shadow-sm"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Health Details <span class="text-danger">*</span></label>
                    <textarea name="health_details" class="form-control rounded-3" rows="6" placeholder="e.g., allergies, medications, weight/height, blood pressure"><?php echo htmlspecialchars($donor['health_conditions'] ?? ''); ?></textarea>
                    <div class="form-text text-muted">Include relevant medical conditions, medications, allergies, weight/height, or blood pressure. This info helps determine your eligibility for donation.</div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-danger shadow-sm" type="submit"><i class="fas fa-save me-1"></i>Save Health Info</button>
                    <a href="eligibility_check.php" class="btn btn-outline-danger shadow-sm"><i class="fas fa-check-circle me-1"></i>Check Eligibility</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    body { background-color: #f8f9fa; }
    .card-header { background: linear-gradient(135deg, #dc3545, #39282aff); border-radius: .5rem .5rem 0 0; font-size:50px; align-items:center;}
    .form-control { box-shadow: inset 0 1px 3px rgba(0,0,0,.1); transition: box-shadow .3s ease; font-size:20px; }
    .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25); border-color: #dc3545; font-size:50px;}
    .btn-danger { background: linear-gradient(135deg, #dc3545, #e4606d); border: none; transition: transform .2s ease;font-size:30px; }
    .btn-danger:hover { transform: translateY(-2px); }
    .btn-outline-danger { border-color: #774c51ff; color: #dc3545; transition: all .2s ease; }
    .btn-outline-danger:hover { background: #dc3545; color: #fff; transform: translateY(-2px); }
</style>

<?php require_once '../includes/donor_footer.php'; ?>
