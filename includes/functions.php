<?php
/**
 * includes/functions.php  (NEW)
 * Small shared helpers: escaping, redirects, flash messages, validation,
 * MySQLi query helpers and level/progress helpers.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

/* ---------------------------------------------------------------- output */

/** Escape text before printing it into HTML (prevents XSS). */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Send the browser to another page of this app and stop the script. */
function redirect($page)
{
    header('Location: ' . $page);
    exit;
}

/** Read a POST value as a plain string (arrays or missing values become ''). */
function post_string($name)
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
}

/* ---------------------------------------------------------- flash messages */

function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Prints the pending flash message (if any) once, then removes it. */
function show_flash()
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $allowed = ['success', 'danger', 'warning', 'info'];
    $type = in_array($flash['type'], $allowed, true) ? $flash['type'] : 'info';
    echo '<div class="alert alert-' . $type . '" role="alert">' . e($flash['message']) . '</div>' . "\n";
}

/* -------------------------------------------------------------- validation */
// Each validator returns an error message, or '' when the value is acceptable.

function validate_username($username)
{
    if ($username === '') {
        return 'Username is required.';
    }
    if (strlen($username) < 3 || strlen($username) > 20) {
        return 'Username must be 3 to 20 characters long.';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        return 'Use only letters, numbers and underscores.';
    }
    return '';
}

function validate_email($email)
{
    if ($email === '') {
        return 'Email is required.';
    }
    if (strlen($email) > 100) {
        return 'Email must be 100 characters or fewer.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    return '';
}

function validate_password($password)
{
    if ($password === '') {
        return 'Password is required.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (strlen($password) > 72) {
        return 'Password must be 72 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password needs at least one letter and one number.';
    }
    return '';
}

/* ------------------------------------------------------------ MySQLi helpers */
// Thin wrappers around the procedural MySQLi functions so pages stay short.
// $types is the bind_param type string, e.g. 'si' = string, integer.

function db_run($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

/** SELECT many rows -> array of associative arrays. */
function db_select_all($conn, $sql, $types = '', $params = [])
{
    $stmt = db_run($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/** SELECT one row -> associative array, or null when nothing matches. */
function db_select_one($conn, $sql, $types = '', $params = [])
{
    $rows = db_select_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/** INSERT / UPDATE / DELETE -> ['affected' => rows, 'insert_id' => id]. */
function db_execute($conn, $sql, $types = '', $params = [])
{
    $stmt = db_run($conn, $sql, $types, $params);
    $info = [
        'affected'  => mysqli_stmt_affected_rows($stmt),
        'insert_id' => mysqli_insert_id($conn),
    ];
    mysqli_stmt_close($stmt);
    return $info;
}

/* ------------------------------------------------------------ level helpers */
// Used by the dashboard now and by the game pages in Deliverable 3.

/** One level row (including the layout text) or null. */
function get_level($conn, $level_id)
{
    return db_select_one(
        $conn,
        'SELECT level_id, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves
         FROM levels WHERE level_id = ?',
        'i',
        [(int) $level_id]
    );
}

/**
 * All levels in play order with a status for one user:
 *   'completed' - the user has a progress row for it
 *   'unlocked'  - level 1, or the previous level is completed
 *   'locked'    - anything after the first unfinished level
 * Unlocking is calculated here; nothing about "unlocked" is stored.
 */
function get_levels_with_status($conn, $user_id)
{
    $levels = db_select_all(
        $conn,
        'SELECT level_id, title, hint, difficulty, grid_width, grid_height, par_moves, layout
         FROM levels ORDER BY sort_order ASC, level_id ASC'
    );
    $done_rows = db_select_all(
        $conn,
        'SELECT level_id, best_moves FROM user_progress WHERE user_id = ?',
        'i',
        [(int) $user_id]
    );
    $best = [];
    foreach ($done_rows as $row) {
        $best[(int) $row['level_id']] = (int) $row['best_moves'];
    }

    $previous_done = true; // the first level is always open
    foreach ($levels as $index => $level) {
        $id = (int) $level['level_id'];
        $is_done = isset($best[$id]);
        if ($is_done) {
            $status = 'completed';
        } elseif ($previous_done) {
            $status = 'unlocked';
        } else {
            $status = 'locked';
        }
        // number of boxes = '$' (box) + '*' (box already on a target) characters in the layout
        $levels[$index]['box_count'] = substr_count($level['layout'], '$') + substr_count($level['layout'], '*');
        unset($levels[$index]['layout']);
        $levels[$index]['number'] = $index + 1;
        $levels[$index]['status'] = $status;
        $levels[$index]['best_moves'] = $is_done ? $best[$id] : null;
        $previous_done = $is_done;
    }
    return $levels;
}

/** True when the user may play this level (used by play.php in Deliverable 3). */
function is_level_unlocked($conn, $user_id, $level_id)
{
    foreach (get_levels_with_status($conn, $user_id) as $level) {
        if ((int) $level['level_id'] === (int) $level_id) {
            return $level['status'] !== 'locked';
        }
    }
    return false;
}

/** Id of the level that follows $level_id in play order, or null for the last level. */
function get_next_level_id($conn, $level_id)
{
    $rows = db_select_all($conn, 'SELECT level_id FROM levels ORDER BY sort_order ASC, level_id ASC');
    foreach ($rows as $index => $row) {
        if ((int) $row['level_id'] === (int) $level_id) {
            return isset($rows[$index + 1]) ? (int) $rows[$index + 1]['level_id'] : null;
        }
    }
    return null;
}

/**
 * Leaderboard position of one player: ['rank', 'levels_done', 'total_moves'] or null if no level is completed.
 * Rule (same as the dashboard): more levels first, then fewer total moves; ties share a rank.
 */
function get_user_rank($conn, $user_id)
{
    $mine = db_select_one(
        $conn,
        'SELECT COUNT(*) AS levels_done, COALESCE(SUM(best_moves), 0) AS total_moves
         FROM user_progress WHERE user_id = ?',
        'i',
        [(int) $user_id]
    );
    $done = (int) $mine['levels_done'];
    $total = (int) $mine['total_moves'];
    if ($done === 0) {
        return null;
    }
    $ahead = db_select_one(
        $conn,
        'SELECT COUNT(*) AS ahead FROM (
             SELECT p.user_id, COUNT(*) AS levels_done, SUM(p.best_moves) AS total_moves
             FROM user_progress p JOIN users u ON u.user_id = p.user_id
             WHERE u.role = \'user\' GROUP BY p.user_id
         ) t
         WHERE t.levels_done > ? OR (t.levels_done = ? AND t.total_moves < ?)',
        'iii',
        [$done, $done, $total]
    );
    return ['rank' => (int) $ahead['ahead'] + 1, 'levels_done' => $done, 'total_moves' => $total];
}

/* ------------------------------------------------- account + level validation */

/**
 * Looks for a username or email that already belongs to ANOTHER account.
 * $exclude_user_id = the account being edited (0 when creating a new one).
 * Returns ['username' => message, 'email' => message] (only the keys that clash).
 */
function check_account_duplicates($conn, $username, $email, $exclude_user_id = 0)
{
    $errors = [];
    $rows = db_select_all(
        $conn,
        'SELECT username, email FROM users WHERE (username = ? OR email = ?) AND user_id <> ?',
        'ssi',
        [$username, $email, (int) $exclude_user_id]
    );
    foreach ($rows as $row) {
        if (strcasecmp($row['username'], $username) === 0) {
            $errors['username'] = 'That username is already taken.';
        }
        if (strcasecmp($row['email'], $email) === 0) {
            $errors['email'] = 'That email is already registered.';
        }
    }
    return $errors;
}

define('LEVEL_MIN_SIZE', 3);    // smallest allowed width / height
define('LEVEL_MAX_SIZE', 15);   // largest allowed width / height

/**
 * Validates a level layout typed into the level form.
 * Returns ['errors' => [...], 'layout' => cleaned text, 'width', 'height', 'boxes', 'targets'].
 * The layout is only acceptable when 'errors' is empty. Width, height and box count are
 * calculated here from the text - the browser never supplies them.
 */
function analyse_level_layout($text)
{
    $info = ['errors' => [], 'layout' => '', 'width' => 0, 'height' => 0, 'boxes' => 0, 'targets' => 0];

    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);   // Windows line endings -> \n
    $lines = explode("\n", $text);
    foreach ($lines as $i => $line) {
        $lines[$i] = rtrim($line, " \t");                        // ignore spaces at the end of a row
    }
    while (!empty($lines) && $lines[0] === '') {
        array_shift($lines);                                     // ignore empty rows before the grid
    }
    while (!empty($lines) && end($lines) === '') {
        array_pop($lines);                                       // ...and after it
    }
    if (empty($lines)) {
        $info['errors'][] = 'The layout is empty.';
        return $info;
    }

    $height = count($lines);
    $width = strlen($lines[0]);
    $allowed = '#-.$*@+';
    $players = 0;
    $boxes = 0;
    $targets = 0;
    $has_blank_row = false;
    $uneven = false;
    $bad_chars = [];
    $loose_boxes = 0;                                            // boxes that do NOT start on a target

    foreach ($lines as $line) {
        if ($line === '') {
            $has_blank_row = true;
            continue;
        }
        if (strlen($line) !== $width) {
            $uneven = true;
        }
        foreach (str_split($line) as $ch) {
            if (strpos($allowed, $ch) === false) {
                $bad_chars[$ch] = true;
                continue;
            }
            if ($ch === '@' || $ch === '+') { $players++; }
            if ($ch === '$' || $ch === '*') { $boxes++; }
            if ($ch === '$') { $loose_boxes++; }
            if ($ch === '.' || $ch === '*' || $ch === '+') { $targets++; }
        }
    }

    if ($has_blank_row) {
        $info['errors'][] = 'Remove empty rows from the middle of the layout.';
    }
    if (!empty($bad_chars)) {
        $shown = [];
        foreach (array_keys($bad_chars) as $ch) {
            $shown[] = (ctype_print((string) $ch) && $ch !== ' ') ? (string) $ch : '(other character)';
        }
        $info['errors'][] = 'Invalid characters: ' . implode(' ', $shown) . '. Allowed: # - . $ * @ +';
    }
    if ($uneven) {
        $info['errors'][] = 'All rows must have the same length.';
    }
    if ($width < LEVEL_MIN_SIZE || $height < LEVEL_MIN_SIZE) {
        $info['errors'][] = 'The grid must be at least ' . LEVEL_MIN_SIZE . 'x' . LEVEL_MIN_SIZE . '.';
    }
    if ($width > LEVEL_MAX_SIZE || $height > LEVEL_MAX_SIZE) {
        $info['errors'][] = 'The grid can be at most ' . LEVEL_MAX_SIZE . 'x' . LEVEL_MAX_SIZE . '.';
    }
    if ($players !== 1) {
        $info['errors'][] = 'The layout needs exactly one player (@ or +). Found ' . $players . '.';
    }
    if ($boxes < 1) {
        $info['errors'][] = 'The layout needs at least one box ($ or *).';
    }
    if ($boxes !== $targets) {
        $info['errors'][] = 'Boxes and targets must match: found ' . $boxes . ' boxes and ' . $targets . ' targets.';
    }
    if ($boxes >= 1 && $boxes === $targets && $loose_boxes === 0) {
        $info['errors'][] = 'Every box already starts on a target, so the level is already solved.';
    }

    $info['layout'] = implode("\n", $lines);
    $info['width'] = $width;
    $info['height'] = $height;
    $info['boxes'] = $boxes;
    $info['targets'] = $targets;
    return $info;
}

/* --------------------------------------------------------------- UI helpers */

/** Bootstrap-friendly CSS class for a difficulty badge. */
function difficulty_class($difficulty)
{
    $map = ['easy' => 'badge-easy', 'medium' => 'badge-medium', 'hard' => 'badge-hard'];
    return $map[$difficulty] ?? 'badge-easy';
}

/**
 * Prints a small read-only picture of a level layout (used on the login page).
 * Characters: # wall, - floor, . target, $ box, * box on target, @ player, + player on target.
 * The CSS classes (.cell .wall .floor .target .box .player) are the same ones the game board will use.
 */
function render_board_preview($rows, $cell_size = 36)
{
    $cols = strlen($rows[0]);
    echo '<div class="board" style="--cols:' . (int) $cols . ';--cell:' . (int) $cell_size . 'px" aria-hidden="true">' . "\n";
    foreach ($rows as $row) {
        foreach (str_split($row) as $ch) {
            $classes = ($ch === '#') ? 'cell wall' : 'cell floor';
            if (strpos('.*+', $ch) !== false) {
                $classes .= ' target';
            }
            if (strpos('$*', $ch) !== false) {
                $classes .= ' box';
            }
            if (strpos('@+', $ch) !== false) {
                $classes .= ' player';
            }
            echo '    <div class="' . $classes . '"></div>' . "\n";
        }
    }
    echo '</div>' . "\n";
}
