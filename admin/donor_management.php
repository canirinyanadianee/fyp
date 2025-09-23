<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Donor Management | " . APP_NAME;

// Database connection
require_once '../includes/db_connect.php';

// Get all donors with their user account status
$query = "SELECT d.*, u.email, u.is_active, u.created_at as account_created, 
          (SELECT COUNT(*) FROM appointments WHERE donor_id = d.id) as appointment_count,
          (SELECT COUNT(*) FROM blood_donations WHERE donor_id = d.id) as donation_count
          FROM donors d 
          JOIN users u ON d.user_id = u.id
          ORDER BY d.last_name, d.first_name";
$donors = $conn->query($query);

// Get appointments for the selected donor
$appointments = [];
$selected_donor_id = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;

if ($selected_donor_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.*, bb.name as blood_bank_name, bb.city as blood_bank_city
        FROM appointments a
        JOIN blood_banks bb ON a.blood_bank_id = bb.id
        WHERE a.donor_id = ?
        ORDER BY a.appointment_date DESC
    ");
    $stmt->bind_param('i', $selected_donor_id);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Donor Management</h1>
            </div>

            <div class="row">
                <!-- Donors List -->
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Donors List</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while ($donor = $donors->fetch_assoc()): ?>
                                    <a href="?donor_id=<?= $donor['id'] ?>" 
                                       class="list-group-item list-group-item-action <?= $selected_donor_id == $donor['id'] ? 'active' : '' ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h6>
                                            <small class="badge bg-<?= $donor['is_active'] ? 'success' : 'secondary' ?>" style="font-size: 0.7rem;">
                                                <?= $donor['is_active'] ? 'Active' : 'Inactive' ?>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-<?= str_ends_with($donor['blood_type'], '+') ? 'danger' : 'info' ?>">
                                                <?= $donor['blood_type'] ?>
                                            </span>
                                            <small class="text-muted ms-2">
                                                <?= $donor['donation_count'] ?> donations | 
                                                <?= $donor['appointment_count'] ?> appointments
                                            </small>
                                        </p>
                                        <small>Last donation: <?= $donor['last_donation_date'] ? date('M j, Y', strtotime($donor['last_donation_date'])) : 'Never' ?></small>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Donor Details and Appointments -->
                <div class="col-md-8">
                    <?php if ($selected_donor_id > 0): 
                        $donor = $donors->data_seek(0); // Reset pointer
                        $donor = null;
                        while ($d = $donors->fetch_assoc()) {
                            if ($d['id'] == $selected_donor_id) {
                                $donor = $d;
                                break;
                            }
                        }
                        if ($donor): ?>
                            <div class="card shadow-sm mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Donor Details</h6>
                                    <div>
                                        <a href="edit_donor.php?id=<?= $donor['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                            <i class="fas fa-user-slash"></i> <?= $donor['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Name:</strong> <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></p>
                                            <p><strong>Email:</strong> <?= htmlspecialchars($donor['email']) ?></p>
                                            <p><strong>Phone:</strong> <?= htmlspecialchars($donor['phone'] ?? 'N/A') ?></p>
                                            <p><strong>Date of Birth:</strong> <?= date('M j, Y', strtotime($donor['dob'])) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Blood Type:</strong> 
                                                <span class="badge bg-<?= str_ends_with($donor['blood_type'], '+') ? 'danger' : 'info' ?>">
                                                    <?= $donor['blood_type'] ?>
                                                </span>
                                            </p>
                                            <p><strong>Gender:</strong> <?= ucfirst($donor['gender']) ?></p>
                                            <p><strong>Total Donations:</strong> <?= $donor['donation_count'] ?></p>
                                            <p><strong>Last Donation:</strong> 
                                                <?= $donor['last_donation_date'] ? date('M j, Y', strtotime($donor['last_donation_date'])) : 'Never' ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (!empty($donor['address'])): ?>
                                        <div class="mt-3">
                                            <h6>Address</h6>
                                            <p class="mb-1"><?= nl2br(htmlspecialchars($donor['address'])) ?></p>
                                            <p class="mb-1">
                                                <?= htmlspecialchars($donor['city'] . ', ' . $donor['state'] . ' ' . $donor['postal_code']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($donor['health_conditions'])): ?>
                                        <div class="mt-3">
                                            <h6>Health Notes</h6>
                                            <p><?= nl2br(htmlspecialchars($donor['health_conditions'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Appointments Section -->
                            <div class="card shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Appointment History</h6>
                                    <a href="create_appointment.php?donor_id=<?= $donor['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> New Appointment
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($appointments)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Blood Bank</th>
                                                        <th>Location</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($appointments as $appt): ?>
                                                        <tr>
                                                            <td><?= date('M j, Y g:i A', strtotime($appt['appointment_date'])) ?></td>
                                                            <td><?= htmlspecialchars($appt['blood_bank_name']) ?></td>
                                                            <td><?= htmlspecialchars($appt['blood_bank_city']) ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= 
                                                                    $appt['status'] === 'confirmed' ? 'success' : 
                                                                    ($appt['status'] === 'cancelled' ? 'danger' : 'warning')
                                                                ?>">
                                                                    <?= ucfirst($appt['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group btn-group-sm">
                                                                    <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-outline-primary">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <a href="edit_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <button class="btn btn-outline-danger" 
                                                                            onclick="confirmCancel(<?= $appt['id'] ?>)"
                                                                            <?= $appt['status'] === 'cancelled' ? 'disabled' : '' ?>>
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center p-4">
                                            <p class="text-muted">No appointments found for this donor.</p>
                                            <a href="create_appointment.php?donor_id=<?= $donor['id'] ?>" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Schedule an Appointment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                Donor not found. Please select a donor from the list.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <i class="fas fa-user-friends fa-4x text-muted mb-3"></i>
                            <h5>Select a donor to view details</h5>
                            <p class="text-muted">Choose a donor from the list to view their profile and appointment history.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Deactivate Confirmation Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $donor['is_active'] ? 'Deactivate' : 'Activate' ?> Donor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to <?= $donor['is_active'] ? 'deactivate' : 'activate' ?> this donor account?
                <?= !$donor['is_active'] ? 'The donor will be able to log in again.' : 'The donor will not be able to log in.' ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="toggle_donor_status.php" method="POST" class="d-inline">
                    <input type="hidden" name="donor_id" value="<?= $donor['id'] ?>">
                    <button type="submit" class="btn btn-<?= $donor['is_active'] ? 'danger' : 'success' ?>">
                        <?= $donor['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Appointment Confirmation Script -->
<script>
function confirmCancel(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        window.location.href = 'cancel_appointment.php?id=' + appointmentId;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
