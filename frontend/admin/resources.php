<?php
require_once '../resident/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ../login.html');
    exit();
}

$conn = getDBConnection();

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->prepare("DELETE FROM resources WHERE id = ?")->execute([$id]);
    $_SESSION['success_message'] = "Resource deleted successfully!";
    header('Location: resources.php');
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    $total_quantity = (int)$_POST['total_quantity'];
    $available_quantity = (int)$_POST['available_quantity'];
    $status = $_POST['status'];

    if (isset($_POST['id'])) {
        // Edit
        $id = $_POST['id'];
        $conn->prepare("UPDATE resources SET name = ?, description = ?, total_quantity = ?, available_quantity = ?, status = ? WHERE id = ?")
             ->execute([$name, $description, $total_quantity, $available_quantity, $status, $id]);
        $_SESSION['success_message'] = "Resource updated successfully!";
    } else {
        // Add
        $conn->prepare("INSERT INTO resources (name, description, total_quantity, available_quantity, status) VALUES (?, ?, ?, ?, ?)")
             ->execute([$name, $description, $total_quantity, $available_quantity, $status]);
        $_SESSION['success_message'] = "Resource added successfully!";
    }
    header('Location: resources.php');
    exit();
}

// Fetch resources with usage calculation - REMOVED category field
$resources = $conn->query("
    SELECT *,
           (total_quantity - available_quantity) as borrowed_quantity,
           CASE 
               WHEN available_quantity = 0 THEN 'out-of-stock'
               WHEN available_quantity < total_quantity * 0.2 THEN 'low-stock'
               ELSE 'in-stock'
           END as stock_status
    FROM resources 
    ORDER BY name
")->fetchAll();

// Calculate statistics
$totalResources = count($resources);
$totalQuantity = array_sum(array_column($resources, 'total_quantity'));
$availableQuantity = array_sum(array_column($resources, 'available_quantity'));
$borrowedQuantity = $totalQuantity - $availableQuantity;
$lowStockCount = count(array_filter($resources, function($r) {
    return $r['available_quantity'] < $r['total_quantity'] * 0.2 && $r['available_quantity'] > 0;
}));
$outOfStockCount = count(array_filter($resources, function($r) {
    return $r['available_quantity'] == 0;
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Resources | BRMS Admin</title>
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
                    <a href="resources.php" class="nav-link active">
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
                        <h1><i class="fas fa-boxes"></i> Manage Resources</h1>
                        <p class="header-subtitle">Add, edit, and manage barangay resource inventory</p>
                    </div>
                    
                    <div class="header-controls">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Search resources...">
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
                        <div class="stat-title">Total Resources</div>
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $totalResources; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-database"></i>
                        Items in inventory
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-title">Available Items</div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $availableQuantity; ?></div>
                    <div class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        Ready for borrowing
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-title">Borrowed Items</div>
                        <div class="stat-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $borrowedQuantity; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-users"></i>
                        Currently in use
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div class="stat-title">Low Stock</div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $lowStockCount + $outOfStockCount; ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-exclamation-circle"></i>
                        Needs attention
                    </div>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="filters-section">
                <div class="filter-group">
                    <span class="filter-label">Filter by:</span>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                        <option value="low-stock">Low Stock</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>
                    <button onclick="resetFilters()" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>
            </div>

            <!-- ADD RESOURCE BUTTON -->
            <div class="add-resource-section">
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Resource
                </button>
            </div>

            <!-- RESOURCES TABLE -->
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fas fa-list"></i> Resource Inventory</h2>
                    <span class="table-count"><?php echo $totalResources; ?> resources</span>
                </div>
                
                <div class="table-responsive">
                    <table class="resources-table">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Total</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resources as $resource): 
                                $usagePercent = $resource['total_quantity'] > 0 ? 
                                    round(($resource['borrowed_quantity'] / $resource['total_quantity']) * 100) : 0;
                            ?>
                                <tr data-status="<?php echo $resource['stock_status']; ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($resource['name'])); ?>">
                                    <td>
                                        <div class="resource-info">
                                            <div class="resource-icon">
                                                <i class="fas fa-box"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo clean($resource['name']); ?></strong>
                                                <div class="resource-description">
                                                    <?php echo clean($resource['description']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="resource-quantity"><?php echo $resource['total_quantity']; ?></span>
                                    </td>
                                    <td>
                                        <div class="availability-info">
                                            <span class="available-quantity"><?php echo $resource['available_quantity']; ?></span>
                                            <?php if ($resource['available_quantity'] < $resource['total_quantity']): ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $usagePercent; ?>%"></div>
                                                </div>
                                                <span class="usage-text"><?php echo $usagePercent; ?>% in use</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($resource['available_quantity'] == 0): ?>
                                            <span class="status-badge out-of-stock">
                                                <i class="fas fa-times-circle"></i> Out of Stock
                                            </span>
                                        <?php elseif ($resource['available_quantity'] < $resource['total_quantity'] * 0.2): ?>
                                            <span class="status-badge low-stock">
                                                <i class="fas fa-exclamation-triangle"></i> Low Stock
                                            </span>
                                        <?php elseif ($resource['status'] == 'available'): ?>
                                            <span class="status-badge available">
                                                <i class="fas fa-check-circle"></i> Available
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge unavailable">
                                                <i class="fas fa-times-circle"></i> Unavailable
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="editResource(
                                                <?php echo $resource['id']; ?>,
                                                '<?php echo addslashes($resource['name']); ?>',
                                                '<?php echo addslashes($resource['description']); ?>',
                                                <?php echo $resource['total_quantity']; ?>,
                                                <?php echo $resource['available_quantity']; ?>,
                                                '<?php echo $resource['status']; ?>'
                                            )" class="btn-action edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete=<?php echo $resource['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this resource? This action cannot be undone.')"
                                               class="btn-action delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <button onclick="viewResource(<?php echo $resource['id']; ?>)" 
                                                    class="btn-action view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($resources)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <h3>No Resources Found</h3>
                                            <p>Start by adding your first resource to the inventory.</p>
                                            <button onclick="openModal()" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Add First Resource
                                            </button>
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
                <p>Barangay Resource Management System | Resource Management v2.0</p>
                <p>© <?php echo date('Y'); ?> All rights reserved | Last updated: <?php echo date('F j, Y g:i A'); ?></p>
            </footer>
        </main>
    </div>

    <!-- MODAL -->
    <div id="resourceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modal-title">Add New Resource</h2>
            <form method="POST" id="resourceForm">
                <input type="hidden" name="id" id="resource-id">
                <div class="form-group">
                    <label>Resource Name *</label>
                    <input type="text" name="name" id="resource-name" required placeholder="Enter resource name">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="resource-description" rows="3" placeholder="Describe the resource..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Total Quantity *</label>
                        <input type="number" name="total_quantity" id="total-quantity" required min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Available Quantity *</label>
                        <input type="number" name="available_quantity" id="available-quantity" required min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" id="resource-status" required>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="maintenance">Under Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Resource</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Resources Specific Styles */
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
        
        /* Add Resource Section */
        .add-resource-section {
            margin-bottom: 25px;
            display: flex;
            justify-content: flex-end;
        }
        
        .add-resource-section .btn {
            padding: 12px 30px;
            font-size: 15px;
            font-weight: 600;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .resources-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .resources-table thead {
            background: var(--admin-bg-light);
        }
        
        .resources-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--admin-text);
            border-bottom: 2px solid var(--admin-border);
        }
        
        .resources-table td {
            padding: 15px;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
        }
        
        .resources-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .resource-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .resource-icon {
            width: 40px;
            height: 40px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-accent);
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .resource-description {
            font-size: 13px;
            color: var(--admin-text-secondary);
            margin-top: 4px;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .resource-quantity {
            font-weight: 600;
            color: var(--admin-text);
        }
        
        .availability-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .available-quantity {
            font-weight: 600;
            font-size: 16px;
            color: var(--admin-text);
        }
        
        .progress-bar {
            width: 100px;
            height: 6px;
            background: var(--admin-border);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--admin-accent);
            border-radius: 3px;
        }
        
        .usage-text {
            font-size: 11px;
            color: var(--admin-text-secondary);
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
        }
        
        .status-badge.available {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .status-badge.unavailable {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
        }
        
        .status-badge.low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--admin-warning);
        }
        
        .status-badge.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
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
        }
        
        .btn-action.edit {
            background: rgba(59, 130, 246, 0.1);
            color: var(--admin-accent);
        }
        
        .btn-action.edit:hover {
            background: var(--admin-accent);
            color: white;
        }
        
        .btn-action.delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--admin-danger);
            text-decoration: none;
        }
        
        .btn-action.delete:hover {
            background: var(--admin-danger);
            color: white;
        }
        
        .btn-action.view {
            background: rgba(16, 185, 129, 0.1);
            color: var(--admin-success);
        }
        
        .btn-action.view:hover {
            background: var(--admin-success);
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .dark-mode .modal-content {
            background: var(--admin-secondary);
            color: var(--admin-text);
        }
        
        .modal-content h2 {
            margin-bottom: 25px;
            color: var(--admin-text);
            font-size: 24px;
        }
        
        .close {
            color: var(--admin-text-secondary);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: var(--admin-text);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .table-count {
            background: var(--admin-accent);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
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
                const rows = document.querySelectorAll('.resources-table tbody tr');
                
                rows.forEach(row => {
                    const resourceName = row.dataset.name || '';
                    if (resourceName.includes(searchTerm) || searchTerm === '') {
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
                const rows = document.querySelectorAll('.resources-table tbody tr');
                
                rows.forEach(row => {
                    const rowStatus = row.dataset.status || '';
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    const resourceName = row.dataset.name || '';
                    
                    const statusMatch = !status || rowStatus === status;
                    const searchMatch = !searchTerm || resourceName.includes(searchTerm);
                    
                    if (statusMatch && searchMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            statusFilter.addEventListener('change', applyFilters);
            
            // Modal functionality
            window.openModal = function() {
                document.getElementById('resourceModal').style.display = 'block';
                document.getElementById('modal-title').textContent = 'Add New Resource';
                document.getElementById('resourceForm').reset();
                document.getElementById('resource-id').value = '';
                document.getElementById('total-quantity').value = '0';
                document.getElementById('available-quantity').value = '0';
            };
            
            window.closeModal = function() {
                document.getElementById('resourceModal').style.display = 'none';
            };
            
            window.editResource = function(id, name, description, totalQty, availQty, status) {
                document.getElementById('resourceModal').style.display = 'block';
                document.getElementById('modal-title').textContent = 'Edit Resource';
                document.getElementById('resource-id').value = id;
                document.getElementById('resource-name').value = name;
                document.getElementById('resource-description').value = description;
                document.getElementById('total-quantity').value = totalQty;
                document.getElementById('available-quantity').value = availQty;
                document.getElementById('resource-status').value = status;
            };
            
            window.viewResource = function(id) {
                alert('Viewing resource details for ID: ' + id + '\n\nIn a complete implementation, this would show:\n- Borrow history\n- Usage statistics\n- Current borrowers\n- Maintenance records');
            };
            
            window.resetFilters = function() {
                statusFilter.value = '';
                searchInput.value = '';
                applyFilters();
            };
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                var modal = document.getElementById('resourceModal');
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };
            
            // Validate available quantity doesn't exceed total quantity
            const totalQtyInput = document.getElementById('total-quantity');
            const availQtyInput = document.getElementById('available-quantity');
            
            if (totalQtyInput && availQtyInput) {
                totalQtyInput.addEventListener('input', function() {
                    const total = parseInt(this.value) || 0;
                    const available = parseInt(availQtyInput.value) || 0;
                    if (available > total) {
                        availQtyInput.value = total;
                    }
                });
                
                availQtyInput.addEventListener('input', function() {
                    const total = parseInt(totalQtyInput.value) || 0;
                    const available = parseInt(this.value) || 0;
                    if (available > total) {
                        this.value = total;
                        alert('Available quantity cannot exceed total quantity.');
                    }
                });
            }
            
            // Form validation
            const resourceForm = document.getElementById('resourceForm');
            if (resourceForm) {
                resourceForm.addEventListener('submit', function(e) {
                    const total = parseInt(totalQtyInput.value) || 0;
                    const available = parseInt(availQtyInput.value) || 0;
                    
                    if (available > total) {
                        e.preventDefault();
                        alert('Available quantity cannot exceed total quantity.');
                        return false;
                    }
                    
                    if (total < 0 || available < 0) {
                        e.preventDefault();
                        alert('Quantity values cannot be negative.');
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>