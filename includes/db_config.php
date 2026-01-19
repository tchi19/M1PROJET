<?php
/**
 * Database configuration
 * Works on: Local (WAMP/XAMPP) AND Railway (MySQL)
 */

// Detect Railway environment
if (getenv("MYSQL_HOST")) {
    // ===== RAILWAY CONFIG =====
    $DB_HOST = getenv("MYSQL_HOST");
    $DB_USER = getenv("MYSQL_USER");
    $DB_PASS = getenv("MYSQL_PASSWORD");
    $DB_NAME = getenv("MYSQL_DATABASE");
    $DB_PORT = getenv("MYSQL_PORT");
} else {
    // ===== LOCAL CONFIG =====
    $DB_HOST = "localhost";
    $DB_USER = "root";
    $DB_PASS = "";
    $DB_NAME = "exam_timetable";
    $DB_PORT = 3306;
}

// Create MySQL connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");
?>
