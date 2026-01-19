<?php
require_once '../includes/db_config.php';

set_time_limit(300);

echo "<h1>Seeding Modules (6-9 per Formation)</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>";

// Check if we have professors
$professors = $conn->query("SELECT p.id, p.department_id FROM professeurs p")->fetch_all(MYSQLI_ASSOC);
if (count($professors) == 0) {
    die("<p class='error'>Error: No professors found. Please create professors first (they are required to assign to modules).</p>");
}

// Group professors by department
$profs_by_dept = [];
foreach ($professors as $prof) {
    $dept_id = $prof['department_id'];
    if (!isset($profs_by_dept[$dept_id])) {
        $profs_by_dept[$dept_id] = [];
    }
    $profs_by_dept[$dept_id][] = $prof['id'];
}

echo "<p>Found <strong>" . count($professors) . "</strong> professors across <strong>" . count($profs_by_dept) . "</strong> departments.</p>";

// Get all formations
$formations = $conn->query("SELECT f.id, f.name, f.department_id, d.name as department_name 
                            FROM formations f 
                            JOIN departements d ON f.department_id = d.id 
                            ORDER BY d.id, f.id")->fetch_all(MYSQLI_ASSOC);

if (count($formations) == 0) {
    die("<p class='error'>Error: No formations found. Please create formations first.</p>");
}

echo "<p>Found <strong>" . count($formations) . "</strong> formations.</p>";

// Module name templates per subject area
$module_templates = [
    'general' => ['Introduction to', 'Fundamentals of', 'Principles of', 'Basics of', 'Theory of', 'Applied', 'Advanced', 'Modern', 'Contemporary'],
    'subjects' => ['Analysis', 'Systems', 'Methods', 'Techniques', 'Applications', 'Design', 'Development', 'Management', 'Research', 'Practice', 'Studies', 'Concepts', 'Modeling', 'Computing', 'Engineering']
];

$modules_created = 0;
$formations_processed = 0;
$module_index = 0;

echo "<h2>Creating Modules...</h2>";

foreach ($formations as $formation) {
    $formation_id = $formation['id'];
    $dept_id = $formation['department_id'];
    $formation_name = $formation['name'];

    // Check existing modules for this formation
    $existing = $conn->query("SELECT COUNT(*) as c FROM modules WHERE formation_id = $formation_id")->fetch_assoc()['c'];

    if ($existing >= 6) {
        echo "<p class='warning'>Skipping '{$formation_name}' - already has {$existing} modules.</p>";
        continue;
    }

    // How many modules to create (6-9 total, minus existing)
    $target_modules = rand(6, 9);
    $to_create = $target_modules - $existing;

    // Get professors for this department (or use any if none in dept)
    $available_profs = $profs_by_dept[$dept_id] ?? array_column($professors, 'id');
    if (count($available_profs) == 0) {
        $available_profs = array_column($professors, 'id');
    }

    $prof_index = 0;

    for ($i = 0; $i < $to_create; $i++) {
        $module_index++;

        // Generate module name based on formation
        $prefix = $module_templates['general'][array_rand($module_templates['general'])];
        $suffix = $module_templates['subjects'][array_rand($module_templates['subjects'])];
        $module_name = $prefix . ' ' . $suffix . ' ' . ($existing + $i + 1);

        // Generate unique code
        $code = 'MOD' . str_pad($module_index, 5, '0', STR_PAD_LEFT);

        // Assign professor (round-robin within department)
        $prof_id = $available_profs[$prof_index % count($available_profs)];
        $prof_index++;

        // Random semester and credit hours
        $semester = rand(1, 6);
        $credit_hours = rand(2, 4);

        $sql = "INSERT INTO modules (name, code, formation_id, professeur_id, credit_hours, semester) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiii", $module_name, $code, $formation_id, $prof_id, $credit_hours, $semester);

        if ($stmt->execute()) {
            $modules_created++;
        }
    }

    $formations_processed++;

    // Progress update
    if ($formations_processed % 20 == 0) {
        echo "<p>Processed {$formations_processed}/" . count($formations) . " formations...</p>";
        flush();
    }
}

// Final stats
$total_modules = $conn->query("SELECT COUNT(*) as c FROM modules")->fetch_assoc()['c'];
$formations_with_modules = $conn->query("SELECT COUNT(DISTINCT formation_id) as c FROM modules")->fetch_assoc()['c'];

echo "<h2 class='success'>✅ Module Seeding Complete!</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Metric</th><th>Count</th></tr>";
echo "<tr><td>Modules Created (this run)</td><td><strong>{$modules_created}</strong></td></tr>";
echo "<tr><td>Total Modules in Database</td><td><strong>{$total_modules}</strong></td></tr>";
echo "<tr><td>Formations with Modules</td><td><strong>{$formations_with_modules}</strong></td></tr>";
echo "</table>";

echo "<p style='margin-top:20px;'>";
echo "<a href='modules.php' style='padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin-right:10px;'>View Modules</a>";
echo "<a href='dashboard.php' style='padding:10px 20px;background:#4caf50;color:white;text-decoration:none;border-radius:5px;'>Back to Dashboard</a>";
echo "</p>";
?>