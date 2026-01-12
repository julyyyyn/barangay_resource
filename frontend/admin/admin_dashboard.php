<?php
require_once '../resident/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../login.html');
    exit();
}

$conn = getDBConnection();

// Fetch total resources
$totalResources = $conn->query("SELECT COUNT(*) as count FROM resources")->fetch()['count'];

// Fetch active borrowings (approved borrow requests)
$activeBorrowings = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE status = 'approved'")->fetch()['count'];

// Fetch total donations
$totalDonations = $conn->query("SELECT COUNT(*) as count FROM donations")->fetch()['count'];

// Fetch pending requests
$pendingDonations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status = 'pending'")->fetch()['count'];
$pendingBorrows = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE status = 'pending'")->fetch()['count'];
$pendingRequests = $pendingDonations + $pendingBorrows;

// Fetch pending verifications - USING role_id and status
// role_id = 2 (residents) with status = 'Pending'
$pendingVerifications = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND status = 'Pending'")->fetch()['count'];

// Fetch total residents (verified)
$totalResidents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND status = 'Active'")->fetch()['count'];

// Fetch recent requests
$recentRequests = $conn->query("
    SELECT 'donation' as type, d.id, d.item_name as item, d.status, d.created_at, u.first_name, u.last_name
    FROM donations d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.status = 'pending'
    UNION ALL
    SELECT 'borrow' as type, br.id, r.name as item, br.status, br.created_at, u.first_name, u.last_name
    FROM borrow_requests br
    JOIN resources r ON br.resource_id = r.id
    JOIN users u ON br.user_id = u.user_id
    WHERE br.status = 'pending'
    ORDER BY created_at DESC
    LIMIT 8
")->fetchAll();

// Fetch resource summary
$resourceSummary = $conn->query("
    SELECT 
        name,
        available_quantity,
        total_quantity
    FROM resources 
    ORDER BY name 
    LIMIT 5
")->fetchAll();

// Fetch overdue returns
$overdueReturns = $conn->query("
    SELECT COUNT(*) as count 
    FROM borrow_requests 
    WHERE status = 'approved' 
    AND return_date < CURDATE()
")->fetch()['count'];

// Check if we need a roles table (optional)
$hasRolesTable = $conn->query("SHOW TABLES LIKE 'roles'")->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Barangay Resource Management</title>
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
                    <a href="admin_dashboard.php" class="nav-link active">
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
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
            
            <div class="logout-section">
                <a href="index.html" class="logout-btn">
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
                        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                        <p class="header-subtitle">System-wide overview and control panel for administrative tasks</p>
                    </div>
                    
                    <div class="header-controls">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search requests, residents, or resources...">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="notification-badge">
                            <i class="fas fa-bell"></i>
                            <span class="badge"><?php echo $pendingRequests + $pendingVerifications; ?></span>
                        </div>
                        <div class="theme-toggle" id="themeToggle">
                            <i class="fas fa-moon"></i>
                            <span class="toggle-label">Dark Mode</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ALERT BANNER FOR PENDING ITEMS -->
            <?php if ($pendingRequests > 0 || $pendingVerifications > 0 || $overdueReturns > 0): ?>
            <div class="alert-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <h3>Attention Required</h3>
                    <p>
                        <?php 
                            $alerts = [];
                            if ($pendingRequests > 0) $alerts[] = "{$pendingRequests} pending requests";
                            if ($pendingVerifications > 0) $alerts[] = "{$pendingVerifications} pending verifications";
                            if ($overdueReturns > 0) $alerts[] = "{$overdueReturns} overdue returns";
                            echo implode(", ", $alerts);
                        ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- STATISTICS GRID -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Resources</div>
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalResources; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        System Inventory
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-title">Active Borrowings</div>
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $activeBorrowings; ?></div>
                    <div class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        Currently in use
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Donations</div>
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalDonations; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-users"></i>
                        Community contributions
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Pending Requests</div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pendingRequests; ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-exclamation-circle"></i>
                        Requires attention
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div class="stat-title">Overdue Returns</div>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $overdueReturns; ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-exclamation-triangle"></i>
                        Past return date
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Pending Verifications</div>
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pendingVerifications; ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-user-clock"></i>
                        Awaiting approval
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-title">Total Residents</div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalResidents; ?></div>
                    <div class="stat-trend positive">
                        <i class="fas fa-user-check"></i>
                        Verified residents
                    </div>
                </div>
            </div>

            <!-- CONTENT GRID -->
            <div class="content-grid">
                <!-- LEFT COLUMN -->
                <div class="left-column">
                    <!-- RECENT REQUESTS PANEL -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-history"></i> Recent Pending Requests</h2>
                            <a href="manage_requests.php" class="view-all">View All →</a>
                        </div>
                        
                        <div class="requests-list">
                            <?php if (!empty($recentRequests)): ?>
                                <?php foreach ($recentRequests as $request): 
                                    $timeAgo = getTimeAgo($request['created_at']);
                                ?>
                                    <div class="request-item <?php echo $request['type']; ?>">
                                        <div class="request-header">
                                            <div class="request-user">
                                                <?php echo clean($request['first_name'] . ' ' . $request['last_name']); ?>
                                            </div>
                                            <span class="request-type <?php echo $request['type']; ?>">
                                                <?php echo ucfirst($request['type']); ?>
                                            </span>
                                        </div>
                                        <div class="request-details">
                                            <?php echo clean($request['item']); ?>
                                        </div>
                                        <div class="request-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?>
                                            (<?php echo $timeAgo; ?>)
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: var(--admin-text-secondary);">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
                                    <p>No pending requests</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="right-column">
                    <!-- QUICK ACTIONS PANEL -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        </div>
                        
                        <div class="actions-grid">
                            <a href="manage_requests.php" class="action-btn">
                                <i class="fas fa-tasks"></i>
                                <span>Manage Requests</span>
                            </a>
                            <a href="add_resource.php" class="action-btn">
                                <i class="fas fa-plus-circle"></i>
                                <span>Add Resource</span>
                            </a>
                            <a href="reports.php" class="action-btn">
                                <i class="fas fa-chart-bar"></i>
                                <span>Generate Report</span>
                            </a>
                            <a href="residents.php" class="action-btn">
                                <i class="fas fa-user-plus"></i>
                                <span>Verify Users</span>
                            </a>
                            <a href="resources.php" class="action-btn">
                                <i class="fas fa-boxes"></i>
                                <span>Inventory</span>
                            </a>
                            <a href="settings.php" class="action-btn">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                        </div>
                    </div>

                    <!-- RESOURCE SUMMARY PANEL -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-chart-pie"></i> Resource Summary</h2>
                            <a href="resources.php" class="view-all">View All →</a>
                        </div>
                        
                        <div class="resource-list">
                            <?php if (!empty($resourceSummary)): ?>
                                <?php foreach ($resourceSummary as $resource): ?>
                                    <div class="resource-item">
                                        <div class="resource-name">
                                            <?php echo clean($resource['name']); ?>
                                        </div>
                                        <div class="resource-stats">
                                            <span class="resource-available">
                                                <?php echo $resource['available_quantity']; ?> available
                                            </span>
                                            <span class="resource-total">
                                                / <?php echo $resource['total_quantity']; ?> total
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 20px; color: var(--admin-text-secondary);">
                                    <i class="fas fa-box-open"></i>
                                    <p>No resources found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <footer class="admin-footer">
                <p>Barangay Resource Management System | Admin Dashboard v2.0</p>
                <p>© <?php echo date('Y'); ?> All rights reserved | Last updated: <?php echo date('F j, Y g:i A'); ?></p>
            </footer>
        </main>
    </div>

    <script>
        // Dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                
                if (searchTerm.length > 2) {
                    // In a real app, this would trigger an AJAX search
                    console.log('Searching for:', searchTerm);
                    // You could implement AJAX search here
                }
            });

            // Filter requests by type
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    
                    // Remove active class from all buttons
                    filterButtons.forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Filter request items
                    const requestItems = document.querySelectorAll('.request-item');
                    requestItems.forEach(item => {
                        if (filter === 'all' || item.classList.contains(filter)) {
                            item.style.display = 'block';
                            setTimeout(() => {
                                item.style.opacity = '1';
                                item.style.transform = 'translateX(0)';
                            }, 10);
                        } else {
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(-10px)';
                            setTimeout(() => {
                                item.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });

            // Add hover animations to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                    this.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.4)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 30px var(--admin-shadow)';
                });
            });

            // Notification badge animation
            const notificationBadge = document.querySelector('.notification-badge');
            if (notificationBadge) {
                notificationBadge.addEventListener('click', function() {
                    // Show notification dropdown
                    // In a real app, this would show a dropdown with notifications
                    console.log('Notifications clicked');
                });
            }

            // Responsive sidebar toggle
            const sidebarToggle = document.createElement('div');
            sidebarToggle.className = 'sidebar-toggle';
            sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
            sidebarToggle.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                background: var(--admin-accent);
                color: white;
                border-radius: 50%;
                display: none;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 1000;
                box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            `;
            
            document.body.appendChild(sidebarToggle);
            
            sidebarToggle.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                sidebar.classList.toggle('collapsed');
            });

            // Show/hide sidebar toggle on resize
            function checkResponsive() {
                if (window.innerWidth <= 992) {
                    sidebarToggle.style.display = 'flex';
                } else {
                    sidebarToggle.style.display = 'none';
                }
            }
            
            checkResponsive();
            window.addEventListener('resize', checkResponsive);
        });

        // Helper function for time ago
        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) {
                return 'just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes}m ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours}h ago`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days}d ago`;
            } else {
                return date.toLocaleDateString();
            }
        }

        // Theme toggle functionality
const themeToggle = document.getElementById('themeToggle');
const themeIcon = themeToggle.querySelector('i');
const themeLabel = themeToggle.querySelector('.toggle-label');

// Check for saved theme preference or default to light
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
    </script>
</body>
</html>

<?php
// Helper function for time ago
function getTimeAgo($date) {
    $timestamp = strtotime($date);
    $currentTime = time();
    $timeDiff = $currentTime - $timestamp;
    
    if ($timeDiff < 60) {
        return 'just now';
    } elseif ($timeDiff < 3600) {
        $minutes = floor($timeDiff / 60);
        return $minutes . 'm ago';
    } elseif ($timeDiff < 86400) {
        $hours = floor($timeDiff / 3600);
        return $hours . 'h ago';
    } elseif ($timeDiff < 604800) {
        $days = floor($timeDiff / 86400);
        return $days . 'd ago';
    } else {
        return date('M d', $timestamp);
    }
}

