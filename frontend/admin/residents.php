<?php
require_once '../resident/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../login.html');
    exit();
}

$conn = getDBConnection();

// Fetch residents with their statistics
$residents = $conn->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM borrow_requests br WHERE br.user_id = u.user_id AND br.status = 'approved') as total_borrowed,
           (SELECT COUNT(*) FROM donations d WHERE d.user_id = u.user_id AND d.status = 'approved') as total_donated
    FROM users u 
    WHERE role_id = 2 
    ORDER BY u.first_name
")->fetchAll();

// Calculate statistics
$totalResidents = count($residents);
$activeResidents = count(array_filter($residents, function($r) {
    return $r['status'] == 'Active';
}));
$pendingResidents = count(array_filter($residents, function($r) {
    return $r['status'] == 'Pending';
}));
$verifiedResidents = $activeResidents;
$totalBorrowed = array_sum(array_column($residents, 'total_borrowed'));
$totalDonated = array_sum(array_column($residents, 'total_donated'));

// Handle status update
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $action = $_GET['action'];
    
    if ($action == 'verify') {
        $conn->prepare("UPDATE users SET status = 'Active' WHERE user_id = ?")->execute([$user_id]);
        $_SESSION['success_message'] = "Resident verified successfully!";
    } elseif ($action == 'reject') {
        $conn->prepare("UPDATE users SET status = 'Rejected' WHERE user_id = ?")->execute([$user_id]);
        $_SESSION['success_message'] = "Resident rejected successfully!";
    } elseif ($action == 'deactivate') {
        $conn->prepare("UPDATE users SET status = 'Inactive' WHERE user_id = ?")->execute([$user_id]);
        $_SESSION['success_message'] = "Resident deactivated successfully!";
    } elseif ($action == 'activate') {
        $conn->prepare("UPDATE users SET status = 'Active' WHERE user_id = ?")->execute([$user_id]);
        $_SESSION['success_message'] = "Resident activated successfully!";
    }
    
    header('Location: residents.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Residents | BRMS Admin</title>
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
                    <a href="residents.php" class="nav-link active">
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
                    <a href="settings.php" class="nav-link">
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
                        <h1><i class="fas fa-users"></i> Manage Residents</h1>
                        <p class="header-subtitle">View, verify, and manage barangay residents</p>
                    </div>
                    
                    <div class="header-controls">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search residents...">
                            <i class="fas fa-search"></i>
                        </div>
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

            <!-- STATISTICS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Residents</div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalResidents; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-user-friends"></i>
                        Registered users
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-title">Verified Residents</div>
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $verifiedResidents; ?></div>
                    <div class="stat-trend positive">
                        <i class="fas fa-check-circle"></i>
                        Active accounts
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Pending Verification</div>
                        <div class="stat-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pendingResidents; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-clock"></i>
                        Awaiting approval
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Community Activity</div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalBorrowed + $totalDonated; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-exchange-alt"></i>
                        Total transactions
                    </div>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="filters-section">
                <div class="filter-group">
                    <span class="filter-label">Filter by:</span>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                    <button onclick="resetFilters()" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>
            </div>

            <!-- RESIDENTS TABLE -->
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-list"></i> Resident Directory</h2>
                    <span class="table-count"><?php echo $totalResidents; ?> residents</span>
                </div>
                
                <div class="table-responsive">
                    <table class="residents-table">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Activity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($residents as $resident): 
                                $fullName = clean($resident['first_name'] . ' ' . $resident['last_name']);
                                $address = clean($resident['barangay'] . ', ' . $resident['city'] . ', ' . $resident['province']);
                                $purok = $resident['purok'] ? 'Purok ' . $resident['purok'] : '';
                            ?>
                                <tr data-status="<?php echo $resident['status']; ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($fullName)); ?>">
                                    <td>
                                        <div class="resident-info">
                                            <div class="resident-avatar">
                                                <div class="avatar-placeholder">
                                                    <?php echo strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <strong><?php echo $fullName; ?></strong>
                                                <div class="resident-email">
                                                    <?php echo clean($resident['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <div class="contact-item">
                                                <i class="fas fa-phone"></i>
                                                <span><?php echo clean($resident['contact_no']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="address-info">
                                            <div class="address-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo $address; ?></span>
                                            </div>
                                            <?php if ($purok): ?>
                                                <div class="address-subitem">
                                                    <i class="fas fa-home"></i>
                                                    <span><?php echo $purok; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="activity-info">
                                            <div class="activity-item">
                                                <span class="activity-label">Borrowed:</span>
                                                <span class="activity-value"><?php echo $resident['total_borrowed']; ?></span>
                                            </div>
                                            <div class="activity-item">
                                                <span class="activity-label">Donated:</span>
                                                <span class="activity-value"><?php echo $resident['total_donated']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($resident['status'] == 'Active'): ?>
                                            <span class="status-badge active">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php elseif ($resident['status'] == 'Pending'): ?>
                                            <span class="status-badge pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif ($resident['status'] == 'Inactive'): ?>
                                            <span class="status-badge inactive">
                                                <i class="fas fa-ban"></i> Inactive
                                            </span>
                                        <?php elseif ($resident['status'] == 'Rejected'): ?>
                                            <span class="status-badge rejected">
                                                <i class="fas fa-times-circle"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge unknown">
                                                <i class="fas fa-question-circle"></i> <?php echo $resident['status']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($resident['status'] == 'Pending'): ?>
                                                <a href="?action=verify&user_id=<?php echo $resident['user_id']; ?>" 
                                                   class="btn-action verify" 
                                                   title="Verify Resident"
                                                   onclick="return confirm('Verify this resident? They will gain access to the system.')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="?action=reject&user_id=<?php echo $resident['user_id']; ?>" 
                                                   class="btn-action reject" 
                                                   title="Reject Application"
                                                   onclick="return confirm('Reject this resident application? This action cannot be undone.')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php elseif ($resident['status'] == 'Active'): ?>
                                                <a href="?action=deactivate&user_id=<?php echo $resident['user_id']; ?>" 
                                                   class="btn-action deactivate" 
                                                   title="Deactivate Account"
                                                   onclick="return confirm('Deactivate this resident account? They will lose access to the system.')">
                                                    <i class="fas fa-user-slash"></i>
                                                </a>
                                            <?php elseif ($resident['status'] == 'Inactive'): ?>
                                                <a href="?action=activate&user_id=<?php echo $resident['user_id']; ?>" 
                                                   class="btn-action activate" 
                                                   title="Activate Account"
                                                   onclick="return confirm('Activate this resident account? They will regain access to the system.')">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button onclick="viewResident(<?php echo $resident['user_id']; ?>)" 
                                                    class="btn-action view" 
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($residents)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <div class="empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <h3>No Residents Found</h3>
                                            <p>No residents are currently registered in the system.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER -->
            <footer class="admin-footer">
                <p>Barangay Resource Management System | Resident Management v2.0</p>
                <p>© <?php echo date('Y'); ?> All rights reserved | Last updated: <?php echo date('F j, Y g:i A'); ?></p>
            </footer>
        </main>
    </div>

    <style>
        /* Residents Specific Styles */
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--admin-border);
            box-shadow: 0 2px 8px var(--admin-shadow);
        }
        
        .dark-mode .filters-section {
            background: rgba(30, 41, 59, 0.8);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-label {
            font-weight: 500;
            color: var(--admin-text);
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: var(--admin-bg-light);
            color: var(--admin-text);
            min-width: 180px;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--admin-accent);
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .residents-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .residents-table thead {
            background: var(--admin-bg-light);
        }
        
        .residents-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--admin-text);
            border-bottom: 2px solid var(--admin-border);
        }
        
        .residents-table td {
            padding: 15px;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
        }
        
        .residents-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        /* Resident Info */
        .resident-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .resident-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--admin-accent), var(--admin-success));
            flex-shrink: 0;
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        
        .resident-email {
            font-size: 13px;
            color: var(--admin-text-secondary);
            margin-top: 2px;
        }
        
        /* Contact Info */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .contact-item i {
            color: var(--admin-accent);
            font-size: 14px;
            width: 16px;
        }
        
        .contact-item span {
            font-size: 14px;
            color: var(--admin-text);
        }
        
        /* Address Info */
        .address-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .address-item, .address-subitem {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .address-item i {
            color: var(--admin-accent);
            font-size: 14px;
            width: 16px;
            margin-top: 2px;
        }
        
        .address-subitem i {
            color: var(--admin-text-secondary);
            font-size: 12px;
            width: 16px;
            margin-top: 1px;
        }
        
        .address-item span, .address-subitem span {
            font-size: 14px;
            color: var(--admin-text);
            line-height: 1.4;
        }
        
        .address-subitem span {
            font-size: 12px;
            color: var(--admin-text-secondary);
        }
        
        /* Activity Info */
        .activity-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        
        .activity-label {
            font-size: 12px;
            color: var(--admin-text-secondary);
        }
        
        .activity-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--admin-text);
            background: var(--admin-bg-light);
            padding: 3px 8px;
            border-radius: 12px;
            min-width: 24px;
            text-align: center;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
        }
        
        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .status-badge.unknown {
            background: rgba(156, 163, 175, 0.1);
            color: #9ca3af;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
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
            text-decoration: none;
        }
        
        .btn-action.verify {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .btn-action.verify:hover {
            background: var(--admin-success);
            color: white;
        }
        
        .btn-action.reject {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .btn-action.reject:hover {
            background: var(--admin-danger);
            color: white;
        }
        
        .btn-action.activate {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .btn-action.activate:hover {
            background: var(--admin-success);
            color: white;
        }
        
        .btn-action.deactivate {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .btn-action.deactivate:hover {
            background: var(--admin-danger);
            color: white;
        }
        
        .btn-action.view {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
        }
        
        .btn-action.view:hover {
            background: var(--admin-accent);
            color: white;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
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
        
        .table-count {
            background: var(--admin-accent);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--admin-text-secondary);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--admin-text);
        }
        
        .empty-state p {
            font-size: 16px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .alert-banner.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.2) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .alert-banner.success i {
            color: var(--admin-success);
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
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.residents-table tbody tr');
                
                rows.forEach(row => {
                    const residentName = row.dataset.name || '';
                    if (residentName.includes(searchTerm) || searchTerm === '') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Filter functionality
            const statusFilter = document.getElementById('statusFilter');
            
            function applyFilters() {
                const status = statusFilter.value;
                const rows = document.querySelectorAll('.residents-table tbody tr');
                
                rows.forEach(row => {
                    const rowStatus = row.dataset.status || '';
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    const residentName = row.dataset.name || '';
                    
                    const statusMatch = !status || rowStatus === status;
                    const searchMatch = !searchTerm || residentName.includes(searchTerm);
                    
                    if (statusMatch && searchMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            statusFilter.addEventListener('change', applyFilters);
            
            window.viewResident = function(userId) {
                alert('Viewing resident details for ID: ' + userId + '\n\nIn a complete implementation, this would show:\n- Complete profile information\n- Borrow history\n- Donation history\n- Activity timeline\n- Contact information');
            };
            
            window.resetFilters = function() {
                statusFilter.value = '';
                searchInput.value = '';
                applyFilters();
            };
            
            // Add confirmation to all action links
            const actionLinks = document.querySelectorAll('.btn-action[href*="action="]');
            actionLinks.forEach(link => {
                if (!link.onclick) {
                    link.addEventListener('click', function(e) {
                        if (!confirm('Are you sure you want to perform this action?')) {
                            e.preventDefault();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>