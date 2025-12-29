<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard | BRMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../frontend/admin/admin-dashboard.css">
</head>

<body>

<div class="dashboard">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">
    <span>BRMS Admin</span>
  </div>

  <ul>
    <li class="active">Dashboard</li>
    <li>Manage Requests</li>
    <li>Resources</li>
    <li>Residents</li>
    <li>Reports</li>
    <li class="logout">Logout</li>
  </ul>
</aside>

<!-- MAIN -->
<main class="main">

<header class="topbar">
  <h1>Admin Dashboard</h1>
  <span class="sub">Barangay Resource Management System</span>
</header>

<!-- STATS -->
<section class="stats">
  <div class="stat-card">
    <h3>124</h3>
    <p>Total Resources</p>
  </div>

  <div class="stat-card">
    <h3>37</h3>
    <p>Active Borrowings</p>
  </div>

  <div class="stat-card">
    <h3>89</h3>
    <p>Total Donations</p>
  </div>

  <div class="stat-card alert">
    <h3>6</h3>
    <p>Pending Requests</p>
  </div>
</section>

<!-- PANELS -->
<section class="grid">

  <div class="panel">
    <h2>Recent Requests</h2>
    <div class="row">Juan Dela Cruz — Chairs (Pending)</div>
    <div class="row">Maria Santos — Tables (Approved)</div>
    <div class="row">Pedro Reyes — Sound System</div>
  </div>

  <div class="panel">
    <h2>Quick Actions</h2>
    <button>View All Requests</button>
    <button>Add Resource</button>
    <button>Generate Report</button>
  </div>

</section>

</main>
</div>

</body>
</html>
