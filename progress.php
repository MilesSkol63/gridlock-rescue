<?php
/**
 * progress.php  (REPLACES the placeholder)  - progress overview, same page for both roles.
 *   regular user : every level with status, best moves and completion date (read only)
 *   administrator: all stored progress records (read only)
 * Reset and delete actions arrive in Deliverable 4.
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');

if ($is_admin) {
    $records = db_select_all(
        $conn,
        'SELECT u.username, l.title, p.best_moves, p.completed_at, p.updated_at
         FROM user_progress p
         JOIN users  u ON u.user_id  = p.user_id
         JOIN levels l ON l.level_id = p.level_id
         ORDER BY p.updated_at DESC, p.progress_id DESC
         LIMIT 100'
    );
} else {
    $levels = get_levels_with_status($conn, (int) $account['user_id']);
    $dates = [];
    $date_rows = db_select_all(
        $conn,
        'SELECT level_id, completed_at FROM user_progress WHERE user_id = ?',
        'i',
        [(int) $account['user_id']]
    );
    foreach ($date_rows as $row) {
        $dates[(int) $row['level_id']] = substr($row['completed_at'], 0, 10);
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
        </div>

<?php if ($is_admin): ?>
        <section class="gr-card" id="admin-progress">
            <h2 class="section-title">All progress records (latest 100)</h2>
    <?php if (empty($records)): ?>
            <div class="empty-state">No progress records yet.</div>
    <?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead><tr><th>Player</th><th>Level</th><th>Best Moves</th><th class="d-none d-md-table-cell">Completed</th></tr></thead>
                    <tbody>
        <?php foreach ($records as $row): ?>
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
            <p class="text-muted small mt-3 mb-0">Deleting records arrives in Deliverable 4.</p>
        </section>
<?php else: ?>
        <section class="gr-card" id="user-progress">
            <h2 class="section-title">Levels</h2>
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
            <?php if ($level['status'] !== 'locked'): ?>
                                <a class="btn btn-outline-navy btn-sm" href="play.php?id=<?php echo (int) $level['level_id']; ?>"><?php echo ($level['status'] === 'completed') ? 'Play Again' : 'Play'; ?></a>
            <?php else: ?>
                                &mdash;
            <?php endif; ?>
                            </td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php endif; ?>
            <p class="text-muted small mt-3 mb-0">Resetting progress arrives in Deliverable 4.</p>
        </section>
<?php endif; ?>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
