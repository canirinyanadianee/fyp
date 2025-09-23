<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn PDO

// Basic system summary
$phpVersion = PHP_VERSION;
$dbVersion = '';
try { $dbVersion = $conn->query('SELECT VERSION() v')->fetch()['v'] ?? ''; } catch (Exception $e) {}

include '../includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-server me-2"></i>System Performance & Info</h3>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">PHP Version</div>
          <div class="fs-5 fw-semibold"><?php echo htmlspecialchars($phpVersion); ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Database Version</div>
          <div class="fs-5 fw-semibold"><?php echo htmlspecialchars($dbVersion ?: 'N/A'); ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-light fw-bold"><i class="fas fa-info-circle me-2"></i>Notes</div>
    <div class="card-body">
      <ul class="mb-0">
        <li>For detailed performance metrics, integrate server monitoring or database slow query logs.</li>
        <li>Consider enabling query logging in development to analyze bottlenecks.</li>
      </ul>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
