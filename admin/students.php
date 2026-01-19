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
$search = trim($_GET['search'] ?? '');

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
if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR e.student_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM etudiants e
              JOIN users u ON e.user_id = u.id
              JOIN formations f ON e.formation_id = f.id
              JOIN departements d ON f.department_id = d.id
              $where_sql";

if (count($params) > 0) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_students = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_students = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_students / $per_page);

// Get students
$sql = "SELECT e.id, e.student_number, e.enrollment_date, 
        u.full_name, u.email, u.phone,
        f.name as formation_name, f.id as formation_id,
        d.name as department_name, d.id as department_id
        FROM etudiants e
        JOIN users u ON e.user_id = u.id
        JOIN formations f ON e.formation_id = f.id
        JOIN departements d ON f.department_id = d.id
        $where_sql
        ORDER BY d.name, f.name, u.full_name
        LIMIT $per_page OFFSET $offset";

if (count($params) > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $students = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-form .form-group { margin-bottom: 0; min-width: 150px; }
        .search-input { min-width: 250px; }
        .pagination { display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .active { background: #667eea; color: white; border-color: #667eea; }
        .stats-bar { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; gap: 2rem; flex-wrap: wrap; }
        .stats-bar .stat { font-size: 0.9rem; }
        .stats-bar .stat strong { color: #667eea; }
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
        <a href="students.php" class="active">Students</a>
        <a href="rooms.php">Exam Rooms</a>
        <a href="exams.php">Exams</a>
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
                    <h1 class="card-title">Students List</h1>
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
                        <div class="form-group search-input">
                            <label class="form-label">Search (Name or Student Number)</label>
                            <input type="text" name="search" class="form-control" placeholder="Enter name or student number..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="students.php" class="btn btn-secondary">Clear</a>
                    </form>

                    <!-- Stats Bar -->
                    <div class="stats-bar">
                        <div class="stat">Total Results: <strong><?php echo number_format($total_students); ?></strong></div>
                        <div class="stat">Page: <strong><?php echo $page; ?> / <?php echo max(1, $total_pages); ?></strong></div>
                        <div class="stat">Showing: <strong><?php echo count($students); ?></strong> students</div>
                    </div>

                    <?php if (count($students) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Formation</th>
                                    <th>Enrollment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['student_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['formation_name']); ?></td>
                                        <td><?php echo $student['enrollment_date'] ? date('d/m/Y', strtotime($student['enrollment_date'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php 
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $query_string = http_build_query($query_params);
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo $query_string; ?>&page=1">« First</a>
                                    <a href="?<?php echo $query_string; ?>&page=<?php echo $page - 1; ?>">‹ Prev</a>
                                <?php endif; ?>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($p = $start_page; $p <= $end_page; $p++): 
                                ?>
                                    <?php if ($p == $page): ?>
                                        <span class="active"><?php echo $p; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo $query_string; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo $query_string; ?>&page=<?php echo $page + 1; ?>">Next ›</a>
                                    <a href="?<?php echo $query_string; ?>&page=<?php echo $total_pages; ?>">Last »</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No students found. <?php if ($filter_department || $filter_formation || $search): ?>Try adjusting your filters.<?php else: ?>Use the <a href="seed_data.php">Student Populate</a> tool to add students.<?php endif; ?></p>
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
    filterFormations();
    </script>
</body>
</html>
