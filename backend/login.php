<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Check if user exists
    $query = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {

        $user = mysqli_fetch_assoc($result);

        // Verify password
        if (password_verify($password, $user['password'])) {

            // Start session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];

            // Redirect based on role
            if ($user['role_id'] == 1) {
                header("Location: ../frontend/admin/admin_dashboard.php");
            } else {
                header("Location: ../frontend/resident/dashboard.php");
            }
            exit();

        } else {
            // Wrong password
            header("Location: ../frontend/login.html?error=invalid");
            exit();
        }

    } else {
        // Email not found
        header("Location: ../frontend/login.html?error=invalid");
        exit();
    }

} else {
    header("Location: ../frontend/login.html");
    exit();
}
