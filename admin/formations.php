<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role(['admin', 'prof']);

if (has_role('prof') && !has_role('admin')) {
    if (!is_chef_de_departement($_SESSION['user_id'])) {
        header("Location: ../index.php?error=unauthorized");
        exit();
    }
}

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
            $department_id = $_POST['department_id'] ?? '';
            $duration_months = $_POST['duration_months'] ?? '';
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name) || empty($department_id)) {
                $error = 'Formation name and department are required';
            } else {
                $sql = "INSERT INTO formations (name, department_id, duration_months, description) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siss", $name, $department_id, $duration_months, $description);
                
                if ($stmt->execute()) {
                    header("Location: formations.php?msg=Formation added successfully");
                    exit();
                } else {
                    $error = 'Error adding formation: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'edit') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $department_id = $_POST['department_id'] ?? '';
            $duration_months = $_POST['duration_months'] ?? '';
            $description = trim($_POST['description'] ?? '');
            
            if (empty($id) || empty($name) || empty($department_id)) {
                $error = 'All fields are required';
            } else {
                $sql = "UPDATE formations SET name = ?, department_id = ?, duration_months = ?, description = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sissi", $name, $department_id, $duration_months, $description, $id);
                
                if ($stmt->execute()) {
                    header("Location: formations.php?msg=Formation updated successfully");
                    exit();
                } else {
                    $error = 'Error updating formation: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'delete') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                $error = 'Formation ID is required';
            } else {
                $sql = "DELETE FROM formations WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    header("Location: formations.php?msg=Formation deleted successfully");
                    exit();
                } else {
                    $error = 'Error deleting formation: ' . $conn->error;
                }
            }
        }
    }
}

// Get formations
$sql = "SELECT f.*, d.name as department_name FROM formations f JOIN departements d ON f.department_id = d.id ORDER BY f.name";
$formations = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get departments for dropdown
$sql = "SELECT * FROM departements ORDER BY name";
$departments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get formation to edit
$edit_form = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM formations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_form = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formations - Admin Dashboard</title>
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
        <a href="formations.php" class="active">Formations</a>
        <a href="modules.php">Modules</a>
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
                    <h1 class="card-title"><?php echo ($action == 'edit' && $edit_form) ? 'Edit Formation' : 'Add New Formation'; ?></h1>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo ($action == 'edit' && $edit_form) ? 'edit' : 'add'; ?>">
                        <?php if ($action == 'edit' && $edit_form): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_form['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Formation Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Enter formation name" 
                                   value="<?php echo $edit_form ? htmlspecialchars($edit_form['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select a department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo ($edit_form && $edit_form['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Duration (months)</label>
                            <input type="number" name="duration_months" class="form-control" placeholder="e.g., 24"
                                   value="<?php echo $edit_form && $edit_form['duration_months'] ? $edit_form['duration_months'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" placeholder="Enter formation description" rows="4"><?php echo $edit_form ? htmlspecialchars($edit_form['description'] ?? '') : ''; ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="formations.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
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
                                    <th>Department</th>
                                    <th>Duration (months)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($formations as $form): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($form['name']); ?></td>
                                        <td><?php echo htmlspecialchars($form['department_name']); ?></td>
                                        <td><?php echo $form['duration_months'] ?? 'N/A'; ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $form['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $form['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No formations found. <a href="?action=add">Add one</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
