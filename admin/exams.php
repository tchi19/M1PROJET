<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('admin');

$user = get_auth_user();
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Get filter values
$filter_department = $_GET['department_id'] ?? '';
$filter_formation = $_GET['formation_id'] ?? '';

// Get departments and formations for filters
$departments = $conn->query("SELECT id, name FROM departements ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$formations = $conn->query("SELECT f.id, f.name, f.department_id FROM formations f ORDER BY f.name")->fetch_all(MYSQLI_ASSOC);

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_department)) {
    $where_clauses[] = "d.id = ?";
    $params[] = $filter_department;
    $types .= 'i';
}
if (!empty($filter_formation)) {
    $where_clauses[] = "f.id = ?";
    $params[] = $filter_formation;
    $types .= 'i';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
        f.name as formation_name, f.id as formation_id,
        d.name as department_name, d.id as department_id,
        s.name as room_name, s.capacity,
        COUNT(DISTINCT CASE 
            WHEN e.group_number = 0 THEN i.student_id
            WHEN et.group_number = e.group_number THEN i.student_id
            ELSE NULL
        END) as enrolled_count,
        (SELECT u.full_name FROM surveillances sv 
         JOIN professeurs p ON sv.prof_id = p.id 
         JOIN users u ON p.user_id = u.id 
         WHERE sv.exam_id = e.id LIMIT 1) as invigilator_name
        FROM examens e
        JOIN modules m ON e.module_id = m.id
        JOIN formations f ON m.formation_id = f.id
        JOIN departements d ON f.department_id = d.id
        JOIN salles s ON e.room_id = s.id
        LEFT JOIN inscriptions i ON m.id = i.module_id AND i.status = 'active'
        LEFT JOIN etudiants et ON i.student_id = et.id
        $where_sql
        GROUP BY e.id
        ORDER BY d.name, f.name, e.exam_date, e.start_time";

if (count($params) > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $exams = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Group exams by department and formation
$grouped_exams = [];
foreach ($exams as $exam) {
    $dept_name = $exam['department_name'];
    $form_name = $exam['formation_name'];
    if (!isset($grouped_exams[$dept_name])) {
        $grouped_exams[$dept_name] = [];
    }
    if (!isset($grouped_exams[$dept_name][$form_name])) {
        $grouped_exams[$dept_name][$form_name] = [];
    }
    $grouped_exams[$dept_name][$form_name][] = $exam;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete') {
        $exam_id = $_POST['id'] ?? '';
        
        if (!empty($exam_id)) {
            $conn->query("DELETE FROM surveillances WHERE exam_id = $exam_id");
            $conn->query("DELETE FROM conflicts WHERE exam_id = $exam_id");
            
            $sql = "DELETE FROM examens WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $exam_id);
            
            if ($stmt->execute()) {
                header("Location: exams.php?msg=Exam deleted successfully");
                exit();
            } else {
                $error = 'Error deleting exam';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-form .form-group { margin-bottom: 0; }
        .group-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.75rem 1rem; margin: 1.5rem 0 0.5rem 0; border-radius: 8px; font-weight: 600; }
        .formation-header { background: #f8f9fa; padding: 0.5rem 1rem; margin: 0.5rem 0; border-left: 4px solid #667eea; font-weight: 500; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Admin</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?> (Admin)</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="departments.php">Departments</a>
        <a href="formations.php">Formations</a>
        <a href="modules.php">Modules</a>
        <a href="professors.php">Professors</a>
        <a href="rooms.php">Exam Rooms</a>
        <a href="exams.php" class="active">Exams</a>
        <a href="scheduling.php">Schedule Exams</a>
        <a href="conflicts.php">Conflicts</a>
        <a href="users.php">Manage Users</a>
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
                    <h1 class="card-title">Exam Schedule</h1>
                </div>
                <div class="card-body">
                    <!-- Filter Form -->
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select" id="filterDepartment" onchange="filterFormations()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Formation</label>
                            <select name="formation_id" class="form-select" id="filterFormation">
                                <option value="">All Formations</option>
                                <?php foreach ($formations as $form): ?>
                                    <option value="<?php echo $form['id']; ?>" 
                                            data-department="<?php echo $form['department_id']; ?>"
                                            <?php echo $filter_formation == $form['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($form['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="exams.php" class="btn btn-secondary">Clear</a>
                        <a href="scheduling.php" class="btn btn-success">Generate New Schedule</a>
                    </form>

                    <?php if (count($exams) > 0): ?>
                        <?php foreach ($grouped_exams as $dept_name => $formations_group): ?>
                            <div class="group-header"><?php echo htmlspecialchars($dept_name); ?></div>
                            
                            <?php foreach ($formations_group as $form_name => $form_exams): ?>
                                <div class="formation-header"><?php echo htmlspecialchars($form_name); ?> (<?php echo count($form_exams); ?> exams)</div>
                                
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Module</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Room</th>
                                            <th>Invigilator</th>
                                            <th>Enrolled / Capacity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($form_exams as $exam): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($exam['module_code']); ?></strong><br>
                                                    <?php echo htmlspecialchars($exam['module_name']); ?>
                                                    <?php if ($exam['group_number'] > 0): ?>
                                                        <br><span class="badge badge-info">Group <?php echo $exam['group_number']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($exam['exam_date'])); ?></td>
                                                <td><?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($exam['room_name']); ?></td>
                                                <td><?php echo $exam['invigilator_name'] ? htmlspecialchars($exam['invigilator_name']) : '<em>Not assigned</em>'; ?></td>
                                                <td>
                                                    <?php echo $exam['enrolled_count']; ?> / <?php echo $exam['capacity']; ?>
                                                    <?php if ($exam['enrolled_count'] > $exam['capacity']): ?>
                                                        <span class="badge badge-danger">OVER CAPACITY</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-primary"><?php echo ucfirst($exam['status']); ?></span></td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $exam['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No exams found. <?php if ($filter_department || $filter_formation): ?>Try clearing the filters or <?php endif; ?><a href="scheduling.php">Generate a schedule</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function filterFormations() {
        const deptSelect = document.getElementById('filterDepartment');
        const formSelect = document.getElementById('filterFormation');
        const selectedDept = deptSelect.value;
        
        for (let option of formSelect.options) {
            if (option.value === '') {
                option.style.display = '';
            } else if (selectedDept === '' || option.dataset.department === selectedDept) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
                if (option.selected) {
                    formSelect.value = '';
                }
            }
        }
    }
    // Run on page load
    filterFormations();
    </script>
</body>
</html>
