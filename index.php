<?php
/**
 * index.php  (NEW)  - LOGIN PAGE and landing page of the application.
 * Accepts username OR email + password. Logged-in users are sent to the dashboard.
 */
require_once 'includes/auth.php';

redirect_if_logged_in();   // instructor rule: an active session cannot reopen the login page

// A real bcrypt hash used only to keep response time similar when the account does not exist.
const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim(post_string('identifier'));
    $password   = post_string('password');

    if (!csrf_is_valid()) {
        $errors['form'] = 'Your form expired. Please try again.';
    } else {
        if ($identifier === '') {
            $errors['identifier'] = 'Enter your username or email.';
        }
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }
    }

    if (empty($errors)) {
        // Prepared statement: the user input is never glued into the SQL text.
        $account = db_select_one(
            $conn,
            'SELECT user_id, username, password_hash, role FROM users WHERE username = ? OR email = ? LIMIT 1',
            'ss',
            [$identifier, $identifier]
        );

        $hash = ($account !== null) ? $account['password_hash'] : DUMMY_HASH;
        $password_ok = password_verify($password, $hash);

        if ($account !== null && $password_ok) {
            login_user($account);                 // regenerates the session ID
            redirect('dashboard.php');
        }
        // Same message for "no such user" and "wrong password".
        $errors['form'] = 'Invalid username/email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In - Gridlock Rescue</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">
    <header class="auth-topbar">
        <div class="container-fluid px-lg-4 d-flex align-items-center gap-2">
            <span class="brand-logo">GR</span>
            <span class="auth-topbar-title">GRIDLOCK RESCUE</span>
        </div>
    </header>

    <main class="container auth-main" id="page-content">
        <div class="row g-4 align-items-stretch justify-content-center">
            <div class="col-lg-6 d-none d-lg-flex">
                <section class="brand-panel" id="brand-panel">
                    <?php render_board_preview(['#######', '#@$.--#', '#######'], 36); ?>
                    <h1 class="brand-title">GRIDLOCK RESCUE</h1>
                    <p class="brand-tagline">Push the boxes. Free the grid.</p>
                    <ul class="brand-list">
                        <li>Plan every move</li>
                        <li>Beat your best score</li>
                    </ul>
                </section>
            </div>

            <div class="col-lg-6 col-xl-5">
                <section class="gr-card auth-card" id="login-card">
                    <h2 class="auth-heading">Log In</h2>
                    <?php show_flash(); ?>
                    <?php if (isset($errors['form'])): ?>
                    <div class="alert alert-danger" role="alert"><?php echo e($errors['form']); ?></div>
                    <?php endif; ?>

                    <form method="post" action="index.php" id="login-form" novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="identifier" class="form-label">Username or Email</label>
                            <input type="text" class="form-control<?php echo isset($errors['identifier']) ? ' is-invalid' : ''; ?>"
                                   id="identifier" name="identifier" value="<?php echo e($identifier); ?>"
                                   autocomplete="username" maxlength="100" required autofocus>
                            <div class="invalid-feedback"><?php echo e($errors['identifier'] ?? ''); ?></div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control<?php echo isset($errors['password']) ? ' is-invalid' : ''; ?>"
                                   id="password" name="password" autocomplete="current-password" required>
                            <div class="invalid-feedback"><?php echo e($errors['password'] ?? ''); ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="login-button">LOG IN</button>
                    </form>
                    <p class="text-center mt-3 mb-0">New player? <a href="register.php">Register here</a></p>
                </section>
            </div>
        </div>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
