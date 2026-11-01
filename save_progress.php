<?php
/**
 * save_progress.php  (NEW)  - JSON endpoint called by assets/js/game.js when a level is won.
 * Request : POST JSON {"level_id": int, "moves": int}  + header X-CSRF-Token
 * Response: JSON {"success", "message", "is_new_best", "best_moves", "next_level_id"}
 *
 * The user is taken ONLY from the PHP session. The browser never sends a user id or a role.
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Send a JSON reply and stop. */
function json_reply($status, $success, $message, $extra = [])
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// 1. Method, session, CSRF, role -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_reply(405, false, 'Method not allowed.');
}

$account = get_session_account();
if ($account === null) {
    json_reply(401, false, 'Your session has expired. Please log in again.');
}
if (!csrf_header_is_valid()) {
    json_reply(403, false, 'Security check failed. Reload the page and try again.');
}
if ($account['role'] !== 'user') {
    json_reply(403, false, 'Administrators cannot save progress.');
}
$user_id = (int) $account['user_id'];

// 2. Read and validate the JSON body -------------------------------------------------------
$raw = file_get_contents('php://input', false, null, 0, 1024);
$body = json_decode((string) $raw, true);
if (!is_array($body) || !isset($body['level_id'], $body['moves'])
    || !is_int($body['level_id']) || !is_int($body['moves'])) {
    json_reply(400, false, 'Invalid request.');
}
$level_id = $body['level_id'];
$moves = $body['moves'];

// 3. Check the level, the unlock rule and the move count on the server ---------------------
$level = get_level($conn, $level_id);
if ($level === null) {
    json_reply(404, false, 'That level does not exist.');
}
if (!is_level_unlocked($conn, $user_id, $level_id)) {
    json_reply(403, false, 'That level is locked.');
}
// A box that does not start on a target needs at least one push, so fewer moves than that is impossible.
$minimum_moves = max(1, substr_count($level['layout'], '$'));
if ($moves < $minimum_moves || $moves > 9999) {
    json_reply(422, false, 'That move count is not valid for this level.');
}

// 4. Save: create the first record, or improve the best score ------------------------------
$is_new_best = false;
$existing = db_select_one(
    $conn,
    'SELECT best_moves FROM user_progress WHERE user_id = ? AND level_id = ?',
    'ii',
    [$user_id, $level_id]
);

if ($existing === null) {
    try {
        db_execute(
            $conn,
            'INSERT INTO user_progress (user_id, level_id, best_moves) VALUES (?, ?, ?)',
            'iii',
            [$user_id, $level_id, $moves]
        );
        $is_new_best = true;
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() !== 1062) {          // 1062 = the row was created a moment ago (double request)
            error_log('Gridlock Rescue: save_progress insert failed: ' . $ex->getMessage());
            json_reply(500, false, 'Could not save your progress. Please try again.');
        }
        // Another request won the race: continue below and treat it as an existing record.
        $existing = ['best_moves' => PHP_INT_MAX];
    }
}

if ($existing !== null) {
    // The WHERE best_moves > ? makes this safe: a worse or equal score never overwrites the best one.
    $result = db_execute(
        $conn,
        'UPDATE user_progress SET best_moves = ? WHERE user_id = ? AND level_id = ? AND best_moves > ?',
        'iiii',
        [$moves, $user_id, $level_id, $moves]
    );
    $is_new_best = ($result['affected'] > 0);
}

// 5. Reply with the stored best score and the next level -----------------------------------
$saved = db_select_one(
    $conn,
    'SELECT best_moves FROM user_progress WHERE user_id = ? AND level_id = ?',
    'ii',
    [$user_id, $level_id]
);
$best_moves = (int) $saved['best_moves'];
$next_level_id = get_next_level_id($conn, $level_id);

$message = $is_new_best ? 'New best! Progress saved.' : 'Progress saved. Your best is ' . $best_moves . ' moves.';
if ($next_level_id !== null) {
    $message .= ' The next level is unlocked.';
}

json_reply(200, true, $message, [
    'is_new_best'   => $is_new_best,
    'best_moves'    => $best_moves,
    'next_level_id' => $next_level_id,
]);
