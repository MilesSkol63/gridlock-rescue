<?php
/**
 * save_progress.php - JSON endpoint called by assets/js/game.js when a level is won.
 * Request: POST JSON {"level_id": int, "layout_hash": hex, "moves": [[vehicle, position], ...]}
 *          + header X-CSRF-Token
 * Response: JSON {"success", "message", "is_new_best", "best_moves", "next_level_id"}
 *
 * The user is taken ONLY from the PHP session. The browser never sends a user id or a role.
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Send a JSON reply and stop the script. */
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
    if (account_was_disabled()) {
        json_reply(403, false, 'Your account has been disabled. Please contact an administrator.');
    }
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
// The layout hash binds the move list to the exact board shown when the player started.
$raw = file_get_contents('php://input', false, null, 0, 20000);
$body = json_decode((string) $raw, true);
if (!is_array($body) || !isset($body['level_id'], $body['layout_hash'], $body['moves'])
    || !is_int($body['level_id']) || !is_string($body['layout_hash'])
    || !preg_match('/\A[a-f0-9]{64}\z/D', $body['layout_hash']) || !is_array($body['moves'])) {
    json_reply(400, false, 'Invalid request. Reload the level and try again.');
}
$level_id = $body['level_id'];
$submitted_layout_hash = $body['layout_hash'];
$slides = array_values($body['moves']);
if (count($slides) < 1 || count($slides) > 500) {
    json_reply(422, false, 'That move list is not valid for this level.');
}

// 3. Serialize completion against admin edits, then verify the exact board ----------------
// Both this endpoint and the admin level editor lock the level row first. This prevents a
// completion based on an old board from being saved while that board is being replaced.
try {
    mysqli_begin_transaction($conn);

    $level = db_select_one(
        $conn,
        "SELECT level_id, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves
         FROM levels WHERE level_id = ? AND game_type = 'sliding' FOR UPDATE",
        'i',
        [$level_id]
    );

    if ($level === null) {
        mysqli_rollback($conn);
        json_reply(404, false, 'That level does not exist.');
    }

    $level_rows = sliding_rows_from_text($level['layout']);
    $current_layout_hash = sliding_layout_fingerprint($level['layout']);
    if (!hash_equals($current_layout_hash, $submitted_layout_hash)) {
        mysqli_rollback($conn);
        json_reply(409, false, 'This level was changed after you opened it. Reload the level before playing again.');
    }

    if (!is_level_unlocked($conn, $user_id, $level_id)) {
        mysqli_rollback($conn);
        json_reply(403, false, 'That level is locked.');
    }

    // Every slide is checked with the same rules as the browser. The score is the number
    // of slides counted here; a number sent by the browser is never accepted.
    $replay = sliding_replay($level_rows, $slides);
    if (!$replay['ok']) {
        mysqli_rollback($conn);
        json_reply(422, false, 'The submitted game is not a valid solution of this level.');
    }
    $moves = (int) $replay['moves'];

    // The level row lock serializes submissions for this level; lock the player's row too
    // before deciding whether to insert a first result or improve an existing best score.
    $existing = db_select_one(
        $conn,
        'SELECT best_moves FROM user_progress WHERE user_id = ? AND level_id = ? FOR UPDATE',
        'ii',
        [$user_id, $level_id]
    );

    $is_new_best = false;
    if ($existing === null) {
        db_execute(
            $conn,
            'INSERT INTO user_progress (user_id, level_id, best_moves) VALUES (?, ?, ?)',
            'iii',
            [$user_id, $level_id, $moves]
        );
        $is_new_best = true;
    } else {
        // A worse or equal score never overwrites the player's best result.
        $result = db_execute(
            $conn,
            'UPDATE user_progress SET best_moves = ? WHERE user_id = ? AND level_id = ? AND best_moves > ?',
            'iiii',
            [$moves, $user_id, $level_id, $moves]
        );
        $is_new_best = ($result['affected'] > 0);
    }

    $saved = db_select_one(
        $conn,
        'SELECT best_moves FROM user_progress WHERE user_id = ? AND level_id = ?',
        'ii',
        [$user_id, $level_id]
    );
    $best_moves = (int) $saved['best_moves'];
    $next_level_id = get_next_level_id($conn, $level_id);

    mysqli_commit($conn);
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($conn);
    error_log('Gridlock Rescue: save_progress failed: ' . $ex->getMessage());
    json_reply(500, false, 'Could not save your progress. Please try again.');
}

// 4. Reply only after the transaction has committed ---------------------------------------
$message = $is_new_best ? 'New best! Progress saved.' : 'Progress saved. Your best is ' . $best_moves . ' moves.';
if ($next_level_id !== null) {
    $message .= ' The next level is unlocked.';
}

json_reply(200, true, $message, [
    'is_new_best'   => $is_new_best,
    'best_moves'    => $best_moves,
    'next_level_id' => $next_level_id,
]);
