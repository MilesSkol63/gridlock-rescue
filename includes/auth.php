<?php
/**
 * includes/auth.php  (NEW)
 * Loads config + database + helpers, starts the PHP session safely and provides
 * the login guards and CSRF helpers. Every page begins with:
 *     require_once 'includes/auth.php';
 * Session variables used everywhere:
 *     $_SESSION['user_id'], ['username'], ['role'] ('user' | 'admin'), ['csrf_token'], ['flash']
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/** True when the visitor reached us over HTTPS (Railway's proxy sends X-Forwarded-Proto). */
function request_is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Start the session with safe cookie settings. */
function start_secure_session()
{
    ini_set('session.use_strict_mode', '1');   // refuse session IDs the server did not create
    ini_set('session.use_only_cookies', '1');
    session_name('GRESCUE_SID');
    session_set_cookie_params([
        'lifetime' => 0,                       // until the browser is closed
        'path'     => '/',
        'secure'   => request_is_https(),      // Secure cookie when HTTPS is available
        'httponly' => true,                    // JavaScript cannot read the cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (session_status() === PHP_SESSION_NONE) {
    start_secure_session();
}

// Basic protective headers for every page.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ------------------------------------------------------------------ roles */

function current_role()
{
    return $_SESSION['role'] ?? '';
}

function is_admin()
{
    return current_role() === 'admin';
}

/* ----------------------------------------------------------- session control */

/** Store the account in the session after a successful login. */
function login_user($account)
{
    session_regenerate_id(true);               // new session ID after authentication
    unset($_SESSION['csrf_token']);            // new CSRF token for the new session
    $_SESSION['user_id']  = (int) $account['user_id'];
    $_SESSION['username'] = $account['username'];
    $_SESSION['role']     = $account['role'];
}

/** Completely end the current session (data, cookie and server-side file). */
function destroy_session()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/** Start a new empty session after destroy_session() (needed to show a flash message). */
function start_fresh_session()
{
    start_secure_session();
}

/** Sends logged-in visitors away from login/register to the dashboard. */
function redirect_if_logged_in()
{
    if (!empty($_SESSION['user_id'])) {
        redirect('dashboard.php');
    }
}

/**
 * Looks up the logged-in account WITHOUT redirecting.
 * Returns ['user_id', 'username', 'role'] or null (no session / account deleted / bad role).
 * The role and username in the session are refreshed from the database.
 */
function get_session_account()
{
    global $conn;

    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $account = db_select_one(
        $conn,
        'SELECT user_id, username, role FROM users WHERE user_id = ?',
        'i',
        [(int) $_SESSION['user_id']]
    );
    if ($account === null || !in_array($account['role'], ['user', 'admin'], true)) {
        return null;
    }
    $_SESSION['username'] = $account['username'];
    $_SESSION['role']     = $account['role'];
    return $account;
}

/**
 * Guard for every protected page. It checks on the SERVER that:
 *   1. a session exists, 2. the account still exists in the database,
 *   3. the role stored in the database is valid.
 * Returns the account row: ['user_id', 'username', 'role'].
 */
function require_login()
{
    // Protected pages must not be kept in the browser cache (back button after logout).
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (empty($_SESSION['user_id'])) {
        set_flash('warning', 'Please log in to continue.');
        redirect('index.php');
    }

    $account = get_session_account();
    if ($account === null) {
        destroy_session();
        start_fresh_session();
        set_flash('warning', 'Your session is no longer valid. Please log in again.');
        redirect('index.php');
    }
    return $account;
}

/** Guard for administrator-only pages and actions. */
function require_admin()
{
    $account = require_login();
    if ($account['role'] !== 'admin') {
        set_flash('danger', 'Access denied: this page is for administrators only.');
        redirect('dashboard.php');
    }
    return $account;
}

/** Guard for regular-user-only pages and actions (admins have no personal progress). */
function require_user()
{
    $account = require_login();
    if ($account['role'] !== 'user') {
        set_flash('danger', 'This feature is for players only.');
        redirect('dashboard.php');
    }
    return $account;
}

/* -------------------------------------------------------------------- CSRF */

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True when the submitted token matches the one stored in the session. */
function csrf_is_valid()
{
    $sent = post_string('csrf_token');
    return $sent !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/** CSRF check for fetch() requests: the token travels in the X-CSRF-Token header. */
function csrf_header_is_valid()
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/** For action handlers: stop with a redirect when the token is missing or wrong. */
function verify_csrf()
{
    if (!csrf_is_valid()) {
        set_flash('danger', 'Your form expired or was invalid. Please try again.');
        redirect(empty($_SESSION['user_id']) ? 'index.php' : 'dashboard.php');
    }
}
