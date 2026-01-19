<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('doyen');

$user = get_auth_user();
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['action'])) {
        $exam_id = $_POST['exam_id'] ?? '';
        $action = $_POST['action'];

        if (!empty($exam_id) && in_array($action, ['approve', 'reject'])) {
            // Only allow doyen to approve/reject if chef has already approved
            $check_sql = "SELECT accepted_by_chefdep FROM examens WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $exam_id);
            $check_stmt->execute();
            $exam_check = $check_stmt->get_result()->fetch_assoc();

            if ($exam_check && $exam_check['accepted_by_chefdep'] == 1) {
                $approved = ($action == 'approve') ? 1 : 0;
                $sql = "UPDATE examens SET accepted_by_doyen = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $approved, $exam_id);

                if ($stmt->execute()) {
                    $msg_text = $action == 'approve' ? 'Exam approved by Doyen' : 'Exam rejected by Doyen';
                    header("Location: exams.php?msg=" . urlencode($msg_text));
                    exit();
                } else {
                    $error = 'Error updating exam approval status';
                }
            } else {
                $error = 'Cannot approve/reject: Chef de Département must approve first';
            }
        }
    } elseif (isset($_POST['bulk_action']) && isset($_POST['exam_ids']) && is_array($_POST['exam_ids'])) {
        $bulk_action = $_POST['bulk_action'];
        if (in_array($bulk_action, ['approve_all', 'reject_all'])) {
            $approved = ($bulk_action == 'approve_all') ? 1 : 0;
            $ids = $_POST['exam_ids'];

            // Create placeholders for IN clause
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Logic: Update ONLY if Chef has approved. We must enforce this constraint in the query or pre-check.
            // Safest: Add WHERE accepted_by_chefdep = 1
            $sql = "UPDATE examens SET accepted_by_doyen = ? 
                    WHERE id IN ($placeholders) AND accepted_by_chefdep = 1";

            $stmt = $conn->prepare($sql);

            // Bind params
            $types = "i" . str_repeat('i', count($ids));
            $params = array_merge([$approved], $ids);

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $count = $stmt->affected_rows;
                $msg_text = "$count exam(s) " . ($approved ? 'approved' : 'rejected') . " by Doyen";
                header("Location: exams.php?msg=" . urlencode($msg_text));
                exit();
            } else {
                $error = 'Error updating bulk approval status';
            }
        }
    }
}

// Get Departments for filter
$depts_sql = "SELECT id, name FROM departements ORDER BY name";
$departments = $conn->query($depts_sql)->fetch_all(MYSQLI_ASSOC);

// Handle Filters
$filter_dept = $_GET['department_id'] ?? '';
$filter_formation = $_GET['formation_id'] ?? '';

// Get Formations for filter (dependent on dept if selected)
if (!empty($filter_dept)) {
    $formations_sql = "SELECT id, name FROM formations WHERE department_id = ? ORDER BY name";
    $stmt = $conn->prepare($formations_sql);
    $stmt->bind_param("i", $filter_dept);
    $stmt->execute();
    $formations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $formations_sql = "SELECT id, name FROM formations ORDER BY name";
    $formations = $conn->query($formations_sql)->fetch_all(MYSQLI_ASSOC);
}

// Get all exams with approval status and filters
$sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
        f.name as formation_name, d.name as department_name,
        s.name as room_name, s.capacity,
        e.accepted_by_chefdep, e.accepted_by_doyen,
        COUNT(DISTINCT CASE 
            WHEN e.group_number = 0 THEN i.student_id
            WHEN et.group_number = e.group_number THEN i.student_id
            ELSE NULL
        END) as enrolled_count
        FROM examens e
        JOIN modules m ON e.module_id = m.id
        JOIN formations f ON m.formation_id = f.id
        JOIN departements d ON f.department_id = d.id
        JOIN salles s ON e.room_id = s.id
        LEFT JOIN inscriptions i ON m.id = i.module_id AND i.status = 'active'
        LEFT JOIN etudiants et ON i.student_id = et.id
        WHERE 1=1";

$params = array();
$types = "";

if (!empty($filter_dept)) {
    $sql .= " AND d.id = ?";
    $params[] = $filter_dept;
    $types .= "i";
}

if (!empty($filter_formation)) {
    $sql .= " AND f.id = ?";
    $params[] = $filter_formation;
    $types .= "i";
}

$sql .= " GROUP BY e.id
        ORDER BY e.accepted_by_doyen IS NULL DESC, e.accepted_by_chefdep DESC, e.exam_date DESC, e.start_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count pending (chef approved, doyen pending)
$pending_count = 0;
foreach ($exams as $exam) {
    if ($exam['accepted_by_chefdep'] == 1 && $exam['accepted_by_doyen'] === null)
        $pending_count++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Exams - Doyen Dashboard</title>
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

        .waiting-chef {
            background-color: #e2e3e5;
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
        <div class="navbar-brand">Exam Timetable Management - Doyen</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?> (Doyen)</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="statistics.php">Statistics</a>
        <a href="conflicts.php">Conflicts</a>
        <a href="exams.php" class="active">All Exams</a>
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
                    <h1 class="card-title">All Scheduled Exams</h1>
                    <form method="GET" class="filter-form"
                        style="display: flex; gap: 10px; align-items: center; margin-left: auto;">
                        <select name="department_id" class="form-select" style="width: auto;"
                            onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($filter_dept == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="formation_id" class="form-select" style="width: auto;">
                            <option value="">All Formations</option>
                            <?php foreach ($formations as $formation): ?>
                                <option value="<?php echo $formation['id']; ?>" <?php echo ($filter_formation == $formation['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($formation['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">Filter</button>
                        <?php if ($filter_dept || $filter_formation): ?>
                            <a href="exams.php" class="btn btn-secondary"
                                style="padding: 0.3rem 0.8rem; text-decoration: none;">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="card-body">
                    <p>Total: <strong><?php echo count($exams); ?></strong> exams</p>
                    <?php if ($pending_count > 0): ?>
                        <p style="color: #856404;"><strong><?php echo $pending_count; ?></strong> exams pending your
                            approval (already approved by Chef)</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($exams) > 0): ?>
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title" style="margin: 0;">Exam List & Approval</h2>
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
                                        <th>Department</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Room</th>
                                        <th>Enrolled/Capacity</th>
                                        <th>Chef Approval</th>
                                        <th>Doyen Approval</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): ?>
                                        <?php
                                        $row_class = '';
                                        if ($exam['accepted_by_chefdep'] != 1) {
                                            $row_class = 'waiting-chef';
                                        } elseif ($exam['accepted_by_doyen'] === null) {
                                            $row_class = 'approval-pending';
                                        } elseif ($exam['accepted_by_doyen'] == 1) {
                                            $row_class = 'approval-approved';
                                        } else {
                                            $row_class = 'approval-rejected';
                                        }
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td style="text-align: center;">
                                                <?php if ($exam['accepted_by_chefdep'] == 1 && $exam['accepted_by_doyen'] === null): ?>
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
                                            <td><?php echo htmlspecialchars($exam['department_name']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($exam['exam_date'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($exam['room_name']); ?></td>
                                            <td>
                                                <?php echo $exam['enrolled_count']; ?> / <?php echo $exam['capacity']; ?>
                                                <?php if ($exam['enrolled_count'] > $exam['capacity']): ?>
                                                    <span class="badge badge-danger">OVER</span>
                                                <?php endif; ?>
                                            </td>
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
                                                <?php if ($exam['accepted_by_chefdep'] == 1 && $exam['accepted_by_doyen'] === null): ?>
                                                    <button type="button" class="btn-approve"
                                                        onclick="submitSingleAction(<?php echo $exam['id']; ?>, 'approve')">✓
                                                        Approve</button>
                                                    <button type="button" class="btn-reject"
                                                        onclick="submitSingleAction(<?php echo $exam['id']; ?>, 'reject')">✗
                                                        Reject</button>
                                                <?php elseif ($exam['accepted_by_chefdep'] != 1): ?>
                                                    <em style="color: #6c757d;">Awaiting Chef</em>
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
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p>No exams scheduled yet.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>