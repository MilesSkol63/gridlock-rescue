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

## Railway deployment (Railpack, no Dockerfile)
Railpack detects PHP from `index.php` / `composer.json` and serves it with FrankenPHP. `composer.json` requires PHP >= 8.2 and `ext-mysqli`, so the extension is installed automatically.
1. Push the project to GitHub (check that `includes/config.local.php` is NOT in the repo).
2. Railway -> New Project -> Deploy from GitHub repo. This creates the PHP service.
3. In the same project: **+ New -> Database -> Add MySQL**. Keep the default service name `MySQL`. Do not enable a public TCP proxy for it.
4. PHP service -> **Variables** -> add (names must match exactly):
   - `MYSQLHOST` = `${{MySQL.MYSQLHOST}}`  (if the connection fails, use `${{MySQL.RAILWAY_PRIVATE_DOMAIN}}`)
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

### Railway verification checklist
- [ ] Build log shows PHP 8.2+ and `mysqli` installed
- [ ] Domain opens the login page (HTTP 200)
- [ ] `import.php` prints `Levels in database: 5`
- [ ] Registering a player works and redirects to login
- [ ] `setup_admin.php` created the admin; admin login shows the Admin Dashboard
- [ ] Session cookie `GRESCUE_SID` has `Secure` and `HttpOnly` (browser dev tools > Application > Cookies)
- [ ] `/includes/db.php` returns 403, `/database/` is not listed
- [ ] MySQL service has no public TCP proxy

## Test cases (fill in Actual Result / Status while testing)
| # | Test | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Open `/` while logged out | Login page shows | | Not run |
| 2 | Database connection (local) | Pages load, no 500 page | | Not run |
| 3 | Stop MySQL, open `/` | Generic "temporarily unavailable" page, no host/password | | Not run |
| 4 | Run `import.php` twice | No errors, still 5 levels | | Not run |
| 5 | Register valid player | Redirect to login with success message; `users` row has role `user` and a `$2y$` hash | | Not run |
| 6 | Register duplicate username / email | Field error shown, no new row | | Not run |
| 7 | Register with mismatched passwords or weak password | Field error shown | | Not run |
| 8 | Add `role=admin` to the register form with dev tools | Row is still saved with role `user` | | Not run |
| 9 | Player login (username, then email) | Dashboard, Player badge | | Not run |
| 10 | Admin login | Admin Dashboard, same `dashboard.php` | | Not run |
| 11 | Wrong password / unknown user | Same generic error text | | Not run |
| 12 | Session ID after login | `GRESCUE_SID` value differs from before login | | Not run |
| 13 | Logged out: open `dashboard.php`, `levels.php`, `users.php` | Redirect to login with notice | | Not run |
| 14 | Logged in: open `index.php`, `register.php` | Redirect to dashboard | | Not run |
| 15 | Player opens `users.php`, `level_form.php` | Redirect to dashboard, "Access denied" | | Not run |
| 16 | Admin opens `users.php`, `level_form.php` | Placeholder page shows | | Not run |
| 17 | Delete the logged-in player's row in phpMyAdmin, reload any page | Logged out with notice | | Not run |
| 18 | Logout button | Login page, "logged out" message; back button does not show dashboard | | Not run |
| 19 | Open `logout.php` by URL (GET) | Nothing happens, still logged in | | Not run |
| 20 | POST to logout with wrong CSRF token | Not logged out, error notice | | Not run |
| 21 | Nav links as player | Dashboard, Levels, Leaderboard, Progress, Profile | | Not run |
| 22 | Nav links as admin | Dashboard, Levels, Users, Progress, Leaderboard, Profile | | Not run |
| 23 | Every nav / dashboard link | Opens a page, no 404 | | Not run |
| 24 | Dashboard, new player | Zero stats, empty states, Level 1 as next | | Not run |
| 25 | Insert a `user_progress` row in phpMyAdmin | Dashboard stats, rank, recent scores update | | Not run |
| 26 | Admin dashboard counts | Match row counts in phpMyAdmin | | Not run |
| 27 | Phone width (375px) | Hamburger menu, stacked cards, no horizontal scroll | | Not run |
| 28 | View source of each page | `html > head > title > body` present | | Not run |
