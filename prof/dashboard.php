<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role(['prof', 'chef']);

$user = get_auth_user();
$professor = get_professor_by_user_id($user['id']);

if (!$professor) {
    header("Location: ../auth/logout.php");
    exit();
}

// Check if user is a chef (by assignment to department, not user role)
$is_chef = false;
$dept_id = null;
$dept_stats = null;
$pending_approvals = 0;

if ($professor) {
    $dept_sql = "SELECT d.id FROM departements d WHERE d.chef_id = ?";
    $dept_stmt = $conn->prepare($dept_sql);
    $dept_stmt->bind_param("i", $professor['id']);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();

    if ($dept_result->num_rows > 0) {
        $dept_row = $dept_result->fetch_assoc();
        $dept_id = $dept_row['id'];
        $is_chef = true;
        $dept_stats = get_department_statistics($dept_id);

        // Count pending approvals
        $pending_sql = "SELECT COUNT(*) as count FROM examens e
                       JOIN modules m ON e.module_id = m.id
                       JOIN formations f ON m.formation_id = f.id
                       WHERE f.department_id = ? AND e.accepted_by_chefdep IS NULL";
        $pending_stmt = $conn->prepare($pending_sql);
        $pending_stmt->bind_param("i", $dept_id);
        $pending_stmt->execute();
        $pending_approvals = $pending_stmt->get_result()->fetch_assoc()['count'];
    }
}

// Get professor's exams
$exams = get_professor_exams($professor['id']);

// Get professor's surveillances
$surveillances = get_professor_surveillances($professor['id']);

// Get professor's modules
$modules = get_professor_modules($professor['id']);

// Check for overload
$overload = check_professor_overload($professor['id']);

// Count exams per day
$exams_per_day = array();
foreach ($exams as $exam) {
    if (!isset($exams_per_day[$exam['exam_date']])) {
        $exams_per_day[$exam['exam_date']] = 0;
    }
    $exams_per_day[$exam['exam_date']]++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard - Exam Timetable</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .chef-tag {
            background-color: #e74c3c;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            vertical-align: middle;
            margin-left: 0.5rem;
        }

        .sidebar-divider {
            color: #95a5a6;
            padding: 1rem 1rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Portale Professor</div>
        <div class="navbar-menu">
            <span>
                <?php echo htmlspecialchars($user['full_name']); ?>
                <?php if ($is_chef): ?>
                    <span class="chef-tag">Chef de Département</span>
                <?php endif; ?>
            </span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php" class="active">My Dashboard</a>
        <a href="exams.php">My Supervisions</a>
        <a href="modules.php">My Modules</a>

        <?php if ($is_chef): ?>
            <div class="sidebar-divider">Department Management</div>
            <a href="approvals.php">Exam Approvals</a>
            <a href="manage_formations.php">Formations</a>
            <a href="manage_conflicts.php">Conflicts</a>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">
                        Professor Dashboard
                        <?php if ($is_chef): ?>
                            <span class="chef-tag">Chef de Département</span>
                        <?php endif; ?>
                    </h1>
                </div>
                <div class="card-body">
                    <p>Welcome, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                    <p>Department: <strong><?php
                    $dept = $conn->query("SELECT name FROM departements WHERE id = " . $professor['department_id'])->fetch_assoc();
                    echo htmlspecialchars($dept['name']);
                    ?></strong></p>

                    <?php if ($is_chef && $pending_approvals > 0): ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Pending Approvals!</strong>
                            <br>You have <?php echo $pending_approvals; ?> exam(s) waiting for your approval.
                            <br><a href="manage_exams.php">Go to Department Exams</a>
                        </div>
                    <?php endif; ?>

                    <?php if (count($overload) > 0): ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Schedule Overload Detected!</strong>
                            <br>You have <?php echo count($overload); ?> day(s) with more than 3 exams:
                            <?php foreach ($overload as $day): ?>
                                <br>- <?php echo date('d/m/Y', strtotime($day['exam_date'])); ?>:
                                <?php echo $day['exam_count']; ?> exams
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_chef && $dept_stats): ?>
                <h2 style="margin: 2rem 0 1rem; color: #2c3e50;">Department Overview</h2>
                <div class="row">
                    <div class="col-3">
                        <div class="stat-box" style="background-color: #34495e; color: white;">
                            <h3><?php echo $dept_stats['formations']; ?></h3>
                            <p>Formations</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-box" style="background-color: #34495e; color: white;">
                            <h3><?php echo $dept_stats['students']; ?></h3>
                            <p>Students</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-box" style="background-color: #34495e; color: white;">
                            <h3><?php echo $dept_stats['professors']; ?></h3>
                            <p>Professors</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-box" style="background-color: #34495e; color: white;">
                            <h3><?php echo $dept_stats['exams']; ?></h3>
                            <p>Dept Exams</p>
                        </div>
                    </div>
                </div>
                <hr style="margin: 2rem 0; border: 0; border-top: 1px solid #ddd;">
            <?php endif; ?>

            <h2 style="margin: 2rem 0 1rem; color: #2c3e50;">My Teaching Schedule</h2>
            <div class="row">
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo count($exams); ?></h3>
                        <p>Total Exams to Teach</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo count($surveillances); ?></h3>
                        <p>Surveillance Assignments</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo count($modules); ?></h3>
                        <p>Modules Teaching</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo count($exams_per_day); ?></h3>
                        <p>Days with Exams</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upcoming Exams (First 10)</h2>
                </div>
                <div class="card-body">
                    <?php if (count($exams) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 0;
                                foreach ($exams as $exam):
                                    if ($count >= 10)
                                        break;
                                    $count++;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['module_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($exam['module_code']); ?></strong></td>
                                        <td><?php echo date('d/m/Y (D)', strtotime($exam['exam_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($exam['room_name']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['department_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($exams) > 10): ?>
                            <p><a href="exams.php">View all exams</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No exams scheduled for you yet.</p>
                    <?php endif; ?>
                </div>
            </div>


        </div>
    </div>
</body>

</html>