<?php
/**
 * Tests the confirmation policy for administrator edits to sliding-block levels.
 * Run: php tests/php/level_edit_policy_test.php
 * No database connection is required.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/functions.php';

$passed = 0;
$failed = 0;
function policy_check($name, $actual, $expected)
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
    } else {
        $failed++;
        echo 'FAIL: ' . $name . ' -> expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . PHP_EOL;
    }
}

policy_check(
    'metadata-only edit with existing progress does not require confirmation',
    sliding_layout_edit_requires_progress_confirmation(false, 4, false),
    false
);
policy_check(
    'changed layout with no progress does not require confirmation',
    sliding_layout_edit_requires_progress_confirmation(true, 0, false),
    false
);
policy_check(
    'changed layout with progress and no confirmation is rejected',
    sliding_layout_edit_requires_progress_confirmation(true, 4, false),
    true
);
policy_check(
    'changed layout with progress and explicit confirmation is allowed',
    sliding_layout_edit_requires_progress_confirmation(true, 4, true),
    false
);
policy_check(
    'zero progress count is normalized safely',
    sliding_layout_edit_requires_progress_confirmation(true, '0', false),
    false
);

echo ($failed === 0 ? 'ALL PASSED' : 'FAILURES') . ': ' . $passed . ' checks passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
