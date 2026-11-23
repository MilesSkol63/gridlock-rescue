<?php
/**
 * Transactional, integrity-checked import into a NEW/EMPTY PostgreSQL database.
 * php database/import_postgres.php /secure/offline/snapshot
 * Explicit reimport: add --replace-destination after private backups, as OWNER only.
 * Uses MIGRATION_DATABASE_URL (not DATABASE_URL).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/transfer_lib.php';
$folder = $argv[1] ?? '';
$replace = ($argv[2] ?? '') === '--replace-destination';
if ($folder === '' || (isset($argv[2]) && !$replace)) stop_transfer('Usage: php database/import_postgres.php SNAPSHOT_FOLDER [--replace-destination]');
if ($replace && env_value('CONFIRM_DESTINATION_REPLACE') !== 'YES_DELETE_SUPABASE_DATA') {
    stop_transfer('Re-import must be explicitly confirmed through CONFIRM_DESTINATION_REPLACE=YES_DELETE_SUPABASE_DATA.');
}
try {
    $manifest = read_manifest($folder); // verify checksums before starting a transaction
    $pg = migration_pg_connection();
    $pg->beginTransaction();
    // Lock all tables up front: check / replace / import / verify are one atomic operation.
    $pg->exec('LOCK TABLE public.users, public.levels, public.user_progress, public.app_sessions IN ACCESS EXCLUSIVE MODE');
    if ($replace) {
        $pg->exec('TRUNCATE TABLE public.user_progress, public.levels, public.users, public.app_sessions RESTART IDENTITY');
    } else {
        foreach ([...array_keys(TRANSFER_COLUMNS), 'app_sessions'] as $table) {
            if ((int) $pg->query('SELECT count(*) FROM public.' . $table)->fetchColumn() !== 0) {
                throw new RuntimeException('Destination is not empty; import aborted before writing any records.');
            }
        }
    }
    // Parent rows first to preserve all FK constraints, children last.
    foreach (TRANSFER_COLUMNS as $table => $columns) {
        $markers = implode(', ', array_fill(0, count($columns), '?'));
        $statement = $pg->prepare('INSERT INTO public.' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $markers . ')');
        $in = new SplFileObject($folder . '/' . $table . '.jsonl', 'rb');
        $count = 0;
        while (!$in->eof()) {
            $line = $in->fgets();
            if (trim($line) === '') continue;
            $source = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($source)) throw new RuntimeException('Invalid row in ' . $table);
            $values = canonical_row($table, $source);
            if ($table === 'users') $values['is_active'] = $values['is_active'] === '1' ? 'true' : 'false';
            $statement->execute(array_values($values));
            $count++;
        }
        if ($count !== $manifest['tables'][$table]['rows']) throw new RuntimeException('Row count changed after snapshot checksum validation');
        echo "$table: $count rows imported\n";
    }
    // Identity sequence restarts are transactional (unlike setval() side effects).
    foreach (TRANSFER_COLUMNS as $table => $columns) {
        $id = $columns[0];
        $next = (int) $pg->query('SELECT COALESCE(MAX(' . $id . '), 0) + 1 FROM public.' . $table)->fetchColumn();
        $pg->exec('ALTER TABLE public.' . $table . ' ALTER COLUMN ' . $id . ' RESTART WITH ' . $next);
    }
    // Verify every record, every field, in ID order against source export, not only counts.
    foreach (TRANSFER_COLUMNS as $table => $columns) {
        $cursor = $pg->query('SELECT ' . implode(', ', $columns) . ' FROM public.' . $table . ' ORDER BY ' . $columns[0]);
        $hash = hash_init('sha256');
        $count = 0;
        while ($row = $cursor->fetch(PDO::FETCH_ASSOC)) {
            hash_update($hash, encode_row(canonical_row($table, $row)));
            $count++;
        }
        $cursor->closeCursor();
        if ($count !== $manifest['tables'][$table]['rows']
            || !hash_equals($manifest['tables'][$table]['sha256'], hash_final($hash))) {
            throw new RuntimeException('Data mismatch in table ' . $table . '; destination will be rolled back');
        }
    }
    $pg->commit();
    echo "Import committed. Every row and field matches the offline snapshot.\n";
} catch (Throwable $ex) {
    if (isset($pg) && $pg->inTransaction()) $pg->rollBack();
    // Avoid leaking PostgreSQL passwords or host details inside exceptions.
    stop_transfer('Import was NOT committed: ' . (str_contains($ex->getMessage(), 'mismatch') ? 'verification mismatch' : get_class($ex)) . '.');
}
