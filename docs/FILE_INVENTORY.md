# Exact source → destination file inventory

Derived from comparing the uploaded `GridlockRescue_Project.zip` file bytes with this destination tree. Git metadata, local editor files, untracked secrets, and the inventory itself are excluded. The uploaded source ZIP was not edited.

**Summary:** 27 content-modified originals, 18 byte-identical originals, 4 deliberately removed source paths, 24 added packaged files (including this inventory). 24 originals changed location.

## Added files

- `.dockerignore`
- `.env.example`
- `Caddyfile`
- `Dockerfile`
- `README.md`
- `config/php-production.ini`
- `database/export_mysql.php`
- `database/import_postgres.php`
- `database/postgres_permissions.sql`
- `database/postgres_schema.sql`
- `database/postgres_seed_sliding.sql`
- `database/transfer_lib.php`
- `database/verify_postgres.php`
- `docs/CHANGES.md`
- `docs/DATA_MIGRATION.md`
- `docs/ROLLBACK.md`
- `docs/SUPABASE_SETUP.md`
- `docs/TEST_REPORT.md`
- `docs/VERCEL_DEPLOYMENT.md`
- `includes/session_pg.php`
- `public/health.php`
- `public/health_db.php`
- `tests/php/transfer_format_test.php`
- `docs/FILE_INVENTORY.md`

## Existing files moved or modified

| Original path | Delivered path | Content |
|---|---|---|
| `.gitignore` | `.gitignore` | Modified |
| `assets/css/style.css` | `public/assets/css/style.css` | Unchanged bytes |
| `assets/img/logo.svg` | `public/assets/img/logo.svg` | Unchanged bytes |
| `assets/js/app.js` | `public/assets/js/app.js` | Unchanged bytes |
| `assets/js/game.js` | `public/assets/js/game.js` | Unchanged bytes |
| `assets/js/level-editor.js` | `public/assets/js/level-editor.js` | Unchanged bytes |
| `assets/js/sliding-engine.js` | `public/assets/js/sliding-engine.js` | Unchanged bytes |
| `composer.json` | `composer.json` | Modified |
| `dashboard.php` | `public/dashboard.php` | Modified |
| `database/gridlock_rescue.sql` | `database/legacy_mysql/gridlock_rescue.sql` | Unchanged bytes |
| `database/migrate_sliding.sql` | `database/legacy_mysql/migrate_sliding.sql` | Unchanged bytes |
| `database/migrate_user_status.sql` | `database/legacy_mysql/migrate_user_status.sql` | Unchanged bytes |
| `docs/README.md` | `docs/README_RAILWAY_LEGACY.md` | Unchanged bytes |
| `includes/auth.php` | `includes/auth.php` | Modified |
| `includes/config.php` | `includes/config.php` | Modified |
| `includes/db.php` | `includes/db.php` | Modified |
| `includes/functions.php` | `includes/functions.php` | Modified |
| `index.php` | `public/index.php` | Modified |
| `leaderboard.php` | `public/leaderboard.php` | Modified |
| `level_form.php` | `public/level_form.php` | Modified |
| `levels.php` | `public/levels.php` | Modified |
| `logout.php` | `public/logout.php` | Modified |
| `play.php` | `public/play.php` | Modified |
| `profile.php` | `public/profile.php` | Modified |
| `progress.php` | `public/progress.php` | Modified |
| `register.php` | `public/register.php` | Modified |
| `save_progress.php` | `public/save_progress.php` | Modified |
| `setup_admin.php` | `tools/setup_admin.php` | Modified |
| `tests/account_status_logic.test.js` | `tests/account_status_logic.test.js` | Modified |
| `tests/account_status_sql.test.js` | `tests/account_status_sql.test.js` | Modified |
| `tests/editor.test.js` | `tests/editor.test.js` | Modified |
| `tests/engine.test.js` | `tests/engine.test.js` | Modified |
| `tests/php/account_status_test.php` | `tests/php/account_status_test.php` | Modified |
| `tests/sql_seed.test.js` | `tests/sql_seed.test.js` | Modified |
| `tests/ui-smoke.test.js` | `tests/ui-smoke.test.js` | Modified |
| `user_edit.php` | `public/user_edit.php` | Modified |
| `users.php` | `public/users.php` | Modified |

## Removed source files (not removed from original Railway ZIP)

- `.htaccess`
- `database/import.php`
- `includes/config.local.example.php`
- `includes/config.local.php`

## Existing files retained with unchanged bytes

- `includes/confirm_modal.php`
- `includes/navbar.php`
- `includes/sliding.php`
- `tests/fixtures/sliding_levels.json`
- `tests/fixtures/sliding_php_cases.json`
- `tests/php/level_edit_policy_test.php`
- `tests/php/sliding_test.php`
- `tests/run_all.js`

See `CHANGES.md` for the architectural purpose of each change.
