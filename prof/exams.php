<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role(['prof']);

$user = get_auth_user();
$professor = get_professor_by_user_id($user['id']);
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Check if this professor is a chef of any department
$is_chef = is_chef_de_departement($user['id']);

// Get professor's surveillance assignments (exams they supervise)
$surveillances = get_professor_surveillances($professor['id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exam Supervisions - Professor Dashboard</title>
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
        <a href="dashboard.php">My Dashboard</a>
        <a href="exams.php" class="active">My Supervisions</a>
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
            <?php if ($msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- ========== NORMAL PROFESSOR VIEW: Header ========== -->
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">My Exam Supervisions</h1>
                </div>
                <div class="card-body">
                    <p>Total Supervision Assignments: <strong><?php echo count($surveillances); ?></strong></p>
                </div>
            </div>

            <!-- ========== SURVEILLANCE ASSIGNMENTS ========== -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Supervisions List</h2>
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
                        <p>No supervision assignments yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>