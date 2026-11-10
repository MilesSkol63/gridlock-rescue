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

$level = get_level($conn, $level_id);        // includes the layout text
$next_level_id = isset($levels[$index + 1]) ? (int) $levels[$index + 1]['level_id'] : null;
$best_moves = $is_admin ? null : $levels[$index]['best_moves'];

// Everything the JavaScript engine needs. The user id and role are deliberately NOT included.
$level_data = [
    'levelId'     => (int) $level['level_id'],
    'title'       => $level['title'],
    'layout'      => $level['layout'],
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

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="gr-card board-card" aria-label="Game board">
                    <div class="board-wrap" id="board-wrap">
                        <div class="board board-game" id="game-board" role="img" aria-label="Puzzle board"></div>

                        <div class="win-overlay d-none" id="win-overlay" role="dialog" aria-live="polite" aria-labelledby="win-title">
                            <div class="win-box">
                                <h2 id="win-title">LEVEL COMPLETE!</h2>
                                <p class="win-line">Moves: <strong id="win-moves">0</strong>
                                    <span class="badge badge-new-best d-none" id="win-new-best">NEW BEST!</span></p>
                                <p class="win-status" id="win-status"></p>
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    <button type="button" class="btn btn-primary d-none" id="btn-next">Next Level</button>
                                    <button type="button" class="btn btn-warning d-none" id="btn-retry">Retry Save</button>
                                    <button type="button" class="btn btn-outline-light" id="btn-replay">Replay</button>
                                    <a class="btn btn-outline-light" href="levels.php" id="btn-levels">Level List</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <ul class="board-legend list-unstyled" aria-label="Legend">
                        <li><span class="legend-swatch cell wall"></span> wall</li>
                        <li><span class="legend-swatch cell floor"></span> floor</li>
                        <li><span class="legend-swatch cell floor target"></span> target</li>
                        <li><span class="legend-swatch cell floor box"></span> box</li>
                        <li><span class="legend-swatch cell floor box target"></span> box on target</li>
                        <li><span class="legend-swatch cell floor player"></span> player</li>
                    </ul>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="gr-card" id="game-sidebar">
                    <div class="stat-label">MOVES</div>
                    <div class="move-count" id="move-count">0</div>
                    <p class="mb-3">Best: <strong id="best-moves">&mdash;</strong></p>

                    <div class="d-grid gap-2 mb-3">
                        <button type="button" class="btn btn-outline-navy" id="btn-undo" disabled>Undo (Z)</button>
                        <button type="button" class="btn btn-outline-navy" id="btn-restart">Restart (R)</button>
                    </div>

                    <div class="stat-label">HOW TO PLAY</div>
                    <p class="small mb-3">Move with the arrow keys or WASD. Push every box onto a target. Boxes can be pushed but never pulled.</p>
<?php if (!empty($level['hint'])): ?>
                    <div class="stat-label">HINT</div>
                    <p class="small mb-3"><?php echo e($level['hint']); ?></p>
<?php endif; ?>

                    <div class="dpad-container">
                        <div class="dpad d-lg-none" aria-label="On-screen controls">
                            <button type="button" class="btn btn-outline-navy dpad-btn dpad-up" data-dir="up" aria-label="Move up">&uarr;</button>
                            <button type="button" class="btn btn-outline-navy dpad-btn dpad-left" data-dir="left" aria-label="Move left">&larr;</button>
                            <button type="button" class="btn btn-outline-navy dpad-btn dpad-down" data-dir="down" aria-label="Move down">&darr;</button>
                            <button type="button" class="btn btn-outline-navy dpad-btn dpad-right" data-dir="right" aria-label="Move right">&rarr;</button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <script type="application/json" id="level-data"><?php
            echo json_encode($level_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?></script>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/game.js"></script>
</body>
</html>
