<?php
/**
 * leaderboard.php  (NEW)  - protected placeholder shell (any logged-in user).
 * Keeps the shared layout, navigation and permission check; the real feature comes later.
 */
require_once 'includes/auth.php';

require_login();
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
    <?php include 'includes/navbar.php'; ?>

    <main class="container page-main" id="page-content">
        <?php show_flash(); ?>
        <div class="page-header">
            <h1>Leaderboard</h1>
            <span class="badge badge-stage">Deliverable 3</span>
        </div>

        <section class="gr-card placeholder-card" id="placeholder">
            <h2 class="section-title">This page is a placeholder</h2>
            <p class="mb-2">The leaderboard is built in Deliverable 3, once players can complete levels and save scores.</p>
            <ul class="mb-3">
                <li>Overall ranking: most levels completed, then fewest total moves</li>
                <li>Per-level rankings by best moves</li>
            </ul>
            <a class="btn btn-primary align-self-start" href="dashboard.php">Back to Dashboard</a>
        </section>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
