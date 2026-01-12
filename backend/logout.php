<?php
session_start();

// Unset all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: /barangay_resource/frontend/login.html?logout=success");
exit();
