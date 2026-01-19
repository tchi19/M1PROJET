<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

if (is_logged_in()) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';
$role_options = array('student', 'prof', 'admin', 'doyen');

// Fetch formations for dropdown
$formations_sql = "SELECT id, name FROM formations ORDER BY name";
$formations_result = $conn->query($formations_sql);
$formations = $formations_result->fetch_all(MYSQLI_ASSOC);

// Fetch departments for dropdown
$departments_sql = "SELECT id, name FROM departements ORDER BY name";
$departments_result = $conn->query($departments_sql);
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'signup') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? '';
            $formation_id = $_POST['formation_id'] ?? '';
            $department_id = $_POST['department_id'] ?? '';

            // Validation
            if (empty($email) || empty($password) || empty($full_name) || empty($role)) {
                $error = 'All fields are required';
            } elseif ($role == 'student' && empty($formation_id)) {
                $error = 'Please select a formation';
            } elseif ($role == 'prof' && empty($department_id)) {
                $error = 'Please select a department';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } elseif (!in_array($role, $role_options)) {
                $error = 'Invalid role selected';
            } else {
                // Check if email exists
                $sql = "SELECT id FROM users WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();

                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Email already registered';
                } else {
                    // Hash password and create user
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    $sql = "INSERT INTO users (email, password, role, full_name, phone) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssss", $email, $hashed_password, $role, $full_name, $phone);

                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;

                        // Create additional records based on role
                        if ($role == 'student') {
                            // Will be assigned to formation by admin - NOW SELECTED BY USER
                            $student_number = 'STU' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                            $sql = "INSERT INTO etudiants (user_id, formation_id, student_number) VALUES (?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iis", $user_id, $formation_id, $student_number);
                            $stmt->execute();
                        } elseif ($role == 'prof') {
                            // Will be assigned to department by admin - NOW SELECTED BY USER
                            $sql = "INSERT INTO professeurs (user_id, department_id) VALUES (?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ii", $user_id, $department_id);
                            $stmt->execute();
                        }

                        $success = 'Account created successfully! Please login.';
                        // Redirect to login after 2 seconds
                        header("refresh:2;url=login.php");
                    } else {
                        $error = 'Error creating account: ' . $conn->error;
                    }
                }
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
    <title>Sign Up - Exam Timetable Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        function toggleRoleFields() {
            var roleSelect = document.querySelector('select[name="role"]');
            var formationField = document.getElementById('formation-field');
            var departmentField = document.getElementById('department-field');

            // Reset fields
            formationField.style.display = 'none';
            departmentField.style.display = 'none';
            document.querySelector('select[name="formation_id"]').required = false;
            document.querySelector('select[name="department_id"]').required = false;

            if (roleSelect.value === 'student') {
                formationField.style.display = 'block';
                document.querySelector('select[name="formation_id"]').required = true;
            } else if (roleSelect.value === 'prof') {
                departmentField.style.display = 'block';
                document.querySelector('select[name="department_id"]').required = true;
            }
        }
    </script>
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Create Account</h1>
            <p style="text-align: center; color: #7f8c8d; margin-bottom: 2rem;">Exam Timetable Management Platform</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="signup">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required
                        placeholder="Enter your full name">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label class="form-label">Phone (Optional)</label>
                    <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number">
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required onchange="toggleRoleFields()">
                        <option value="">Select your role</option>
                        <option value="student">Student</option>
                        <option value="prof">Professor</option>
                        <option value="admin">Admin</option>
                        <option value="doyen">Doyen (Dean)</option>
                    </select>
                </div>

                <div class="form-group" id="formation-field" style="display: none;">
                    <label class="form-label">Formation</label>
                    <select name="formation_id" class="form-select">
                        <option value="">Select your formation</option>
                        <?php foreach ($formations as $formation): ?>
                            <option value="<?php echo $formation['id']; ?>">
                                <?php echo htmlspecialchars($formation['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="department-field" style="display: none;">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">Select your department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>">
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required
                        placeholder="Minimum 6 characters">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required
                        placeholder="Confirm your password">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>

</html>