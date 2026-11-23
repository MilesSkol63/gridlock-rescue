-- Run only AFTER creating the LOGIN role gridlock_app as database owner in Supabase SQL editor.
-- The password is set privately via Supabase SQL editor, never committed to this project.
-- Change gridlock_app consistently if you choose a different dedicated runtime role.
BEGIN;
GRANT CONNECT ON DATABASE postgres TO gridlock_app;
GRANT USAGE ON SCHEMA public TO gridlock_app;
GRANT SELECT, INSERT, UPDATE ON public.users TO gridlock_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.levels, public.user_progress, public.app_sessions TO gridlock_app;
-- Grant only the three application identity sequences, not Supabase-owned sequences.
GRANT USAGE, SELECT ON SEQUENCE public.users_user_id_seq,
    public.levels_level_id_seq, public.user_progress_progress_id_seq TO gridlock_app;
-- RLS is active on all tables; authorize only the dedicated direct-Postgres runtime role.
CREATE POLICY gridlock_users_app ON public.users FOR ALL TO gridlock_app USING (true) WITH CHECK (true);
CREATE POLICY gridlock_levels_app ON public.levels FOR ALL TO gridlock_app USING (true) WITH CHECK (true);
CREATE POLICY gridlock_progress_app ON public.user_progress FOR ALL TO gridlock_app USING (true) WITH CHECK (true);
CREATE POLICY gridlock_sessions_app ON public.app_sessions FOR ALL TO gridlock_app USING (true) WITH CHECK (true);
COMMIT;
