<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('admin');

$user = get_auth_user();
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Get all users
$sql = "SELECT * FROM users ORDER BY role, full_name";
$users = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Handle user status change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'toggle_status') {
        $user_id = $_POST['id'] ?? '';
        
        if (!empty($user_id) && $user_id != $user['id']) { // Prevent disabling self
            $sql = "UPDATE users SET active = NOT active WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                header("Location: users.php?msg=User status updated");
                exit();
            }
        }
    }
}

// Count users by role
$role_counts = array();
foreach ($users as $u) {
    if (!isset($role_counts[$u['role']])) {
        $role_counts[$u['role']] = 0;
    }
    $role_counts[$u['role']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
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
        <a href="rooms.php">Exam Rooms</a>
        <a href="exams.php">Exams</a>
        <a href="scheduling.php">Schedule Exams</a>
        <a href="conflicts.php">Conflicts</a>
        <a href="users.php" class="active">Manage Users</a>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if ($msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo isset($role_counts['admin']) ? $role_counts['admin'] : 0; ?></h3>
                        <p>Admins</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo isset($role_counts['prof']) ? $role_counts['prof'] : 0; ?></h3>
                        <p>Professors</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo isset($role_counts['student']) ? $role_counts['student'] : 0; ?></h3>
                        <p>Students</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo isset($role_counts['doyen']) ? $role_counts['doyen'] : 0; ?></h3>
                        <p>Doyens</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">All Users</h1>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo ucfirst($u['role']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($u['active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <?php if ($u['id'] != $user['id']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    <?php echo $u['active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 0.85rem;">Your account</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
