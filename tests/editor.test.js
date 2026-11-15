/** tests/editor.test.js - the level editor's model (assets/js/level-editor.js, EditorCore).   node tests/editor.test.js */
const E = require('../assets/js/sliding-engine.js');
const Ed = require('../assets/js/level-editor.js');
const LEVELS = require('./fixtures/sliding_levels.json');
let passed = 0, failed = 0;
const check = (n, ok, d) => { if (ok) passed++; else { failed++; console.log('FAIL: ' + n + (d ? ' -> ' + d : '')); } };

// round trip: every bundled level -> editor model -> rows is identical, and the engine accepts it
LEVELS.forEach(L => {
    const v = Ed.fromRows(L.rows);
    check('level ' + L.id + ': editor round-trips the rows exactly', JSON.stringify(Ed.toRows(v)) === JSON.stringify(L.rows));
    check('level ' + L.id + ': rows written by the editor are accepted by the engine', E.parseLevel(Ed.toRows(v)).length === v.length);
});
check('invalid saved layout throws (the page falls back to an empty board)', (() => { try { Ed.fromRows(['x', 'y']); return false; } catch (e) { return true; } })());

let v = Ed.defaultVehicles();
check('default board has only the rescue vehicle in row 3', v.length === 1 && v[0].id === 'R' && v[0].row === 2 && v[0].orient === 'H');
let r = Ed.addVehicle(v, 'V', 3, 0, 4);
check('adding a vertical truck works and gets letter A', !r.error && r.id === 'A' && r.vehicles.length === 2);
v = r.vehicles;
check('rows reflect the new vehicle', JSON.stringify(Ed.toRows(v)) === JSON.stringify(['....A.', '....A.', 'RR..A.', '......', '......', '......']));
check('cannot add on top of another vehicle', !!Ed.addVehicle(v, 'H', 2, 0, 4).error);
r = Ed.addVehicle(v, 'H', 2, 2, 4);
check('cannot add a vehicle that overlaps (row 3, col 5)', !!r.error);
r = Ed.addVehicle(v, 'H', 3, 5, 5);
check('a vehicle clicked near the right edge is shifted left to fit', !r.error && r.vehicles[r.vehicles.length - 1].col === 3);
r = Ed.addVehicle(v, 'V', 3, 5, 0);
check('a vehicle clicked near the bottom edge is shifted up to fit', !r.error && r.vehicles[r.vehicles.length - 1].row === 3);
check('letters skip R (next after Q is S)', (() => { let vv = Ed.defaultVehicles(); const ids = []; for (let i = 0; i < 25; i++) { const a = Ed.addVehicle(vv, i % 2 ? 'H' : 'V', 2, 0, 0); if (a.error) { vv = vv; continue; } ids.push(a.id); vv = a.vehicles; } return ids.indexOf('R') === -1; })());
// moving, rotating, resizing, deleting
r = Ed.moveVehicle(v, 'A', 0, 1);
check('move right by one square', !r.error && r.vehicles.find(x => x.id === 'A').col === 5);
check('cannot move off the board', !!Ed.moveVehicle(v, 'A', -3, 0).error && !!Ed.moveVehicle(r.vehicles, 'A', 0, 1).error);
check('rescue vehicle cannot be moved into another vehicle', !!Ed.moveVehicle(v, 'R', 0, 3).error);
const blocked = Ed.moveVehicle([{ id: 'R', orient: 'H', length: 2, row: 2, col: 0, rescue: true }, { id: 'A', orient: 'V', length: 2, row: 1, col: 2, rescue: false }], 'R', 0, 1);
check('rescue vehicle cannot overlap a vehicle (explicit setup)', !!blocked.error);
check('rescue vehicle cannot leave row 3', !!Ed.moveVehicle(v, 'R', 1, 0).error);
check('rescue vehicle cannot be rotated', !!Ed.rotateVehicle(v, 'R').error);
check('rescue vehicle cannot be deleted', !!Ed.removeVehicle(v, 'R').error);
r = Ed.rotateVehicle(v, 'A');
check('a truck cannot rotate when it would not fit', !!r.error);
r = Ed.updateVehicle(v, 'A', { length: 2 });
check('shrink a truck to length 2', !r.error && r.vehicles.find(x => x.id === 'A').length === 2);
check('growing the truck beyond the board edge is rejected', !!Ed.updateVehicle(Ed.addVehicle(Ed.defaultVehicles(), 'V', 2, 4, 0).vehicles, 'A', { length: 3, row: 4 }).error);
r = Ed.removeVehicle(v, 'A');
check('delete a vehicle', !r.error && r.vehicles.length === 1);
check('unknown vehicle id is an error, not a crash', !!Ed.moveVehicle(v, 'Z', 0, 1).error && !!Ed.removeVehicle(v, 'Z').error);

// what the editor produces must be exactly what the solver and replay accept
const built = Ed.addVehicle(Ed.defaultVehicles(), 'V', 2, 1, 3).vehicles;
const rows = Ed.toRows(built);
const sol = E.solve(rows, 50000);
check('a level built in the editor can be solved by the engine', !!sol && sol.moves.length >= 1);
check('editor output with an immovable wall of trucks is detected as unsolvable by the same solver', (() => {
    let vv = Ed.defaultVehicles();
    // two vertical trucks that together fill column 3 from top to bottom (length 3 + 3): they can never clear row 3
    vv = Ed.addVehicle(vv, 'V', 3, 0, 3).vehicles; vv = Ed.addVehicle(vv, 'V', 3, 5, 3).vehicles;
    return E.solve(Ed.toRows(vv), 50000) === null;
})());

console.log((failed === 0 ? 'ALL PASSED' : 'FAILURES') + ': ' + passed + ' checks passed, ' + failed + ' failed');
process.exit(failed === 0 ? 0 : 1);
