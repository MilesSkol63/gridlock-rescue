<?php
/**
 * includes/functions.php  (NEW)
 * Small shared helpers: escaping, redirects, flash messages, validation,
 * PDO PostgreSQL query helpers and level/progress helpers.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/sliding.php';   // PHP version of the sliding-block rules (validation + replay)

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

/* ------------------------------------------------------------ PDO helpers */
// Original wrapper signatures retained to keep application behavior stable.
// Types: i (integer), s (string), b (PostgreSQL boolean). Emulated parameters
// are REQUIRED for the Supabase shared TRANSACTION pooler: never concatenate input.
function db_run(PDO $conn, string $sql, string $types = '', array $params = []): PDOStatement
{
    if (strlen($types) !== count($params)) {
        throw new InvalidArgumentException('Parameter type count mismatch');
    }
    $stmt = $conn->prepare($sql);
    foreach ($params as $index => $value) {
        $type = $types[$index] ?? 's';
        if ($value === null) {
            $pdoType = PDO::PARAM_NULL;
        } elseif ($type === 'i') {
            $pdoType = PDO::PARAM_INT;
            $value = (int) $value;
        } elseif ($type === 'b') {
            // Explicit PostgreSQL boolean text + cast avoids PDO/PgSQL bool
            // emulation differences (e.g. unquoted 0/1 vs boolean literals).
            $pdoType = PDO::PARAM_STR;
            $value = $value ? 'true' : 'false';
        } elseif ($type === 's') {
            $pdoType = PDO::PARAM_STR;
        } else {
            throw new InvalidArgumentException('Unexpected bind type');
        }
        $stmt->bindValue($index + 1, $value, $pdoType);
    }
    $stmt->execute();
    return $stmt;
}

/** PDO_pgsql can return booleans as true/false, t/f, or 1/0. Normalize explicitly. */
function pg_bool($value): bool
{
    if (is_bool($value)) return $value;
    if ($value === 1 || $value === '1' || $value === 't' || $value === 'true') return true;
    if ($value === 0 || $value === '0' || $value === 'f' || $value === 'false') return false;
    throw new UnexpectedValueException('Invalid PostgreSQL boolean value');
}

function db_select_all(PDO $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($conn, $sql, $types, $params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    foreach ($rows as &$row) {
        // Preserve the existing view and controller expectations for account status.
        if (array_key_exists('is_active', $row)) {
            $row['is_active'] = pg_bool($row['is_active']) ? 1 : 0;
        }
    }
    unset($row);
    return $rows;
}

function db_select_one(PDO $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_select_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/** PostgreSQL RETURNING supplies inserted IDs without MySQL's last_insert_id. */
function db_execute(PDO $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($conn, $sql, $types, $params);
    $id = null;
    if (preg_match('/\bRETURNING\s+([a-z_]+)\b/i', $sql)) {
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $id = $row === false ? null : (int) $row[0];
    }
    $affected = $stmt->rowCount();
    $stmt->closeCursor();
    return ['affected' => $affected, 'insert_id' => $id];
}

function db_rollback_if_active(PDO $conn): void
{
    if ($conn->inTransaction()) $conn->rollBack();
}

function is_unique_violation(PDOException $ex): bool
{
    return $ex->getCode() === '23505';
}

/**
 * Whether editing a sliding-block level must be confirmed as a progress reset.
 * A metadata-only edit never requires clearing progress, and a changed board
 * with no saved records has nothing to clear.
 */
function sliding_layout_edit_requires_progress_confirmation($layout_changed, $progress_count, $confirmed)
{
    return (bool) $layout_changed && (int) $progress_count > 0 && !(bool) $confirmed;
}

/* ------------------------------------------------------------ level helpers */
// Used by the dashboard now and by the game pages in Deliverable 3.

/** One sliding-block level (including the layout text) or null. Archived Sokoban levels are never returned. */
function get_level($conn, $level_id)
{
    return db_select_one(
        $conn,
        'SELECT level_id, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves
         FROM levels WHERE level_id = ? AND game_type = \'sliding\'',
        'i',
        [(int) $level_id]
    );
}

/**
 * Disables or enables an account (administrators use this instead of deleting users).
 * Nothing is ever deleted: profile, password hash, role, progress and scores stay exactly as they are.
 *
 * All checks run INSIDE one transaction while the administrator rows are locked, so two administrators can never
 * disable each other (or the last active administrator) at the same moment.
 * Rules: the actor must be an active administrator; you cannot disable your own account; an administrator can only be
 * disabled while another ACTIVE administrator remains.
 * Returns ['ok' => bool, 'error' => string, 'message' => string].
 */
function set_account_active($conn, $actor_id, $target_id, $make_active)
{
    $actor_id = (int) $actor_id;
    $target_id = (int) $target_id;
    $result = ['ok' => false, 'error' => '', 'message' => ''];

    try {
        $conn->beginTransaction();

        // lock all administrator rows in a fixed order, then the target row
        $admins = db_select_all($conn, 'SELECT user_id, is_active FROM users WHERE role = \'admin\' ORDER BY user_id FOR UPDATE');
        $target = db_select_one($conn, 'SELECT user_id, username, role, is_active FROM users WHERE user_id = ? FOR UPDATE', 'i', [$target_id]);

        $actor_is_active_admin = false;
        $other_active_admins = 0;
        foreach ($admins as $admin) {
            if ((int) $admin['user_id'] === $actor_id && (int) $admin['is_active'] === 1) {
                $actor_is_active_admin = true;
            }
            if ((int) $admin['user_id'] !== $target_id && (int) $admin['is_active'] === 1) {
                $other_active_admins++;
            }
        }

        if (!$actor_is_active_admin) {
            $result['error'] = 'Only an active administrator can change account status.';
        } elseif ($target === null) {
            $result['error'] = 'That account no longer exists.';
        } elseif ($make_active && (int) $target['is_active'] === 1) {
            $result['error'] = 'That account is already active.';
        } elseif (!$make_active && (int) $target['is_active'] === 0) {
            $result['error'] = 'That account is already disabled.';
        } elseif (!$make_active && $target_id === $actor_id) {
            $result['error'] = 'You cannot disable your own account.';
        } elseif (!$make_active && $target['role'] === 'admin' && $other_active_admins < 1) {
            $result['error'] = 'The last active administrator cannot be disabled.';
        }

        if ($result['error'] !== '') {
            db_rollback_if_active($conn);
            return $result;
        }

        db_execute($conn, 'UPDATE users SET is_active = ?::boolean WHERE user_id = ?', 'bi', [$make_active, $target_id]);
        $conn->commit();

        $result['ok'] = true;
        $result['message'] = $make_active
            ? 'Account "' . $target['username'] . '" was enabled. The player can log in again and keeps all saved progress.'
            : 'Account "' . $target['username'] . '" was disabled. It can no longer log in or use the site, and all data and progress are preserved.';
        return $result;
    } catch (PDOException $ex) {
        db_rollback_if_active($conn);
        error_log('Gridlock Rescue: set_account_active failed: ' . ('SQLSTATE ' . $ex->getCode()));
        $result['ok'] = false;
        $result['error'] = 'The account status could not be changed. Please try again.';
        return $result;
    }
}

/** Number of different vehicles in a layout text (the rescue vehicle R included). */
function sliding_vehicle_count($layout)
{
    return count(array_unique(array_filter(str_split(preg_replace('/[^A-Z]/', '', (string) $layout)))));
}

/** How many playable (sliding-block) levels exist. */
function count_sliding_levels($conn)
{
    return (int) db_select_one($conn, 'SELECT COUNT(*) AS total FROM levels WHERE game_type = \'sliding\'')['total'];
}

/**
 * All sliding-block levels in play order with a status for one user:
 *   'completed' - the user has a progress row for it
 *   'unlocked'  - level 1, or the previous level is completed
 *   'locked'    - anything after the first unfinished level
 * Unlocking is calculated here; nothing about "unlocked" is stored.
 * Archived Sokoban levels (game_type 'sokoban') and their scores are ignored everywhere.
 */
function get_levels_with_status($conn, $user_id)
{
    $levels = db_select_all(
        $conn,
        'SELECT level_id, title, hint, difficulty, grid_width, grid_height, par_moves, layout
         FROM levels WHERE game_type = \'sliding\' ORDER BY sort_order ASC, level_id ASC'
    );
    $done_rows = db_select_all(
        $conn,
        'SELECT p.level_id, p.best_moves
         FROM user_progress p JOIN levels l ON l.level_id = p.level_id
         WHERE p.user_id = ? AND l.game_type = \'sliding\'',
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
        $levels[$index]['vehicle_count'] = sliding_vehicle_count($level['layout']);
        unset($levels[$index]['layout']);
        $levels[$index]['number'] = $index + 1;
        $levels[$index]['status'] = $status;
        $levels[$index]['best_moves'] = $is_done ? $best[$id] : null;
        $previous_done = $is_done;
    }
    return $levels;
}

/** True when the user may play this level (used by play.php and save_progress.php). */
function is_level_unlocked($conn, $user_id, $level_id)
{
    foreach (get_levels_with_status($conn, $user_id) as $level) {
        if ((int) $level['level_id'] === (int) $level_id) {
            return $level['status'] !== 'locked';
        }
    }
    return false;
}

/** Id of the sliding-block level that follows $level_id in play order, or null for the last level. */
function get_next_level_id($conn, $level_id)
{
    $rows = db_select_all($conn, 'SELECT level_id FROM levels WHERE game_type = \'sliding\' ORDER BY sort_order ASC, level_id ASC');
    foreach ($rows as $index => $row) {
        if ((int) $row['level_id'] === (int) $level_id) {
            return isset($rows[$index + 1]) ? (int) $rows[$index + 1]['level_id'] : null;
        }
    }
    return null;
}

/**
 * Deletes a player's saved SLIDING-BLOCK scores (the account and archived Sokoban records are kept).
 * Returns the number of deleted rows.
 */
function delete_sliding_progress($conn, $user_id, $level_id = null)
{
    $sql = 'DELETE FROM user_progress WHERE user_id = ?
            AND level_id IN (SELECT level_id FROM levels WHERE game_type = \'sliding\')';
    $types = 'i';
    $params = [(int) $user_id];
    if ($level_id !== null) {
        $sql .= ' AND level_id = ?';
        $types .= 'i';
        $params[] = (int) $level_id;
    }
    $result = db_execute($conn, $sql, $types, $params);
    return (int) $result['affected'];
}

/**
 * Leaderboard position of one player: ['rank', 'levels_done', 'total_moves'] or null if no level is completed.
 * Only sliding-block scores count. Rule: more levels first, then fewer total moves; ties share a rank.
 */
function get_user_rank($conn, $user_id)
{
    $mine = db_select_one(
        $conn,
        'SELECT COUNT(*) AS levels_done, COALESCE(SUM(p.best_moves), 0) AS total_moves
         FROM user_progress p JOIN levels l ON l.level_id = p.level_id
         WHERE p.user_id = ? AND l.game_type = \'sliding\'',
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
             FROM user_progress p
             JOIN users  u ON u.user_id  = p.user_id
             JOIN levels l ON l.level_id = p.level_id
             WHERE u.role = \'user\' AND l.game_type = \'sliding\' GROUP BY p.user_id
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
        'SELECT username, email FROM users WHERE (lower(username) = lower(?) OR lower(email) = lower(?)) AND user_id <> ?',
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

/* --------------------------------------------------------------- UI helpers */

/** Bootstrap-friendly CSS class for a difficulty badge. */
function difficulty_class($difficulty)
{
    $map = ['easy' => 'badge-easy', 'medium' => 'badge-medium', 'hard' => 'badge-hard', 'expert' => 'badge-expert'];
    return $map[$difficulty] ?? 'badge-easy';
}

/**
 * Prints a small decorative traffic-jam picture (login page brand panel). Pure CSS, no scripts.
 * Vehicles are placed on a 6 x 3 grid with grid-column / grid-row.
 */
function render_traffic_preview()
{
    $vehicles = [
        // [css class, column start, column span, row start, row span]
        ['rescue h', 1, 2, 2, 1],
        ['car-a v', 4, 1, 1, 3],
        ['car-b h', 5, 2, 1, 1],
        ['car-c v', 3, 1, 1, 2],
        ['car-d h', 1, 2, 3, 1],
        ['car-e h', 5, 2, 3, 1],
    ];
    echo '<div class="sl-mini" aria-hidden="true">' . "\n";
    foreach ($vehicles as $v) {
        echo '    <span class="sl-mini-vehicle ' . $v[0] . '" style="grid-column:' . $v[1] . ' / span ' . $v[2]
            . ';grid-row:' . $v[3] . ' / span ' . $v[4] . '"></span>' . "\n";
    }
    echo '    <span class="sl-mini-exit">EXIT &#10148;</span>' . "\n";
    echo '</div>' . "\n";
}
