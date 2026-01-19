<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('doyen');

$user = get_auth_user();

// Get global statistics
$stats = get_statistics();

// Get department breakdown
$dept_sql = "SELECT d.id, d.name,
            (SELECT COUNT(*) FROM formations WHERE department_id = d.id) as formations,
            (SELECT COUNT(DISTINCT e.id) FROM etudiants e JOIN formations f ON e.formation_id = f.id WHERE f.department_id = d.id) as students,
            (SELECT COUNT(*) FROM professeurs WHERE department_id = d.id) as professors,
            (SELECT COUNT(*) FROM examens e JOIN modules m ON e.module_id = m.id JOIN formations f ON m.formation_id = f.id WHERE f.department_id = d.id) as exams
            FROM departements d
            ORDER BY d.name";

$dept_stats = $conn->query($dept_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Doyen Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Doyen</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?> (Doyen)</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="statistics.php" class="active">Statistics</a>
        <a href="conflicts.php">Conflicts</a>
        <a href="exams.php">All Exams</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">System Statistics</h1>
                </div>
            </div>

            <div class="row">
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_departments']; ?></h3>
                        <p>Departments</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_formations']; ?></h3>
                        <p>Formations</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_modules']; ?></h3>
                        <p>Modules</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p>Students</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_professors']; ?></h3>
                        <p>Professors</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_rooms']; ?></h3>
                        <p>Exam Rooms</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_exams']; ?></h3>
                        <p>Scheduled Exams</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box" style="border-top-color: #e74c3c;">
                        <h3><?php echo $stats['active_conflicts']; ?></h3>
                        <p>Active Conflicts</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Statistics by Department</h2>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Formations</th>
                                <th>Students</th>
                                <th>Professors</th>
                                <th>Exams</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_stats as $dept): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                    <td><?php echo $dept['formations']; ?></td>
                                    <td><?php echo $dept['students']; ?></td>
                                    <td><?php echo $dept['professors']; ?></td>
                                    <td><?php echo $dept['exams']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
