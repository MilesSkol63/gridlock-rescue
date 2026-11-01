# Gridlock Rescue - Deliverable 2 (foundation + authentication)

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

## Deliverable 2 test record - LOCAL XAMPP

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

| Area                              | What was checked                                                                                                                                                                                                                                                                                                                                                                                                   | How                                                      | Result                |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------- | --------------------- |
| Game engine (`assets/js/game.js`) | 70 checks: all 5 seeded levels parse; each stored solution wins in exactly its par moves and not earlier; wall, box-against-wall and box-against-box blocking; blocked moves not counted; no pulling; undo and restart restore player, boxes and count; input locked after a win; invalid layouts rejected; CRLF layouts parse; 20,000 random moves/undos/restarts compared against an independent reference model | **Executed** (Node)                                      | 70 passed, 0 failed   |
| Sample levels                     | All five are solvable; par = shortest solution (exhaustive search)                                                                                                                                                                                                                                                                                                                                                 | **Executed** (solver, before the SQL was generated)      | Passed                |
| PHP files                         | Lexer errors, bracket balance, undefined helper calls                                                                                                                                                                                                                                                                                                                                                              | Static check only                                        | No problems found     |
| JS <-> HTML                       | Every element id and JSON key used by `game.js` exists in `play.php`                                                                                                                                                                                                                                                                                                                                               | Static check only                                        | Consistent            |
| PHP, MySQL, browser               | Everything in the checklist below                                                                                                                                                                                                                                                                                                                                                                                  | **NOT executed** (no PHP/MySQL in the build environment) | Pending - team to run |

### Deliverable 3 checklist for the team (local XAMPP)

| #   | Test                                                                            | Expected Result                                                                                  | Actual Result | Status  |
| --- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ------------- | ------- | --- |
| 1   | Player (new account) opens Levels                                               | Level 1 unlocked, levels 2-5 locked and greyed                                                   |               | Not run | yes |
| 2   | Open `play.php?id=2` by URL as that player                                      | Redirect to Levels with "locked" notice                                                          |               | Not run | yes |
| 3   | Open `play.php?id=999` and `play.php?id=abc`                                    | Redirect to Levels with notice                                                                   |               | Not run | yes |
| 4   | Level 1: arrow keys, then WASD                                                  | Player moves; page does not scroll                                                               |               | Not run | yes |
| 5   | Walk into a wall                                                                | Nothing happens, moves unchanged                                                                 |               | Not run | yes |
| 6   | Push a box; walk away                                                           | Box moves one cell; it is not pulled back; moves count +1 each                                   |               | Not run | yes |
| 7   | Undo button and Z key                                                           | Player, box and counter go back one step; button disabled at 0                                   |               | Not run | yes |
| 8   | Restart button and R key                                                        | Original layout, moves 0                                                                         |               | Not run | yes |
| 9   | Solve level 1                                                                   | Overlay "LEVEL COMPLETE", moves shown, "Progress saved", NEW BEST                                |               | Not run | yes |
| 10  | phpMyAdmin: `user_progress`                                                     | One row for this user and level, `best_moves` = moves                                            |               | Not run | yes |
| 11  | Replay level 1 with MORE moves                                                  | Row unchanged, still one row                                                                     |               | Not run | yes |
| 12  | Replay level 1 with FEWER moves                                                 | `best_moves` lowered, still one row                                                              |               | Not run | yes |
| 13  | Click Replay quickly several times after winning                                | Still exactly one row                                                                            |               | Not run | yes |
| 14  | After level 1                                                                   | Level 2 unlocked, "Next Level" button works                                                      |               | Not run | yes |
| 15  | Dashboard                                                                       | Levels done, total moves, rank, recent scores and Continue Playing (next unlocked level) updated |               | Not run | yes |
| 16  | My Progress page                                                                | Statuses, best moves and dates match the database                                                |               | Not run | yes |
| 17  | Log out, log in again                                                           | Progress and unlocks still there                                                                 |               | Not run |
| 18  | Complete level 5                                                                | No "Next Level" button; dashboard shows "All levels completed"                                   |               | Not run |
| 19  | Second player completes some levels                                             | Leaderboard ranks by levels then total moves; own row highlighted; per-level filter works        |               | Not run | yes |
| 20  | Admin: Levels page                                                              | All levels have "Test Play", no locked state                                                     |               | Not run | yes |
| 21  | Admin plays and wins a level                                                    | Banner "Admin preview", "nothing was saved", no new `user_progress` row                          |               | Not run | yes |
| 22  | Admin not on leaderboard                                                        | Not listed                                                                                       |               | Not run | yes |
| 23  | Browser dev tools: POST `save_progress.php` with a wrong/missing `X-CSRF-Token` | 403 JSON, nothing saved                                                                          |               | Not run | yes |
| 24  | Same POST for a locked level, with `moves` = 0, 99999 or a text value           | 403 / 422 / 400 JSON, nothing saved                                                              |               | Not run | yes |
| 25  | Same POST while logged out                                                      | 401 JSON                                                                                         |               | Not run | yes |
| 26  | Same POST as admin                                                              | 403 "Administrators cannot save progress"                                                        |               | Not run | yes |
| 27  | Add `user_id` to the POST body                                                  | Ignored; the row belongs to the logged-in user                                                   |               | Not run | yes |
| 28  | Phone width (375px)                                                             | Board fits the screen, D-pad visible, overlay readable                                           |               | Not run | yes |
| 29  | Stop MySQL briefly during a win                                                 | Message "could not save" + Retry button; Retry works afterwards                                  |               | Not run | yes |
