<?php
require_once '../includes/db_config.php';

set_time_limit(300);

echo "<h1>Seeding Professors (30 per Department)</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .warning{color:orange;} .error{color:red;}</style>";

// Get all departments
$departments = $conn->query("SELECT id, name FROM departements ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if (count($departments) == 0) {
    die("<p class='error'>Error: No departments found. Please create departments first.</p>");
}

echo "<p>Found <strong>" . count($departments) . "</strong> departments.</p>";

$password = password_hash('prof123', PASSWORD_BCRYPT);
$profs_per_dept = 30;

// Specialities per department type
$specialities = [
    'default' => ['Research', 'Teaching', 'Applied Sciences', 'Theory', 'Methodology', 'Systems', 'Analysis', 'Development', 'Management', 'Engineering']
];

// First names and last names for generating realistic names
$first_names = [
    'Ahmed',
    'Mohamed',
    'Fatima',
    'Amina',
    'Youssef',
    'Hassan',
    'Leila',
    'Karim',
    'Nadia',
    'Omar',
    'Sara',
    'Rachid',
    'Hana',
    'Bilal',
    'Salma',
    'Tariq',
    'Rania',
    'Jamal',
    'Dina',
    'Faisal',
    'Layla',
    'Samir',
    'Noor',
    'Walid',
    'Mona',
    'Imad',
    'Sana',
    'Khalid',
    'Houda',
    'Badr'
];
$last_names = [
    'Benali',
    'Mansouri',
    'El-Khatib',
    'Bouaziz',
    'Nasri',
    'Hamdi',
    'Tazi',
    'Chraibi',
    'Alami',
    'Berrada',
    'Fassi',
    'Idrissi',
    'Bennani',
    'Ouazzani',
    'Senhaji',
    'Ziani',
    'Kettani',
    'Mohammadi',
    'Bahri',
    'Lahlou'
];

$profs_created = 0;
$prof_index = 0;

echo "<h2>Creating Professors...</h2>";

foreach ($departments as $dept) {
    $dept_id = $dept['id'];
    $dept_name = $dept['name'];

    // Check existing professors for this department
    $existing = $conn->query("SELECT COUNT(*) as c FROM professeurs WHERE department_id = $dept_id")->fetch_assoc()['c'];

    if ($existing >= $profs_per_dept) {
        echo "<p class='warning'>Skipping '{$dept_name}' - already has {$existing} professors.</p>";
        continue;
    }

    $to_create = $profs_per_dept - $existing;
    echo "<p>Creating {$to_create} professors for <strong>{$dept_name}</strong>...</p>";

    for ($i = 0; $i < $to_create; $i++) {
        $prof_index++;

        // Generate name
        $first = $first_names[array_rand($first_names)];
        $last = $last_names[array_rand($last_names)];
        $full_name = "Dr. {$first} {$last}";

        // Generate unique email
        $email = 'prof' . $prof_index . '@university.edu';

        // Generate phone
        $phone = '06' . str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        // Random speciality
        $speciality = $specialities['default'][array_rand($specialities['default'])] . ' - ' . $dept_name;

        // Create user first
        $sql = "INSERT INTO users (email, password, role, full_name, phone) VALUES (?, ?, 'prof', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $email, $password, $full_name, $phone);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;

            // Create professor record
            $sql = "INSERT INTO professeurs (user_id, department_id, speciality, max_exams_per_day) VALUES (?, ?, ?, 3)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $user_id, $dept_id, $speciality);

            if ($stmt->execute()) {
                $profs_created++;
            }
        }
    }

    flush();
}

// Final stats
$total_profs = $conn->query("SELECT COUNT(*) as c FROM professeurs")->fetch_assoc()['c'];
$depts_with_profs = $conn->query("SELECT COUNT(DISTINCT department_id) as c FROM professeurs")->fetch_assoc()['c'];

echo "<h2 class='success'>✅ Professor Seeding Complete!</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Metric</th><th>Count</th></tr>";
echo "<tr><td>Professors Created (this run)</td><td><strong>{$profs_created}</strong></td></tr>";
echo "<tr><td>Total Professors in Database</td><td><strong>{$total_profs}</strong></td></tr>";
echo "<tr><td>Departments with Professors</td><td><strong>{$depts_with_profs}</strong></td></tr>";
echo "</table>";

echo "<p style='margin-top:20px;'>";
echo "<a href='professors.php' style='padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin-right:10px;'>View Professors</a>";
echo "<a href='seed_modules.php' style='padding:10px 20px;background:#ff9800;color:white;text-decoration:none;border-radius:5px;margin-right:10px;'>Next: Populate Modules</a>";
echo "<a href='dashboard.php' style='padding:10px 20px;background:#4caf50;color:white;text-decoration:none;border-radius:5px;'>Back to Dashboard</a>";
echo "</p>";
?>