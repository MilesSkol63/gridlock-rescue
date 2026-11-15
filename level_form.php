<?php
/**
 * level_form.php  (REWRITTEN for the sliding-block game)  - ADMINISTRATOR-ONLY create / edit a level.
 *   level_form.php        -> create a new level
 *   level_form.php?id=4   -> edit level 4
 * The visual editor (assets/js/level-editor.js) writes the board as 6 rows of 6 characters into the hidden field
 * "layout". Here the server parses that text again, checks the geometry, SOLVES the puzzle with the PHP solver
 * (includes/sliding.php) and only then saves. The optimal number of moves is calculated, never trusted.
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
    $existing = get_level($conn, $editing_id);          // only sliding-block levels can be edited
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
        'layout'     => implode("\n", sliding_rows_from_text($existing['layout'])),
    ];
    $progress_count = (int) db_select_one($conn, 'SELECT COUNT(*) AS total FROM user_progress WHERE level_id = ?', 'i', [$editing_id])['total'];
} else {
    $next_order = (int) db_select_one($conn, "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM levels WHERE game_type = 'sliding'")['next_order'];
    $form = [
        'title' => '', 'hint' => '', 'difficulty' => 'easy', 'sort_order' => (string) $next_order, 'par_moves' => '',
        'layout' => "......\n......\nRR....\n......\n......\n......",
    ];
    $progress_count = 0;
}
$errors = [];
$layout_errors = [];
$optimal_found = null;
$reset_progress = false;

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
    if (!in_array($form['difficulty'], ['easy', 'medium', 'hard', 'expert'], true)) {
        $errors['difficulty'] = 'Choose Easy, Medium, Hard or Expert.';
    }
    $order = filter_var($form['sort_order'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
    if ($order === false) {
        $errors['sort_order'] = 'Order must be a whole number from 1 to 9999.';
    }
    if (!isset($errors['title'])) {
        $same = db_select_one(
            $conn,
            "SELECT level_id FROM levels WHERE title = ? AND game_type = 'sliding' AND level_id <> ?",
            'si',
            [$form['title'], (int) $editing_id]
        );
        if ($same !== null) {
            $errors['title'] = 'Another level already uses this title.';
        }
    }

    // ---- validate the board: geometry, rescue vehicle, exit path, solvability (the real check) ----
    $analysis = analyse_sliding_layout($form['layout']);
    $layout_errors = $analysis['errors'];
    $optimal_found = $analysis['optimal'];

    // ---- par moves: empty = use the optimal count; otherwise it cannot be lower than the optimal count ----
    $par = null;
    if ($optimal_found !== null) {
        if ($form['par_moves'] === '') {
            $par = $optimal_found;
        } else {
            $par = filter_var($form['par_moves'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
            if ($par === false) {
                $errors['par_moves'] = 'Par moves must be a whole number from 1 to 9999, or empty.';
                $par = null;
            } elseif ($par < $optimal_found) {
                $errors['par_moves'] = 'Par cannot be lower than the optimal solution (' . $optimal_found . ' moves).';
                $par = null;
            }
        }
    }

    if (empty($errors) && empty($layout_errors)) {
        $hint_value = ($form['hint'] === '') ? null : $form['hint'];

        try {
            mysqli_begin_transaction($conn);
            if ($existing === null) {
                db_execute(
                    $conn,
                    "INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
                     VALUES ('sliding', ?, ?, ?, ?, 6, 6, ?, ?)",
                    'sssisi',
                    [$form['title'], $hint_value, $form['difficulty'], $order, $analysis['layout'], $par]
                );
                $message = 'Level "' . $form['title'] . '" was created (optimal solution: ' . $optimal_found . ' moves).';
                mysqli_commit($conn);
                set_flash('success', $message);
                redirect('levels.php');
            } else {
                // Lock the level first. Score submissions also lock this row, so a
                // concurrent completion cannot slip in between the progress check
                // and a board update.
                $locked_level = db_select_one(
                    $conn,
                    "SELECT layout FROM levels WHERE level_id = ? AND game_type = 'sliding' FOR UPDATE",
                    'i',
                    [$editing_id]
                );

                if ($locked_level === null) {
                    mysqli_rollback($conn);
                    $errors['form'] = 'This level no longer exists. Return to the level list and try again.';
                } else {
                    // Lock the matching progress rows too. New score inserts must
                    // pass the level foreign key and therefore wait for this level
                    // row lock; existing score updates wait for these row locks.
                    $locked_progress = db_select_all(
                        $conn,
                        'SELECT progress_id FROM user_progress WHERE level_id = ? FOR UPDATE',
                        'i',
                        [$editing_id]
                    );
                    $progress_count = count($locked_progress);
                    $current_layout = implode("\n", sliding_rows_from_text($locked_level['layout']));
                    $layout_changed = ($analysis['layout'] !== $current_layout);

                    if (sliding_layout_edit_requires_progress_confirmation($layout_changed, $progress_count, $reset_progress)) {
                        mysqli_rollback($conn);
                        $errors['progress_confirmation'] = 'The board layout has changed and this level has ' . $progress_count . ' saved score(s). Confirm that these scores should be cleared before saving the new board.';
                    } else {
                        db_execute(
                            $conn,
                            "UPDATE levels SET title = ?, hint = ?, difficulty = ?, sort_order = ?, grid_width = 6, grid_height = 6, layout = ?, par_moves = ?
                             WHERE level_id = ? AND game_type = 'sliding'",
                            'sssisii',
                            [$form['title'], $hint_value, $form['difficulty'], $order, $analysis['layout'], $par, $editing_id]
                        );
                        $message = 'Level "' . $form['title'] . '" was updated (optimal solution: ' . $optimal_found . ' moves).';

                        if ($layout_changed && $progress_count > 0) {
                            // The board and its now-obsolete progress are changed atomically.
                            $deleted = db_execute($conn, 'DELETE FROM user_progress WHERE level_id = ?', 'i', [$editing_id]);
                            $message .= ' ' . (int) $deleted['affected'] . ' saved score(s) for this level were cleared.';
                        }

                        mysqli_commit($conn);
                        set_flash('success', $message);
                        redirect('levels.php');
                    }
                }
            }
        } catch (mysqli_sql_exception $ex) {
            // A rollback is safe here because the transaction was started above.
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
<?php if (isset($errors['progress_confirmation'])): ?>
        <div class="alert alert-warning" role="alert" id="progress-confirmation-error"><?php echo e($errors['progress_confirmation']); ?></div>
<?php endif; ?>
<?php if (!empty($layout_errors)): ?>
        <div class="alert alert-danger" role="alert" id="layout-errors">
            <strong>The board cannot be saved:</strong>
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
                <div class="col-lg-5 order-lg-2">
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
    <?php foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'expert' => 'Expert'] as $value => $label): ?>
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
                                <label for="par_moves" class="form-label">Par moves</label>
                                <input type="number" class="form-control<?php echo isset($errors['par_moves']) ? ' is-invalid' : ''; ?>" id="par_moves" name="par_moves" value="<?php echo e($form['par_moves']); ?>" min="1" max="9999" placeholder="auto">
                                <div class="form-text">Empty = optimal</div>
                                <div class="invalid-feedback"><?php echo e($errors['par_moves'] ?? ''); ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="hint" class="form-label">Hint / description <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control<?php echo isset($errors['hint']) ? ' is-invalid' : ''; ?>" id="hint" name="hint" value="<?php echo e($form['hint']); ?>" maxlength="255">
                            <div class="invalid-feedback"><?php echo e($errors['hint'] ?? ''); ?></div>
                        </div>
<?php if ($existing !== null && $progress_count > 0): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="reset_progress" name="reset_progress"<?php echo $reset_progress ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="reset_progress">
                                I confirm that the <?php echo $progress_count; ?> saved score(s) for this level may be cleared if I change its board layout.
                                <span class="text-muted d-block small">Required only when the actual vehicle arrangement changes. Metadata-only edits keep all scores.</span>
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

                <div class="col-lg-7 order-lg-1">
                    <section class="gr-card" id="level-editor">
                        <h2 class="section-title">Board editor</h2>
                        <p class="small text-muted">Pick an orientation and a length, then click an empty square to add a vehicle. Click a vehicle to select it and move, rotate, resize or delete it. The red vehicle R is the rescue vehicle: it stays in the exit row (row 3) and leaves through the exit on the right.</p>

                        <div class="sl-editor-tools">
                            <span class="small fw-semibold">Add:</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Orientation">
                                <button type="button" class="btn btn-outline-navy" id="tool-h" aria-pressed="true">&harr; Horizontal</button>
                                <button type="button" class="btn btn-outline-navy" id="tool-v" aria-pressed="false">&varr; Vertical</button>
                            </div>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Length">
                                <button type="button" class="btn btn-outline-navy" id="tool-len2" aria-pressed="true">Length 2</button>
                                <button type="button" class="btn btn-outline-navy" id="tool-len3" aria-pressed="false">Length 3</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="btn-clear">Clear board</button>
                        </div>

                        <div class="sl-board-area sl-editor-area">
                            <div class="sl-frame">
                                <div class="sl-board sl-editor-board" id="editor-board" aria-label="Level board editor"></div>
                                <div class="sl-exit" aria-hidden="true"><span class="sl-exit-arrow">&#10148;</span><span class="sl-exit-text">EXIT</span></div>
                                <div class="sl-exit-gap" aria-hidden="true"></div>
                            </div>
                        </div>

                        <div class="sl-selected">
                            <div class="small fw-semibold" id="sel-info" aria-live="polite"></div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-left" aria-label="Move left">&larr;</button>
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-up" aria-label="Move up">&uarr;</button>
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-down" aria-label="Move down">&darr;</button>
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-right" aria-label="Move right">&rarr;</button>
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-rotate">Rotate</button>
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-len2">Length 2</button>
                                <button type="button" class="btn btn-sm btn-outline-navy" id="sel-len3">Length 3</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="sel-delete">Delete</button>
                            </div>
                        </div>
                        <p class="small text-danger mt-2 mb-0" id="editor-status" role="status"></p>

                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-outline-navy" id="btn-check">Check puzzle</button>
                            <span class="editor-check" id="check-result" role="status"></span>
                        </div>
                        <input type="hidden" id="layout" name="layout" value="<?php echo e($form['layout']); ?>">
                    </section>
                </div>
            </div>
        </form>
    </main>

    <footer class="site-footer">Gridlock Rescue - Endterm Project</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sliding-engine.js"></script>
    <script src="assets/js/level-editor.js"></script>
</body>
</html>
