<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('admin');

$user = get_auth_user();
$stats = get_statistics();
$action = $_GET['action'] ?? '';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Calculate Professor Assignment Statistics
// 1. Get total number of active professors
$total_profs_result = $conn->query("SELECT COUNT(*) as count FROM professeurs");
$total_profs = $total_profs_result->fetch_assoc()['count'];

// 2. Get total number of surveillances assigned
$total_assignments_result = $conn->query("SELECT COUNT(*) as count FROM surveillances");
$total_assignments = $total_assignments_result->fetch_assoc()['count'];

// 3. Calculate Average (Moyenne)
$average_assignments = ($total_profs > 0) ? round($total_assignments / $total_profs, 2) : 0;

// 4. Count Professors Above and Below/Equal Average
// We need to count assignments per professor first
$sql_dist = "SELECT p.id, COUNT(s.id) as assignment_count 
             FROM professeurs p
             LEFT JOIN surveillances s ON p.id = s.prof_id
             GROUP BY p.id";
$dist_result = $conn->query($sql_dist);

$count_above_avg = 0;
$count_below_avg = 0;

while ($row = $dist_result->fetch_assoc()) {
    if ($row['assignment_count'] > $average_assignments) {
        $count_above_avg++;
    } else {
        $count_below_avg++; // Consider equal as below/at par for this partition
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Exam Timetable Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Admin</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?> (Admin)</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="departments.php">Departments</a>
        <a href="formations.php">Formations</a>
        <a href="modules.php">Modules</a>
        <a href="professors.php">Professors</a>

        <a href="rooms.php">Exam Rooms</a>
        <a href="exams.php">Exams</a>
        <a href="scheduling.php">Schedule Exams</a>
        <a href="conflicts.php">Conflicts</a>
        <a href="users.php">Manage Users</a>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if ($msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Admin Dashboard</h1>
                </div>
                <div class="card-body">
                    <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
                    <p>Manage all aspects of the exam timetable system using the menu on the left.</p>
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
                        <h3><?php echo $stats['total_professors']; ?></h3>
                        <p>Professors</p>
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
                        <h3><?php echo $stats['total_modules']; ?></h3>
                        <p>Modules</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_exams']; ?></h3>
                        <p>Exams</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_rooms']; ?></h3>
                        <p>Exam Rooms</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box" style="border-top-color: #e74c3c;">
                        <h3><?php echo $stats['active_conflicts']; ?></h3>
                        <p>Active Conflicts</p>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h2 class="card-title">Assignment Statistics</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <div class="stat-box" style="border-top-color: #3498db;">
                                <h3><?php echo $average_assignments; ?></h3>
                                <p>Average Exams / Professor</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box" style="border-top-color: #f39c12;">
                                <h3><?php echo $count_above_avg; ?></h3>
                                <p>Profs Above Average</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box" style="border-top-color: #2ecc71;">
                                <h3><?php echo $count_below_avg; ?></h3>
                                <p>Profs Below/At Average</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <a href="departments.php?action=add" class="btn btn-primary">Add Department</a>
                        <a href="formations.php?action=add" class="btn btn-primary">Add Formation</a>
                        <a href="modules.php?action=add" class="btn btn-primary">Add Module</a>
                        <a href="rooms.php?action=add" class="btn btn-primary">Add Exam Room</a>
                        <a href="scheduling.php" class="btn btn-success">Generate Schedule</a>
                        <a href="conflicts.php" class="btn btn-warning">View Conflicts</a>
                        <a href="seed_professors.php" class="btn btn-secondary"
                            onclick="return confirm('This will create 30 professors per department. Continue?')">Professor
                            Populate</a>
                        <a href="seed_modules.php" class="btn btn-secondary"
                            onclick="return confirm('This will create 6-9 modules per formation. Continue?')">Module
                            Populate</a>
                        <a href="seed_data.php" class="btn btn-secondary"
                            onclick="return confirm('This will create 13,000 students. Continue?')">Student Populate</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>