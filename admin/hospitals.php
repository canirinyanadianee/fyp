<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();
$page_title = "Manage Hospitals | " . APP_NAME;

// Get all hospitals with their request counts
// Detect if users.is_active exists to avoid SQL errors on differing schemas
$hasIsActive = false;
try {
    $colStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($colStmt) {
        $hasIsActive = ($colStmt->rowCount() > 0);
    }
} catch (Exception $e) {
    $hasIsActive = false;
}

$activeSelect = $hasIsActive ? "u.is_active as is_active" : "1 as is_active";

$query = "SELECT h.*, 
          CONCAT_WS(', ', NULLIF(TRIM(h.city), ''), NULLIF(TRIM(h.state), '')) AS location,
          u.email, $activeSelect, 
          (SELECT COUNT(*) FROM blood_requests WHERE hospital_id = h.id) as request_count
          FROM hospitals h 
          JOIN users u ON h.user_id = u.id
          ORDER BY h.name";
$hospitals = $conn->query($query);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Hospitals</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_hospital.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add New Hospital
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?= $_SESSION['info']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['info']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Registered Hospitals</h5>
                        <div>
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search hospitals...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Hospital Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Location</th>
                                    <th>Requests</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($hospital = $hospitals->fetch()): 
                                    $status_class = $hospital['is_active'] ? 'success' : 'secondary';
                                    $status_text = $hospital['is_active'] ? 'Active' : 'Inactive';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <div class="avatar-title bg-light text-primary rounded">
                                                        <i class="fas fa-hospital"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($hospital['name']) ?></h6>
                                                    <small class="text-muted">ID: <?= $hospital['id'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($hospital['email']) ?></td>
                                        <td><?= htmlspecialchars($hospital['phone'] ?? 'N/A') ?></td>
                                        <td><?= ($hospital['location'] ?? '') !== '' ? htmlspecialchars($hospital['location']) : 'N/A' ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= $hospital['request_count'] ?> requests
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?>">
                                                <?= $status_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#hospitalDetailsModal"
                                                        data-id="<?= $hospital['id'] ?>"
                                                        data-name="<?= htmlspecialchars($hospital['name']) ?>"
                                                        data-email="<?= htmlspecialchars($hospital['email']) ?>"
                                                        data-phone="<?= htmlspecialchars($hospital['phone']) ?>"
                                                        data-address="<?= htmlspecialchars($hospital['address'] ?? '') ?>"
                                                        data-location="<?= htmlspecialchars($hospital['location'] ?? '') ?>"
                                                        data-status="<?= $hospital['is_active'] ? 'Active' : 'Inactive' ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="edit_hospital.php?id=<?= $hospital['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete(<?= $hospital['id'] ?>, '<?= htmlspecialchars(addslashes($hospital['name'])) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Hospital Details Modal -->
<div class="modal fade" id="hospitalDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hospital Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hospital Name:</label>
                            <p id="modalHospitalName" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email:</label>
                            <p id="modalHospitalEmail" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone:</label>
                            <p id="modalHospitalPhone" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address:</label>
                            <p id="modalHospitalAddress" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Location:</label>
                            <p id="modalHospitalLocation" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status:</label>
                            <p id="modalHospitalStatus" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="editHospitalBtn" class="btn btn-primary">Edit Hospital</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteHospitalName"></strong>? This action cannot be undone.</p>
                <p class="text-danger">Note: This will also deactivate the associated user account.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" action="delete_hospital.php" method="POST" class="d-inline">
                    <input type="hidden" name="hospital_id" id="deleteHospitalId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Search functionality
$(document).ready(function() {
    $("#searchInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});

// Hospital Details Modal
var hospitalDetailsModal = document.getElementById('hospitalDetailsModal');
hospitalDetailsModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    
    // Extract info from data-bs-* attributes
    var hospitalId = button.getAttribute('data-id');
    var hospitalName = button.getAttribute('data-name');
    var hospitalEmail = button.getAttribute('data-email');
    var hospitalPhone = button.getAttribute('data-phone');
    var hospitalAddress = button.getAttribute('data-address');
    var hospitalLocation = button.getAttribute('data-location');
    var hospitalStatus = button.getAttribute('data-status');
    
    // Update the modal's content
    document.getElementById('modalHospitalName').textContent = hospitalName;
    document.getElementById('modalHospitalEmail').textContent = hospitalEmail;
    document.getElementById('modalHospitalPhone').textContent = hospitalPhone || 'N/A';
    document.getElementById('modalHospitalAddress').textContent = hospitalAddress || 'N/A';
    document.getElementById('modalHospitalLocation').textContent = hospitalLocation || 'N/A';
    document.getElementById('modalHospitalStatus').innerHTML = 
        `<span class="badge bg-${hospitalStatus === 'Active' ? 'success' : 'secondary'}">${hospitalStatus}</span>`;
    
    // Update edit button link
    document.getElementById('editHospitalBtn').href = `edit_hospital.php?id=${hospitalId}`;
});

// Delete Confirmation
function confirmDelete(id, name) {
    document.getElementById('deleteHospitalId').value = id;
    document.getElementById('deleteHospitalName').textContent = name;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>
