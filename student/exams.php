<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('student');

$user = get_auth_user();
$student = get_student_by_user_id($user['id']);

if (!$student) {
    header("Location: ../auth/logout.php");
    exit();
}

// Get student's formation info
$formation_sql = "SELECT f.*, d.name as department_name FROM formations f 
                  JOIN departements d ON f.department_id = d.id 
                  WHERE f.id = ?";
$formation_stmt = $conn->prepare($formation_sql);
$formation_stmt->bind_param("i", $student['formation_id']);
$formation_stmt->execute();
$formation = $formation_stmt->get_result()->fetch_assoc();

// Get exams for student's formation (only approved exams - both chef and doyen approved)
$sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
        s.name as room_name, s.capacity, f.name as formation_name, 
        d.name as department_name, e.accepted_by_chefdep, e.accepted_by_doyen
        FROM examens e
        JOIN modules m ON e.module_id = m.id
        JOIN salles s ON e.room_id = s.id
        JOIN formations f ON m.formation_id = f.id
        JOIN departements d ON f.department_id = d.id
        WHERE f.id = ?
        AND (e.group_number = 0 OR e.group_number = ?)
        ORDER BY e.exam_date ASC, e.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student['formation_id'], $student['group_number']);
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filter to show only approved exams for display, but count all
$approved_exams = array_filter($exams, function ($e) {
    return $e['accepted_by_chefdep'] == 1 && $e['accepted_by_doyen'] == 1;
});
$pending_exams = array_filter($exams, function ($e) {
    return $e['accepted_by_chefdep'] !== 1 || $e['accepted_by_doyen'] !== 1;
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exams - Student Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .exam-approved {
            background-color: #d4edda;
        }

        .exam-pending {
            background-color: #fff3cd;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Student</div>
        <div class="navbar-menu">
            <span>
                <?php echo htmlspecialchars($user['full_name']); ?>
            </span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">My Timetable</a>
        <a href="exams.php" class="active">My Exams</a>
        <a href="modules.php">My Modules</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">My Exam Schedule</h1>
                </div>
                <div class="card-body">
                    <p>Student: <strong>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </strong></p>
                    <p>Formation: <strong>
                            <?php echo htmlspecialchars($formation['name']); ?>
                        </strong></p>
                    <p>Department: <strong>
                            <?php echo htmlspecialchars($formation['department_name']); ?>
                        </strong></p>
                    <p>Group: <strong>
                            <?php echo $student['group_number']; ?>
                        </strong></p>
                    <hr>
                    <p>Confirmed Exams: <strong style="color: #28a745;">
                            <?php echo count($approved_exams); ?>
                        </strong></p>
                    <?php if (count($pending_exams) > 0): ?>
                        <p>Pending Approval: <strong style="color: #856404;">
                                <?php echo count($pending_exams); ?>
                            </strong></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($approved_exams) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Confirmed Exams</h2>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Group</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_exams as $exam): ?>
                                    <tr class="exam-approved">
                                        <td>
                                            <?php echo htmlspecialchars($exam['module_name']); ?>
                                        </td>
                                        <td><strong>
                                                <?php echo htmlspecialchars($exam['module_code']); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo date('d/m/Y (D)', strtotime($exam['exam_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($exam['room_name']); ?>
                                        </td>
                                        <td>
                                            <?php if ($exam['group_number'] > 0): ?>
                                                <span class="badge badge-info">Group
                                                    <?php echo $exam['group_number']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">All</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($pending_exams) > 0): ?>
                <div class="card" style="border-left: 4px solid #ffc107;">
                    <div class="card-header">
                        <h2 class="card-title">Pending Approval</h2>
                    </div>
                    <div class="card-body">
                        <p style="color: #856404; margin-bottom: 1rem;">These exams are scheduled but waiting for approval
                            from department head and/or dean.</p>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_exams as $exam): ?>
                                    <tr class="exam-pending">
                                        <td>
                                            <?php echo htmlspecialchars($exam['module_name']); ?>
                                        </td>
                                        <td><strong>
                                                <?php echo htmlspecialchars($exam['module_code']); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo date('d/m/Y (D)', strtotime($exam['exam_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($exam['room_name']); ?>
                                        </td>
                                        <td>
                                            <?php if ($exam['accepted_by_chefdep'] != 1): ?>
                                                <span class="badge badge-warning">Awaiting Chef</span>
                                            <?php elseif ($exam['accepted_by_doyen'] != 1): ?>
                                                <span class="badge badge-warning">Awaiting Doyen</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($exams) == 0): ?>
                <div class="card" style="text-align: center;">
                    <div class="card-body" style="padding: 3rem;">
                        <h2 style="color: #6c757d; margin-bottom: 1rem;">No Exams Scheduled</h2>
                        <p>No exams have been scheduled for your formation yet.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>