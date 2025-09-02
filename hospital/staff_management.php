<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$success = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_staff'])) {
        $staff_name = $_POST['staff_name'] ?? '';
        $staff_email = $_POST['staff_email'] ?? '';
        $staff_phone = $_POST['staff_phone'] ?? '';
        $staff_role = $_POST['staff_role'] ?? '';
        $department = $_POST['department'] ?? '';
        $hire_date = $_POST['hire_date'] ?? '';
        
        if (empty($staff_name) || empty($staff_email) || empty($staff_role)) {
            $error = 'Name, email, and role are required';
        } else {
            // Add staff member
            $sql = "INSERT INTO hospital_staff (hospital_id, name, email, phone, role, department, hire_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("issssss", $user_id, $staff_name, $staff_email, $staff_phone, $staff_role, $department, $hire_date);
                if ($stmt->execute()) {
                    $success = 'Staff member added successfully';
                } else {
                    $error = 'Error adding staff member: ' . $stmt->error;
                }
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        }
    } elseif (isset($_POST['update_status'])) {
        $staff_id = $_POST['staff_id'];
        $new_status = $_POST['status'];
        
        $update_sql = "UPDATE hospital_staff SET status = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param("sii", $new_status, $staff_id, $user_id);
            if ($update_stmt->execute()) {
                $success = 'Staff status updated successfully';
            } else {
                $error = 'Error updating status: ' . $update_stmt->error;
            }
        }
    }
}

// Get all staff members for this hospital
$staff_query = "SELECT * FROM hospital_staff WHERE hospital_id = ? ORDER BY created_at DESC";
$staff_stmt = $conn->prepare($staff_query);
$staff_members = [];
if ($staff_stmt) {
    $staff_stmt->bind_param("i", $user_id);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    while ($row = $staff_result->fetch_assoc()) {
        $staff_members[] = $row;
    }
}

// Get staff statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_staff,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_staff,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_staff,
        COUNT(DISTINCT department) as departments,
        COUNT(DISTINCT role) as roles
    FROM hospital_staff 
    WHERE hospital_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats = ['total_staff' => 0, 'active_staff' => 0, 'inactive_staff' => 0, 'departments' => 0, 'roles' => 0];
if ($stats_stmt) {
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
}

// Get department breakdown
$dept_query = "
    SELECT department, COUNT(*) as staff_count 
    FROM hospital_staff 
    WHERE hospital_id = ? AND status = 'active'
    GROUP BY department 
    ORDER BY staff_count DESC
";
$dept_stmt = $conn->prepare($dept_query);
$dept_breakdown = [];
if ($dept_stmt) {
    $dept_stmt->bind_param("i", $user_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    while ($row = $dept_result->fetch_assoc()) {
        $dept_breakdown[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Include header -->
    <?php include '../includes/hospital_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-light sidebar py-3">
                <h6 class="text-muted mb-3">HOSPITAL MENU</h6>
                <div class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-chart-bar"></i> Blood Inventory
                    </a>
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-plus-circle"></i> Request Blood
                    </a>
                    <a class="nav-link" href="blood_requests.php">
                        <i class="fas fa-list"></i> Blood Requests
                    </a>
                    <a class="nav-link" href="record_usage.php">
                        <i class="fas fa-chart-line"></i> Record Usage
                    </a>
                    <a class="nav-link" href="usage_reports.php">
                        <i class="fas fa-file-alt"></i> Usage Reports
                    </a>
                    <a class="nav-link" href="ai_predictions.php">
                        <i class="fas fa-robot"></i> AI Predictions
                    </a>
                    <a class="nav-link" href="nearby_bloodbanks.php">
                        <i class="fas fa-map-marker-alt"></i> Nearby Blood Banks
                    </a>
                    <a class="nav-link active" href="staff_management.php">
                        <i class="fas fa-users"></i> Staff Management
                    </a>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Staff Management</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="fas fa-plus"></i> Add Staff
                        </button>
                        <button class="btn btn-outline-success" onclick="exportStaff()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo $stats['total_staff']; ?></h3>
                                <p class="card-text">Total Staff</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo $stats['active_staff']; ?></h3>
                                <p class="card-text">Active Staff</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo $stats['departments']; ?></h3>
                                <p class="card-text">Departments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo $stats['roles']; ?></h3>
                                <p class="card-text">Different Roles</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Staff List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Staff Directory</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($staff_members)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>No Staff Members</h5>
                                        <p class="text-muted">Start by adding your first staff member.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                            Add First Staff Member
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Role</th>
                                                    <th>Department</th>
                                                    <th>Contact</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($staff_members as $staff): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($staff['name']); ?></strong>
                                                            <br><small class="text-muted">Hired: <?php echo date('M j, Y', strtotime($staff['hire_date'])); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($staff['role']); ?></td>
                                                        <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                                        <td>
                                                            <small>
                                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?>
                                                                <?php if (!empty($staff['phone'])): ?>
                                                                    <br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['phone']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_class = $staff['status'] === 'active' ? 'bg-success' : 'bg-secondary';
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($staff['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editModal<?php echo $staff['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewModal<?php echo $staff['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Department Breakdown -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Department Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($dept_breakdown)): ?>
                                    <p class="text-muted">No active staff members to display department breakdown.</p>
                                <?php else: ?>
                                    <canvas id="departmentChart" width="400" height="300"></canvas>
                                    <div class="mt-3">
                                        <?php foreach ($dept_breakdown as $dept): ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span><?php echo htmlspecialchars($dept['department']); ?></span>
                                                <span class="badge bg-primary"><?php echo $dept['staff_count']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="staff_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="staff_name" name="staff_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="staff_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="staff_email" name="staff_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="staff_phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="staff_phone" name="staff_phone">
                        </div>
                        <div class="mb-3">
                            <label for="staff_role" class="form-label">Role</label>
                            <select class="form-select" id="staff_role" name="staff_role" required>
                                <option value="">Select Role</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Nurse">Nurse</option>
                                <option value="Technician">Technician</option>
                                <option value="Administrator">Administrator</option>
                                <option value="Lab Technician">Lab Technician</option>
                                <option value="Blood Bank Manager">Blood Bank Manager</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="">Select Department</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Surgery">Surgery</option>
                                <option value="Cardiology">Cardiology</option>
                                <option value="Oncology">Oncology</option>
                                <option value="Pediatrics">Pediatrics</option>
                                <option value="Blood Bank">Blood Bank</option>
                                <option value="Laboratory">Laboratory</option>
                                <option value="Administration">Administration</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="hire_date" class="form-label">Hire Date</label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_staff" class="btn btn-primary">Add Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit/View Staff Modals -->
    <?php foreach ($staff_members as $staff): ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?php echo $staff['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo ($staff['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($staff['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="on_leave" <?php echo ($staff['status'] === 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Modal -->
        <div class="modal fade" id="viewModal<?php echo $staff['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Staff Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo htmlspecialchars($staff['name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td><?php echo htmlspecialchars($staff['phone'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Role:</strong></td>
                                <td><?php echo htmlspecialchars($staff['role']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Department:</strong></td>
                                <td><?php echo htmlspecialchars($staff['department']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Hire Date:</strong></td>
                                <td><?php echo date('F j, Y', strtotime($staff['hire_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="badge <?php echo $staff['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($staff['status']); ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Added:</strong></td>
                                <td><?php echo date('F j, Y g:i A', strtotime($staff['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Department Chart
        <?php if (!empty($dept_breakdown)): ?>
        const deptData = <?php echo json_encode($dept_breakdown); ?>;
        
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: deptData.map(item => item.department),
                datasets: [{
                    data: deptData.map(item => item.staff_count),
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        function exportStaff() {
            let csv = 'Name,Email,Phone,Role,Department,Status,Hire Date\n';
            <?php foreach ($staff_members as $staff): ?>
                csv += '"<?php echo str_replace('"', '""', $staff['name']); ?>","<?php echo str_replace('"', '""', $staff['email']); ?>","<?php echo str_replace('"', '""', $staff['phone'] ?? ''); ?>","<?php echo str_replace('"', '""', $staff['role']); ?>","<?php echo str_replace('"', '""', $staff['department']); ?>","<?php echo $staff['status']; ?>","<?php echo $staff['hire_date']; ?>"\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'hospital_staff_<?php echo date("Y-m-d"); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Set today's date as default for hire date
        document.getElementById('hire_date').value = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
