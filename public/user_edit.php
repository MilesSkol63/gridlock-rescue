<?php
/**
 * user_edit.php  (NEW)  - ADMINISTRATOR-ONLY: edit a REGULAR player's username, email and (optionally) password.
 * The role can never be changed here and administrator accounts cannot be opened.
 */
require_once __DIR__ . '/../includes/auth.php';

/** @var PDO $conn */

$account = require_admin();

$target_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($target_id === false || $target_id < 1) {
    set_flash('warning', 'Choose a player from the list.');
    redirect('users.php');
}
$target = db_select_one($conn, 'SELECT user_id, username, email, role, created_at FROM users WHERE user_id = ?', 'i', [$target_id]);
if ($target === null || $target['role'] !== 'user') {
    set_flash('warning', 'Only existing player accounts can be edited here.');
    redirect('users.php');
}

$errors = [];
$username = $target['username'];
$email = $target['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim(post_string('username'));
    $email    = strtolower(trim(post_string('email')));
    $new_password = post_string('new_password');

    $message = validate_username($username);
    if ($message !== '') { $errors['username'] = $message; }
    $message = validate_email($email);
    if ($message !== '') { $errors['email'] = $message; }
    if ($new_password !== '') {
        $message = validate_password($new_password);
        if ($message !== '') { $errors['new_password'] = $message; }
    }
    if (!isset($errors['username']) && !isset($errors['email'])) {
        $errors = array_merge($errors, check_account_duplicates($conn, $username, $email, $target_id));
    }

    if (empty($errors)) {
        try {
            if ($new_password !== '') {
                db_execute(
                    $conn,
                    "UPDATE users SET username = ?, email = ?, password_hash = ? WHERE user_id = ? AND role = 'user'",
                    'sssi',
                    [$username, $email, password_hash($new_password, PASSWORD_DEFAULT), $target_id]
                );
            } else {
                db_execute(
                    $conn,
                    "UPDATE users SET username = ?, email = ? WHERE user_id = ? AND role = 'user'",
                    'ssi',
                    [$username, $email, $target_id]
                );
            }
            set_flash('success', 'Player "' . $username . '" was updated.');
            redirect('users.php');
        } catch (PDOException $ex) {
            error_log('Gridlock Rescue: user_edit failed: ' . ('SQLSTATE ' . $ex->getCode()));
            $errors['form'] = is_unique_violation($ex)
                ? 'That username or email is already registered.'
                : 'The account could not be updated. Please try again.';
        }
    }
}

$active_page = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User - Gridlock Rescue</title>
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
            <h1>Edit Player</h1>
            <span class="badge badge-role-player">PLAYER</span>
            <a class="ms-auto" href="users.php">&lsaquo; Back to Users</a>
        </div>

        <section class="gr-card form-card" id="edit-user-card">
<?php if (isset($errors['form'])): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($errors['form']); ?></div>
<?php endif; ?>
            <p class="text-muted small">Account #<?php echo (int) $target['user_id']; ?> &middot; joined <?php echo e(substr($target['created_at'], 0, 10)); ?> &middot; the role cannot be changed here.</p>
            <form method="post" action="user_edit.php?id=<?php echo (int) $target['user_id']; ?>" novalidate>
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control<?php echo isset($errors['username']) ? ' is-invalid' : ''; ?>" id="username" name="username" value="<?php echo e($username); ?>" maxlength="20" required>
                    <div class="invalid-feedback"><?php echo e($errors['username'] ?? ''); ?></div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>" id="email" name="email" value="<?php echo e($email); ?>" maxlength="100" required>
                    <div class="invalid-feedback"><?php echo e($errors['email'] ?? ''); ?></div>
                </div>
                <div class="mb-4">
                    <label for="new_password" class="form-label">New Password (optional)</label>
                    <input type="password" class="form-control<?php echo isset($errors['new_password']) ? ' is-invalid' : ''; ?>" id="new_password" name="new_password" autocomplete="new-password">
                    <div class="form-text">Leave empty to keep the current password.</div>
                    <div class="invalid-feedback"><?php echo e($errors['new_password'] ?? ''); ?></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a class="btn btn-outline-navy" href="users.php">Cancel</a>
                </div>
            </form>
        </section>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
