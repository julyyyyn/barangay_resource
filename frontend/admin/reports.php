<?php
require_once '../resident/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../login.html');
    exit();
}

$conn = getDBConnection();

// Get date range from URL or default to last 30 days
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Generate report based on type
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Fetch statistics for overview
if ($report_type == 'overview') {
    // Total statistics
    $total_residents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role_id = 2 AND status = 'Active'")->fetch()['count'];
    $total_resources = $conn->query("SELECT COUNT(*) as count FROM resources")->fetch()['count'];
    $total_borrow_requests = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetch()['count'];
    $total_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetch()['count'];
    
    // Approved requests
    $approved_borrows = $conn->query("SELECT COUNT(*) as count FROM borrow_requests WHERE status = 'approved' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetch()['count'];
    $approved_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status = 'approved' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetch()['count'];
    
    // Monthly trend data - FIXED: Use derived table
    $monthly_trends = $conn->query("
        SELECT 
            month,
            borrow_count,
            donation_count
        FROM (
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN type = 'borrow' THEN 1 ELSE 0 END) as borrow_count,
                SUM(CASE WHEN type = 'donation' THEN 1 ELSE 0 END) as donation_count
            FROM (
                SELECT 'borrow' as type, created_at FROM borrow_requests WHERE status = 'approved'
                UNION ALL
                SELECT 'donation' as type, created_at FROM donations WHERE status = 'approved'
            ) as combined
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ) as monthly_data
        ORDER BY month DESC
        LIMIT 6
    ")->fetchAll();
    
    // Top resources - FIXED: Don't use alias in ORDER BY
    $top_resources = $conn->query("
        SELECT 
            r.name,
            COUNT(br.id) as borrow_count,
            COALESCE(SUM(br.quantity), 0) as total_borrowed
        FROM resources r
        LEFT JOIN borrow_requests br ON br.resource_id = r.id AND br.status = 'approved' AND br.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY r.id, r.name
        ORDER BY COUNT(br.id) DESC, COALESCE(SUM(br.quantity), 0) DESC
        LIMIT 5
    ")->fetchAll();
    
    // Top residents - FIXED: Calculate activity in ORDER BY
    $top_residents = $conn->query("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) as name,
            COUNT(DISTINCT br.id) as borrow_count,
            COUNT(DISTINCT d.id) as donation_count
        FROM users u
        LEFT JOIN borrow_requests br ON u.user_id = br.user_id AND br.status = 'approved' AND br.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        LEFT JOIN donations d ON u.user_id = d.user_id AND d.status = 'approved' AND d.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        WHERE u.role_id = 2 AND u.status = 'Active'
        GROUP BY u.user_id, u.first_name, u.last_name
        ORDER BY (COUNT(DISTINCT br.id) + COUNT(DISTINCT d.id)) DESC
        LIMIT 5
    ")->fetchAll();
}

// Resource utilization report - FIXED: No alias issue here
if ($report_type == 'resource_utilization') {
    $resource_report = $conn->query("
        SELECT 
            r.name,
            r.total_quantity,
            r.available_quantity,
            (r.total_quantity - r.available_quantity) as borrowed_quantity,
            CASE 
                WHEN r.total_quantity = 0 THEN 0
                WHEN r.available_quantity = 0 THEN 100
                ELSE ROUND((r.total_quantity - r.available_quantity) * 100.0 / r.total_quantity, 1)
            END as utilization_rate
        FROM resources r
        ORDER BY (r.total_quantity - r.available_quantity) * 100.0 / r.total_quantity DESC
    ")->fetchAll();
}

// Borrowing trends report - FIXED: No alias issue here
if ($report_type == 'borrowing_trends') {
    $borrowing_trends = $conn->query("
        SELECT 
            DATE(br.created_at) as date,
            COUNT(br.id) as request_count,
            SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN br.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM borrow_requests br
        WHERE br.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY DATE(br.created_at)
        ORDER BY date
    ")->fetchAll();
}

// Donation report - FIXED: No alias issue here
if ($report_type == 'donations') {
    $donation_report = $conn->query("
        SELECT 
            d.item_name,
            d.quantity,
            d.status,
            d.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as donor_name
        FROM donations d
        JOIN users u ON d.user_id = u.user_id
        WHERE d.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        ORDER BY d.created_at DESC
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports | BRMS Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../frontend/admin/admin-dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="reports.php" class="nav-link active">
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
                        <h1><i class="fas fa-chart-bar"></i> System Reports</h1>
                        <p class="header-subtitle">Generate and analyze system data and statistics</p>
                    </div>
                    
                    <div class="header-controls">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search reports...">
                            <i class="fas fa-search"></i>
                        </div>
                        <button class="btn btn-primary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <div class="theme-toggle" id="themeToggle">
                            <i class="fas fa-moon"></i>
                            <span class="toggle-label">Dark Mode</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- DATE RANGE SELECTOR -->
            <div class="date-range-section">
                <form method="GET" class="date-range-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Report Type</label>
                            <select name="report_type" id="reportType" onchange="this.form.submit()">
                                <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>System Overview</option>
                                <option value="resource_utilization" <?php echo $report_type == 'resource_utilization' ? 'selected' : ''; ?>>Resource Utilization</option>
                                <option value="borrowing_trends" <?php echo $report_type == 'borrowing_trends' ? 'selected' : ''; ?>>Borrowing Trends</option>
                                <option value="donations" <?php echo $report_type == 'donations' ? 'selected' : ''; ?>>Donation Report</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-secondary" onclick="applyDateRange('today')">Today</button>
                            <button type="button" class="btn btn-secondary" onclick="applyDateRange('week')">This Week</button>
                            <button type="button" class="btn btn-secondary" onclick="applyDateRange('month')">This Month</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- OVERVIEW REPORT -->
            <?php if ($report_type == 'overview'): ?>
                <div class="report-section">
                    <!-- KEY METRICS -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-title">Total Residents</div>
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $total_residents; ?></div>
                            <div class="stat-trend">
                                <i class="fas fa-user-check"></i>
                                Verified residents
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
                                Inventory items
                            </div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-header">
                                <div class="stat-title">Approved Borrows</div>
                                <div class="stat-icon">
                                    <i class="fas fa-handshake"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $approved_borrows; ?></div>
                            <div class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <?php echo $total_borrow_requests > 0 ? round(($approved_borrows / $total_borrow_requests) * 100) : 0; ?>% approval rate
                            </div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-header">
                                <div class="stat-title">Approved Donations</div>
                                <div class="stat-icon">
                                    <i class="fas fa-gift"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $approved_donations; ?></div>
                            <div class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <?php echo $total_donations > 0 ? round(($approved_donations / $total_donations) * 100) : 0; ?>% approval rate
                            </div>
                        </div>
                    </div>

                    <!-- CHARTS ROW -->
                    <div class="charts-row">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3><i class="fas fa-chart-line"></i> Monthly Activity Trends</h3>
                            </div>
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3><i class="fas fa-chart-pie"></i> Request Distribution</h3>
                            </div>
                            <canvas id="requestDistributionChart"></canvas>
                        </div>
                    </div>

                    <!-- TOP LISTS ROW -->
                    <div class="content-grid">
                        <div class="panel">
                            <div class="panel-header">
                                <h2><i class="fas fa-star"></i> Top Resources</h2>
                                <span class="table-count">Most borrowed</span>
                            </div>
                            <div class="resource-list">
                                <?php if (!empty($top_resources)): ?>
                                    <?php foreach ($top_resources as $index => $resource): ?>
                                        <div class="top-item">
                                            <div class="rank"><?php echo $index + 1; ?></div>
                                            <div class="item-details">
                                                <strong><?php echo clean($resource['name']); ?></strong>
                                                <div class="item-stats">
                                                    <span class="stat"><?php echo $resource['borrow_count']; ?> borrows</span>
                                                    <span class="stat"><?php echo $resource['total_borrowed']; ?> total items</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-chart-bar"></i>
                                        <p>No borrowing data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-header">
                                <h2><i class="fas fa-user-star"></i> Top Residents</h2>
                                <span class="table-count">Most active</span>
                            </div>
                            <div class="resident-list">
                                <?php if (!empty($top_residents)): ?>
                                    <?php foreach ($top_residents as $index => $resident): ?>
                                        <div class="top-item">
                                            <div class="rank"><?php echo $index + 1; ?></div>
                                            <div class="resident-avatar">
                                                <?php echo strtoupper(substr(explode(' ', $resident['name'])[0], 0, 1)); ?>
                                            </div>
                                            <div class="item-details">
                                                <strong><?php echo clean($resident['name']); ?></strong>
                                                <div class="item-stats">
                                                    <span class="stat"><?php echo $resident['borrow_count']; ?> borrows</span>
                                                    <span class="stat"><?php echo $resident['donation_count']; ?> donations</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No resident activity data</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- RESOURCE UTILIZATION REPORT -->
            <?php elseif ($report_type == 'resource_utilization'): ?>
                <div class="report-section">
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-chart-bar"></i> Resource Utilization Report</h2>
                            <span class="table-count"><?php echo count($resource_report); ?> resources</span>
                        </div>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Total Quantity</th>
                                        <th>Available</th>
                                        <th>Borrowed</th>
                                        <th>Utilization Rate</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resource_report as $resource): ?>
                                        <tr>
                                            <td>
                                                <div class="resource-info">
                                                    <div class="resource-icon">
                                                        <i class="fas fa-box"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo clean($resource['name']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $resource['total_quantity']; ?></td>
                                            <td>
                                                <span class="available-quantity"><?php echo $resource['available_quantity']; ?></span>
                                            </td>
                                            <td>
                                                <span class="borrowed-quantity"><?php echo $resource['borrowed_quantity']; ?></span>
                                            </td>
                                            <td>
                                                <div class="utilization-bar">
                                                    <div class="bar-fill" style="width: <?php echo min($resource['utilization_rate'], 100); ?>%"></div>
                                                    <span class="utilization-text"><?php echo $resource['utilization_rate']; ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($resource['utilization_rate'] >= 80): ?>
                                                    <span class="status-badge high-usage">
                                                        <i class="fas fa-fire"></i> High Usage
                                                    </span>
                                                <?php elseif ($resource['utilization_rate'] >= 50): ?>
                                                    <span class="status-badge medium-usage">
                                                        <i class="fas fa-chart-line"></i> Moderate
                                                    </span>
                                                <?php elseif ($resource['utilization_rate'] > 0): ?>
                                                    <span class="status-badge low-usage">
                                                        <i class="fas fa-chart-bar"></i> Low Usage
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge no-usage">
                                                        <i class="fas fa-times-circle"></i> Unused
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- BORROWING TRENDS REPORT -->
            <?php elseif ($report_type == 'borrowing_trends'): ?>
                <div class="report-section">
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-chart-line"></i> Borrowing Trends Report</h2>
                            <span class="table-count"><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></span>
                        </div>
                        <div class="chart-container large">
                            <canvas id="borrowingTrendsChart"></canvas>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Approval Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowing_trends as $trend): 
                                        $approval_rate = $trend['request_count'] > 0 ? round(($trend['approved_count'] / $trend['request_count']) * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($trend['date'])); ?></td>
                                            <td><?php echo $trend['request_count']; ?></td>
                                            <td>
                                                <span class="approved-count"><?php echo $trend['approved_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="rejected-count"><?php echo $trend['rejected_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="approval-rate">
                                                    <span class="rate-text"><?php echo $approval_rate; ?>%</span>
                                                    <div class="rate-bar">
                                                        <div class="rate-fill" style="width: <?php echo $approval_rate; ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($borrowing_trends)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <div class="empty-state">
                                                    <i class="fas fa-chart-line"></i>
                                                    <p>No borrowing data for the selected date range</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- DONATION REPORT -->
            <?php elseif ($report_type == 'donations'): ?>
                <div class="report-section">
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-gift"></i> Donation Report</h2>
                            <span class="table-count"><?php echo count($donation_report); ?> donations</span>
                        </div>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Donor</th>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donation_report as $donation): ?>
                                        <tr>
                                            <td>
                                                <div class="donor-info">
                                                    <div class="donor-avatar">
                                                        <?php echo strtoupper(substr(explode(' ', $donation['donor_name'])[0], 0, 1)); ?>
                                                    </div>
                                                    <span><?php echo clean($donation['donor_name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo clean($donation['item_name'] ?: 'General Donation'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="donation-quantity"><?php echo $donation['quantity']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($donation['status'] == 'approved'): ?>
                                                    <span class="status-badge approved">
                                                        <i class="fas fa-check-circle"></i> Approved
                                                    </span>
                                                <?php elseif ($donation['status'] == 'pending'): ?>
                                                    <span class="status-badge pending">
                                                        <i class="fas fa-clock"></i> Pending
                                                    </span>
                                                <?php elseif ($donation['status'] == 'rejected'): ?>
                                                    <span class="status-badge rejected">
                                                        <i class="fas fa-times-circle"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($donation['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($donation_report)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <div class="empty-state">
                                                    <i class="fas fa-gift"></i>
                                                    <p>No donation data for the selected date range</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- FOOTER -->
            <footer class="admin-footer">
                <p>Barangay Resource Management System | Reports v2.0</p>
                <p>© <?php echo date('Y'); ?> All rights reserved | Report generated: <?php echo date('F j, Y g:i A'); ?></p>
            </footer>
        </main>
    </div>

    <style>
        /* Reports Specific Styles */
        .date-range-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--admin-border);
            box-shadow: 0 2px 8px var(--admin-shadow);
        }
        
        .dark-mode .date-range-section {
            background: rgba(30, 41, 59, 0.8);
        }
        
        .date-range-form .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .date-range-form .form-group {
            margin-bottom: 0;
        }
        
        .date-range-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--admin-text);
            font-size: 14px;
        }
        
        .date-range-form select,
        .date-range-form input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: var(--admin-bg-light);
            color: var(--admin-text);
            font-size: 14px;
        }
        
        .date-range-form button {
            margin-top: 0;
        }
        
        .report-section {
            margin-bottom: 30px;
        }
        
        /* Charts */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--admin-border);
            box-shadow: 0 2px 8px var(--admin-shadow);
        }
        
        .dark-mode .chart-container {
            background: rgba(30, 41, 59, 0.8);
        }
        
        .chart-container.large {
            grid-column: 1 / -1;
        }
        
        .chart-header {
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--admin-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Top Items Lists */
        .top-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid var(--admin-border);
            transition: all 0.3s;
        }
        
        .top-item:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .top-item:last-child {
            border-bottom: none;
        }
        
        .rank {
            width: 30px;
            height: 30px;
            background: var(--admin-accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .resident-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--admin-accent), var(--admin-success));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-details strong {
            display: block;
            margin-bottom: 5px;
            color: var(--admin-text);
            font-size: 15px;
        }
        
        .item-stats {
            display: flex;
            gap: 15px;
        }
        
        .item-stats .stat {
            font-size: 12px;
            color: var(--admin-text-secondary);
            padding: 3px 8px;
            background: var(--admin-bg-light);
            border-radius: 12px;
        }
        
        /* Report Tables */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table thead {
            background: var(--admin-bg-light);
        }
        
        .report-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--admin-text);
            border-bottom: 2px solid var(--admin-border);
        }
        
        .report-table td {
            padding: 15px;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
        }
        
        .report-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        /* Resource Info */
        .resource-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .resource-icon {
            width: 35px;
            height: 35px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-accent);
            font-size: 16px;
            flex-shrink: 0;
        }
        
        /* Quantity Styles */
        .available-quantity {
            color: var(--admin-success);
            font-weight: 600;
        }
        
        .borrowed-quantity {
            color: var(--admin-warning);
            font-weight: 600;
        }
        
        .donation-quantity {
            color: var(--admin-accent);
            font-weight: 600;
        }
        
        /* Utilization Bar */
        .utilization-bar {
            width: 100px;
            height: 24px;
            background: var(--admin-bg-light);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--admin-accent), var(--admin-success));
            border-radius: 12px;
            transition: width 0.3s;
        }
        
        .utilization-text {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: white;
            z-index: 1;
        }
        
        /* Approval Rate */
        .approval-rate {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rate-text {
            font-weight: 600;
            color: var(--admin-text);
            min-width: 40px;
        }
        
        .rate-bar {
            flex: 1;
            height: 6px;
            background: var(--admin-bg-light);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .rate-fill {
            height: 100%;
            background: var(--admin-success);
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        /* Donor Info */
        .donor-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .donor-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--admin-success), var(--admin-accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
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
        
        .status-badge.approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
        }
        
        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .status-badge.high-usage {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .status-badge.medium-usage {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
        }
        
        .status-badge.low-usage {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
        }
        
        .status-badge.no-usage {
            background: rgba(156, 163, 175, 0.1);
            color: #9ca3af;
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
            padding: 30px;
            color: var(--admin-text-secondary);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 14px;
            margin: 0;
        }
        
        .text-center {
            text-align: center;
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
            
            // Date range quick buttons
            window.applyDateRange = function(range) {
                const today = new Date();
                let startDate = new Date();
                let endDate = new Date();
                
                switch(range) {
                    case 'today':
                        startDate = endDate;
                        break;
                    case 'week':
                        startDate.setDate(today.getDate() - 7);
                        break;
                    case 'month':
                        startDate.setMonth(today.getMonth() - 1);
                        break;
                }
                
                document.querySelector('input[name="start_date"]').value = startDate.toISOString().split('T')[0];
                document.querySelector('input[name="end_date"]').value = endDate.toISOString().split('T')[0];
                document.querySelector('.date-range-form').submit();
            };
            
            // Print report
            window.printReport = function() {
                window.print();
            };
            
            // Initialize charts if we're on overview page
            <?php if ($report_type == 'overview'): ?>
                // Monthly Trends Chart
                const monthlyCtx = document.getElementById('monthlyTrendsChart')?.getContext('2d');
                if (monthlyCtx) {
                    new Chart(monthlyCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_map(function($t) { 
                                return date('M Y', strtotime($t['month'] . '-01')); 
                            }, array_reverse($monthly_trends))); ?>,
                            datasets: [
                                {
                                    label: 'Borrows',
                                    data: <?php echo json_encode(array_map(function($t) { return $t['borrow_count']; }, array_reverse($monthly_trends))); ?>,
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    tension: 0.4
                                },
                                {
                                    label: 'Donations',
                                    data: <?php echo json_encode(array_map(function($t) { return $t['donation_count']; }, array_reverse($monthly_trends))); ?>,
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Request Distribution Chart
                const distributionCtx = document.getElementById('requestDistributionChart')?.getContext('2d');
                if (distributionCtx) {
                    new Chart(distributionCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Borrow Requests', 'Donations'],
                            datasets: [{
                                data: [<?php echo $total_borrow_requests; ?>, <?php echo $total_donations; ?>],
                                backgroundColor: [
                                    '#3b82f6',
                                    '#10b981'
                                ],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>
            
            <?php if ($report_type == 'borrowing_trends' && !empty($borrowing_trends)): ?>
                // Borrowing Trends Chart
                const borrowingCtx = document.getElementById('borrowingTrendsChart')?.getContext('2d');
                if (borrowingCtx) {
                    new Chart(borrowingCtx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(array_map(function($t) { 
                                return date('M d', strtotime($t['date'])); 
                            }, $borrowing_trends)); ?>,
                            datasets: [
                                {
                                    label: 'Total Requests',
                                    data: <?php echo json_encode(array_column($borrowing_trends, 'request_count')); ?>,
                                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                    borderColor: '#3b82f6',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Approved',
                                    data: <?php echo json_encode(array_column($borrowing_trends, 'approved_count')); ?>,
                                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                    borderColor: '#10b981',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Rejected',
                                    data: <?php echo json_encode(array_column($borrowing_trends, 'rejected_count')); ?>,
                                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                    borderColor: '#ef4444',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            <?php endif; ?>
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('.report-table tbody tr, .top-item');
                    
                    rows.forEach(row => {
                        const rowText = row.textContent.toLowerCase();
                        if (rowText.includes(searchTerm) || searchTerm === '') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Auto-submit form when date inputs change
            document.querySelectorAll('input[name="start_date"], input[name="end_date"]').forEach(input => {
                input.addEventListener('change', function() {
                    this.form.submit();
                });
            });
        });
    </script>
</body>
</html>