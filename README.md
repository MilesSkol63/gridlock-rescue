# Gridlock Rescue — separate Supabase + Vercel deployment

**Status (2026-11-23): Code migration prepared and static/regression checks passed. NOT connected to Railway, NOT imported into a live Supabase database, NOT Docker-built, and NOT deployed to Vercel.** Treat this as a staging candidate until the integration checklist is completed.

This is a fork of the supplied **latest account-status Gridlock Rescue** application, **not** a rebuild of the puzzle. Its 6×6 sliding-block engine, current pages, player/admin workflows, password hashes, level editing, completion replay, score semantics and historical Sokoban data formats were retained. It uses **PHP 8.4 + PDO PostgreSQL**, Supabase PostgreSQL, **FrankenPHP/Caddy** and Vercel's **Container runtime (Beta)**. The uploaded Railway project and live Railway MySQL database were not accessed or changed.

## Start here

1. Read [Supabase setup](docs/SUPABASE_SETUP.md) and [data transfer guide](docs/DATA_MIGRATION.md) **before** importing production data.
2. Connect a **separate** Supabase project and migrate a consistent Railway **read-only snapshot**. Source is never written to by the tools.
3. Set `DATABASE_URL` only in your private shell/Vercel secret settings, not in a committed `.env` file. Use the **shared transaction pooler**, with a **dedicated restricted runtime login role**.
4. Follow [Vercel deployment](docs/VERCEL_DEPLOYMENT.md) using a separate `supabase-migration` Git branch. Keep Railway connected to its original production branch.
5. Complete [test report / acceptance checklist](docs/TEST_REPORT.md) before exposing the new deployment to users.
6. Keep [rollback instructions](docs/ROLLBACK.md) available. **Never run `--replace-destination` without a verified backup and explicit understanding of lost Supabase-only writes.**

## Important implementation choices

- **PDO PgSQL + parameter binding:** `includes/db.php` uses emulated *client-side* parameter binding, not unsafe string concatenation. This avoids server-prepared-statement incompatibilities on Supabase's shared transaction pooler while retaining parameterized SQL. PostgreSQL `BOOLEAN` values are explicitly normalized in database helpers.
- **Authentication:** The original PHP auth remains; Supabase Auth is not used. A login/regenerated session persists via the `public.app_sessions` table. The session handler holds a transaction-scoped advisory lock until session close so concurrent requests cannot overwrite each other's changes. The handler opens **one additional database connection for each active authenticated PHP request**; the session transaction pins a backend while the request processes. Benchmark concurrent traffic against the actual Supabase pooler limit; close sessions early if appropriate after careful review, or use managed Redis if this design is not economical at scale.
- **Server status checks:** Each protected request queries the account's current active status. A disabled user loses access on their next authenticated request; no user-delete endpoint is provided. App role has **no `DELETE` grant on users**.
- **Case-insensitive uniqueness:** `lower(username)` and `lower(email)` unique indexes are used and query predicates match; this preserves case-insensitive behavior for the application's ASCII usernames and common email case differences. MySQL `utf8mb4_unicode_ci` also has additional accent/collation folding semantics, so inspect legacy non-ASCII imported identifiers for exceptional collisions/differences before final cutover.
- **UTC date storage:** Original MySQL `TIMESTAMP` values are exported with the source session set to UTC and imported as UTC `timestamp without time zone`. `user_progress.updated_at` uses a PostgreSQL update trigger.
- **ID and history preservation:** Export/import covers all three supplied core tables with *all* rows, including archived `sokoban` records, the expert level, hashes, disabled accounts and every progress record. IDs are preserved, identity counters are advanced, and per-table SHA-256 of canonical rows is checked. Export **fails closed** if the live schema includes different or extra tables/columns; update the mapping after inspecting that production schema instead of discarding data.
- **No local persistent files:** Runtime sessions, scores and account records are in PostgreSQL. The container serves only `public/`; private includes, migration scripts and docs are not copied to the web root.
- **Demo levels:** `database/postgres_seed_sliding.sql` is only for a **new disposable demo/test database**. Never apply it to a database receiving the Railway snapshot.

## Files

```text
Dockerfile                   PHP 8.4 FrankenPHP image with pdo_pgsql, mbstring
Caddyfile                    public/ root on PORT (default 80)
config/php-production.ini    secure production PHP settings
public/                      all existing public pages, game assets, health checks
includes/                    PDO, authentication, session handler, game server engine
database/postgres_schema.sql full PostgreSQL schema and progress timestamp trigger
database/postgres_permissions.sql limited direct-Postgres application permissions
database/export_mysql.php    consistent read-only Railway snapshot, JSONL + hashes
database/import_postgres.php transactional import, verification + identity restarts
database/verify_postgres.php independent read-only full-field comparison
```

## Local prerequisites / basic tests

Install Docker Desktop or equivalent, Git, Node.js and CLI PHP with needed extensions. The **runtime** image installs `pdo_pgsql` and `mbstring`. Exporting from MySQL on your own machine additionally requires `pdo_mysql`.

```sh
node tests/run_all.js
php tests/php/sliding_test.php
php tests/php/level_edit_policy_test.php
php tests/php/transfer_format_test.php
docker build -t gridlock-rescue-supabase .
# Place your real secrets in an UNTRACKED env file, NOT in the ZIP or repository.
docker run --rm --env-file /outside-repository/gridlock.env -p 8080:80 gridlock-rescue-supabase
# In another terminal:
curl -i http://localhost:8080/health.php
curl -i http://localhost:8080/health_db.php
```

Expected health responses: PHP-only `/health.php` → `200`; database `/health_db.php` → `200` only when PgSQL is reachable and all required tables exist. Private `/includes/config.php`, `/database/postgres_schema.sql` and `/tools/setup_admin.php` **must** return `404`.

## Full instructions

- [Supabase setup, security, connection pooling](docs/SUPABASE_SETUP.md)
- [Data migration, integrity checks, final snapshot](docs/DATA_MIGRATION.md)
- [Vercel and Docker deployment, branch separation](docs/VERCEL_DEPLOYMENT.md)
- [Rollback and independent-data warning](docs/ROLLBACK.md)
- [Tests actually run and unverified integration matrix](docs/TEST_REPORT.md)
- [Migration checklist and changed-file inventory](docs/CHANGES.md)
- [Legacy Railway README (historical reference only)](docs/README_RAILWAY_LEGACY.md)

Official references: [Vercel PHP Docker guide](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker), [Vercel Container runtime](https://vercel.com/docs/functions/runtimes/container), [Supabase PostgreSQL connection modes](https://supabase.com/docs/guides/database/connecting-to-postgres), [Supabase production checklist](https://supabase.com/docs/guides/deployment/going-into-prod).
