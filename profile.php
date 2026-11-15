<?php
/**
 * profile.php  (REPLACES the placeholder)  - own account page for BOTH roles (same page).
 * The account is ALWAYS the one in the PHP session; no id is read from the URL or the form.
 *   update_profile : change username / email
 *   change_password: needs the current password
 *   reset_progress : players only (administrators have no progress)
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account  = require_login();
$is_admin = ($account['role'] === 'admin');
$my_id    = (int) $account['user_id'];

$me = db_select_one($conn, 'SELECT user_id, username, email, role, created_at FROM users WHERE user_id = ?', 'i', [$my_id]);

$profile_errors = [];
$password_errors = [];
$username = $me['username'];
$email = $me['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');

    if ($action === 'update_profile') {
        $username = trim(post_string('username'));
        $email    = strtolower(trim(post_string('email')));

        $message = validate_username($username);
        if ($message !== '') { $profile_errors['username'] = $message; }
        $message = validate_email($email);
        if ($message !== '') { $profile_errors['email'] = $message; }
        if (empty($profile_errors)) {
            $profile_errors = check_account_duplicates($conn, $username, $email, $my_id);   // other accounts only
        }

        if (empty($profile_errors)) {
            try {
                db_execute($conn, 'UPDATE users SET username = ?, email = ? WHERE user_id = ?', 'ssi', [$username, $email, $my_id]);
                $_SESSION['username'] = $username;                 // keep the navbar consistent
                set_flash('success', 'Your profile was updated.');
                redirect('profile.php');
            } catch (mysqli_sql_exception $ex) {
                error_log('Gridlock Rescue: profile update failed: ' . $ex->getMessage());
                $profile_errors['form'] = ($ex->getCode() === 1062)
                    ? 'That username or email is already registered.'
                    : 'Your profile could not be updated. Please try again.';
            }
        }

    } elseif ($action === 'change_password') {
        $current = post_string('current_password');
        $new     = post_string('new_password');
        $confirm = post_string('confirm_password');

        $row = db_select_one($conn, 'SELECT password_hash FROM users WHERE user_id = ?', 'i', [$my_id]);
        if ($current === '' || !password_verify($current, $row['password_hash'])) {
            $password_errors['current_password'] = 'Your current password is not correct.';
        }
        $message = validate_password($new);
        if ($message !== '') {
            $password_errors['new_password'] = $message;
        } elseif ($new !== $confirm) {
            $password_errors['confirm_password'] = 'The new passwords do not match.';
        }

        if (empty($password_errors)) {
            db_execute($conn, 'UPDATE users SET password_hash = ? WHERE user_id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), $my_id]);
            session_regenerate_id(true);                           // new session ID after a credential change
            set_flash('success', 'Your password was changed.');
            redirect('profile.php');
        }

    } elseif ($action === 'reset_progress') {
        if ($is_admin) {
            set_flash('danger', 'Administrators have no progress to reset.');
        } else {
            $removed = delete_sliding_progress($conn, $my_id);
            set_flash('success', 'Your progress was reset (' . $removed . ' record(s) removed).');
        }
        redirect('profile.php');
    } else {
        set_flash('danger', 'Unknown action.');
        redirect('profile.php');
    }
}

if (!$is_admin) {
    $rank = get_user_rank($conn, $my_id);
    $total_levels = count_sliding_levels($conn);
}
$active_page = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile - Gridlock Rescue</title>
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
            <h1>Profile</h1>
            <span class="badge <?php echo $is_admin ? 'badge-role-admin' : 'badge-role-player'; ?>"><?php echo $is_admin ? 'ADMIN' : 'PLAYER'; ?></span>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <section class="gr-card h-100" id="account-info">
                    <div class="avatar" aria-hidden="true"><?php echo e(strtoupper(substr($me['username'], 0, 2))); ?></div>
                    <h2 class="h5 mt-3 mb-1"><?php echo e($me['username']); ?></h2>
                    <p class="text-muted mb-1"><?php echo e($me['email']); ?></p>
                    <p class="text-muted small mb-0">Member since <?php echo e(substr($me['created_at'], 0, 10)); ?></p>
                </section>
            </div>
            <div class="col-lg-8">
                <section class="gr-card h-100" id="edit-profile">
                    <h2 class="section-title">Edit Profile</h2>
<?php if (isset($profile_errors['form'])): ?>
                    <div class="alert alert-danger" role="alert"><?php echo e($profile_errors['form']); ?></div>
<?php endif; ?>
                    <form method="post" action="profile.php" novalidate>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control<?php echo isset($profile_errors['username']) ? ' is-invalid' : ''; ?>" id="username" name="username" value="<?php echo e($username); ?>" maxlength="20" required>
                            <div class="invalid-feedback"><?php echo e($profile_errors['username'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control<?php echo isset($profile_errors['email']) ? ' is-invalid' : ''; ?>" id="email" name="email" value="<?php echo e($email); ?>" maxlength="100" required>
                            <div class="invalid-feedback"><?php echo e($profile_errors['email'] ?? ''); ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </section>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <section class="gr-card h-100" id="change-password">
                    <h2 class="section-title">Change Password</h2>
                    <form method="post" action="profile.php" novalidate>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password" class="form-control<?php echo isset($password_errors['current_password']) ? ' is-invalid' : ''; ?>" id="current_password" name="current_password" autocomplete="current-password" required>
                            <div class="invalid-feedback"><?php echo e($password_errors['current_password'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New password</label>
                            <input type="password" class="form-control<?php echo isset($password_errors['new_password']) ? ' is-invalid' : ''; ?>" id="new_password" name="new_password" autocomplete="new-password" required>
                            <div class="form-text">At least 8 characters, with a letter and a number</div>
                            <div class="invalid-feedback"><?php echo e($password_errors['new_password'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm new password</label>
                            <input type="password" class="form-control<?php echo isset($password_errors['confirm_password']) ? ' is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?php echo e($password_errors['confirm_password'] ?? ''); ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </section>
            </div>
<?php if (!$is_admin): ?>
            <div class="col-lg-6">
                <section class="gr-card h-100" id="progress-summary">
                    <h2 class="section-title">Progress Summary</h2>
    <?php if ($rank === null): ?>
                    <div class="empty-state mb-3">You have not completed a level yet.</div>
    <?php else: ?>
                    <p class="mb-1">Levels completed: <strong><?php echo (int) $rank['levels_done']; ?> / <?php echo $total_levels; ?></strong></p>
                    <p class="mb-1">Total best moves: <strong><?php echo (int) $rank['total_moves']; ?></strong></p>
                    <p class="mb-3">Leaderboard rank: <strong>#<?php echo (int) $rank['rank']; ?></strong></p>
    <?php endif; ?>
                    <a class="btn btn-outline-navy align-self-start" href="progress.php">View My Progress</a>
                </section>
            </div>
<?php endif; ?>
        </div>

<?php if (!$is_admin): ?>
        <section class="gr-card danger-zone" id="danger-zone">
            <h2 class="section-title">Danger Zone</h2>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="flex-grow-1">Deletes all my completed-level records and returns me to Level 1. My account stays.</span>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-modal"
                        data-action="reset_progress" data-id="0"
                        data-title="Reset all your progress?"
                        data-message="Every saved best score will be deleted and you will start again at Level 1. This cannot be undone."
                        data-button="Reset progress">Reset Progress</button>
            </div>
        </section>
<?php endif; ?>
    </main>

    <?php include 'includes/confirm_modal.php'; ?>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
