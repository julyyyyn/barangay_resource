<?php
// Include database connection
include 'db.php'; // Make sure this path is correct

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get form values and sanitize
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $password   = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact_no']);
    $province   = mysqli_real_escape_string($conn, $_POST['province']);
    $city       = mysqli_real_escape_string($conn, $_POST['city']);
    $barangay   = mysqli_real_escape_string($conn, $_POST['barangay']);
    $purok      = mysqli_real_escape_string($conn, $_POST['purok']);
    $role_id    = 2; // Resident role
    $status     = 'Pending'; // If admin approval is needed

    // Combine full address
    $address = $province . ', ' . $city . ', ' . $barangay . ', ' . $purok;

    // Validate password
    if($password !== $confirm_password){
        echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
        exit();
    }

    // Password length validation (minimum 8 characters)
    if (strlen($password) < 8) {
    echo "<script>
        alert('Password must be at least 8 characters long.');
        window.history.back();
    </script>";
    exit();
    }

    // Check if email already exists
    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if(mysqli_num_rows($check) > 0){
        echo "<script>alert('Email already registered.'); window.history.back();</script>";
        exit();
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into database
    $insert = mysqli_query($conn, "INSERT INTO users 
    (first_name, last_name, email, password, contact_no, province, city, barangay, purok, role_id, status) 
    VALUES 
    ('$first_name','$last_name','$email','$hashed_password','$contact_no','$province','$city','$barangay','$purok','$role_id','$status')");


    if ($insert) {
        header("Location: ../frontend/login.html?registered=success");
        exit();
    } else {
        echo "<script>alert('Registration failed. Please try again.'); window.history.back();</script>";
        exit();
    }

} else {
    // If accessed directly, redirect to registration page
    header('Location: ../frontend/register.html');
    exit();
}
?>
