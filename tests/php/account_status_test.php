<?php
/**
 * tests/php/account_status_test.php  (NEW)  - REAL-DATABASE test of disable / enable accounts.
 *     php tests/php/account_status_test.php
 *
 * SAFETY: runs ONLY with a separate test database indicated by GRIDLOCK_TEST_DATABASE=1
 * and a DB whose current_database() ends with _test. Needs the PostgreSQL schema
 * and the demo seed in this dedicated test database. It creates temporary users
 * and cleans them up using the dedicated migration OWNER credentials.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('GRIDLOCK_TEST_DATABASE') !== '1') { fwrite(STDERR, "Set GRIDLOCK_TEST_DATABASE=1 for isolated test databases only.\n"); exit(2); }
require_once __DIR__ . '/../../includes/config.php';
// Validate BOTH database URIs before loading auth.php: auth starts a persistent
// session, which would otherwise write to the wrong database on test startup.
foreach (['DATABASE_URL', 'MIGRATION_DATABASE_URL'] as $variable) {
    $raw = env_value($variable);
    $parts = parse_url($raw);
    $targetDb = isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : '';
    if (!preg_match('/_test$/', $targetDb)) {
        fwrite(STDERR, "Refusing to start: $variable must target a dedicated *_test database.\n");
        exit(2);
    }
}
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_pg.php';
require_once __DIR__ . '/../../includes/auth.php';

/** @var PDO $conn */

[$testDsn, $testUser, $testPass] = db_connection_settings('MIGRATION_DATABASE_URL');
$conn = new PDO($testDsn, $testUser, $testPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
$database = db_select_one($conn, 'SELECT current_database() AS name')['name'];
if (!is_string($database) || !preg_match('/_test$/', $database)) {
    fwrite(STDERR, 'Refusing to run: the connected database is "' . $database . '". Use a database whose name ends with _test.' . PHP_EOL);
    exit(2);
}

$passed = 0;
$failed = 0;
function check($name, $ok, $detail = '')
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
    } else {
        $failed++;
        echo 'FAIL: ' . $name . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
    }
}
function count_of($conn, $sql, $types = '', $params = [])
{
    return (int) array_values(db_select_one($conn, $sql, $types, $params))[0];
}
function make_user($conn, $name, $role)
{
    return db_execute(
        $conn,
        'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?) RETURNING user_id',
        'ssss',
        [$name, $name . '@test.local', password_hash('Test1234x', PASSWORD_DEFAULT), $role]
    )['insert_id'];
}
function cleanup($conn)
{
    // PostgreSQL forbids deleting users with progress; cleanup children first.
    $conn->exec("DELETE FROM user_progress WHERE user_id IN
        (SELECT user_id FROM users WHERE username LIKE 'zz\\_status%' ESCAPE E'\\\\')");
    $conn->exec("DELETE FROM users WHERE username LIKE 'zz\\_status%' ESCAPE E'\\\\'");
}

cleanup($conn);
$level = db_select_one($conn, "SELECT level_id FROM levels WHERE game_type = 'sliding' ORDER BY level_id LIMIT 1");
if ($level === null) {
    fwrite(STDERR, 'Apply database/postgres_seed_sliding.sql in the dedicated test database first (no sliding levels found).' . PHP_EOL);
    exit(2);
}
$level_id = (int) $level['level_id'];

$admin1 = make_user($conn, 'zz_status_admin1', 'admin');
$admin2 = make_user($conn, 'zz_status_admin2', 'admin');
$player = make_user($conn, 'zz_status_player', 'user');
$other = make_user($conn, 'zz_status_other', 'user');
db_execute($conn, 'INSERT INTO user_progress (user_id, level_id, best_moves) VALUES (?, ?, 12)', 'ii', [$player, $level_id]);

check('new accounts are active by default (column default 1)', count_of($conn, 'SELECT is_active FROM users WHERE user_id = ?', 'i', [$player]) === 1);
$snapshot = json_encode(db_select_all($conn, 'SELECT user_id, username, email, password_hash, role FROM users WHERE user_id = ?', 'i', [$player]));
$progress_snapshot = json_encode(db_select_all($conn, 'SELECT user_id, level_id, best_moves FROM user_progress WHERE user_id = ?', 'i', [$player]));

/* disable a player */
$r = set_account_active($conn, $admin1, $player, false);
check('administrator can disable a player', $r['ok'] === true && count_of($conn, 'SELECT is_active FROM users WHERE user_id = ?', 'i', [$player]) === 0, $r['error']);
check('profile, password hash, role and id are unchanged', json_encode(db_select_all($conn, 'SELECT user_id, username, email, password_hash, role FROM users WHERE user_id = ?', 'i', [$player])) === $snapshot);
check('progress and scores are unchanged', json_encode(db_select_all($conn, 'SELECT user_id, level_id, best_moves FROM user_progress WHERE user_id = ?', 'i', [$player])) === $progress_snapshot);

/* the login query and the session guard see the status */
$row = db_select_one($conn, 'SELECT user_id, username, password_hash, role, is_active FROM users WHERE lower(username) = lower(?) OR lower(email) = lower(?) LIMIT 1', 'ss', ['zz_status_player', 'zz_status_player']);
check('login query returns is_active = 0 for the disabled player', (int) $row['is_active'] === 0 && password_verify('Test1234x', $row['password_hash']));
$_SESSION['user_id'] = $player;
check('an open session of a disabled player is rejected on the next request', get_session_account() === null && account_was_disabled() === true);
$_SESSION['user_id'] = $other;
check('another player\'s session is still valid', get_session_account() !== null && account_was_disabled() === false);
$_SESSION['user_id'] = $admin2;
check('an active administrator\'s session is valid', get_session_account() !== null && get_session_account()['role'] === 'admin');

/* enable again */
$r = set_account_active($conn, $admin1, $player, true);
$_SESSION['user_id'] = $player;
check('administrator can enable the player again; the session guard accepts them', $r['ok'] === true && get_session_account() !== null);
check('profile and progress are still intact after re-enabling', json_encode(db_select_all($conn, 'SELECT user_id, username, email, password_hash, role FROM users WHERE user_id = ?', 'i', [$player])) === $snapshot
    && json_encode(db_select_all($conn, 'SELECT user_id, level_id, best_moves FROM user_progress WHERE user_id = ?', 'i', [$player])) === $progress_snapshot);

/* administrator protections */
check('repeated enable / disable is refused', set_account_active($conn, $admin1, $player, true)['ok'] === false);
check('an administrator cannot disable their own account', set_account_active($conn, $admin1, $admin1, false)['ok'] === false && count_of($conn, 'SELECT is_active FROM users WHERE user_id = ?', 'i', [$admin1]) === 1);
check('a player cannot disable or enable anyone', set_account_active($conn, $player, $other, false)['ok'] === false && set_account_active($conn, $player, $other, true)['ok'] === false && count_of($conn, 'SELECT is_active FROM users WHERE user_id = ?', 'i', [$other]) === 1);
check('an unknown account is refused', set_account_active($conn, $admin1, 999999999, false)['ok'] === false);
$r = set_account_active($conn, $admin1, $admin2, false);
check('administrator 1 can disable administrator 2 while another active administrator remains', $r['ok'] === true, $r['error']);
check('a disabled administrator can no longer change statuses', set_account_active($conn, $admin2, $admin1, false)['ok'] === false);
check('administrator 1 cannot disable themselves (they would be the last active administrator)', set_account_active($conn, $admin1, $admin1, false)['ok'] === false);
check('administrator 1 can enable administrator 2 again', set_account_active($conn, $admin1, $admin2, true)['ok'] === true);
check('no test user or progress row was deleted by any status change', count_of($conn, "SELECT COUNT(*) FROM users WHERE username LIKE 'zz\\_status%' ESCAPE E'\\\\'") === 4 && count_of($conn, 'SELECT COUNT(*) FROM user_progress WHERE user_id = ?', 'i', [$player]) === 1);

cleanup($conn);
check('test data removed again', count_of($conn, "SELECT COUNT(*) FROM users WHERE username LIKE 'zz\\_status%' ESCAPE E'\\\\'") === 0);

echo ($failed === 0 ? 'ALL PASSED' : 'FAILURES') . ': ' . $passed . ' checks passed, ' . $failed . ' failed (database: ' . $database . ')' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
