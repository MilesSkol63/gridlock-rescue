<?php
/** Environment-only configuration for the separate Supabase deployment. */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

define('APP_NAME', 'Gridlock Rescue');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function env_value(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? ($_SERVER[$name] ?? '');
    }
    return is_string($value) ? $value : '';
}

function db_connection_settings(string $variable = 'DATABASE_URL'): array
{
    $url = env_value($variable);
    if ($url === '') {
        throw new RuntimeException('PostgreSQL connection variable is not configured');
    }
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['path'])
        || !in_array($parts['scheme'], ['postgres', 'postgresql'], true)) {
        throw new RuntimeException('Invalid DATABASE_URL; use a PostgreSQL URI with host, user, password and database');
    }
    $port = $parts['port'] ?? 5432;
    $dbname = ltrim($parts['path'], '/');
    if ($dbname === '' || str_contains($dbname, '/')) {
        throw new RuntimeException('Invalid database name');
    }
    // Options can be placed in the query, but SSL is mandatory. Never accept sslmode=disable.
    $query = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $query);
    if (isset($query['sslmode']) && !in_array($query['sslmode'], ['require', 'verify-ca', 'verify-full'], true)) {
        throw new RuntimeException('TLS is required for PostgreSQL');
    }
    $sslmode = $query['sslmode'] ?? 'require';
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=5',
        $parts['host'], $port, rawurldecode($dbname), $sslmode);
    return [$dsn, rawurldecode($parts['user']), rawurldecode($parts['pass'])];
}
