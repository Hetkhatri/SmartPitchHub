<?php
// UNDO SCRIPT for Step 2 - Investment Console Integration
require 'db.php';

echo "Rolling back Investment Console changes...\n";

// Note: This script is a bit manual because I modified existing files.
// For a real undo, I'd need to restore the exact contents. 
// However, since I am an AI, I can't easily 'restore' without a backup file.
// I will provide instructions to manually revert if needed, OR 
// I can attempt to 'replace' back.

// Instruction: The most reliable way to undo is to use the edit history of your IDE.
// But as an AI, I can try to undo the process-investment.php file.
if (unlink('Investment/process-investment.php')) {
    echo "SUCCESS: process-investment.php deleted.\n";
} else {
    echo "ERROR: Could not delete process-investment.php\n";
}

echo "NOTE: UI changes in investment-console.php must be reverted manually or by me upon request.\n";
?>