<!-- Search Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Find Matching Donors</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="donorMatchForm">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Blood Type</label>
                    <select class="form-select" name="blood_type" required>
                        <option value="">Select Blood Type</option>
                        <option value="A+" <?= $blood_type === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= $blood_type === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= $blood_type === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= $blood_type === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= $blood_type === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= $blood_type === 'AB-' ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= $blood_type === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= $blood_type === 'O-' ? 'selected' : '' ?>>O-</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Location (City)</label>
                    <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($location) ?>" required>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Find Donors
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results -->
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Matching Donors (<?= count($results) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($results)): ?>
                <div class="alert alert-warning mb-0">
                    No matching donors found. Try different search criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Blood Type</th>
                                <th>Last Donation</th>
                                <th>Total Donations</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $donor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></td>
                                    <td><span class="badge bg-danger"><?= $donor['blood_type'] ?></span></td>
                                    <td><?= $donor['last_donation'] ?? 'Never' ?></td>
                                    <td><?= $donor['donation_count'] ?? 0 ?></td>
                                    <td>
                                        <?= htmlspecialchars($donor['email'] ?? '') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($donor['phone'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" 
                                                onclick="viewDonor(<?= $donor['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!empty($donor['email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($donor['email']) ?>" 
                                               class="btn btn-sm btn-outline-success me-1">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($donor['phone'])): ?>
                                            <a href="tel:<?= htmlspecialchars($donor['phone']) ?>" 
                                               class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function viewDonor(id) {
    window.location.href = 'donor_details.php?id=' + id;
}
</script>
