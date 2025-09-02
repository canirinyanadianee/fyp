<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
require_once '../includes/donor_header.php';
?>

<div class="container py-5">
  <!-- Header & Search -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
      <h2 class="text-danger fw-bold">Available Blood Banks</h2>
      <p class="text-muted small mb-0">Find a nearby blood bank and book your donation appointment easily.</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <div class="input-group" style="min-width: 250px;">
        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
        <input id="bankSearch" type="search" class="form-control form-control-sm border-start-0" placeholder="Search by name, city or state">
      </div>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>
  </div>

  <!-- Blood Bank Cards -->
  <div class="row g-4" id="banksList">
    <?php
    $banks = $conn->query("SELECT * FROM blood_banks ORDER BY name");
    if ($banks && $banks->num_rows > 0):
        while($row = $banks->fetch_assoc()): ?>
        <div class="col-lg-4 col-md-6 bank-card">
          <div class="card h-100 shadow-sm border-0 rounded-4">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title text-danger fw-semibold"><?php echo htmlspecialchars($row['name']); ?></h5>
              <p class="mb-1"><i class="fas fa-city me-1 text-muted"></i><strong>City:</strong> <?php echo htmlspecialchars($row['city']); ?></p>
              <p class="mb-1"><i class="fas fa-map-marker-alt me-1 text-muted"></i><strong>State:</strong> <?php echo htmlspecialchars($row['state']); ?></p>
              <p class="mb-2 text-muted small"><i class="fas fa-envelope me-1"></i><strong>Contact:</strong> <?php echo htmlspecialchars($row['contact_email'] ?? $row['phone'] ?? '—'); ?></p>
              <div class="mt-auto d-flex gap-2">
                <a href="view_bloodbank.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary flex-fill">View</a>
                <a href="book_appointment.php?blood_bank_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary flex-fill">Book Appointment</a>
              </div>
            </div>
          </div>
        </div>
    <?php endwhile; else: ?>
      <div class="col-12 text-center">
        <div class="alert alert-warning">No blood banks found.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
  body { background: #dad9dbff; }
  .card-title { font-size: 2.1rem; }
  .bank-card { transition: transform .2s, box-shadow .2s; cursor: pointer; background: #dad9dbff;  }
  .bank-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px #cac9c91a; }
  .input-group .form-control { border-radius: 50px; }
  .input-group .input-group-text { border-radius: 50px 0 0 50px; }
  @media (max-width: 576px){ .d-flex.flex-md-row { flex-direction: column !important; } }
</style>

<script>
  document.getElementById('bankSearch').addEventListener('input', function(){
    let q = this.value.trim().toLowerCase();
    document.querySelectorAll('#banksList .bank-card').forEach(function(card){
      let text = card.innerText.toLowerCase();
      card.style.display = q === '' || text.includes(q) ? '' : 'none';
    });
  });
</script>

<?php require_once '../includes/donor_footer.php'; ?>
