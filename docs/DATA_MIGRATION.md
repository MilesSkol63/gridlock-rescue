# Railway MySQL → Supabase PostgreSQL: consistent, verified migration

## Non-negotiable safety

- **Railway is the source, read-only**. These tools only execute `SELECT`, session settings and a read-only transaction against MySQL; they do not modify Railway application files or MySQL data.
- Use **separate** source `MYSQL_SOURCE_URL` (MySQL read-only account), target **OWNER** `MIGRATION_DATABASE_URL`, and restricted runtime `DATABASE_URL` variables. Never paste any secret in chat, Git or README. Do not point the migration owner URL at the original Railway database.
- Save an **independent offline MySQL backup** as well as the canonical JSONL snapshot. Protect archives as sensitive: they contain account identifiers and password hashes (not plaintext passwords).
- The scripts intentionally support the three source tables verified in the supplied current SQL: `users`, `levels`, `user_progress`. **Export stops if production has more/different tables or columns.** Inspect and extend the schema, transfer column map and verification instead of skipping extra historical records.
- If Railway is still accepting writes after the first snapshot, Supabase becomes a **diverging copy**. Never pretend it is continuously synced. For an eventual cutover, briefly stop new production writes by an operational method you control, create a final consistent snapshot, verify, then switch traffic; don't turn off the original deployment until validation is complete.

## 1. Back up the source safely

Install the MySQL or MariaDB command-line client on a **trusted machine** with source DB network access. The following is a generic example; confirm your actual environment's credential and network settings. Use a temporary dedicated read-only database user if possible. `mysqldump` may need `--no-tablespaces` or `--column-statistics=0` depending on server/client versions; do not force destructive schema changes to make it work.

```sh
# Replace the host/user/database with values from Railway, NOT from this file.
# The -p flag asks for the password; do not put the password on the command line.
mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
  -h SOURCE_MYSQL_HOST -u READONLY_SOURCE_USER -p SOURCE_DB_NAME \
  > /secure-backups/railway-mysql-backup.sql
```

This `.sql` file is an **offline MySQL backup**, not an importable PostgreSQL dump. Validate that the file is not empty, store it encrypted and never add it to your application repository.

## 2. Make a source-consistent, database-aware export

On a machine with **PHP 8.4 CLI + `pdo_mysql`**, configure `MYSQL_SOURCE_URL` **outside the repository** as a `mysql://USER:PASSWORD@HOST:PORT/DATABASE` URI using the Railway read-only user's real connection parameters. URL-encode special characters in user/password. Make sure the machine can reach the Railway MySQL host; if the database only exposes a private Railway network, arrange an authorized export context rather than altering the live app.

```sh
# Use an empty/unused path, not a source repository directory.
php database/export_mysql.php /secure-backups/gridlock-railway-snapshot-01
```

The exporter starts a MySQL **REPEATABLE READ / consistent read-only snapshot**, sets MySQL session timezone to UTC, rejects unexpected tables/columns and streams one JSON record at a time without holding an entire table in memory. It creates `users.jsonl`, `levels.jsonl`, `user_progress.jsonl`, and `manifest.json` with row counts and SHA-256 checksums; files are chmod-restricted on compatible systems. If an error occurs it rolls back and cleans up incomplete output where possible. **Check the resulting snapshot carefully and keep it private.**

You must not assume a pre-existing Railway SQL dump uses exactly the latest schema: the live database was not accessible here and must be inspected at export time. If the export refuses a table/column difference, stop and document that difference before updating the transfer map.

## 3. Prepare an EMPTY destination

In the separately created Supabase project, execute `database/postgres_schema.sql` and `database/postgres_permissions.sql` as described in [SUPABASE_SETUP.md](SUPABASE_SETUP.md). **Do not run the demo seed script.** Create the restricted runtime role separately. The destination must have zero rows in `users`, `levels`, `user_progress`, **and** `app_sessions` for initial import; the importer checks all four tables and refuses nonempty targets.

Store `MIGRATION_DATABASE_URL` privately in the CLI process environment and use a **database owner** URI from the dashboard with `sslmode=require`. A direct IPv6 connection or session pooler may be required, depending on your network. **Never add this privileged URL to Vercel runtime variables.**

## 4. Import transactionally and verify

```sh
php database/import_postgres.php /secure-backups/gridlock-railway-snapshot-01
php database/verify_postgres.php /secure-backups/gridlock-railway-snapshot-01
```

The importer verifies manifest/sha256 first, begins one destination transaction, locks relevant tables, rejects non-empty targets, imports users → levels → user_progress **preserving explicit IDs**, sets `GENERATED BY DEFAULT AS IDENTITY` next values greater than existing ID maxima, recomputes every table's canonical SHA-256, and **commits only if every row, column and count agrees**. Any failure rolls back all imported data and identity restarts. The second verification script performs an independent, read-only check against the committed data.

Check snapshot counts printed on export and import against those in `manifest.json`. Compare results to SQL queries in the dashboard:

```sql
SELECT 'users' AS table_name, count(*) AS total FROM public.users
UNION ALL SELECT 'levels', count(*) FROM public.levels
UNION ALL SELECT 'user_progress', count(*) FROM public.user_progress;
SELECT game_type, count(*) FROM public.levels GROUP BY game_type;
SELECT role, is_active, count(*) FROM public.users GROUP BY role, is_active;
SELECT min(user_id), max(user_id) FROM public.users;
SELECT min(level_id), max(level_id) FROM public.levels;
SELECT min(progress_id), max(progress_id) FROM public.user_progress;
```

Verify all historical Sokoban rows, six sliding levels, user IDs, password hashes and archived score rows using the manifest and full hash comparison. **No accounts or level IDs are regenerated.** Registration and new level/progress IDs should be validated in a dedicated test DB after import.

## 5. Final re-import before a later switch (OPTIONAL, DESTRUCTIVE to destination)

Only after (a) separately backing up current Supabase data, (b) determining the intended canonical source is **still Railway**, and (c) arranging to prevent source writes during the final snapshot, execute:

```sh
# Fresh, consistent Railway source export with a NEW destination folder:
php database/export_mysql.php /secure-backups/gridlock-final-snapshot
# This truncates the Supabase target tables and app_sessions atomically.
# You must set the confirmation variable privately in this shell (exact value shown).
CONFIRM_DESTINATION_REPLACE=YES_DELETE_SUPABASE_DATA \
  php database/import_postgres.php /secure-backups/gridlock-final-snapshot --replace-destination
php database/verify_postgres.php /secure-backups/gridlock-final-snapshot
```

**DANGER:** `--replace-destination` deletes **all existing Supabase-only accounts, progress, updated levels and session records**, then restores the Railway snapshot. This is irreversible without a prior destination backup; it logs out existing Vercel sessions. It never edits the Railway source. If both apps have received distinct writes after the first snapshot, reconciliation is a **separate data-merge project**, not supported by this importer. Confirm you are targeting the **correct Supabase project** before setting the replacement flag.

## 6. Common failure paths

- **`could not connect` / timeout:** verify network, pooler selection, TLS, Supabase project active state, and role credentials privately.
- **`Unexpected source table inventory` / `Unexpected column`:** the actual Railway schema differs from the provided three-table baseline. Stop and extend migration tooling to capture *all* records.
- **`unique violation` on usernames/emails:** current PostgreSQL lower-case uniqueness differs from unexpected source identifiers. Investigate case/accent rules; **do not delete or rename users automatically**.
- **`foreign key violation`:** source records violate assumed relationships or columns differ; stop rather than discarding rows.
- **`verification mismatch`:** import automatically rolls back. Compare snapshot encoding/timestamp handling and source SQL types. Do not disable verification.
- **Huge tables:** exporter/importer operate row-by-row; they avoid loading all table rows in PHP memory, but import runs in a **single potentially long transaction**, requiring sufficient disk/pooler resources. For very large datasets, use a planned `COPY`/staging approach with an atomic final swap, not manual partial inserts.

## 7. Operational backups after migration

The JSONL exporter is **MySQL-only** and **not** a recurring Supabase database backup. On Supabase, use a supported `pg_dump`/`supabase db dump` with an encrypted offline location and recovery drill. Review [Supabase backups documentation](https://supabase.com/docs/guides/platform/backups) for current CLI flags and plan restrictions. Supabase Free may pause after ~7 low-activity days and **does not include automatic daily backups**.
