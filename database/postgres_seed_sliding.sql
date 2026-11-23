-- For fresh DEMO databases ONLY. Do not run before importing Railway data.
-- No fixed IDs: avoid interfering with preserved Railway IDs.
BEGIN;
INSERT INTO public.levels (game_type,title,hint,difficulty,sort_order,grid_width,grid_height,layout,par_moves)
SELECT 'sliding', 'First Response', 'Clear the blue truck in front of the exit, then drive out.', 'easy', 1, 6, 6, 'D..A..
D..A.C
.RRA.C
..EEE.
..BB..
......', 5
WHERE NOT EXISTS (SELECT 1 FROM public.levels WHERE game_type = 'sliding' AND lower(title) = lower('First Response'));
INSERT INTO public.levels (game_type,title,hint,difficulty,sort_order,grid_width,grid_height,layout,par_moves)
SELECT 'sliding', 'Crosstown Jam', 'Several vehicles block each other. Free the middle first.', 'medium', 2, 6, 6, '....IG
.BB.IG
CRRHIA
C.DH.A
C.DFFF
EEE...', 10
WHERE NOT EXISTS (SELECT 1 FROM public.levels WHERE game_type = 'sliding' AND lower(title) = lower('Crosstown Jam'));
INSERT INTO public.levels (game_type,title,hint,difficulty,sort_order,grid_width,grid_height,layout,par_moves)
SELECT 'sliding', 'Delivery Day', 'Almost every vehicle has to move once. Find the right order.', 'medium', 3, 6, 6, 'FFF.E.
CCC.EH
RRB.EH
..BII.
.AA.GG
.DD...', 12
WHERE NOT EXISTS (SELECT 1 FROM public.levels WHERE game_type = 'sliding' AND lower(title) = lower('Delivery Day'));
INSERT INTO public.levels (game_type,title,hint,difficulty,sort_order,grid_width,grid_height,layout,par_moves)
SELECT 'sliding', 'Truck Convoy', 'Long trucks form a wall in front of the exit. Take them out one by one.', 'hard', 4, 6, 6, '.HAF.C
.HAFIC
RRAFI.
GG..I.
J..BBD
J..EED', 14
WHERE NOT EXISTS (SELECT 1 FROM public.levels WHERE game_type = 'sliding' AND lower(title) = lower('Truck Convoy'));
INSERT INTO public.levels (game_type,title,hint,difficulty,sort_order,grid_width,grid_height,layout,par_moves)
SELECT 'sliding', 'Bottleneck', 'Only one truck blocks the exit, but freeing it takes a long chain of moves.', 'hard', 5, 6, 6, 'BEEE.D
BJ..GD
.JRRG.
K.CCGH
K.ALLH
FFAII.', 17
WHERE NOT EXISTS (SELECT 1 FROM public.levels WHERE game_type = 'sliding' AND lower(title) = lower('Bottleneck'));
INSERT INTO public.levels (game_type,title,hint,difficulty,sort_order,grid_width,grid_height,layout,par_moves)
SELECT 'sliding', 'Gridlock Gauntlet', 'Every move unlocks another. Plan the order before you start.', 'expert', 6, 6, 6, 'CFF.DD
C..HHH
GRRK..
GLLK.A
..IJJA
BBIEEA', 20
WHERE NOT EXISTS (SELECT 1 FROM public.levels WHERE game_type = 'sliding' AND lower(title) = lower('Gridlock Gauntlet'));
COMMIT;
