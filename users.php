<?php
/**
 * users.php  (REPLACES the placeholder)  - ADMINISTRATOR-ONLY user management.
 *   Create: "Add Player" form (role is always 'user', same validators as register.php)
 *   Read  : searchable table of accounts
 *   Update: Edit button -> user_edit.php
 *   Status: Disable / Enable Account (confirmation dialog -> POST disable_user / enable_user).
 *           Accounts are NEVER deleted here; disabling keeps every record (profile, progress, scores).
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account = require_admin();
$my_id   = (int) $account['user_id'];

$add_errors = [];
$add = ['username' => '', 'email' => ''];
$open_add_form = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');

    if ($action === 'add_user') {
        // ---------- CREATE (administrators can only create regular players) ----------
        $add['username'] = trim(post_string('username'));
        $add['email']    = strtolower(trim(post_string('email')));
        $password = post_string('password');
        $confirm  = post_string('confirm_password');

        $message = validate_username($add['username']);
        if ($message !== '') { $add_errors['username'] = $message; }
        $message = validate_email($add['email']);
        if ($message !== '') { $add_errors['email'] = $message; }
        $message = validate_password($password);
        if ($message !== '') { $add_errors['password'] = $message; }
        if (!isset($add_errors['password']) && $password !== $confirm) {
            $add_errors['confirm_password'] = 'Passwords do not match.';
        }
        if (!isset($add_errors['username']) && !isset($add_errors['email'])) {
            $add_errors = array_merge($add_errors, check_account_duplicates($conn, $add['username'], $add['email']));
        }

        if (empty($add_errors)) {
            try {
                db_execute(
                    $conn,
                    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')",
                    'sss',
                    [$add['username'], $add['email'], password_hash($password, PASSWORD_DEFAULT)]
                );
                set_flash('success', 'Player "' . $add['username'] . '" was created.');
                redirect('users.php');
            } catch (mysqli_sql_exception $ex) {
                error_log('Gridlock Rescue: add_user failed: ' . $ex->getMessage());
                $add_errors['form'] = ($ex->getCode() === 1062)
                    ? 'That username or email is already registered.'
                    : 'The account could not be created. Please try again.';
            }
        }
        $open_add_form = true;      // show the form again with the messages

    } elseif ($action === 'disable_user' || $action === 'enable_user') {
        // ---------- DISABLE / ENABLE (no data is deleted; all safety rules are enforced in set_account_active()) ----------
        $target_id = filter_var(post_string('id'), FILTER_VALIDATE_INT);
        if ($target_id === false || $target_id < 1) {
            set_flash('danger', 'Invalid account.');
        } else {
            $outcome = set_account_active($conn, $my_id, $target_id, $action === 'enable_user');
            if ($outcome['ok']) {
                set_flash('success', $outcome['message']);
            } else {
                set_flash('danger', $outcome['error']);
            }
        }
        redirect('users.php' . (isset($_GET['q']) && is_string($_GET['q']) && $_GET['q'] !== '' ? '?q=' . urlencode($_GET['q']) : ''));
    } else {
        set_flash('danger', 'Unknown action.');
        redirect('users.php');
    }
}

// ---------- READ: list + search ----------
$q = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';          // % and _ typed by the admin are searched literally
    $users = db_select_all(
        $conn,
        'SELECT u.user_id, u.username, u.email, u.role, u.is_active, u.created_at,
                (SELECT COUNT(*) FROM user_progress p JOIN levels lv ON lv.level_id = p.level_id
                 WHERE p.user_id = u.user_id AND lv.game_type = \'sliding\') AS levels_done
         FROM users u WHERE u.username LIKE ? OR u.email LIKE ?
         ORDER BY u.user_id ASC LIMIT 200',
        'ss',
        [$like, $like]
    );
} else {
    $users = db_select_all(
        $conn,
        'SELECT u.user_id, u.username, u.email, u.role, u.is_active, u.created_at,
                (SELECT COUNT(*) FROM user_progress p JOIN levels lv ON lv.level_id = p.level_id
                 WHERE p.user_id = u.user_id AND lv.game_type = \'sliding\') AS levels_done
         FROM users u ORDER BY u.user_id ASC LIMIT 200'
    );
}
$total_levels = count_sliding_levels($conn);
$confirm_action_url = 'users.php' . ($q !== '' ? '?q=' . urlencode($q) : '');   // the confirmation dialog posts here, keeping the search

$active_page = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users - Gridlock Rescue</title>
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
            <h1>Manage Users</h1>
            <span class="badge badge-role-admin">ADMIN ONLY</span>
            <form method="get" action="users.php" class="ms-auto d-flex gap-2" id="user-search-form" role="search">
                <label for="q" class="visually-hidden">Search users</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" value="<?php echo e($q); ?>" placeholder="username or email" maxlength="100">
                <button type="submit" class="btn btn-outline-navy btn-sm">Search</button>
<?php if ($q !== ''): ?>
                <a class="btn btn-link btn-sm" href="users.php">Clear</a>
<?php endif; ?>
            </form>
        </div>

        <section class="gr-card mb-4" id="add-player">
            <h2 class="section-title mb-0">
                <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#add-player-form"
                        aria-expanded="<?php echo $open_add_form ? 'true' : 'false'; ?>" aria-controls="add-player-form">+ Add Player</button>
            </h2>
            <div class="collapse<?php echo $open_add_form ? ' show' : ''; ?>" id="add-player-form">
                <p class="text-muted small mt-2">Creates a regular player account. Administrator accounts are created only with <code>setup_admin.php</code>.</p>
<?php if (isset($add_errors['form'])): ?>
                <div class="alert alert-danger" role="alert"><?php echo e($add_errors['form']); ?></div>
<?php endif; ?>
                <form method="post" action="users.php" class="row g-3" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_user">
                    <div class="col-md-6">
                        <label for="add-username" class="form-label">Username</label>
                        <input type="text" class="form-control<?php echo isset($add_errors['username']) ? ' is-invalid' : ''; ?>" id="add-username" name="username"
                               value="<?php echo e($add['username']); ?>" maxlength="20" autocomplete="off" required>
                        <div class="invalid-feedback"><?php echo e($add_errors['username'] ?? ''); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="add-email" class="form-label">Email</label>
                        <input type="email" class="form-control<?php echo isset($add_errors['email']) ? ' is-invalid' : ''; ?>" id="add-email" name="email"
                               value="<?php echo e($add['email']); ?>" maxlength="100" autocomplete="off" required>
                        <div class="invalid-feedback"><?php echo e($add_errors['email'] ?? ''); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="add-password" class="form-label">Password</label>
                        <input type="password" class="form-control<?php echo isset($add_errors['password']) ? ' is-invalid' : ''; ?>" id="add-password" name="password" autocomplete="new-password" required>
                        <div class="form-text">At least 8 characters, with a letter and a number</div>
                        <div class="invalid-feedback"><?php echo e($add_errors['password'] ?? ''); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="add-confirm" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control<?php echo isset($add_errors['confirm_password']) ? ' is-invalid' : ''; ?>" id="add-confirm" name="confirm_password" autocomplete="new-password" required>
                        <div class="invalid-feedback"><?php echo e($add_errors['confirm_password'] ?? ''); ?></div>
                    </div>
                    <div class="col-12"><button type="submit" class="btn btn-primary">Create Player</button></div>
                </form>
            </div>
        </section>

        <section class="gr-card" id="users-table">
            <h2 class="section-title">Registered accounts<?php echo ($q !== '') ? ' matching "' . e($q) . '"' : ''; ?> (<?php echo count($users); ?>)</h2>
<?php if (empty($users)): ?>
            <div class="empty-state">No accounts found.</div>
<?php else: ?>
            <div class="table-responsive">
                <table class="table gr-table align-middle mb-0">
                    <thead>
                        <tr><th>ID</th><th>Username</th><th class="d-none d-md-table-cell">Email</th><th>Role</th><th>Status</th>
                            <th class="d-none d-lg-table-cell">Levels</th><th class="d-none d-lg-table-cell">Joined</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
    <?php foreach ($users as $row): ?>
        <?php $is_player = ($row['role'] === 'user'); $is_active = ((int) $row['is_active'] === 1); $is_me = ((int) $row['user_id'] === $my_id); ?>
                        <tr>
                            <td><?php echo (int) $row['user_id']; ?></td>
                            <td><?php echo e($row['username']); ?><?php echo ((int) $row['user_id'] === $my_id) ? ' <span class="text-muted">(you)</span>' : ''; ?></td>
                            <td class="d-none d-md-table-cell"><?php echo e($row['email']); ?></td>
                            <td><span class="badge <?php echo $is_player ? 'badge-role-player' : 'badge-role-admin'; ?>"><?php echo $is_player ? 'PLAYER' : 'ADMIN'; ?></span></td>
                            <td><span class="badge <?php echo $is_active ? 'badge-status-active' : 'badge-status-disabled'; ?>"><?php echo $is_active ? 'Active' : 'Disabled'; ?></span></td>
                            <td class="d-none d-lg-table-cell"><?php echo $is_player ? (int) $row['levels_done'] . ' / ' . $total_levels : '&mdash;'; ?></td>
                            <td class="d-none d-lg-table-cell"><?php echo e(substr($row['created_at'], 0, 10)); ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
        <?php if ($is_player): ?>
                                    <a class="btn btn-outline-navy btn-sm" href="user_edit.php?id=<?php echo (int) $row['user_id']; ?>">Edit</a>
                                    <a class="btn btn-outline-navy btn-sm" href="progress.php?user_id=<?php echo (int) $row['user_id']; ?>">Progress</a>
        <?php endif; ?>
        <?php if ($is_me): ?>
                                    <span class="text-muted small align-self-center">Your account</span>
        <?php elseif ($is_active): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                                            data-action="disable_user" data-id="<?php echo (int) $row['user_id']; ?>"
                                            data-title="Disable <?php echo e($row['username']); ?>?"
                                            data-message="<?php echo e($row['username']); ?> will be logged out on their next request and cannot log in until you enable the account again. Nothing is deleted: profile, progress and scores are kept."
                                            data-button="Disable account" data-button-class="btn-danger">Disable Account</button>
        <?php else: ?>
                                    <button type="button" class="btn btn-outline-navy btn-sm" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                                            data-action="enable_user" data-id="<?php echo (int) $row['user_id']; ?>"
                                            data-title="Enable <?php echo e($row['username']); ?>?"
                                            data-message="<?php echo e($row['username']); ?> will be able to log in again and will keep all previous progress and scores."
                                            data-button="Enable account" data-button-class="btn-primary">Enable Account</button>
        <?php endif; ?>
                                </div>
                            </td>
                        </tr>
    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif; ?>
            <p class="text-muted small mt-3 mb-0">Accounts are never deleted: disabling keeps all data and can be undone. You cannot disable your own account or the last active administrator. Administrators change their own details on the Profile page.</p>
        </section>
    </main>

    <?php include 'includes/confirm_modal.php'; ?>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
