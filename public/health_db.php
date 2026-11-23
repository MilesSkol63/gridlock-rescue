<?php
/** Database readiness without credential or SQL error disclosure. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    require_once __DIR__ . '/../includes/config.php';
    [$dsn, $user, $password] = db_connection_settings();
    $conn = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
    $sql = "SELECT count(*) = 4 AS ready FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name IN ('users','levels','user_progress','app_sessions')";
    $ready = $conn->query($sql)->fetchColumn();
    if ($ready === true || $ready === 't' || $ready === '1' || $ready === 1) {
        echo json_encode(['status' => 'ok', 'component' => 'database']);
    } else {
        http_response_code(503);
        echo json_encode(['status' => 'unavailable', 'component' => 'database']);
    }
} catch (Throwable $ex) {
    error_log('Gridlock Rescue readiness failure (details omitted)');
    http_response_code(503);
    echo json_encode(['status' => 'unavailable', 'component' => 'database']);
}
