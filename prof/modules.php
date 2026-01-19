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

// Get professor's modules
$modules = get_professor_modules($professor['id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Modules - Professor Dashboard</title>
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
        <a href="exams.php">My Supervisions</a>
        <a href="modules.php" class="active">My Modules</a>

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
                    <h1 class="card-title">My Teaching Modules</h1>
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
                        <p>No modules assigned to you yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>