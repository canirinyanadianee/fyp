<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = 'Blood Banks';
include '../includes/header.php';

// Fetch all blood banks
$bloodbanks = $conn->query("SELECT * FROM blood_banks ORDER BY name ASC");
?>
<div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-tint text-danger me-2"></i>Blood Banks</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>License #</th>
                <th>City</th>
                <th>State</th>
                <th>Contact Email</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($bank = $bloodbanks->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($bank['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($bank['license_number'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($bank['city'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($bank['state'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($bank['contact_email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($bank['phone'] ?? ''); ?></td>
                <td>
                    <a href="edit_bloodbank.php?id=<?php echo $bank['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include '../includes/admin_footer.php'; ?>
