<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();
$page_title = "Manage Donors | " . APP_NAME;

// Detect schema differences for user active flag
$hasIsActive = false;
try {
    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($col) {
        // PDOStatement with rowCount compat
        $hasIsActive = ($col->rowCount() > 0);
    }
} catch (Exception $e) {
    $hasIsActive = false;
}

// Simple search filter
$search = trim($_GET['search'] ?? '');
$blood_type = $_GET['blood_type'] ?? '';

// Build basic query
// Build active flag depending on schema
$activeExpr = $hasIsActive ? "u.is_active" : "(CASE WHEN u.status='active' THEN 1 ELSE 0 END)";

$query = "SELECT d.*, u.email, $activeExpr as is_active,
          (SELECT COUNT(*) FROM blood_donations WHERE donor_id = d.id) as donation_count,
          (SELECT MAX(donation_date) FROM blood_donations WHERE donor_id = d.id) as last_donation_date
          FROM donors d 
          JOIN users u ON d.user_id = u.id
          WHERE 1=1";

$params = [];

// Apply search filter
if ($search) {
    $query .= " AND (d.first_name LIKE ? OR d.last_name LIKE ? OR d.phone LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Apply blood type filter
if ($blood_type) {
    $query .= " AND d.blood_type = ?";
    $params[] = $blood_type;
}

$query .= " ORDER BY d.last_name, d.first_name";

// Execute query
$stmt = $conn->prepare($query);
// Execute with PDO using positional parameters
$stmt->execute($params);
// Use the PDOStatement directly for fetching
$donors = $stmt;

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Donors</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_donor.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i> Add New Donor
                    </a>
                </div>
            </div>

<!-- Donation Reminder Modal -->
<div class="modal fade" id="sendReminderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Donation Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reminderForm" action="send_notifications.php" method="POST">
                <input type="hidden" name="donor_ids" id="reminderDonorIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reminderSubject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="reminderSubject" name="subject" value="Donation Reminder" required>
                    </div>
                    <div class="mb-3">
                        <label for="reminderMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="reminderMessage" name="message" rows="5" required>Hi, this is a friendly reminder that you may be eligible to donate blood again. Your contribution can save lives. Please consider scheduling your next donation. Thank you!</textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="reminderSendEmail" name="send_email" checked>
                        <label class="form-check-label" for="reminderSendEmail">
                            Send as email
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="reminderSendSms" name="send_sms">
                        <label class="form-check-label" for="reminderSendSms">
                            Send as SMS (if available)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Send Reminder
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>

            <!-- Simple Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" id="search" 
                                       value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, or phone">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="bloodType" class="form-label">Filter by Blood Type</label>
                            <select name="blood_type" class="form-select" id="bloodType" onchange="this.form.submit()">
                                <option value="">All Blood Types</option>
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?>
                                    <option value="<?= $type ?>" <?= ($blood_type === $type) ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <?php if ($search || $blood_type): ?>
                                <a href="donors.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk Actions Bar (Initially hidden) -->
            <div class="card mb-3" id="bulkActionsBar" style="display: none;">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center">
                        <span class="me-3" id="selectedCount">0</span> donors selected
                        
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-bell me-1"></i> Send Notification
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">Custom Message</a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sendReminderModal">Donation Reminder</a></li>
                            </ul>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="exportSelectedBtn">
                            <i class="fas fa-file-export me-1"></i> Export Selected
                        </button>
                        
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-sync-alt me-1"></i> Update Status
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item status-update" href="#" data-status="active">Mark as Active</a></li>
                                <li><a class="dropdown-item status-update" href="#" data-status="inactive">Mark as Inactive</a></li>
                            </ul>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-link text-danger ms-auto" id="clearSelection">
                            <i class="fas fa-times me-1"></i> Clear Selection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Donors Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Registered Donors</h5>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="selectAllDonors">
                                <label class="form-check-label" for="selectAllDonors">Select All</label>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#">Newest First</a></li>
                                    <li><a class="dropdown-item" href="#">Oldest First</a></li>
                                    <li><a class="dropdown-item" href="#">Name (A-Z)</a></li>
                                    <li><a class="dropdown-item" href="#">Name (Z-A)</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                    <th>Blood Type</th>
                                    <th>Contact</th>
                                    <th>Donations</th>
                                    <th>Last Donation</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($donor = $donors->fetch()): 
                                    $status_class = $donor['is_active'] ? 'success' : 'secondary';
                                    $status_text = $donor['is_active'] ? 'Active' : 'Inactive';
                                    $blood_type_class = str_ends_with($donor['blood_type'], '+') ? 'danger' : 'primary';
                                ?>
                                <tr data-donor-id="<?= $donor['id'] ?>">
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input donor-checkbox" type="checkbox" value="<?= $donor['id'] ?>" id="donor_<?= $donor['id'] ?>">
                                        </div>
                                    </td>
                                    <td>#<?= $donor['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2">
                                                <span class="avatar-initial rounded-circle bg-<?= $blood_type_class ?> text-white">
                                                    <?= substr($donor['first_name'], 0, 1) . substr($donor['last_name'], 0, 1) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($donor['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $blood_type_class ?>">
                                            <?= $donor['blood_type'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($donor['phone'])): ?>
                                            <div><i class="fas fa-phone-alt me-2"></i> <?= htmlspecialchars($donor['phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($donor['last_donation_date'])): ?>
                                            <small class="text-muted">Last donation: <?= date('Y-m-d', strtotime($donor['last_donation_date'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= (int)$donor['donation_count'] ?> donations</span>
                                    </td>
                                    <td>
                                        <?php if (!empty($donor['last_donation_date'])): ?>
                                            <?= date('M j, Y', strtotime($donor['last_donation_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">No donations yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary view-donor" 
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top"
                                                    data-bs-title="View Details"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#donorDetailsModal"
                                                    data-donor-id="<?= $donor['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="edit_donor.php?id=<?= $donor['id'] ?>" 
                                               class="btn btn-sm btn-outline-warning" 
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="top"
                                               data-bs-title="Edit Donor">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger delete-donor" 
                                                    data-id="<?= $donor['id'] ?>" 
                                                    data-name="<?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?>"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top"
                                                    data-bs-title="Delete Donor"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteDonorModal">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Simple Pagination -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item">
                                <a class="page-link" href="#" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Donor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="donorDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading donor details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="viewFullProfile" class="btn btn-outline-primary me-auto">
                    <i class="fas fa-user me-1"></i> View Full Profile
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="scheduleAppointment" class="btn btn-primary">
                    <i class="fas fa-calendar-plus me-1"></i> Schedule Appointment
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteDonorModal" tabindex="-1" aria-labelledby="deleteDonorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteDonorModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete donor <strong id="donorName"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <div class="form-check me-auto">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">I understand this action cannot be undone.</label>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteDonorForm" method="POST" action="delete_donor.php" class="d-inline">
                    <input type="hidden" name="donor_id" id="deleteDonorId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Handle donor details modal
$(document).on('click', '.view-donor', function() {
    var donorId = $(this).data('donor-id');
    var modal = $('#donorDetailsModal');
    
    // Update modal title and buttons
    $('#viewFullProfile').attr('href', 'view_donor.php?id=' + donorId);
    $('#scheduleAppointment').attr('href', 'add_appointment.php?donor_id=' + donorId);
    // Set loading state content
    $('#donorDetailsContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    // Show the modal
    modal.modal('show');
    
    // Load donor details via AJAX (correct endpoint path)
    $.get('get_donor_details.php', { id: donorId }, function(data) {
        $('#donorDetailsContent').html(data);
    }).fail(function() {
        $('#donorDetailsContent').html('<div class="alert alert-danger">Error loading donor details. Please try again.</div>');
    });
});

// Handle delete confirmation
$(document).on('click', '.delete-donor', function() {
    var donorId = $(this).data('id');
    var donorName = $(this).data('name');
    
    $('#donorName').text(donorName);
    $('#deleteDonorId').val(donorId);
});

// Initialize tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// When the Donation Reminder modal opens, prefill selected donor IDs
$('#sendReminderModal').on('show.bs.modal', function () {
    var selectedDonorIds = $('#selectedDonorIds').val();
    $('#reminderDonorIds').val(selectedDonorIds);
});
</script>

<!-- Send Notification Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="notificationForm" action="send_notifications.php" method="POST">
                <input type="hidden" name="donor_ids" id="selectedDonorIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notificationSubject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="notificationSubject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="notificationMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="notificationMessage" name="message" rows="5" required></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendEmail" name="send_email" checked>
                        <label class="form-check-label" for="sendEmail">
                            Send as email
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendSms" name="send_sms">
                        <label class="form-check-label" for="sendSms">
                            Send as SMS (if available)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Send Notifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Confirmation Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Donor Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="statusUpdateForm" action="update_donor_status.php" method="POST">
                <input type="hidden" name="donor_ids" id="statusUpdateDonorIds">
                <input type="hidden" name="status" id="statusUpdateValue">
                <div class="modal-body">
                    <p>Are you sure you want to update the status of <span id="selectedDonorsCount">0</span> donors to <strong><span id="newStatus">Active</span></strong>?</p>
                    <div class="form-group">
                        <label for="statusNotes">Notes (Optional)</label>
                        <textarea class="form-control" id="statusNotes" name="notes" rows="2" placeholder="Add any notes about this status change"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Bulk selection functionality
    const checkboxes = document.querySelectorAll('.donor-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllDonors');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const selectedDonorIds = document.getElementById('selectedDonorIds');
    const statusUpdateDonorIds = document.getElementById('statusUpdateDonorIds');
    const selectedDonorsCount = document.getElementById('selectedDonorsCount');
    const newStatus = document.getElementById('newStatus');
    const statusUpdateValue = document.getElementById('statusUpdateValue');
    
    // Toggle bulk actions bar based on selection
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.donor-checkbox:checked');
        const count = checkedBoxes.length;
        
        if (count > 0) {
            bulkActionsBar.style.display = 'block';
            selectedCount.textContent = count;
            selectedDonorsCount.textContent = count;
            
            // Update hidden fields with selected donor IDs
            const selectedIds = Array.from(checkedBoxes).map(checkbox => checkbox.value);
            selectedDonorIds.value = selectedIds.join(',');
            statusUpdateDonorIds.value = selectedIds.join(',');
        } else {
            bulkActionsBar.style.display = 'none';
        }
        
        // Update select all checkbox state
        selectAllCheckbox.checked = (count > 0 && count === checkboxes.length);
        selectAllCheckbox.indeterminate = (count > 0 && count < checkboxes.length);
    }
    
    // Handle individual checkbox selection
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    // Select all checkboxes
    selectAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });
    
    // Clear selection
    document.getElementById('clearSelection').addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
        updateBulkActions();
    });
    
    // Status update buttons
    document.querySelectorAll('.status-update').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const status = this.getAttribute('data-status');
            statusUpdateValue.value = status;
            newStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            
            const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
            modal.show();
        });
    });
    
    // View donor details
    document.querySelectorAll('.view-donor').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const donorId = this.getAttribute('data-donor-id');
            
            // In a real implementation, you would fetch donor details via AJAX here
            // For now, we'll just show the modal with the donor ID
            const modalTitle = document.querySelector('#donorDetailsModal .modal-title');
            modalTitle.textContent = `Donor Details #${donorId}`;
            
            // Show loading state
            const modalBody = document.querySelector('#donorDetailsModal .modal-body');
            modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('donorDetailsModal'));
            modal.show();
            
            // Simulate loading data (replace with actual AJAX call)
            setTimeout(() => {
                // This is a placeholder - replace with actual data from your server
                modalBody.innerHTML = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Personal Information</h6>
                            <p><strong>Name:</strong> Loading...<br>
                            <strong>Email:</strong> loading@example.com<br>
                            <strong>Phone:</strong> +1 234 567 890</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Donation History</h6>
                            <p><strong>Last Donation:</strong> Not available<br>
                            <strong>Total Donations:</strong> 0</p>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> This is a placeholder. In a real implementation, 
                        this would show detailed donor information loaded via AJAX.
                    </div>`;
            }, 1000);
        });
    });
    
    // Delete donor confirmation
    document.querySelectorAll('.delete-donor').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const donorId = this.getAttribute('data-id');
            const donorName = this.getAttribute('data-name');
            
            // Update the modal with donor info
            document.getElementById('deleteDonorId').value = donorId;
            document.getElementById('deleteDonorName').textContent = donorName;
            
            // Reset the confirmation checkbox
            const confirmCheckbox = document.getElementById('confirmDelete');
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }
            
            // Show the delete confirmation modal
            const modal = new bootstrap.Modal(document.getElementById('deleteDonorModal'));
            modal.show();
        });
    });
    
    // Handle delete form submission
    const deleteForm = document.getElementById('deleteDonorForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            const confirmCheckbox = document.getElementById('confirmDelete');
            if (!confirmCheckbox || !confirmCheckbox.checked) {
                e.preventDefault();
                alert('Please confirm deletion by checking the box.');
                return false;
            }
            return true;
        });
    }
    
    // Export selected donors
    document.getElementById('exportSelectedBtn').addEventListener('click', function() {
        const selectedIds = Array.from(document.querySelectorAll('.donor-checkbox:checked'))
            .map(checkbox => checkbox.value);
            
        if (selectedIds.length > 0) {
            // Create a form and submit it to trigger the export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_donors.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'donor_ids';
            input.value = selectedIds.join(',');
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        } else {
            alert('Please select at least one donor to export.');
        }
    });
});
</script>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteDonorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteDonorForm" action="delete_donor.php" method="POST">
                <input type="hidden" name="donor_id" id="deleteDonorId">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong><span id="donorName"></span></strong>? This action cannot be undone.</p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirm_delete" id="confirmDelete" required>
                        <label class="form-check-label" for="confirmDelete">
                            Yes, I want to delete this donor
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Delete Donor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle delete donor modal
$('#deleteDonorModal').on('show.bs.modal', function (event) {
    const button = $(event.relatedTarget);
    const donorId = button.data('id');
    const donorName = button.data('name');
    
    const modal = $(this);
    modal.find('#deleteDonorId').val(donorId);
    modal.find('#donorName').text(donorName);
    modal.find('#confirmDelete').prop('checked', false);
});

// Handle form submission with confirmation
$('#deleteDonorForm').on('submit', function(e) {
    if (!confirm('Are you absolutely sure you want to delete this donor? This action cannot be undone.')) {
        e.preventDefault();
        return false;
    }
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>
