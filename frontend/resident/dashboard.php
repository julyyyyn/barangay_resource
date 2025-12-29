<?php
include '../../backend/db.php';
include '../../backend/auth.php';

$user_id = $_SESSION['user_id'];

// USER INFO
$user = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT first_name FROM users WHERE user_id=$user_id"));

// COUNTS
$borrowed = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM borrow_requests WHERE user_id=$user_id AND status='approved'"))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM borrow_requests WHERE user_id=$user_id AND status='pending'"))['total'];

$donations = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM donations WHERE user_id=$user_id"))['total'];

$notifications = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM notifications WHERE user_id=$user_id AND is_read=0"))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resident Dashboard | BRMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../frontend/resident/dashboard.css">
</head>

<body>

<div class="dashboard">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">
    <img src="../assets/logo.png" alt="Barangay Logo">
    <span>BRMS</span>
  </div>

  <ul>
    <li class="active">Dashboard</li>
    <li>Borrow</li>
    <li>Donations</li>
    <li>Profile</li>
    <li class="logout"><a href="../backend/logout.php">Logout</a></li>
  </ul>
</aside>

<!-- MAIN -->
<main class="main">

<header class="topbar">
  <h1>Welcome, <?= htmlspecialchars($user['first_name']) ?></h1>
</header>

<!-- STATS -->
<section class="stats">
  <div class="stat-card animate">
    <h3><?= $borrowed ?></h3>
    <p>Borrowed Items</p>
  </div>

  <div class="stat-card animate">
    <h3><?= $pending ?></h3>
    <p>Pending Requests</p>
  </div>

  <div class="stat-card animate">
    <h3><?= $donations ?></h3>
    <p>Donations</p>
  </div>

  <div class="stat-card animate">
    <h3><?= $notifications ?></h3>
    <p>Notifications</p>
  </div>
</section>

<!-- RECENT -->
<section class="panel">
  <h2>Recent Notifications</h2>
  <?php
  $notif = mysqli_query($conn,
  "SELECT message FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 5");

  if (mysqli_num_rows($notif) == 0) {
      echo "<p class='empty'>No notifications</p>";
  } else {
      while ($row = mysqli_fetch_assoc($notif)) {
          echo "<div class='notif'>{$row['message']}</div>";
      }
  }
  ?>
</section>

</main>
</div>

</body>
</html>
