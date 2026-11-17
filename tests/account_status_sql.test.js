/** tests/account_status_sql.test.js - the account-status (disable/enable) SQL is guarded and non-destructive.   node tests/account_status_sql.test.js */
const fs = require('fs'), path = require('path');
let passed = 0, failed = 0;
const check = (n, ok, d) => { if (ok) passed++; else { failed++; console.log('FAIL: ' + n + (d ? ' -> ' + d : '')); } };
const read = f => fs.readFileSync(path.join(__dirname, '..', f), 'utf8').replace(/\r\n/g, '\n');

const mig = read('database/migrate_user_status.sql');
const code = mig.split('\n').filter(l => !l.trim().startsWith('--')).join('\n');
check('migration adds users.is_active TINYINT(1) NOT NULL DEFAULT 1', /ALTER TABLE users ADD COLUMN is_active TINYINT\(1\) NOT NULL DEFAULT 1 AFTER role/.test(code));
check('migration only adds the column when it is missing (information_schema guard)', /information_schema\.COLUMNS[\s\S]*COLUMN_NAME = 'is_active'/.test(code) && /IF\(COUNT\(\*\) = 0/.test(code));
check('migration never drops, deletes, truncates or updates anything', !/\b(DROP|TRUNCATE|DELETE|UPDATE|INSERT INTO|RENAME|MODIFY|CHANGE)\b/i.test(code.replace(/DEALLOCATE PREPARE/gi, '')));
check('migration touches only the users table', !/\b(levels|user_progress)\b/i.test(code));
check('existing users stay active: the default is 1, not 0', !/DEFAULT 0/.test(code));

const fresh = read('database/gridlock_rescue.sql');
check('fresh install: users table has is_active TINYINT(1) NOT NULL DEFAULT 1', /CREATE TABLE IF NOT EXISTS users \([\s\S]*is_active\s+TINYINT\(1\) NOT NULL DEFAULT 1[\s\S]*?\) ENGINE/.test(fresh));
check('fresh/combined SQL also contains the guarded column migration', /information_schema\.COLUMNS[\s\S]*COLUMN_NAME = 'is_active'/.test(fresh));
check('foreign keys still cascade (relationships were not removed)', (fresh.match(/ON DELETE CASCADE/g) || []).length >= 2);
check('import.php accepts the new migration file', /migrate_user_status\.sql/.test(read('database/import.php')));

// application code: no permanent user deletion is left anywhere
const files = ['users.php', 'user_edit.php', 'profile.php', 'progress.php', 'levels.php', 'dashboard.php', 'register.php', 'index.php', 'setup_admin.php', 'save_progress.php', 'play.php', 'leaderboard.php', 'level_form.php', 'includes/functions.php', 'includes/auth.php', 'assets/js/app.js', 'assets/js/game.js']
    .map(f => [f, read(f)]);
check('no "DELETE FROM users" statement in any application file', files.every(([f, s]) => !/DELETE\s+FROM\s+users/i.test(s)), files.filter(([f, s]) => /DELETE\s+FROM\s+users/i.test(s)).map(x => x[0]).join(','));
check('no delete_user action / handler / button left', files.every(([f, s]) => !/delete_user|Delete account/i.test(s)));
const users = read('users.php');
check('users.php offers Disable and Enable, via POST actions with the confirmation modal', /data-action="disable_user"/.test(users) && /data-action="enable_user"/.test(users) && /confirm-modal/.test(users));
check('users.php handles the status change only after verify_csrf() and require_admin()', users.indexOf('require_admin()') < users.indexOf("'disable_user'") && users.indexOf('verify_csrf()') < users.indexOf("'disable_user'"));
check('users.php shows an Active / Disabled badge', /Active/.test(users) && /Disabled/.test(users));
check('status is never changed through GET (no $_GET in the status handler)', !/\$_GET\[['"](id|action)['"]\][\s\S]{0,40}disable/.test(users));
const auth = read('includes/auth.php'), idx = read('index.php');
check('the session guard reads is_active on every request and ends a disabled session', /is_active FROM users WHERE user_id/.test(auth) && /account_was_disabled\(true\)/.test(auth) && /destroy_session\(\)/.test(auth));
check('login refuses disabled accounts (after the password check)', /is_active/.test(idx) && idx.indexOf('password_verify') < idx.indexOf("has been disabled"));
check('registration still creates active accounts (column default, role stays user)', /VALUES \(\?, \?, \?, 'user'\)/.test(read('register.php')));

console.log((failed === 0 ? 'ALL PASSED' : 'FAILURES') + ': ' + passed + ' checks passed, ' + failed + ' failed');
process.exit(failed === 0 ? 0 : 1);
