<?php
/** Shared PDO PostgreSQL connection; no MySQL dependency. */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/config.php';

/** Fail without disclosing PostgreSQL endpoints, users, secrets or error details. */
function show_fatal_error(string $details): never
{
    error_log('Gridlock Rescue database failure: ' . $details);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection or configuration failed; see server logs.\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Service unavailable</title><main><h1>Gridlock Rescue is temporarily unavailable</h1><p>Please try again later.</p></main></html>';
    exit;
}

function new_pg_connection(): PDO
{
    [$dsn, $user, $password] = db_connection_settings();
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true, // required by Supabase shared transaction pooler
        PDO::ATTR_PERSISTENT => false, // no stateful connections across serverless requests
    ]);
}

try {
    $conn = new_pg_connection();
} catch (Throwable $ex) {
    // Do not log raw exception: DSNs or credentials may appear in driver errors.
    show_fatal_error(get_class($ex) . ': connection/configuration failed');
}
