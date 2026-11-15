/** tests/sql_seed.test.js - the SQL files insert exactly the bundled levels and are non-destructive.   node tests/sql_seed.test.js */
const fs = require('fs'), path = require('path');
const LEVELS = require('./fixtures/sliding_levels.json');
let passed = 0, failed = 0;
const check = (n, ok, d) => { if (ok) passed++; else { failed++; console.log('FAIL: ' + n + (d ? ' -> ' + d : '')); } };
const read = f => fs.readFileSync(path.join(__dirname, '..', 'database', f), 'utf8').replace(/\r\n/g, '\n');

for (const file of ['migrate_sliding.sql', 'gridlock_rescue.sql']) {
    const sql = read(file);
    const re = /INSERT INTO levels \(game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves\)\nSELECT 'sliding', '((?:[^']|'')*)', '((?:[^']|'')*)', '(\w+)', (\d+), (\d+), (\d+), '([^']*)', (\d+)\nFROM DUAL WHERE NOT EXISTS \(SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = '((?:[^']|'')*)'\);/g;
    const found = []; let m;
    while ((m = re.exec(sql)) !== null) found.push({ title: m[1].replace(/''/g, "'"), hint: m[2].replace(/''/g, "'"), diff: m[3], order: +m[4], w: +m[5], h: +m[6], rows: m[7].split('\n'), par: +m[8], guard: m[9].replace(/''/g, "'") });
    check(file + ': six level inserts found', found.length === 6, 'found ' + found.length);
    LEVELS.forEach((L, i) => {
        const f = found[i] || {};
        check(file + ': level ' + L.id + ' matches the tested fixture (title, rows, par, order, difficulty, hint)',
            f.title === L.name && JSON.stringify(f.rows) === JSON.stringify(L.rows) && f.par === L.best && f.order === L.id && f.diff === L.difficulty.toLowerCase() && f.hint === L.hint);
        check(file + ': level ' + L.id + ' is 6x6 and the duplicate guard uses the same title', f.w === 6 && f.h === 6 && f.guard === L.name);
    });
    // non-destructive: nothing that deletes or rewrites existing data
    const code = sql.split('\n').filter(l => !l.trim().startsWith('--')).join('\n');
    check(file + ': no DROP / TRUNCATE / DELETE', !/\b(DROP|TRUNCATE|DELETE)\b/i.test(code.replace(/DEALLOCATE PREPARE/gi, '').replace(/ON DELETE CASCADE/gi, '')));
    check(file + ': never UPDATEs or ALTERs users or user_progress', !/\b(UPDATE|ALTER TABLE|INSERT INTO)\s+(users|user_progress)\b/i.test(code));
    check(file + ': adds game_type only if missing (information_schema guard)', /information_schema\.COLUMNS[\s\S]*COLUMN_NAME = 'game_type'/.test(code) && /ADD COLUMN game_type ENUM\(''sokoban'',''sliding''\) NOT NULL DEFAULT ''sokoban''/.test(code));
    check(file + ': existing levels default to the archived type sokoban', /DEFAULT ''sokoban''/.test(code));
    check(file + ': difficulty gets the value expert without removing the others', /MODIFY difficulty ENUM\('easy','medium','hard','expert'\)/.test(code));
}
const fresh = read('gridlock_rescue.sql');
check('fresh install: levels table has game_type, expert and the index', /CREATE TABLE IF NOT EXISTS levels \([\s\S]*game_type\s+ENUM\('sokoban','sliding'\)[\s\S]*'expert'[\s\S]*idx_levels_game_order/.test(fresh));
check('fresh install: no Sokoban sample levels are inserted', !/'sokoban', '/i.test(fresh.replace(/DEFAULT ''sokoban''/g, '').replace(/ENUM\(''sokoban''/g, '')));
check('fresh install: still creates users and user_progress', /CREATE TABLE IF NOT EXISTS users/.test(fresh) && /CREATE TABLE IF NOT EXISTS user_progress/.test(fresh));
check('migration file does not create or alter user tables', !/CREATE TABLE/i.test(read('migrate_sliding.sql')));

console.log((failed === 0 ? 'ALL PASSED' : 'FAILURES') + ': ' + passed + ' checks passed, ' + failed + ' failed');
process.exit(failed === 0 ? 0 : 1);
