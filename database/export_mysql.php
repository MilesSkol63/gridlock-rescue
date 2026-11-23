<?php
/**
 * Read-only consistent snapshot of the THREE verified Railway MySQL tables.
 * php database/export_mysql.php /secure/offline/snapshot
 * Requires ext-pdo_mysql and MYSQL_SOURCE_URL (separate from destination credentials).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/transfer_lib.php';
$folder = $argv[1] ?? '';
if ($folder === '' || is_dir($folder) || file_exists($folder)) stop_transfer('Provide a NEW destination folder (will not overwrite backups).');
$url = env_value('MYSQL_SOURCE_URL');
$parts = parse_url($url);
if (!$parts || ($parts['scheme'] ?? '') !== 'mysql' || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) {
    stop_transfer('MYSQL_SOURCE_URL must be a complete mysql:// URI; not a PostgreSQL URI.');
}
$database = ltrim($parts['path'], '/');
if ($database === '' || str_contains($database, '/')) stop_transfer('Invalid MySQL database name.');
$dsn = 'mysql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 3306) . ';dbname=' . rawurldecode($database) . ';charset=utf8mb4';
// The backup directory receives hashes, account names, and progress; keep it private.
try {
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('The PHP pdo_mysql extension is required for source export');
    $src = new PDO($dsn, rawurldecode($parts['user']), rawurldecode($parts['pass']), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    $src->exec("SET SESSION time_zone = '+00:00'");
    $src->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $src->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $tables = $src->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    $expected = array_keys(TRANSFER_COLUMNS);
    sort($expected);
    if ($tables !== $expected) {
        throw new RuntimeException('Unexpected source table inventory: ' . implode(', ', $tables) . '. Review and extend migration tooling; no data was modified.');
    }
    if (!mkdir($folder, 0700, true) || !is_dir($folder)) throw new RuntimeException('Failed to create export directory');
    $manifest = ['format_version' => 1, 'exported_at_utc' => gmdate('c'), 'tables' => []];
    foreach (TRANSFER_COLUMNS as $table => $columns) {
        $file = $folder . '/' . $table . '.jsonl';
        $out = fopen($file, 'xb');
        if ($out === false) throw new RuntimeException('Could not create output file for ' . $table);
        $cursor = $src->query('SELECT * FROM `' . $table . '` ORDER BY `' . $columns[0] . '` ASC');
        $count = 0;
        while ($row = $cursor->fetch(PDO::FETCH_ASSOC)) {
            $normalized = canonical_row($table, $row);
            $line = encode_row($normalized);
            if (fwrite($out, $line) !== strlen($line)) throw new RuntimeException('Disk write incomplete');
            $count++;
        }
        $cursor->closeCursor();
        fclose($out);
        chmod($file, 0600);
        $manifest['tables'][$table] = ['columns' => $columns, 'rows' => $count, 'sha256' => hash_file('sha256', $file)];
        echo "$table: $count rows exported\n";
    }
    $src->commit();
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    file_put_contents($folder . '/manifest.json', $encoded . "\n", LOCK_EX);
    chmod($folder . '/manifest.json', 0600);
    echo "Consistent read-only snapshot complete: $folder\n";
} catch (Throwable $ex) {
    if (isset($src) && $src->inTransaction()) $src->rollBack();
    // Remove any incomplete snapshot on failure; never leave files that appear complete.
    if (isset($folder) && is_dir($folder) && !is_file($folder . '/manifest.json')) {
        foreach (glob($folder . '/*.jsonl') ?: [] as $partial) @unlink($partial);
        @rmdir($folder);
    }
    // Some PDO errors may include endpoint info; never print the raw exception in logs.
    stop_transfer('Export failed (' . get_class($ex) . '); check connectivity, table inventory and permissions.');
}
