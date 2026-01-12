<?php
require_once '../resident/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../login.html');
    exit();
}

$conn = getDBConnection();

// Handle approve/reject
if (isset($_GET['action']) && isset($_GET['type']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $type = $_GET['type'];
    $id = $_GET['id'];

    if ($type == 'borrow') {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        if ($action == 'approve') {
            // Get the borrow request details
            $stmt = $conn->prepare("SELECT resource_id, quantity FROM borrow_requests WHERE id = ?");
            $stmt->execute([$id]);
            $request = $stmt->fetch();
            if ($request) {
                // Check if enough quantity is available
                $resourceStmt = $conn->prepare("SELECT available_quantity FROM resources WHERE id = ?");
                $resourceStmt->execute([$request['resource_id']]);
                $resource = $resourceStmt->fetch();
                if ($resource && $resource['available_quantity'] >= $request['quantity']) {
                    // Start transaction
                    $conn->beginTransaction();
                    try {
                        // Update borrow request status
                        $conn->prepare("UPDATE borrow_requests SET status = ? WHERE id = ?")->execute([$status, $id]);
                        // Subtract from available quantity
                        $conn->prepare("UPDATE resources SET available_quantity = available_quantity - ? WHERE id = ?")->execute([$request['quantity'], $request['resource_id']]);
                        $conn->commit();
                        $_SESSION['success_message'] = "Borrow request approved successfully!";
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $_SESSION['error_message'] = "Failed to process request. Please try again.";
                    }
                } else {
                    $_SESSION['error_message'] = "Insufficient quantity available for approval.";
                }
            }
        } else {
            // Just reject
            $conn->prepare("UPDATE borrow_requests SET status = ? WHERE id = ?")->execute([$status, $id]);
            $_SESSION['success_message'] = "Borrow request rejected successfully!";
        }
    } elseif ($type == 'donation') {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        if ($action == 'approve') {
            // Get the donation details
            $stmt = $conn->prepare("SELECT resource_id, quantity FROM donations WHERE id = ?");
            $stmt->execute([$id]);
            $donation = $stmt->fetch();
            if ($donation && $donation['resource_id']) {
                // Start transaction
                $conn->beginTransaction();
                try {
                    // Update donation status
                    $conn->prepare("UPDATE donations SET status = ? WHERE id = ?")->execute([$status, $id]);
                    // Add to available quantity
                    $conn->prepare("UPDATE resources SET available_quantity = available_quantity + ? WHERE id = ?")->execute([$donation['quantity'], $donation['resource_id']]);
                    $conn->commit();
                    $_SESSION['success_message'] = "Donation approved successfully!";
                } catch (Exception $e) {
                    $conn->rollBack();
                    $_SESSION['error_message'] = "Failed to process donation. Please try again.";
                }
            } else {
                // General donation, just approve
                $conn->prepare("UPDATE donations SET status = ? WHERE id = ?")->execute([$status, $id]);
                $_SESSION['success_message'] = "Donation approved successfully!";
            }
        } else {
            // Just reject
            $conn->prepare("UPDATE donations SET status = ? WHERE id = ?")->execute([$status, $id]);
            $_SESSION['success_message'] = "Donation rejected successfully!";
        }
    }
    header('Location: manage_requests.php');
    exit();
}

// Fetch pending requests - REMOVED avatar_url column
$borrowRequests = $conn->query("
    SELECT br.*, r.name as resource_name, r.available_quantity, 
           u.first_name, u.last_name,
           DATE_FORMAT(br.created_at, '%b %d, %Y') as formatted_date,
           DATE_FORMAT(br.created_at, '%h:%i %p') as formatted_time
    FROM borrow_requests br
    JOIN resources r ON br.resource_id = r.id
    JOIN users u ON br.user_id = u.user_id
    WHERE br.status = 'pending'
    ORDER BY br.created_at DESC
")->fetchAll();

$donations = $conn->query("
    SELECT d.*, 
           u.first_name, u.last_name,
           DATE_FORMAT(d.created_at, '%b %d, %Y') as formatted_date,
           DATE_FORMAT(d.created_at, '%h:%i %p') as formatted_time
    FROM donations d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.status = 'pending'
    ORDER BY d.created_at DESC
")->fetchAll();

$totalPending = count($borrowRequests) + count($donations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Requests | BRMS Admin</title>
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
                    <a href="manage_requests.php" class="nav-link active">
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
                        <h1><i class="fas fa-tasks"></i> Manage Requests</h1>
                        <p class="header-subtitle">Approve or reject borrow requests and donations from residents</p>
                    </div>
                    
                    <div class="header-controls">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search requests, residents, or resources...">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="notification-badge">
                            <i class="fas fa-bell"></i>
                            <span class="badge"><?php echo $totalPending; ?></span>
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
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert-banner danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="alert-content">
                        <h3>Error!</h3>
                        <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- REQUEST STATS -->
            <div class="stats-grid">
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Pending Borrow Requests</div>
                        <div class="stat-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo count($borrowRequests); ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-clock"></i>
                        Awaiting approval
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Pending Donations</div>
                        <div class="stat-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo count($donations); ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-heart"></i>
                        Community contributions
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-title">Total Pending</div>
                        <div class="stat-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalPending; ?></div>
                    <div class="stat-trend positive">
                        <i class="fas fa-exclamation-circle"></i>
                        Requires attention
                    </div>
                </div>
            </div>

            <!-- REQUEST TABS -->
            <div class="request-tabs">
                <button class="tab-btn active" data-tab="borrow">Borrow Requests</button>
                <button class="tab-btn" data-tab="donation">Donations</button>
            </div>

            <!-- BORROW REQUESTS SECTION -->
            <section id="borrow-section" class="request-section active">
                <div class="section-header">
                    <h2><i class="fas fa-handshake"></i> Borrow Requests</h2>
                    <span class="section-count"><?php echo count($borrowRequests); ?> pending</span>
                </div>
                
                <?php if (empty($borrowRequests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No pending borrow requests</h3>
                        <p>All borrow requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-grid">
                        <?php foreach ($borrowRequests as $request): 
                            $canApprove = $request['available_quantity'] >= $request['quantity'];
                        ?>
                            <div class="request-card <?php echo !$canApprove ? 'insufficient' : ''; ?>">
                                <div class="request-header">
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <div class="avatar-placeholder">
                                                <?php echo strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo clean($request['first_name'] . ' ' . $request['last_name']); ?></h4>
                                            <span class="user-role">Resident</span>
                                        </div>
                                    </div>
                                    <span class="request-type borrow">Borrow Request</span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <i class="fas fa-box"></i>
                                        <div>
                                            <span class="detail-label">Resource</span>
                                            <span class="detail-value"><?php echo clean($request['resource_name']); ?></span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <div>
                                            <span class="detail-label">Quantity</span>
                                            <span class="detail-value <?php echo !$canApprove ? 'danger' : ''; ?>">
                                                <?php echo $request['quantity']; ?> 
                                                <?php if (!$canApprove): ?>
                                                    <span class="available-info">(Only <?php echo $request['available_quantity']; ?> available)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <div>
                                            <span class="detail-label">Requested On</span>
                                            <span class="detail-value"><?php echo $request['formatted_date']; ?> at <?php echo $request['formatted_time']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <?php if ($canApprove): ?>
                                        <a href="?action=approve&type=borrow&id=<?php echo $request['id']; ?>" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled title="Insufficient quantity available">
                                            <i class="fas fa-times"></i> Cannot Approve
                                        </button>
                                    <?php endif; ?>
                                    <a href="?action=reject&type=borrow&id=<?php echo $request['id']; ?>" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                    <a href="#" class="btn btn-secondary view-details">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- DONATIONS SECTION -->
            <section id="donation-section" class="request-section">
                <div class="section-header">
                    <h2><i class="fas fa-gift"></i> Donations</h2>
                    <span class="section-count"><?php echo count($donations); ?> pending</span>
                </div>
                
                <?php if (empty($donations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gift"></i>
                        <h3>No pending donations</h3>
                        <p>All donations have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-grid">
                        <?php foreach ($donations as $donation): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <div class="avatar-placeholder">
                                                <?php echo strtoupper(substr($donation['first_name'], 0, 1) . substr($donation['last_name'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo clean($donation['first_name'] . ' ' . $donation['last_name']); ?></h4>
                                            <span class="user-role">Resident</span>
                                        </div>
                                    </div>
                                    <span class="request-type donation">Donation</span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <i class="fas fa-gift"></i>
                                        <div>
                                            <span class="detail-label">Item</span>
                                            <span class="detail-value"><?php echo clean($donation['item_name'] ?: 'General Donation'); ?></span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <div>
                                            <span class="detail-label">Quantity</span>
                                            <span class="detail-value"><?php echo $donation['quantity']; ?></span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="far fa-file-alt"></i>
                                        <div>
                                            <span class="detail-label">Description</span>
                                            <span class="detail-value"><?php echo clean($donation['description'] ?: 'No description provided'); ?></span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <div>
                                            <span class="detail-label">Donated On</span>
                                            <span class="detail-value"><?php echo $donation['formatted_date']; ?> at <?php echo $donation['formatted_time']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="request-actions">
                                    <a href="?action=approve&type=donation&id=<?php echo $donation['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?action=reject&type=donation&id=<?php echo $donation['id']; ?>" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                    <a href="#" class="btn btn-secondary view-details">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- FOOTER -->
            <footer class="admin-footer">
                <p>Barangay Resource Management System | Manage Requests v2.0</p>
                <p>© <?php echo date('Y'); ?> All rights reserved | Last updated: <?php echo date('F j, Y g:i A'); ?></p>
            </footer>
        </main>
    </div>

    <style>
        /* Manage Requests Specific Styles */
        .request-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding: 0 5px;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: var(--admin-bg-light);
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            color: var(--admin-text-secondary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
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
        
        .request-section {
            display: none;
        }
        
        .request-section.active {
            display: block;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--admin-border);
        }
        
        .section-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--admin-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-count {
            background: var(--admin-warning);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        @media (max-width: 992px) {
            .requests-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .request-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--admin-border);
            box-shadow: 0 4px 12px var(--admin-shadow);
            transition: all 0.3s ease;
        }
        
        .dark-mode .request-card {
            background: rgba(30, 41, 59, 0.8);
        }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px var(--admin-shadow);
        }
        
        .request-card.insufficient {
            border-left: 4px solid var(--admin-danger);
        }
        
        .request-card:not(.insufficient) {
            border-left: 4px solid var(--admin-accent);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--admin-border);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--admin-accent), var(--admin-success));
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .user-details h4 {
            margin: 0 0 5px 0;
            color: var(--admin-text);
            font-size: 16px;
            font-weight: 600;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--admin-text-secondary);
            background: var(--admin-bg-light);
            padding: 3px 10px;
            border-radius: 12px;
        }
        
        .request-type {
            font-size: 12px;
            padding: 5px 12px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .request-type.borrow {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
        }
        
        .request-type.donation {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .request-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .request-details {
                grid-template-columns: 1fr;
            }
        }
        
        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .detail-item i {
            color: var(--admin-accent);
            font-size: 16px;
            margin-top: 2px;
        }
        
        .detail-label {
            display: block;
            font-size: 12px;
            color: var(--admin-text-secondary);
            margin-bottom: 3px;
        }
        
        .detail-value {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--admin-text);
        }
        
        .detail-value.danger {
            color: var(--admin-danger);
        }
        
        .available-info {
            font-size: 11px;
            font-weight: normal;
            opacity: 0.8;
        }
        
        .request-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
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
        
        .btn-success {
            background: var(--admin-success);
            color: white;
        }
        
        .btn-success:hover {
            background: #0da271;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: var(--admin-danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-secondary {
            background: var(--admin-bg-light);
            color: var(--admin-text-secondary);
            border: 1px solid var(--admin-border);
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
            border-color: var(--admin-accent);
        }
        
        .btn-secondary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            // Tab functionality
            const tabBtns = document.querySelectorAll('.tab-btn');
            const requestSections = document.querySelectorAll('.request-section');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.dataset.tab;
                    
                    // Update active tab button
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding section
                    requestSections.forEach(section => {
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
                const activeSection = document.querySelector('.request-section.active');
                const requestCards = activeSection.querySelectorAll('.request-card');
                
                requestCards.forEach(card => {
                    const cardText = card.textContent.toLowerCase();
                    if (cardText.includes(searchTerm) || searchTerm === '') {
                        card.style.display = 'block';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 300);
                    }
                });
            });
            
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
            
            // Confirmation for reject actions
            const rejectButtons = document.querySelectorAll('.btn-danger');
            rejectButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to reject this request? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>