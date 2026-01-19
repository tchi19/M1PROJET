<?php
require_once 'db_config.php';

// Hash password
function hash_password($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

// Get user by email
function get_user_by_email($email)
{
    global $conn;

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Create new user
function create_user($email, $password, $role, $full_name, $phone = null)
{
    global $conn;

    // Check if email exists
    if (get_user_by_email($email)) {
        return array('success' => false, 'message' => 'Email already exists');
    }

    $hashed_password = hash_password($password);

    $sql = "INSERT INTO users (email, password, role, full_name, phone) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $email, $hashed_password, $role, $full_name, $phone);

    if ($stmt->execute()) {
        return array('success' => true, 'user_id' => $conn->insert_id, 'message' => 'User created successfully');
    } else {
        return array('success' => false, 'message' => $conn->error);
    }
}

// Get professor by user ID
function get_professor_by_user_id($user_id)
{
    global $conn;

    $sql = "SELECT p.* FROM professeurs p WHERE p.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

// Get student by user ID
function get_student_by_user_id($user_id)
{
    global $conn;

    $sql = "SELECT s.* FROM etudiants s WHERE s.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

// Get student's enrolled modules
function get_student_modules($student_id)
{
    global $conn;

    $sql = "SELECT m.*, f.name as formation_name, d.name as department_name 
            FROM modules m
            JOIN formations f ON m.formation_id = f.id
            JOIN departements d ON f.department_id = d.id
            JOIN inscriptions i ON m.id = i.module_id
            WHERE i.student_id = ? AND i.status = 'active'
            ORDER BY m.name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get professor's modules
function get_professor_modules($prof_id)
{
    global $conn;

    $sql = "SELECT m.*, f.name as formation_name, d.name as department_name 
            FROM modules m
            JOIN formations f ON m.formation_id = f.id
            JOIN departements d ON f.department_id = d.id
            WHERE m.professeur_id = ?
            ORDER BY m.name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get exams for student (only approved by both chef and doyen)
function get_student_exams($student_id)
{
    global $conn;

    $sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
            s.name as room_name, s.capacity, f.name as formation_name, d.name as department_name
            FROM examens e
            JOIN modules m ON e.module_id = m.id
            JOIN salles s ON e.room_id = s.id
            JOIN formations f ON m.formation_id = f.id
            JOIN departements d ON f.department_id = d.id
            JOIN inscriptions i ON m.id = i.module_id
            WHERE i.student_id = ? AND i.status = 'active'
            AND e.accepted_by_chefdep = 1 AND e.accepted_by_doyen = 1
            ORDER BY e.exam_date ASC, e.start_time ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get exams for professor
function get_professor_exams($prof_id)
{
    global $conn;

    $sql = "SELECT e.*, m.name as module_name, m.code as module_code, 
            s.name as room_name, s.capacity, d.name as department_name
            FROM examens e
            JOIN modules m ON e.module_id = m.id
            JOIN salles s ON e.room_id = s.id
            JOIN formations f ON m.formation_id = f.id
            JOIN departements d ON f.department_id = d.id
            WHERE m.professeur_id = ?
            ORDER BY e.exam_date ASC, e.start_time ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get professor's surveillance assignments
function get_professor_surveillances($prof_id)
{
    global $conn;

    $sql = "SELECT sv.*, e.exam_date, e.start_time, e.end_time, 
            m.name as module_name, m.code as module_code, s.name as room_name, 
            d.name as department_name
            FROM surveillances sv
            JOIN examens e ON sv.exam_id = e.id
            JOIN modules m ON e.module_id = m.id
            JOIN salles s ON e.room_id = s.id
            JOIN departements d ON (
                SELECT department_id FROM formations WHERE id = m.formation_id
            ) = d.id
            WHERE sv.prof_id = ?
            ORDER BY e.exam_date ASC, e.start_time ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Check for student exam conflicts (only time overlaps on the same day) - only for approved exams
function check_student_exam_conflicts($student_id)
{
    global $conn;

    // Find actual time overlaps: exams on the same date where times overlap
    $sql = "SELECT e1.exam_date, e1.id as exam1_id, e2.id as exam2_id,
            m1.name as module1_name, m2.name as module2_name,
            e1.start_time as start1, e1.end_time as end1,
            e2.start_time as start2, e2.end_time as end2
            FROM examens e1
            JOIN examens e2 ON e1.exam_date = e2.exam_date AND e1.id < e2.id
            JOIN modules m1 ON e1.module_id = m1.id
            JOIN modules m2 ON e2.module_id = m2.id
            JOIN inscriptions i1 ON m1.id = i1.module_id
            JOIN inscriptions i2 ON m2.id = i2.module_id
            WHERE i1.student_id = ? AND i2.student_id = ?
            AND i1.status = 'active' AND i2.status = 'active'
            AND e1.status IN ('scheduled', 'in_progress', 'completed')
            AND e2.status IN ('scheduled', 'in_progress', 'completed')
            AND e1.accepted_by_chefdep = 1 AND e1.accepted_by_doyen = 1
            AND e2.accepted_by_chefdep = 1 AND e2.accepted_by_doyen = 1
            AND (e1.start_time < e2.end_time AND e1.end_time > e2.start_time)
            ORDER BY e1.exam_date";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}


// Check for professor overload (max 3 exams per day)
function check_professor_overload($prof_id)
{
    global $conn;

    $sql = "SELECT e.exam_date, COUNT(DISTINCT m.id) as exam_count, 
            GROUP_CONCAT(DISTINCT m.name) as modules
            FROM examens e
            JOIN modules m ON e.module_id = m.id
            WHERE m.professeur_id = ? 
            AND e.status IN ('scheduled', 'in_progress', 'completed')
            GROUP BY e.exam_date
            HAVING exam_count > 3";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Check room capacity violations
function check_room_capacity_violations($exam_id)
{
    global $conn;

    $sql = "SELECT e.id, s.capacity, COUNT(DISTINCT i.student_id) as enrolled_count
            FROM examens e
            JOIN salles s ON e.room_id = s.id
            JOIN modules m ON e.module_id = m.id
            JOIN inscriptions i ON m.id = i.module_id
            WHERE e.id = ? AND i.status = 'active'
            GROUP BY e.id, s.id
            HAVING enrolled_count > capacity";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

// Get all conflicts
function get_all_conflicts()
{
    global $conn;

    $sql = "SELECT c.*, m.name as module_name, m.code as module_code, e.exam_date, e.start_time
            FROM conflicts c
            LEFT JOIN examens e ON c.exam_id = e.id
            LEFT JOIN modules m ON e.module_id = m.id
            WHERE c.resolved = FALSE
            ORDER BY c.severity DESC, c.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get conflicts by department
function get_conflicts_by_department($department_id)
{
    global $conn;

    $sql = "SELECT c.*, m.name as module_name, m.code as module_code, e.exam_date, e.start_time
            FROM conflicts c
            LEFT JOIN examens e ON c.exam_id = e.id
            LEFT JOIN modules m ON e.module_id = m.id
            LEFT JOIN formations f ON m.formation_id = f.id
            WHERE (f.department_id = ? OR c.department_id = ?) AND c.resolved = FALSE
            ORDER BY c.severity DESC, c.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $department_id, $department_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Record conflict
function record_conflict($exam_id, $conflict_type, $description, $severity = 'medium', $department_id = null)
{
    global $conn;

    // Handle null exam_id for general conflicts
    if ($exam_id === null) {
        $sql = "INSERT INTO conflicts (conflict_type, description, severity, department_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("record_conflict prepare failed: " . $conn->error);
            return false;
        }
        $stmt->bind_param("sssi", $conflict_type, $description, $severity, $department_id);
    } else {
        $sql = "INSERT INTO conflicts (exam_id, conflict_type, description, severity, department_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("record_conflict prepare failed: " . $conn->error);
            return false;
        }
        $stmt->bind_param("isssi", $exam_id, $conflict_type, $description, $severity, $department_id);
    }

    return $stmt->execute();
}

// Get statistics for dashboard
function get_statistics()
{
    global $conn;

    return array(
        'total_departments' => $conn->query("SELECT COUNT(*) as count FROM departements")->fetch_assoc()['count'],
        'total_formations' => $conn->query("SELECT COUNT(*) as count FROM formations")->fetch_assoc()['count'],
        'total_students' => $conn->query("SELECT COUNT(*) as count FROM etudiants")->fetch_assoc()['count'],
        'total_professors' => $conn->query("SELECT COUNT(*) as count FROM professeurs")->fetch_assoc()['count'],
        'total_modules' => $conn->query("SELECT COUNT(*) as count FROM modules")->fetch_assoc()['count'],
        'total_exams' => $conn->query("SELECT COUNT(*) as count FROM examens")->fetch_assoc()['count'],
        'total_rooms' => $conn->query("SELECT COUNT(*) as count FROM salles")->fetch_assoc()['count'],
        'active_conflicts' => $conn->query("SELECT COUNT(*) as count FROM conflicts WHERE resolved = FALSE")->fetch_assoc()['count']
    );
}

// Get department statistics
function get_department_statistics($department_id)
{
    global $conn;

    return array(
        'formations' => $conn->query("SELECT COUNT(*) as count FROM formations WHERE department_id = $department_id")->fetch_assoc()['count'],
        'professors' => $conn->query("SELECT COUNT(*) as count FROM professeurs WHERE department_id = $department_id")->fetch_assoc()['count'],
        'students' => $conn->query("SELECT COUNT(DISTINCT e.id) as count FROM etudiants e 
                                   JOIN formations f ON e.formation_id = f.id 
                                   WHERE f.department_id = $department_id")->fetch_assoc()['count'],
        'exams' => $conn->query("SELECT COUNT(*) as count FROM examens e
                               JOIN modules m ON e.module_id = m.id
                               JOIN formations f ON m.formation_id = f.id
                               WHERE f.department_id = $department_id")->fetch_assoc()['count'],
        'conflicts' => $conn->query("SELECT COUNT(*) as count FROM conflicts c
                                    LEFT JOIN examens e ON c.exam_id = e.id
                                    LEFT JOIN modules m ON e.module_id = m.id
                                    LEFT JOIN formations f ON m.formation_id = f.id
                                    WHERE f.department_id = $department_id AND c.resolved = FALSE")->fetch_assoc()['count']
    );
}

// ===== CONSTRAINT VALIDATION FUNCTIONS =====

/**
 * Check if student can take exam on given date (MAX 1 EXAM PER DAY)
 * @param int $student_id
 * @param string $exam_date (YYYY-MM-DD)
 * @param int $exclude_exam_id (optional - exam ID to exclude from check)
 * @return array ['allowed' => bool, 'existing_exams' => array]
 */
function can_student_take_exam_on_date($student_id, $exam_date, $exclude_exam_id = null)
{
    global $conn;

    $sql = "SELECT e.id, e.start_time, e.end_time, m.name as module_name, m.code as module_code
            FROM examens e
            JOIN modules m ON e.module_id = m.id
            JOIN inscriptions i ON m.id = i.module_id
            WHERE i.student_id = ? 
            AND e.exam_date = ?
            AND i.status = 'active'
            AND e.status IN ('scheduled', 'in_progress', 'completed')";

    if ($exclude_exam_id) {
        $sql .= " AND e.id != ?";
    }

    $stmt = $conn->prepare($sql);

    if ($exclude_exam_id) {
        $stmt->bind_param("isi", $student_id, $exam_date, $exclude_exam_id);
    } else {
        $stmt->bind_param("is", $student_id, $exam_date);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $existing_exams = $result->fetch_all(MYSQLI_ASSOC);

    return array(
        'allowed' => count($existing_exams) == 0,
        'existing_exams' => $existing_exams
    );
}

/**
 * Check if professor can teach exam on given date (MAX 3 EXAMS PER DAY)
 * @param int $prof_id
 * @param string $exam_date (YYYY-MM-DD)
 * @param int $exclude_exam_id (optional)
 * @return array ['allowed' => bool, 'exam_count' => int, 'max_exams' => int]
 */
function can_professor_teach_exam_on_date($prof_id, $exam_date, $exclude_exam_id = null)
{
    global $conn;

    // Get professor's max exams per day limit
    $prof_sql = "SELECT max_exams_per_day FROM professeurs WHERE id = ?";
    $prof_stmt = $conn->prepare($prof_sql);
    $prof_stmt->bind_param("i", $prof_id);
    $prof_stmt->execute();
    $prof_result = $prof_stmt->get_result()->fetch_assoc();
    $max_exams = $prof_result['max_exams_per_day'] ?? 3;

    // Count exams on that date
    $sql = "SELECT COUNT(DISTINCT e.id) as exam_count
            FROM examens e
            JOIN modules m ON e.module_id = m.id
            WHERE m.professeur_id = ? 
            AND e.exam_date = ?
            AND e.status IN ('scheduled', 'in_progress', 'completed')";

    if ($exclude_exam_id) {
        $sql .= " AND e.id != ?";
    }

    $stmt = $conn->prepare($sql);

    if ($exclude_exam_id) {
        $stmt->bind_param("isi", $prof_id, $exam_date, $exclude_exam_id);
    } else {
        $stmt->bind_param("is", $prof_id, $exam_date);
    }

    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $exam_count = $result['exam_count'];

    return array(
        'allowed' => $exam_count < $max_exams,
        'exam_count' => $exam_count,
        'max_exams' => $max_exams
    );
}

/**
 * Check if room can accommodate all enrolled students (RESPECT CAPACITY)
 * @param int $room_id
 * @param int $module_id
 * @return array ['allowed' => bool, 'capacity' => int, 'enrolled_count' => int]
 */
function can_room_accommodate_exam($room_id, $module_id)
{
    global $conn;

    // Get room capacity
    $room_sql = "SELECT capacity FROM salles WHERE id = ?";
    $room_stmt = $conn->prepare($room_sql);
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result()->fetch_assoc();
    $capacity = $room_result['capacity'];

    // Count enrolled students
    $sql = "SELECT COUNT(DISTINCT i.student_id) as enrolled_count
            FROM inscriptions i
            WHERE i.module_id = ? AND i.status = 'active'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $module_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $enrolled_count = $result['enrolled_count'];

    return array(
        'allowed' => $enrolled_count <= $capacity,
        'capacity' => $capacity,
        'enrolled_count' => $enrolled_count
    );
}

/**
 * Check if professor has time conflict with another exam (NO OVERLAPPING TIMES)
 * @param int $prof_id
 * @param string $exam_date (YYYY-MM-DD)
 * @param string $start_time (HH:MM:SS)
 * @param string $end_time (HH:MM:SS)
 * @param int $exclude_exam_id (optional)
 * @return array ['allowed' => bool, 'conflicting_exams' => array]
 */
function check_professor_time_conflict($prof_id, $exam_date, $start_time, $end_time, $exclude_exam_id = null)
{
    global $conn;

    $sql = "SELECT e.id, e.start_time, e.end_time, m.name as module_name, m.code as module_code
            FROM examens e
            JOIN modules m ON e.module_id = m.id
            WHERE m.professeur_id = ? 
            AND e.exam_date = ?
            AND e.status IN ('scheduled', 'in_progress', 'completed')
            AND (
                (TIME(?) < e.end_time AND TIME(?) > e.start_time) OR
                (TIME(?) < e.end_time AND TIME(?) > e.start_time)
            )";

    if ($exclude_exam_id) {
        $sql .= " AND e.id != ?";
    }

    $stmt = $conn->prepare($sql);

    if ($exclude_exam_id) {
        $stmt->bind_param("isssssii", $prof_id, $exam_date, $start_time, $end_time, $start_time, $end_time, $exclude_exam_id);
    } else {
        $stmt->bind_param("issssss", $prof_id, $exam_date, $start_time, $end_time, $start_time, $end_time);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $conflicting_exams = $result->fetch_all(MYSQLI_ASSOC);

    return array(
        'allowed' => count($conflicting_exams) == 0,
        'conflicting_exams' => $conflicting_exams
    );
}

/**
 * Get professors by department (for department priority constraint)
 * @param int $department_id
 * @return array of professor IDs
 */
function get_professors_by_department($department_id)
{
    global $conn;

    $sql = "SELECT id FROM professeurs WHERE department_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $professors = array();
    while ($row = $result->fetch_assoc()) {
        $professors[] = $row['id'];
    }
    return $professors;
}

/**
 * Check supervision load balance (ALL TEACHERS MUST HAVE EQUAL SURVEILLANCES)
 * @param int $department_id (optional - if null, checks globally)
 * @return array ['balanced' => bool, 'professor_loads' => array, 'imbalance' => int]
 */
function check_supervision_balance($department_id = null)
{
    global $conn;

    $sql = "SELECT p.id, p.user_id, u.full_name, COUNT(sv.id) as surveillance_count
            FROM professeurs p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN surveillances sv ON p.id = sv.prof_id AND sv.status IN ('assigned', 'confirmed', 'completed')";

    if ($department_id) {
        $sql .= " WHERE p.department_id = ?";
    }

    $sql .= " GROUP BY p.id, p.user_id, u.full_name
             ORDER BY surveillance_count ASC";

    $stmt = $conn->prepare($sql);

    if ($department_id) {
        $stmt->bind_param("i", $department_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $professor_loads = $result->fetch_all(MYSQLI_ASSOC);

    // Calculate imbalance
    $loads = array_column($professor_loads, 'surveillance_count');
    $imbalance = max($loads) - min($loads);
    $balanced = $imbalance <= 1; // Allow 1 difference due to odd numbers

    return array(
        'balanced' => $balanced,
        'professor_loads' => $professor_loads,
        'imbalance' => $imbalance,
        'min_load' => min($loads),
        'max_load' => max($loads)
    );
}

/**
 * Assign surveillance with department priority (prioritize same department professors)
 * @param int $exam_id
 * @param int $num_supervisors (default 2)
 * @return array ['success' => bool, 'assigned_professors' => array, 'message' => string]
 */
function assign_surveillances_with_priority($exam_id, $num_supervisors = 2)
{
    global $conn;

    // Get exam details
    $exam_sql = "SELECT e.exam_date, m.formation_id FROM examens e 
                 JOIN modules m ON e.module_id = m.id WHERE e.id = ?";
    $exam_stmt = $conn->prepare($exam_sql);
    $exam_stmt->bind_param("i", $exam_id);
    $exam_stmt->execute();
    $exam = $exam_stmt->get_result()->fetch_assoc();

    $formation_sql = "SELECT department_id FROM formations WHERE id = ?";
    $form_stmt = $conn->prepare($formation_sql);
    $form_stmt->bind_param("i", $exam['formation_id']);
    $form_stmt->execute();
    $department_id = $form_stmt->get_result()->fetch_assoc()['department_id'];

    // Get professors from same department (priority)
    $dept_profs_sql = "SELECT p.id, COUNT(sv.id) as surveillance_count
                       FROM professeurs p
                       LEFT JOIN surveillances sv ON p.id = sv.prof_id 
                       AND sv.status IN ('assigned', 'confirmed', 'completed')
                       WHERE p.department_id = ?
                       AND p.id NOT IN (
                           SELECT prof_id FROM surveillances 
                           WHERE exam_id = ?
                       )
                       GROUP BY p.id
                       ORDER BY surveillance_count ASC
                       LIMIT ?";

    $dept_stmt = $conn->prepare($dept_profs_sql);
    $dept_stmt->bind_param("iii", $department_id, $exam_id, $num_supervisors);
    $dept_stmt->execute();
    $dept_profs = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $assigned = array();

    // Assign from same department first
    foreach ($dept_profs as $prof) {
        if (count($assigned) >= $num_supervisors)
            break;

        $surv_sql = "INSERT INTO surveillances (exam_id, prof_id, role, status) 
                     VALUES (?, ?, ?, ?)";
        $surv_stmt = $conn->prepare($surv_sql);
        $role = count($assigned) == 0 ? 'invigilator' : 'reserve';
        $status = 'assigned';
        $surv_stmt->bind_param("iiss", $exam_id, $prof['id'], $role, $status);

        if ($surv_stmt->execute()) {
            $assigned[] = $prof['id'];
        }
    }

    // If not enough from same department, get from other departments
    if (count($assigned) < $num_supervisors) {
        $other_profs_sql = "SELECT p.id, COUNT(sv.id) as surveillance_count
                            FROM professeurs p
                            LEFT JOIN surveillances sv ON p.id = sv.prof_id 
                            AND sv.status IN ('assigned', 'confirmed', 'completed')
                            WHERE p.department_id != ?
                            AND p.id NOT IN (
                                SELECT prof_id FROM surveillances 
                                WHERE exam_id = ?
                            )
                            GROUP BY p.id
                            ORDER BY surveillance_count ASC
                            LIMIT ?";

        $needed = $num_supervisors - count($assigned);
        $other_stmt = $conn->prepare($other_profs_sql);
        $other_stmt->bind_param("iii", $department_id, $exam_id, $needed);
        $other_stmt->execute();
        $other_profs = $other_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($other_profs as $prof) {
            $surv_sql = "INSERT INTO surveillances (exam_id, prof_id, role, status) 
                         VALUES (?, ?, ?, ?)";
            $surv_stmt = $conn->prepare($surv_sql);
            $role = count($assigned) == 0 ? 'invigilator' : 'reserve';
            $status = 'assigned';
            $surv_stmt->bind_param("iiss", $exam_id, $prof['id'], $role, $status);

            if ($surv_stmt->execute()) {
                $assigned[] = $prof['id'];
            }
        }
    }

    return array(
        'success' => count($assigned) >= $num_supervisors,
        'assigned_professors' => $assigned,
        'message' => count($assigned) . ' professor(s) assigned out of ' . $num_supervisors . ' required'
    );
}

/**
 * Validate exam creation against ALL constraints
 * @param int $module_id
 * @param string $exam_date (YYYY-MM-DD)
 * @param string $start_time (HH:MM:SS)
 * @param string $end_time (HH:MM:SS)
 * @param int $room_id
 * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
 */
function validate_exam_creation($module_id, $exam_date, $start_time, $end_time, $room_id)
{
    global $conn;

    $errors = array();
    $warnings = array();

    // Get module details
    $mod_sql = "SELECT * FROM modules WHERE id = ?";
    $mod_stmt = $conn->prepare($mod_sql);
    $mod_stmt->bind_param("i", $module_id);
    $mod_stmt->execute();
    $module = $mod_stmt->get_result()->fetch_assoc();

    // Get all enrolled students
    $std_sql = "SELECT DISTINCT i.student_id FROM inscriptions i WHERE i.module_id = ? AND i.status = 'active'";
    $std_stmt = $conn->prepare($std_sql);
    $std_stmt->bind_param("i", $module_id);
    $std_stmt->execute();
    $students = $std_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Constraint 1: Check room capacity
    $room_check = can_room_accommodate_exam($room_id, $module_id);
    if (!$room_check['allowed']) {
        $errors[] = "Room capacity violation: Room capacity is {$room_check['capacity']} but {$room_check['enrolled_count']} students are enrolled";
    }

    // Constraint 2: Check each student can take exam on this date (max 1/day)
    foreach ($students as $student) {
        $student_check = can_student_take_exam_on_date($student['student_id'], $exam_date);
        if (!$student_check['allowed']) {
            $exam_list = implode(', ', array_column($student_check['existing_exams'], 'module_code'));
            $warnings[] = "Student conflict: Some students already have exam(s) on {$exam_date}: {$exam_list}";
            break; // Only show once
        }
    }

    // Constraint 3: Check professors teaching this module
    $prof_sql = "SELECT DISTINCT m.professeur_id as prof_id FROM modules m WHERE m.id = ?";
    $prof_stmt = $conn->prepare($prof_sql);
    $prof_stmt->bind_param("i", $module_id);
    $prof_stmt->execute();
    $professors = $prof_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($professors as $prof) {
        // Check max exams per day
        $prof_check = can_professor_teach_exam_on_date($prof['prof_id'], $exam_date);
        if (!$prof_check['allowed']) {
            $warnings[] = "Professor overload: A professor has {$prof_check['exam_count']} exams on {$exam_date} (max {$prof_check['max_exams']})";
            break;
        }

        // Check time conflicts
        $time_check = check_professor_time_conflict($prof['prof_id'], $exam_date, $start_time, $end_time);
        if (!$time_check['allowed']) {
            $time_list = implode(', ', array_column($time_check['conflicting_exams'], 'module_code'));
            $warnings[] = "Professor time conflict: Professor has conflicting exam(s): {$time_list}";
            break;
        }
    }

    return array(
        'valid' => count($errors) == 0,
        'errors' => $errors,
        'warnings' => $warnings
    );
}

/**
 * Create exam with validation and conflict detection
 * @param int $module_id
 * @param string $exam_date (YYYY-MM-DD)
 * @param string $start_time (HH:MM:SS)
 * @param string $end_time (HH:MM:SS)
 * @param int $room_id
 * @param int $created_by (user_id)
 * @return array ['success' => bool, 'exam_id' => int, 'message' => string, 'validation' => array]
 */
function create_exam_with_validation($module_id, $exam_date, $start_time, $end_time, $room_id, $created_by)
{
    global $conn;

    // Validate first
    $validation = validate_exam_creation($module_id, $exam_date, $start_time, $end_time, $room_id);

    if (!$validation['valid']) {
        return array(
            'success' => false,
            'exam_id' => null,
            'message' => 'Validation failed: ' . implode('; ', $validation['errors']),
            'validation' => $validation
        );
    }

    // Create exam
    $sql = "INSERT INTO examens (module_id, exam_date, start_time, end_time, room_id, created_by, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $status = 'scheduled';
    $stmt->bind_param("isssii", $module_id, $exam_date, $start_time, $end_time, $room_id, $created_by, $status);

    if (!$stmt->execute()) {
        return array(
            'success' => false,
            'exam_id' => null,
            'message' => 'Failed to create exam: ' . $conn->error,
            'validation' => $validation
        );
    }

    $exam_id = $conn->insert_id;

    // Assign surveillances
    $surv_result = assign_surveillances_with_priority($exam_id, 2);

    // Log any warnings as conflicts
    foreach ($validation['warnings'] as $warning) {
        $severity = strpos($warning, 'overload') !== false ? 'high' : 'medium';
        record_conflict($exam_id, 'scheduling_warning', $warning, $severity);
    }

    return array(
        'success' => true,
        'exam_id' => $exam_id,
        'message' => 'Exam created successfully with ' . count($surv_result['assigned_professors']) . ' supervisors assigned',
        'validation' => $validation,
        'surveillances' => $surv_result
    );
}

// Check if user is a chef de departement
function is_chef_de_departement($user_id)
{
    global $conn;

    $sql = "SELECT d.id FROM departements d 
            JOIN professeurs p ON d.chef_id = p.id 
            WHERE p.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}
?>