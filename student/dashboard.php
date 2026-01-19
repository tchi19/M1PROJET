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

// Get student's exams
$exams = get_student_exams($student['id']);

// Get student's enrolled modules
$modules = get_student_modules($student['id']);



// Get filter
$filter_formation = $_GET['formation'] ?? '';
$filter_date = $_GET['date'] ?? '';

$filtered_exams = $exams;
if (!empty($filter_formation)) {
    $filtered_exams = array_filter($exams, function($e) use ($filter_formation) {
        return $e['formation_name'] == $filter_formation;
    });
}

if (!empty($filter_date)) {
    $filtered_exams = array_filter($filtered_exams, function($e) use ($filter_date) {
        return $e['exam_date'] == $filter_date;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Exam Timetable</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">Exam Timetable Management - Student</div>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="sidebar">
        <a href="dashboard.php" class="active">My Timetable</a>
        <a href="exams.php">My Exams</a>
        <a href="modules.php">My Modules</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">My Exam Timetable</h1>
                </div>
                <div class="card-body">
                    <p>Student: <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                    <p>Total Exams: <strong><?php echo count($exams); ?></strong></p>

                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Filter Exams</h2>
                </div>
                <div class="card-body">
                    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Formation</label>
                            <select name="formation" class="form-select">
                                <option value="">All Formations</option>
                                <?php 
                                $formations_list = array_unique(array_column($exams, 'formation_name'));
                                foreach ($formations_list as $form): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($form); ?>" 
                                            <?php echo $filter_formation == $form ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($form); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        
                        <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="dashboard.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Scheduled Exams (<?php echo count($filtered_exams); ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (count($filtered_exams) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Room</th>
                                    <th>Capacity</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered_exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['module_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($exam['module_code']); ?></strong></td>
                                        <td><?php echo date('d/m/Y (D)', strtotime($exam['exam_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($exam['start_time'])) . ' - ' . date('H:i', strtotime($exam['end_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($exam['room_name']); ?></td>
                                        <td><?php echo $exam['capacity']; ?> seats</td>
                                        <td><?php echo htmlspecialchars($exam['department_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No exams scheduled matching your filter.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Exam Statistics</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div style="background: #f5f7fa; padding: 1rem; border-radius: 4px;">
                            <p style="color: #7f8c8d; margin-bottom: 0.5rem;">Total Exams</p>
                            <h3 style="color: #2c3e50; margin: 0;"><?php echo count($exams); ?></h3>
                        </div>
                        
                        <div style="background: #f5f7fa; padding: 1rem; border-radius: 4px;">
                            <p style="color: #7f8c8d; margin-bottom: 0.5rem;">Exam Days</p>
                            <h3 style="color: #2c3e50; margin: 0;">
                                <?php 
                                $unique_dates = count(array_unique(array_column($exams, 'exam_date')));
                                echo $unique_dates;
                                ?>
                            </h3>
                        </div>
                        
                        <div style="background: #f5f7fa; padding: 1rem; border-radius: 4px;">
                            <p style="color: #7f8c8d; margin-bottom: 0.5rem;">Exam Rooms</p>
                            <h3 style="color: #2c3e50; margin: 0;">
                                <?php 
                                $unique_rooms = count(array_unique(array_column($exams, 'room_name')));
                                echo $unique_rooms;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
