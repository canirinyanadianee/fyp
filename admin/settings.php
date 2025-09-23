<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php';

// Add custom CSS for the settings page
add_custom_css();

function add_custom_css() {
    echo '<style>
        /* Custom styles for settings page */
        .settings-form .card {
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .settings-form .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,.05);
        }
        .settings-form .card-body {
            padding: 1.5rem;
        }
        .settings-form .nav-tabs {
            border-bottom: 1px solid #dee2e6;
        }
        .settings-form .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border-radius: 0;
        }
        .settings-form .nav-tabs .nav-link.active {
            color: #0d6efd;
            background: transparent;
            border-bottom: 2px solid #0d6efd;
        }
        .settings-form .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: #0d6efd;
        }
        .settings-form .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .settings-form .input-group-text {
            background-color: #f8f9fa;
        }
        .password-strength .progress {
            height: 5px !important;
            margin-top: 0.25rem;
        }
        .password-match, .password-strength-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }
        .table th {
            font-weight: 500;
            background-color: #f8f9fa;
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 0.75rem 1rem;
        }
    </style>';
}

// Check if user has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

// Default settings (in a real app, these would come from a database)
$settings = [
    'site_title' => 'Blood Bank Management System',
    'timezone' => 'Asia/Kolkata',
    'date_format' => 'd-m-Y',
    'items_per_page' => '10',
    'email_from' => 'noreply@bloodbank.com',
    'email_from_name' => 'Blood Bank System',
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => '587',
    'smtp_secure' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'login_attempts' => '5',
    'password_expiry' => '90',
    'backup_path' => '../backups/',
    'backup_retention' => '30',
    'auto_backup' => '1',
    'backup_frequency' => 'daily',
    'backup_time' => '02:00'
];

// Function to save settings to database
function saveSettingsToDatabase($settings) {
    // In a real application, you would save these to a database
    // This is a simplified example
    $config_file = __DIR__ . '/../config/settings.json';
    
    // Create config directory if it doesn't exist
    if (!file_exists(dirname($config_file))) {
        mkdir(dirname($config_file), 0755, true);
    }
    
    // Save settings to JSON file
    return file_put_contents($config_file, json_encode($settings, JSON_PRETTY_PRINT));
}

// Function to load settings from database
function loadSettingsFromDatabase() {
    $config_file = __DIR__ . '/../config/settings.json';
    
    if (file_exists($config_file)) {
        return json_decode(file_get_contents($config_file), true) ?? [];
    }
    return [];
}

// Load saved settings
$saved_settings = loadSettingsFromDatabase();
$settings = array_merge($settings, $saved_settings);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle settings update
    if (isset($_POST['update_settings'])) {
        try {
            // In a real app, validate and sanitize all inputs
            $updated_settings = [];
            foreach ($_POST as $key => $value) {
                if (array_key_exists($key, $settings)) {
                    $updated_settings[$key] = trim($value);
                }
            }
            
            // Save to database
            if (saveSettingsToDatabase(array_merge($settings, $updated_settings))) {
                $message = 'Settings updated successfully!';
                $message_type = 'success';
                // Reload settings
                $settings = array_merge($settings, $updated_settings);
            } else {
                throw new Exception('Failed to save settings to file.');
            }
        } catch (Exception $e) {
            $message = 'Error updating settings: ' . $e->getMessage();
            $message_type = 'danger';
        }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required.';
            $message_type = 'danger';
        } elseif (strlen($new_password) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New password and confirm password do not match.';
            $message_type = 'danger';
        } else {
            // In a real app, verify current password and update
            // if (verifyCurrentPassword($current_password)) {
            //     updatePassword($new_password);
                $message = 'Password updated successfully!';
                $message_type = 'success';
            // } else {
            //     $message = 'Current password is incorrect.';
            //     $message_type = 'danger';
            // }
        }
    }
    
    // Handle backup creation
    if (isset($_POST['create_backup'])) {
        try {
            // In a real app, implement backup functionality
            // $backup_file = createDatabaseBackup();
            $message = 'Backup created successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error creating backup: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="fas fa-cog text-primary me-2"></i>System Settings</h2>
            <p class="text-muted mb-0">Configure and customize your system settings</p>
        </div>
        <div class="d-flex">
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-question-circle me-1"></i> Help
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <div><?php echo $message; ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                        <i class="fas fa-sliders-h me-2"></i>General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
                        <i class="fas fa-envelope me-2"></i>Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab" aria-controls="backup" aria-selected="false">
                        <i class="fas fa-database me-2"></i>Backup & Restore
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="settingsTabsContent">
                <!-- General Settings Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <form method="POST" action="" class="settings-form" onsubmit="return validateForm(this)">
                            <input type="hidden" name="update_settings" value="1">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Site Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Site Title</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                                        <input type="text" class="form-control" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                                    </div>
                                                    <small class="form-text text-muted">The name of your blood bank system</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Timezone</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                                                <select class="form-select" name="timezone" required>
                                                            <?php
                                                            $timezones = [
                                                                'UTC' => 'UTC',
                                                                'Asia/Kolkata' => 'Asia/Kolkata (IST)',
                                                                'Asia/Dubai' => 'Asia/Dubai (GST)',
                                                                'America/New_York' => 'America/New York (EST)',
                                                                'Europe/London' => 'Europe/London (GMT)',
                                                                'Europe/Paris' => 'Europe/Paris (CET)',
                                                                'Asia/Tokyo' => 'Asia/Tokyo (JST)',
                                                                'Australia/Sydney' => 'Australia/Sydney (AEST)'
                                                            ];
                                                            
                                                            foreach ($timezones as $value => $label) {
                                                                $selected = ($settings['timezone'] === $value) ? 'selected' : '';
                                                                echo "<option value=\"$value\" $selected>$label</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <small class="form-text text-muted">Default timezone for the system</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Date Format</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                        <select class="form-select" name="date_format" required>
                                                            <?php
                                                            $date_formats = [
                                                                'd-m-Y' => 'DD-MM-YYYY (31-12-2023)',
                                                                'm-d-Y' => 'MM-DD-YYYY (12-31-2023)',
                                                                'Y-m-d' => 'YYYY-MM-DD (2023-12-31)',
                                                                'd/m/Y' => 'DD/MM/YYYY (31/12/2023)',
                                                                'm/d/Y' => 'MM/DD/YYYY (12/31/2023)'
                                                            ];
                                                            
                                                            foreach ($date_formats as $value => $label) {
                                                                $selected = ($settings['date_format'] === $value) ? 'selected' : '';
                                                                echo "<option value=\"$value\" $selected>$label</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Items Per Page</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-list-ol"></i></span>
                                                        <select class="form-select" name="items_per_page">
                                                            <?php
                                                            $items_per_page = [10, 25, 50, 100];
                                                            foreach ($items_per_page as $value) {
                                                                $selected = ($settings['items_per_page'] == $value) ? 'selected' : '';
                                                                echo "<option value=\"$value\" $selected>$value items per page</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-borderless table-sm">
                                                <tbody>
                                                    <tr>
                                                        <td class="fw-bold text-muted" width="30%">PHP Version:</td>
                                                        <td><?php echo phpversion(); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold text-muted">MySQL Version:</td>
                                                        <td><?php 
                                                            $db = new PDO('mysql:host=localhost', 'root', '');
                                                            $version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
                                                            echo $version;
                                                        ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold text-muted">Server Software:</td>
                                                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-bold text-muted">Server Name:</td>
                                                        <td><?php echo $_SERVER['SERVER_NAME']; ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 text-end mt-4">
                                <button type="button" class="btn btn-light me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Email Settings Tab -->
                <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                    <form method="POST" action="" class="settings-form" onsubmit="return validateForm(this)">
                            <input type="hidden" name="update_settings" value="1">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">From Email</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-at"></i></span>
                                                        <input type="email" class="form-control" name="email_from" value="<?php echo htmlspecialchars($settings['email_from']); ?>" required>
                                                    </div>
                                                    <small class="form-text text-muted">Default sender email address</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">From Name</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                        <input type="text" class="form-control" name="email_from_name" value="<?php echo htmlspecialchars($settings['email_from_name']); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="smtpEnabled" name="smtp_enabled" value="1" checked>
                                                    <label class="form-check-label fw-bold" for="smtpEnabled">Enable SMTP</label>
                                                    <small class="d-block text-muted">Use SMTP server for sending emails</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">SMTP Host</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-server"></i></span>
                                                        <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">SMTP Port</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-plug"></i></span>
                                                        <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">SMTP Security</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                        <select class="form-select" name="smtp_secure">
                                                            <option value="tls" <?php echo ($settings['smtp_secure'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                                            <option value="ssl" <?php echo ($settings['smtp_secure'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                                            <option value="" <?php echo empty($settings['smtp_secure']) ? 'selected' : ''; ?>>None</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">SMTP Username</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                        <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">SMTP Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                                                        <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <button type="button" class="btn btn-outline-secondary" onclick="testEmailSettings()">
                                                        <i class="fas fa-paper-plane me-1"></i> Test Email Settings
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 text-end mt-4">
                                <button type="button" class="btn btn-light me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Security Settings Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                    <form method="POST" action="" class="settings-form" onsubmit="return validateForm(this)">
                            <input type="hidden" name="update_settings" value="1">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Max Login Attempts</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-sign-in-alt"></i></span>
                                                        <input type="number" class="form-control" name="login_attempts" min="1" max="10" value="<?php echo htmlspecialchars($settings['login_attempts']); ?>">
                                                    </div>
                                                    <small class="form-text text-muted">Number of failed login attempts before lockout</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Password Expiry (Days)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                                        <input type="number" class="form-control" name="password_expiry" min="0" value="<?php echo htmlspecialchars($settings['password_expiry']); ?>">
                                                        <span class="input-group-text">days</span>
                                                    </div>
                                                    <small class="form-text text-muted">0 = Never expire</small>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="force2FA" name="force_2fa" value="1">
                                                    <label class="form-check-label fw-bold" for="force2FA">Require Two-Factor Authentication</label>
                                                    <small class="d-block text-muted">Users will be required to set up 2FA on next login</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Admin Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Current Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                        <input type="password" class="form-control" name="current_password" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">New Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                                                        <input type="password" class="form-control" name="new_password" id="newPassword" required>
                                                    </div>
                                                    <div class="password-strength mt-2">
                                                        <div class="progress" style="height: 5px;">
                                                            <div class="progress-bar" role="progressbar" style="width: 0%;"></div>
                                                        </div>
                                                        <small class="text-muted password-strength-text">Password strength</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label fw-bold">Confirm Password</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                                        <input type="password" class="form-control" name="confirm_password" required>
                                                    </div>
                                                    <div class="password-match mt-2">
                                                        <small class="text-danger"><i class="fas fa-times-circle me-1"></i> Passwords do not match</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="alert alert-info mb-0">
                                                    <i class="fas fa-info-circle me-2"></i> Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 text-end mt-4">
                                <button type="button" class="btn btn-light me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </button>
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Backup & Restore Tab -->
                <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Backup</h5>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-4">
                                        <h6 class="fw-bold">Create New Backup</h6>
                                        <p class="text-muted">Create a complete backup of your database including all tables and data.</p>
                                        <form method="POST" action="" class="mb-4">
                                            <div class="form-group mb-3">
                                                <label class="form-label fw-bold">Backup Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                    <input type="text" class="form-control" name="backup_name" value="backup_<?php echo date('Y-m-d_His'); ?>">
                                                </div>
                                            </div>
                                            <div class="form-group mb-4">
                                                <label class="form-label fw-bold">Include</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="includeStructure" name="include_structure" checked>
                                                    <label class="form-check-label" for="includeStructure">Database Structure</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="includeData" name="include_data" checked>
                                                    <label class="form-check-label" for="includeData">Table Data</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="compressBackup" name="compress_backup">
                                                    <label class="form-check-label" for="compressBackup">Compress Backup (ZIP)</label>
                                                </div>
                                            </div>
                                            <button type="submit" name="create_backup" class="btn btn-primary w-100">
                                                <i class="fas fa-download me-1"></i> Create Backup Now
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <h6 class="fw-bold">Scheduled Backups</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableAutoBackup" name="auto_backup" <?php echo $settings['auto_backup'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="enableAutoBackup">Enable Automatic Backups</label>
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Frequency</label>
                                                    <select class="form-select" name="backup_frequency">
                                                        <option value="daily" <?php echo ($settings['backup_frequency'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                        <option value="weekly" <?php echo ($settings['backup_frequency'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                        <option value="monthly" <?php echo ($settings['backup_frequency'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Time</label>
                                                    <input type="time" class="form-control" name="backup_time" value="<?php echo htmlspecialchars($settings['backup_time']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label">Retention Period</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="backup_retention" min="1" value="<?php echo htmlspecialchars($settings['backup_retention']); ?>">
                                                        <span class="input-group-text">days</span>
                                                    </div>
                                                    <small class="form-text text-muted">Automatically delete backups older than this period</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent text-end">
                                    <button type="submit" name="save_backup_settings" class="btn btn-primary" id="saveSettingsBtn">
                                        <i class="fas fa-save me-1"></i> Save Settings
                                    </button>
                                    <div id="saveStatus" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Backup History</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Date</th>
                                                    <th>Size</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">
                                                        <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                                        <p class="mb-0">No backup files found</p>
                                                    </td>
                                                </tr>
                                                <!-- Example backup entry (commented out) -->
                                                <!--
                                                <tr>
                                                    <td>backup_2023_10_15_143022.sql</td>
                                                    <td>Oct 15, 2023 14:30</td>
                                                    <td>2.5 MB</td>
                                                    <td class="text-end">
                                                        <button class="btn btn-sm btn-outline-primary me-1">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted small">
                                            Last backup: Never
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-secondary me-1">
                                                <i class="fas fa-sync-alt me-1"></i> Refresh
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash me-1"></i> Clear All
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                                                <option value="Y-m-d">YYYY-MM-DD</option>
                                                <option value="d/m/Y">DD/MM/YYYY</option>
                                                <option value="m/d/Y">MM/DD/YYYY</option>
                                                <option value="d M, Y">DD MMM, YYYY</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <button type="submit" name="update_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Email Settings Tab -->
                        <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>SMTP Host</label>
                                            <input type="text" class="form-control" name="smtp_host" placeholder="smtp.example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>SMTP Port</label>
                                            <input type="number" class="form-control" name="smtp_port" placeholder="587">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>SMTP Username</label>
                                            <input type="text" class="form-control" name="smtp_username" placeholder="user@example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>SMTP Password</label>
                                            <input type="password" class="form-control" name="smtp_password" placeholder="••••••••">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>From Email</label>
                                            <input type="email" class="form-control" name="from_email" placeholder="noreply@example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>From Name</label>
                                            <input type="text" class="form-control" name="from_name" value="Blood Bank System">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <button type="submit" name="update_email_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Email Settings
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary ms-2" id="testEmailBtn">
                                            <i class="fas fa-paper-plane me-2"></i>Send Test Email
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Security Settings Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Change Password</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Current Password</label>
                                                    <input type="password" class="form-control" name="current_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>New Password</label>
                                                    <input type="password" class="form-control" name="new_password" required>
                                                    <small class="form-text text-muted">Minimum 8 characters, with at least 1 uppercase, 1 number, and 1 special character</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Confirm New Password</label>
                                                    <input type="password" class="form-control" name="confirm_password" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <button type="submit" name="change_password" class="btn btn-primary">
                                                    <i class="fas fa-key me-2"></i>Change Password
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Login Security</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enable2fa" name="enable_2fa">
                                            <label class="form-check-label" for="enable2fa">Enable Two-Factor Authentication</label>
                                            <small class="form-text d-block text-muted">Require a verification code in addition to password for login</small>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="failedLoginLockout" name="failed_login_lockout" checked>
                                            <label class="form-check-label" for="failedLoginLockout">Enable Account Lockout After Failed Attempts</label>
                                            <small class="form-text d-block text-muted">Lock account after 5 failed login attempts</small>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="sessionTimeout" name="session_timeout" checked>
                                            <label class="form-check-label" for="sessionTimeout">Enable Session Timeout</label>
                                            <small class="form-text d-block text-muted">Automatically log out inactive users after 30 minutes</small>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <button type="submit" name="update_security_settings" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Save Security Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Backup & Restore Tab -->
                        <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0">Create Backup</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Create a complete backup of the system including database and uploaded files.</p>
                                            <form method="POST" action="">
                                                <div class="form-group mb-3">
                                                    <label>Backup Name</label>
                                                    <input type="text" class="form-control" name="backup_name" value="backup_<?php echo date('Y-m-d_His'); ?>">
                                                </div>
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="includeDatabase" name="include_database" checked>
                                                    <label class="form-check-label" for="includeDatabase">Include Database</label>
                                                </div>
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="includeUploads" name="include_uploads" checked>
                                                    <label class="form-check-label" for="includeUploads">Include Uploaded Files</label>
                                                </div>
                                                <button type="submit" name="create_backup" class="btn btn-primary">
                                                    <i class="fas fa-download me-2"></i>Create Backup
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Restore from Backup</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <strong>Warning:</strong> Restoring from a backup will overwrite all current data. Make sure to create a backup before proceeding.
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Select Backup File</label>
                                                <select class="form-select" name="backup_file">
                                                    <option value="">-- Select a backup file --</option>
                                                    <option value="backup_2023-10-15_143000.zip">backup_2023-10-15_143000.zip</option>
                                                    <option value="backup_2023-10-10_092500.zip">backup_2023-10-10_092500.zip</option>
                                                    <option value="backup_2023-10-01_084500.zip">backup_2023-10-01_084500.zip</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                                                <label class="form-check-label" for="confirmRestore">
                                                    I understand that this will overwrite all current data
                                                </label>
                                            </div>
                                            
                                            <button type="button" class="btn btn-danger" id="restoreBtn" disabled>
                                                <i class="fas fa-undo me-2"></i>Restore from Backup
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Scheduled Backups</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Frequency</th>
                                                    <th>Last Run</th>
                                                    <th>Status</th>
                                                    <th>Next Run</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Daily</td>
                                                    <td>2023-10-15 02:00</td>
                                                    <td><span class="badge bg-success">Success</span></td>
                                                    <td>2023-10-16 02:00</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Weekly</td>
                                                    <td>2023-10-08 03:00</td>
                                                    <td><span class="badge bg-success">Success</span></td>
                                                    <td>2023-10-15 03:00</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Monthly</td>
                                                    <td>2023-09-30 04:00</td>
                                                    <td><span class="badge bg-warning">Warning</span></td>
                                                    <td>2023-10-31 04:00</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button class="btn btn-primary mt-3">
                                        <i class="fas fa-plus me-2"></i>Add New Schedule
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Settings Page -->
<script>
// Enable/disable restore button based on confirmation
const confirmRestore = document.getElementById('confirmRestore');
const restoreBtn = document.getElementById('restoreBtn');

if (confirmRestore && restoreBtn) {
    confirmRestore.addEventListener('change', function() {
        restoreBtn.disabled = !this.checked;
    });
}

// Test email button handler
document.getElementById('testEmailBtn')?.addEventListener('click', function() {
    // Implement test email functionality
    alert('Test email functionality will be implemented here.');
});

// Confirmation for restore
document.getElementById('restoreBtn')?.addEventListener('click', function() {
    if (confirm('WARNING: This will overwrite all current data with the backup. Are you sure you want to continue?')) {
        // Implement restore functionality
        alert('Restore functionality will be implemented here.');
    }
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Password strength meter
const passwordInput = document.getElementById('newPassword');
const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
const strengthMeter = document.querySelector('.password-strength .progress-bar');
const strengthText = document.querySelector('.password-strength-text');
const matchText = document.querySelector('.password-match');

if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        updateStrengthMeter(strength);
        checkPasswordMatch();
    });
}

if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
}

function checkPasswordStrength(password) {
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength += 1;
    
    // Contains lowercase
    if (/[a-z]/.test(password)) strength += 1;
    
    // Contains uppercase
    if (/[A-Z]/.test(password)) strength += 1;
    
    // Contains numbers
    if (/[0-9]/.test(password)) strength += 1;
    
    // Contains special characters
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;
    
    return strength;
}

function updateStrengthMeter(strength) {
    const strengthMeter = document.querySelector('.password-strength .progress-bar');
    const strengthText = document.querySelector('.password-strength-text');
    
    let width = (strength / 5) * 100;
    let color = 'danger';
    let text = 'Very Weak';
    
    if (strength >= 4) {
        color = 'success';
        text = 'Strong';
    } else if (strength >= 3) {
        color = 'info';
        text = 'Good';
    } else if (strength >= 2) {
        color = 'warning';
        text = 'Weak';
    }
    
    strengthMeter.style.width = width + '%';
    strengthMeter.className = 'progress-bar bg-' + color;
    strengthText.textContent = text + ' Password';
    strengthText.className = 'text-' + color + ' password-strength-text';
    strengthText.style.display = 'block';
}

function checkPasswordMatch() {
    if (!passwordInput || !confirmPasswordInput) return;
    
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    
    if (password === '' || confirmPassword === '') {
        matchText.style.display = 'none';
        return;
    }
    
    if (password === confirmPassword) {
        matchText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Passwords match';
        matchText.className = 'text-success';
    } else {
        matchText.innerHTML = '<i class="fas fa-times-circle me-1"></i> Passwords do not match';
        matchText.className = 'text-danger';
    }
    matchText.style.display = 'block';
}

// Form validation
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    const saveBtn = document.getElementById('saveSettingsBtn');
    const saveStatus = document.getElementById('saveStatus');
    
    // Reset previous states
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
    saveStatus.innerHTML = '';
    
    // Validate required fields
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // Custom validation for email fields
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });
    
    // If form is valid, prepare for submission
    if (isValid) {
        // You can add additional validation here if needed
        return true;
    } else {
        // Re-enable the button and show error
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Settings';
        saveStatus.innerHTML = '<div class="alert alert-danger mt-2">Please fill in all required fields correctly.</div>';
        
        // Scroll to first invalid field
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
        }
        
        return false;
    }
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Initialize form validation on all forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.settings-form');
    
    forms.forEach(form => {
        // Handle form submission with AJAX for better UX
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm(this)) {
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const saveStatus = document.getElementById('saveStatus');
                
                // Show loading state
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                
                // Submit form via AJAX
                fetch(this.action || window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Create a temporary div to parse the response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Check for success/error message in the response
                    const messageEl = doc.querySelector('.alert');
                    if (messageEl) {
                        const messageType = messageEl.classList.contains('alert-success') ? 'success' : 'danger';
                        showMessage(messageEl.textContent.trim(), messageType);
                        
                        // If save was successful, update the form with any returned data
                        if (messageType === 'success') {
                            // You might want to update form fields with the response data
                            // This depends on your server-side implementation
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred while saving the settings.', 'danger');
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            }
        });
        
        // Add input event listeners to clear invalid state when user types
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    });
    
    // Initialize SMTP fields toggle
    const smtpToggle = document.getElementById('smtpEnabled');
    const smtpFields = document.querySelectorAll('.smtp-field');
    
    function toggleSmtpFields() {
        const isEnabled = smtpToggle.checked;
        smtpFields.forEach(field => {
            field.disabled = !isEnabled;
            field.parentNode.style.opacity = isEnabled ? '1' : '0.6';
        });
    }
    
    if (smtpToggle) {
        smtpToggle.addEventListener('change', toggleSmtpFields);
        toggleSmtpFields(); // Initial call
    }
});

// Test email settings
function testEmailSettings() {
    const email = prompt('Enter an email address to send a test email to:');
    if (!email) return;
    
    // Show loading state
    const testButton = document.querySelector('[onclick="testEmailSettings()"]');
    const originalText = testButton.innerHTML;
    testButton.disabled = true;
    testButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';
    
    // In a real application, you would make an AJAX call here
    setTimeout(() => {
        // Simulate API call
        const success = Math.random() > 0.2; // 80% success rate for demo
        
        if (success) {
            alert('Test email sent successfully to ' + email);
        } else {
            alert('Failed to send test email. Please check your settings and try again.');
        }
        
        // Reset button
        testButton.disabled = false;
        testButton.innerHTML = originalText;
    }, 1500);
}

// Reset form to default values
function resetForm() {
    if (confirm('Are you sure you want to reset all fields to their default values? Any unsaved changes will be lost.')) {
        const forms = document.querySelectorAll('.settings-form');
        forms.forEach(form => form.reset());
    }
}

// Handle tab state persistence
const tabElms = document.querySelectorAll('button[data-bs-toggle="tab"]');
tabElms.forEach(tabEl => {
    tabEl.addEventListener('click', function(e) {
        localStorage.setItem('lastTab', e.target.getAttribute('data-bs-target'));
    });
});

// Restore last active tab
const lastTab = localStorage.getItem('lastTab');
if (lastTab) {
    const tab = new bootstrap.Tab(document.querySelector(`[data-bs-target="${lastTab}"]`));
    tab.show();
}
</script>

        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
