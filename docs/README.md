# Gridlock Rescue - Project Documentation (Deliverables 1-4)

Complete college endterm project: PHP / MySQL (MySQLi) / Bootstrap 5 puzzle game with player and administrator roles. Local XAMPP testing is complete for Deliverables 2, 3 and 4 (team-executed). **Railway deployment is still PENDING** - it has not been deployed or verified.

Requirements: PHP 8.2+ with the `mysqli` extension, MySQL 8 or MariaDB 10.4+, Apache (XAMPP) locally or Railway in production.

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

## Railway deployment (Railpack, no Dockerfile) - PENDING: not yet deployed or tested

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

### Railway verification checklist (PENDING - NOT YET PERFORMED)

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
Railway was **not** part of this testing - see "Railway verification checklist (PENDING)" above.
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

## Deliverable 3 - game engine and data integration

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

## 3. Level layout rules (server-side, `analyse_level_layout()`)

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
| - | Railway deployment | section "Railway" above | **PENDING - not deployed or verified** |

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
