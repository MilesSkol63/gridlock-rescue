-- =============================================================================
-- Gridlock Rescue - database setup (MySQL 8 / MariaDB 10.4+)       database/gridlock_rescue.sql
-- Creates the 3 tables and the six sliding-block levels. Safe to run more than once, on a new OR an existing
-- database (on an existing one the migration part below converts it without deleting anything).
--
-- BEFORE importing: create an empty database and select it.
--   Local XAMPP : CREATE DATABASE gridlock_rescue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   Railway     : the MySQL service already provides a database (MYSQLDATABASE, usually "railway").
-- No user accounts are created here. Players register on the site;
-- the administrator is created with:  php setup_admin.php
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(20)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS levels (
    level_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    game_type   ENUM('sokoban','sliding') NOT NULL DEFAULT 'sokoban',
    title       VARCHAR(60)  NOT NULL,
    hint        VARCHAR(255) NULL,
    difficulty  ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'easy',
    sort_order  INT UNSIGNED NOT NULL DEFAULT 1,
    grid_width  TINYINT UNSIGNED NOT NULL,
    grid_height TINYINT UNSIGNED NOT NULL,
    layout      TEXT NOT NULL,
    par_moves   INT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (level_id),
    KEY idx_levels_order (sort_order, level_id),
    KEY idx_levels_game_order (game_type, sort_order, level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_progress (
    progress_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    level_id     INT UNSIGNED NOT NULL,
    best_moves   INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (progress_id),
    UNIQUE KEY uq_progress_user_level (user_id, level_id),
    CONSTRAINT fk_progress_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
    CONSTRAINT fk_progress_level FOREIGN KEY (level_id) REFERENCES levels(level_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sliding-block migration (no-ops on a fresh database)
-- -----------------------------------------------------------------------------
-- 1. levels.game_type (only if missing)
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE levels ADD COLUMN game_type ENUM(''sokoban'',''sliding'') NOT NULL DEFAULT ''sokoban'' AFTER level_id',
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'levels' AND COLUMN_NAME = 'game_type');
PREPARE gr_step FROM @sql;
EXECUTE gr_step;
DEALLOCATE PREPARE gr_step;

-- 2. index for "sliding levels in order" (only if missing)
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE levels ADD KEY idx_levels_game_order (game_type, sort_order, level_id)',
    'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'levels' AND INDEX_NAME = 'idx_levels_game_order');
PREPARE gr_step FROM @sql;
EXECUTE gr_step;
DEALLOCATE PREPARE gr_step;

-- 3. allow the difficulty 'expert' (re-running this is harmless; existing values are kept)
ALTER TABLE levels MODIFY difficulty ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'easy';

-- 4. the six sliding-block levels.
--    layout = 6 rows of 6 characters ('.' empty, 'R' rescue vehicle, other letters = vehicles);
--    par_moves = optimal number of moves (checked by two independent solvers before release).

-- Level 1: First Response (Easy, optimal 5 moves)
INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'sliding', 'First Response', 'Clear the blue truck in front of the exit, then drive out.', 'easy', 1, 6, 6, 'D..A..
D..A.C
.RRA.C
..EEE.
..BB..
......', 5
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = 'First Response');

-- Level 2: Crosstown Jam (Medium, optimal 10 moves)
INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'sliding', 'Crosstown Jam', 'Several vehicles block each other. Free the middle first.', 'medium', 2, 6, 6, '....IG
.BB.IG
CRRHIA
C.DH.A
C.DFFF
EEE...', 10
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = 'Crosstown Jam');

-- Level 3: Delivery Day (Medium, optimal 12 moves)
INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'sliding', 'Delivery Day', 'Almost every vehicle has to move once. Find the right order.', 'medium', 3, 6, 6, 'FFF.E.
CCC.EH
RRB.EH
..BII.
.AA.GG
.DD...', 12
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = 'Delivery Day');

-- Level 4: Truck Convoy (Hard, optimal 14 moves)
INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'sliding', 'Truck Convoy', 'Long trucks form a wall in front of the exit. Take them out one by one.', 'hard', 4, 6, 6, '.HAF.C
.HAFIC
RRAFI.
GG..I.
J..BBD
J..EED', 14
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = 'Truck Convoy');

-- Level 5: Bottleneck (Hard, optimal 17 moves)
INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'sliding', 'Bottleneck', 'Only one truck blocks the exit, but freeing it takes a long chain of moves.', 'hard', 5, 6, 6, 'BEEE.D
BJ..GD
.JRRG.
K.CCGH
K.ALLH
FFAII.', 17
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = 'Bottleneck');

-- Level 6: Gridlock Gauntlet (Expert, optimal 20 moves)
INSERT INTO levels (game_type, title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'sliding', 'Gridlock Gauntlet', 'Every move unlocks another. Plan the order before you start.', 'expert', 6, 6, 6, 'CFF.DD
C..HHH
GRRK..
GLLK.A
..IJJA
BBIEEA', 20
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE game_type = 'sliding' AND title = 'Gridlock Gauntlet');

-- -----------------------------------------------------------------------------
-- Account status migration (no-op on a fresh database; existing users stay active)
-- -----------------------------------------------------------------------------
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role',
    'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active');
PREPARE gr_step FROM @sql;
EXECUTE gr_step;
DEALLOCATE PREPARE gr_step;
