<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('doyen');

$user = get_auth_user();
$stats = get_statistics();

// Get department statistics
$departments_sql = "SELECT d.id, d.name FROM departements d ORDER BY d.name";
$departments = $conn->query($departments_sql)->fetch_all(MYSQLI_ASSOC);

// Get all conflicts
$all_conflicts = get_all_conflicts();

// Get room occupancy
$occupancy_sql = "SELECT s.id, s.name, s.capacity, COUNT(DISTINCT e.id) as exam_count,
                   ROUND((COUNT(DISTINCT e.id) / (SELECT COUNT(*) FROM examens)) * 100, 1) as usage_percent
                   FROM salles s
                   LEFT JOIN examens e ON s.id = e.room_id
                   GROUP BY s.id, s.name, s.capacity
                   ORDER BY usage_percent DESC";
$occupancy = $conn->query($occupancy_sql)->fetch_all(MYSQLI_ASSOC);

// Get workload by professor
$prof_workload_sql = "SELECT u.full_name, COUNT(DISTINCT e.id) as exam_count, COUNT(DISTINCT sv.id) as surveillance_count
                      FROM professeurs p
                      JOIN users u ON p.user_id = u.id
                      LEFT JOIN modules m ON p.id = m.professeur_id
                      LEFT JOIN examens e ON m.id = e.module_id
                      LEFT JOIN surveillances sv ON p.id = sv.prof_id
                      GROUP BY p.id, u.full_name
                      ORDER BY exam_count DESC
                      LIMIT 10";
$prof_workload = $conn->query($prof_workload_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doyen Dashboard - Exam Timetable</title>
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
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="statistics.php">Statistics</a>
        <a href="conflicts.php">Conflicts</a>
        <a href="exams.php">All Exams</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Doyen Dashboard</h1>
                </div>
                <div class="card-body">
                    <p>Welcome, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                    <p>Global overview of the exam timetable management system</p>
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
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p>Students</p>
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

            <div class="row">
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_formations']; ?></h3>
                        <p>Formations</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_professors']; ?></h3>
                        <p>Professors</p>
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
                        <h3><?php echo $stats['total_rooms']; ?></h3>
                        <p>Exam Rooms</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Room Occupancy Rate</h2>
                </div>
                <div class="card-body">
                    <?php if (count($occupancy) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Capacity</th>
                                    <th>Exams</th>
                                    <th>Usage %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($occupancy as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['name']); ?></td>
                                        <td><?php echo $room['capacity']; ?> seats</td>
                                        <td><?php echo $room['exam_count']; ?></td>
                                        <td>
                                            <div
                                                style="background: #ecf0f1; border-radius: 4px; overflow: hidden; height: 20px;">
                                                <div
                                                    style="background: #3498db; height: 100%; width: <?php echo min($room['usage_percent'], 100); ?>%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem;">
                                                    <?php echo $room['usage_percent']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Top 10 Professor Workload</h2>
                </div>
                <div class="card-body">
                    <?php if (count($prof_workload) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Professor</th>
                                    <th>Exams to Teach</th>
                                    <th>Surveillance Assignments</th>
                                    <th>Total Load</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prof_workload as $prof): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prof['full_name']); ?></td>
                                        <td><?php echo $prof['exam_count']; ?></td>
                                        <td><?php echo $prof['surveillance_count']; ?></td>
                                        <td><strong><?php echo ($prof['exam_count'] + $prof['surveillance_count']); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="statistics.php" class="btn btn-primary">View Statistics</a>
                        <a href="conflicts.php" class="btn btn-warning">Review Conflicts</a>
                        <a href="exams.php" class="btn btn-secondary">View All Exams</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>