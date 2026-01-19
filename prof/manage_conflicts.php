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

// Get conflicts
$conflicts = get_conflicts_by_department($dept_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Conflicts</title>
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
        <a href="manage_formations.php">Formations</a>
        <a href="manage_conflicts.php" class="active">Conflicts</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Department Conflicts</h1>
                </div>
                <div class="card-body">
                    <?php
                    // Calculate statistics
                    $stats = [
                        'prof_overload' => 0,
                        'formation_overload' => 0,
                        'room_capacity' => 0,
                        'unused_rooms' => 0
                    ];

                    foreach ($conflicts as $c) {
                        if ($c['conflict_type'] == 'prof_overload') {
                            $stats['prof_overload']++;
                        } elseif ($c['conflict_type'] == 'student_overlap') {
                            $stats['formation_overload']++;
                        } elseif ($c['conflict_type'] == 'room_capacity') {
                            // Distinguish between capacity violation (high/medium) and unused rooms (low)
                            // or verify based on description if possible. 
                            // Previous logic set unused rooms to 'low' severity.
                            // Capacity violation is usually 'high'.
                            if ($c['severity'] == 'low') {
                                $stats['unused_rooms']++;
                            } else {
                                $stats['room_capacity']++;
                            }
                        }
                    }
                    ?>

                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div class="stat-box"
                            style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #e74c3c;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;">
                                <?php echo $stats['prof_overload']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Professor Overload</p>
                            <small style="color: #95a5a6;">(> 3 exams/day)</small>
                        </div>
                        <div class="stat-box"
                            style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #e67e22;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;">
                                <?php echo $stats['formation_overload']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Formation Overload</p>
                            <small style="color: #95a5a6;">(> 1 exam/day)</small>
                        </div>
                        <div class="stat-box"
                            style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #f1c40f;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;">
                                <?php echo $stats['room_capacity']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Room Capacity</p>
                            <small style="color: #95a5a6;">(Students > Seats)</small>
                        </div>
                        <div class="stat-box"
                            style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #3498db;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;">
                                <?php echo $stats['unused_rooms']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Unused Rooms</p>
                            <small style="color: #95a5a6;">(No exams scheduled)</small>
                        </div>
                    </div>

                    <p style="margin-top: 1.5rem;">Total: <strong>
                            <?php echo count($conflicts); ?>
                        </strong> unresolved conflicts</p>
                </div>
            </div>

            <?php if (count($conflicts) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Conflicts List</h2>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Module</th>

                                    <th>Description</th>
                                    <th>Date Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conflicts as $conflict): ?>
                                    <tr>
                                        <td>
                                            <?php echo str_replace('_', ' ', strtoupper($conflict['conflict_type'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($conflict['module_code'] ?? 'N/A'); ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($conflict['description']); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($conflict['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card" style="border-top: 4px solid #27ae60; text-align: center;">
                    <div class="card-body" style="padding: 3rem;">
                        <h2 style="color: #27ae60; margin-bottom: 1rem;">✓ No Conflicts</h2>
                        <p>Your department's schedule is conflict-free!</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>