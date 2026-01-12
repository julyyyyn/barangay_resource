<?php
require_once 'config.php';
requireLogin();

if (isAdmin()) {
    header('Location: ../admin/admin_dashboard.php');
    exit();
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$errors = [];

// Handle borrow request submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $resource_id = (int)$_POST['resource_id'];
    $quantity = (int)$_POST['quantity'];
    $date_of_use = clean($_POST['date_of_use'] ?? '');
    $return_date = clean($_POST['return_date'] ?? '');
    $purpose = trim(clean($_POST['purpose'] ?? '')); // Make this optional

    // Basic validation
    if (empty($resource_id) || $resource_id <= 0) {
        $errors[] = "Please select a valid resource.";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than zero.";
    }

    if (empty($date_of_use)) {
        $errors[] = "Date of use is required.";
    } else {
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_use)) {
            $errors[] = "Invalid date format for date of use.";
        } else {
            $use_date_obj = DateTime::createFromFormat('Y-m-d', $date_of_use);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if ($use_date_obj < $today) {
                $errors[] = "Date of use cannot be in the past.";
            }
        }
    }

    if (empty($return_date)) {
        $errors[] = "Return date is required.";
    } else {
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
            $errors[] = "Invalid date format for return date.";
        } elseif (!empty($date_of_use)) {
            $use_date_obj = DateTime::createFromFormat('Y-m-d', $date_of_use);
            $return_date_obj = DateTime::createFromFormat('Y-m-d', $return_date);
            
            if ($return_date_obj <= $use_date_obj) {
                $errors[] = "Return date must be after the date of use.";
            }
        }
    }

    // Check resource availability
    if ($resource_id > 0 && empty($errors)) {
        $resource_stmt = $conn->prepare("SELECT available_quantity, name FROM resources WHERE id = ?");
        $resource_stmt->execute([$resource_id]);
        $resource = $resource_stmt->fetch();
        
        if (!$resource) {
            $errors[] = "Selected resource not found.";
        } elseif ($quantity > $resource['available_quantity']) {
            $errors[] = "Requested quantity exceeds available quantity. Only " . $resource['available_quantity'] . " " . $resource['name'] . " available.";
        }
    }

    // If no errors, save borrow request
    if (empty($errors)) {
        try {
            // First, let's check what columns exist in the borrow_requests table
            $checkColumns = $conn->prepare("SHOW COLUMNS FROM borrow_requests");
            $checkColumns->execute();
            $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('purpose', $columns)) {
                // If purpose column exists, include it in the insert
                $stmt = $conn->prepare("
                    INSERT INTO borrow_requests 
                    (user_id, resource_id, quantity, borrow_date, return_date, purpose, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$userId, $resource_id, $quantity, $borrow_date, $return_date, $purpose]);
            } else {
                // If purpose column doesn't exist, exclude it
                $stmt = $conn->prepare("
                    INSERT INTO borrow_requests 
                    (user_id, resource_id, quantity, date_of_use, return_date, status) 
                    VALUES (?, ?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$userId, $resource_id, $quantity, $borrow_date, $return_date]);
            }

            // Update the resource available quantity
            $update_stmt = $conn->prepare("
                UPDATE resources 
                SET available_quantity = available_quantity - ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$quantity, $resource_id]);

            header('Location: borrow.php?success=1');
            exit();
        } catch (Exception $e) {
            // Log the actual error for debugging
            error_log("Borrow request error: " . $e->getMessage());
            $errors[] = "Failed to submit borrow request. Please try again. Error: " . $e->getMessage();
        }
    }
}

// Fetch available resources
$resources = $conn->query("
    SELECT * FROM resources 
    WHERE available_quantity > 0 
    ORDER BY name
")->fetchAll();

// Calculate minimum return date (tomorrow)
$min_return_date = date('Y-m-d', strtotime('+1 day'));
$min_use_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Borrow Resources - Barangay Resource Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        box-shadow: 0 10px 25px rgba(5, 150, 105, 0.2);
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
        color: #d1fae5;
        font-size: 16px;
        max-width: 700px;
        line-height: 1.6;
    }
    
    /* ALERT MESSAGES */
    .alert {
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-left: 5px solid;
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border-left-color: #10b981;
    }
    
    .alert-error {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border-left-color: #ef4444;
    }
    
    /* CONTENT GRID */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    @media (min-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    /* BORROW FORM CARD */
    .borrow-form-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .form-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .form-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .form-icon {
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
    
    /* FORM STYLES */
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.3s;
        background: #f8fafc;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        background: white;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    @media (max-width: 640px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    /* BUTTON STYLES */
    .btn-primary {
        width: 100%;
        padding: 18px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 6px 15px rgba(59, 130, 246, 0.3);
        margin-top: 10px;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transform: translateY(-2px);
        box-shadow: 0 12px 20px rgba(37, 99, 235, 0.4);
    }
    
    .btn-primary:active {
        transform: translateY(0);
    }
    
    /* RESOURCES CARD */
    .resources-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .resources-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .resources-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .resources-icon {
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
    
    /* RESOURCES LIST */
    .resources-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
        max-height: 600px;
        overflow-y: auto;
        padding-right: 10px;
    }
    
    .resource-item {
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
        border-left: 4px solid #0ea5e9;
        transition: all 0.3s ease;
    }
    
    .resource-item:hover {
        background: #f1f5f9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .resource-title {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .resource-description {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 8px;
        line-height: 1.5;
    }
    
    .resource-quantity {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 13px;
        color: #10b981;
        font-weight: 600;
    }
    
    .resource-quantity.warning {
        color: #f59e0b;
    }
    
    .resource-quantity.low {
        color: #ef4444;
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
        
        .borrow-form-card,
        .resources-card {
            padding: 30px 20px;
        }
        
        .content-grid {
            gap: 20px;
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
    .resources-list::-webkit-scrollbar {
        width: 6px;
    }
    
    .resources-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .resources-list::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }
    
    .resources-list::-webkit-scrollbar-thumb:hover {
        background: #a1a1a1;
    }
    
    /* Loading overlay */
    #loadingOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    #loadingOverlay.active {
        display: flex;
    }
    
    .loading-spinner {
        width: 60px;
        height: 60px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

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
                <a href="borrow.php" class="nav-link active">
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
                <a href="/barangay_resource/backend/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- SUCCESS MESSAGE -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" id="successMessage">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Borrow request submitted successfully!</strong><br>
                    Your request has been received and is now pending admin review. You'll be notified once it's approved.
                </div>
            </div>
        <?php endif; ?>

        <!-- ERROR MESSAGES -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" id="errorMessages">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin-top: 10px; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> Borrow Resources</h1>
            <p>Request to borrow barangay resources for community activities. All requests are subject to availability and admin approval.</p>
        </div>

        <div class="content-grid">
            <!-- BORROW FORM -->
            <div class="borrow-form-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h2>Submit Borrow Request</h2>
                </div>

                <form method="POST" id="borrowForm">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-box"></i> Select Resource
                        </label>
                        <select name="resource_id" id="resourceSelect" required>
                            <option value="">Choose a resource...</option>
                            <?php foreach ($resources as $resource): 
                                $quantity_class = '';
                                if ($resource['available_quantity'] <= 3) {
                                    $quantity_class = 'low';
                                } elseif ($resource['available_quantity'] <= 10) {
                                    $quantity_class = 'warning';
                                }
                            ?>
                            <option value="<?php echo $resource['id']; ?>" 
                                    data-available="<?php echo $resource['available_quantity']; ?>"
                                    data-name="<?php echo clean($resource['name']); ?>">
                                <?php echo clean($resource['name']); ?> 
                                (Available: <span class="resource-quantity <?php echo $quantity_class; ?>"><?php echo $resource['available_quantity']; ?></span>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-hashtag"></i> Quantity Needed
                        </label>
                        <input type="number" name="quantity" id="quantityInput" min="1" value="1" placeholder="Enter quantity" required>
                        <small style="display: block; margin-top: 8px; color: #64748b;">
                            <i class="fas fa-info-circle"></i> Maximum available: <span id="maxQuantity">1</span>
                        </small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-calendar-alt"></i> Date of Use
                            </label>
                            <input type="date" name="date_of_use" id="dateOfUse" min="<?php echo $min_use_date; ?>" value="<?php echo $min_use_date; ?>" required>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-calendar-check"></i> Return Date
                            </label>
                            <input type="date" name="return_date" id="returnDate" min="<?php echo $min_return_date; ?>" value="<?php echo $min_return_date; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-bullseye"></i> Purpose of Use (Optional)
                        </label>
                        <textarea name="purpose" placeholder="Describe what you need the resource for, event details, or specific requirements..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Borrow Request
                    </button>
                </form>
            </div>

            <!-- AVAILABLE RESOURCES -->
            <div class="resources-card">
                <div class="resources-header">
                    <div class="resources-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h2>Available Resources</h2>
                </div>
                
                <div class="resources-list">
                    <?php foreach ($resources as $resource): 
                        $quantity_class = '';
                        $icon = 'fa-check-circle';
                        
                        if ($resource['available_quantity'] <= 3) {
                            $quantity_class = 'low';
                            $icon = 'fa-exclamation-triangle';
                        } elseif ($resource['available_quantity'] <= 10) {
                            $quantity_class = 'warning';
                            $icon = 'fa-exclamation-circle';
                        }
                    ?>
                        <div class="resource-item">
                            <div class="resource-title">
                                <i class="fas fa-box"></i>
                                <?php echo clean($resource['name']); ?>
                            </div>
                            <div class="resource-description">
                                <?php echo clean($resource['description']); ?>
                            </div>
                            <div class="resource-quantity <?php echo $quantity_class; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                                Available: <?php echo $resource['available_quantity']; ?> / <?php echo $resource['total_quantity']; ?>
                            </div>
                            <?php if ($resource['available_quantity'] <= 3): ?>
                                <div style="font-size: 12px; color: #ef4444; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Low stock - Limited availability
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
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
            const form = document.getElementById('borrowForm');
            const submitBtn = document.getElementById('submitBtn');
            const resourceSelect = document.getElementById('resourceSelect');
            const quantityInput = document.getElementById('quantityInput');
            const dateOfUse = document.getElementById('dateOfUse');
            const returnDate = document.getElementById('returnDate');
            const maxQuantitySpan = document.getElementById('maxQuantity');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Update max quantity when resource is selected
            function updateMaxQuantity() {
                const selectedOption = resourceSelect.options[resourceSelect.selectedIndex];
                if (selectedOption.value) {
                    const available = parseInt(selectedOption.getAttribute('data-available'));
                    maxQuantitySpan.textContent = available;
                    quantityInput.max = available;
                    quantityInput.placeholder = `Enter quantity (max: ${available})`;
                    
                    // Reset quantity if it exceeds available
                    if (parseInt(quantityInput.value) > available) {
                        quantityInput.value = available;
                    }
                } else {
                    maxQuantitySpan.textContent = '1';
                    quantityInput.max = 1;
                    quantityInput.placeholder = 'Enter quantity';
                }
            }

            // Update date validation
            dateOfUse.addEventListener('change', function() {
                if (this.value) {
                    const useDate = new Date(this.value);
                    const nextDay = new Date(useDate);
                    nextDay.setDate(nextDay.getDate() + 1);
                    returnDate.min = nextDay.toISOString().split('T')[0];
                    
                    // If return date is before new min, update it
                    if (returnDate.value && new Date(returnDate.value) < nextDay) {
                        returnDate.value = nextDay.toISOString().split('T')[0];
                    }
                }
            });

            // Form submission handler
            form.addEventListener('submit', function(e) {
                // Show loading overlay
                loadingOverlay.classList.add('active');
                
                // Validate resource selection
                if (!resourceSelect.value) {
                    e.preventDefault();
                    loadingOverlay.classList.remove('active');
                    Swal.fire({
                        icon: 'error',
                        title: 'Resource Required',
                        text: 'Please select a resource to borrow.',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }
                
                // Validate quantity
                const selectedOption = resourceSelect.options[resourceSelect.selectedIndex];
                const available = parseInt(selectedOption.getAttribute('data-available'));
                const quantity = parseInt(quantityInput.value);
                
                if (quantity <= 0) {
                    e.preventDefault();
                    loadingOverlay.classList.remove('active');
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Quantity',
                        text: 'Quantity must be greater than zero.',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }
                
                if (quantity > available) {
                    e.preventDefault();
                    loadingOverlay.classList.remove('active');
                    Swal.fire({
                        icon: 'error',
                        title: 'Quantity Exceeds Available',
                        text: `Only ${available} items available. Please reduce quantity.`,
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }
                
                // Validate dates
                const useDate = new Date(dateOfUse.value);
                const retDate = new Date(returnDate.value);
                
                if (retDate <= useDate) {
                    e.preventDefault();
                    loadingOverlay.classList.remove('active');
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Dates',
                        text: 'Return date must be after the date of use.',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }
                
                // Change button to loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // Allow form submission
            });

            // Update max quantity when resource changes
            resourceSelect.addEventListener('change', updateMaxQuantity);

            // Initialize max quantity
            updateMaxQuantity();

            // Auto-hide success message after 5 seconds
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 5000);
            }

            // Auto-hide error messages after 10 seconds
            const errorMessages = document.getElementById('errorMessages');
            if (errorMessages) {
                setTimeout(() => {
                    errorMessages.style.display = 'none';
                }, 10000);
            }

            // If page was reloaded after error, scroll to form
            <?php if (!empty($errors)): ?>
                window.scrollTo({
                    top: form.offsetTop - 100,
                    behavior: 'smooth'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>