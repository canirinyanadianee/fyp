<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();
$page_title = "Manage Blood Banks | " . APP_NAME;

// Get all blood banks with their inventory summary
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

$query = "SELECT b.*, 
          CONCAT_WS(', ', NULLIF(TRIM(b.city), ''), NULLIF(TRIM(b.state), '')) AS location,
          u.email, $activeSelect, 
          (SELECT COUNT(*) FROM blood_inventory WHERE blood_bank_id = b.id) as inventory_count,
          (SELECT COUNT(DISTINCT blood_type) FROM blood_inventory WHERE blood_bank_id = b.id) as blood_types_count
          FROM blood_banks b 
          JOIN users u ON b.user_id = u.id
          ORDER BY b.name";
$bloodBanks = $conn->query($query);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Blood Banks</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_bloodbank.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add New Blood Bank
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

            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Registered Blood Banks</h5>
                        <div>
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search blood banks...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Blood Bank Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Location</th>
                                    <th>Inventory</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($bank = $bloodBanks->fetch()): 
                                    $status_class = !empty($bank['is_active']) ? 'success' : 'secondary';
                                    $status_text = !empty($bank['is_active']) ? 'Active' : 'Inactive';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <div class="avatar-title bg-light text-danger rounded">
                                                        <i class="fas fa-tint"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($bank['name']) ?></h6>
                                                    <small class="text-muted">ID: <?= $bank['id'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($bank['email'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($bank['phone'] ?? 'N/A') ?></td>
                                        <td><?= ($bank['location'] ?? '') !== '' ? htmlspecialchars($bank['location']) : 'N/A' ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= $bank['inventory_count'] ?> units
                                            </span>
                                            <span class="badge bg-info">
                                                <?= $bank['blood_types_count'] ?> blood types
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
                                                        data-bs-toggle="modal" data-bs-target="#bloodBankDetailsModal"
                                                        data-id="<?= $bank['id'] ?>"
                                                        data-name="<?= htmlspecialchars($bank['name']) ?>"
                                                        data-email="<?= htmlspecialchars($bank['email']) ?>"
                                                        data-phone="<?= htmlspecialchars($bank['phone']) ?>"
                                                        data-address="<?= htmlspecialchars($bank['address'] ?? '') ?>"
                                                        data-location="<?= htmlspecialchars($bank['location'] ?? '') ?>"
                                                        data-inventory="<?= (int)($bank['inventory_count'] ?? 0) ?>"
                                                        data-bloodtypes="<?= (int)($bank['blood_types_count'] ?? 0) ?>"
                                                        data-status="<?= !empty($bank['is_active']) ? 'Active' : 'Inactive' ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="edit_bloodbank.php?id=<?= $bank['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="inventory.php?bloodbank_id=<?= $bank['id'] ?>" class="btn btn-sm btn-outline-info" title="View Inventory">
                                                    <i class="fas fa-boxes"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete(<?= $bank['id'] ?>, '<?= htmlspecialchars(addslashes($bank['name'])) ?>')">
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

<!-- Blood Bank Details Modal -->
<div class="modal fade" id="bloodBankDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Blood Bank Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Blood Bank Name:</label>
                            <p id="modalBankName" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email:</label>
                            <p id="modalBankEmail" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone:</label>
                            <p id="modalBankPhone" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address:</label>
                            <p id="modalBankAddress" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Location:</label>
                            <p id="modalBankLocation" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Inventory:</label>
                            <p id="modalBankInventory" class="form-control-plaintext"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status:</label>
                            <p id="modalBankStatus" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="editBankBtn" class="btn btn-primary">Edit Blood Bank</a>
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
                <p>Are you sure you want to delete <strong id="deleteBankName"></strong>? This action cannot be undone.</p>
                <p class="text-danger">Note: This will also deactivate the associated user account and remove all inventory records.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" action="delete_bloodbank.php" method="POST" class="d-inline">
                    <input type="hidden" name="bloodbank_id" id="deleteBankId">
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

// Blood Bank Details Modal
var bloodBankDetailsModal = document.getElementById('bloodBankDetailsModal');
bloodBankDetailsModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    
    // Extract info from data-bs-* attributes
    var bankId = button.getAttribute('data-id');
    var bankName = button.getAttribute('data-name');
    var bankEmail = button.getAttribute('data-email');
    var bankPhone = button.getAttribute('data-phone');
    var bankAddress = button.getAttribute('data-address');
    var bankLocation = button.getAttribute('data-location');
    var inventoryCount = button.getAttribute('data-inventory');
    var bloodTypesCount = button.getAttribute('data-bloodtypes');
    var bankStatus = button.getAttribute('data-status');
    
    // Update the modal's content
    document.getElementById('modalBankName').textContent = bankName;
    document.getElementById('modalBankEmail').textContent = bankEmail;
    document.getElementById('modalBankPhone').textContent = bankPhone || 'N/A';
    document.getElementById('modalBankAddress').textContent = bankAddress || 'N/A';
    document.getElementById('modalBankLocation').textContent = bankLocation || 'N/A';
    document.getElementById('modalBankInventory').innerHTML = 
        `<span class="badge bg-primary">${inventoryCount} units</span> ` +
        `<span class="badge bg-info">${bloodTypesCount} blood types</span>`;
    document.getElementById('modalBankStatus').innerHTML = 
        `<span class="badge bg-${bankStatus === 'Active' ? 'success' : 'secondary'}">${bankStatus}</span>`;
    
    // Update edit button link
    document.getElementById('editBankBtn').href = `edit_bloodbank.php?id=${bankId}`;
});

// Delete Confirmation
function confirmDelete(id, name) {
    document.getElementById('deleteBankId').value = id;
    document.getElementById('deleteBankName').textContent = name;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>
