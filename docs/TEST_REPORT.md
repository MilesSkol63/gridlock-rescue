# Migration test report — 2026-11-23

**No live deployment or production database was available in this environment.** This report distinguishes tests that **actually ran** from source inspection and the manual tests the user still must perform. The acceptance criteria demanding a successful PostgreSQL connection, Docker build, instance-spanning sessions and verified Vercel URL remain **OPEN** until independently confirmed.

## Actually executed here

| Test | Method | Result |
|---|---|---|
| Sliding-block JavaScript engine | `node tests/run_all.js` → engine | **PASS: 129 / 129 checks** |
| Administrator level editor JS logic | `node tests/run_all.js` → editor | **PASS: 35 / 35 checks** |
| PostgreSQL optional seed fixture match | `node tests/run_all.js` → sql_seed | **PASS: 10 / 10 checks** |
| Account-status PostgreSQL static SQL patterns | `node tests/run_all.js` → account_status_sql | **PASS: 15 / 15 checks** |
| Account-status state simulation | `node tests/run_all.js` → account_status_logic | **PASS: 19 / 19 checks** — SQLite simulation, **not** PostgreSQL integration |
| Public/JS UI smoke inspection | `node tests/run_all.js` → ui-smoke | **PASS: 47 / 47 checks** — static JS validation, not a browser deployment |
| PHP sliding-block replay/validation | `php tests/php/sliding_test.php` | **PASS: 116 checks** |
| PHP level-layout edit reset policy | `php tests/php/level_edit_policy_test.php` | **PASS: 5 checks** |
| PHP migration-format conversions | `php tests/php/transfer_format_test.php` | **PASS: 27 checks** — canonical row types, sensitive-field fail-closed behavior, booleans, URI/TLS parsing |
| PHP syntax for all bundled PHP files | `find ... -name '*.php' -exec php -l ...` | **PASS: 32 PHP files linted** |
| MySQLi dependency scan | Search active `public/`, `includes/`, `tools/`, `database/*.php` | **Static check: no executable MySQLi runtime path**; CLI source export intentionally uses **PDO MySQL** |

**Important distinction:** fixture, static, simulated and unit tests do not prove that Supabase returns the expected PDO types, that Caddy routes all pages, that a live transaction pooler supports the session handler, or that the deployment runs across multiple Vercel containers.

## Tests NOT executed here (required for final acceptance)

| Area | Status | Verification needed |
|---|---|---|
| Live Supabase connection | **NOT RUN** | `DATABASE_URL` with real custom role, TLS and pooler, health DB endpoint |
| Live Railway export | **NOT RUN** | source read-only credentials and network access; inspect actual schema/inventory |
| Production record migration | **NOT RUN** | run exporter/importer/verifier; confirm all three tables, historical and disabled rows |
| PostgreSQL integration test | **NOT RUN** | `GRIDLOCK_TEST_DATABASE=1` and isolated database with `_test` suffix; run `tests/php/account_status_test.php` |
| Automatic `updated_at` trigger | **NOT RUN** | update progress rows and verify timestamp behavior on real PG |
| Unique/foreign-key/role grants | **NOT RUN** | run actual SQL tests on disposable PG, including case-only duplicate rejection |
| PostgreSQL live session concurrency | **NOT RUN** | simultaneous requests per SID; locks, session expiration, logout and active-session invalidation |
| Docker build, runtime module inventory | **NOT RUN** | Docker unavailable in analysis environment; build and check `php -m` |
| Browser/game/admin end-to-end | **NOT RUN** | run all six levels and full admin test matrix on disposable PG |
| Vercel beta Container build/deployment | **NOT RUN** | actual separate Vercel project and connected Git branch |
| Multi-instance Vercel session persistence | **NOT RUN** | cold starts and concurrent Vercel instances, inspect session lock behavior |
| Real-world performance/quota under load | **NOT RUN** | Supabase pooler connection quota and Vercel function load testing |

## Run the PostgreSQL integration test SAFELY

**Never run destructive tests against the actual Railway database or your final Supabase import.** Set up a disposable **PostgreSQL database** with a name ending in `_test` and apply `postgres_schema.sql` and optionally `postgres_seed_sliding.sql` (only for this disposable DB). Both `DATABASE_URL` and `MIGRATION_DATABASE_URL` **must** refer to the same isolated database ending in `_test`; runtime must be able to open sessions, owner must clean up test users. Then:

```sh
GRIDLOCK_TEST_DATABASE=1 php tests/php/account_status_test.php
```

The test checks status updates, protected-session account validation, preservation of progress and last-admin rules, then cleans its own test records. It refuses to run without the special flag and database-name suffix, but these are guardrails, not a substitute for isolating credentials and database instances.

## Mandatory manual acceptance checklist before launch

- [ ] Inspect live Railway schema and any extra tables, verify read-only snapshot.
- [ ] PostgreSQL schema, constraints, indexes and active-status data checked on real database.
- [ ] Exact export/import SHA-256 and counts verified including all archived Sokoban rows, password hashes, IDs and history.
- [ ] Runtime role has only intended privileges and **not DELETE on users**.
- [ ] HTTP/HTTPS login, registration, sessions, logout, password verification, CSRF, case-insensitive uniqueness.
- [ ] Disabled user rejected on next authenticated page/API request; self/last-admin protections work under concurrency.
- [ ] All six sliding levels, mouse/touch/keyboard, undo, collision, replay, completion, score save, unlock, progress, leaderboard.
- [ ] User search/list/edit/status and level CRUD/editor/progress reset work; no delete-user controls.
- [ ] All PHP endpoints, static assets, health checks and private-file 404s checked on actual Docker and Vercel.
- [ ] Real concurrent sessions / multi-instance scale, timeouts and Supabase connection quotas checked.
- [ ] Secrets absent from frontend, logs, ZIP and Git; backups/rollback rehearsed; Railway remains operational.

**Result: CODE-LEVEL REGRESSION CHECKS PASSED, LIVE INTEGRATION/DEPLOYMENT NOT VERIFIED.** Do not mark this release production-ready solely on this report.
