# Rollback to the untouched Railway application

**The Railway production application and its MySQL database were not modified by this migration deliverable.** This is deliberate: the existing live Railway deployment remains your fallback.

## Before Vercel cutover

Do nothing to Railway. If Supabase import or Vercel testing fails, leave Railway's live public URL and Git production branch exactly as they are. You may delete or discard the **separate Vercel project** and Supabase staging database at your discretion only after keeping independent backups. Never delete/change Railway data as part of cleanup.

## After making Vercel the primary experience

1. Stop new writes to Vercel using a maintenance mechanism you control, if appropriate.
2. Confirm Railway's existing URL still loads and authenticates against the untouched MySQL database.
3. Restore your routing/link/domain to Railway's **original URL** (if you changed routing). Keep Railway's old production branch deployed.
4. **Do not** import the PostgreSQL schema into MySQL, or overwrite Railway MySQL with a PostgreSQL dump.
5. Announce any progress/account data that was written **only to Supabase** and is not on Railway. The deployments are independent; a rollback of traffic alone **does not copy or merge** those changes. Any reconciliation requires a separately designed, validated data transfer with backups.
6. Retain an encrypted Supabase backup and investigation logs, rotate any exposed secrets, and investigate the failed deploy independently.

## Data divergence example

If a player registers or earns a best score on Vercel after the initial Railway snapshot, that user/score exists only in Supabase. Sending the player back to Railway restores the **older** Railway state for them. Conversely, if Railway users continue to play after the snapshot, that new Railway data is absent from Supabase. Do not run automatic dual-writes or attempt an undocumented merge.
