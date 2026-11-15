<?php
/**
 * dashboard.php  (NEW)  - the ONE dashboard for both roles.
 * The session role decides which queries run and which sections are printed.
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');
$user_id  = (int) $account['user_id'];

if ($is_admin) {
    // ---- Administrator data: real counts from the database ----
    $stats = db_select_one(
        $conn,
        'SELECT (SELECT COUNT(*) FROM users)         AS total_users,
                (SELECT COUNT(*) FROM levels WHERE game_type = \'sliding\') AS total_levels,
                (SELECT COUNT(*) FROM user_progress pr JOIN levels lv ON lv.level_id = pr.level_id
                 WHERE lv.game_type = \'sliding\') AS total_records'
    );
    $recent_completions = db_select_all(
        $conn,
        'SELECT u.username, l.title, p.best_moves, p.completed_at
         FROM user_progress p
         JOIN users  u ON u.user_id  = p.user_id
         JOIN levels l ON l.level_id = p.level_id
         WHERE l.game_type = \'sliding\'
         ORDER BY p.completed_at DESC, p.progress_id DESC
         LIMIT 5'
    );
} else {
    // ---- Regular user data ----
    $levels = get_levels_with_status($conn, $user_id);
    $total_levels = count($levels);
    $completed = 0;
    $unlocked = 0;
    $locked = 0;
    $next_level = null;                       // first level that is unlocked but not finished
    foreach ($levels as $level) {
        if ($level['status'] === 'completed') {
            $completed++;
        } elseif ($level['status'] === 'unlocked') {
            $unlocked++;
            if ($next_level === null) {
                $next_level = $level;
            }
        } else {
            $locked++;
        }
    }
    $percent = ($total_levels > 0) ? (int) round($completed / $total_levels * 100) : 0;

    // Sliding-block scores only (archived Sokoban records are ignored). Same ranking rule as the leaderboard.
    $rank_info = get_user_rank($conn, $user_id);
    $total_moves = ($rank_info !== null) ? $rank_info['total_moves'] : 0;
    $rank = ($rank_info !== null) ? $rank_info['rank'] : null;

    $recent_scores = db_select_all(
        $conn,
        'SELECT l.title, l.difficulty, p.best_moves, p.completed_at
         FROM user_progress p JOIN levels l ON l.level_id = p.level_id
         WHERE p.user_id = ? AND l.game_type = \'sliding\'
         ORDER BY p.updated_at DESC, p.progress_id DESC
         LIMIT 5',
        'i',
        [$user_id]
    );
}

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Gridlock Rescue</title>
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

<?php if ($is_admin): ?>
        <!-- ================= ADMINISTRATOR DASHBOARD ================= -->
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <span class="badge badge-role-admin">ADMIN</span>
        </div>

        <div class="row g-3 mb-4" id="admin-stats">
            <div class="col-12 col-md-4">
                <div class="stat-card"><div class="stat-label">REGISTERED USERS</div><div class="stat-value"><?php echo (int) $stats['total_users']; ?></div></div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card"><div class="stat-label">LEVELS</div><div class="stat-value"><?php echo (int) $stats['total_levels']; ?></div></div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card"><div class="stat-label">COMPLETION RECORDS</div><div class="stat-value"><?php echo (int) $stats['total_records']; ?></div></div>
            </div>
        </div>

        <section class="gr-card mb-4" id="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-navy" href="users.php">Manage Users</a>
                <a class="btn btn-outline-navy" href="levels.php">Manage Levels</a>
                <a class="btn btn-primary" href="level_form.php">+ New Level</a>
                <a class="btn btn-outline-navy" href="progress.php">Progress Records</a>
            </div>
        </section>

        <section class="gr-card" id="recent-completions">
            <h2 class="section-title">Recent Completions</h2>
    <?php if (empty($recent_completions)): ?>
            <div class="empty-state">No levels have been completed yet. Records appear here once players finish levels.</div>
    <?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead><tr><th>Player</th><th>Level</th><th>Moves</th><th class="d-none d-md-table-cell">Completed</th></tr></thead>
                    <tbody>
        <?php foreach ($recent_completions as $row): ?>
                        <tr>
                            <td><?php echo e($row['username']); ?></td>
                            <td><?php echo e($row['title']); ?></td>
                            <td><?php echo (int) $row['best_moves']; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo e(substr($row['completed_at'], 0, 10)); ?></td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php endif; ?>
        </section>

<?php else: ?>
        <!-- ================= REGULAR USER DASHBOARD ================= -->
        <div class="page-header">
            <h1>Welcome back, <?php echo e($account['username']); ?>!</h1>
            <span class="badge badge-role-player">PLAYER</span>
        </div>

        <div class="row g-3 mb-4" id="user-stats">
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-label">LEVELS DONE</div><div class="stat-value"><?php echo $completed; ?> / <?php echo $total_levels; ?></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-label">TOTAL BEST MOVES</div><div class="stat-value"><?php echo ($completed > 0) ? $total_moves : '&mdash;'; ?></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-label">RANK</div><div class="stat-value"><?php echo ($rank !== null) ? '#' . $rank : '&mdash;'; ?></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-label">NEXT LEVEL</div><div class="stat-value stat-value-sm"><?php echo ($next_level !== null) ? 'Level ' . (int) $next_level['number'] : '&mdash;'; ?></div></div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <section class="gr-card h-100" id="continue-playing">
                    <h2 class="section-title"><?php echo ($completed === 0 ) ? 'Start Playing' : 'Continue Playing'; ?></h2>
    <?php if ($total_levels === 0): ?>
                    <div class="empty-state">No levels are available yet. Check back soon!</div>
    <?php elseif ($next_level === null): ?>
                    <p class="mb-2"><strong>All levels completed!</strong> Replay any level to improve your best score.</p>
                    <a class="btn btn-primary align-self-start" href="levels.php">Browse Levels</a>
    <?php else: ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="level-title">Level <?php echo (int) $next_level['number']; ?> - <?php echo e($next_level['title']); ?></span>
                        <span class="badge <?php echo e(difficulty_class($next_level['difficulty'])); ?>"><?php echo e(ucfirst($next_level['difficulty'])); ?></span>
                    </div>
                    <p class="text-muted small mb-2">Progress: <?php echo $completed; ?> of <?php echo $total_levels; ?> completed (<?php echo $unlocked; ?> unlocked, <?php echo $locked; ?> locked)</p>
                    <div class="progress mb-3" role="progressbar" aria-label="Level progress" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <a class="btn btn-primary align-self-start" href="play.php?id=<?php echo (int) $next_level['level_id']; ?>">&#9654; <?php echo ($completed === 0 ) ? 'Start Playing' : 'Continue Playing'; ?></a>
    <?php endif; ?>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="gr-card h-100" id="shortcuts">
                    <h2 class="section-title">Shortcuts</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-navy" href="levels.php">Browse Levels</a>
                        <a class="btn btn-outline-navy" href="leaderboard.php">Leaderboard</a>
                        <a class="btn btn-outline-navy" href="profile.php">My Profile</a>
                    </div>
                </section>
            </div>
        </div>

        <section class="gr-card" id="recent-scores">
            <h2 class="section-title">My Recent Best Scores</h2>
    <?php if (empty($recent_scores)): ?>
            <div class="empty-state">You have not completed a level yet. Your best scores will appear here.</div>
    <?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead><tr><th>Level</th><th>Difficulty</th><th>Best Moves</th><th class="d-none d-md-table-cell">Completed</th></tr></thead>
                    <tbody>
        <?php foreach ($recent_scores as $row): ?>
                        <tr>
                            <td><?php echo e($row['title']); ?></td>
                            <td><span class="badge <?php echo e(difficulty_class($row['difficulty'])); ?>"><?php echo e(ucfirst($row['difficulty'])); ?></span></td>
                            <td><?php echo (int) $row['best_moves']; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo e(substr($row['completed_at'], 0, 10)); ?></td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php endif; ?>
        </section>
<?php endif; ?>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
