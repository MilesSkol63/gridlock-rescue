<?php
/**
 * level_form.php  (REPLACES the placeholder)  - ADMINISTRATOR-ONLY create / edit a level.
 *   level_form.php        -> create a new level
 *   level_form.php?id=4   -> edit level 4
 * Width, height and box count are CALCULATED from the layout (analyse_level_layout in functions.php).
 */
require_once 'includes/auth.php';

/** @var mysqli $conn */

$account = require_admin();

$editing_id = null;
$existing = null;
if (isset($_GET['id'])) {
    $editing_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($editing_id === false || $editing_id < 1) {
        set_flash('warning', 'Invalid level.');
        redirect('levels.php');
    }
    $existing = get_level($conn, $editing_id);
    if ($existing === null) {
        set_flash('warning', 'That level does not exist.');
        redirect('levels.php');
    }
}

// Starting values for the form
if ($existing !== null) {
    $form = [
        'title'      => $existing['title'],
        'hint'       => (string) $existing['hint'],
        'difficulty' => $existing['difficulty'],
        'sort_order' => (string) $existing['sort_order'],
        'par_moves'  => ($existing['par_moves'] === null) ? '' : (string) $existing['par_moves'],
        'layout'     => str_replace(["\r\n", "\r"], "\n", $existing['layout']),
    ];
    $progress_count = (int) db_select_one($conn, 'SELECT COUNT(*) AS total FROM user_progress WHERE level_id = ?', 'i', [$editing_id])['total'];
} else {
    $next_order = (int) db_select_one($conn, 'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM levels')['next_order'];
    $form = [
        'title' => '', 'hint' => '', 'difficulty' => 'easy', 'sort_order' => (string) $next_order, 'par_moves' => '',
        'layout' => "#####\n#@$.#\n#####",
    ];
    $progress_count = 0;
}
$errors = [];
$layout_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $form['title']      = trim(post_string('title'));
    $form['hint']       = trim(post_string('hint'));
    $form['difficulty'] = post_string('difficulty');
    $form['sort_order'] = trim(post_string('sort_order'));
    $form['par_moves']  = trim(post_string('par_moves'));
    $form['layout']     = post_string('layout');
    $reset_progress     = (post_string('reset_progress') === '1');

    // ---- validate the simple fields ----
    if ($form['title'] === '') {
        $errors['title'] = 'Title is required.';
    } elseif (strlen($form['title']) > 60) {
        $errors['title'] = 'Title must be 60 characters or fewer.';
    } elseif (!preg_match('/^[\p{L}\p{N} \-\'.,!?:]+$/u', $form['title'])) {
        $errors['title'] = 'Use letters, numbers, spaces and basic punctuation only.';
    }
    if (strlen($form['hint']) > 255) {
        $errors['hint'] = 'The hint must be 255 characters or fewer.';
    }
    if (!in_array($form['difficulty'], ['easy', 'medium', 'hard'], true)) {
        $errors['difficulty'] = 'Choose Easy, Medium or Hard.';
    }
    $order = filter_var($form['sort_order'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
    if ($order === false) {
        $errors['sort_order'] = 'Order must be a whole number from 1 to 9999.';
    }
    $par = null;
    if ($form['par_moves'] !== '') {
        $par = filter_var($form['par_moves'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
        if ($par === false) {
            $errors['par_moves'] = 'Par moves must be a whole number from 1 to 9999, or empty.';
            $par = null;
        }
    }
    if (!isset($errors['title'])) {
        $same = db_select_one(
            $conn,
            'SELECT level_id FROM levels WHERE title = ? AND level_id <> ?',
            'si',
            [$form['title'], (int) $editing_id]
        );
        if ($same !== null) {
            $errors['title'] = 'Another level already uses this title.';
        }
    }

    // ---- validate the layout (the browser preview is only a hint; this is the real check) ----
    $analysis = analyse_level_layout($form['layout']);
    $layout_errors = $analysis['errors'];

    if (empty($errors) && empty($layout_errors)) {
        $layout_changed = ($existing !== null)
            && ($analysis['layout'] !== str_replace(["\r\n", "\r"], "\n", rtrim($existing['layout'])));
        $hint_value = ($form['hint'] === '') ? null : $form['hint'];

        try {
            mysqli_begin_transaction($conn);
            if ($existing === null) {
                db_execute(
                    $conn,
                    'INSERT INTO levels (title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'sssiiisi',
                    [$form['title'], $hint_value, $form['difficulty'], $order, $analysis['width'], $analysis['height'], $analysis['layout'], $par]
                );
                $message = 'Level "' . $form['title'] . '" was created.';
            } else {
                db_execute(
                    $conn,
                    'UPDATE levels SET title = ?, hint = ?, difficulty = ?, sort_order = ?, grid_width = ?, grid_height = ?, layout = ?, par_moves = ?
                     WHERE level_id = ?',
                    'sssiiisii',
                    [$form['title'], $hint_value, $form['difficulty'], $order, $analysis['width'], $analysis['height'], $analysis['layout'], $par, $editing_id]
                );
                $message = 'Level "' . $form['title'] . '" was updated.';
                if ($layout_changed && $reset_progress) {
                    // Both changes must succeed together, hence the transaction.
                    $deleted = db_execute($conn, 'DELETE FROM user_progress WHERE level_id = ?', 'i', [$editing_id]);
                    $message .= ' ' . (int) $deleted['affected'] . ' saved score(s) for this level were cleared.';
                }
            }
            mysqli_commit($conn);
            set_flash('success', $message);
            redirect('levels.php');
        } catch (mysqli_sql_exception $ex) {
            mysqli_rollback($conn);
            error_log('Gridlock Rescue: level save failed: ' . $ex->getMessage());
            $errors['form'] = 'The level could not be saved. Please try again.';
        }
    }
}

$active_page = 'levels';
$page_title = ($existing !== null) ? 'Edit Level' : 'Create Level';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($page_title); ?> - Gridlock Rescue</title>
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
            <h1><?php echo e($page_title); ?></h1>
<?php if ($existing !== null): ?>
            <span class="text-muted small">Level id <?php echo (int) $editing_id; ?></span>
<?php endif; ?>
            <a class="ms-auto" href="levels.php">&lsaquo; Back to Levels</a>
        </div>

<?php if (isset($errors['form'])): ?>
        <div class="alert alert-danger" role="alert"><?php echo e($errors['form']); ?></div>
<?php endif; ?>
<?php if (!empty($layout_errors)): ?>
        <div class="alert alert-danger" role="alert" id="layout-errors">
            <strong>The layout cannot be saved:</strong>
            <ul class="mb-0">
    <?php foreach ($layout_errors as $message): ?>
                <li><?php echo e($message); ?></li>
    <?php endforeach; ?>
            </ul>
        </div>
<?php endif; ?>

        <form method="post" action="level_form.php<?php echo ($existing !== null) ? '?id=' . (int) $editing_id : ''; ?>" id="level-form" novalidate>
            <?php echo csrf_field(); ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <section class="gr-card" id="level-fields">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control<?php echo isset($errors['title']) ? ' is-invalid' : ''; ?>" id="title" name="title" value="<?php echo e($form['title']); ?>" maxlength="60" required>
                            <div class="invalid-feedback"><?php echo e($errors['title'] ?? ''); ?></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-4">
                                <label for="difficulty" class="form-label">Difficulty</label>
                                <select class="form-select<?php echo isset($errors['difficulty']) ? ' is-invalid' : ''; ?>" id="difficulty" name="difficulty">
    <?php foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"<?php echo ($form['difficulty'] === $value) ? ' selected' : ''; ?>><?php echo $label; ?></option>
    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php echo e($errors['difficulty'] ?? ''); ?></div>
                            </div>
                            <div class="col-sm-4">
                                <label for="sort_order" class="form-label">Order</label>
                                <input type="number" class="form-control<?php echo isset($errors['sort_order']) ? ' is-invalid' : ''; ?>" id="sort_order" name="sort_order" value="<?php echo e($form['sort_order']); ?>" min="1" max="9999" required>
                                <div class="invalid-feedback"><?php echo e($errors['sort_order'] ?? ''); ?></div>
                            </div>
                            <div class="col-sm-4">
                                <label for="par_moves" class="form-label">Par moves <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="number" class="form-control<?php echo isset($errors['par_moves']) ? ' is-invalid' : ''; ?>" id="par_moves" name="par_moves" value="<?php echo e($form['par_moves']); ?>" min="1" max="9999">
                                <div class="invalid-feedback"><?php echo e($errors['par_moves'] ?? ''); ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="hint" class="form-label">Hint <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control<?php echo isset($errors['hint']) ? ' is-invalid' : ''; ?>" id="hint" name="hint" value="<?php echo e($form['hint']); ?>" maxlength="255">
                            <div class="invalid-feedback"><?php echo e($errors['hint'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="layout" class="form-label">Layout</label>
                            <textarea class="form-control layout-input<?php echo !empty($layout_errors) ? ' is-invalid' : ''; ?>" id="layout" name="layout" rows="12" wrap="off" spellcheck="false" required><?php echo e($form['layout']); ?></textarea>
                            <div class="form-text">One row per line, all rows the same length, <?php echo LEVEL_MIN_SIZE; ?>x<?php echo LEVEL_MIN_SIZE; ?> up to <?php echo LEVEL_MAX_SIZE; ?>x<?php echo LEVEL_MAX_SIZE; ?>.</div>
                        </div>
<?php if ($existing !== null && $progress_count > 0): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="reset_progress" name="reset_progress">
                            <label class="form-check-label" for="reset_progress">
                                Clear the <?php echo $progress_count; ?> saved score(s) for this level if I change the layout
                                <span class="text-muted d-block small">Old best-move counts may be impossible on a new layout.</span>
                            </label>
                        </div>
<?php endif; ?>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" id="save-level">Save Level</button>
                            <a class="btn btn-outline-navy" href="levels.php">Cancel</a>
<?php if ($existing !== null): ?>
                            <a class="btn btn-outline-navy ms-auto" href="play.php?id=<?php echo (int) $editing_id; ?>">Test Play (saved version)</a>
<?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-lg-5">
                    <aside class="gr-card" id="layout-help">
                        <h2 class="section-title">Legend</h2>
                        <pre class="legend-text mb-3"># wall      - floor
.  target    $  box
@  player    *  box on target
+  player on target</pre>
                        <h2 class="section-title">Rules</h2>
                        <ul class="small">
                            <li>Exactly one player (<code>@</code> or <code>+</code>)</li>
                            <li>At least one box; boxes and targets must match</li>
                            <li>The level must not already be solved</li>
                            <li>Surround the playable area with walls</li>
                        </ul>
                        <h2 class="section-title">Preview</h2>
                        <div class="preview-wrap"><div class="board" id="layout-preview" aria-label="Layout preview"></div></div>
                        <p class="small text-muted mt-2 mb-0" id="preview-note"></p>
                    </aside>
                </div>
            </div>
        </form>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/game.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
