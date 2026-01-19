<?php
require_once 'db_config.php';

// Create tables
$sql = array(

    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'doyen', 'chef', 'prof', 'student') NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        active BOOLEAN DEFAULT TRUE
    )",

    // Departments table
    "CREATE TABLE IF NOT EXISTS departements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        chef_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chef_id) REFERENCES professeurs(id) ON DELETE SET NULL
    )",

    // Formations table
    "CREATE TABLE IF NOT EXISTS formations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        department_id INT NOT NULL,
        description TEXT,
        duration_months INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departements(id) ON DELETE CASCADE,
        UNIQUE(name, department_id)
    )",

    // Modules table
    "CREATE TABLE IF NOT EXISTS modules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        formation_id INT NOT NULL,
        professeur_id INT NOT NULL,
        credit_hours INT,
        semester INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
        FOREIGN KEY (professeur_id) REFERENCES professeurs(id) ON DELETE CASCADE
    )",

    // Professors table
    "CREATE TABLE IF NOT EXISTS professeurs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        department_id INT NOT NULL,
        speciality VARCHAR(255),
        max_exams_per_day INT DEFAULT 3,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departements(id) ON DELETE CASCADE
    )",

    // Students table
    "CREATE TABLE IF NOT EXISTS etudiants (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        formation_id INT NOT NULL,
        student_number VARCHAR(50) NOT NULL UNIQUE,
        enrollment_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
    )",

    // Exam Rooms table
    "CREATE TABLE IF NOT EXISTS salles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        capacity INT NOT NULL,
        location VARCHAR(255),
        type ENUM('classroom', 'amphi', 'computer_lab') DEFAULT 'classroom',
        department_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departements(id) ON DELETE SET NULL
    )",

    // Enrollments (Students in Modules) table
    "CREATE TABLE IF NOT EXISTS inscriptions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        module_id INT NOT NULL,
        enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        grade DECIMAL(5, 2),
        status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
        FOREIGN KEY (student_id) REFERENCES etudiants(id) ON DELETE CASCADE,
        FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
        UNIQUE(student_id, module_id)
    )",



    // Exams table
    "CREATE TABLE IF NOT EXISTS examens (
        id INT PRIMARY KEY AUTO_INCREMENT,
        module_id INT NOT NULL,
        formation_id INT,
        group_number INT NOT NULL DEFAULT 1,
        exam_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        room_id INT NOT NULL,
        created_by INT,
        status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
        accepted_by_chefdep BOOLEAN DEFAULT NULL,
        accepted_by_doyen BOOLEAN DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
        FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL,
        FOREIGN KEY (room_id) REFERENCES salles(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE(module_id, group_number, exam_date, start_time)
    )",

    // Exam Supervisions (Surveillances) table
    "CREATE TABLE IF NOT EXISTS surveillances (
        id INT PRIMARY KEY AUTO_INCREMENT,
        exam_id INT NOT NULL,
        prof_id INT NOT NULL,
        role ENUM('invigilator', 'reserve') DEFAULT 'invigilator',
        assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('assigned', 'confirmed', 'completed', 'absent') DEFAULT 'assigned',
        FOREIGN KEY (exam_id) REFERENCES examens(id) ON DELETE CASCADE,
        FOREIGN KEY (prof_id) REFERENCES professeurs(id) ON DELETE CASCADE,
        UNIQUE(exam_id, prof_id)
    )",

    // Conflicts table
    "CREATE TABLE IF NOT EXISTS conflicts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        exam_id INT,
        conflict_type ENUM('student_overlap', 'prof_overload', 'room_capacity', 'time_conflict') NOT NULL,
        description TEXT,
        severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
        resolved BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES examens(id) ON DELETE CASCADE
    )"
);

// Execute table creation
foreach ($sql as $query) {
    if ($conn->query($query) === FALSE) {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

echo "Database tables initialized successfully!";
?>