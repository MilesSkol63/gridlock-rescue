/** tests/run_all.js - runs every JavaScript test suite.   node tests/run_all.js   (PHP tests: php tests/php/sliding_test.php) */
const { spawnSync } = require('child_process');
let bad = 0;
['engine', 'editor', 'sql_seed', 'ui-smoke'].forEach(n => {
    const r = spawnSync(process.execPath, [__dirname + '/' + n + '.test.js'], { encoding: 'utf8' });
    const last = (r.stdout || '').trim().split('\n').pop();
    console.log(n.padEnd(10) + ' ' + last);
    if (r.status !== 0) { bad++; console.log(r.stdout); }
});
process.exit(bad ? 1 : 0);
