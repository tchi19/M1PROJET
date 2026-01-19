<?php
require_once 'c:/XAMPP/htdocs/M1PROJET/includes/db_config.php';
$m = $conn->query("SELECT COUNT(*) as c FROM modules")->fetch_assoc()['c'];
$i = $conn->query("SELECT COUNT(*) as c FROM inscriptions")->fetch_assoc()['c'];
$i_active = $conn->query("SELECT COUNT(*) as c FROM inscriptions WHERE status = 'active'")->fetch_assoc()['c'];
echo "Modules: $m\n";
echo "Total Inscriptions: $i\n";
echo "Active Inscriptions: $i_active\n";
?>