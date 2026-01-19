<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('student');

$user = get_auth_user();
$student = get_student_by_user_id($user['id']);

// Get student's enrolled modules
$modules = get_student_modules($student['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Modules - Student Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Student</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">My Timetable</a>
        <a href="modules.php" class="active">My Modules</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">My Enrolled Modules</h1>
                </div>
                <div class="card-body">
                    <p>Total Modules: <strong><?php echo count($modules); ?></strong></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Module List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($modules) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Formation</th>
                                    <th>Department</th>
                                    <th>Credits</th>
                                    <th>Semester</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($module['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($module['name']); ?></td>
                                        <td><?php echo htmlspecialchars($module['formation_name']); ?></td>
                                        <td><?php echo htmlspecialchars($module['department_name']); ?></td>
                                        <td><?php echo $module['credit_hours'] ?? 'N/A'; ?></td>
                                        <td><?php echo $module['semester'] ?? 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>You have no enrolled modules yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
