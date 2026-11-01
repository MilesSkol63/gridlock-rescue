-- =============================================================================
-- Gridlock Rescue - database setup (MySQL 8 / MariaDB 10.4+)       database/gridlock_rescue.sql
-- Creates the 3 approved tables and the sample levels. Safe to run more than once.
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
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS levels (
    level_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(60)  NOT NULL,
    hint        VARCHAR(255) NULL,
    difficulty  ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
    sort_order  INT UNSIGNED NOT NULL DEFAULT 1,
    grid_width  TINYINT UNSIGNED NOT NULL,
    grid_height TINYINT UNSIGNED NOT NULL,
    layout      TEXT NOT NULL,
    par_moves   INT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (level_id),
    KEY idx_levels_order (sort_order, level_id)
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
-- Sample levels. Layout characters:  # wall  - floor  . target  $ box  * box on target
--                                    @ player  + player on target
-- par_moves is the shortest solution found by an exhaustive search (checked before release).
-- Each INSERT is skipped if a level with the same title already exists.
-- -----------------------------------------------------------------------------

-- Level 1: Warm-Up (easy, 7x5, 1 box, shortest solution 1 moves: R)
INSERT INTO levels (title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'Warm-Up', 'Push the box onto the target.', 'easy', 1, 7, 5, '#######
#-----#
#-@$.-#
#-----#
#######', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE title = 'Warm-Up');

-- Level 2: Side Step (easy, 7x6, 1 box, shortest solution 5 moves: URULL)
INSERT INTO levels (title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'Side Step', 'The box has to change direction, so walk around it.', 'easy', 2, 7, 6, '#######
#.----#
#--$--#
#--@--#
#-----#
#######', 5
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE title = 'Side Step');

-- Level 3: Two Boxes (medium, 7x6, 2 boxes, shortest solution 7 moves: ULDURRD)
INSERT INTO levels (title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'Two Boxes', 'Two boxes, one lane between them. Mind the order.', 'medium', 3, 7, 6, '#######
#-----#
#-$@$-#
#-.#.-#
#-----#
#######', 7
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE title = 'Two Boxes');

-- Level 4: Corner Pocket (medium, 7x6, 2 boxes, shortest solution 12 moves: RLLURULRRRDD)
INSERT INTO levels (title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'Corner Pocket', 'Never push a box into a corner it cannot leave.', 'medium', 4, 7, 6, '#######
#.----#
#-$-#-#
#--@$-#
#-#--.#
#######', 12
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE title = 'Corner Pocket');

-- Level 5: Tight Squeeze (hard, 7x7, 3 boxes, shortest solution 22 moves: DDLLLUURULDDLDRRRUURUL)
INSERT INTO levels (title, hint, difficulty, sort_order, grid_width, grid_height, layout, par_moves)
SELECT 'Tight Squeeze', 'Three boxes and narrow lanes. Plan before you push.', 'hard', 5, 7, 7, '#######
#.-.--#
#-$$-@#
#--#--#
#-$---#
#.----#
#######', 22
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM levels WHERE title = 'Tight Squeeze');
