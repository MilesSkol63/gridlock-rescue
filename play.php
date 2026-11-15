<?php
/**
 * play.php  (NEW)  - the gameplay page: play.php?id=<level_id>
 * PHP checks access and prints the level as JSON; assets/js/game.js runs the puzzle in the browser.
 *   regular user : may open completed and unlocked levels; results are saved
 *   administrator: may preview ANY level; nothing is saved
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');
$user_id  = (int) $account['user_id'];

$level_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($level_id === false || $level_id < 1) {
    set_flash('warning', 'Please choose a level from the list.');
    redirect('levels.php');
}

// All levels with this user's status, so we know the number, the next level and the lock state.
$levels = get_levels_with_status($conn, $user_id);
$index = null;
foreach ($levels as $i => $row) {
    if ((int) $row['level_id'] === $level_id) {
        $index = $i;
    }
}
if ($index === null) {
    set_flash('warning', 'That level does not exist.');
    redirect('levels.php');
}
if (!$is_admin && $levels[$index]['status'] === 'locked') {
    set_flash('warning', 'That level is locked. Complete the previous level first.');
    redirect('levels.php');
}

$level = get_level($conn, $level_id);        // the stored layout: 6 rows of 6 characters
$canonical_rows = sliding_rows_from_text($level['layout']);
$layout_hash = sliding_layout_fingerprint($level['layout']);
$next_level_id = isset($levels[$index + 1]) ? (int) $levels[$index + 1]['level_id'] : null;
$best_moves = $is_admin ? null : $levels[$index]['best_moves'];

// Everything the JavaScript game needs. The user id and role are deliberately NOT included.
$level_data = [
    'levelId'     => (int) $level['level_id'],
    'title'       => $level['title'],
    'rows'        => $canonical_rows,
    'layoutHash'  => $layout_hash,
    'par'         => (int) $level['par_moves'],
    'canSave'     => !$is_admin,
    'bestMoves'   => $best_moves,
    'nextLevelId' => $next_level_id,
    'csrf'        => csrf_token(),
    'saveUrl'     => 'save_progress.php',
    'playUrl'     => 'play.php',
];

$active_page = 'levels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Level <?php echo (int) $levels[$index]['number']; ?>: <?php echo e($level['title']); ?> - Gridlock Rescue</title>
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
            <h1>Level <?php echo (int) $levels[$index]['number']; ?>: <?php echo e($level['title']); ?></h1>
            <span class="badge <?php echo e(difficulty_class($level['difficulty'])); ?>"><?php echo e(ucfirst($level['difficulty'])); ?></span>
            <a class="ms-auto" href="levels.php" id="back-to-levels">&lsaquo; Back to Levels</a>
        </div>

<?php if ($is_admin): ?>
        <div class="alert alert-info" role="status" id="admin-preview-banner">Admin preview &ndash; progress is not saved.</div>
<?php endif; ?>

        <div class="sl-wrap">
            <section class="sl-hud" aria-label="Status">
                <div class="stat-card"><div class="stat-label">MOVES</div><div class="stat-value sl-moves" id="move-count">0</div></div>
                <div class="stat-card"><div class="stat-label">PAR (OPTIMAL)</div><div class="stat-value" id="best-known"><?php echo (int) $level['par_moves']; ?></div></div>
                <div class="stat-card"><div class="stat-label">YOUR BEST</div><div class="stat-value" id="your-best">&mdash;</div></div>
            </section>

            <section class="sl-board-area" id="board-area" aria-label="Game board">
                <div class="sl-frame">
                    <div class="sl-board" id="game-board" role="application" aria-label="Rescue puzzle board"></div>
                    <div class="sl-exit" aria-hidden="true"><span class="sl-exit-arrow">&#10148;</span><span class="sl-exit-text">EXIT</span></div>
                    <div class="sl-exit-gap" aria-hidden="true"></div>
                </div>

                <div class="sl-overlay d-none" id="win-overlay" role="dialog" aria-live="polite" aria-labelledby="win-title">
                    <div class="sl-win-box">
                        <div class="sl-siren" aria-hidden="true"></div>
                        <h2 id="win-title">RESCUE COMPLETE!</h2>
                        <p class="sl-win-moves"><strong id="win-moves">0</strong> moves
                            <span class="badge badge-new-best d-none" id="win-new-best">NEW BEST!</span></p>
                        <p class="sl-win-rating" id="win-rating"></p>
                        <p class="sl-win-status" id="win-status"></p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-primary d-none" id="btn-next">Next Level</button>
                            <button type="button" class="btn btn-warning d-none" id="btn-retry">Retry Save</button>
                            <button type="button" class="btn btn-outline-light" id="btn-replay">Replay</button>
                            <a class="btn btn-outline-light" href="levels.php" id="btn-levels">Level List</a>
                        </div>
                    </div>
                </div>
            </section>

            <div class="sl-controls">
                <button type="button" class="btn btn-outline-navy" id="btn-undo" disabled>&#8630; Undo <kbd>Z</kbd></button>
                <button type="button" class="btn btn-outline-navy" id="btn-restart">&#8635; Restart <kbd>R</kbd></button>
            </div>

<?php if (!empty($level['hint'])): ?>
            <p class="sl-hint"><?php echo e($level['hint']); ?></p>
<?php endif; ?>
            <p class="sl-help sl-help-touch">Drag a vehicle along its lane. A slide of any length is one move. Get the red rescue vehicle out through the exit.</p>
            <p class="sl-help sl-help-keys">Drag a vehicle, or select it (click or <kbd>Tab</kbd>) and use the arrow keys; hold <kbd>Shift</kbd> to slide all the way.
                <kbd>Z</kbd> undo, <kbd>R</kbd> restart. A slide of any length is one move.</p>
        </div>

        <script type="application/json" id="level-data"><?php
            echo json_encode($level_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?></script>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sliding-engine.js"></script>
    <script src="assets/js/game.js"></script>
</body>
</html>
