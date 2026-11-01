<?php
/**
 * includes/db.php  (NEW)
 * Creates the ONE MySQLi connection ($conn) used by every page.
 * Procedural MySQLi style is used on purpose (instructor requirement).
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/config.php';

// Make MySQLi throw exceptions instead of printing warnings.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Shows a generic error page (no passwords, hosts or SQL) and stops. */
function show_fatal_error($detail_for_log)
{
    error_log('Gridlock Rescue: ' . $detail_for_log);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database error: " . $detail_for_log . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service unavailable - Gridlock Rescue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5 text-center">
        <h1 class="h3">Gridlock Rescue is temporarily unavailable</h1>
        <p class="text-muted">We could not reach the database. Please try again in a moment.</p>
    </main>
</body>
</html>
    <?php
    exit;
}

$db_settings = load_db_settings();
if ($db_settings === null || empty($db_settings['host']) || empty($db_settings['database'])) {
    show_fatal_error('database is not configured (set MYSQL* variables or includes/config.local.php)');
}

try {
    $conn = mysqli_connect(
        $db_settings['host'],
        $db_settings['user'],
        $db_settings['password'],
        $db_settings['database'],
        (int) $db_settings['port']
    );
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $ex) {
    show_fatal_error('connection failed: ' . $ex->getMessage());
}

unset($db_settings); // do not keep the settings around in memory
