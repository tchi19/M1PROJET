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
            $capacity = $_POST['capacity'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $type = $_POST['type'] ?? 'classroom';
            $department_id = $_POST['department_id'] ?? '';
            
            if (empty($name) || empty($capacity)) {
                $error = 'Room name and capacity are required';
            } else {
                $sql = "INSERT INTO salles (name, capacity, location, type, department_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sisi$", $name, $capacity, $location, $type);
                
                if (empty($department_id)) {
                    $stmt->bind_param("sisii", $name, $capacity, $location, $type, $null);
                } else {
                    $stmt->bind_param("sisii", $name, $capacity, $location, $type, $department_id);
                }
                
                if ($stmt->execute()) {
                    header("Location: rooms.php?msg=Exam room added successfully");
                    exit();
                } else {
                    $error = 'Error adding room: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'edit') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $capacity = $_POST['capacity'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $type = $_POST['type'] ?? 'classroom';
            $department_id = $_POST['department_id'] ?? '';
            
            if (empty($id) || empty($name) || empty($capacity)) {
                $error = 'All required fields must be filled';
            } else {
                if (empty($department_id)) {
                    $sql = "UPDATE salles SET name = ?, capacity = ?, location = ?, type = ?, department_id = NULL WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sisis", $name, $capacity, $location, $type, $id);
                } else {
                    $sql = "UPDATE salles SET name = ?, capacity = ?, location = ?, type = ?, department_id = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sisii", $name, $capacity, $location, $type, $department_id, $id);
                }
                
                if ($stmt->execute()) {
                    header("Location: rooms.php?msg=Exam room updated successfully");
                    exit();
                } else {
                    $error = 'Error updating room: ' . $conn->error;
                }
            }
        } elseif ($post_action == 'delete') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                $error = 'Room ID is required';
            } else {
                $sql = "DELETE FROM salles WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    header("Location: rooms.php?msg=Exam room deleted successfully");
                    exit();
                } else {
                    $error = 'Error deleting room: ' . $conn->error;
                }
            }
        }
    }
}

// Get rooms
$sql = "SELECT s.*, d.name as department_name FROM salles s LEFT JOIN departements d ON s.department_id = d.id ORDER BY s.name";
$rooms = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get departments for dropdown
$sql = "SELECT * FROM departements ORDER BY name";
$departments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get room to edit
$edit_room = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM salles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_room = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Rooms - Admin Dashboard</title>
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
        <a href="modules.php">Modules</a>
        <a href="professors.php">Professors</a>
        <a href="students.php">Students</a>
        <a href="rooms.php" class="active">Exam Rooms</a>
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
                    <h1 class="card-title"><?php echo ($action == 'edit' && $edit_room) ? 'Edit Exam Room' : 'Add New Exam Room'; ?></h1>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo ($action == 'edit' && $edit_room) ? 'edit' : 'add'; ?>">
                        <?php if ($action == 'edit' && $edit_room): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_room['id']; ?>">
                        <?php endif; ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Room Name *</label>
                                <input type="text" name="name" class="form-control" required placeholder="e.g., Amphitheater A" 
                                       value="<?php echo $edit_room ? htmlspecialchars($edit_room['name']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Capacity *</label>
                                <input type="number" name="capacity" class="form-control" required placeholder="Number of seats" 
                                       value="<?php echo $edit_room ? $edit_room['capacity'] : ''; ?>">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g., Building A, Floor 2" 
                                       value="<?php echo $edit_room ? htmlspecialchars($edit_room['location'] ?? '') : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Room Type</label>
                                <select name="type" class="form-select">
                                    <option value="classroom" <?php echo ($edit_room && $edit_room['type'] == 'classroom') ? 'selected' : ''; ?>>Classroom</option>
                                    <option value="amphi" <?php echo ($edit_room && $edit_room['type'] == 'amphi') ? 'selected' : ''; ?>>Amphitheater</option>
                                    <option value="computer_lab" <?php echo ($edit_room && $edit_room['type'] == 'computer_lab') ? 'selected' : ''; ?>>Computer Lab</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department (Optional)</label>
                                <select name="department_id" class="form-select">
                                    <option value="">Not assigned to department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo ($edit_room && $edit_room['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Exam Rooms List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($rooms) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Capacity</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Department</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['name']); ?></td>
                                        <td><?php echo $room['capacity']; ?> seats</td>
                                        <td><?php echo htmlspecialchars($room['location'] ?? 'N/A'); ?></td>
                                        <td><span class="badge badge-primary"><?php echo ucfirst(str_replace('_', ' ', $room['type'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($room['department_name'] ?? 'All departments'); ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $room['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No exam rooms found. <a href="?action=add">Add one</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
