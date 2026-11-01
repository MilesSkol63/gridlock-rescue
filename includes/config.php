<?php
/**
 * includes/config.php  (NEW)
 * Application constants + database settings loader.
 * No credentials are stored in this file:
 *   - On Railway (and any server) the MYSQLHOST / MYSQLPORT / MYSQLUSER /
 *     MYSQLPASSWORD / MYSQLDATABASE environment variables are used.
 *   - On a local computer, copy includes/config.local.example.php to
 *     includes/config.local.php (that file is ignored by Git).
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit; // this file must never be opened directly in the browser
}

define('APP_NAME', 'Gridlock Rescue');

// Visitors must never see PHP errors. They are written to the server log instead
// (on Railway: service > Deploy Logs).
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/** Read an environment variable (works with getenv, $_ENV and $_SERVER). */
function env_value($name)
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? ($_SERVER[$name] ?? '');
    }
    return is_string($value) ? $value : '';
}

/**
 * Returns the database settings array, or null when nothing is configured.
 * Environment variables win over the local file.
 */
function load_db_settings()
{
    $host = env_value('MYSQLHOST');
    if ($host !== '') {
        $port = env_value('MYSQLPORT');
        return [
            'host'     => $host,
            'port'     => ($port !== '') ? (int) $port : 3306,
            'user'     => env_value('MYSQLUSER'),
            'password' => env_value('MYSQLPASSWORD'),
            'database' => env_value('MYSQLDATABASE'),
        ];
    }

    $local_file = __DIR__ . '/config.local.php';
    if (is_file($local_file)) {
        $settings = require $local_file;
        if (is_array($settings)) {
            return $settings;
        }
    }
    return null;
}
