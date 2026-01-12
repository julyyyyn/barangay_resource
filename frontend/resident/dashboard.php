<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) {
    header('Location: ../admin/admin_dashboard.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Fetch donation stats
$donationStats = $conn->prepare("
    SELECT
        COUNT(*) as total_donations,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_donations
    FROM donations
    WHERE user_id = ?
");
$donationStats->execute([$userId]);
$donationStats = $donationStats->fetch();

// Fetch borrow stats
$borrowStats = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_borrows,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active_borrows
    FROM borrow_requests
    WHERE user_id = ?
");
$borrowStats->execute([$userId]);
$borrowStats = $borrowStats->fetch();

// Fetch available resources count
$availableResources = $conn->query("
    SELECT COUNT(*) as count
    FROM resources
    WHERE available_quantity > 0
")->fetch()['count'];

// Fetch recent activities
$activities = $conn->prepare("
    SELECT action_type, description, created_at
    FROM activity_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$activities->execute([$userId]);
$activities = $activities->fetchAll();

// Fetch notifications
$notifications = $conn->prepare("
    SELECT title, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications->execute([$userId]);
$notifications = $notifications->fetchAll();

// Count unread notifications
$notificationCount = $conn->prepare("
    SELECT COUNT(*) as count
    FROM notifications
    WHERE user_id = ? AND is_read = FALSE
");
$notificationCount->execute([$userId]);
$notificationCount = $notificationCount->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Barangay Resource Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../frontend/css/dashboard.css">
</head>
<body>
    <!-- HEADER - UPDATED COLORS -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <div class="logo-icon">BR</div>
                <div class="logo-text">
                    <div class="main">Barangay RMS</div>
                    <div class="sub">Resource Management System</div>
                </div>
            </div>
            
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="donate.php" class="nav-link">
                    <i class="fas fa-hand-holding-heart"></i> Donate
                </a>
                <a href="borrow.php" class="nav-link">
                    <i class="fas fa-exchange-alt"></i> Borrow
                </a>
                <a href="my_activities.php" class="nav-link">
                    <i class="fas fa-history"></i> Activities
                </a>
            </div>
            
            <div class="user-menu">
                <div class="user-greeting">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr(clean($_SESSION['first_name']), 0, 1)); ?>
                    </div>
                    <?php echo clean($_SESSION['first_name']); ?>
                </div>
                <a href="#" onclick="confirmLogout()" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="container">
        <!-- WELCOME BANNER -->
        <section class="welcome-banner animate-fade-in-up">
            <h1>Welcome, <?php echo clean($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>! 👋</h1>
            <p>Manage your donations, borrowing requests, and access community resources in our barangay resource management system.</p>
        </section>

        <!-- STATISTICS -->
        <section class="stats-grid">
            <div class="stat-card animate-fade-in-up" style="animation-delay: 0.1s">
                <div class="label">
                    <i class="fas fa-hand-holding-heart"></i> Total Donations
                </div>
                <div class="value"><?php echo $donationStats['total_donations'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card animate-fade-in-up" style="animation-delay: 0.2s">
                <div class="label">
                    <i class="fas fa-clock"></i> Pending Requests
                </div>
                <div class="value"><?php echo (($donationStats['pending_donations'] ?? 0) + ($borrowStats['pending_borrows'] ?? 0)); ?></div>
            </div>
            
            <div class="stat-card animate-fade-in-up" style="animation-delay: 0.3s">
                <div class="label">
                    <i class="fas fa-exchange-alt"></i> Active Borrows
                </div>
                <div class="value"><?php echo $borrowStats['active_borrows'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card animate-fade-in-up" style="animation-delay: 0.4s">
                <div class="label">
                    <i class="fas fa-boxes"></i> Available Resources
                </div>
                <div class="value"><?php echo $availableResources ?? 0; ?></div>
            </div>
        </section>

        <!-- ACTIVITIES & NOTIFICATIONS -->
        <section class="content-grid">
            <!-- RECENT ACTIVITIES -->
            <div class="card animate-fade-in-up">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Activities</h2>
                    <a href="my_activities.php" class="view-all">View All →</a>
                </div>
                
                <?php if (!empty($activities)): ?>
                    <div class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="title"><?php echo clean($activity['action_type']); ?></div>
                                <div class="desc"><?php echo clean($activity['description']); ?></div>
                                <div class="time">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-calendar-alt"></i>
                        <p>No activities yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="card animate-fade-in-up">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($notificationCount > 0): ?>
                            <span class="badge badge-danger"><?php echo $notificationCount; ?> new</span>
                        <?php endif; ?>
                    </h2>
                    <a href="notifications.php" class="view-all">View All →</a>
                </div>
                
                <?php if (!empty($notifications)): ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <?php if (!$notif['is_read']): ?>
                                    <div class="notification-badge animate-pulse"></div>
                                <?php endif; ?>
                                <div class="title"><?php echo clean($notif['title']); ?></div>
                                <div class="message"><?php echo clean($notif['message']); ?></div>
                                <div class="time">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-bell"></i>
                        <p>No notifications</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- QUICK ACTIONS -->
        <section class="quick-actions">
            <a href="donate.php" class="action-card animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="action-icon">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="action-title">Make a Donation</div>
                <div class="action-desc">Share resources with the community</div>
            </a>
            
            <a href="borrow.php" class="action-card animate-fade-in-up" style="animation-delay: 0.6s">
                <div class="action-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="action-title">Borrow Resources</div>
                <div class="action-desc">Find and request available items</div>
            </a>
            
            <a href="resources.php" class="action-card animate-fade-in-up" style="animation-delay: 0.7s">
                <div class="action-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="action-title">View Resources</div>
                <div class="action-desc">Browse available community items</div>
            </a>
            
            <a href="profile.php" class="action-card animate-fade-in-up" style="animation-delay: 0.8s">
                <div class="action-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="action-title">My Profile</div>
                <div class="action-desc">Update your personal information</div>
            </a>
        </section>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-container">
            <p class="footer-text">Serving Our Community Since 2020</p>
            <p class="footer-text">Barangay Resource Management System</p>
            <p class="copyright">© 2023 Barangay RMS. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function confirmLogout() {
    Swal.fire({
        title: 'Logout Confirmation',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel',
        background: '#0f172a',
        color: '#f8fafc',
        customClass: {
            popup: 'logout-modal',
            confirmButton: 'logout-confirm-btn',
            cancelButton: 'logout-cancel-btn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Logging out...',
                text: 'Please wait',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirect to logout page after a short delay
            setTimeout(() => {
                window.location.href = '../../frontend/index.html';
            }, 1000);
        }
    });
}

// Alternative: Using native confirm dialog (no CDN needed)
function confirmLogoutSimple() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = '../../frontend/index.html';
    }
}

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading class to body initially
            document.body.classList.add('loading');
            
            // Simulate loading delay (remove in production)
            setTimeout(() => {
                document.body.classList.remove('loading');
            }, 500);
            
            // Add click animation to cards
            document.querySelectorAll('.stat-card, .action-card, .activity-item, .notification-item').forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
            
            // Mark notification as read when clicked
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.addEventListener('click', function() {
                    this.classList.remove('unread');
                    const badge = this.querySelector('.notification-badge');
                    if (badge) badge.remove();
                    
                    // Update notification count
                    const badgeCount = document.querySelector('.badge-danger');
                    if (badgeCount) {
                        let count = parseInt(badgeCount.textContent);
                        count--;
                        if (count > 0) {
                            badgeCount.textContent = count + ' new';
                        } else {
                            badgeCount.remove();
                        }
                    }
                });
            });
            
            // Update greeting based on time of day
            function updateGreeting() {
                const hour = new Date().getHours();
                const welcomeElement = document.querySelector('.welcome-banner h1');
                let greeting = 'Welcome';
                
                if (hour < 12) greeting = 'Good morning';
                else if (hour < 17) greeting = 'Good afternoon';
                else greeting = 'Good evening';
                
                const name = '<?php echo clean($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>';
                welcomeElement.innerHTML = `${greeting}, ${name}! 👋`;
            }
            
            updateGreeting();
            
            // Add hover effect to cards
            document.querySelectorAll('.stat-card, .card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)';
                });
            });
            
            // Add smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            // Update notification badge animation
            const notificationBadge = document.querySelector('.badge-danger');
            if (notificationBadge) {
                notificationBadge.style.animation = 'pulse 2s infinite';
            }
            
            // Add floating scroll to top button
            const scrollTopBtn = document.createElement('button');
            scrollTopBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
            scrollTopBtn.style.cssText = `
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 50px;
                height: 50px;
                background: #3b82f6;
                color: white;
                border: none;
                border-radius: 50%;
                font-size: 20px;
                cursor: pointer;
                box-shadow: 0 4px 6px rgba(59, 130, 246, 0.5);
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.3s ease;
                z-index: 100;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            document.body.appendChild(scrollTopBtn);
            
            // Show/hide scroll button
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    scrollTopBtn.style.opacity = '1';
                    scrollTopBtn.style.transform = 'translateY(0)';
                } else {
                    scrollTopBtn.style.opacity = '0';
                    scrollTopBtn.style.transform = 'translateY(20px)';
                }
            });
            
            // Scroll to top
            scrollTopBtn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            
            // Add tooltip to action cards
            document.querySelectorAll('.action-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const title = this.querySelector('.action-title').textContent;
                    // You could add tooltip functionality here
                });
            });
        });
    </script>
</body>
</html>