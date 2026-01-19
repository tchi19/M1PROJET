<?php
require_once '../includes/db_config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

require_role('admin');

$user = get_auth_user();
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle schedule generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'generate') {
        try {
            // Clear existing exams and related data
            $conn->query("DELETE FROM surveillances");
            $conn->query("DELETE FROM conflicts");
            $conn->query("DELETE FROM examens");

            // ============================================================
            // STEP 1: Fetch all modules (regardless of enrollment)
            // ============================================================
            $modules_sql = "SELECT m.id, m.name, m.code, m.formation_id, 
                           f.name as formation_name, f.department_id, f.number_of_groups,
                           COUNT(DISTINCT i.student_id) as student_count
                           FROM modules m
                           JOIN formations f ON m.formation_id = f.id
                           LEFT JOIN inscriptions i ON m.id = i.module_id AND i.status = 'active'
                           GROUP BY m.id
                           ORDER BY f.department_id, f.id, m.id";
            $modules = $conn->query($modules_sql)->fetch_all(MYSQLI_ASSOC);

            if (count($modules) == 0) {
                $error = 'No modules found in the database';
            } else {
                // ============================================================
                // STEP 2: Fetch rooms (sorted by capacity ASCENDING for best fit logic inside loop)
                // ============================================================
                $rooms_sql = "SELECT id, capacity FROM salles WHERE capacity > 0 ORDER BY capacity ASC";
                $rooms = $conn->query($rooms_sql)->fetch_all(MYSQLI_ASSOC);

                if (count($rooms) == 0) {
                    $error = 'No exam rooms available';
                } else {
                    // ============================================================
                    // STEP 3: Fetch professors with department info
                    // ============================================================
                    $profs_sql = "SELECT p.id, p.department_id FROM professeurs p";
                    $profs = $conn->query($profs_sql)->fetch_all(MYSQLI_ASSOC);

                    if (count($profs) == 0) {
                        $error = 'No professors available for invigilation';
                    } else {
                        // ============================================================
                        // STEP 4: Initialize tracking structures
                        // ============================================================
                        $exam_times = [
                            ['08:00', '10:00'],
                            ['10:30', '12:30'],
                            ['14:00', '16:00'],
                            ['16:30', '18:30']
                        ];

                        $start_date = date('Y-m-d', strtotime('+1 day'));

                        // Track: formation_id => [date => slot_count]
                        $formation_exams_per_day = [];

                        // Track: prof_id => total_assignments
                        $prof_total_assignments = [];
                        foreach ($profs as $prof) {
                            $prof_total_assignments[$prof['id']] = 0;
                        }

                        // Track: prof_id => [date => count]
                        $prof_daily_assignments = [];
                        foreach ($profs as $prof) {
                            $prof_daily_assignments[$prof['id']] = [];
                        }

                        // Track: date_slot => [prof_ids assigned]
                        $slot_prof_assignments = [];

                        // Track: date_slot => [room_ids assigned]
                        $slot_room_usage = [];

                        $exam_count = 0;
                        $current_date = $start_date;
                        $max_days = 365; // Safety limit

                        // ============================================================
                        // STEP 5: Schedule each module
                        // ============================================================
                        foreach ($modules as $module) {
                            $formation_id = $module['formation_id'];
                            $department_id = $module['department_id'];
                            $total_student_count = (int) $module['student_count'];

                            if ($total_student_count == 0) {
                                // Handle 0 students case if needed. Assuming 0 students doesn't require a room/prof essentially,
                                // but we might want to schedule it for completeness.
                                // For logic below, we treat as 1 student to just assign a room.
                            }

                            // Find a valid date for this formation (max 1 exam per day)
                            $scheduled = false;
                            $attempt_date = $start_date;
                            $day_attempts = 0;

                            while (!$scheduled && $day_attempts < $max_days) {
                                // Check if formation already has an exam on this date
                                $formation_day_count = $formation_exams_per_day[$formation_id][$attempt_date] ?? 0;

                                if ($formation_day_count < 1) {
                                    // Try all 4 slots
                                    for ($slot_idx = 0; $slot_idx < 4; $slot_idx++) {
                                        $time = $exam_times[$slot_idx];
                                        $slot_key = $attempt_date . '_' . $slot_idx;

                                        // GREEDY ALLOCATION START
                                        $students_left = $total_student_count;
                                        if ($students_left == 0)
                                            $students_left = 1; // Handle 0 students

                                        $allocated_rooms = []; // Array of room records
                                        $allocated_sizes = []; // Array of ints
                                        $allocated_profs = []; // Array of prof IDs

                                        $slot_valid = true;
                                        $temp_slot_room_usage = $slot_room_usage[$slot_key] ?? [];

                                        // Get list of usable rooms
                                        $usable_rooms = [];
                                        foreach ($rooms as $r) {
                                            if (!isset($temp_slot_room_usage[$r['id']])) {
                                                $usable_rooms[] = $r;
                                            }
                                        }

                                        while ($students_left > 0) {
                                            if (empty($usable_rooms)) {
                                                $slot_valid = false;
                                                break; // Run out of rooms
                                            }

                                            // 1. Check if any room can fit ALL remaining
                                            $best_fit_idx = -1;
                                            $best_fit_cap = PHP_INT_MAX;

                                            foreach ($usable_rooms as $idx => $r) {
                                                if ($r['capacity'] >= $students_left) {
                                                    if ($r['capacity'] < $best_fit_cap) {
                                                        $best_fit_cap = $r['capacity'];
                                                        $best_fit_idx = $idx;
                                                    }
                                                }
                                            }

                                            $chosen_room_idx = -1;
                                            $allocated_size = 0;

                                            if ($best_fit_idx != -1) {
                                                // Found a room that fits ALL
                                                $chosen_room_idx = $best_fit_idx;
                                                $allocated_size = $students_left;
                                            } else {
                                                // None fits all. Pick largest available.
                                                // We need to find the max capacity room in usable_rooms
                                                $max_cap = -1;
                                                $max_idx = -1;
                                                foreach ($usable_rooms as $idx => $r) {
                                                    if ($r['capacity'] > $max_cap) {
                                                        $max_cap = $r['capacity'];
                                                        $max_idx = $idx;
                                                    }
                                                }
                                                $chosen_room_idx = $max_idx;
                                                $allocated_size = $usable_rooms[$max_idx]['capacity'];
                                            }

                                            $chosen_room = $usable_rooms[$chosen_room_idx];

                                            // Remove used room from usable (before calling find_best_professor or continuing)
                                            // We use array_splice, but that reindexes. $usable_rooms is a 0-indexed array.
                                            // Splice returns the removed elements, which is fine.
                                            array_splice($usable_rooms, $chosen_room_idx, 1);

                                            // FIND PROFESSOR for this group
                                            $current_simulated_assignments = $slot_prof_assignments;
                                            if (!isset($current_simulated_assignments[$slot_key])) {
                                                $current_simulated_assignments[$slot_key] = [];
                                            }
                                            $current_simulated_assignments[$slot_key] = array_merge($current_simulated_assignments[$slot_key], $allocated_profs);

                                            $best_prof_id = find_best_professor(
                                                $profs,
                                                $department_id,
                                                $attempt_date,
                                                $slot_key,
                                                $prof_total_assignments,
                                                $prof_daily_assignments,
                                                $current_simulated_assignments
                                            );

                                            if ($best_prof_id === null) {
                                                $slot_valid = false;
                                                break; // No prof available
                                            }

                                            // Success for this chunk
                                            $allocated_rooms[] = $chosen_room;
                                            $allocated_sizes[] = $allocated_size;
                                            $allocated_profs[] = $best_prof_id;
                                            $students_left -= $allocated_size;
                                            if ($students_left < 0)
                                                $students_left = 0;
                                        }

                                        if ($slot_valid) {
                                            // COMMIT THIS SCHEDULE

                                            // Update Formation Groups Count
                                            $num_groups_needed = count($allocated_rooms);
                                            $conn->query("UPDATE formations SET number_of_groups = $num_groups_needed WHERE id = $formation_id");

                                            // Re-distribute students based on allocated sizes
                                            distribute_students_by_chunks($conn, $formation_id, $allocated_sizes);

                                            // Insert Exams
                                            foreach ($allocated_rooms as $idx => $room) {
                                                $group_num = $idx + 1;
                                                $prof_id = $allocated_profs[$idx];

                                                // Insert Exam
                                                $sql = "INSERT INTO examens (module_id, group_number, exam_date, start_time, end_time, room_id, created_by, status) 
                                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->bind_param("iisssii", $module['id'], $group_num, $attempt_date, $time[0], $time[1], $room['id'], $user['id']);
                                                $stmt->execute();
                                                $exam_id = $conn->insert_id;
                                                $exam_count++;

                                                // Insert Surveillance
                                                $surv_sql = "INSERT INTO surveillances (exam_id, prof_id, role, status) 
                                                           VALUES (?, ?, 'invigilator', 'assigned')";
                                                $surv_stmt = $conn->prepare($surv_sql);
                                                $surv_stmt->bind_param("ii", $exam_id, $prof_id);
                                                $surv_stmt->execute();

                                                // Update Tracking
                                                $prof_total_assignments[$prof_id]++;

                                                if (!isset($prof_daily_assignments[$prof_id][$attempt_date])) {
                                                    $prof_daily_assignments[$prof_id][$attempt_date] = 0;
                                                }
                                                $prof_daily_assignments[$prof_id][$attempt_date]++;

                                                if (!isset($slot_prof_assignments[$slot_key])) {
                                                    $slot_prof_assignments[$slot_key] = [];
                                                }
                                                $slot_prof_assignments[$slot_key][] = $prof_id;

                                                if (!isset($slot_room_usage[$slot_key])) {
                                                    $slot_room_usage[$slot_key] = [];
                                                }
                                                $slot_room_usage[$slot_key][$room['id']] = true;
                                            }

                                            // Update Formation Daily Tracking
                                            if (!isset($formation_exams_per_day[$formation_id])) {
                                                $formation_exams_per_day[$formation_id] = [];
                                            }
                                            $formation_exams_per_day[$formation_id][$attempt_date] = ($formation_exams_per_day[$formation_id][$attempt_date] ?? 0) + 1;

                                            $scheduled = true;
                                            break; // Slot loop
                                        }
                                    }
                                }

                                if (!$scheduled) {
                                    $attempt_date = date('Y-m-d', strtotime($attempt_date . ' +1 day'));
                                    $day_attempts++;
                                }
                            }

                            if (!$scheduled) {
                                record_conflict(
                                    null,
                                    'time_conflict',
                                    "Could not schedule module {$module['code']} ($total_student_count students) - no combination of rooms/profs found.",
                                    'high'
                                );
                            }
                        }

                        // ============================================================
                        // STEP 6: Check Fairness and Record Conflicts
                        // ============================================================
                        $total_assignments = array_sum($prof_total_assignments);
                        $count_profs = count($profs);
                        $average = ($count_profs > 0) ? $total_assignments / $count_profs : 0;
                        $count_above = 0;
                        $count_below = 0;

                        foreach ($prof_total_assignments as $pid => $count) {
                            if ($count > $average)
                                $count_above++;
                            elseif ($count < $average)
                                $count_below++;
                        }

                        if ($count_above > 0 || $count_below > 0) {
                            $desc = "Fairness Report: $count_above professors are ABOVE average (" . number_format($average, 2) . ") assignments, and $count_below are BELOW average.";
                            $c_sql = "INSERT INTO conflicts (conflict_type, description, severity, resolved) VALUES ('prof_fairness', ?, 'low', FALSE)";
                            $c_stmt = $conn->prepare($c_sql);
                            $c_stmt->bind_param("s", $desc);
                            $c_stmt->execute();
                        }

                        header("Location: scheduling.php?msg=Schedule generated successfully with $exam_count exams");
                        exit();
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error generating schedule: ' . $e->getMessage();
        }
    }
}

/**
 * Find the best professor for a given slot
 */
function find_best_professor($profs, $target_dept_id, $date, $slot_key, $prof_total_assignments, $prof_daily_assignments, $slot_prof_assignments)
{
    $candidates = [];
    foreach ($profs as $prof) {
        $prof_id = $prof['id'];
        if (isset($slot_prof_assignments[$slot_key]) && in_array($prof_id, $slot_prof_assignments[$slot_key]))
            continue;
        $daily_count = $prof_daily_assignments[$prof_id][$date] ?? 0;
        if ($daily_count >= 3)
            continue;
        $score = $prof_total_assignments[$prof_id];
        $same_dept = ($prof['department_id'] == $target_dept_id) ? 0 : 1000;
        $candidates[] = ['id' => $prof_id, 'score' => $score + $same_dept];
    }
    if (count($candidates) == 0)
        return null;
    usort($candidates, function ($a, $b) {
        return $a['score'] - $b['score']; });
    return $candidates[0]['id'];
}

/**
 * Distribute students into chunks
 */
function distribute_students_by_chunks($conn, $formation_id, $chunks)
{
    $sql = "SELECT id FROM etudiants WHERE formation_id = ? ORDER BY id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);

    $current_student_idx = 0;
    $total_students = count($students);

    foreach ($chunks as $idx => $size) {
        $group_num = $idx + 1;
        $count = 0;
        while ($count < $size && $current_student_idx < $total_students) {
            $s_id = $students[$current_student_idx]['id'];
            $conn->query("UPDATE etudiants SET group_number = $group_num WHERE id = $s_id");
            $current_student_idx++;
            $count++;
        }
    }
}

// Get statistics
$total_modules = $conn->query("SELECT COUNT(*) as count FROM modules")->fetch_assoc()['count'];
$modules_with_exams = $conn->query("SELECT COUNT(DISTINCT module_id) as count FROM examens")->fetch_assoc()['count'];
$total_exams = $conn->query("SELECT COUNT(*) as count FROM examens")->fetch_assoc()['count'];
$scheduled_exams = $conn->query("SELECT COUNT(*) as count FROM examens WHERE status = 'scheduled'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Exams - Admin Dashboard</title>
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
        <a href="rooms.php">Exam Rooms</a>
        <a href="exams.php">Exams</a>
        <a href="scheduling.php" class="active">Schedule Exams</a>
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
            <div class="row">
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $total_modules; ?></h3>
                        <p>Total Modules</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $modules_with_exams; ?></h3>
                        <p>Modules with Exams</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $total_exams; ?></h3>
                        <p>Total Exams</p>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-box">
                        <h3><?php echo $scheduled_exams; ?></h3>
                        <p>Scheduled Exams</p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Generate Exam Schedule</h1>
                </div>
                <div class="card-body">
                    <p>Click the button below to automatically generate an exam schedule for all modules.</p>
                    <p><strong>Algorithm Rules:</strong></p>
                    <ul>
                        <li><strong>All modules scheduled</strong> - Every module gets an exam</li>
                        <li><strong>Max 1 exam per formation per day</strong></li>
                        <li><strong>Fair professor distribution</strong></li>
                        <li><strong>Department priority</strong></li>
                        <li><strong>Greedy Group Allocation</strong> - Splits groups dynamically based on room capacity</li>
                    </ul>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="action" value="generate">
                        <button type="submit" class="btn btn-success btn-lg"
                            onclick="return confirm('This will clear all existing exams. Are you sure?')">
                            Generate Schedule
                        </button>
                        <a href="exams.php" class="btn btn-secondary btn-lg">View Current Exams</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>