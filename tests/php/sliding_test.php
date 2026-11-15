<?php
/**
 * tests/php/sliding_test.php  (NEW)  - command-line test of includes/sliding.php (the PHP rules).
 *   php tests/php/sliding_test.php
 * It needs no database. It loads tests/fixtures/*.json, which were produced and double-checked with the JavaScript engine,
 * so the PHP validator and the browser engine are held to exactly the same answers.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/sliding.php';

$passed = 0;
$failed = 0;
function check($name, $ok, $detail = '')
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
    } else {
        $failed++;
        echo 'FAIL: ' . $name . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
    }
}

$levels = json_decode(file_get_contents(__DIR__ . '/../fixtures/sliding_levels.json'), true);
$cases = json_decode(file_get_contents(__DIR__ . '/../fixtures/sliding_php_cases.json'), true);

foreach ($levels as $level) {
    $label = 'Level ' . $level['id'] . ' (' . $level['name'] . ')';
    $parsed = sliding_parse_rows($level['rows']);
    check($label . ': parses without errors', empty($parsed['errors']), implode(' ', $parsed['errors']));

    $solution = sliding_solve($parsed['vehicles']);
    check($label . ': PHP solver finds a solution', $solution['status'] === 'solved');
    check($label . ': PHP optimal length equals ' . $level['best'], count($solution['moves']) === $level['best'], 'got ' . count($solution['moves']));
    check($label . ': solver stays inside its limits (' . $solution['states'] . ' states)', $solution['states'] < SLIDING_MAX_STATES);

    $replay = sliding_replay($level['rows'], $level['solution']);
    check($label . ': stored solution replays as valid', $replay['ok'] === true && $replay['moves'] === $level['best'], $replay['error']);
    $own = sliding_replay($level['rows'], $solution['moves']);
    check($label . ': the PHP solver\'s own solution replays as valid', $own['ok'] === true && $own['moves'] === $level['best']);

    $analysis = analyse_sliding_layout(implode("\n", $level['rows']));
    check($label . ': analyse_sliding_layout accepts it with the optimal count', empty($analysis['errors']) && $analysis['optimal'] === $level['best']);
    $crlf = analyse_sliding_layout(implode("\r\n", $level['rows']) . "\r\n");
    check($label . ': Windows line endings and a trailing newline are accepted', empty($crlf['errors']) && $crlf['layout'] === implode("\n", $level['rows']));
}

foreach ($cases['replayRejects'] as $case) {
    $level = null;
    foreach ($levels as $l) {
        if ($l['id'] === $case['level']) {
            $level = $l;
        }
    }
    $replay = sliding_replay($level['rows'], $case['slides']);
    check('Level ' . $case['level'] . ' replay rejected: ' . $case['name'], $replay['ok'] === false);
}

foreach ($cases['invalidLayouts'] as $case) {
    $analysis = analyse_sliding_layout($case['text']);
    check('Invalid layout rejected: ' . $case['name'], !empty($analysis['errors']));
}

// the score is the number of slides counted by the server, never a number from the browser
$first = $levels[0];
$padded = sliding_replay($first['rows'], $first['solution']);
check('moves are counted by the replay', $padded['moves'] === count($first['solution']));

$layout_text = implode("\n", $first['rows']);
$layout_crlf = implode("\r\n", $first['rows']) . "\r\n";
check('layout fingerprint is stable across Windows and Unix line endings', sliding_layout_fingerprint($layout_text) === sliding_layout_fingerprint($layout_crlf));
$changed_rows = $first['rows'];
$changed_rows[0] = ($changed_rows[0][0] === '.' ? 'X' : '.') . substr($changed_rows[0], 1);
check('layout fingerprint changes when the board content changes', sliding_layout_fingerprint($layout_text) !== sliding_layout_fingerprint(implode("\n", $changed_rows)));

echo ($failed === 0 ? 'ALL PASSED' : 'FAILURES') . ': ' . $passed . ' checks passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
