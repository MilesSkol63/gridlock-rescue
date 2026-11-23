# Migration checklist and file inventory

This migration is a **separate destination codebase** generated from the supplied latest account-status ZIP; the provided original ZIP and external Railway deployment were left unchanged.

## Review checklist

- [x] Inspect original three-table MySQL schema, account-disable logic, server-side completion replay, level CRUD, JS tests and public route dependencies.
- [x] Convert active PHP SQL operations to PostgreSQL/PDO, replace query helpers, preserve update transaction locks and custom authentication.
- [x] Add a normalized boolean helper so PostgreSQL `t/f` / `true/false` do not break existing account-status views.
- [x] Add case-insensitive indexed login/search/uniqueness behavior.
- [x] Create the exact three-table PostgreSQL schema, ID constraints, FKs, `app_sessions` and `updated_at` trigger.
- [x] Keep `users` delete permission denied and retain all levels/progress, including archived Sokoban records.
- [x] Add consistent MySQL read-only export, transactional PostgreSQL import, SHA-256 field verification, and identity restart.
- [x] Implement external PgSQL sessions using transaction-level advisory locks (not local files).
- [x] Move private code out of Caddy document root; block web setup scripts by excluding them.
- [x] Add PHP 8.4 FrankenPHP Dockerfile, Caddyfile, environment placeholder and two health endpoints.
- [x] Run available JS/PHP offline regressions, static checks and lints; report unavailable integration tests accurately.
- [ ] Verify live Railway schema/data vs snapshot (requires user-side credentials/network).
- [ ] Build and exercise Docker locally (requires Docker).
- [ ] Run PgSQL integration tests against isolated test database.
- [ ] Test live Supabase import and user/status/level queries.
- [ ] Verify actual Vercel Beta Container build, multi-instance sessions, load, production URLs and all UI flows.

## New files

- `README.md` (root migration/quick start)
- `.env.example` (placeholder only)
- `.dockerignore`
- `Dockerfile`
- `Caddyfile`
- `composer.json` (metadata only; **no Composer dependency**)
- `config/php-production.ini`
- `includes/session_pg.php`
- `public/health.php`, `public/health_db.php`
- `database/postgres_schema.sql`
- `database/postgres_permissions.sql`
- `database/postgres_seed_sliding.sql` (isolated demo/test only)
- `database/transfer_lib.php`
- `database/export_mysql.php`
- `database/import_postgres.php`
- `database/verify_postgres.php`
- `docs/SUPABASE_SETUP.md`, `docs/VERCEL_DEPLOYMENT.md`, `docs/DATA_MIGRATION.md`, `docs/ROLLBACK.md`, `docs/TEST_REPORT.md`, `docs/CHANGES.md`

## Modified files / intentional moves

- `includes/config.php`: environment-based PostgreSQL URI configuration (local static MySQL config removed).
- `includes/db.php`: mysqli replaced with PDO PgSQL.
- `includes/functions.php`: PDO helpers, PostgreSQL SQL, active-account consistency, boolean coercion, safe transaction handling.
- `includes/auth.php`: shared Pg-backed sessions, server-side active-account checks and production cookie settings.
- `includes/navbar.php`: retained unchanged; private include references in its public callers were updated.
- All existing public pages moved from project root into `public/` without changing URL path names: `index.php`, `register.php`, `logout.php`, `dashboard.php`, `levels.php`, `level_form.php`, `play.php`, `save_progress.php`, `progress.php`, `leaderboard.php`, `profile.php`, `users.php`, `user_edit.php`.
- `assets/css/*`, `assets/js/*`, `assets/img/*` moved to `public/assets/*`, and public PHP include paths changed accordingly.
- `setup_admin.php` moved to **CLI-only** `tools/setup_admin.php` (not included in runtime image).
- Original MySQL setup/migration SQL files moved to `database/legacy_mysql/` for archival comparison; never import them into Supabase.
- Original `docs/README.md` preserved as `docs/README_RAILWAY_LEGACY.md` for historical reference.
- `tests/*.js` updated to the new public asset paths and PostgreSQL schema checks.
- `tests/php/account_status_test.php` updated to PostgreSQL, and guarded to isolated `_test` DB credentials before opening sessions.
- Game logic in `includes/sliding.php`, existing puzzle layouts and client-side game mechanics remain fundamentally unchanged.

## Removed from new destination (original Railway ZIP still retains them)

- `includes/config.local.php` and `includes/config.local.example.php` (old local credentials/config patterns).
- `.htaccess` (Apache rules not used by Caddy).
- `database/legacy_mysql/import.php` (old MySQLi importer, incompatible with destination; historical `.sql` retained).
- No live Railway files or databases were removed or changed.

## Architectural limits / deployment considerations

1. Session locking is correct only once verified against the **real Supabase pooler**, especially under concurrent requests. It retains a backend database transaction per session-request until close; this can cause pooler bottlenecks.
2. The canonical export mapping covers all **known** 3-table current schema fields. It is **fail-closed** for extra production columns/tables, so unexamined Railway schema drift is a hard stop.
3. MySQL `utf8mb4_unicode_ci` and PostgreSQL `lower(...)` are not byte-for-byte identical for all Unicode collation cases. The user-facing usernames validated by the app are ASCII, but imported non-ASCII historical fields require review.
4. The code has no Supabase object storage integration because the provided app does not use persistent user uploads. If production differs, stop and add one before moving such a feature.
5. No automatic deployment cutover, cross-platform write sync or schema mutations of the Railway installation are performed.

The exact byte-comparison inventory is in [FILE_INVENTORY.md](FILE_INVENTORY.md).
