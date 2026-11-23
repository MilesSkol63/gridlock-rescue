<?php
/**
 * register.php  (NEW)  - public registration for REGULAR USERS only.
 * The role is written by the server ('user' inside the SQL) and is never read from the form.
 */
require_once __DIR__ . '/../includes/auth.php';


/** @var PDO $conn */

redirect_if_logged_in();   // authenticated users cannot open the registration page

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(post_string('username'));
    $email    = strtolower(trim(post_string('email')));
    $password = post_string('password');
    $confirm  = post_string('confirm_password');

    if (!csrf_is_valid()) {
        $errors['form'] = 'Your form expired. Please try again.';
    }

    $message = validate_username($username);
    if ($message !== '') { $errors['username'] = $message; }

    $message = validate_email($email);
    if ($message !== '') { $errors['email'] = $message; }

    $message = validate_password($password);
    if ($message !== '') { $errors['password'] = $message; }

    if (!isset($errors['password']) && $password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Duplicate check (only when the format is valid).
    if (!isset($errors['username']) && !isset($errors['email'])) {
        $existing = db_select_all(
            $conn,
            'SELECT username, email FROM users WHERE lower(username) = lower(?) OR lower(email) = lower(?)',
            'ss',
            [$username, $email]
        );
        foreach ($existing as $row) {
            if (strcasecmp($row['username'], $username) === 0) {
                $errors['username'] = 'That username is already taken.';
            }
            if (strcasecmp($row['email'], $email) === 0) {
                $errors['email'] = 'That email is already registered.';
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            // 'user' is written in the SQL text itself: the browser cannot influence the role.
            db_execute(
                $conn,
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')",
                'sss',
                [$username, $email, $hash]
            );
            set_flash('success', 'Account created. Please log in.');
            redirect('index.php');
        } catch (PDOException $ex) {
            if (is_unique_violation($ex)) {          // duplicate key: someone registered at the same moment
                $errors['form'] = 'That username or email is already registered.';
            } else {
                error_log('Gridlock Rescue: registration failed: ' . ('SQLSTATE ' . $ex->getCode()));
                $errors['form'] = 'Sorry, the account could not be created. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Gridlock Rescue</title>
    <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body" id="auth-header">
    <header class="auth-topbar">
        <div class="container-fluid px-lg-4 d-flex align-items-center gap-2">
            <span class="brand-logo">GR</span>
            <span class="auth-topbar-title">GRIDLOCK RESCUE</span>
        </div>
    </header>

    <main class="container auth-main" id="page-content">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <section class="gr-card auth-card" id="register-card">
                    <h1 class="auth-heading">Create Your Account</h1>
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo e($errors['form'] ?? 'Please fix the errors below.'); ?>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="register.php" id="register-form" novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control<?php echo isset($errors['username']) ? ' is-invalid' : ''; ?>"
                                   id="username" name="username" value="<?php echo e($username); ?>"
                                   autocomplete="username" minlength="3" maxlength="20" required>
                            <div class="form-text">3-20 letters, numbers or _</div>
                            <div class="invalid-feedback"><?php echo e($errors['username'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control<?php echo isset($errors['email']) ? ' is-invalid' : ''; ?>"
                                   id="email" name="email" value="<?php echo e($email); ?>"
                                   autocomplete="email" maxlength="100" required>
                            <div class="invalid-feedback"><?php echo e($errors['email'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control<?php echo isset($errors['password']) ? ' is-invalid' : ''; ?>"
                                   id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <div class="form-text">At least 8 characters, with a letter and a number</div>
                            <div class="invalid-feedback"><?php echo e($errors['password'] ?? ''); ?></div>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control<?php echo isset($errors['confirm_password']) ? ' is-invalid' : ''; ?>"
                                   id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?php echo e($errors['confirm_password'] ?? ''); ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="register-button">CREATE ACCOUNT</button>
                    </form>
                    <p class="text-center mt-3 mb-0">Already registered? <a href="index.php">Log in</a></p>
                </section>
            </div>
        </div>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
