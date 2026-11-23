<?php
/** Independent read-only re-verification after commit. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/transfer_lib.php';
$folder = $argv[1] ?? '';
if (!$folder) stop_transfer('Usage: php database/verify_postgres.php SNAPSHOT_FOLDER');
try {
    $manifest = read_manifest($folder);
    $pg = migration_pg_connection();
    $pg->beginTransaction();
    $pg->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    foreach (TRANSFER_COLUMNS as $table => $columns) {
        $st = $pg->query('SELECT ' . implode(', ', $columns) . ' FROM public.' . $table . ' ORDER BY ' . $columns[0]);
        $hash = hash_init('sha256'); $count = 0;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            hash_update($hash, encode_row(canonical_row($table, $row)));
            $count++;
        }
        if ($count !== $manifest['tables'][$table]['rows']
            || !hash_equals(hash_final($hash), $manifest['tables'][$table]['sha256'])) {
            throw new RuntimeException('Verification mismatch in ' . $table);
        }
        echo "$table: $count rows / SHA-256 verified\n";
    }
    $pg->commit();
    echo "VERIFIED: PostgreSQL tables match snapshot exactly.\n";
} catch (Throwable $ex) {
    if (isset($pg) && $pg->inTransaction()) $pg->rollBack();
    stop_transfer('Verification FAILED. See source and target data manually.');
}
