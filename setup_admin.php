<?php
/**
 * setup_admin.php  (NEW)  - ONE-TIME, COMMAND-LINE-ONLY creation of the first administrator.
 *
 * Interactive:      php setup_admin.php
 * Non-interactive:  set ADMIN_USERNAME, ADMIN_EMAIL and ADMIN_PASSWORD, then run  php setup_admin.php
 *
 * It refuses to run in a browser and refuses to run when an administrator already exists.
 * No password is stored in any file: it is typed (or read from the environment), hashed with
 * password_hash() and only the hash is saved.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

/** Ask a question in the terminal. $hidden = do not echo what is typed (Linux/macOS). */
function ask($question, $hidden = false)
{
    echo $question;
    $can_hide = $hidden && DIRECTORY_SEPARATOR === '/';
    if ($can_hide) {
        shell_exec('stty -echo');
    }
    $answer = fgets(STDIN);
    if ($can_hide) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return ($answer === false) ? '' : trim($answer);
}

function stop($message, $code = 1)
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

$existing = db_select_one($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
if ((int) $existing['total'] > 0) {
    stop('An administrator account already exists. Nothing was changed.');
}

$username = env_value('ADMIN_USERNAME');
$email    = env_value('ADMIN_EMAIL');
$password = env_value('ADMIN_PASSWORD');

if ($username === '' || $email === '' || $password === '') {
    echo "Create the first Gridlock Rescue administrator" . PHP_EOL;
    $username = ask('Username (3-20 letters, numbers, _): ');
    $email    = ask('Email: ');
    $password = ask('Password (min 8, letter + number; hidden when supported): ', true);
    $confirm  = ask('Repeat password: ', true);
    if ($password !== $confirm) {
        stop('Passwords do not match. Nothing was changed.');
    }
}
$email = strtolower($email);

foreach ([validate_username($username), validate_email($email), validate_password($password)] as $problem) {
    if ($problem !== '') {
        stop('Invalid input: ' . $problem);
    }
}

$taken = db_select_one($conn, 'SELECT user_id FROM users WHERE username = ? OR email = ?', 'ss', [$username, $email]);
if ($taken !== null) {
    stop('That username or email is already used by another account.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
db_execute(
    $conn,
    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
    'sss',
    [$username, $email, $hash]
);

echo "Administrator '" . $username . "' created. You can now log in at the login page." . PHP_EOL;
if (env_value('ADMIN_PASSWORD') !== '') {
    echo "Remove the ADMIN_PASSWORD variable now (Railway: service > Variables)." . PHP_EOL;
}
