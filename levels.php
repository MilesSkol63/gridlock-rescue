<?php
/**
 * levels.php  (REPLACES the placeholder)  - level selection for both roles (same page).
 *   regular user : completed / unlocked / locked cards; locked levels cannot be opened
 *   administrator: every level has a Test Play button (preview, nothing is saved)
 * Creating, editing and deleting levels is Deliverable 4.
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');
$levels   = get_levels_with_status($conn, (int) $account['user_id']);
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
            <span class="badge badge-role-admin">ADMIN PREVIEW</span>
            <?php endif; ?>
        </div>

<?php if ($is_admin): ?>
        <p class="text-muted">Test Play opens any level without saving progress. Creating, editing and deleting levels arrives in Deliverable 4.</p>
<?php endif; ?>

<?php if (empty($levels)): ?>
        <div class="empty-state">No levels are available yet.</div>
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
                        <span class="text-muted small"><?php echo (int) $level['grid_width']; ?>x<?php echo (int) $level['grid_height']; ?>, <?php echo (int) $level['box_count']; ?> box<?php echo ((int) $level['box_count'] === 1) ? '' : 'es'; ?></span>
                    </div>
                    <p class="level-note">
        <?php if ($is_admin): ?>
                        <?php echo ($level['par_moves'] !== null) ? 'Par: ' . (int) $level['par_moves'] . ' moves' : 'No par set'; ?>
        <?php elseif ($status === 'completed'): ?>
                        Best: <?php echo (int) $level['best_moves']; ?> moves
        <?php elseif ($status === 'unlocked'): ?>
                        Not completed yet
        <?php else: ?>
                        Finish the previous level first
        <?php endif; ?>
                    </p>
        <?php if ($status === 'locked'): ?>
                    <button type="button" class="btn btn-secondary" disabled>Locked</button>
        <?php else: ?>
                    <a class="btn <?php echo ($status === 'completed') ? 'btn-outline-navy' : 'btn-primary'; ?>" href="play.php?id=<?php echo (int) $level['level_id']; ?>"><?php
                        echo $is_admin ? 'Test Play' : (($status === 'completed') ? 'Play Again' : 'Play'); ?></a>
        <?php endif; ?>
                </article>
            </div>
    <?php endforeach; ?>
        </div>
<?php endif; ?>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
