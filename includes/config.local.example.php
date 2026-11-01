<?php
/**
 * includes/config.local.example.php  (NEW)
 * LOCAL DEVELOPMENT ONLY.
 * 1. Copy this file to includes/config.local.php
 * 2. Put your own XAMPP / local MySQL values below.
 * config.local.php is listed in .gitignore and must never be committed or zipped.
 * It is a PHP file, so even if someone opens it in a browser the values are not shown.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'user'     => 'root',
    'password' => '',                 // XAMPP default is an empty password
    'database' => 'gridlock_rescue',
];
