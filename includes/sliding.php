<?php
/**
 * includes/sliding.php  (NEW)  - PHP version of the sliding-block rules.
 * It follows assets/js/sliding-engine.js exactly (same level format, same movement rules) and is used to
 *   1. validate levels saved by administrators (format, geometry, solvability, optimal moves), and
 *   2. replay the slides a player submits, so a score can never be saved without a real solution.
 *
 * Level format: 6 rows of 6 characters. '.' = empty, 'R' = the rescue vehicle, any other capital letter = a vehicle.
 * Orientation and length are read from the shape. A vehicle position is its top (vertical) or left (horizontal) cell index.
 * The exit is the right edge of row 3 (index 2); only the rescue vehicle may leave, and it is gone at position 6.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

define('SLIDING_BOARD', 6);
define('SLIDING_EXIT_ROW', 2);
define('SLIDING_MAX_STATES', 200000);   // solver safety limits (the six bundled levels need fewer than 12,000 states)
define('SLIDING_MAX_SECONDS', 8);

/** Layout text (as typed or stored) -> array of row strings. */
function sliding_rows_from_text($text)
{
    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
    $lines = explode("\n", $text);
    foreach ($lines as $i => $line) {
        $lines[$i] = rtrim($line, " \t");
    }
    while (!empty($lines) && $lines[0] === '') {
        array_shift($lines);
    }
    while (!empty($lines) && end($lines) === '') {
        array_pop($lines);
    }
    return $lines;
}

/** Stable SHA-256 fingerprint for a normalized 6x6 level layout. */
function sliding_layout_fingerprint($layout)
{
    return hash('sha256', implode("\n", sliding_rows_from_text($layout)));
}

/**
 * Rows -> vehicles. Returns ['vehicles' => [id => vehicle], 'errors' => [messages]].
 * vehicle = ['id', 'orient' => 'H'|'V', 'length' => 2|3, 'row', 'col', 'rescue' => bool]
 */
function sliding_parse_rows($rows)
{
    $errors = [];
    if (count($rows) !== SLIDING_BOARD) {
        return ['vehicles' => [], 'errors' => ['The board needs exactly ' . SLIDING_BOARD . ' rows (found ' . count($rows) . ').']];
    }
    foreach ($rows as $i => $row) {
        if (strlen($row) !== SLIDING_BOARD) {
            $errors[] = 'Row ' . ($i + 1) . ' needs exactly ' . SLIDING_BOARD . ' characters.';
        }
    }
    if (!empty($errors)) {
        return ['vehicles' => [], 'errors' => $errors];
    }

    $cells = [];
    for ($r = 0; $r < SLIDING_BOARD; $r++) {
        for ($c = 0; $c < SLIDING_BOARD; $c++) {
            $ch = $rows[$r][$c];
            if ($ch === '.') {
                continue;
            }
            if (!preg_match('/^[A-Z]$/', $ch)) {
                $errors[] = 'Invalid character in row ' . ($r + 1) . '. Use "." for empty cells and capital letters for vehicles.';
                continue;
            }
            $cells[$ch][] = [$r, $c];
        }
    }
    ksort($cells);

    $vehicles = [];
    foreach ($cells as $id => $list) {
        $count = count($list);
        if ($count < 2 || $count > 3) {
            $errors[] = 'Vehicle ' . $id . ' must cover 2 or 3 cells (found ' . $count . ').';
            continue;
        }
        $same_row = true;
        $same_col = true;
        foreach ($list as $p) {
            if ($p[0] !== $list[0][0]) { $same_row = false; }
            if ($p[1] !== $list[0][1]) { $same_col = false; }
        }
        if (!$same_row && !$same_col) {
            $errors[] = 'Vehicle ' . $id . ' is not in a straight line.';
            continue;
        }
        $orient = $same_row ? 'H' : 'V';
        $index = $same_row ? 1 : 0;
        $coords = [];
        foreach ($list as $p) {
            $coords[] = $p[$index];
        }
        sort($coords);
        $gap = false;
        for ($i = 1; $i < $count; $i++) {
            if ($coords[$i] !== $coords[$i - 1] + 1) { $gap = true; }
        }
        if ($gap) {
            $errors[] = 'Vehicle ' . $id . ' has a gap.';
            continue;
        }
        $rows_used = [];
        $cols_used = [];
        foreach ($list as $p) {
            $rows_used[] = $p[0];
            $cols_used[] = $p[1];
        }
        $vehicles[$id] = [
            'id'     => $id,
            'orient' => $orient,
            'length' => $count,
            'row'    => min($rows_used),
            'col'    => min($cols_used),
            'rescue' => ($id === 'R'),
        ];
    }

    if (empty($errors)) {
        if (!isset($vehicles['R'])) {
            $errors[] = 'The board needs the red rescue vehicle (letter R).';
        } elseif ($vehicles['R']['orient'] !== 'H' || $vehicles['R']['row'] !== SLIDING_EXIT_ROW) {
            $errors[] = 'The rescue vehicle must be horizontal and in row 3 (the exit row).';
        }
    }
    return ['vehicles' => $vehicles, 'errors' => $errors];
}

/** The cells a vehicle covers, as [row, col] pairs (a rescue vehicle may reach col 6 or more while exiting). */
function sliding_cells($v)
{
    $out = [];
    for ($i = 0; $i < $v['length']; $i++) {
        $out[] = ($v['orient'] === 'H') ? [$v['row'], $v['col'] + $i] : [$v['row'] + $i, $v['col']];
    }
    return $out;
}

function sliding_pos($v)
{
    return ($v['orient'] === 'H') ? $v['col'] : $v['row'];
}

function sliding_with_pos($v, $p)
{
    if ($v['orient'] === 'H') {
        $v['col'] = $p;
    } else {
        $v['row'] = $p;
    }
    return $v;
}

/** Lowest and highest position vehicle $id can slide to. Returns ['min' => int, 'max' => int]. */
function sliding_range($vehicles, $id)
{
    $v = $vehicles[$id];
    $grid = array_fill(0, SLIDING_BOARD * SLIDING_BOARD, '');
    foreach ($vehicles as $other) {
        if ($other['id'] === $id) {
            continue;
        }
        foreach (sliding_cells($other) as $c) {
            $grid[$c[0] * SLIDING_BOARD + $c[1]] = $other['id'];
        }
    }
    $can_exit = ($v['rescue'] && $v['orient'] === 'H' && $v['row'] === SLIDING_EXIT_ROW);

    // Can the vehicle stand with its start at position $q?
    $free = function ($q) use ($v, $grid, $can_exit) {
        foreach (sliding_cells(sliding_with_pos($v, $q)) as $c) {
            if ($c[0] < 0 || $c[1] < 0 || $c[0] >= SLIDING_BOARD) {
                return false;
            }
            if ($c[1] >= SLIDING_BOARD) {
                if (!$can_exit) {
                    return false;           // only the rescue vehicle may leave, and only through the exit
                }
                continue;
            }
            if ($grid[$c[0] * SLIDING_BOARD + $c[1]] !== '') {
                return false;
            }
        }
        return true;
    };

    $p = sliding_pos($v);
    $lo = $p;
    $hi = $p;
    while ($free($lo - 1)) {
        $lo--;
    }
    $max_start = $can_exit ? SLIDING_BOARD : SLIDING_BOARD - $v['length'];
    while ($hi < $max_start && $free($hi + 1)) {
        $hi++;
    }
    return ['min' => $lo, 'max' => $hi];
}

/**
 * Replays a player's slides on a level and checks every one of them.
 * $slides = [[vehicleId, newPosition], ...]  (what assets/js/game.js sends: engine.getHistory()).
 * Returns ['ok' => bool, 'error' => string, 'moves' => int]. ok is true only if the rescue vehicle left the board.
 */
function sliding_replay($rows, $slides)
{
    $parsed = sliding_parse_rows($rows);
    if (!empty($parsed['errors'])) {
        return ['ok' => false, 'error' => 'The stored level is invalid.', 'moves' => 0];
    }
    $vehicles = $parsed['vehicles'];
    $exited = false;
    $count = 0;

    foreach ($slides as $slide) {
        if ($exited) {
            return ['ok' => false, 'error' => 'Moves continue after the rescue vehicle left the board.', 'moves' => $count];
        }
        if (!is_array($slide) || count($slide) !== 2 || !isset($slide[0], $slide[1]) || !is_string($slide[0]) || !is_int($slide[1])) {
            return ['ok' => false, 'error' => 'Malformed move.', 'moves' => $count];
        }
        $id = $slide[0];
        $to = $slide[1];
        if (!isset($vehicles[$id])) {
            return ['ok' => false, 'error' => 'Unknown vehicle in the move list.', 'moves' => $count];
        }
        $from = sliding_pos($vehicles[$id]);
        if ($to === $from) {
            return ['ok' => false, 'error' => 'A slide must change the position.', 'moves' => $count];
        }
        $range = sliding_range($vehicles, $id);
        if ($to < $range['min'] || $to > $range['max']) {
            return ['ok' => false, 'error' => 'Illegal slide in the move list.', 'moves' => $count];
        }
        $vehicles[$id] = sliding_with_pos($vehicles[$id], $to);
        $count++;
        if ($vehicles[$id]['rescue'] && $vehicles[$id]['col'] >= SLIDING_BOARD) {
            $exited = true;
        }
    }
    if (!$exited) {
        return ['ok' => false, 'error' => 'The rescue vehicle did not leave the board.', 'moves' => $count];
    }
    return ['ok' => true, 'error' => '', 'moves' => $count];
}

/**
 * Breadth-first search for the shortest solution (every move = one vehicle slides any legal distance).
 * Returns ['status' => 'solved'|'unsolvable'|'limit', 'moves' => [[id, to], ...], 'states' => int].
 */
function sliding_solve($vehicles_map)
{
    $list = array_values($vehicles_map);
    $n = count($list);
    $horizontal = [];
    $length = [];
    $fixed = [];
    $start = '';
    $rescue_index = -1;
    foreach ($list as $i => $v) {
        $horizontal[$i] = ($v['orient'] === 'H');
        $length[$i] = $v['length'];
        $fixed[$i] = $horizontal[$i] ? $v['row'] : $v['col'];
        $start .= chr(sliding_pos($v));
        if ($v['rescue']) {
            $rescue_index = $i;
        }
    }

    $queue = [$start];
    $parent = [-1];
    $move = [null];
    $seen = [$start => true];
    $head = 0;
    $started = microtime(true);

    while ($head < count($queue)) {
        if (count($queue) > SLIDING_MAX_STATES || (microtime(true) - $started) > SLIDING_MAX_SECONDS) {
            return ['status' => 'limit', 'moves' => [], 'states' => $head];
        }
        $state = $queue[$head];

        // which vehicle stands on each cell in this state (-1 = empty)
        $grid = array_fill(0, SLIDING_BOARD * SLIDING_BOARD, -1);
        for ($i = 0; $i < $n; $i++) {
            $p = ord($state[$i]);
            for ($k = 0; $k < $length[$i]; $k++) {
                $q = $p + $k;
                if ($q < SLIDING_BOARD) {
                    $grid[$horizontal[$i] ? $fixed[$i] * SLIDING_BOARD + $q : $q * SLIDING_BOARD + $fixed[$i]] = $i;
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            $p = ord($state[$i]);

            // how far can vehicle $i slide towards the start of the board / towards the end?
            $lo = $p;
            while ($lo - 1 >= 0) {
                $q = $lo - 1;
                $cell = $horizontal[$i] ? $fixed[$i] * SLIDING_BOARD + $q : $q * SLIDING_BOARD + $fixed[$i];
                if ($grid[$cell] !== -1) {
                    break;
                }
                $lo = $q;
            }
            $max_start = ($i === $rescue_index) ? SLIDING_BOARD : SLIDING_BOARD - $length[$i];
            $hi = $p;
            while ($hi < $max_start) {
                $q = $hi + $length[$i];                 // the new front cell when sliding one step further
                if ($q < SLIDING_BOARD) {
                    $cell = $horizontal[$i] ? $fixed[$i] * SLIDING_BOARD + $q : $q * SLIDING_BOARD + $fixed[$i];
                    if ($grid[$cell] !== -1) {
                        break;
                    }
                }
                $hi++;
            }

            for ($np = $lo; $np <= $hi; $np++) {
                if ($np === $p) {
                    continue;
                }
                if ($i === $rescue_index && $np >= SLIDING_BOARD) {
                    // goal: rebuild the list of moves
                    $path = [[$list[$i]['id'], $np]];
                    $k = $head;
                    while ($parent[$k] !== -1) {
                        array_unshift($path, $move[$k]);
                        $k = $parent[$k];
                    }
                    return ['status' => 'solved', 'moves' => $path, 'states' => $head];
                }
                $next = $state;
                $next[$i] = chr($np);
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                    $parent[] = $head;
                    $move[] = [$list[$i]['id'], $np];
                }
            }
        }
        $head++;
    }
    return ['status' => 'unsolvable', 'moves' => [], 'states' => $head];
}

/**
 * Full server-side check of a layout typed by an administrator.
 * Returns ['errors' => [...], 'rows' => [...], 'layout' => text, 'vehicles' => count,
 *          'optimal' => shortest solution length or null, 'solution' => [[id, to], ...]].
 * The layout may only be saved when 'errors' is empty.
 */
function analyse_sliding_layout($text)
{
    $info = ['errors' => [], 'rows' => [], 'layout' => '', 'vehicles' => 0, 'optimal' => null, 'solution' => []];

    $rows = sliding_rows_from_text($text);
    $parsed = sliding_parse_rows($rows);
    if (!empty($parsed['errors'])) {
        $info['errors'] = $parsed['errors'];
        return $info;
    }
    $info['rows'] = $rows;
    $info['layout'] = implode("\n", $rows);
    $info['vehicles'] = count($parsed['vehicles']);

    @set_time_limit(30);
    $result = sliding_solve($parsed['vehicles']);
    if ($result['status'] === 'solved') {
        $info['optimal'] = count($result['moves']);
        $info['solution'] = $result['moves'];
    } elseif ($result['status'] === 'unsolvable') {
        $info['errors'][] = 'This puzzle cannot be solved: the rescue vehicle can never leave the board.';
    } else {
        $info['errors'][] = 'The solver could not check this puzzle within its limits. Please simplify it.';
    }
    return $info;
}
