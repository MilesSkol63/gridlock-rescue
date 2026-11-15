-- =============================================================================
-- MIGRATION: Sokoban -> sliding-block traffic puzzle               database/migrate_sliding.sql
--
-- NON-DESTRUCTIVE and safe to run more than once (MySQL 8 / MariaDB 10.4+):
--   * adds levels.game_type ('sokoban' | 'sliding'); every existing level becomes 'sokoban' (archived)
--   * adds the difficulty value 'expert'
--   * adds the six sliding-block levels (skipped if they already exist)
--   * does NOT touch users, user_progress rows, passwords or sessions
-- The old Sokoban levels and their saved scores stay in the database but are never shown, played,
-- counted, ranked or reset by the sliding-block game.
-- Run it on a DATABASE THAT ALREADY HAS the Gridlock Rescue tables:  php database/import.php migrate_sliding.sql
-- =============================================================================

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
