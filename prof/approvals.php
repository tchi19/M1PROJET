<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role(['prof']);

$user = get_auth_user();

// Check if user is a chef de departement
if (!is_chef_de_departement($user['id'])) {
    header("Location: exams.php");
    exit();
}

$professor = get_professor_by_user_id($user['id']);
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Check if this professor is a chef of any department
$dept_id = null;
$dept_name = null;
$is_chef = false;

if ($professor) {
    $dept_sql = "SELECT d.id, d.name FROM departements d WHERE d.chef_id = ?";
    $dept_stmt = $conn->prepare($dept_sql);
    $dept_stmt->bind_param("i", $professor['id']);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();

    if ($dept_result->num_rows > 0) {
        $dept_row = $dept_result->fetch_assoc();
        $dept_id = $dept_row['id'];
        $dept_name = $dept_row['name'];
        $is_chef = true;
    }
}

// Chef-specific: Handle approvals
$dept_exams = [];
$pending_count = 0;

// Handle approval actions
if ($dept_id && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['action'])) {
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
                header("Location: approvals.php?msg=" . urlencode($msg_text));
                exit();
            } else {
                $error = 'Error updating exam approval status';
            }
        }
    } elseif (isset($_POST['bulk_action']) && isset($_POST['exam_ids']) && is_array($_POST['exam_ids'])) {
        $bulk_action = $_POST['bulk_action'];
        if (in_array($bulk_action, ['approve_all', 'reject_all'])) {
            $approved = ($bulk_action == 'approve_all') ? 1 : 0;
            $ids = $_POST['exam_ids'];

            // Create a secure string of placeholders
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $sql = "UPDATE examens e
                    JOIN modules m ON e.module_id = m.id
                    JOIN formations f ON m.formation_id = f.id
                    SET e.accepted_by_chefdep = ?
                    WHERE e.id IN ($placeholders) AND f.department_id = ?";

            $stmt = $conn->prepare($sql);

            // Bind parameters: 1 for approved, X for IDs, 1 for dept_id
            $types = "i" . str_repeat('i', count($ids)) . "i";
            $params = array_merge([$approved], $ids, [$dept_id]);

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $count = $stmt->affected_rows;
                $msg_text = "$count exam(s) " . ($approved ? 'approved' : 'rejected') . " successfully";
                header("Location: approvals.php?msg=" . urlencode($msg_text));
                exit();
            } else {
                $error = 'Error updating bulk approval status';
            }
        }
    }
}

// Get department exams with approval status
if ($dept_id) {
    // Fetch formations for filter dropdown
    $formations_sql = "SELECT id, name FROM formations WHERE department_id = ? ORDER BY name";
    $formations_stmt = $conn->prepare($formations_sql);
    $formations_stmt->bind_param("i", $dept_id);
    $formations_stmt->execute();
    $formations = $formations_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Handle Filter
    $filter_formation = $_GET['formation_id'] ?? '';

    $exams_sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
                f.name as formation_name, s.name as room_name, s.capacity,
                e.accepted_by_chefdep, e.accepted_by_doyen
                FROM examens e
                JOIN modules m ON e.module_id = m.id
                JOIN formations f ON m.formation_id = f.id
                JOIN departements d ON f.department_id = d.id
                JOIN salles s ON e.room_id = s.id
                WHERE d.id = ?";

    $params = array($dept_id);
    $types = "i";

    if (!empty($filter_formation)) {
        $exams_sql .= " AND f.id = ?";
        $params[] = $filter_formation;
        $types .= "i";
    }

    $exams_sql .= " ORDER BY e.accepted_by_chefdep IS NULL DESC, e.exam_date ASC, e.start_time ASC";

    $exams_stmt = $conn->prepare($exams_sql);
    $exams_stmt->bind_param($types, ...$params);
    $exams_stmt->execute();
    $dept_exams = $exams_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Count pending approvals
    foreach ($dept_exams as $exam) {
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
    <title>Exam Approvals - Chef de Département</title>
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
    </style>
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

        <?php if ($is_chef): ?>
            <div class="sidebar-divider">Department Management</div>
            <a href="approvals.php" class="active">Exam Approvals</a>
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

            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">
                        Exam Approvals
                        <span class="chef-tag">Chef de Département</span>
                    </h1>
                </div>
                <div class="card-body">
                    <?php if (!$dept_id): ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Account Configuration Issue</strong>
                            <p>You are logged in with a "Chef de Département" role, but you haven't been assigned to lead
                                any department yet.</p>
                            <p>Please contact an Administrator to assign you as the head of your department.</p>
                        </div>
                    <?php else: ?>
                        <p>Department: <strong><?php echo htmlspecialchars($dept_name); ?></strong></p>
                        <p>Total Exams: <strong><?php echo count($dept_exams); ?></strong></p>
                        <?php if ($pending_count > 0): ?>
                            <p style="color: #856404;"><strong><?php echo $pending_count; ?></strong> exam(s) pending your
                                approval</p>
                        <?php endif; ?>

                        <form method="GET" class="filter-form"
                            style="display: flex; gap: 10px; align-items: center; margin-top: 1rem;">
                            <label>Filter by Formation:</label>
                            <select name="formation_id" class="form-select" style="width: auto;">
                                <option value="">All Formations</option>
                                <?php foreach ($formations as $formation): ?>
                                    <option value="<?php echo $formation['id']; ?>" <?php echo ($filter_formation == $formation['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($formation['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">Filter</button>
                            <?php if ($filter_formation): ?>
                                <a href="approvals.php" class="btn btn-secondary"
                                    style="padding: 0.3rem 0.8rem; text-decoration: none;">Reset</a>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($dept_id && count($dept_exams) > 0): ?>
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title" style="margin: 0;">Pending & Processed Exams</h2>
                        <?php if ($pending_count > 0): ?>
                            <div>
                                <button type="submit" form="bulkForm" name="bulk_action" value="approve_all" class="btn-approve"
                                    onclick="return confirm('Approve all selected exams?')">Approve Selected</button>
                                <button type="submit" form="bulkForm" name="bulk_action" value="reject_all" class="btn-reject"
                                    onclick="return confirm('Reject all selected exams?')">Reject Selected</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="bulkForm">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">
                                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                        </th>
                                        <th>Module</th>
                                        <th>Formation</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Room</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dept_exams as $exam): ?>
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
                                            <td style="text-align: center;">
                                                <?php if ($exam['accepted_by_chefdep'] === null): ?>
                                                    <input type="checkbox" name="exam_ids[]" value="<?php echo $exam['id']; ?>"
                                                        class="exam-checkbox">
                                                <?php else: ?>
                                                    <input type="checkbox" disabled>
                                                <?php endif; ?>
                                            </td>
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
                                                <?php if ($exam['accepted_by_chefdep'] === null): ?>
                                                    <!-- Single Actions use separate buttons that submit to same page but handled by original logic -->
                                                    <!-- Note: We cannot nest forms. We will use buttons that submit the main form with specific action/id 
                                                         BUT that requires JS modification or changing the backend logic to accept single action from main form.
                                                         To preserve the existing logic which expects 'exam_id' and 'action', we can use button onclick to set hidden fields.
                                                    -->
                                                    <button type="button" class="btn-approve"
                                                        onclick="submitSingleAction(<?php echo $exam['id']; ?>, 'approve')">✓</button>
                                                    <button type="button" class="btn-reject"
                                                        onclick="submitSingleAction(<?php echo $exam['id']; ?>, 'reject')">✗</button>
                                                <?php else: ?>
                                                    <em style="color: #6c757d;">Decided</em>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <!-- Hidden inputs for single action -->
                            <input type="hidden" name="action" id="singleActionInput" value="">
                            <input type="hidden" name="exam_id" id="singleExamIdInput" value="">
                        </form>
                    </div>
                </div>
                <script>
                    function toggleSelectAll(source) {
                        checkboxes = document.querySelectorAll('.exam-checkbox');
                        for (var i = 0, n = checkboxes.length; i < n; i++) {
                            checkboxes[i].checked = source.checked;
                        }
                    }

                    function submitSingleAction(id, action) {
                        if (confirm(action.charAt(0).toUpperCase() + action.slice(1) + ' this exam?')) {
                            document.getElementById('singleExamIdInput').value = id;
                            document.getElementById('singleActionInput').value = action;
                            document.getElementById('bulkForm').submit();
                        }
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>