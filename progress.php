<?php
/**
 * progress.php  (MODIFIED)  - progress overview and management, same page for both roles.
 *   regular user : own levels; reset ONE level or ALL progress (always WHERE user_id = session user)
 *   administrator: all records (optionally ?user_id=ID); delete one record or reset one player
 * Deleting progress never deletes an account. Unlocking is recalculated from the remaining rows.
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');
$my_id    = (int) $account['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $id = filter_var(post_string('id'), FILTER_VALIDATE_INT);
    $back = ($is_admin && isset($_GET['user_id'])) ? 'progress.php?user_id=' . (int) $_GET['user_id'] : 'progress.php';

    if (!$is_admin && $action === 'reset_level' && $id !== false) {
        // The user id comes from the SESSION, so players can only touch their own record.
        $result = db_execute($conn, 'DELETE FROM user_progress WHERE user_id = ? AND level_id = ?', 'ii', [$my_id, $id]);
        set_flash($result['affected'] > 0 ? 'success' : 'warning', $result['affected'] > 0 ? 'The saved score for that level was reset.' : 'There was no saved score for that level.');
    } elseif (!$is_admin && $action === 'reset_all') {
        $result = db_execute($conn, 'DELETE FROM user_progress WHERE user_id = ?', 'i', [$my_id]);
        set_flash('success', 'Your progress was reset (' . (int) $result['affected'] . ' record(s) removed). Level 1 is your starting level again.');
    } elseif ($is_admin && $action === 'delete_record' && $id !== false) {
        $result = db_execute($conn, 'DELETE FROM user_progress WHERE progress_id = ?', 'i', [$id]);
        set_flash($result['affected'] > 0 ? 'success' : 'warning', $result['affected'] > 0 ? 'The progress record was deleted.' : 'That record no longer exists.');
    } elseif ($is_admin && $action === 'reset_player' && $id !== false) {
        $result = db_execute($conn, 'DELETE FROM user_progress WHERE user_id = ?', 'i', [$id]);
        set_flash('success', (int) $result['affected'] . ' progress record(s) of that player were deleted.');
    } else {
        set_flash('danger', 'That action is not allowed.');
    }
    redirect($back);
}

$filter_player = null;
if ($is_admin) {
    $records_sql = 'SELECT p.progress_id, u.user_id, u.username, l.title, p.best_moves, p.completed_at
                    FROM user_progress p
                    JOIN users  u ON u.user_id  = p.user_id
                    JOIN levels l ON l.level_id = p.level_id';
    $filter_id = filter_var($_GET['user_id'] ?? '', FILTER_VALIDATE_INT);
    if ($filter_id !== false && $filter_id > 0) {
        $filter_player = db_select_one($conn, "SELECT user_id, username FROM users WHERE user_id = ? AND role = 'user'", 'i', [$filter_id]);
    }
    if ($filter_player !== null) {
        $records = db_select_all($conn, $records_sql . ' WHERE p.user_id = ? ORDER BY l.sort_order ASC, l.level_id ASC', 'i', [(int) $filter_player['user_id']]);
    } else {
        $records = db_select_all($conn, $records_sql . ' ORDER BY p.updated_at DESC, p.progress_id DESC LIMIT 200');
    }
    if ($filter_player !== null) {
        $confirm_action_url = 'progress.php?user_id=' . (int) $filter_player['user_id'];
    }
} else {
    $levels = get_levels_with_status($conn, $my_id);
    $dates = [];
    foreach (db_select_all($conn, 'SELECT level_id, completed_at FROM user_progress WHERE user_id = ?', 'i', [$my_id]) as $row) {
        $dates[(int) $row['level_id']] = substr($row['completed_at'], 0, 10);
    }
    $done_count = 0;
    foreach ($levels as $level) {
        if ($level['status'] === 'completed') { $done_count++; }
    }
}
$active_page = 'progress';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Progress - Gridlock Rescue</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="container page-main" id="page-content">
        <?php show_flash(); ?>

        <div class="page-header">
            <h1><?php echo $is_admin ? 'Progress Records' : 'My Progress'; ?></h1>
<?php if ($is_admin): ?>
            <span class="badge badge-role-admin">ADMIN</span>
    <?php if ($filter_player !== null): ?>
            <span class="text-muted">Player: <strong><?php echo e($filter_player['username']); ?></strong></span>
            <a class="ms-auto" href="progress.php">Show all players</a>
    <?php endif; ?>
<?php endif; ?>
        </div>

<?php if ($is_admin): ?>
        <section class="gr-card" id="admin-progress">
            <h2 class="section-title"><?php echo ($filter_player !== null) ? 'Records of this player' : 'All progress records (latest 200)'; ?></h2>
    <?php if (empty($records)): ?>
            <div class="empty-state">No progress records found.</div>
    <?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead><tr><th>Player</th><th>Level</th><th>Best Moves</th><th class="d-none d-md-table-cell">Completed</th><th>Action</th></tr></thead>
                    <tbody>
        <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?php echo e($row['username']); ?></td>
                            <td><?php echo e($row['title']); ?></td>
                            <td><?php echo (int) $row['best_moves']; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo e(substr($row['completed_at'], 0, 10)); ?></td>
                            <td>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                                        data-action="delete_record" data-id="<?php echo (int) $row['progress_id']; ?>"
                                        data-title="Delete this record?"
                                        data-message="Deletes <?php echo e($row['username']); ?>'s saved score for &quot;<?php echo e($row['title']); ?>&quot;. The account is not deleted."
                                        data-button="Delete record">Delete</button>
                            </td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php endif; ?>
    <?php if ($filter_player !== null && !empty($records)): ?>
            <div class="mt-3">
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                        data-action="reset_player" data-id="<?php echo (int) $filter_player['user_id']; ?>"
                        data-title="Reset all progress of <?php echo e($filter_player['username']); ?>?"
                        data-message="Deletes every saved score of this player. The account is not deleted and the player restarts at Level 1."
                        data-button="Reset progress">Reset this player's progress</button>
            </div>
    <?php endif; ?>
        </section>
<?php else: ?>
        <section class="gr-card" id="user-progress">
            <h2 class="section-title">Levels (<?php echo $done_count; ?> of <?php echo count($levels); ?> completed)</h2>
    <?php if (empty($levels)): ?>
            <div class="empty-state">No levels are available yet.</div>
    <?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead><tr><th>Level</th><th>Status</th><th>Best Moves</th><th class="d-none d-md-table-cell">Completed</th><th>Action</th></tr></thead>
                    <tbody>
        <?php foreach ($levels as $level): ?>
                        <tr>
                            <td><?php echo (int) $level['number']; ?> &ndash; <?php echo e($level['title']); ?></td>
                            <td>
            <?php if ($level['status'] === 'completed'): ?>
                                <span class="badge badge-role-player">Completed</span>
            <?php elseif ($level['status'] === 'unlocked'): ?>
                                <span class="badge badge-stage">Unlocked</span>
            <?php else: ?>
                                <span class="badge badge-muted">Locked</span>
            <?php endif; ?>
                            </td>
                            <td><?php echo ($level['best_moves'] !== null) ? (int) $level['best_moves'] : '&mdash;'; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo isset($dates[(int) $level['level_id']]) ? e($dates[(int) $level['level_id']]) : '&mdash;'; ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
            <?php if ($level['status'] !== 'locked'): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="play.php?id=<?php echo (int) $level['level_id']; ?>"><?php echo ($level['status'] === 'completed') ? 'Play Again' : 'Play'; ?></a>
            <?php endif; ?>
            <?php if ($level['status'] === 'completed'): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                                            data-action="reset_level" data-id="<?php echo (int) $level['level_id']; ?>"
                                            data-title="Reset this level?"
                                            data-message="Deletes your saved best score for &quot;<?php echo e($level['title']); ?>&quot;. Levels after it may become locked again."
                                            data-button="Reset level">Reset</button>
            <?php elseif ($level['status'] === 'locked'): ?>
                                    <span class="text-muted">&mdash;</span>
            <?php endif; ?>
                                </div>
                            </td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php endif; ?>
    <?php if ($done_count > 0): ?>
            <div class="mt-3">
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                        data-action="reset_all" data-id="0"
                        data-title="Reset all your progress?"
                        data-message="Deletes every saved score and returns you to Level 1. Your account is not deleted. This cannot be undone."
                        data-button="Reset all progress">Reset All My Progress</button>
            </div>
    <?php endif; ?>
        </section>
<?php endif; ?>
    </main>

    <?php include 'includes/confirm_modal.php'; ?>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
