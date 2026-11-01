<?php
/**
 * database/import.php  (NEW)  - COMMAND-LINE-ONLY helper that runs database/gridlock_rescue.sql
 * through the application's own MySQLi connection (works on Railway's private network).
 *     php database/import.php
 * Safe to run again: tables use IF NOT EXISTS and sample levels are skipped if present.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$sql = file_get_contents(__DIR__ . '/gridlock_rescue.sql');
if ($sql === false) {
    fwrite(STDERR, 'Cannot read gridlock_rescue.sql' . PHP_EOL);
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

$count = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total FROM levels'));
echo 'Schema imported. Levels in database: ' . $count['total'] . PHP_EOL;
