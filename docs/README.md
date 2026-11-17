# Gridlock Rescue - Project Documentation (Deliverables 1-4)

Complete college endterm project: PHP / MySQL (MySQLi) / Bootstrap 5 sliding-block traffic puzzle with player and administrator roles. The Sokoban version passed the team's local XAMPP tests (Deliverables 2-4, team-executed). **The sliding-block integration (this version) has not yet been run on XAMPP or Railway** - see section H for what was and was not tested.

Requirements: PHP 8.2+ with the `mysqli` extension, MySQL 8 or MariaDB 10.4+, Apache (XAMPP) locally or Railway in production.

# CURRENT VERSION: sliding-block traffic puzzle (replaces the Sokoban game)

> **Reading guide.** This section describes the current application. The sections _after_ it (Deliverables 2-4) are the
> original documentation and test records of the **Sokoban version**; they are kept as history. Where they disagree with this
> section (the game, the level format, the level editor, the scoring), this section wins. Authentication, sessions, roles,
> user management, profile, progress management, dashboards and the leaderboard logic were kept (see the checklist below).

## A. What changed

The box-pushing game was replaced by the approved sliding-block traffic rescue game (6x6 board, vehicles of length 2-3 with a fixed
orientation, red rescue vehicle, one slide of any length = one move). Everything else is the same application.
| Area | Result |
|---|---|
| Game | `assets/js/sliding-engine.js` (rules, same code as the prototype + `getHistory()`), `assets/js/game.js` (screen + input), `play.php` (page) |
| Server rules | `includes/sliding.php`: PHP version of the rules, level validation, solver, and replay of submitted games |
| Saving a score | `save_progress.php` receives the **list of accepted slides**, replays it on the server and counts the moves itself |
| Admin level editor | `level_form.php` + `assets/js/level-editor.js`: visual 6x6 editor (add / select / move / rotate / resize / delete vehicles, rescue vehicle fixed to row 3), live solvability check, server-side validation with the PHP solver |
| Levels | six bundled levels in the database (5, 10, 12, 14, 17, 20 optimal moves); levels are read from MySQL, none are hard-coded in the game |
| Old data | Sokoban levels and scores are **archived, not deleted** (see C) |

## B. Level format (one format for the editor, the browser game and PHP)

6 rows of 6 characters stored in `levels.layout`: `.` empty cell, `R` the rescue vehicle, any other capital letter = one vehicle.
Orientation and length are read from the shape (2 or 3 cells in a straight line). The rescue vehicle must be horizontal in row 3 and leaves
through the exit on the right edge of that row. Example (level 1):

```
D..A..
D..A.C
.RRA.C
..EEE.
..BB..
......
```

Columns used in `levels`: `game_type` ('sliding'), `title`, `hint` (description), `difficulty` (easy/medium/hard/expert), `sort_order`,
`grid_width`/`grid_height` (always 6), `layout`, `par_moves` (= optimal moves, calculated by the server; an admin may set a higher par but never a lower one).

## C. Database migration (non-destructive)

`database/migrate_sliding.sql` (also contained in the updated `database/gridlock_rescue.sql`, which is safe on new AND existing databases):
adds `levels.game_type` only if missing (all existing levels become `'sokoban'` = archived), adds the difficulty value `expert`, adds an index, and inserts the
six sliding-block levels once. It does **not** change or delete `users`, `user_progress`, passwords or sessions. Archived Sokoban levels/scores stay in the
tables but are filtered out of every game list, unlocking rule, dashboard statistic, leaderboard, progress page and reset action.
**Before migrating, make a backup of the database** (any method your host offers).

**Local XAMPP (existing database):** phpMyAdmin -> select your `gridlock_rescue` database -> Import -> `database/migrate_sliding.sql` -> Go.
Or from a terminal in the project folder: `C:\xampp\php\php.exe database\import.php migrate_sliding.sql`. Then copy the new files over the old project.
**Local XAMPP (new database):** create the database, then import `database/gridlock_rescue.sql` as before.
**Railway (existing database):** deploy the new code, then run once from inside the app container (private network, nothing exposed):
`railway ssh -- php database/import.php migrate_sliding.sql`. It prints how many sliding levels exist and how many Sokoban levels were kept.
Order matters only a little: until the migration runs, pages that read `levels.game_type` will show a database error, so run it right after the deploy (or before, because the old code ignores the new column).
Re-running the migration is harmless. **Rollback:** restore the backup (the old code only needs the old data, the new column is ignored by it).

## D. How the admin creates a level

Levels -> **+ New Level** (or **Edit**). Choose orientation and length, click an empty square to add a vehicle, click a vehicle to select it (move with the
arrow buttons or arrow keys, rotate, length 2/3, delete). The red R stays in row 3. The page shows "Solvable. Optimal solution: N moves" (browser check) and fills
_Par moves_ when empty. On **Save Level** the server parses the board, checks geometry (6x6, valid vehicles, no overlaps because each cell holds one letter, rescue vehicle rules),
solves it with the PHP solver and rejects unsolvable levels; par below the optimum is rejected. If the board arrangement changes and progress exists, saving is blocked until the administrator explicitly confirms clearing those scores. Metadata-only edits preserve progress. The board update and targeted score deletion happen in one transaction.
Test Play (admin) plays any level without saving. Deleting a level (confirmation dialog) also deletes its scores (existing cascade). Archived Sokoban levels cannot be edited or deleted from the site.

## E. How a score is saved and checked

The browser sends `{level_id, layout_hash, moves: [[vehicle, newPosition], ...]}` (the accepted slides, with undo and restart already applied) plus the CSRF token in a header. `layout_hash` is a SHA-256 fingerprint of the normalized board shown to the player.
`save_progress.php`: session user only, role must be player, level must exist and be unlocked, and the submitted layout fingerprint must still match the database. A stale game opened before an administrator changed the board is rejected with HTTP 409; the game page offers a **Reload Level** action instead of retrying the stale move list. The endpoint then **replays every slide with the PHP rules**; the game must end with the rescue vehicle completely off the board. The stored score is the number of slides counted by the server (never a number from the browser). The endpoint locks the level row and saves the score in a transaction, serializing score submission against admin board edits. A forged or impossible move list is rejected with HTTP 422 and nothing is stored.

## F. Files added / modified / removed

| Status    | File                                                                                                                                                                                                               | What                                                                                                                                         |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------- |
| NEW       | `includes/sliding.php`                                                                                                                                                                                             | PHP rules, validator, BFS solver, replay, normalized layout fingerprint                                                                      |
| NEW       | `assets/js/sliding-engine.js`                                                                                                                                                                                      | browser engine (from the approved prototype)                                                                                                 |
| NEW       | `assets/js/level-editor.js`                                                                                                                                                                                        | visual level editor                                                                                                                          |
| NEW       | `database/migrate_sliding.sql`                                                                                                                                                                                     | non-destructive migration + six levels                                                                                                       |
| NEW       | `tests/*.test.js`, `tests/run_all.js`, `tests/fixtures/*`, `tests/php/sliding_test.php`, `tests/php/level_edit_policy_test.php`                                                                                    | automated tests                                                                                                                              |
| REPLACED  | `assets/js/game.js`                                                                                                                                                                                                | was the Sokoban engine; now the sliding-block screen/input code                                                                              |
| REPLACED  | `play.php`, `save_progress.php`, `level_form.php`                                                                                                                                                                  | sliding-block page, score replay, visual editor                                                                                              |
| MODIFIED  | `includes/functions.php`                                                                                                                                                                                           | level helpers read only `game_type='sliding'`; Sokoban validator removed; `delete_sliding_progress()`; tested level-edit confirmation policy |
| MODIFIED  | `levels.php`, `leaderboard.php`, `dashboard.php`, `progress.php`, `profile.php`, `users.php`                                                                                                                       | queries limited to sliding-block levels/scores; wording "vehicles"; archive note for admins; resets only touch sliding scores                |
| MODIFIED  | `index.php`                                                                                                                                                                                                        | login page picture (traffic jam instead of the Sokoban board)                                                                                |
| MODIFIED  | `assets/css/style.css`, `assets/js/app.js`, `database/gridlock_rescue.sql`, `database/import.php`, `docs/README.md`                                                                                                | styles; Sokoban preview code removed from app.js; new schema; `import.php` can run the migration file                                        |
| REMOVED   | old Sokoban tests (`tests/engine.test.js` content), Sokoban board CSS, on-screen D-pad (not needed: vehicles are dragged or moved with the keyboard)                                                               |                                                                                                                                              |
| UNCHANGED | `register.php`, `logout.php`, `user_edit.php`, `setup_admin.php`, `includes/auth.php`, `includes/db.php`, `includes/config.php`, `includes/navbar.php`, `includes/confirm_modal.php`, `composer.json`, `.htaccess` | authentication, sessions, roles, CRUD of users, navigation                                                                                   |

`includes/config.local.php` (your local credentials) is **not** in the ZIP; copy `includes/config.local.example.php` as before. Environment variables on Railway are unchanged.

## G. Feature-preservation checklist

Status meanings: **Code kept** = the code path was not changed (verified by comparing files); **Adapted** = changed only to use sliding-block levels. PHP syntax and database-independent PHP logic tests have now been executed; a real MySQL/MariaDB-backed website test and browser/device test are still outstanding (see section H).
| Feature | How it is preserved | Status |
|---|---|---|
| Registration, login, logout, sessions, CSRF | `register.php`, `index.php` (login code), `logout.php`, `includes/auth.php` unchanged | Code kept |
| Roles and access control | `require_login/require_admin/require_user` unchanged; every changing action still POST + CSRF | Code kept |
| Admin / player dashboards | same page, queries limited to sliding levels | Adapted |
| User management (add, edit, delete, search) | `users.php`, `user_edit.php`; level counts use sliding levels | Code kept / Adapted |
| Profile, password change, reset own progress | `profile.php`; reset only deletes sliding scores | Adapted |
| Progress page, reset one level / all, admin delete | `progress.php` adapted the same way | Adapted |
| Level list, unlocking, "Continue/Start Playing" | `get_levels_with_status()` (same rule) on sliding levels | Adapted |
| Leaderboard (more levels first, then fewer total moves, ties share a rank) | same SQL with `game_type='sliding'` | Adapted |
| Admin level CRUD, order, difficulty, par, description, delete with confirmation, Test Play | `levels.php` + new `level_form.php`; changed boards with saved progress require confirmation and clear only that level's scores transactionally | Adapted |
| Level activation/publishing | the existing app had no such feature, so none was added | n/a |
| Archived Sokoban data | kept in the database, hidden from the game | Preserved |
| MySQLi prepared statements, Railway env variables, no secrets in the ZIP | unchanged conventions | Code kept |

## H. Test report

**Executed in the available container (Node + PHP CLI):** `node tests/run_all.js`, `php tests/php/sliding_test.php`, and `php tests/php/level_edit_policy_test.php`
| Suite | Result |
|---|---|
| `tests/engine.test.js` - rules, collisions, bounds, exit/victory, move counting, undo/restart, history, all six levels replayed, optimal length by two solvers, difficulty curve, 36,000 random actions | **129 passed, 0 failed** |
| `tests/editor.test.js` - editor model: add/move/rotate/resize/delete, rescue-vehicle rules, round trip of all six levels, unsolvable board detected | **35 passed, 0 failed** |
| `tests/sql_seed.test.js` - both SQL files insert exactly the tested levels, are guarded and non-destructive | **40 passed, 0 failed** |
| `tests/ui-smoke.test.js` - the real `game.js` on a fake page: keyboard, drag, undo/restart, save request format including the board fingerprint, stale-board reload flow, retry, failures, admin preview, all six levels played through the UI, board sizes for 320-1280px | **47 passed, 0 failed** |
| `tests/php/sliding_test.php` - PHP parser, solver, layout validation and move replay | **116 passed, 0 failed** |
| `tests/php/level_edit_policy_test.php` - metadata-only edits, changed layouts with/without progress, confirmation required/accepted | **5 passed, 0 failed** |
Sabotage check: four deliberate engine breakages (no collision check, early victory, no move merging, empty history) were each caught by the engine tests (22, 3, 3 and 5 failed checks); three of them were also caught by the UI test.
**Executed here (algorithm cross-check):** a line-by-line Python transliteration of `includes/sliding.php` (parser, range, replay, solver, analysis) reproduced all 107 expected results: all six optimal move counts, 54 rejected tampered move lists and 11 invalid/unsolvable layouts. This checks the PHP _logic_; it is **not** a PHP run.
**Executed here (PHP CLI):** `php tests/php/sliding_test.php` (**116 passed**), `php tests/php/level_edit_policy_test.php` (**5 passed**), and `php -l` on all **25 PHP files** (no syntax errors). Node test runner completed all **251 JavaScript checks**.
**NOT RUN:**

- Real MySQLi database queries, row-locking/transaction behavior, and the migration against a real MySQL/MariaDB database (the available PHP CLI does not have the `mysqli` extension and no MySQL/MariaDB server is installed here).
- XAMPP website behavior, real browser rendering, phone touch interactions, and Railway deployment. These still need testing on your XAMPP installation.
- Login/registration/sessions/roles/CRUD regression runs. Their test records further down in this file belong to the Sokoban version.

### Checklist for the team (local XAMPP, then Railway) - nothing below has been executed yet

| #   | Test                                                                                                                                                                   | Expected Result                                                                                                                                                 | Actual Result | Status          |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- | --------------- |
| 1   | `php -l` on every PHP file; `php tests/php/sliding_test.php`; `php tests/php/level_edit_policy_test.php`                                                               | no syntax errors; 116 + 5 PHP checks passed in the available container; repeat under XAMPP                                                                      |               | Repeat on XAMPP |
| 2   | Copy the database, run `migrate_sliding.sql`, run it again                                                                                                             | levels table has `game_type`; 6 sliding levels; users and `user_progress` row counts unchanged; second run changes nothing                                      |               | Not run         |
| 3   | Fresh database + `gridlock_rescue.sql`                                                                                                                                 | tables created, 6 levels                                                                                                                                        |               | Not run         |
| 4   | Log in as a player with old Sokoban scores                                                                                                                             | Levels page shows only the 6 new levels; Level 1 unlocked, others locked; dashboard/leaderboard ignore old scores                                               |               | Not run         |
| 5   | Play level 1: drag, arrow keys, Shift+arrow, Undo (Z), Restart (R)                                                                                                     | vehicles slide, blocked vehicles shake, counter correct                                                                                                         |               | Not run         |
| 6   | Finish level 1                                                                                                                                                         | rescue vehicle drives out, overlay with rating, "Progress saved", Next Level works; one row in `user_progress`                                                  |               | Not run         |
| 7   | Finish it again with more / fewer moves                                                                                                                                | best only improves; still one row                                                                                                                               |               | Not run         |
| 8   | Finish levels in order                                                                                                                                                 | each completion unlocks the next; leaderboard ranks by levels then total moves                                                                                  |               | Not run         |
| 9   | Dev tools: send `save_progress.php` a wrong move list, stale `layout_hash`, locked level, no / wrong CSRF header, an admin session, a `moves` number instead of a list | all rejected (400/403/409/422), nothing stored                                                                                                                  |               | Not run         |
| 10  | Admin: Levels list, Test Play, finish a level in preview                                                                                                               | "nothing was saved"; no `user_progress` row                                                                                                                     |               | Not run         |
| 11  | Admin: new level in the editor (add, move, rotate, resize, delete vehicles)                                                                                            | live message "Solvable... N moves"; Save works; level appears and loads in the game                                                                             |               | Not run         |
| 12  | Admin: unsolvable board (e.g. two trucks filling a column); par below optimal; empty title; duplicate title                                                            | each rejected with a clear message, nothing saved                                                                                                               |               | Not run         |
| 13  | Admin: edit a level that has scores (layout change without confirmation, then with confirmation; metadata-only edit); delete a level                                   | board change without confirmation is rejected; confirmed board change clears only its scores; metadata-only edit preserves scores; delete asks for confirmation |               | Not run         |
| 14  | Player opens `level_form.php` or POSTs `delete_level`                                                                                                                  | redirected / refused                                                                                                                                            |               | Not run         |
| 15  | Profile, password change, My Progress reset (one level / all), admin delete record / reset player                                                                      | work; old Sokoban rows are NOT deleted (check in phpMyAdmin)                                                                                                    |               | Not run         |
| 16  | Users page: add, edit, search, delete a player                                                                                                                         | work as before                                                                                                                                                  |               | Not run         |
| 17  | Phone widths 360px and 375px: play, editor, levels, leaderboard                                                                                                        | board fits, vehicles are draggable, page scrolls outside the board                                                                                              |               | Not run         |
| 18  | Railway: deploy, run `railway ssh -- php database/import.php migrate_sliding.sql`, repeat 4-8                                                                          | same results as local                                                                                                                                           |               | Not run         |

## I. Known limitations and manual steps

- The migration must be run once on every existing database (local and Railway) and a backup is recommended first.
- `par_moves` of the six bundled levels is the proven optimum; an admin-set par may be higher than optimal (ratings use par).
- The PHP solver stops at 200,000 states or 8 seconds and then rejects the level as "too complex to verify"; the six bundled levels need under 8,000 states.
- Old Sokoban levels cannot be edited or deleted on the site; they can only be removed directly in the database (not recommended).
- A player who loses the page after winning but before the save finishes must replay the level (there is no offline queue).
- There is no level activation/publishing switch because the original application did not have one.
- The editor needs JavaScript; the server still validates everything it receives.

## J. Account status: disable / enable instead of deleting users

Administrators can no longer delete user accounts. `users.php` now shows an **Active / Disabled** badge and a **Disable Account** or **Enable Account** button
(confirmation dialog first, POST + CSRF + administrator check). Nothing is deleted: profile, password hash, role, progress, scores and leaderboard history stay.
The delete handler, button and dialog text were removed; no other permanent user-deletion path exists in the application (the database cascade rules were left in place).

- **Database:** `users.is_active TINYINT(1) NOT NULL DEFAULT 1` (1 = active). `database/migrate_user_status.sql` adds it only if missing; existing users stay active; nothing else changes. The combined `database/gridlock_rescue.sql` contains the same guarded step.
- **Apply to your existing database (back it up first):** local: import `database/migrate_user_status.sql` in phpMyAdmin (database selected) or run `php database/import.php migrate_user_status.sql`. Railway: deploy, then `railway ssh -- php database/import.php migrate_user_status.sql`. **Run the migration before (or right after) deploying the new code**: the new login query reads `is_active` and fails until the column exists. Nothing in this package touches your live database.
- **Login:** a disabled account is refused with "This account has been disabled. Please contact an administrator." - shown only after the correct password, so wrong passwords still get the generic message.
- **Open sessions:** `get_session_account()` / `require_login()` read `is_active` on every authenticated request. A disabled user (player or administrator) is logged out on the next request, the session is destroyed and the login page explains why. `save_progress.php` answers 403 with the same reason.
- **Safeguards (all on the server, in one transaction that locks the administrator rows):** only an active administrator may change statuses; you cannot disable your own account; an administrator can only be disabled while another active administrator remains; repeated or unknown requests are refused.
- **Registration** and "Add Player" create active accounts (column default). **Level progression, scoring, leaderboard and the sliding-block game are unchanged**; disabled players stay on the leaderboard because their history is preserved.
- **Files created:** `database/migrate_user_status.sql`, `tests/account_status_sql.test.js`, `tests/account_status_logic.test.js`, `tests/php/account_status_test.php`.
  **Modified:** `users.php`, `index.php`, `includes/auth.php`, `includes/functions.php` (`set_account_active()`), `save_progress.php`, `assets/js/app.js` (button colour in the dialog), `assets/css/style.css` (status badges), `database/gridlock_rescue.sql`, `database/import.php`, `tests/sql_seed.test.js`, `tests/run_all.js`, `docs/README.md`.

### Account-status test report

**Executed here (Node):** `node tests/run_all.js` - engine 129, editor 35, SQL seed 40 (adjusted to allow the one guarded column), account-status SQL/code checks 18, account-status logic simulation 19, game UI 47; **all passed**. The simulation runs the disable/enable rules on SQLite (not MySQL, not the PHP code). The SQL/code checks confirm the migration is guarded and non-destructive and that no `DELETE FROM users` / `delete_user` remains in the application.
**Executed here (static):** lexer, bracket and undefined-function checks of all 26 PHP files (no problems); all links and script targets exist.
**NOT run:** PHP and MySQL (not available here): `php -l`, the real-database test `php tests/php/account_status_test.php` (needs a database named `*_test`; it refuses to run elsewhere), the migration on a real database, all pages in a browser, Railway.
| # | Test (local XAMPP) | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | `php -l` on every PHP file; `php tests/php/account_status_test.php` on a `*_test` database | no errors; ALL PASSED | | Not run |
| 2 | Run `migrate_user_status.sql` twice on a copy of your database | column exists once; all users active; user and progress counts unchanged | | Not run |
| 3 | Admin: Users page | Active/Disabled badges; Disable button on others, "Your account" on yours; no Delete anywhere | | Not run |
| 4 | Disable a player (confirm dialog) | success message; badge turns Disabled; row, progress and leaderboard entry remain | | Not run |
| 5 | Disabled player tries to log in (right password / wrong password) | "account has been disabled" / generic invalid message | | Not run |
| 6 | Player logged in (second browser), admin disables them, player clicks any link or opens `play.php` directly | logged out, login page shows the disabled message | | Not run |
| 7 | Same while the player is mid-game and finishes the level | save is refused (403) | | Not run |
| 8 | Enable the player | can log in; old progress and scores are all there | | Not run |
| 9 | Disable your own account (button hidden; also POST `disable_user` with your id via dev tools) | refused | | Not run |
| 10 | Two administrators: disable the other one; then the disabled one tries any admin action | works once; disabled one is logged out / refused | | Not run |
| 11 | Only one active administrator remains: try to disable them (POST) | refused | | Not run |
| 12 | Player (or logged-out visitor) POSTs `disable_user` / `enable_user`; or sends GET `users.php?action=disable_user&id=..` | redirected / nothing changes | | Not run |
| 13 | POST without or with a wrong CSRF token | refused | | Not run |
| 14 | Register, login, logout, edit user, dashboards, levels, play + save a level, leaderboard | all unchanged | | Not run |

---

# HISTORY: documentation and test records of the Sokoban version (Deliverables 2-4)

## Local setup (XAMPP)

1. Copy the project folder to `C:\xampp\htdocs\gridlock-rescue` (macOS/Linux: your web root).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Create the database: open http://localhost/phpmyadmin -> New -> name `gridlock_rescue`, collation `utf8mb4_unicode_ci`.
4. Import `database/gridlock_rescue.sql`: select the `gridlock_rescue` database -> Import -> choose the file -> Go.
   (Alternative from a terminal in the project folder: `C:\xampp\php\php.exe database\import.php`, after step 5.)
5. Copy `includes/config.local.example.php` to `includes/config.local.php` and adjust the values if yours differ (XAMPP defaults already match). This file is ignored by Git.
6. Create the first administrator (terminal in the project folder):
   `C:\xampp\php\php.exe setup_admin.php` and answer the prompts. It refuses to run if an admin exists. (Windows shows the typed password.)
7. Open http://localhost/gridlock-rescue/ . Register a player, then log in as player and as admin.

## Railway deployment (Railpack, no Dockerfile) - the earlier Sokoban version is reported live by the team; this sliding-block version is NOT yet deployed or tested on Railway

Railpack detects PHP from `index.php` / `composer.json` and serves it with FrankenPHP. `composer.json` requires PHP >= 8.2 and `ext-mysqli`, so the extension is installed automatically.

1. Push the project to GitHub (check that `includes/config.local.php` is NOT in the repo).
2. Railway -> New Project -> Deploy from GitHub repo. This creates the PHP service.
3. In the same project: **+ New -> Database -> Add MySQL**. Keep the default service name `MySQL`. Do not enable a public TCP proxy for it.
4. PHP service -> **Variables** -> add (names must match exactly):
   - `MYSQLHOST` = `${{MySQL.MYSQLHOST}}` (if the connection fails, use `${{MySQL.RAILWAY_PRIVATE_DOMAIN}}`)
   - `MYSQLPORT` = `${{MySQL.MYSQLPORT}}`
   - `MYSQLUSER` = `${{MySQL.MYSQLUSER}}`
   - `MYSQLPASSWORD` = `${{MySQL.MYSQLPASSWORD}}`
   - `MYSQLDATABASE` = `${{MySQL.MYSQLDATABASE}}`
     These resolve to Railway's private network host (`*.railway.internal`).
5. PHP service -> Settings -> Networking -> **Generate Domain**.
6. Install the Railway CLI, then in the project folder: `railway login`, `railway link` (pick the PHP service), and register an SSH key if asked (`railway ssh keys`).
7. Import the schema from inside the container (private network, nothing exposed): `railway ssh -- php database/import.php`
8. Create the administrator: `railway ssh` then `php setup_admin.php`. Non-interactive alternative: add variables `ADMIN_USERNAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, run `railway ssh -- php setup_admin.php`, then DELETE `ADMIN_PASSWORD`.
9. Open the generated domain and run the verification checklist below.

Notes: sessions are stored on the container disk, so a redeploy logs everyone out (acceptable for this project). The web root is the project root, so `database/*.sql`, `docs/` and `composer.json` can be downloaded; they contain no secrets. PHP files in `includes/` return 403 when opened directly.

### Railway verification checklist (no results recorded in this file; repeat it after deploying the sliding-block version)

- [ ] Build log shows PHP 8.2+ and `mysqli` installed
- [ ] Domain opens the login page (HTTP 200)
- [ ] `import.php` prints `Levels in database: 5`
- [ ] Registering a player works and redirects to login
- [ ] `setup_admin.php` created the admin; admin login shows the Admin Dashboard
- [ ] Session cookie `GRESCUE_SID` has `Secure` and `HttpOnly` (browser dev tools > Application > Cookies)
- [ ] `/includes/db.php` returns 403, `/database/` is not listed
- [ ] MySQL service has no public TCP proxy

## Deliverable 2 test record - LOCAL XAMPP (team-executed)

All 28 local test cases below were executed by the project team in their local XAMPP environment and **passed** (result reported by the team; no additional evidence was recorded).
Railway was **not** part of this testing - see the Railway section above.
| # | Test | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Open `/` while logged out | Login page shows | Matched expected result (team-reported, local XAMPP) | Passed |
| 2 | Database connection (local) | Pages load, no 500 page | Matched expected result (team-reported, local XAMPP) | Passed |
| 3 | Stop MySQL, open `/` | Generic "temporarily unavailable" page, no host/password | Matched expected result (team-reported, local XAMPP) | Passed |
| 4 | Run `import.php` twice | No errors, still 5 levels | Matched expected result (team-reported, local XAMPP) | Passed |
| 5 | Register valid player | Redirect to login with success message; `users` row has role `user` and a `$2y$` hash | Matched expected result (team-reported, local XAMPP) | Passed |
| 6 | Register duplicate username / email | Field error shown, no new row | Matched expected result (team-reported, local XAMPP) | Passed |
| 7 | Register with mismatched passwords or weak password | Field error shown | Matched expected result (team-reported, local XAMPP) | Passed |
| 8 | Add `role=admin` to the register form with dev tools | Row is still saved with role `user` | Matched expected result (team-reported, local XAMPP) | Passed |
| 9 | Player login (username, then email) | Dashboard, Player badge | Matched expected result (team-reported, local XAMPP) | Passed |
| 10 | Admin login | Admin Dashboard, same `dashboard.php` | Matched expected result (team-reported, local XAMPP) | Passed |
| 11 | Wrong password / unknown user | Same generic error text | Matched expected result (team-reported, local XAMPP) | Passed |
| 12 | Session ID after login | `GRESCUE_SID` value differs from before login | Matched expected result (team-reported, local XAMPP) | Passed |
| 13 | Logged out: open `dashboard.php`, `levels.php`, `users.php` | Redirect to login with notice | Matched expected result (team-reported, local XAMPP) | Passed |
| 14 | Logged in: open `index.php`, `register.php` | Redirect to dashboard | Matched expected result (team-reported, local XAMPP) | Passed |
| 15 | Player opens `users.php`, `level_form.php` | Redirect to dashboard, "Access denied" | Matched expected result (team-reported, local XAMPP) | Passed |
| 16 | Admin opens `users.php`, `level_form.php` | Placeholder page shows | Matched expected result (team-reported, local XAMPP) | Passed |
| 17 | Delete the logged-in player's row in phpMyAdmin, reload any page | Logged out with notice | Matched expected result (team-reported, local XAMPP) | Passed |
| 18 | Logout button | Login page, "logged out" message; back button does not show dashboard | Matched expected result (team-reported, local XAMPP) | Passed |
| 19 | Open `logout.php` by URL (GET) | Nothing happens, still logged in | Matched expected result (team-reported, local XAMPP) | Passed |
| 20 | POST to logout with wrong CSRF token | Not logged out, error notice | Matched expected result (team-reported, local XAMPP) | Passed |
| 21 | Nav links as player | Dashboard, Levels, Leaderboard, Progress, Profile | Matched expected result (team-reported, local XAMPP) | Passed |
| 22 | Nav links as admin | Dashboard, Levels, Users, Progress, Leaderboard, Profile | Matched expected result (team-reported, local XAMPP) | Passed |
| 23 | Every nav / dashboard link | Opens a page, no 404 | Matched expected result (team-reported, local XAMPP) | Passed |
| 24 | Dashboard, new player | Zero stats, empty states, Level 1 as next | Matched expected result (team-reported, local XAMPP) | Passed |
| 25 | Insert a `user_progress` row in phpMyAdmin | Dashboard stats, rank, recent scores update | Matched expected result (team-reported, local XAMPP) | Passed |
| 26 | Admin dashboard counts | Match row counts in phpMyAdmin | Matched expected result (team-reported, local XAMPP) | Passed |
| 27 | Phone width (375px) | Hamburger menu, stacked cards, no horizontal scroll | Matched expected result (team-reported, local XAMPP) | Passed |
| 28 | View source of each page | `html > head > title > body` present | Matched expected result (team-reported, local XAMPP) | Passed |

## Deliverable 3 - game engine and data integration (HISTORICAL: Sokoban game, replaced by the sliding-block game)

No new setup steps. Existing database and accounts are reused (the five sample levels already exist).
Run the automated engine tests any time (needs Node.js, no browser): `node tests/engine.test.js`

### Test record

| Area                              | What was checked                                                                                                                                                                                                                                                                                                                                                                                                   | How                                                 | Result                 |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------- | ---------------------- |
| Game engine (`assets/js/game.js`) | 70 checks: all 5 seeded levels parse; each stored solution wins in exactly its par moves and not earlier; wall, box-against-wall and box-against-box blocking; blocked moves not counted; no pulling; undo and restart restore player, boxes and count; input locked after a win; invalid layouts rejected; CRLF layouts parse; 20,000 random moves/undos/restarts compared against an independent reference model | **Executed** (Node)                                 | 70 passed, 0 failed    |
| Sample levels                     | All five are solvable; par = shortest solution (exhaustive search)                                                                                                                                                                                                                                                                                                                                                 | **Executed** (solver, before the SQL was generated) | Passed                 |
| PHP files                         | Lexer errors, bracket balance, undefined helper calls                                                                                                                                                                                                                                                                                                                                                              | Static check (before the team's runtime tests)      | No problems found      |
| JS <-> HTML                       | Every element id and JSON key used by `game.js` exists in `play.php`                                                                                                                                                                                                                                                                                                                                               | Static check only                                   | Consistent             |
| PHP, MySQL, browser               | Everything in the checklist below                                                                                                                                                                                                                                                                                                                                                                                  | Team-executed on local XAMPP                        | Passed (team-reported) |

### Deliverable 3 checklist - LOCAL XAMPP (all passed, team-reported)

The team reported that all Deliverable 3 local test cases passed; no additional evidence was recorded.
| # | Test | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Player (new account) opens Levels | Level 1 unlocked, levels 2-5 locked and greyed | Matched expected result (team-reported, local XAMPP) | Passed |
| 2 | Open `play.php?id=2` by URL as that player | Redirect to Levels with "locked" notice | Matched expected result (team-reported, local XAMPP) | Passed |
| 3 | Open `play.php?id=999` and `play.php?id=abc` | Redirect to Levels with notice | Matched expected result (team-reported, local XAMPP) | Passed |
| 4 | Level 1: arrow keys, then WASD | Player moves; page does not scroll | Matched expected result (team-reported, local XAMPP) | Passed |
| 5 | Walk into a wall | Nothing happens, moves unchanged | Matched expected result (team-reported, local XAMPP) | Passed |
| 6 | Push a box; walk away | Box moves one cell; it is not pulled back; moves count +1 each | Matched expected result (team-reported, local XAMPP) | Passed |
| 7 | Undo button and Z key | Player, box and counter go back one step; button disabled at 0 | Matched expected result (team-reported, local XAMPP) | Passed |
| 8 | Restart button and R key | Original layout, moves 0 | Matched expected result (team-reported, local XAMPP) | Passed |
| 9 | Solve level 1 | Overlay "LEVEL COMPLETE", moves shown, "Progress saved", NEW BEST | Matched expected result (team-reported, local XAMPP) | Passed |
| 10 | phpMyAdmin: `user_progress` | One row for this user and level, `best_moves` = moves | Matched expected result (team-reported, local XAMPP) | Passed |
| 11 | Replay level 1 with MORE moves | Row unchanged, still one row | Matched expected result (team-reported, local XAMPP) | Passed |
| 12 | Replay level 1 with FEWER moves | `best_moves` lowered, still one row | Matched expected result (team-reported, local XAMPP) | Passed |
| 13 | Click Replay quickly several times after winning | Still exactly one row | Matched expected result (team-reported, local XAMPP) | Passed |
| 14 | After level 1 | Level 2 unlocked, "Next Level" button works | Matched expected result (team-reported, local XAMPP) | Passed |
| 15 | Dashboard | Levels done, total moves, rank, recent scores and Continue Playing (next unlocked level) updated | Matched expected result (team-reported, local XAMPP) | Passed |
| 16 | My Progress page | Statuses, best moves and dates match the database | Matched expected result (team-reported, local XAMPP) | Passed |
| 17 | Log out, log in again | Progress and unlocks still there | Matched expected result (team-reported, local XAMPP) | Passed |
| 18 | Complete level 5 | No "Next Level" button; dashboard shows "All levels completed" | Matched expected result (team-reported, local XAMPP) | Passed |
| 19 | Second player completes some levels | Leaderboard ranks by levels then total moves; own row highlighted; per-level filter works | Matched expected result (team-reported, local XAMPP) | Passed |
| 20 | Admin: Levels page | All levels have "Test Play", no locked state | Matched expected result (team-reported, local XAMPP) | Passed |
| 21 | Admin plays and wins a level | Banner "Admin preview", "nothing was saved", no new `user_progress` row | Matched expected result (team-reported, local XAMPP) | Passed |
| 22 | Admin not on leaderboard | Not listed | Matched expected result (team-reported, local XAMPP) | Passed |
| 23 | Browser dev tools: POST `save_progress.php` with a wrong/missing `X-CSRF-Token` | 403 JSON, nothing saved | Matched expected result (team-reported, local XAMPP) | Passed |
| 24 | Same POST for a locked level, with `moves` = 0, 99999 or a text value | 403 / 422 / 400 JSON, nothing saved | Matched expected result (team-reported, local XAMPP) | Passed |
| 25 | Same POST while logged out | 401 JSON | Matched expected result (team-reported, local XAMPP) | Passed |
| 26 | Same POST as admin | 403 "Administrators cannot save progress" | Matched expected result (team-reported, local XAMPP) | Passed |
| 27 | Add `user_id` to the POST body | Ignored; the row belongs to the logged-in user | Matched expected result (team-reported, local XAMPP) | Passed |
| 28 | Phone width (375px) | Board fits the screen, D-pad visible, overlay readable | Matched expected result (team-reported, local XAMPP) | Passed |
| 29 | Stop MySQL briefly during a win | Message "could not save" + Retry button; Retry works afterwards | Matched expected result (team-reported, local XAMPP) | Passed |

---

# Deliverable 4 - CRUD, dashboards, integration and final documentation

## 1. What was added

| File                         | Status               | Purpose                                                                                     |
| ---------------------------- | -------------------- | ------------------------------------------------------------------------------------------- |
| `users.php`                  | replaced placeholder | Admin: search/list accounts, Add Player (create), delete with confirmation                  |
| `user_edit.php`              | new                  | Admin: edit a player's username, email, optional new password                               |
| `level_form.php`             | replaced placeholder | Admin: create / edit a level; server-side layout validation; live preview                   |
| `levels.php`                 | modified             | Admin buttons (+ New Level, Test Play, Edit, Delete); POST delete handler                   |
| `profile.php`                | replaced placeholder | Own username/email, password change, reset own progress                                     |
| `progress.php`               | modified             | Players reset one level or all; admins delete a record or reset a player                    |
| `includes/functions.php`     | modified             | + `check_account_duplicates()`, `analyse_level_layout()`                                    |
| `includes/confirm_modal.php` | new                  | One shared confirmation dialog (Bootstrap modal)                                            |
| `assets/js/app.js`           | new                  | Fills the dialog; draws the level preview                                                   |
| `assets/js/game.js`          | modified             | Starts a game only on pages that contain a level (so the preview page can reuse the engine) |
| `assets/css/style.css`       | modified             | Styles for the management pages                                                             |

Unchanged: authentication, sessions, `save_progress.php`, `play.php`, the SQL schema and the five sample levels.

## 2. CRUD map (every action is a real MySQLi prepared statement)

| Resource | Create                                                                                | Read                                         | Update                                                                       | Delete                                                                                                     |
| -------- | ------------------------------------------------------------------------------------- | -------------------------------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Users    | `register.php` (public) and `users.php` Add Player (admin) - `INSERT ... role 'user'` | `users.php` list/search, `profile.php`       | `profile.php` (own), `user_edit.php` (admin, players only) - `UPDATE`        | `users.php` - `DELETE`, players only, in a transaction (progress removed by cascade)                       |
| Levels   | `level_form.php` - `INSERT`                                                           | `levels.php`, `play.php` - `SELECT`          | `level_form.php?id=` - `UPDATE` (optionally clears scores, in a transaction) | `levels.php` - `DELETE` (scores removed by cascade)                                                        |
| Progress | `save_progress.php` - `INSERT` on first completion                                    | dashboard, `progress.php`, `leaderboard.php` | `save_progress.php` - `UPDATE best_moves` only when lower                    | `progress.php`, `profile.php` - `DELETE` (own: `WHERE user_id = session user`; admin: by record or player) |

Every changing action: logged in, correct role checked on the server, `POST` only, CSRF token verified, input validated, prepared statement, flash message, redirect.
Safeguards: administrators cannot be edited or deleted through the user pages (so you cannot delete yourself or the last administrator); roles can never be changed; usernames/emails are unique; levels cannot be saved unless the layout is valid.

## 3. Level layout rules (SUPERSEDED: Sokoban layout, see section B above for the current format)

Characters `# - . $ * @ +` only; all rows the same length; 3x3 up to 15x15; exactly one player; at least one box; boxes = targets; the level must not start already solved; no empty rows in the middle. Width, height and box count are calculated from the text. The browser preview only gives early feedback.
Note: the server cannot prove a level is _solvable_. Test Play every new level and enter a par only if you know a solution.

## 4. Database and setup

Tables `users`, `levels`, `user_progress` (see `database/gridlock_rescue.sql`). `user_progress` has `UNIQUE(user_id, level_id)` and both foreign keys use `ON DELETE CASCADE`. Setup steps: see "Local setup (XAMPP)" at the top of this file. No schema change was needed in Deliverable 4.
Create the first administrator: `php setup_admin.php` (command line only; refuses if an admin exists).

## 5. How to use the application

**Player:** register -> log in -> Dashboard shows progress -> Levels -> play with arrows/WASD (Z undo, R restart) -> finish a level to save the score and unlock the next -> Leaderboard, My Progress (reset a level or everything), Profile (edit details, change password).
**Administrator:** log in -> Dashboard shows counts and recent completions -> Users (search, add player, edit, delete, view a player's progress) -> Levels (+ New Level, Edit, Test Play, Delete) -> Progress Records (delete a record or reset a player) -> Profile (own details and password). Administrators have no scores and are not on the leaderboard.

## 6. How the main parts work together

- `includes/auth.php`: session, guards (`require_login`, `require_admin`, `require_user`), CSRF. Every page starts with it.
- `includes/db.php` + `functions.php`: one MySQLi connection and small helpers (`db_select_all`, `db_execute`, validators, level/progress helpers).
- `includes/navbar.php`: the one navigation menu, links chosen by role.
- `play.php` -> JSON level data -> `assets/js/game.js` (engine + page wiring) -> `save_progress.php` (validates, saves best score) -> `levels.php`/dashboard recalculate unlocking from `user_progress`.
- `includes/confirm_modal.php` + `assets/js/app.js`: every destructive button opens the same dialog, which posts the action and id with the CSRF token to the current page.

## 7. Final directory tree

```
gridlock-rescue/
  index.php  register.php  logout.php  dashboard.php
  levels.php  level_form.php  play.php  save_progress.php
  leaderboard.php  progress.php  profile.php  users.php  user_edit.php
  setup_admin.php  composer.json  .htaccess  .gitignore
  includes/  auth.php  config.php  config.local.example.php  confirm_modal.php  db.php  functions.php  navbar.php
  assets/    css/style.css  js/game.js  js/app.js  img/logo.svg
  database/  gridlock_rescue.sql  import.php
  tests/     engine.test.js
  docs/      README.md
```

## 8. Instructor requirement checklist

Local = tested by the team on local XAMPP (Deliverable 2, 3 and 4 test records in this file). Railway is the only open item.
| # | Requirement | Where | Status |
|---|---|---|---|
| 1 | Login landing page, two user types | `index.php`, `users.role` | Implemented; passed locally (D2) |
| 2 | Session variables distinguish roles | `$_SESSION['role']` | Implemented; passed locally (D2) |
| 3 | No session -> login | `require_login()` | Implemented; passed locally (D2, D4 regression) |
| 4 | Active session -> dashboard | `redirect_if_logged_in()` | Implemented; passed locally (D2) |
| 5 | `html > head > title > body` on every page | all rendering pages | Implemented; passed locally (D2, D4 test 46) |
| 6 | Navigation menu with links to the pages | `includes/navbar.php` | Implemented; passed locally (D2) |
| 7 | One shared nav file with `include` | `include 'includes/navbar.php'` | Implemented; passed locally (D2) |
| 8 | All four CRUD operations with MySQLi | section 2 | Implemented; passed locally (D4 tests 1-40) |
| 9 | Session controls content, no duplicate role pages | dashboard, levels, progress, profile, users | Implemented; passed locally (D2, D3, D4) |
| 10 | CSS + Bootstrap, responsive, ids/classes | `style.css`, Bootstrap 5 | Implemented; passed locally (D2, D3, D4 test 45) |
| - | Railway deployment | section "Railway" above | Earlier version reported live by the team; sliding-block version **not yet deployed or verified** |

## 9. Deliverable 4 test record - LOCAL XAMPP (team-executed)

All 46 Deliverable 4 test cases were executed by the project team on their local XAMPP environment and **passed** (result reported by the team; no additional evidence was recorded).
Other checks: `node tests/engine.test.js` was re-run after the `game.js` change (70 checks, 0 failures). A static review of the changed PHP files (syntax, undefined helpers, link targets, SQL column names, JS element ids) was done before the team's runtime tests.
These tests are local only. Railway has not been tested.

| #              | Test                                                                    | Expected Result                                                        | Actual Result                                        | Status |
| -------------- | ----------------------------------------------------------------------- | ---------------------------------------------------------------------- | ---------------------------------------------------- | ------ |
| **Users**      |                                                                         |                                                                        |                                                      |        |
| 1              | Admin opens Users                                                       | Table with id, username, email, role, levels, joined                   | Matched expected result (team-reported, local XAMPP) | Passed |
| 2              | Search `ma`, an email fragment, and `%`                                 | Matching rows only; `%` matches only names containing `%`              | Matched expected result (team-reported, local XAMPP) | Passed |
| 3              | Add Player (valid)                                                      | Success message; new row with role PLAYER and `$2y$` hash              | Matched expected result (team-reported, local XAMPP) | Passed |
| 4              | Add Player with a duplicate username / email / weak password / mismatch | Field errors; form stays open; no row added                            | Matched expected result (team-reported, local XAMPP) | Passed |
| 5              | Edit a player (username, email)                                         | Success; list updated; player can still log in                         | Matched expected result (team-reported, local XAMPP) | Passed |
| 6              | Edit with a duplicate username/email; with a new password               | Duplicate rejected; new password works at login                        | Matched expected result (team-reported, local XAMPP) | Passed |
| 7              | Open `user_edit.php?id=` of an admin, `?id=999`, `?id=abc`              | Redirect to Users with notice                                          | Matched expected result (team-reported, local XAMPP) | Passed |
| 8              | Delete a player who has progress (confirm dialog)                       | Dialog warns about progress; account and its `user_progress` rows gone | Matched expected result (team-reported, local XAMPP) | Passed |
| 9              | Admin row in the list                                                   | Shows "Protected", no Edit/Delete                                      | Matched expected result (team-reported, local XAMPP) | Passed |
| 10             | POST `delete_user` for own id / an admin id (dev tools)                 | Refused with message; nothing deleted                                  | Matched expected result (team-reported, local XAMPP) | Passed |
| 11             | Player opens `users.php`, `user_edit.php`; POSTs `delete_user`          | Redirect to dashboard, "Access denied"; nothing deleted                | Matched expected result (team-reported, local XAMPP) | Passed |
| 12             | POST `delete_user` without / with a wrong CSRF token                    | Refused, nothing deleted                                               | Matched expected result (team-reported, local XAMPP) | Passed |
| **Levels**     |                                                                         |                                                                        |                                                      |        |
| 13             | Create a valid level (default template)                                 | Saved; appears last on Levels with correct size and box count          | Matched expected result (team-reported, local XAMPP) | Passed |
| 14             | Layout with an invalid character (e.g. `x`)                             | Error lists it; nothing saved                                          | Matched expected result (team-reported, local XAMPP) | Passed |
| 15             | Rows of different length                                                | Error; nothing saved                                                   | Matched expected result (team-reported, local XAMPP) | Passed |
| 16             | No player / two players                                                 | Error; nothing saved                                                   | Matched expected result (team-reported, local XAMPP) | Passed |
| 17             | Boxes != targets; no boxes; already solved                              | Error; nothing saved                                                   | Matched expected result (team-reported, local XAMPP) | Passed |
| 18             | Grid 2x2 and 16x16                                                      | Size error; nothing saved                                              | Matched expected result (team-reported, local XAMPP) | Passed |
| 19             | Empty title, duplicate title, bad order, bad par                        | Field errors                                                           | Matched expected result (team-reported, local XAMPP) | Passed |
| 20             | Live preview while typing                                               | Grid redraws; message for invalid layouts                              | Matched expected result (team-reported, local XAMPP) | Passed |
| 21             | Edit an existing level and save                                         | Row updated; Levels page shows new values                              | Matched expected result (team-reported, local XAMPP) | Passed |
| 22             | Edit layout of a level with scores, tick "clear scores"                 | Level updated and its `user_progress` rows removed                     | Matched expected result (team-reported, local XAMPP) | Passed |
| 23             | Test Play a new level as admin and solve it                             | Plays; "nothing was saved"; no new `user_progress` row                 | Matched expected result (team-reported, local XAMPP) | Passed |
| 24             | A player plays the new level (after unlocking)                          | Saves normally                                                         | Matched expected result (team-reported, local XAMPP) | Passed |
| 25             | Delete a level (confirm dialog)                                         | Level and its scores removed; unlocking still works                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 26             | Player opens `level_form.php`; POSTs `delete_level`                     | Redirect to dashboard; nothing deleted                                 | Matched expected result (team-reported, local XAMPP) | Passed |
| 27             | Five sample levels still present and playable                           | Unchanged unless edited                                                | Matched expected result (team-reported, local XAMPP) | Passed |
| **Profile**    |                                                                         |                                                                        |                                                      |        |
| 28             | Open Profile (player and admin)                                         | Own details; admin has no progress summary or reset                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 29             | Change username / email                                                 | Saved; navbar shows the new name immediately                           | Matched expected result (team-reported, local XAMPP) | Passed |
| 30             | Duplicate username / email                                              | Error; nothing changed                                                 | Matched expected result (team-reported, local XAMPP) | Passed |
| 31             | Change password (correct current password)                              | Works; old password no longer logs in; stays logged in                 | Matched expected result (team-reported, local XAMPP) | Passed |
| 32             | Wrong current password / weak / mismatched new password                 | Field errors                                                           | Matched expected result (team-reported, local XAMPP) | Passed |
| 33             | Add `user_id` or `role` to the profile form (dev tools)                 | Ignored; only the logged-in account changes                            | Matched expected result (team-reported, local XAMPP) | Passed |
| **Progress**   |                                                                         |                                                                        |                                                      |        |
| 34             | Player: Reset one level                                                 | That record removed; later levels follow the unlock rule               | Matched expected result (team-reported, local XAMPP) | Passed |
| 35             | Player: Reset All My Progress                                           | All records removed; only Level 1 unlocked; account intact             | Matched expected result (team-reported, local XAMPP) | Passed |
| 36             | Dashboard and leaderboard after a reset                                 | Stats, rank, next level and leaderboard updated                        | Matched expected result (team-reported, local XAMPP) | Passed |
| 37             | Admin: Progress Records, filter `?user_id=`                             | All records / that player's records                                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 38             | Admin: delete a record; reset a player                                  | Rows removed; account intact                                           | Matched expected result (team-reported, local XAMPP) | Passed |
| 39             | Player POSTs `delete_record` / `reset_player`                           | Refused                                                                | Matched expected result (team-reported, local XAMPP) | Passed |
| 40             | Player POSTs `reset_level` for a level id they never completed          | "No saved score"; other players' rows untouched                        | Matched expected result (team-reported, local XAMPP) | Passed |
| **Regression** |                                                                         |                                                                        |                                                      |        |
| 41             | Register, login (username and email), logout, back button               | As in Deliverable 2                                                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 42             | Player opens an admin URL; logged-out user opens any page               | Redirects as before                                                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 43             | Play level 1: move, wall, push, undo, restart, win                      | As in Deliverable 3                                                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 44             | Win saves best score; unlocking; leaderboard                            | As in Deliverable 3                                                    | Matched expected result (team-reported, local XAMPP) | Passed |
| 45             | Phone width (375px): Users, Levels, Level form, Progress, Profile, game | Usable, no sideways page scroll                                        | Matched expected result (team-reported, local XAMPP) | Passed |
| 46             | View source of each new page                                            | `html > head > title > body` present                                   | Matched expected result (team-reported, local XAMPP) | Passed |

## 10. Submission preparation

- Zip name: `GridlockRescue_Project.zip`. Video name: `GridlockRescue_Demo.mp4`.
- The ZIP must contain the project folder with `database/gridlock_rescue.sql`, `assets/`, `includes/` and `docs/`.
- It must NOT contain `includes/config.local.php`, any `.env` file, or other zip files (the `.gitignore` lists them). Check the zip before submitting.
- Railway is not yet deployed or verified: complete the Railway checklist before claiming a live URL. Local XAMPP testing is complete.

## 11. Suggested demo video (about 8 minutes)

1. (0:30) Intro: project name, stack, local XAMPP, show the three database tables in phpMyAdmin.
2. (0:45) Visitor opens a protected URL -> redirected to login; register a new player (show validation errors first).
3. (0:30) Log in as the player; show the dashboard with empty states and the navbar.
4. (2:00) Play Level 1: movement, wall, push, undo, restart, win; show "progress saved"; Level 2 unlocks; show the `user_progress` row in phpMyAdmin.
5. (0:45) Leaderboard, My Progress, Profile (change username, change password).
6. (0:30) Reset one level in My Progress; dashboard updates.
7. (0:30) Logout; try the browser back button; try `users.php` while logged out.
8. (0:30) Log in as administrator: same dashboard, different content and navbar.
9. (1:00) Users: search, add player, edit, delete (show the confirmation text and the progress rows disappearing).
10. (1:30) Levels: create an invalid level (show errors), then a valid one; preview; Test Play; edit; delete.
11. (0:30) Progress Records: delete a record; show a player trying an admin URL and being refused.
12. (0:30) Show the code structure briefly (`auth.php`, `navbar.php`, one prepared statement) and close.
