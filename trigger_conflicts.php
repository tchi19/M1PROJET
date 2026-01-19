<?php
require_once 'includes/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/conflict_detection_logic.php';

echo "Triggering Conflict Detection...\n";

// Run detection
detect_and_store_conflicts();

echo "Conflict Detection Completed.\n";

// Count results
$res = $conn->query("SELECT conflict_type, COUNT(*) as cnt FROM conflicts WHERE resolved = FALSE GROUP BY conflict_type");
while ($row = $res->fetch_assoc()) {
    echo $row['conflict_type'] . ": " . $row['cnt'] . "\n";
}
?>
