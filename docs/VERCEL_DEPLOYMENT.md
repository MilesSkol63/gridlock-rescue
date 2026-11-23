# Vercel deployment — Docker + FrankenPHP + PHP 8.4

**The supplied ZIP is a deployable source candidate, not proof of a successful build or a live hosted deployment.** Use a separate Vercel project, separate domain and `supabase-migration` branch. The Railway Git production branch and application remain untouched.

## Official platform status (checked 2026-11-23)

Vercel officially documents PHP with Docker/FrankenPHP: https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker. The **Container runtime is Beta** and available on all plans, with usage/Active CPU charges and plan-specific function/billing quotas: https://vercel.com/docs/functions/runtimes/container. Beta availability is not the same as a proven production SLA; inspect limits in the project dashboard and perform live scale/security tests before treating it as primary production. Supabase limits and Free-plan project pausing also apply.

## 1. Prepare the separate Git branch (in YOUR own repository)

From your *existing* repository with a clean tree, create a branch without changing its production branch:

```sh
git switch -c supabase-migration
# Copy the extracted ZIP's entire gridlock-rescue/ contents into your project working tree,
# preserving the .git directory and keeping all .env/secrets out of it.
git status --short
git add -A
git commit -m "Add isolated Supabase PostgreSQL and Vercel container migration"
git push -u origin supabase-migration
```

**Do not execute these commands in the current Railway production branch.** Confirm the Railway Git service still deploys the prior production branch, and configure **Vercel's production branch to `supabase-migration`** if importing the same GitHub repo. Do not configure bidirectional synchronization.

## 2. Set up Supabase first

Complete [SUPABASE_SETUP.md](SUPABASE_SETUP.md) and [DATA_MIGRATION.md](DATA_MIGRATION.md). Test the restricted application role against the target database; create the session table and policies. Use the **actual** Dashboard > Connect > Transaction pooler connection string for the custom role and its password. The runtime PHP process has no MySQL dependency.

## 3. Set Vercel environment variables

In Vercel Project Settings > Environment Variables, add these **server-side** values for the intended deployment environment:

| Variable | Meaning | Required? |
| --- | --- | --- |
| `DATABASE_URL` | **Supabase shared transaction pooler** URL for restricted application role, with TLS (`?sslmode=require`) | Yes |
| `APP_ENV` | `production` (enables Secure session cookies) | Yes |
| `APP_URL` | Vercel public HTTPS URL (informational; reserved for future absolute links) | Recommended |
| `SESSION_TTL_SECONDS` | PHP session lifetime in seconds, default `7200` | Optional |

Never set `MIGRATION_DATABASE_URL`, `MYSQL_SOURCE_URL`, owner DB password, MySQL source creds, Supabase service key or session export data in Vercel. Do **not** put secrets into `NEXT_PUBLIC_*` or frontend JS. Check sensitive values are not part of Git history, the Docker image or error messages.

## 4. Build locally with Docker (required before production)

The project includes a root `Dockerfile` based on `dunglas/frankenphp:1-php8.4-alpine`, `Caddyfile`, PHP production configuration and a `public/` document root. Runtime `pdo_pgsql` and `mbstring` are installed; OpenSSL, JSON, session support, fileinfo and other standard PHP features come from the PHP runtime image and **must be checked in the built image**, not assumed.

```sh
docker build --pull -t gridlock-rescue-supabase .
docker run --rm gridlock-rescue-supabase php -m
# Create PRIVATE local env file OUTSIDE the Git working directory, with dev/test URLs only:
docker run --rm --env-file /path/outside/repo/gridlock.env \
  -p 8080:80 gridlock-rescue-supabase
```

In another terminal:

```sh
curl -i http://localhost:8080/health.php
curl -i http://localhost:8080/health_db.php
curl -i http://localhost:8080/index.php
curl -i http://localhost:8080/includes/config.php
curl -i http://localhost:8080/database/postgres_schema.sql
curl -i http://localhost:8080/tools/setup_admin.php
```

Expected: PHP check `200`; database readiness `200` only with reachable, initialized Supabase test database; login page reachable; private paths `404`. Use `APP_ENV=development` for non-HTTPS `localhost` tests so session cookies can be set; production automatically forces Secure cookies.

Also test Vercel's **documented** local mode with Docker running and the Vercel CLI installed:

```sh
vercel dev --local
```

## 5. Create and deploy the separate Vercel project

In the Vercel dashboard import your repository and select the `supabase-migration` branch (or create a new project through the CLI). Keep the root directory set to the folder holding the `Dockerfile`; the container framework preset should be selected automatically, without `vercel.json`. If your existing project has a different preset, Vercel documents `vercel project update --framework container --yes` after linking, but a fresh dedicated project is safer.

With Vercel CLI installed and after you configure variables:

```sh
vercel login
vercel deploy --prod
```

Alternatively, let the separate Git-integrated Vercel project deploy from its dedicated branch. Record the resulting **new public URL** and verify its TLS certificate, response headers and behavior. Do not change the Railway URL, branch, DNS routing or deployment settings.

## 6. End-to-end acceptance verification (on the actual Vercel URL)

- Open `/health.php` (PHP process check) and `/health_db.php` (DB table check); neither may emit credentials or detailed SQL errors.
- Verify login/register, logout, password verification, profile editing, player/admin dashboards, all six boards, drag/keyboard input, undo/restart, server-side replay, victories, best score and progression, leaderboard, level editor/CRUD and user management.
- Disable a logged-in player from an admin session, then request a protected page as that player: **next request must fail**, while progress/password/profile remain. Re-enable the user; confirm no admin can disable themselves/last active admin.
- Test two simultaneous requests with **the same session ID** and two different Vercel instances (or force separate instances): no silent overwritten session changes; SessionStart login regeneration and logout must work. These are **NOT executable inside the delivered ZIP alone**.
- Check static CSS/JS/images, 404s for private scripts, no MySQLi calls, session persistence after container cold start, and no credentials in HTML or JS.
- Load-test realistic parallel traffic against Supabase shared pooler because the session handler holds a transaction-level advisory lock and **pins one DB backend per active request**. Monitor DB/pooler connections and function execution duration. If pool exhaustion occurs, do not declare the service production-ready; redesign session persistence (e.g. a managed Redis store with reliable locking), or constrain traffic appropriately.

## 7. Free plan, beta and cost warnings

Vercel Container is **Beta** as of the documentation checked, and current plan/billing restrictions can change. Hobby availability does not guarantee a free production workload; inspect commercial-use eligibility and actual function/CPU usage. Supabase Free can auto-pause after low activity; no built-in daily backups. Check up-to-date prices before go-live. **No extra Redis account is required** for this implementation because sessions use PostgreSQL, though DB pool capacity is an additional operational constraint.

Official references:

- https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker
- https://vercel.com/kb/docker
- https://vercel.com/docs/functions/runtimes/container
- https://supabase.com/docs/guides/database/connecting-to-postgres
