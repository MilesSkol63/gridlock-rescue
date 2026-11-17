-- =============================================================================
-- MIGRATION: account status (disable / enable)                    database/migrate_user_status.sql
--
-- Adds users.is_active (1 = active, 0 = disabled). NON-DESTRUCTIVE and safe to run more than once:
--   * every existing user stays ACTIVE (the column defaults to 1)
--   * no passwords, roles, profiles, user ids, progress or scores are changed or deleted
-- Run it on a database that already has the Gridlock Rescue tables:
--     php database/import.php migrate_user_status.sql
-- (or import this file in phpMyAdmin with your database selected). Make a backup first.
-- =============================================================================

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role',
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active');
PREPARE gr_step FROM @sql;
EXECUTE gr_step;
DEALLOCATE PREPARE gr_step;
