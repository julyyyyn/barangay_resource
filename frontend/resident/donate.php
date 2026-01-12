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

// Handle donation submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $item_name = trim(clean($_POST['item_name']));
    $quantity = (int) $_POST['quantity'];
    $description = trim(clean($_POST['description']));

    // Basic validation
    if (empty($item_name)) {
        $errors[] = "Item name is required.";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than zero.";
    }

    if (empty($description)) {
        $errors[] = "Description is required.";
    }

    // Photo validation
    if (!isset($_FILES['item_photo']) || $_FILES['item_photo']['error'] !== 0) {
        $errors[] = "Item photo is required.";
    } else {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['item_photo']['type'];
        $fileSize = $_FILES['item_photo']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only JPG and PNG images are allowed.";
        }

        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "Image size must not exceed 10MB.";
        }
    }

    // If no errors, save donation
    if (empty($errors)) {

        $uploadDir = "../uploads/donations/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExt = pathinfo($_FILES['item_photo']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid("donation_") . "." . $fileExt;
        $filePath = $uploadDir . $fileName;

        move_uploaded_file($_FILES['item_photo']['tmp_name'], $filePath);

        $stmt = $conn->prepare("
            INSERT INTO donations (user_id, item_name, quantity, description, photo, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$userId, $item_name, $quantity, $description, $fileName]);

        header('Location: donate.php?success=1');
        exit();
    }
}

// Fetch available resources
$resources = $conn->query("
    SELECT * FROM resources 
    WHERE available_quantity > 0 
    ORDER BY name
")->fetchAll();
?>

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

// Handle donation submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $item_name = trim(clean($_POST['item_name']));
    $quantity = (int) $_POST['quantity'];
    $description = trim(clean($_POST['description']));

    // Basic validation
    if (empty($item_name)) {
        $errors[] = "Item name is required.";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than zero.";
    }

    if (empty($description)) {
        $errors[] = "Description is required.";
    }

    // Photo validation
    if (!isset($_FILES['item_photo']) || $_FILES['item_photo']['error'] !== 0) {
        $errors[] = "Item photo is required.";
    } else {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = $_FILES['item_photo']['type'];
        $fileSize = $_FILES['item_photo']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only JPG and PNG images are allowed.";
        }

        if ($fileSize > 2 * 1024 * 1024) {
            $errors[] = "Image size must not exceed 2MB.";
        }
    }

    // If no errors, save donation
    if (empty($errors)) {

        $uploadDir = "../uploads/donations/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExt = pathinfo($_FILES['item_photo']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid("donation_") . "." . $fileExt;
        $filePath = $uploadDir . $fileName;

        move_uploaded_file($_FILES['item_photo']['tmp_name'], $filePath);

        $stmt = $conn->prepare("
            INSERT INTO donations (user_id, item_name, quantity, description, photo, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$userId, $item_name, $quantity, $description, $fileName]);

        header('Location: donate.php?success=1');
        exit();
    }
}

// Fetch available resources
$resources = $conn->query("
    SELECT * FROM resources 
    WHERE available_quantity > 0 
    ORDER BY name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donate Resources - Barangay Resource Management</title>
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
    
    /* DONATION FORM CARD */
    .donation-form-card {
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
        min-height: 120px;
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
        color: #3b82f6;
        font-weight: 600;
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
        
        .donation-form-card,
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
                <a href="donate.php" class="nav-link active">
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
                <a href="/barangay_resource/backend/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- SUCCESS MESSAGE -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Donation submitted successfully!</strong><br>
                    Your donation has been received and is now pending admin review. You'll be notified once it's approved.
                </div>
            </div>
        <?php endif; ?>

        <!-- ERROR MESSAGES -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
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
            <h1><i class="fas fa-hand-holding-heart"></i> Donate Resources</h1>
            <p>Help your community by donating items or resources. Your contributions will support neighbors in need.</p>
        </div>

        <div class="content-grid">
            <!-- DONATION FORM -->
            <div class="donation-form-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h2>Submit Donation</h2>
                </div>

                <form method="POST" enctype="multipart/form-data">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-tag"></i> Item Name
                        </label>
                        <input type="text" name="item_name" placeholder="Enter item name (e.g., Rice, Canned Goods)" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-hashtag"></i> Quantity
                        </label>
                        <input type="number" name="quantity" min="1" placeholder="Number of items" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea name="description" placeholder="Describe the item, condition, and any important details..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-camera"></i> Item Photo
                        </label>
                        <input type="file" name="item_photo" accept="image/png,image/jpeg" required>
                        <small style="display: block; margin-top: 8px; color: #64748b;">
                            <i class="fas fa-info-circle"></i> Only JPG and PNG images allowed (max 2MB)
                        </small>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Donation
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
                    <?php foreach ($resources as $resource): ?>
                        <div class="resource-item">
                            <div class="resource-title">
                                <i class="fas fa-box"></i>
                                <?php echo clean($resource['name']); ?>
                            </div>
                            <div class="resource-description">
                                <?php echo clean($resource['description']); ?>
                            </div>
                            <div class="resource-quantity">
                                <i class="fas fa-check-circle"></i>
                                Available: <?php echo $resource['available_quantity']; ?>
                            </div>
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
        // Simple form validation for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('.btn-primary');
            
            form.addEventListener('submit', function(e) {
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                submitBtn.disabled = true;
                
                // The actual form validation is handled by PHP
            });
            
            // Add focus effects to form inputs
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#3b82f6';
                    this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.borderColor = '#e2e8f0';
                    this.style.boxShadow = 'none';
                });
            });
        });
    </script>
</body>
</html>