<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('doyen');

$user = get_auth_user();
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle conflict resolution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'resolve') {
        $conflict_id = $_POST['id'] ?? '';
        
        if (!empty($conflict_id)) {
            $sql = "UPDATE conflicts SET resolved = TRUE WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $conflict_id);
            
            if ($stmt->execute()) {
                header("Location: conflicts.php?msg=Conflict marked as resolved");
                exit();
            } else {
                $error = 'Error resolving conflict';
            }
        }
    }
}

// Get filter value
$filter_type = $_GET['type'] ?? '';

// Query Construction - Doyen sees ALL conflicts
$all_conflicts_sql = "SELECT c.*, m.name as module_name, m.code as module_code, e.exam_date, e.start_time
            FROM conflicts c
            LEFT JOIN examens e ON c.exam_id = e.id
            LEFT JOIN modules m ON e.module_id = m.id
            LEFT JOIN formations f ON m.formation_id = f.id
            WHERE c.resolved = FALSE";

if (!empty($filter_type)) {
    $all_conflicts_sql .= " AND c.conflict_type = '" . $conn->real_escape_string($filter_type) . "'";
}
// Sort by Severity then Date
$all_conflicts_sql .= " ORDER BY c.severity DESC, c.created_at DESC";

$all_conflicts = $conn->query($all_conflicts_sql)->fetch_all(MYSQLI_ASSOC);

// Get unique conflict types for filter dropdown
$types_result = $conn->query("SELECT DISTINCT conflict_type FROM conflicts WHERE resolved = FALSE ORDER BY conflict_type");
$conflict_types = $types_result->fetch_all(MYSQLI_ASSOC);

// Statistics Calculation
$stats = [
    'prof_overload' => 0,
    'student_overlap' => 0, // 'formation_overload'
    'room_capacity' => 0,
    'unused_rooms' => 0
];

foreach ($all_conflicts as $c) {
    if ($c['conflict_type'] == 'prof_overload') {
        $stats['prof_overload']++;
    } elseif ($c['conflict_type'] == 'student_overlap') {
        $stats['student_overlap']++;
    } elseif ($c['conflict_type'] == 'room_capacity') {
        if ($c['severity'] == 'low') {
            $stats['unused_rooms']++;
        } else {
            $stats['room_capacity']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conflicts - Doyen Dashboard</title>
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
        <a href="statistics.php">Statistics</a>
        <a href="conflicts.php" class="active">Conflicts</a>
        <a href="exams.php">All Exams</a>
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
                    <h1 class="card-title">Scheduling Conflicts</h1>
                </div>
                <div class="card-body">
                    <!-- Statistics Dashboard (Matching Chef Style) -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; margin-bottom: 2rem;">
                        <div class="stat-box" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #e74c3c;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;"><?php echo $stats['prof_overload']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Professor Overload</p>
                            <small style="color: #95a5a6;">(> 3 exams/day)</small>
                        </div>
                        <div class="stat-box" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #e67e22;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;"><?php echo $stats['student_overlap']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Formation Overload</p>
                            <small style="color: #95a5a6;">(> 1 exam/day)</small>
                        </div>
                        <div class="stat-box" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #f1c40f;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;"><?php echo $stats['room_capacity']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Room Capacity</p>
                            <small style="color: #95a5a6;">(Students > Seats)</small>
                        </div>
                        <div class="stat-box" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #3498db;">
                            <h3 style="margin: 0; font-size: 1.5rem; color: #2c3e50;"><?php echo $stats['unused_rooms']; ?></h3>
                            <p style="margin: 0.5rem 0 0; color: #7f8c8d;">Unused Rooms</p>
                            <small style="color: #95a5a6;">(No exams scheduled)</small>
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Conflict Type</label>
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($conflict_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['conflict_type']); ?>" 
                                            <?php echo $filter_type == $type['conflict_type'] ? 'selected' : ''; ?>>
                                        <?php echo str_replace('_', ' ', ucwords($type['conflict_type'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="conflicts.php" class="btn btn-secondary">Clear</a>
                    </form>
                    
                    <p>Total Unresolved Conflicts: <strong><?php echo count($all_conflicts); ?></strong></p>
                </div>
            </div>

            <?php if (count($all_conflicts) > 0): ?>
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
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_conflicts as $conflict): ?>
                                    <tr>
                                        <td>
                                            <?php echo str_replace('_', ' ', strtoupper($conflict['conflict_type'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($conflict['module_code'] ?? 'N/A'); ?></td>

                                        <td><?php echo htmlspecialchars($conflict['description']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($conflict['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="resolve">
                                                <input type="hidden" name="id" value="<?php echo $conflict['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                                            </form>
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
                        <h2 style="color: #27ae60; margin-bottom: 1rem;">✓ No Active Conflicts</h2>
                        <p>All scheduling conflicts have been resolved!</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
