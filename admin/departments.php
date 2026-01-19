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
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $error = 'Department name is required';
            } else {
                $sql = "INSERT INTO departements (name, description) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $name, $description);

                if ($stmt->execute()) {
                    header("Location: departments.php?msg=Department added successfully");
                    exit();
                } else {
                    $error = 'Error adding department: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'edit') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $chef_id = !empty($_POST['chef_id']) ? $_POST['chef_id'] : null;

            if (empty($id) || empty($name)) {
                $error = 'Department ID and name are required';
            } else {
                $sql = "UPDATE departements SET name = ?, description = ?, chef_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii", $name, $description, $chef_id, $id);

                if ($stmt->execute()) {
                    header("Location: departments.php?msg=Department updated successfully");
                    exit();
                } else {
                    $error = 'Error updating department: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'delete') {
            $id = $_POST['id'] ?? '';

            if (empty($id)) {
                $error = 'Department ID is required';
            } else {
                $sql = "DELETE FROM departements WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    header("Location: departments.php?msg=Department deleted successfully");
                    exit();
                } else {
                    $error = 'Error deleting department: ' . $conn->error;
                }
            }
        }
    }
}

// Get departments with chef name
$sql = "SELECT d.*, u.full_name as chef_name FROM departements d LEFT JOIN professeurs p ON d.chef_id = p.id LEFT JOIN users u ON p.user_id = u.id ORDER BY d.name";
$departments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get department to edit and its professors
$edit_dept = null;
$dept_profs = [];
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM departements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_dept = $stmt->get_result()->fetch_assoc();

    if ($edit_dept) {
        // Get professors for this department
        $sql = "SELECT p.id, u.full_name FROM professeurs p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.department_id = ? 
                ORDER BY u.full_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $dept_profs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Admin Dashboard</title>
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
        <a href="departments.php" class="active">Departments</a>
        <a href="formations.php">Formations</a>
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
                    <h1 class="card-title">
                        <?php echo ($action == 'edit' && $edit_dept) ? 'Edit Department' : 'Add New Department'; ?>
                    </h1>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action"
                            value="<?php echo ($action == 'edit' && $edit_dept) ? 'edit' : 'add'; ?>">
                        <?php if ($action == 'edit' && $edit_dept): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_dept['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="name" class="form-control" required
                                placeholder="Enter department name"
                                value="<?php echo $edit_dept ? htmlspecialchars($edit_dept['name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" placeholder="Enter department description"
                                rows="4"><?php echo $edit_dept ? htmlspecialchars($edit_dept['description']) : ''; ?></textarea>
                        </div>

                        <?php if ($action == 'edit'): ?>
                            <div class="form-group">
                                <label class="form-label">Head of Department (Chef)</label>
                                <select name="chef_id" class="form-select">
                                    <option value="">Select a Professor</option>
                                    <?php foreach ($dept_profs as $prof): ?>
                                        <option value="<?php echo $prof['id']; ?>" <?php echo ($edit_dept['chef_id'] == $prof['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prof['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Only professors from this department are listed.</small>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="departments.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Departments List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($departments) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Chef</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo $dept['chef_name'] ? htmlspecialchars($dept['chef_name']) : '<span style="color: #95a5a6;">Not assigned</span>'; ?>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $dept['id']; ?>"
                                                class="btn btn-primary btn-sm">Edit</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No departments found. <a href="?action=add">Add one</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>