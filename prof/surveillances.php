<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role(['prof', 'chef']);

$user = get_auth_user();
$professor = get_professor_by_user_id($user['id']);

// Check if user is a chef (by assignment to department, not user role)
$is_chef = false;
if ($professor) {
    $dept_sql = "SELECT d.id FROM departements d WHERE d.chef_id = ?";
    $dept_stmt = $conn->prepare($dept_sql);
    $dept_stmt->bind_param("i", $professor['id']);
    $dept_stmt->execute();
    $is_chef = $dept_stmt->get_result()->num_rows > 0;
}

// Get professor's surveillances
$surveillances = get_professor_surveillances($professor['id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Surveillances - Professor Dashboard</title>
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
        <div class="navbar-brand">Exam Timetable Management - Professor</div>
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
        <a href="dashboard.php">My Dashboard</a>
        <a href="exams.php">My Exams</a>
        <a href="surveillances.php" class="active">My Surveillances</a>
        <a href="modules.php">My Modules</a>

        <?php if ($is_chef): ?>
            <div class="sidebar-divider">Department Management</div>
            <a href="manage_exams.php">Department Exams</a>
            <a href="manage_formations.php">Formations</a>
            <a href="manage_conflicts.php">Conflicts</a>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">My Surveillance Assignments</h1>
                </div>
                <div class="card-body">
                    <p>Total Assignments: <strong><?php echo count($surveillances); ?></strong></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Surveillance List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($surveillances) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($surveillances as $surv): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($surv['module_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($surv['module_code']); ?></strong></td>
                                        <td><?php echo date('d/m/Y (D)', strtotime($surv['exam_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($surv['start_time'])) . ' - ' . date('H:i', strtotime($surv['end_time'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($surv['room_name']); ?></td>
                                        <td><span class="badge badge-primary"><?php echo ucfirst($surv['role']); ?></span></td>
                                        <td><span class="badge badge-success"><?php echo ucfirst($surv['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No surveillance assignments yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>