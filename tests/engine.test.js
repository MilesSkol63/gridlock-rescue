/**
 * tests/engine.test.js  (NEW)  - runs the REAL game engine (assets/js/game.js) in Node, no browser needed.
 *   node tests/engine.test.js
 * It reads the five levels straight from database/gridlock_rescue.sql and checks the rules.
 */
const fs = require('fs');
const path = require('path');
const { createEngine } = require('../assets/js/game.js');

let passed = 0, failed = 0;
function check(name, condition, detail) {
    if (condition) { passed++; } else { failed++; console.log('FAIL: ' + name + (detail ? ' -> ' + detail : '')); }
}
const DIR = { U: [-1, 0], D: [1, 0], L: [0, -1], R: [0, 1] };
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
function boxesKey(e) { return JSON.stringify(e.getBoxes().map(b => b.row + ',' + b.col).sort()); }

// ---- read levels from the SQL seed file ----
const sql = fs.readFileSync(path.join(__dirname, '..', 'database', 'gridlock_rescue.sql'), 'utf8');
const levels = [];
const re = /-- Level (\d+): (.+?) \(.*?shortest solution (\d+) moves: ([UDLR]+)\)\nINSERT INTO levels[\s\S]*?SELECT '[^']*', '[^']*', '[^']*', \d+, (\d+), (\d+), '([^']*)', (\d+)/g;
let m;
while ((m = re.exec(sql)) !== null) {
    levels.push({ n: +m[1], title: m[2], par: +m[3], path: m[4], w: +m[5], h: +m[6], layout: m[7], parCol: +m[8] });
}
check('5 levels read from SQL', levels.length === 5, 'found ' + levels.length);

// ---- 1. every seeded level: valid, and the stored solution wins in exactly par moves ----
levels.forEach(L => {
    const e = createEngine(L.layout);
    check(L.title + ': parses without error', e.error === '', e.error);
    check(L.title + ': size matches grid_width/grid_height', e.cols === L.w && e.rows === L.h);
    check(L.title + ': par column matches comment', L.par === L.parCol);
    let wonEarly = false;
    L.path.split('').forEach((ch, i) => {
        const r = e.tryMove(...DIR[ch]);
        if (r === 'blocked') { check(L.title + ': solution step ' + i + ' not blocked', false); }
        if (i < L.path.length - 1 && e.isWon()) { wonEarly = true; }
    });
    check(L.title + ': not won before the last step', !wonEarly);
    check(L.title + ': won after the solution', e.isWon());
    check(L.title + ': move count equals par (' + L.par + ')', e.getMoves() === L.par, 'got ' + e.getMoves());
    check(L.title + ': input locked after win', e.tryMove(0, 1) === 'blocked' && e.getMoves() === L.par);
    check(L.title + ': undo disabled after win', e.undo() === false && e.canUndo() === false);
    e.restart();
    check(L.title + ': restart clears win and moves', !e.isWon() && e.getMoves() === 0);
});

// ---- 2. blocked moves are not counted ----
let e = createEngine('#######\n#-----#\n#-@$.-#\n#-----#\n#######');
e.tryMove(-1, 0); e.tryMove(-1, 0);
check('moving into the top wall is blocked and not counted', e.getMoves() === 1);
check('player stayed on the floor', e.getPlayer().row === 1);

// ---- 3. box rules ----
e = createEngine('######\n#@$$.#\n######');           // two boxes in a row: cannot push them
check('two boxes in a row cannot be pushed', e.tryMove(0, 1) === 'blocked' && e.getMoves() === 0);
e = createEngine('#####\n#@$.#\n#####');
check('push into free target works', e.tryMove(0, 1) === 'pushed' && e.getMoves() === 1 && e.isWon());
e = createEngine('#####\n#@$$#\n#--.#\n#####');
check('box against another box is blocked', e.tryMove(0, 1) === 'blocked');
e = createEngine('####\n#@$#\n#-.#\n####');
check('box against a wall is blocked', e.tryMove(0, 1) === 'blocked' && e.getMoves() === 0);
e = createEngine('######\n#-@$.#\n#--.$#\n######');     // push right, step back: the box must NOT follow (no pulling)
e.tryMove(0, 1);
const afterPush = boxesKey(e);
e.tryMove(0, -1);
check('no pulling: moving away leaves the box in place', boxesKey(e) === afterPush);

// ---- 4. undo / restart restore everything ----
e = createEngine(levels[3].layout);
const startPlayer = e.getPlayer(), startBoxes = boxesKey(e);
'RLLU'.split('').forEach(ch => e.tryMove(...DIR[ch]));
check('4 moves counted', e.getMoves() === 4);
for (let i = 0; i < 4; i++) { check('undo ' + (i + 1) + ' succeeds', e.undo() === true); }
check('undo x4 restores player', same(e.getPlayer(), startPlayer));
check('undo x4 restores boxes', boxesKey(e) === startBoxes);
check('undo x4 restores move count 0', e.getMoves() === 0);
check('undo with empty history does nothing', e.undo() === false && e.getMoves() === 0);
'RLL'.split('').forEach(ch => e.tryMove(...DIR[ch]));
e.restart();
check('restart restores player, boxes and moves', same(e.getPlayer(), startPlayer) && boxesKey(e) === startBoxes && e.getMoves() === 0);

// ---- 5. invalid layouts are rejected ----
check('no player -> error', createEngine('####\n#$.#\n####').error !== '');
check('two players -> error', createEngine('#####\n#@@$.#\n#####').error !== '');
check('no box -> error', createEngine('####\n#@.#\n####').error !== '');
check('boxes != targets -> error', createEngine('#####\n#@$$.#\n#####').error !== '');
check('Windows line endings (CRLF) parse', createEngine('#####\r\n#@$.#\r\n#####').error === '');
check('player on target (+) and box on target (*) parse', createEngine('#######\n#+$.$*#\n#######').error === '');

// ---- 6. randomised test against an independent reference model ----
function reference(layout) {                       // simple pure model written separately from the engine
    const g = layout.split('\n').map(r => r.split(''));
    let p, bx = [], tg = new Set();
    g.forEach((row, r) => row.forEach((ch, c) => {
        if (ch === '@' || ch === '+') p = [r, c];
        if (ch === '$' || ch === '*') bx.push(r + ',' + c);
        if ('.*+'.includes(ch)) tg.add(r + ',' + c);
    }));
    const wallAt = (r, c) => !g[r] || g[r][c] === undefined || g[r][c] === '#';
    return {
        p, bx, hist: [], init: { p: p.slice(), bx: bx.slice() },
        move(dr, dc) {
            const nr = this.p[0] + dr, nc = this.p[1] + dc;
            if (wallAt(nr, nc)) return false;
            const k = nr + ',' + nc, i = this.bx.indexOf(k);
            let nb = this.bx.slice();
            if (i >= 0) {
                const br = nr + dr, bc = nc + dc, bk = br + ',' + bc;
                if (wallAt(br, bc) || this.bx.includes(bk)) return false;
                nb[i] = bk;
            }
            this.hist.push({ p: this.p.slice(), bx: this.bx.slice() });
            this.p = [nr, nc]; this.bx = nb; return true;
        },
        undo() { if (!this.hist.length) return; const s = this.hist.pop(); this.p = s.p; this.bx = s.bx; },
        restart() { this.p = this.init.p.slice(); this.bx = this.init.bx.slice(); this.hist = []; },
        won() { return this.bx.every(k => tg.has(k)); }
    };
}
let seed = 12345;
const rnd = () => { seed = (seed * 1664525 + 1013904223) % 4294967296; return seed / 4294967296; };
let steps = 0, mismatches = 0;
levels.forEach(L => {
    const eng = createEngine(L.layout), ref = reference(L.layout);
    for (let i = 0; i < 4000; i++) {
        const x = rnd();
        if (x < 0.80) { const d = DIR['UDLR'[Math.floor(rnd() * 4)]];
            if (!eng.isWon()) { eng.tryMove(...d); ref.move(...d); }
        } else if (x < 0.95) { if (!eng.isWon()) { eng.undo(); ref.undo(); } }
        else { eng.restart(); ref.restart(); }
        steps++;
        const ok = eng.getMoves() === ref.hist.length &&
            same(eng.getPlayer(), { row: ref.p[0], col: ref.p[1] }) &&
            boxesKey(eng) === JSON.stringify(ref.bx.slice().sort()) &&
            eng.getBoxes().length === ref.bx.length;
        if (!ok) { mismatches++; }
        if (eng.isWon() && !ref.won()) { mismatches++; }          // engine may only win when the reference agrees
        if (eng.isWon()) { eng.restart(); ref.restart(); }     // keep exploring after a win
    }
});
check('random play: engine == reference model for ' + steps + ' steps', mismatches === 0, mismatches + ' mismatches');

console.log((failed === 0 ? 'ALL PASSED' : 'FAILURES') + ': ' + passed + ' checks passed, ' + failed + ' failed');
process.exit(failed === 0 ? 0 : 1);
