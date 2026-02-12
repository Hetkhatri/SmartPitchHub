<?php
require_once 'db.php';

$queries = [
    "ALTER TABLE `pitches` ADD COLUMN `duration_days` INT DEFAULT 30 AFTER `round_name`",
    "ALTER TABLE `pitches` ADD COLUMN `expiry_date` DATETIME NULL AFTER `duration_days` ",
    "ALTER TABLE `pitches` ADD COLUMN `round_status` ENUM('active', 'completed', 'expired', 'archived') DEFAULT 'active' AFTER `expiry_date` ",
    "ALTER TABLE `pitches` ADD COLUMN `round_number` INT DEFAULT 1 AFTER `round_status` ",
    "UPDATE `pitches` SET `expiry_date` = DATE_ADD(`created_at`, INTERVAL 30 DAY) WHERE `expiry_date` IS NULL"
];

echo "Starting Database Migration for Rounds Logic...\n";

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "[SUCCESS] Executed: $sql\n";
    } else {
        echo "[ERROR] Failed: $sql - " . $conn->error . "\n";
    }
}

echo "Migration Complete.\n";
$conn->close();
?>