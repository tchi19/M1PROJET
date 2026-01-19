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
            $email = trim($_POST['email'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department_id = $_POST['department_id'] ?? '';
            $speciality = trim($_POST['speciality'] ?? '');
            $password = 'professor123'; // Default password

            if (empty($email) || empty($full_name) || empty($department_id)) {
                $error = 'Email, name and department are required';
            } else {
                // Check if email exists in users
                $check_sql = "SELECT id FROM users WHERE email = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();

                if ($check_stmt->get_result()->num_rows > 0) {
                    $error = 'Email already exists';
                } else {
                    // Create user
                    $role = 'prof';
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $sql = "INSERT INTO users (email, password, role, full_name, phone) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssss", $email, $hashed_password, $role, $full_name, $phone);

                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;

                        // Create professor record
                        $sql = "INSERT INTO professeurs (user_id, department_id, speciality) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iis", $user_id, $department_id, $speciality);

                        if ($stmt->execute()) {
                            header("Location: professors.php?msg=Professor added successfully");
                            exit();
                        } else {
                            // Delete user if professor record fails
                            $conn->query("DELETE FROM users WHERE id = $user_id");
                            $error = 'Error creating professor record';
                        }
                    } else {
                        $error = 'Error creating user account';
                    }
                }
            }
        } elseif ($post_action == 'delete') {
            $id = $_POST['id'] ?? '';

            if (empty($id)) {
                $error = 'Professor ID is required';
            } else {
                // Get user_id
                $sql = "SELECT user_id FROM professeurs WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $prof = $stmt->get_result()->fetch_assoc();

                if ($prof) {
                    // Delete professor record and user
                    $sql = "DELETE FROM professeurs WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id);

                    if ($stmt->execute()) {
                        // Delete user
                        $conn->query("DELETE FROM users WHERE id = " . $prof['user_id']);
                        header("Location: professors.php?msg=Professor deleted successfully");
                        exit();
                    } else {
                        $error = 'Error deleting professor: ' . $conn->error;
                    }
                }
            }
        }
    }
}

// Get professors with department info and chef status
$sql = "SELECT p.*, u.email, u.full_name, u.phone, u.role as user_role, d.name as department_name,
        (SELECT COUNT(*) FROM departements WHERE chef_id = p.id) > 0 as is_chef,
        (SELECT name FROM departements WHERE chef_id = p.id LIMIT 1) as chef_of_department
        FROM professeurs p 
        JOIN users u ON p.user_id = u.id 
        JOIN departements d ON p.department_id = d.id 
        ORDER BY u.full_name";
$professors = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get departments for dropdown
$sql = "SELECT * FROM departements ORDER BY name";
$departments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professors - Admin Dashboard</title>
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
        <a href="professors.php" class="active">Professors</a>
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
                    <h1 class="card-title">Add New Professor</h1>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required
                                    placeholder="professor@university.edu">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required
                                    placeholder="Enter professor name">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Department *</label>
                                <select name="department_id" class="form-select" required>
                                    <option value="">Select a department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Speciality</label>
                                <input type="text" name="speciality" class="form-control"
                                    placeholder="e.g., Computer Science">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" placeholder="Phone number">
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">Create Professor Account</button>
                        </div>
                        <p style="margin-top: 1rem; color: #7f8c8d; font-size: 0.9rem;">Default password: professor123
                            (professor should change on first login)</p>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Professors List (<?php echo count($professors); ?> total)</h2>
                </div>
                <div class="card-body">
                    <?php if (count($professors) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Speciality</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($professors as $prof): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prof['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['email']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['department_name']); ?></td>
                                        <td>
                                            <?php if ($prof['is_chef']): ?>
                                                    <span class="badge badge-success" title="Chef of <?php echo htmlspecialchars($prof['chef_of_department']); ?>">
                                                        👔 Chef de Département
                                                    </span>
                                            <?php else: ?>
                                                    <span class="badge badge-secondary">Professor</span>
                                            <?php endif; ?>

                                                                                   </td>
                                        <td><?php echo htmlspecialchars($prof['speciality'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($prof['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $prof['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
       
                     </table>
                    <?php else: ?>
                            <p>No professors found. <a href="?action=add">Add one</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
