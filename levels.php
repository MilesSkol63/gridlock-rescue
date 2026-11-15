<?php
/**
 * levels.php  (MODIFIED)  - level selection for both roles (same page).
 *   regular user : completed / unlocked / locked cards
 *   administrator: Test Play, Edit and Delete on every level, "+ New Level" button
 * Delete is a POST action (CSRF + confirmation dialog); user_progress rows go with the level (ON DELETE CASCADE).
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();                       // regular users can never run level operations
    verify_csrf();

    if (post_string('action') === 'delete_level') {
        $level_id = filter_var(post_string('id'), FILTER_VALIDATE_INT);
        if ($level_id === false || $level_id < 1) {
            set_flash('danger', 'Invalid level.');
            redirect('levels.php');
        }
        try {
            mysqli_begin_transaction($conn);
            $target = db_select_one($conn, 'SELECT level_id, title FROM levels WHERE level_id = ? AND game_type = \'sliding\' FOR UPDATE', 'i', [$level_id]);
            if ($target === null) {
                mysqli_rollback($conn);
                set_flash('warning', 'That level no longer exists.');
            } else {
                $count = db_select_one($conn, 'SELECT COUNT(*) AS total FROM user_progress WHERE level_id = ?', 'i', [$level_id]);
                db_execute($conn, 'DELETE FROM levels WHERE level_id = ? AND game_type = \'sliding\'', 'i', [$level_id]);
                mysqli_commit($conn);
                set_flash('success', 'Level "' . $target['title'] . '" and ' . (int) $count['total'] . ' saved score(s) were deleted.');
            }
        } catch (mysqli_sql_exception $ex) {
            mysqli_rollback($conn);
            error_log('Gridlock Rescue: delete_level failed: ' . $ex->getMessage());
            set_flash('danger', 'The level could not be deleted. Please try again.');
        }
    } else {
        set_flash('danger', 'Unknown action.');
    }
    redirect('levels.php');
}

$levels = get_levels_with_status($conn, (int) $account['user_id']);

// Admin only: how many saved scores each level has (shown in the delete warning)
$score_counts = [];
if ($is_admin) {
    foreach (db_select_all($conn, 'SELECT level_id, COUNT(*) AS total FROM user_progress GROUP BY level_id') as $row) {
        $score_counts[(int) $row['level_id']] = (int) $row['total'];
    }
}
$legacy_levels = 0;
if ($is_admin) {
    $legacy_levels = (int) db_select_one($conn, 'SELECT COUNT(*) AS total FROM levels WHERE game_type = \'sokoban\'')['total'];
}
$active_page = 'levels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Levels - Gridlock Rescue</title>
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
            <h1>Levels</h1>
<?php if ($is_admin): ?>
            <span class="badge badge-role-admin">ADMIN</span>
            <a class="btn btn-primary ms-auto" href="level_form.php" id="new-level-button">+ New Level</a>
<?php endif; ?>
        </div>

<?php if ($is_admin): ?>
        <p class="text-muted">Test Play opens any level without saving progress. Deleting a level also deletes the saved scores of every player for that level.</p>
    <?php if ($legacy_levels > 0): ?>
        <p class="text-muted small">Archive: <?php echo $legacy_levels; ?> old Sokoban level(s) and their scores are kept in the database but are no longer shown, played or ranked.</p>
    <?php endif; ?>
<?php endif; ?>

<?php if (empty($levels)): ?>
        <div class="empty-state">No levels are available yet.<?php echo $is_admin ? ' Use "+ New Level" to create one.' : ''; ?></div>
<?php else: ?>
        <div class="row g-4" id="level-list">
    <?php foreach ($levels as $level): ?>
        <?php $status = $is_admin ? 'preview' : $level['status']; ?>
            <div class="col-md-6 col-lg-4">
                <article class="level-card level-<?php echo e($status); ?>" id="level-<?php echo (int) $level['level_id']; ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="level-number">Level <?php echo (int) $level['number']; ?></span>
        <?php if ($status === 'completed'): ?>
                        <span class="badge badge-role-player">COMPLETED</span>
        <?php elseif ($status === 'locked'): ?>
                        <span class="badge badge-muted">LOCKED</span>
        <?php elseif ($status === 'unlocked'): ?>
                        <span class="badge badge-stage">UNLOCKED</span>
        <?php endif; ?>
                    </div>
                    <h2 class="level-name"><?php echo e($level['title']); ?></h2>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge <?php echo e(difficulty_class($level['difficulty'])); ?>"><?php echo e(ucfirst($level['difficulty'])); ?></span>
                        <span class="text-muted small"><?php echo (int) $level['vehicle_count']; ?> vehicles</span>
                    </div>
                    <p class="level-note">
        <?php if ($is_admin): ?>
                        <?php echo ($level['par_moves'] !== null) ? 'Par: ' . (int) $level['par_moves'] . ' moves' : 'No par set'; ?>
                        &middot; order <?php echo (int) $level['number']; ?>
        <?php elseif ($status === 'completed'): ?>
                        Best: <?php echo (int) $level['best_moves']; ?> moves
        <?php elseif ($status === 'unlocked'): ?>
                        Not completed yet
        <?php else: ?>
                        Finish the previous level first
        <?php endif; ?>
                    </p>
        <?php if ($is_admin): ?>
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <a class="btn btn-outline-navy btn-sm" href="play.php?id=<?php echo (int) $level['level_id']; ?>">Test Play</a>
                        <a class="btn btn-outline-navy btn-sm" href="level_form.php?id=<?php echo (int) $level['level_id']; ?>">Edit</a>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                                data-action="delete_level" data-id="<?php echo (int) $level['level_id']; ?>"
                                data-title="Delete &quot;<?php echo e($level['title']); ?>&quot;?"
                                data-message="This permanently deletes the level and its <?php echo (int) ($score_counts[(int) $level['level_id']] ?? 0); ?> saved player score(s) (the database cascades the delete). This cannot be undone."
                                data-button="Delete level">Delete</button>
                    </div>
        <?php elseif ($status === 'locked'): ?>
                    <button type="button" class="btn btn-secondary" disabled>Locked</button>
        <?php else: ?>
                    <a class="btn <?php echo ($status === 'completed') ? 'btn-outline-navy' : 'btn-primary'; ?>" href="play.php?id=<?php echo (int) $level['level_id']; ?>"><?php
                        echo ($status === 'completed') ? 'Play Again' : 'Play'; ?></a>
        <?php endif; ?>
                </article>
            </div>
    <?php endforeach; ?>
        </div>
<?php endif; ?>
    </main>

    <?php include 'includes/confirm_modal.php'; ?>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
