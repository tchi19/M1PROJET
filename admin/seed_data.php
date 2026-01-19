<?php
require_once '../includes/db_config.php';

// Increase execution time for large data
set_time_limit(300);

echo "<h1>Seeding Database - 13,000 Students</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .progress{background:#e0e0e0;border-radius:5px;height:20px;margin:10px 0;} .bar{background:#4caf50;height:100%;border-radius:5px;transition:width 0.3s;}</style>";

// --- Configuration ---
$total_students = 13000;
$password = password_hash('student123', PASSWORD_BCRYPT);
$batch_size = 100; // Insert in batches for performance

// --- Get existing data ---
$formations = $conn->query("SELECT f.id, f.name, d.id as department_id, d.name as department_name 
                            FROM formations f 
                            JOIN departements d ON f.department_id = d.id 
                            ORDER BY d.id, f.id")->fetch_all(MYSQLI_ASSOC);
$modules = $conn->query("SELECT id, formation_id FROM modules")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT id, name FROM departements ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if (count($formations) == 0) {
    die("<p style='color:red;'>Error: No formations found. Please run database.sql first.</p>");
}

echo "<p>Found <strong>" . count($departments) . " departments</strong> with <strong>" . count($formations) . " formations</strong>.</p>";

// Group formations by department for fair distribution
$formations_by_dept = [];
foreach ($formations as $f) {
    $dept_id = $f['department_id'];
    if (!isset($formations_by_dept[$dept_id])) {
        $formations_by_dept[$dept_id] = [];
    }
    $formations_by_dept[$dept_id][] = $f;
}

// Calculate students per department (roughly equal)
$num_departments = count($departments);
$students_per_dept = intval($total_students / $num_departments);
$remaining = $total_students - ($students_per_dept * $num_departments);

echo "<h2>Distribution Plan:</h2><ul>";
foreach ($departments as $idx => $dept) {
    $count = $students_per_dept + ($idx < $remaining ? 1 : 0);
    echo "<li><strong>{$dept['name']}</strong>: ~$count students</li>";
}
echo "</ul>";

// --- Create Students ---
echo "<h2>Creating Students...</h2>";
$students_created = 0;
$student_index = 0;

foreach ($departments as $dept_idx => $dept) {
    $dept_id = $dept['id'];
    $dept_name = $dept['name'];
    $dept_formations = $formations_by_dept[$dept_id] ?? [];

    if (count($dept_formations) == 0) {
        echo "<p style='color:orange;'>Warning: No formations in {$dept_name}, skipping...</p>";
        continue;
    }

    // Students for this department
    $dept_student_count = $students_per_dept + ($dept_idx < $remaining ? 1 : 0);

    echo "<p>Creating {$dept_student_count} students for <strong>{$dept_name}</strong>...</p>";
    flush();

    for ($i = 0; $i < $dept_student_count; $i++) {
        $student_index++;

        // Pick a random formation from this department
        $formation = $dept_formations[array_rand($dept_formations)];

        // Generate unique identifiers
        $student_num = 'STU' . str_pad($student_index, 6, '0', STR_PAD_LEFT);
        $email = 'student' . $student_index . '@university.edu';
        $full_name = 'Student ' . $student_index;

        // Create user
        $sql = "INSERT INTO users (email, password, role, full_name) VALUES (?, ?, 'student', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $email, $password, $full_name);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;

            // Create student record
            $sql = "INSERT INTO etudiants (user_id, formation_id, student_number, enrollment_date) VALUES (?, ?, ?, CURDATE())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $user_id, $formation['id'], $student_num);

            if ($stmt->execute()) {
                $students_created++;
            }
        }

        // Progress update every 500 students
        if ($students_created % 500 == 0 && $students_created > 0) {
            $pct = round(($students_created / $total_students) * 100);
            echo "<script>document.title = 'Seeding: $pct%';</script>";
            flush();
        }
    }
}

echo "<p style='color:green;font-size:18px;'>✓ Created <strong>$students_created</strong> students.</p>";

// --- Enroll Students in Modules ---
echo "<h2>Enrolling Students in Modules...</h2>";
echo "<p>This may take a while for " . count($modules) . " modules...</p>";
flush();

// Build module lookup by formation_id for faster access
$modules_by_formation = [];
foreach ($modules as $module) {
    $fid = $module['formation_id'];
    if (!isset($modules_by_formation[$fid])) {
        $modules_by_formation[$fid] = [];
    }
    $modules_by_formation[$fid][] = $module['id'];
}

// Fetch all students
$students = $conn->query("SELECT id, formation_id FROM etudiants")->fetch_all(MYSQLI_ASSOC);
$enrollments_created = 0;
$student_count = count($students);

foreach ($students as $idx => $student) {
    $formation_modules = $modules_by_formation[$student['formation_id']] ?? [];

    foreach ($formation_modules as $module_id) {
        $sql = "INSERT IGNORE INTO inscriptions (student_id, module_id, status) VALUES (?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student['id'], $module_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $enrollments_created++;
        }
    }

    // Progress update every 1000 students
    if (($idx + 1) % 1000 == 0) {
        $pct = round((($idx + 1) / $student_count) * 100);
        $enrolled_count = $idx + 1;
        echo "<p>Enrolled {$enrolled_count}/{$student_count} students ($pct%)...</p>";
        flush();
    }
}

echo "<p style='color:green;font-size:18px;'>✓ Created <strong>$enrollments_created</strong> enrollments.</p>";

// --- Summary ---
echo "<h2 style='color:green;'>✅ Seeding Complete!</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Metric</th><th>Count</th></tr>";
echo "<tr><td>Total Students Created</td><td><strong>$students_created</strong></td></tr>";
echo "<tr><td>Total Enrollments Created</td><td><strong>$enrollments_created</strong></td></tr>";
echo "<tr><td>Departments</td><td><strong>" . count($departments) . "</strong></td></tr>";
echo "<tr><td>Formations</td><td><strong>" . count($formations) . "</strong></td></tr>";
echo "</table>";

echo "<p style='margin-top:20px;'><a href='scheduling.php' style='padding:10px 20px;background:#4caf50;color:white;text-decoration:none;border-radius:5px;'>Go to Scheduling Page</a></p>";
?>