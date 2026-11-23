<?php
/**
 * leaderboard.php  (REPLACES the placeholder)  - rankings for both roles (same page).
 * Overall: most levels completed first, then fewest total best moves; equal rows share a rank.
 * Per level (?level=ID): fewest best moves first. Administrators are never listed.
 */
require_once __DIR__ . '/../includes/auth.php';

/** @var PDO $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');
$my_id    = (int) $account['user_id'];

$level_choices = db_select_all($conn, 'SELECT level_id, title FROM levels WHERE game_type = \'sliding\' ORDER BY sort_order ASC, level_id ASC');

// Which view? ?level=<id> must be one of the existing levels, otherwise show "Overall".
$selected_level = null;
$requested = filter_var($_GET['level'] ?? '', FILTER_VALIDATE_INT);
if ($requested !== false) {
    foreach ($level_choices as $choice) {
        if ((int) $choice['level_id'] === $requested) {
            $selected_level = $choice;
        }
    }
}

if ($selected_level === null) {
    $rows = db_select_all(
        $conn,
        'SELECT u.user_id, u.username, COUNT(p.level_id) AS levels_completed, SUM(p.best_moves) AS total_moves
         FROM users u
         JOIN user_progress p ON p.user_id = u.user_id
         JOIN levels l ON l.level_id = p.level_id
         WHERE u.role = \'user\' AND l.game_type = \'sliding\'
         GROUP BY u.user_id, u.username
         ORDER BY levels_completed DESC, total_moves ASC, u.username ASC
         LIMIT 20'
    );
    // Equal (levels, moves) share the rank of the first of them.
    $previous = null;
    foreach ($rows as $i => $row) {
        $key = $row['levels_completed'] . '/' . $row['total_moves'];
        $rows[$i]['rank'] = ($key === $previous) ? $rows[$i - 1]['rank'] : $i + 1;
        $previous = $key;
    }
} else {
    $rows = db_select_all(
        $conn,
        'SELECT u.user_id, u.username, p.best_moves, p.updated_at
         FROM user_progress p JOIN users u ON u.user_id = p.user_id
         WHERE p.level_id = ? AND u.role = \'user\'
         ORDER BY p.best_moves ASC, p.updated_at ASC, u.username ASC
         LIMIT 20',
        'i',
        [(int) $selected_level['level_id']]
    );
    $previous = null;
    foreach ($rows as $i => $row) {
        $rows[$i]['rank'] = ((int) $row['best_moves'] === $previous) ? $rows[$i - 1]['rank'] : $i + 1;
        $previous = (int) $row['best_moves'];
    }
}

// A player outside the top 20 still sees their own position (overall view only).
$my_rank = null;
if (!$is_admin && $selected_level === null) {
    $in_list = false;
    foreach ($rows as $row) {
        if ((int) $row['user_id'] === $my_id) {
            $in_list = true;
        }
    }
    if (!$in_list) {
        $my_rank = get_user_rank($conn, $my_id);
    }
}
$active_page = 'leaderboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leaderboard - Gridlock Rescue</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container page-main" id="page-content">
        <?php show_flash(); ?>

        <div class="page-header">
            <h1>Leaderboard</h1>
            <form method="get" action="leaderboard.php" class="ms-auto d-flex align-items-center gap-2" id="leaderboard-filter">
                <label for="level" class="form-label mb-0">View:</label>
                <select class="form-select form-select-sm w-auto" id="level" name="level">
                    <option value="0">Overall</option>
    <?php foreach ($level_choices as $choice): ?>
                    <option value="<?php echo (int) $choice['level_id']; ?>"<?php echo ($selected_level !== null && (int) $selected_level['level_id'] === (int) $choice['level_id']) ? ' selected' : ''; ?>><?php echo e($choice['title']); ?></option>
    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-navy btn-sm">Show</button>
            </form>
        </div>

        <section class="gr-card" id="leaderboard-table">
            <h2 class="section-title"><?php echo ($selected_level === null) ? 'Overall ranking' : e($selected_level['title']); ?></h2>
<?php if (empty($rows)): ?>
            <div class="empty-state">No scores yet. Complete a level to appear here!</div>
<?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead>
    <?php if ($selected_level === null): ?>
                        <tr><th>Rank</th><th>Player</th><th>Levels Completed</th><th>Total Best Moves</th></tr>
    <?php else: ?>
                        <tr><th>Rank</th><th>Player</th><th>Best Moves</th><th class="d-none d-md-table-cell">Completed On</th></tr>
    <?php endif; ?>
                    </thead>
                    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php $mine = (!$is_admin && (int) $row['user_id'] === $my_id); ?>
                        <tr<?php echo $mine ? ' class="table-row-me"' : ''; ?>>
                            <td><?php echo (int) $row['rank']; ?></td>
                            <td><?php echo e($row['username']); ?><?php echo $mine ? ' <span class="text-muted">(you)</span>' : ''; ?></td>
        <?php if ($selected_level === null): ?>
                            <td><?php echo (int) $row['levels_completed']; ?> / <?php echo count($level_choices); ?></td>
                            <td><?php echo (int) $row['total_moves']; ?></td>
        <?php else: ?>
                            <td><?php echo (int) $row['best_moves']; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo e(substr($row['updated_at'], 0, 10)); ?></td>
        <?php endif; ?>
                        </tr>
    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif; ?>
<?php if ($my_rank !== null): ?>
            <p class="mt-3 mb-0">Your rank: <strong>#<?php echo (int) $my_rank['rank']; ?></strong>
                (<?php echo (int) $my_rank['levels_done']; ?> levels, <?php echo (int) $my_rank['total_moves']; ?> total moves)</p>
<?php endif; ?>
            <p class="text-muted small mt-3 mb-0">Ranking: more levels first, then fewer total moves; equal results share a rank.</p>
        </section>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
