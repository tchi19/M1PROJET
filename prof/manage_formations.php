<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('prof');

if (!is_chef_de_departement($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$user = get_auth_user();

// Find department_id (via professeurs table)
$dept_sql = "SELECT d.id FROM departements d 
             JOIN professeurs p ON d.chef_id = p.id 
             WHERE p.user_id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param("i", $user['id']);
$dept_stmt->execute();
$dept = $dept_stmt->get_result()->fetch_assoc();
$dept_id = $dept['id'];

// Get formations
$formations_sql = "SELECT f.*, 
                   (SELECT COUNT(*) FROM modules WHERE formation_id = f.id) as modules,
                   (SELECT COUNT(DISTINCT e.id) FROM etudiants e WHERE formation_id = f.id) as students
                   FROM formations f
                   WHERE department_id = ?
                   ORDER BY f.name";

$formations_stmt = $conn->prepare($formations_sql);
$formations_stmt->bind_param("i", $dept_id);
$formations_stmt->execute();
$formations = $formations_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Formations</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Chef de Département</div>
        <div class="navbar-menu">
            <span>
                <?php echo htmlspecialchars($user['full_name']); ?> (Chef)
            </span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">My Dashboard</a>
        <a href="exams.php">My Supervisions</a>
        <a href="modules.php">My Modules</a>

        <div class="sidebar-divider">Department Management</div>
        <a href="approvals.php">Exam Approvals</a>
        <a href="manage_formations.php" class="active">Formations</a>
        <a href="manage_conflicts.php">Conflicts</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Department Formations</h1>
                </div>
                <div class="card-body">
                    <p>Total: <strong>
                            <?php echo count($formations); ?>
                        </strong> formations</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formations List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($formations) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Modules</th>
                                    <th>Students</th>
                                    <th>Duration</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($formations as $form): ?>
                                    <tr>
                                        <td><strong>
                                                <?php echo htmlspecialchars($form['name']); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo $form['modules']; ?>
                                        </td>
                                        <td>
                                            <?php echo $form['students']; ?>
                                        </td>
                                        <td>
                                            <?php echo $form['duration_months'] ? $form['duration_months'] . ' months' : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($form['description'] ?? 'N/A'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No formations in your department.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>