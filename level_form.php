<?php
/**
 * level_form.php  (NEW)  - protected placeholder shell (ADMINISTRATORS ONLY).
 * Keeps the shared layout, navigation and permission check; the real feature comes later.
 */
require_once 'includes/auth.php';

require_admin();
$active_page = 'levels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Level Form - Gridlock Rescue</title>
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
            <h1>Level Form</h1>
            <span class="badge badge-stage">Deliverable 4</span>
        </div>

        <section class="gr-card placeholder-card" id="placeholder">
            <h2 class="section-title">This page is a placeholder</h2>
            <p class="mb-2">The level create/edit form is part of Deliverable 4. Only administrators can open this page.</p>
            <ul class="mb-3">
                <li>Create a level or edit an existing one</li>
                <li>Layout validation with a read-only preview</li>
            </ul>
            <a class="btn btn-primary align-self-start" href="dashboard.php">Back to Dashboard</a>
        </section>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
