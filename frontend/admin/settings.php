<?php
require_once '../resident/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../login.html');
    exit();
}

$conn = getDBConnection();

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_settings'])) {
        // Handle system settings update
        $system_name = clean($_POST['system_name']);
        $admin_email = clean($_POST['admin_email']);
        $items_per_page = (int)$_POST['items_per_page'];
        $enable_registration = isset($_POST['enable_registration']) ? 1 : 0;
        $enable_email_notifications = isset($_POST['enable_email_notifications']) ? 1 : 0;
        $borrow_limit = (int)$_POST['borrow_limit'];
        $borrow_duration = (int)$_POST['borrow_duration'];
        $late_fee_per_day = (float)$_POST['late_fee_per_day'];
        
        // In a real application, you would save these to a settings table
        // For now, we'll simulate with session messages
        $_SESSION['success_message'] = "System settings updated successfully!";
        
    } elseif (isset($_POST['update_profile'])) {
        // Handle admin profile update
        $admin_name = clean($_POST['admin_name']);
        $admin_email = clean($_POST['admin_email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current admin info
        $admin_id = $_SESSION['user_id'];
        $current_admin = $conn->query("SELECT * FROM users WHERE user_id = $admin_id")->fetch();
        
        if (!empty($new_password)) {
            // Verify current password
            if (!password_verify($current_password, $current_admin['password_hash'])) {
                $_SESSION['error_message'] = "Current password is incorrect!";
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['error_message'] = "New passwords do not match!";
            } elseif (strlen($new_password) < 8) {
                $_SESSION['error_message'] = "Password must be at least 8 characters!";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$hashed_password, $admin_id]);
                $_SESSION['success_message'] = "Password updated successfully!";
            }
        }
        
        // Update name and email
        $conn->prepare("UPDATE users SET first_name = ?, email = ? WHERE user_id = ?")->execute([$admin_name, $admin_email, $admin_id]);
        $_SESSION['success_message'] = isset($_SESSION['success_message']) ? $_SESSION['success_message'] . " Profile updated!" : "Profile updated successfully!";
        
        // Update session
        $_SESSION['user_name'] = $admin_name;
        $_SESSION['user_email'] = $admin_email;
    }
    
    header('Location: settings.php');
    exit();
}

// Get current admin info
$admin_id = $_SESSION['user_id'];
$admin_info = $conn->query("SELECT * FROM users WHERE user_id = $admin_id")->fetch();

// Get system statistics for dashboard
$total_residents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND status = 'Active'")->fetch()['count'];
$total_resources = $conn->query("SELECT COUNT(*) as count FROM resources")->fetch()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE status = 'pending'")->fetch()['count'] + 
                    $conn->query("SELECT COUNT(*) as count FROM donations WHERE status = 'pending'")->fetch()['count'];
$pending_verifications = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND status = 'Pending'")->fetch()['count'];

// Get recent activity logs (simulated for now)
$recent_activities = [
    ['action' => 'System Login', 'user' => 'Admin', 'time' => 'Just now', 'icon' => 'sign-in-alt', 'color' => 'success'],
    ['action' => 'Resource Added', 'user' => 'Admin', 'time' => '5 minutes ago', 'icon' => 'plus-circle', 'color' => 'primary'],
    ['action' => 'Request Approved', 'user' => 'Admin', 'time' => '15 minutes ago', 'icon' => 'check-circle', 'color' => 'success'],
    ['action' => 'User Verified', 'user' => 'Admin', 'time' => '1 hour ago', 'icon' => 'user-check', 'color' => 'success'],
    ['action' => 'System Backup', 'user' => 'System', 'time' => '2 hours ago', 'icon' => 'database', 'color' => 'warning'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings | BRMS Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../frontend/admin/admin-dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="logo">
                <h2>
                    <i class="fas fa-shield-alt"></i>
                    <span>BRMS Admin</span>
                </h2>
                <p>Administrator Panel</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_requests.php" class="nav-link">
                        <i class="fas fa-tasks"></i>
                        <span>Manage Requests</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="resources.php" class="nav-link">
                        <i class="fas fa-boxes"></i>
                        <span>Resources</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="residents.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Residents</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link active">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
            
            <div class="logout-section">
                <a href="/barangay_resource/backend/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- HEADER -->
            <header class="page-header">
                <div class="header-main">
                    <div class="header-title-section">
                        <h1><i class="fas fa-cog"></i> System Settings</h1>
                        <p class="header-subtitle">Configure system preferences and manage your account</p>
                    </div>
                    
                    <div class="header-controls">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search settings...">
                            <i class="fas fa-search"></i>
                        </div>
                        <button class="btn btn-primary" onclick="backupSystem()">
                            <i class="fas fa-download"></i> Backup
                        </button>
                        <div class="theme-toggle" id="themeToggle">
                            <i class="fas fa-moon"></i>
                            <span class="toggle-label">Dark Mode</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ALERT MESSAGES -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert-banner success">
                    <i class="fas fa-check-circle"></i>
                    <div class="alert-content">
                        <h3>Success!</h3>
                        <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert-banner danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="alert-content">
                        <h3>Error!</h3>
                        <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- QUICK STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">System Users</div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_residents + 1; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-user-plus"></i>
                        <?php echo $total_residents; ?> residents
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Resources</div>
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_resources; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-database"></i>
                        In inventory
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Pending Items</div>
                        <div class="stat-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pending_requests; ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-exclamation-circle"></i>
                        Needs attention
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Pending Verifications</div>
                        <div class="stat-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pending_verifications; ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-clock"></i>
                        Awaiting approval
                    </div>
                </div>
            </div>

            <!-- SETTINGS TABS -->
            <div class="settings-tabs">
                <button class="tab-btn active" data-tab="system">System Settings</button>
                <button class="tab-btn" data-tab="profile">Admin Profile</button>
                <button class="tab-btn" data-tab="security">Security</button>
                <button class="tab-btn" data-tab="notifications">Notifications</button>
                <button class="tab-btn" data-tab="backup">Backup & Restore</button>
            </div>

            <!-- SYSTEM SETTINGS -->
            <section id="system-section" class="settings-section active">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-sliders-h"></i> System Configuration</h2>
                        <span class="section-help">Configure general system settings</span>
                    </div>
                    
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="update_settings" value="1">
                        
                        <div class="form-group">
                            <label>System Name</label>
                            <input type="text" name="system_name" value="Barangay Resource Management System" placeholder="Enter system name">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Admin Email</label>
                                <input type="email" name="admin_email" value="admin@barangay.gov.ph" placeholder="admin@example.com">
                            </div>
                            <div class="form-group">
                                <label>Items Per Page</label>
                                <select name="items_per_page">
                                    <option value="10">10 items</option>
                                    <option value="25" selected>25 items</option>
                                    <option value="50">50 items</option>
                                    <option value="100">100 items</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Borrow Limit per User</label>
                                <input type="number" name="borrow_limit" value="5" min="1" max="20">
                                <span class="form-help">Maximum items a user can borrow at once</span>
                            </div>
                            <div class="form-group">
                                <label>Borrow Duration (days)</label>
                                <input type="number" name="borrow_duration" value="7" min="1" max="30">
                                <span class="form-help">Default borrowing period</span>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Late Fee per Day (₱)</label>
                                <input type="number" name="late_fee_per_day" value="20" min="0" step="0.5">
                                <span class="form-help">Fee for overdue items per day</span>
                            </div>
                        </div>
                        
                        <div class="form-checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="enable_registration" id="enable_registration" checked>
                                <label for="enable_registration">Enable Resident Registration</label>
                                <span class="checkbox-help">Allow new residents to register accounts</span>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="enable_email_notifications" id="enable_email_notifications" checked>
                                <label for="enable_email_notifications">Enable Email Notifications</label>
                                <span class="checkbox-help">Send email notifications for important events</span>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="maintenance_mode" id="maintenance_mode">
                                <label for="maintenance_mode">Maintenance Mode</label>
                                <span class="checkbox-help">Take system offline for maintenance</span>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="reset" class="btn btn-secondary">Reset</button>
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- ADMIN PROFILE -->
            <section id="profile-section" class="settings-section">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-user-cog"></i> Admin Profile</h2>
                        <span class="section-help">Update your account information</span>
                    </div>
                    
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <div class="avatar-large">
                                    <?php echo strtoupper(substr($admin_info['first_name'], 0, 1) . substr($admin_info['last_name'], 0, 1)); ?>
                                </div>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="changeAvatar()">
                                    <i class="fas fa-camera"></i> Change Photo
                                </button>
                            </div>
                            <div class="profile-info">
                                <h3><?php echo clean($admin_info['first_name'] . ' ' . $admin_info['last_name']); ?></h3>
                                <p class="profile-role">System Administrator</p>
                                <p class="profile-joined">Joined: <?php echo date('F Y', strtotime($admin_info['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="admin_name" value="<?php echo clean($admin_info['first_name'] . ' ' . $admin_info['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="admin_email" value="<?php echo clean($admin_info['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="tel" name="contact_no" value="<?php echo clean($admin_info['contact_no']); ?>" placeholder="+63 XXX XXX XXXX">
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="Administrator" disabled class="disabled-input">
                            </div>
                        </div>
                        
                        <h3 class="section-title"><i class="fas fa-key"></i> Change Password</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" placeholder="Enter current password">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Enter new password">
                                <span class="form-help">Minimum 8 characters</span>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="reset" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- SECURITY SETTINGS -->
            <section id="security-section" class="settings-section">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                        <span class="section-help">Configure security preferences</span>
                    </div>
                    
                    <div class="security-settings">
                        <div class="security-item">
                            <div class="security-info">
                                <h4><i class="fas fa-lock"></i> Two-Factor Authentication</h4>
                                <p>Add an extra layer of security to your account</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="2fa_toggle">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <h4><i class="fas fa-history"></i> Session Timeout</h4>
                                <p>Automatically log out after 30 minutes of inactivity</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="session_timeout" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <h4><i class="fas fa-ban"></i> IP Restriction</h4>
                                <p>Restrict access to specific IP addresses</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="ip_restriction">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <h4><i class="fas fa-file-alt"></i> Activity Logging</h4>
                                <p>Log all administrative activities</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="activity_logging" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <h4><i class="fas fa-exclamation-triangle"></i> Failed Login Alerts</h4>
                                <p>Receive alerts for multiple failed login attempts</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="login_alerts" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" onclick="saveSecuritySettings()">Save Security Settings</button>
                    </div>
                </div>
                
                <!-- RECENT ACTIVITY LOG -->
                <div class="panel" style="margin-top: 30px;">
                    <div class="panel-header">
                        <h2><i class="fas fa-history"></i> Recent Activity Log</h2>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['color']; ?>">
                                    <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <h4><?php echo $activity['action']; ?></h4>
                                    <p>By <?php echo $activity['user']; ?></p>
                                </div>
                                <div class="activity-time">
                                    <?php echo $activity['time']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- NOTIFICATION SETTINGS -->
            <section id="notifications-section" class="settings-section">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
                        <span class="section-help">Configure how you receive notifications</span>
                    </div>
                    
                    <div class="notification-settings">
                        <h3 class="section-title">Email Notifications</h3>
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>New Registration Requests</h4>
                                <p>Get notified when new residents register</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>Borrow Requests</h4>
                                <p>Notifications for new borrow requests</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>Donation Submissions</h4>
                                <p>Alerts for new donation submissions</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>Overdue Returns</h4>
                                <p>Daily alerts for overdue items</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>System Alerts</h4>
                                <p>Important system notifications</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <h3 class="section-title">In-App Notifications</h3>
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>Desktop Notifications</h4>
                                <p>Show browser notifications</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h4>Sound Alerts</h4>
                                <p>Play sound for new notifications</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" onclick="saveNotificationSettings()">Save Notification Settings</button>
                    </div>
                </div>
            </section>

            <!-- BACKUP & RESTORE -->
            <section id="backup-section" class="settings-section">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fas fa-database"></i> Backup & Restore</h2>
                        <span class="section-help">Manage system backups and data recovery</span>
                    </div>
                    
                    <div class="backup-settings">
                        <div class="backup-info">
                            <div class="backup-stats">
                                <div class="stat-item">
                                    <i class="fas fa-database"></i>
                                    <div>
                                        <h4>Database Size</h4>
                                        <p>15.7 MB</p>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <h4>Last Backup</h4>
                                        <p>2 days ago</p>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-save"></i>
                                    <div>
                                        <h4>Auto-backup</h4>
                                        <p>Enabled (Daily)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="backup-actions">
                            <button class="btn btn-primary" onclick="createBackup()">
                                <i class="fas fa-download"></i> Create Backup Now
                            </button>
                            <button class="btn btn-secondary" onclick="scheduleBackup()">
                                <i class="fas fa-clock"></i> Schedule Backup
                            </button>
                            <button class="btn btn-secondary" onclick="restoreBackup()">
                                <i class="fas fa-upload"></i> Restore Backup
                            </button>
                        </div>
                        
                        <h3 class="section-title">Recent Backups</h3>
                        <div class="backup-list">
                            <div class="backup-item">
                                <div class="backup-details">
                                    <i class="fas fa-database"></i>
                                    <div>
                                        <h4>Full System Backup</h4>
                                        <p>Size: 15.7 MB • Date: Today, 00:00</p>
                                    </div>
                                </div>
                                <div class="backup-actions">
                                    <button class="btn-action download" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn-action restore" title="Restore">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <button class="btn-action delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="backup-item">
                                <div class="backup-details">
                                    <i class="fas fa-database"></i>
                                    <div>
                                        <h4>Database Only</h4>
                                        <p>Size: 8.2 MB • Date: Yesterday, 00:00</p>
                                    </div>
                                </div>
                                <div class="backup-actions">
                                    <button class="btn-action download" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn-action restore" title="Restore">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <button class="btn-action delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="backup-item">
                                <div class="backup-details">
                                    <i class="fas fa-database"></i>
                                    <div>
                                        <h4>Weekly Backup</h4>
                                        <p>Size: 15.5 MB • Date: 7 days ago</p>
                                    </div>
                                </div>
                                <div class="backup-actions">
                                    <button class="btn-action download" title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn-action restore" title="Restore">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <button class="btn-action delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="auto-backup-settings">
                            <h3 class="section-title">Auto-backup Settings</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <select id="backup_frequency">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" id="backup_time" value="00:00">
                                </div>
                                <div class="form-group">
                                    <label>Keep Backups For</label>
                                    <select id="retention_period">
                                        <option value="7">7 days</option>
                                        <option value="30" selected>30 days</option>
                                        <option value="90">90 days</option>
                                        <option value="365">1 year</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" onclick="saveBackupSettings()">Save Backup Settings</button>
                    </div>
                </div>
            </section>

            <!-- FOOTER -->
            <footer class="admin-footer">
                <p>Barangay Resource Management System | Settings v2.0</p>
                <p>© <?php echo date('Y'); ?> All rights reserved | Version: 2.0.1</p>
            </footer>
        </main>
    </div>

    <style>
        /* Settings Specific Styles */
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding: 0 5px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: var(--admin-bg-light);
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            color: var(--admin-text-secondary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab-btn.active {
            background: var(--admin-accent);
            color: white;
            border-color: var(--admin-accent);
        }
        
        .tab-btn:hover:not(.active) {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--admin-accent);
            color: var(--admin-accent);
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        /* Form Styles */
        .settings-form {
            padding: 20px 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--admin-text);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: var(--admin-bg-light);
            color: var(--admin-text);
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-help {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--admin-text-secondary);
        }
        
        .disabled-input {
            background: #f3f4f6 !important;
            cursor: not-allowed;
        }
        
        .dark-mode .disabled-input {
            background: #374151 !important;
        }
        
        /* Checkbox Group */
        .form-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--admin-bg-light);
            border-radius: 8px;
            border: 1px solid var(--admin-border);
        }
        
        .checkbox-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .checkbox-item label {
            font-weight: 500;
            color: var(--admin-text);
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .checkbox-help {
            font-size: 13px;
            color: var(--admin-text-secondary);
            margin-left: 26px;
        }
        
        /* Profile Header */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
            border-radius: 12px;
            border: 1px solid var(--admin-border);
        }
        
        .avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--admin-accent), var(--admin-success));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .profile-avatar {
            text-align: center;
        }
        
        .btn-sm {
            padding: 8px 16px !important;
            font-size: 13px !important;
        }
        
        .profile-info h3 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: var(--admin-text);
        }
        
        .profile-role {
            font-size: 16px;
            color: var(--admin-accent);
            font-weight: 500;
            margin: 0 0 10px 0;
        }
        
        .profile-joined {
            font-size: 14px;
            color: var(--admin-text-secondary);
            margin: 0;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--admin-text);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-help {
            font-size: 14px;
            color: var(--admin-text-secondary);
            font-weight: normal;
        }
        
        /* Toggle Switch */
        .security-settings,
        .notification-settings {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .security-item,
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--admin-bg-light);
            border-radius: 8px;
            border: 1px solid var(--admin-border);
            transition: all 0.3s;
        }
        
        .security-item:hover,
        .notification-item:hover {
            border-color: var(--admin-accent);
            transform: translateX(5px);
        }
        
        .security-info h4,
        .notification-info h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: var(--admin-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .security-info p,
        .notification-info p {
            margin: 0;
            font-size: 14px;
            color: var(--admin-text-secondary);
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--admin-accent);
        }
        
        input:checked + .slider:before {
            transform: translateX(30px);
        }
        
        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--admin-border);
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: translateX(5px);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .activity-icon.primary {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
        }
        
        .activity-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .activity-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
        }
        
        .activity-details h4 {
            margin: 0 0 5px 0;
            font-size: 15px;
            color: var(--admin-text);
        }
        
        .activity-details p {
            margin: 0;
            font-size: 13px;
            color: var(--admin-text-secondary);
        }
        
        .activity-time {
            margin-left: auto;
            font-size: 12px;
            color: var(--admin-text-secondary);
        }
        
        /* Backup Settings */
        .backup-info {
            margin-bottom: 30px;
        }
        
        .backup-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: var(--admin-bg-light);
            border-radius: 8px;
            border: 1px solid var(--admin-border);
        }
        
        .stat-item i {
            font-size: 24px;
            color: var(--admin-accent);
        }
        
        .stat-item h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: var(--admin-text);
        }
        
        .stat-item p {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--admin-text);
        }
        
        .backup-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .backup-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--admin-bg-light);
            border-radius: 8px;
            border: 1px solid var(--admin-border);
            transition: all 0.3s;
        }
        
        .backup-item:hover {
            border-color: var(--admin-accent);
            transform: translateX(5px);
        }
        
        .backup-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .backup-details i {
            font-size: 24px;
            color: var(--admin-accent);
        }
        
        .backup-details h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: var(--admin-text);
        }
        
        .backup-details p {
            margin: 0;
            font-size: 13px;
            color: var(--admin-text-secondary);
        }
        
        .backup-actions .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            margin-left: 5px;
        }
        
        .btn-action.download {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
        }
        
        .btn-action.download:hover {
            background: var(--admin-accent);
            color: white;
        }
        
        .btn-action.restore {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .btn-action.restore:hover {
            background: var(--admin-success);
            color: white;
        }
        
        .btn-action.delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .btn-action.delete:hover {
            background: var(--admin-danger);
            color: white;
        }
        
        .auto-backup-settings {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--admin-border);
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--admin-border);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--admin-accent);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--admin-accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: var(--admin-bg-light);
            color: var(--admin-text-secondary);
            border: 1px solid var(--admin-border);
        }
        
        .btn-secondary:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
            border-color: var(--admin-accent);
        }
        
        .alert-banner.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.2) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .alert-banner.success i {
            color: var(--admin-success);
        }
        
        .alert-banner.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.2) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .alert-banner.danger i {
            color: var(--admin-danger);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');
            const themeLabel = themeToggle.querySelector('.toggle-label');
            
            // Check for saved theme preference
            const currentTheme = localStorage.getItem('admin-theme') || 'light';
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeIcon.className = 'fas fa-sun';
                themeLabel.textContent = 'Light Mode';
            }
            
            // Toggle theme
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                
                if (document.body.classList.contains('dark-mode')) {
                    themeIcon.className = 'fas fa-sun';
                    themeLabel.textContent = 'Light Mode';
                    localStorage.setItem('admin-theme', 'dark');
                } else {
                    themeIcon.className = 'fas fa-moon';
                    themeLabel.textContent = 'Dark Mode';
                    localStorage.setItem('admin-theme', 'light');
                }
            });
            
            // Tab functionality
            const tabBtns = document.querySelectorAll('.tab-btn');
            const settingsSections = document.querySelectorAll('.settings-section');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.dataset.tab;
                    
                    // Update active tab button
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding section
                    settingsSections.forEach(section => {
                        section.classList.remove('active');
                        if (section.id === `${tabId}-section`) {
                            section.classList.add('active');
                        }
                    });
                });
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                const activeSection = document.querySelector('.settings-section.active');
                
                // Search in form labels and headings
                const formElements = activeSection.querySelectorAll('label, h3, h4, p');
                formElements.forEach(element => {
                    if (element.textContent.toLowerCase().includes(searchTerm) || searchTerm === '') {
                        element.closest('.form-group, .security-item, .notification-item, .backup-item')?.style.display = '';
                        element.style.opacity = '1';
                    } else {
                        const parent = element.closest('.form-group, .security-item, .notification-item, .backup-item');
                        if (parent) {
                            parent.style.opacity = '0.3';
                        }
                    }
                });
            });
            
            // Backup system function
            window.backupSystem = function() {
                if (confirm('Create a full system backup now? This may take a few minutes.')) {
                    showLoading('Creating backup...');
                    setTimeout(() => {
                        hideLoading();
                        alert('Backup created successfully! Download will start automatically.');
                        // In real implementation, this would trigger a download
                    }, 2000);
                }
            };
            
            // Security settings functions
            window.saveSecuritySettings = function() {
                const settings = {
                    twoFactor: document.getElementById('2fa_toggle').checked,
                    sessionTimeout: document.getElementById('session_timeout').checked,
                    ipRestriction: document.getElementById('ip_restriction').checked,
                    activityLogging: document.getElementById('activity_logging').checked,
                    loginAlerts: document.getElementById('login_alerts').checked
                };
                
                // In real implementation, save to server
                localStorage.setItem('security_settings', JSON.stringify(settings));
                alert('Security settings saved successfully!');
            };
            
            // Notification settings functions
            window.saveNotificationSettings = function() {
                // Collect all notification toggles
                const toggles = document.querySelectorAll('#notifications-section .switch input');
                const settings = {};
                toggles.forEach((toggle, index) => {
                    settings[`notification_${index}`] = toggle.checked;
                });
                
                // In real implementation, save to server
                localStorage.setItem('notification_settings', JSON.stringify(settings));
                alert('Notification settings saved successfully!');
            };
            
            // Backup settings functions
            window.saveBackupSettings = function() {
                const settings = {
                    frequency: document.getElementById('backup_frequency').value,
                    time: document.getElementById('backup_time').value,
                    retention: document.getElementById('retention_period').value
                };
                
                // In real implementation, save to server
                localStorage.setItem('backup_settings', JSON.stringify(settings));
                alert('Backup settings saved successfully!');
            };
            
            window.createBackup = function() {
                if (confirm('Create a new system backup? This will include all data and settings.')) {
                    showLoading('Creating backup... Please wait.');
                    setTimeout(() => {
                        hideLoading();
                        alert('Backup created successfully! You can download it from the backup list.');
                        // Add new backup to list dynamically
                    }, 3000);
                }
            };
            
            window.scheduleBackup = function() {
                alert('Backup scheduling feature coming soon!');
            };
            
            window.restoreBackup = function() {
                if (confirm('WARNING: Restoring a backup will overwrite current data. Continue?')) {
                    alert('Please select a backup file to restore.');
                    // In real implementation, show file picker
                }
            };
            
            window.changeAvatar = function() {
                alert('Avatar upload feature coming soon!');
            };
            
            // Loading indicator
            function showLoading(message) {
                let loader = document.getElementById('loadingOverlay');
                if (!loader) {
                    loader = document.createElement('div');
                    loader.id = 'loadingOverlay';
                    loader.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.7);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        z-index: 9999;
                    `;
                    loader.innerHTML = `
                        <div class="spinner" style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px;"></div>
                        <p>${message}</p>
                    `;
                    document.body.appendChild(loader);
                    
                    // Add spin animation
                    const style = document.createElement('style');
                    style.textContent = `
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
            
            function hideLoading() {
                const loader = document.getElementById('loadingOverlay');
                if (loader) {
                    loader.remove();
                }
            }
            
            // Load saved settings
            function loadSavedSettings() {
                // Load security settings
                const savedSecurity = localStorage.getItem('security_settings');
                if (savedSecurity) {
                    const settings = JSON.parse(savedSecurity);
                    document.getElementById('2fa_toggle').checked = settings.twoFactor || false;
                    document.getElementById('session_timeout').checked = settings.sessionTimeout !== false;
                    document.getElementById('ip_restriction').checked = settings.ipRestriction || false;
                    document.getElementById('activity_logging').checked = settings.activityLogging !== false;
                    document.getElementById('login_alerts').checked = settings.loginAlerts !== false;
                }
                
                // Load backup settings
                const savedBackup = localStorage.getItem('backup_settings');
                if (savedBackup) {
                    const settings = JSON.parse(savedBackup);
                    document.getElementById('backup_frequency').value = settings.frequency || 'daily';
                    document.getElementById('backup_time').value = settings.time || '00:00';
                    document.getElementById('retention_period').value = settings.retention || '30';
                }
            }
            
            loadSavedSettings();
        });
    </script>
</body>
</html>