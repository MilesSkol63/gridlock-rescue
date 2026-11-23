<?php
/**
 * logout.php  (NEW)
 * POST-only logout. Checks the CSRF token, destroys the session and returns to login.
 * No HTML is rendered here, so there is no document structure to maintain.
 */
require_once __DIR__ . '/../includes/auth.php';

// Opening logout.php by typing the URL (GET) does nothing harmful.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(empty($_SESSION['user_id']) ? 'index.php' : 'dashboard.php');
}

verify_csrf();            // wrong or missing token -> back to the dashboard / login
destroy_session();        // clears data, expires the cookie, removes the server-side session
start_fresh_session();    // brand-new session ID, only to carry the message below
set_flash('success', 'You have been logged out.');
redirect('index.php');
