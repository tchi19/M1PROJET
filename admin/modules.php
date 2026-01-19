<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('admin');

$user = get_auth_user();
$action = $_GET['action'] ?? '';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $post_action = $_POST['action'];
        
        if ($post_action == 'add') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $formation_id = $_POST['formation_id'] ?? '';
            $professeur_id = $_POST['professeur_id'] ?? '';
            $credit_hours = $_POST['credit_hours'] ?? '';
            $semester = $_POST['semester'] ?? '';
            
            if (empty($name) || empty($code) || empty($formation_id) || empty($professeur_id)) {
                $error = 'Module name, code, formation and professor are required';
            } else {
                // Check formation module count limit (max 9)
                $count_sql = "SELECT COUNT(*) as count FROM modules WHERE formation_id = ?";
                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param("i", $formation_id);
                $count_stmt->execute();
                $module_count = $count_stmt->get_result()->fetch_assoc()['count'];
                
                if ($module_count >= 9) {
                    $error = 'This formation already has 9 modules (maximum allowed). Cannot add more.';
                } else {
                    $sql = "INSERT INTO modules (name, code, formation_id, professeur_id, credit_hours, semester) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssiiii", $name, $code, $formation_id, $professeur_id, $credit_hours, $semester);
                    
                    if ($stmt->execute()) {
                        header("Location: modules.php?msg=Module added successfully");
                        exit();
                    } else {
                        $error = 'Error adding module: ' . $conn->error;
                    }
                }
            }
        } elseif ($post_action == 'edit') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $formation_id = $_POST['formation_id'] ?? '';
            $professeur_id = $_POST['professeur_id'] ?? '';
            $credit_hours = $_POST['credit_hours'] ?? '';
            $semester = $_POST['semester'] ?? '';
            
            if (empty($id) || empty($name) || empty($code) || empty($formation_id) || empty($professeur_id)) {
                $error = 'All required fields must be filled';
            } else {
                $sql = "UPDATE modules SET name = ?, code = ?, formation_id = ?, professeur_id = ?, credit_hours = ?, semester = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiiiii", $name, $code, $formation_id, $professeur_id, $credit_hours, $semester, $id);
                
                if ($stmt->execute()) {
                    header("Location: modules.php?msg=Module updated successfully");
                    exit();
                } else {
                    $error = 'Error updating module: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'delete') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                $error = 'Module ID is required';
            } else {
                // Check if formation will have at least 6 modules after deletion
                $check_sql = "SELECT formation_id FROM modules WHERE id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();
                
                if ($result) {
                    $formation_id = $result['formation_id'];
                    $count_sql = "SELECT COUNT(*) as count FROM modules WHERE formation_id = ?";
                    $count_stmt = $conn->prepare($count_sql);
                    $count_stmt->bind_param("i", $formation_id);
                    $count_stmt->execute();
                    $module_count = $count_stmt->get_result()->fetch_assoc()['count'];
                    
                    if ($module_count <= 6) {
                        $error = 'Cannot delete. Formation must have at least 6 modules.';
                    } else {
                        $sql = "DELETE FROM modules WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $id);
                        
                        if ($stmt->execute()) {
                            header("Location: modules.php?msg=Module deleted successfully");
                            exit();
                        } else {
                            $error = 'Error deleting module: ' . $conn->error;
                        }
                    }
                } else {
                    $error = 'Module not found';
                }
            }
        }
    }
}

// Get modules with professor name
$sql = "SELECT m.*, f.name as formation_name, d.name as department_name, u.full_name as professor_name 
        FROM modules m 
        JOIN formations f ON m.formation_id = f.id 
        JOIN departements d ON f.department_id = d.id 
        JOIN professeurs p ON m.professeur_id = p.id
        JOIN users u ON p.user_id = u.id
        ORDER BY m.name";
$modules = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get formations for dropdown
$sql = "SELECT m.*, d.name as department_name FROM formations m JOIN departements d ON m.department_id = d.id ORDER BY m.name";
$formations = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get professors for dropdown
$sql = "SELECT p.id, u.full_name, d.name as department_name 
        FROM professeurs p 
        JOIN users u ON p.user_id = u.id 
        JOIN departements d ON p.department_id = d.id 
        ORDER BY u.full_name";
$professors = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get module to edit
$edit_mod = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM modules WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_mod = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modules - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
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
        <a href="modules.php" class="active">Modules</a>
        <a href="professors.php">Professors</a>
        <a href="students.php">Students</a>
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
                    <h1 class="card-title"><?php echo ($action == 'edit' && $edit_mod) ? 'Edit Module' : 'Add New Module'; ?></h1>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo ($action == 'edit' && $edit_mod) ? 'edit' : 'add'; ?>">
                        <?php if ($action == 'edit' && $edit_mod): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_mod['id']; ?>">
                        <?php endif; ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Module Name *</label>
                                <input type="text" name="name" class="form-control" required placeholder="Enter module name" 
                                       value="<?php echo $edit_mod ? htmlspecialchars($edit_mod['name']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Module Code *</label>
                                <input type="text" name="code" class="form-control" required placeholder="e.g., CS101" 
                                       value="<?php echo $edit_mod ? htmlspecialchars($edit_mod['code']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Formation *</label>
                                <select name="formation_id" class="form-select" required>
                                    <option value="">Select a formation</option>
                                    <?php foreach ($formations as $form): ?>
                                        <option value="<?php echo $form['id']; ?>" 
                                                <?php echo ($edit_mod && $edit_mod['formation_id'] == $form['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($form['name']); ?> (<?php echo htmlspecialchars($form['department_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Professor *</label>
                                <select name="professeur_id" class="form-select" required>
                                    <option value="">Select a professor</option>
                                    <?php foreach ($professors as $prof): ?>
                                        <option value="<?php echo $prof['id']; ?>" 
                                                <?php echo ($edit_mod && $edit_mod['professeur_id'] == $prof['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prof['full_name']); ?> (<?php echo htmlspecialchars($prof['department_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Credit Hours</label>
                                <input type="number" name="credit_hours" class="form-control" placeholder="e.g., 3"
                                       value="<?php echo $edit_mod && $edit_mod['credit_hours'] ? $edit_mod['credit_hours'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Semester</label>
                                <input type="number" name="semester" class="form-control" placeholder="e.g., 1"
                                       value="<?php echo $edit_mod && $edit_mod['semester'] ? $edit_mod['semester'] : ''; ?>">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="modules.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Modules List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($modules) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Formation</th>
                                    <th>Professor</th>
                                    <th>Department</th>
                                    <th>Credits</th>
                                    <th>Semester</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $mod): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($mod['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mod['name']); ?></td>
                                        <td><?php echo htmlspecialchars($mod['formation_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mod['professor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mod['department_name']); ?></td>
                                        <td><?php echo $mod['credit_hours'] ?? 'N/A'; ?></td>
                                        <td><?php echo $mod['semester'] ?? 'N/A'; ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $mod['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $mod['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No modules found. <a href="?action=add">Add one</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
