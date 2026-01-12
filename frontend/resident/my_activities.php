<?php
require_once 'config.php';
requireLogin();

if (isAdmin()) {
    header('Location: ../admin/admin_dashboard.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Fetch user's donations
$donations = $conn->prepare("
    SELECT d.*, r.name as resource_name
    FROM donations d
    LEFT JOIN resources r ON d.resource_id = r.id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
");
$donations->execute([$userId]);
$donations = $donations->fetchAll();

// Fetch user's borrow requests
$borrowRequests = $conn->prepare("
    SELECT br.*, r.name as resource_name
    FROM borrow_requests br
    JOIN resources r ON br.resource_id = r.id
    WHERE br.user_id = ?
    ORDER BY br.created_at DESC
");
$borrowRequests->execute([$userId]);
$borrowRequests = $borrowRequests->fetchAll();

// Fetch user's activity logs - combining donations, borrows, and returns
$activities = $conn->prepare("
    (SELECT 
        'donation' as type,
        d.id,
        CONCAT('Donated ', d.item_name, ' (', d.quantity, ' items)') as description,
        d.status,
        d.created_at,
        d.photo,
        NULL as return_date
     FROM donations d
     WHERE d.user_id = ?
     AND d.status != 'Rejected')
     
     UNION ALL
     
     (SELECT 
        'borrow' as type,
        br.id,
        CONCAT('Borrowed ', r.name, ' (', br.quantity, ' items)') as description,
        br.status,
        br.created_at,
        NULL as photo,
        br.return_date
     FROM borrow_requests br
     JOIN resources r ON br.resource_id = r.id
     WHERE br.user_id = ?
     AND br.status != 'Rejected')
     
     UNION ALL
     
     (SELECT 
        'return' as type,
        br.id,
        CONCAT('Returned ', r.name, ' (', br.quantity, ' items)') as description,
        'Returned' as status,
        br.created_at,
        NULL as photo,
        NULL as return_date
     FROM borrow_requests br
     JOIN resources r ON br.resource_id = r.id
     WHERE br.user_id = ?
     AND br.status = 'Returned')
     
     ORDER BY created_at DESC
     LIMIT 50
");
$activities->execute([$userId, $userId, $userId]);
$activities = $activities->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Activities - Barangay Resource Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* RESET AND BASE */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8fafc;
        color: #1e293b;
        line-height: 1.6;
    }
    
    /* HEADER - MATCHING DASHBOARD */
    .navbar {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        color: white;
        padding: 0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    .nav-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 70px;
    }
    
    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .logo-icon {
        width: 40px;
        height: 40px;
        background: #60a5fa;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
        font-size: 18px;
        box-shadow: 0 4px 6px rgba(96, 165, 250, 0.3);
    }
    
    .logo-text {
        display: flex;
        flex-direction: column;
    }
    
    .logo-text .main {
        font-size: 20px;
        font-weight: 700;
        color: white;
        line-height: 1;
    }
    
    .logo-text .sub {
        font-size: 12px;
        color: #dbeafe;
        margin-top: 2px;
    }
    
    .nav-links {
        display: flex;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        padding: 4px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
    }
    
    .nav-link {
        color: #e2e8f0;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .nav-link:hover {
        color: white;
        background: rgba(255, 255, 255, 0.15);
    }
    
    .nav-link.active {
        color: white;
        background: #3b82f6;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }
    
    .user-menu {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .user-greeting {
        font-size: 14px;
        color: #e2e8f0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        font-size: 14px;
    }
    
    .logout-btn {
        background: #ef4444;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    
    .logout-btn:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
    }
    
    /* MAIN CONTAINER */
    .container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 30px 1rem;
        min-height: calc(100vh - 200px);
    }
    
    /* PAGE HEADER */
    .page-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        color: white;
        padding: 40px;
        border-radius: 16px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(30, 58, 138, 0.2);
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    }
    
    .page-header h1 {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .page-header p {
        color: #dbeafe;
        font-size: 16px;
        max-width: 700px;
        line-height: 1.6;
    }
    
    /* CONTENT GRID */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    @media (min-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr 2fr;
        }
    }
    
    /* ACTIVITY STATS CARD */
    .stats-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .stats-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .stats-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .stats-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        font-size: 24px;
        box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
    }
    
    /* STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .stat-item {
        padding: 25px;
        background: #f8fafc;
        border-radius: 12px;
        border-left: 4px solid;
        transition: all 0.3s ease;
    }
    
    .stat-item:hover {
        background: #f1f5f9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .stat-item.donations {
        border-left-color: #3b82f6;
    }
    
    .stat-item.borrows {
        border-left-color: #10b981;
    }
    
    .stat-item.pending {
        border-left-color: #f59e0b;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
    }
    
    .stat-label {
        font-size: 14px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }
    
    .stat-icon {
        float: right;
        font-size: 24px;
        color: #94a3b8;
    }
    
    /* ACTIVITIES CARD */
    .activities-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .activities-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .activities-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .activities-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0ea5e9;
        font-size: 24px;
        box-shadow: 0 4px 6px rgba(14, 165, 233, 0.2);
    }
    
    /* FILTER TABS */
    .filter-tabs {
        display: flex;
        gap: 8px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 8px;
    }
    
    .filter-tab {
        padding: 8px 16px;
        border-radius: 6px;
        background: transparent;
        border: none;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        color: #64748b;
        transition: all 0.2s;
    }
    
    .filter-tab:hover {
        color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
    }
    
    .filter-tab.active {
        color: white;
        background: #3b82f6;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }
    
    /* ACTIVITIES LIST */
    .activities-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
        max-height: 600px;
        overflow-y: auto;
        padding-right: 10px;
    }
    
    .activity-item {
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
        transition: all 0.3s ease;
        border-left: 4px solid;
        position: relative;
    }
    
    .activity-item:hover {
        background: #f1f5f9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .activity-item.donation {
        border-left-color: #3b82f6;
    }
    
    .activity-item.borrow {
        border-left-color: #10b981;
    }
    
    .activity-item.return {
        border-left-color: #8b5cf6;
    }
    
    .activity-item::before {
        content: '';
        position: absolute;
        top: 50%;
        left: -8px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        transform: translateY(-50%);
    }
    
    .activity-item.donation::before {
        background: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
    }
    
    .activity-item.borrow::before {
        background: #10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
    }
    
    .activity-item.return::before {
        background: #8b5cf6;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2);
    }
    
    .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .activity-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .activity-type {
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .activity-type.donation {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .activity-type.borrow {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .activity-type.return {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }
    
    .activity-status {
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: 600;
    }
    
    .activity-status.pending {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }
    
    .activity-status.approved {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }
    
    .activity-status.returned {
        background: rgba(139, 92, 246, 0.1);
        color: #7c3aed;
    }
    
    .activity-status.rejected {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }
    
    .activity-details {
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 8px;
    }
    
    .activity-time {
        font-size: 12px;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 40px;
        color: #94a3b8;
        font-size: 16px;
        background: #f8fafc;
        border-radius: 12px;
        border: 2px dashed #e2e8f0;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 20px;
        color: #cbd5e1;
    }
    
    .empty-state p {
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    /* FOOTER */
    footer {
        background: #1e293b;
        color: white;
        padding: 30px 0;
        margin-top: 60px;
    }
    
    .footer-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 1rem;
        text-align: center;
    }
    
    .footer-text {
        font-size: 14px;
        color: #cbd5e1;
        margin-bottom: 4px;
    }
    
    .copyright {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 8px;
    }
    
    /* RESPONSIVE DESIGN */
    @media (max-width: 768px) {
        .nav-container {
            flex-direction: column;
            height: auto;
            padding: 16px;
            gap: 16px;
        }
        
        .nav-links {
            order: 3;
            width: 100%;
            justify-content: center;
            margin-top: 16px;
        }
        
        .user-menu {
            order: 2;
            flex-direction: column;
        }
        
        .page-header {
            padding: 30px 20px;
        }
        
        .page-header h1 {
            font-size: 24px;
        }
        
        .stats-card,
        .activities-card {
            padding: 30px 20px;
        }
        
        .content-grid {
            gap: 20px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .activity-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .activities-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .filter-tabs {
            align-self: flex-start;
        }
    }
    
    @media (max-width: 480px) {
        .nav-links {
            flex-wrap: wrap;
        }
        
        .nav-link {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .page-header h1 {
            font-size: 20px;
        }
    }
    
    /* CUSTOM SCROLLBAR */
    .activities-list::-webkit-scrollbar {
        width: 6px;
    }
    
    .activities-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .activities-list::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }
    
    .activities-list::-webkit-scrollbar-thumb:hover {
        background: #a1a1a1;
    }
</style>
</head>
<body>
    <!-- HEADER -->
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="donate.php" class="nav-link">
                    <i class="fas fa-hand-holding-heart"></i> Donate
                </a>
                <a href="borrow.php" class="nav-link">
                    <i class="fas fa-exchange-alt"></i> Borrow
                </a>
                <a href="my_activities.php" class="nav-link active">
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
                <a href="/barangay_resource/backend/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1><i class="fas fa-history"></i> My Activities</h1>
            <p>Track your donations, borrow requests, and all transactions with the barangay resource system.</p>
        </div>

        <div class="content-grid">
            <!-- STATISTICS CARD -->
            <div class="stats-card">
                <div class="stats-header">
                    <div class="stats-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h2>Activity Summary</h2>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-item donations">
                        <div class="stat-value">
                            <?php 
                                $totalDonations = 0;
                                $pendingDonations = 0;
                                foreach ($donations as $donation) {
                                    $totalDonations++;
                                    if ($donation['status'] == 'Pending') $pendingDonations++;
                                }
                                echo $totalDonations;
                            ?>
                        </div>
                        <div class="stat-label">Total Donations</div>
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                    </div>
                    
                    <div class="stat-item borrows">
                        <div class="stat-value">
                            <?php 
                                $totalBorrows = 0;
                                $pendingBorrows = 0;
                                $returnedBorrows = 0;
                                foreach ($borrowRequests as $request) {
                                    $totalBorrows++;
                                    if ($request['status'] == 'Pending') $pendingBorrows++;
                                    if ($request['status'] == 'Returned') $returnedBorrows++;
                                }
                                echo $totalBorrows;
                            ?>
                        </div>
                        <div class="stat-label">Borrow Requests</div>
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                    
                    <div class="stat-item pending">
                        <div class="stat-value">
                            <?php echo $pendingDonations + $pendingBorrows; ?>
                        </div>
                        <div class="stat-label">Pending Actions</div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $returnedBorrows; ?>
                        </div>
                        <div class="stat-label">Items Returned</div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIVITIES CARD -->
            <div class="activities-card">
                <div class="activities-header">
                    <div>
                        <div class="activities-icon">
                            <i class="fas fa-stream"></i>
                        </div>
                        <h2>Recent Activities</h2>
                    </div>
                    
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterActivities('all')">All</button>
                        <button class="filter-tab" onclick="filterActivities('donation')">Donations</button>
                        <button class="filter-tab" onclick="filterActivities('borrow')">Borrows</button>
                        <button class="filter-tab" onclick="filterActivities('return')">Returns</button>
                    </div>
                </div>
                
                <div class="activities-list" id="activitiesList">
                    <?php if (empty($activities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No activities yet</p>
                            <p>Start by donating or borrowing resources</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): 
                            $typeClass = $activity['type'];
                            $statusClass = strtolower($activity['status']);
                            $timeAgo = getTimeAgo($activity['created_at']);
                        ?>
                            <div class="activity-item <?php echo $typeClass; ?>" data-type="<?php echo $typeClass; ?>">
                                <div class="activity-header">
                                    <div class="activity-title">
                                        <span class="activity-type <?php echo $typeClass; ?>">
                                            <?php echo ucfirst($typeClass); ?>
                                        </span>
                                        <?php echo clean($activity['description']); ?>
                                    </div>
                                    <span class="activity-status <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="activity-details">
                                    <?php if ($activity['type'] == 'borrow' && $activity['return_date']): ?>
                                        <i class="far fa-calendar-alt"></i> 
                                        Return by: <?php echo date('M d, Y', strtotime($activity['return_date'])); ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($activity['type'] == 'donation' && $activity['photo']): ?>
                                        <i class="fas fa-camera"></i> Photo included
                                    <?php endif; ?>
                                </div>
                                
                                <div class="activity-time">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?>
                                    (<?php echo $timeAgo; ?>)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-container">
            <p class="footer-text">Serving Our Community Since 2020</p>
            <p class="footer-text">Barangay Resource Management System</p>
            <p class="copyright">© 2023 Barangay RMS. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter activities function
            window.filterActivities = function(type) {
                const tabs = document.querySelectorAll('.filter-tab');
                const activities = document.querySelectorAll('.activity-item');
                
                // Update active tab
                tabs.forEach(tab => {
                    if (tab.textContent.toLowerCase().includes(type)) {
                        tab.classList.add('active');
                    } else {
                        tab.classList.remove('active');
                    }
                });
                
                // Filter activities
                activities.forEach(activity => {
                    if (type === 'all' || activity.getAttribute('data-type') === type) {
                        activity.style.display = 'block';
                        setTimeout(() => {
                            activity.style.opacity = '1';
                            activity.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        activity.style.opacity = '0';
                        activity.style.transform = 'translateY(10px)';
                        setTimeout(() => {
                            activity.style.display = 'none';
                        }, 300);
                    }
                });
                
                // Show empty state if no activities
                const visibleActivities = Array.from(activities).filter(activity => 
                    activity.style.display !== 'none' || type === 'all'
                );
                
                const emptyState = document.querySelector('.empty-state');
                if (emptyState && visibleActivities.length > 0 && type !== 'all') {
                    emptyState.style.display = 'none';
                }
            };

            // Add animations to activity items
            const activityItems = document.querySelectorAll('.activity-item');
            activityItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add hover effects
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
            });

            // Add click animation to filter tabs
            const filterTabs = document.querySelectorAll('.filter-tab');
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    filterTabs.forEach(t => t.classList.remove('active'));
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Add ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = event.clientX - rect.left - size / 2;
                    const y = event.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(59, 130, 246, 0.3);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        top: ${y}px;
                        left: ${x}px;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    setTimeout(() => ripple.remove(), 600);
                });
            });

            // Add CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
                
                .filter-tab {
                    position: relative;
                    overflow: hidden;
                }
                
                .activity-item {
                    transition: all 0.3s ease;
                }
            `;
            document.head.appendChild(style);
        });

        // Function to calculate relative time
        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) {
                return 'just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} day${days !== 1 ? 's' : ''} ago`;
            } else {
                return date.toLocaleDateString();
            }
        }
    </script>
</body>
</html>

<?php
// Helper function to get relative time
function getTimeAgo($date) {
    $timestamp = strtotime($date);
    $currentTime = time();
    $timeDiff = $currentTime - $timestamp;
    
    if ($timeDiff < 60) {
        return 'just now';
    } elseif ($timeDiff < 3600) {
        $minutes = floor($timeDiff / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    } elseif ($timeDiff < 86400) {
        $hours = floor($timeDiff / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    } elseif ($timeDiff < 604800) {
        $days = floor($timeDiff / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}