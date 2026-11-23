<?php
/** Shared CLI-only row formats and validation for exact, repeatable backups. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/config.php';

const TRANSFER_COLUMNS = [
    'users' => ['user_id','username','email','password_hash','role','is_active','created_at'],
    'levels' => ['level_id','game_type','title','hint','difficulty','sort_order','grid_width','grid_height','layout','par_moves','created_at'],
    'user_progress' => ['progress_id','user_id','level_id','best_moves','completed_at','updated_at'],
];
const TRANSFER_INTEGERS = [
    'user_id','level_id','progress_id','sort_order','grid_width','grid_height','par_moves','best_moves',
];

function stop_transfer(string $message): never
{
    fwrite(STDERR, "Transfer stopped: $message\n");
    exit(1);
}

/** Canonical cross-driver representation: normalized string integers, booleans, and exact UTC timestamps. */
function canonical_row(string $table, array $row): array
{
    $out = [];
    foreach (TRANSFER_COLUMNS[$table] as $key) {
        if (!array_key_exists($key, $row)) {
            throw new RuntimeException("Missing required column in $table: $key");
        }
        $value = $row[$key];
        if ($value !== null && in_array($key, TRANSFER_INTEGERS, true)) {
            if (!preg_match('/^\\d+$/', (string) $value)) throw new RuntimeException("Invalid integer in $table.$key");
            $value = (string) (int) $value;
        }
        if ($key === 'is_active') {
            if (in_array($value, [true, 't', 'true', 1, '1'], true)) $value = '1';
            elseif (in_array($value, [false, 'f', 'false', 0, '0'], true)) $value = '0';
            else throw new RuntimeException('Invalid account status in export or database');
        }
        $out[$key] = $value;
    }
    if (count($row) !== count($out)) {
        throw new RuntimeException("Unexpected column found in $table; investigate source schema before migration");
    }
    return $out;
}

function encode_row(array $row): string
{
    return json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function migration_pg_connection(): PDO
{
    [$dsn, $user, $pass] = db_connection_settings('MIGRATION_DATABASE_URL');
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true, PDO::ATTR_PERSISTENT => false]);
}

function read_manifest(string $path): array
{
    $manifest = json_decode((string) file_get_contents($path . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['format_version'] ?? null) !== 1 || !isset($manifest['tables'])) {
        throw new RuntimeException('Unrecognized transfer manifest format');
    }
    foreach (TRANSFER_COLUMNS as $table => $columns) {
        if (($manifest['tables'][$table]['columns'] ?? null) !== $columns
            || !is_int($manifest['tables'][$table]['rows'] ?? null)
            || !preg_match('/^[0-9a-f]{64}$/', $manifest['tables'][$table]['sha256'] ?? '')) {
            throw new RuntimeException("Missing or incompatible manifest for $table");
        }
        if (!is_file($path . '/' . $table . '.jsonl')
            || !hash_equals($manifest['tables'][$table]['sha256'], hash_file('sha256', $path . '/' . $table . '.jsonl'))) {
            throw new RuntimeException("Export checksum mismatch for $table");
        }
    }
    if (count($manifest['tables']) !== count(TRANSFER_COLUMNS)) {
        throw new RuntimeException('Manifest contains unsupported additional tables');
    }
    return $manifest;
}
