<?php
/**
 * includes/navbar.php  (NEW)
 * The ONE shared navigation menu. Pages include it right after <body>:
 *     $active_page = 'dashboard';
 *     include 'includes/navbar.php';
 * Links and badges depend on $_SESSION['role'] (set/refreshed by require_login()).
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

$nav_is_admin = (($_SESSION['role'] ?? '') === 'admin');
$nav_active   = $active_page ?? '';

$nav_all = [
    'dashboard'   => ['Dashboard',   'dashboard.php'],
    'levels'      => ['Levels',      'levels.php'],
    'users'       => ['Users',       'users.php'],
    'progress'    => ['Progress',    'progress.php'],
    'leaderboard' => ['Leaderboard', 'leaderboard.php'],
    'profile'     => ['Profile',     'profile.php'],
];
$nav_keys = $nav_is_admin
    ? ['dashboard', 'levels', 'users', 'progress', 'leaderboard', 'profile']   // administrator
    : ['dashboard', 'levels', 'leaderboard', 'progress', 'profile'];           // regular user
?>
    <nav class="navbar navbar-expand-lg navbar-gr" id="main-nav">
        <div class="container-fluid px-lg-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
                <span class="brand-logo">GR</span>
                <span>Gridlock Rescue</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                    aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
<?php foreach ($nav_keys as $key): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
                           href="<?php echo e($nav_all[$key][1]); ?>"<?php echo ($nav_active === $key) ? ' aria-current="page"' : ''; ?>><?php echo e($nav_all[$key][0]); ?></a>
                    </li>
<?php endforeach; ?>
                </ul>
                <div class="nav-user d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                    <span class="nav-username"><?php echo e($_SESSION['username'] ?? ''); ?></span>
                    <span class="badge <?php echo $nav_is_admin ? 'badge-role-admin' : 'badge-role-player'; ?>"><?php echo $nav_is_admin ? 'ADMIN' : 'PLAYER'; ?></span>
                    <form method="post" action="logout.php" class="m-0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
