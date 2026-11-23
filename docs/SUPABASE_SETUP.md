# Supabase PostgreSQL setup (separate from Railway)

**Do not expose production credentials in screenshots, chat, Git commits, the ZIP, logs or public JS.** All database access is server-side PHP. This app intentionally does not use Supabase Auth or the Supabase Data API.

## 1. Create a new Supabase project

Create a **new project** in your Supabase organization and record its private database credentials in a password manager. Do not use your Railway MySQL database or the production Git branch to set it up. For a production rollout, review Supabase's [production checklist](https://supabase.com/docs/guides/deployment/going-into-prod), network restrictions, costs and backups.

## 2. Apply schema — before data import

From the **Supabase SQL Editor** (as the project database administrator), execute the complete contents of `database/postgres_schema.sql` once. It creates:

- `public.users`, `public.levels`, `public.user_progress` with preserved IDs, constraints and indexes;
- `public.app_sessions` for external PHP sessions;
- `trg_gridlock_progress_updated_at` for MySQL-compatible progress-update timestamps;
- case-insensitive username/email unique indexes, and RLS enabled with no generic public policies.

Check the tables and their definitions in **Database > Tables** and/or SQL Editor. **Do not** run `postgres_seed_sliding.sql` in the real migration database: the full Railway export provides every level including historical rows.

## 3. Create a limited application login

Run in the **SQL Editor** and type a new unique high-entropy password **there**, not in a tracked SQL file:

```sql
CREATE ROLE gridlock_app LOGIN PASSWORD 'REPLACE_WITH_A_LONG_PRIVATE_PASSWORD';
```

In the editor execute `database/postgres_permissions.sql` in full. It authorizes this role for the four application tables and PostgreSQL sequences while withholding `DELETE` on `users`. The SQL creates row-level policies scoped to **that specific role**, not `anon`/`authenticated`. This is a one-time script; policy creation is deliberately not silently idempotent. If you rename the role, replace it consistently in the permissions SQL and in your connection URI.

Confirm the restriction:

```sql
SELECT has_table_privilege('gridlock_app', 'public.users', 'SELECT') AS may_read_users,
       has_table_privilege('gridlock_app', 'public.users', 'UPDATE') AS may_update_users,
       has_table_privilege('gridlock_app', 'public.users', 'DELETE') AS may_delete_users;
-- Expected: true / true / false
```

**Do not grant `BYPASSRLS`, `SUPERUSER`, schema ownership, broad database-owner privileges or DELETE on users to `gridlock_app`.** The transfer tools require a separate migration owner credential, not this runtime login.

## 4. Configure safe database URLs

Use the actual **Connect** dialogue in your Supabase dashboard. For Vercel short-lived HTTP invocations choose **Shared pooler > Transaction mode**. Copy **its exact hostname, port, project reference and username form**; never guess a regional pooler host. If connecting as the custom role, use the `gridlock_app.<project_ref>` username form and its own password. Make sure the URI includes `?sslmode=require`.

Set Vercel environment variable `DATABASE_URL` to that **runtime-role** URI. The code enforces `sslmode=require` or stricter and sets `PDO::ATTR_EMULATE_PREPARES=true`, because Supabase shared transaction pooling does not support normal *server-side* prepared statements. **Binding parameters remains in place.**

For **owner-controlled schema import and verification**, run the local CLI tooling with a separate environment variable `MIGRATION_DATABASE_URL` containing an actual owner-level connection URI from Supabase. The **Direct** connection may require IPv6; select the dashboard's shared **Session pooler** option where direct IPv6 is unavailable. Never put `MIGRATION_DATABASE_URL` into Vercel; Vercel only needs `DATABASE_URL`.

The URI parser expects a PostgreSQL URI, including user, password, host, database and optional `sslmode=require`. Percent-encode reserved characters in the username/password. Avoid pasted newlines/spaces. Do not use a URL with `sslmode=disable`.

## 5. Data import

Follow [DATA_MIGRATION.md](DATA_MIGRATION.md). Run the read-only Railway export from a machine that **can reach** the Railway MySQL service. The exporter enforces a consistent source snapshot; the importer preserves IDs and verifies all rows/columns before committing. Import into a fresh empty schema. Do **not** fabricate records, manually recreate accounts, reset hashes or apply the six demo levels over real data.

After import, use the SQL editor to inspect actual table counts, ID maxima, unique indexes and user status distribution. Compare with the manifest, not just the defaults.

## 6. Sessions and connection load

The shared `public.app_sessions` table stores PHP's opaque session data encoded as base64 (including CSRF state and user/role identifiers). `PgSessionHandler` acquires a PostgreSQL **transaction-level** advisory lock per session and retains it through `session_write_close()`; this safely serializes concurrent requests on shared transaction pooling. This does **consume a backend connection for each active session transaction during a request** and opens a separate PDO connection from the ordinary query connection. Confirm real pooler capacity and timeouts under concurrent load. This is cost-free in terms of extra providers but **not free in connections**. Consider a managed Redis session adapter only if production scale demands it, after implementing equivalent locking and account-status invalidation.

The session cookie is HttpOnly, SameSite=Lax, and Secure whenever `APP_ENV=production`. Keep HTTPS on Vercel. Account status is re-read on every authenticated protected request; session data does not override it. Session expiry is controlled by `SESSION_TTL_SECONDS` (default 7200) and expired rows are cleaned by PHP GC. For high-volume installations, add a scheduled authorized SQL cleanup, e.g. `DELETE FROM public.app_sessions WHERE expires_at < now();` (owner role), after reviewing operational impact.

## 7. Plan limits and backups

The **Supabase Free** plan can pause low-activity projects after roughly a week; restore/resume from the Supabase dashboard before debugging connectivity. It does not include automatic daily database backups. Keep encrypted off-site manual exports; Supabase recommends CLI `supabase db dump` or a compatible `pg_dump` backup (see [Supabase backup docs](https://supabase.com/docs/guides/platform/backups)). Check the current plan pricing, storage, pooling and active connection limits in **your** dashboard. The connection and application must be re-tested after an automatic pause/resume.

## References

- https://supabase.com/docs/guides/database/connecting-to-postgres
- https://supabase.com/docs/guides/database/connection-management
- https://supabase.com/docs/guides/deployment/going-into-prod
- https://supabase.com/docs/guides/platform/free-project-pausing
- https://supabase.com/docs/guides/platform/backups
