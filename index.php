<?php
require_once 'includes/session.php';

if (!is_logged_in()) {
    header("Location: auth/login.php");
    exit();
}

$user = get_auth_user();

// Redirect based on role
$redirects = array(
    'admin' => 'admin/dashboard.php',
    'doyen' => 'doyen/dashboard.php',
    'chef' => 'prof/dashboard.php',
    'prof' => 'prof/dashboard.php',
    'student' => 'student/dashboard.php'
);

if (isset($redirects[$user['role']])) {
    header("Location: " . $redirects[$user['role']]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Timetable Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management</div>
        <div class="navbar-menu">
            <a href="#" onclick="logout()"  >Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Welcome <?php echo htmlspecialchars($user['full_name']); ?></h1>
            </div>
            <div class="card-body">
                <p>You are logged in as a <strong><?php echo ucfirst($user['role']); ?></strong></p>
                <p>You will be redirected to your dashboard shortly...</p>
            </div>
        </div>
    </div>
</body>
</html>
