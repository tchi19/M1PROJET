<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('chef');

$user = get_auth_user();
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Find department_id (via professeurs table)
$dept_sql = "SELECT d.id FROM departements d 
             JOIN professeurs p ON d.chef_id = p.id 
             WHERE p.user_id = ?";
$dept_stmt = $conn->prepare($dept_sql);
$dept_stmt->bind_param("i", $user['id']);
$dept_stmt->execute();
$result = $dept_stmt->get_result();

$dept_id = null;
$exams = [];
$pending_count = 0;

if ($result->num_rows > 0) {
    $dept = $result->fetch_assoc();
    $dept_id = $dept['id'];
}

// Handle approval actions
if ($dept_id && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $exam_id = $_POST['exam_id'] ?? '';
    $action = $_POST['action'];

    if (!empty($exam_id) && in_array($action, ['approve', 'reject'])) {
        $approved = ($action == 'approve') ? 1 : 0;
        $sql = "UPDATE examens e
                JOIN modules m ON e.module_id = m.id
                JOIN formations f ON m.formation_id = f.id
                SET e.accepted_by_chefdep = ?
                WHERE e.id = ? AND f.department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $approved, $exam_id, $dept_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg_text = $action == 'approve' ? 'Exam approved successfully' : 'Exam rejected';
            header("Location: manage_exams.php?msg=" . urlencode($msg_text));
            exit();
        } else {
            $error = 'Error updating exam approval status';
        }
    }
}

// Get exams with approval status if dept found
if ($dept_id) {
    $exams_sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
                f.name as formation_name, s.name as room_name, s.capacity,
                e.accepted_by_chefdep, e.accepted_by_doyen
                FROM examens e
                JOIN modules m ON e.module_id = m.id
                JOIN formations f ON m.formation_id = f.id
                JOIN departements d ON f.department_id = d.id
                JOIN salles s ON e.room_id = s.id
                WHERE d.id = ?
                ORDER BY e.accepted_by_chefdep IS NULL DESC, e.exam_date DESC, e.start_time DESC";

    $exams_stmt = $conn->prepare($exams_sql);
    $exams_stmt->bind_param("i", $dept_id);
    $exams_stmt->execute();
    $exams = $exams_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Count pending approvals
    foreach ($exams as $exam) {
        if ($exam['accepted_by_chefdep'] === null)
            $pending_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Exams - Approval</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .approval-pending {
            background-color: #fff3cd;
        }

        .approval-approved {
            background-color: #d4edda;
        }

        .approval-rejected {
            background-color: #f8d7da;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 0.25rem;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Chef de Département</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?> (Chef)</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">My Dashboard</a>
        <a href="exams.php">My Exams</a>
        <a href="surveillances.php">My Surveillances</a>
        <a href="modules.php">My Modules</a>

        <div class="sidebar-divider">Department Management</div>
        <a href="manage_exams.php" class="active">Department Exams</a>
        <a href="manage_formations.php">Formations</a>
        <a href="manage_conflicts.php">Conflicts</a>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if ($msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$dept_id): ?>
                <div class="alert alert-warning"
                    style="background-color: #fff3cd; color: #856404; padding: 1rem; border: 1px solid #ffeeba; border-radius: 4px;">
                    <strong>⚠️ Account Configuration Issue</strong>
                    <p>You are logged in with a "Chef de Département" role, but you haven't been assigned to lead any
                        department yet.</p>
                    <p>Please contact an Administrator to assign you as the head of your department.</p>
                </div>
            <?php else: ?>

                <div class="card">
                    <div class="card-header">
                        <h1 class="card-title">Department Exams</h1>
                    </div>
                    <div class="card-body">
                        <p>Total: <strong><?php echo count($exams); ?></strong> exams</p>
                        <?php if ($pending_count > 0): ?>
                            <p style="color: #856404;"><strong><?php echo $pending_count; ?></strong> exams pending your
                                approval</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Exam Schedule & Approval</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($exams) > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Formation</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Room</th>
                                        <th>Chef Approval</th>
                                        <th>Doyen Approval</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): ?>
                                        <?php
                                        $row_class = '';
                                        if ($exam['accepted_by_chefdep'] === null)
                                            $row_class = 'approval-pending';
                                        elseif ($exam['accepted_by_chefdep'] == 1)
                                            $row_class = 'approval-approved';
                                        else
                                            $row_class = 'approval-rejected';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($exam['module_code']); ?></strong><br>
                                                <?php echo htmlspecialchars($exam['module_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($exam['formation_name']); ?></td>
                                            <td><?php echo date('d/m/Y (D)', strtotime($exam['exam_date'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($exam['room_name']); ?></td>
                                            <td>
                                                <?php if ($exam['accepted_by_chefdep'] === null): ?>
                                                    <span class="badge badge-warning">Pending</span>
                                                <?php elseif ($exam['accepted_by_chefdep'] == 1): ?>
                                                    <span class="badge badge-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['accepted_by_doyen'] === null): ?>
                                                    <span class="badge badge-secondary">Pending</span>
                                                <?php elseif ($exam['accepted_by_doyen'] == 1): ?>
                                                    <span class="badge badge-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['accepted_by_chefdep'] === null): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn-approve"
                                                            onclick="return confirm('Approve this exam?')">✓ Approve</button>
                                                        <button type="submit" name="action" value="reject" class="btn-reject"
                                                            onclick="return confirm('Reject this exam?')">✗ Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <em style="color: #6c757d;">Decided</em>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No exams scheduled for your department.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>