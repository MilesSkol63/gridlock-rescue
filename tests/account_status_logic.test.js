/**
 * tests/account_status_logic.test.js - LOGIC SIMULATION of set_account_active() and of the login / session checks,
 * run on an in-memory SQLite database (node:sqlite, Node 22.5+). It mirrors the PHP rules; it is NOT PostgreSQL and NOT the PHP code.
 * The real checks are tests/php/account_status_test.php (needs PHP + a PostgreSQL test database).   node tests/account_status_logic.test.js
 */
let DatabaseSync;
try { ({ DatabaseSync } = require('node:sqlite')); } catch (e) { console.log('SKIPPED: node:sqlite is not available in this Node version'); process.exit(0); }
process.removeAllListeners('warning');
let passed = 0, failed = 0;
const check = (n, ok, d) => { if (ok) passed++; else { failed++; console.log('FAIL: ' + n + (d ? ' -> ' + d : '')); } };

const db = new DatabaseSync(':memory:');
db.exec(`CREATE TABLE users (user_id INTEGER PRIMARY KEY, username TEXT, email TEXT, password_hash TEXT, role TEXT, is_active INTEGER NOT NULL DEFAULT 1);
 CREATE TABLE user_progress (progress_id INTEGER PRIMARY KEY, user_id INTEGER, level_id INTEGER, best_moves INTEGER);`);
const add = (id, name, role, active = 1) => db.prepare('INSERT INTO users VALUES (?,?,?,?,?,?)').run(id, name, name + '@x', 'HASH' + id, role, active);
add(1, 'admin1', 'admin'); add(2, 'admin2', 'admin'); add(3, 'maya', 'user'); add(4, 'nova', 'user');
[[3, 1, 12], [3, 2, 20], [4, 1, 9]].forEach(r => db.prepare('INSERT INTO user_progress (user_id, level_id, best_moves) VALUES (?,?,?)').run(...r));
const q = (sql, ...p) => db.prepare(sql).all(...p);

// --- mirror of includes/functions.php set_account_active() ---
function setAccountActive(actor, target, makeActive) {
    const res = { ok: false, error: '' };
    db.exec('BEGIN');
    try {
        const admins = q("SELECT user_id, is_active FROM users WHERE role = 'admin' ORDER BY user_id");
        const t = q('SELECT user_id, username, role, is_active FROM users WHERE user_id = ?', target)[0] || null;
        const actorOk = admins.some(a => a.user_id === actor && a.is_active === 1);
        const others = admins.filter(a => a.user_id !== target && a.is_active === 1).length;
        if (!actorOk) res.error = 'Only an active administrator can change account status.';
        else if (!t) res.error = 'That account no longer exists.';
        else if (makeActive && t.is_active === 1) res.error = 'That account is already active.';
        else if (!makeActive && t.is_active === 0) res.error = 'That account is already disabled.';
        else if (!makeActive && target === actor) res.error = 'You cannot disable your own account.';
        else if (!makeActive && t.role === 'admin' && others < 1) res.error = 'The last active administrator cannot be disabled.';
        if (res.error) { db.exec('ROLLBACK'); return res; }
        db.prepare('UPDATE users SET is_active = ? WHERE user_id = ?').run(makeActive ? 1 : 0, target);
        db.exec('COMMIT'); res.ok = true; return res;
    } catch (e) { db.exec('ROLLBACK'); res.error = 'failed'; return res; }
}
// --- mirror of index.php (login) and includes/auth.php get_session_account() ---
function login(identifier, passwordOk) {
    const a = q('SELECT * FROM users WHERE username = ? OR email = ?', identifier, identifier)[0] || null;
    if (a && passwordOk) return a.is_active === 1 ? { ok: true } : { ok: false, msg: 'disabled' };
    return { ok: false, msg: 'Invalid username/email or password.' };
}
function sessionAccount(userId) {
    const a = q('SELECT user_id, role, is_active FROM users WHERE user_id = ?', userId)[0];
    if (!a) return { account: null, disabled: false };
    return a.is_active === 1 ? { account: a, disabled: false } : { account: null, disabled: true };
}

const userBefore = JSON.stringify(q('SELECT user_id, username, email, password_hash, role FROM users ORDER BY user_id'));
const progressBefore = JSON.stringify(q('SELECT * FROM user_progress ORDER BY progress_id'));

check('active player can log in', login('maya', true).ok === true);
check('disable a player', setAccountActive(1, 3, false).ok === true && q('SELECT is_active FROM users WHERE user_id=3')[0].is_active === 0);
check('disabled player cannot log in, with the "disabled" message (only after the right password)', login('maya', true).msg === 'disabled' && login('maya', false).msg === 'Invalid username/email or password.');
check('an already logged-in disabled player loses access on the next request', sessionAccount(3).account === null && sessionAccount(3).disabled === true);
check('another player is unaffected', sessionAccount(4).account !== null && login('nova', true).ok);
check('profile, password hash, role and ids are unchanged', JSON.stringify(q('SELECT user_id, username, email, password_hash, role FROM users ORDER BY user_id')) === userBefore);
check('progress and scores are unchanged while disabled', JSON.stringify(q('SELECT * FROM user_progress ORDER BY progress_id')) === progressBefore);
check('disabling twice is refused', setAccountActive(1, 3, false).error === 'That account is already disabled.');
check('enable the player again', setAccountActive(1, 3, true).ok === true && login('maya', true).ok === true && sessionAccount(3).account !== null);
check('after enabling, previous progress is still there', JSON.stringify(q('SELECT * FROM user_progress ORDER BY progress_id')) === progressBefore && q('SELECT COUNT(*) c FROM user_progress WHERE user_id=3')[0].c === 2);
check('enabling an active account is refused', setAccountActive(1, 3, true).error === 'That account is already active.');
check('an administrator cannot disable their own account', setAccountActive(1, 1, false).error === 'You cannot disable your own account.' && q('SELECT is_active FROM users WHERE user_id=1')[0].is_active === 1);
check('a player cannot disable or enable anyone', setAccountActive(3, 4, false).ok === false && setAccountActive(3, 4, true).ok === false && q('SELECT is_active FROM users WHERE user_id=4')[0].is_active === 1);
check('unknown account is refused', setAccountActive(1, 999, false).error === 'That account no longer exists.');
check('administrator 1 can disable administrator 2 while administrator 1 stays active', setAccountActive(1, 2, false).ok === true && sessionAccount(2).disabled === true);
check('a disabled administrator cannot change any status', setAccountActive(2, 1, false).error === 'Only an active administrator can change account status.');
check('the last active administrator cannot be disabled (not even by themselves)', setAccountActive(1, 1, false).ok === false && q("SELECT COUNT(*) c FROM users WHERE role='admin' AND is_active=1")[0].c === 1);
check('administrator 2 can be enabled again by administrator 1', setAccountActive(1, 2, true).ok === true && login('admin2', true).ok === true);
check('no user row and no progress row was ever deleted', q('SELECT COUNT(*) c FROM users')[0].c === 4 && q('SELECT COUNT(*) c FROM user_progress')[0].c === 3);

console.log((failed === 0 ? 'ALL PASSED' : 'FAILURES') + ': ' + passed + ' checks passed, ' + failed + ' failed (SQLite simulation)');
process.exit(failed === 0 ? 0 : 1);
