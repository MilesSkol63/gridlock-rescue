<?php
/**
 * database/import.php  (NEW)  - COMMAND-LINE-ONLY helper that runs database/gridlock_rescue.sql
 * through the application's own MySQLi connection (works on Railway's private network).
 *     php database/import.php                      -> gridlock_rescue.sql (new OR existing database, safe to repeat)
 *     php database/import.php migrate_sliding.sql  -> only the Sokoban -> sliding-block migration
 *     php database/import.php migrate_user_status.sql -> only the account status (disable/enable) migration
 * Safe to run again: tables use IF NOT EXISTS, the migration is guarded and the levels are skipped if present.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$allowed = ['gridlock_rescue.sql', 'migrate_sliding.sql', 'migrate_user_status.sql'];
$file = $argv[1] ?? 'gridlock_rescue.sql';
if (!in_array($file, $allowed, true)) {
    fwrite(STDERR, 'Unknown file. Use one of: ' . implode(', ', $allowed) . PHP_EOL);
    exit(1);
}
$sql = file_get_contents(__DIR__ . '/' . $file);
if ($sql === false) {
    fwrite(STDERR, 'Cannot read ' . $file . PHP_EOL);
    exit(1);
}

try {
    mysqli_multi_query($conn, $sql);
    do {
        $result = mysqli_store_result($conn);
        if ($result instanceof mysqli_result) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
} catch (mysqli_sql_exception $ex) {
    fwrite(STDERR, 'Import failed: ' . $ex->getMessage() . PHP_EOL);
    exit(1);
}

$count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM levels WHERE game_type = 'sliding'"));
$legacy = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM levels WHERE game_type = 'sokoban'"));
echo $file . ' applied. Sliding-block levels: ' . $count['total'] . '. Archived Sokoban levels kept: ' . $legacy['total'] . PHP_EOL;
