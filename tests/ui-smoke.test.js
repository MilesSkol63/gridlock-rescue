/**
 * tests/ui-smoke.test.js - runs the REAL assets/js/game.js (play page) against a small fake DOM and a fake fetch().
 * It checks the wiring between the engine, the screen and save_progress.php's request format, not the appearance.
 *   node tests/ui-smoke.test.js
 */
const path = require('path');
const crypto = require('crypto');
const LEVELS = require('./fixtures/sliding_levels.json');
require('../public/assets/js/sliding-engine.js');
const E = global.GridlockEngine;
let passed = 0, failed = 0;
const check = (n, ok, d) => { if (ok) passed++; else { failed++; console.log('FAIL: ' + n + (d ? ' -> ' + d : '')); } };
const pause = ms => new Promise(r => setTimeout(r, ms));

function makeEl() {
    const listeners = {}, classes = new Set(), attrs = {};
    const el = {
        children: [], dataset: {}, style: { setProperty() {} }, offsetWidth: 1, clientWidth: 360, textContent: '', _html: '', type: '', disabled: false, value: '',
        classList: { add: c => classes.add(c), remove: c => classes.delete(c), toggle: (c, on) => (on ? classes.add(c) : classes.delete(c)), contains: c => classes.has(c) },
        set innerHTML(v) { this._html = v; if (v === '') this.children = []; }, get innerHTML() { return this._html; },
        setAttribute: (k, v) => { attrs[k] = v; }, getAttribute: k => attrs[k],
        appendChild(c) { this.children.push(c); return c; },
        addEventListener(t, f) { (listeners[t] = listeners[t] || []).push(f); },
        fire(t, ev) { (listeners[t] || []).forEach(f => f(Object.assign({ target: el, preventDefault() {}, button: 0, pointerId: 1, type: t }, ev))); },
        setPointerCapture() {}, focus() { el.fire('focus', {}); }, has: c => classes.has(c)
    };
    Object.defineProperty(el, 'className', { get: () => Array.from(classes).join(' '), set: v => { classes.clear(); String(v).split(' ').filter(Boolean).forEach(c => classes.add(c)); } });
    return el;
}

/** Builds a fresh fake page for one level and runs game.js on it. */
function loadPage(level, overrides) {
    const ids = ['game-board', 'board-area', 'move-count', 'btn-undo', 'btn-restart', 'win-overlay', 'win-status', 'btn-next', 'btn-retry', 'btn-replay', 'win-new-best', 'win-moves', 'win-rating', 'best-known', 'your-best'];
    const byId = {}; ids.forEach(id => { byId[id] = makeEl(); });
    byId['win-overlay'].className = 'sl-overlay d-none';
    ['btn-next', 'btn-retry', 'win-new-best'].forEach(id => { byId[id].className = 'd-none'; });
    byId['level-data'] = makeEl();
    const layoutHash = crypto.createHash('sha256').update(level.rows.join('\n')).digest('hex');
    const data = Object.assign({ levelId: level.id, title: level.name, rows: level.rows, layoutHash, par: level.best, canSave: true, bestMoves: null, nextLevelId: level.id + 1, csrf: 'TOKEN123', saveUrl: 'save_progress.php', playUrl: 'play.php' }, overrides || {});
    byId['level-data'].textContent = JSON.stringify(data);
    const docListeners = {};
    global.document = { getElementById: id => byId[id] || null, createElement: makeEl, addEventListener: (t, f) => { (docListeners[t] = docListeners[t] || []).push(f); } };
    global.window = global; global.location = { href: '', reload() { this.reloaded = true; } }; global.addEventListener = () => {};
    const calls = []; global.__fetchPlan = (global.__fetchPlan || []);
    global.fetch = (url, opts) => {
        calls.push({ url, opts, body: JSON.parse(opts.body) });
        const plan = global.__fetchPlan.shift() || { ok: true, status: 200, json: { success: true, message: 'New best! Progress saved.', is_new_best: true, best_moves: calls[calls.length - 1].body.moves.length, next_level_id: data.nextLevelId } };
        if (plan.reject) return Promise.reject(new Error('offline'));
        return Promise.resolve({ ok: plan.ok, status: plan.status, json: () => Promise.resolve(plan.json) });
    };
    delete require.cache[require.resolve('../public/assets/js/game.js')];
    require('../public/assets/js/game.js');
    const key = (k, extra) => (docListeners.keydown || []).forEach(f => f(Object.assign({ key: k, preventDefault() {}, target: {} }, extra)));
    const veh = id => byId['game-board'].children.find(c => c.dataset.id === id);
    return { byId, calls, key, veh, moves: () => Number(byId['move-count'].textContent), data };
}

/** play a stored solution with arrow keys (repeated presses = one slide) */
function playSolution(page, level) {
    const mirror = E.createGame(level.rows);
    for (const [id, to] of level.solution) {
        const v = mirror.getVehicle(id), cur = v.orient === 'H' ? v.col : v.row, dir = to > cur ? 1 : -1;
        page.veh(id).fire('focus', {});
        for (let s = 0; s < Math.abs(to - cur); s++) page.key(v.orient === 'H' ? (dir > 0 ? 'ArrowRight' : 'ArrowLeft') : (dir > 0 ? 'ArrowDown' : 'ArrowUp'));
        mirror.slide(id, to);
    }
}

(async () => {
    const L1 = LEVELS[0];

    /* ---- build and basic controls ---- */
    let p = loadPage(L1);
    check('every vehicle of the level is drawn as a focusable button', p.byId['game-board'].children.length === E.parseLevel(L1.rows).length);
    check('start state: 0 moves, undo disabled, overlay hidden', p.moves() === 0 && p.byId['btn-undo'].disabled && p.byId['win-overlay'].has('d-none'));
    check('par is shown and "your best" is empty', p.byId['best-known'].textContent == 5 && p.byId['your-best'].innerHTML === '&mdash;');
    p.veh('B').fire('focus', {}); p.key('ArrowUp');
    check('wrong-axis arrow key is ignored', p.moves() === 0);
    p.key('ArrowLeft'); p.key('ArrowLeft');
    check('two arrow presses in one direction count as ONE move', p.moves() === 1);
    p.key('z');
    check('undo reverts the slide', p.moves() === 0 && p.byId['btn-undo'].disabled);
    p.key('ArrowLeft'); p.key('r');
    check('restart resets the counter', p.moves() === 0);
    const b = p.veh('B');
    b.fire('pointerdown', { clientX: 200, clientY: 200 }); b.fire('pointermove', { clientX: 200 - 100, clientY: 200 }); b.fire('pointerup', { clientX: 100, clientY: 200 });
    check('a drag performs a legal slide through the engine', p.moves() === 1);
    check('nothing is sent to the server while playing', p.calls.length === 0);

    /* ---- winning as a player: the accepted slides are sent, never a score or a user id ---- */
    p = loadPage(L1);
    p.veh('B').fire('focus', {}); p.key('ArrowLeft'); p.key('ArrowLeft'); p.key('z'); p.key('r');      // moves that are undone / restarted must not be sent
    playSolution(p, L1);
    check('victory: rescue vehicle animates out', p.veh('R').has('exiting'));
    await pause(950);
    check('completion overlay shown with the move count', !p.byId['win-overlay'].has('d-none') && p.byId['win-moves'].textContent == L1.best);
    check('perfect rating text for an optimal game', /Perfect/.test(p.byId['win-rating'].textContent));
    check('exactly one save request was sent', p.calls.length === 1 && p.calls[0].url === 'save_progress.php' && p.calls[0].opts.method === 'POST');
    check('request carries the CSRF token in a header', p.calls[0].opts.headers['X-CSRF-Token'] === 'TOKEN123');
    check('body carries the layout fingerprint and accepted slides (undo/restart already applied)', JSON.stringify(Object.keys(p.calls[0].body).sort()) === '["layout_hash","level_id","moves"]' && p.calls[0].body.layout_hash === p.data.layoutHash && JSON.stringify(p.calls[0].body.moves) === JSON.stringify(L1.solution));
    check('no user id, role or score is ever sent', !/user|role|score/i.test(p.calls[0].opts.body));
    await pause(20);
    check('save success: message, new-best badge, Next Level button and best shown', /saved/i.test(p.byId['win-status'].textContent) && !p.byId['win-new-best'].has('d-none') && !p.byId['btn-next'].has('d-none') && p.byId['your-best'].textContent == L1.best);
    p.key('ArrowLeft');
    check('input is locked after victory', p.moves() === L1.best);
    p.byId['btn-next'].fire('click', {});
    check('Next Level opens play.php?id=<next>', global.location.href === 'play.php?id=' + (L1.id + 1));
    p.byId['btn-replay'].fire('click', {});
    check('replay hides the overlay and resets the game', p.byId['win-overlay'].has('d-none') && p.moves() === 0 && !p.veh('R').has('exiting'));

    /* ---- save failures and retry ---- */
    global.__fetchPlan = [{ ok: false, status: 500, json: { success: false, message: 'Could not save your progress. Please try again.' } }];
    p = loadPage(L1); playSolution(p, L1); await pause(950);
    check('server error: message and Retry button, no Next Level', /Could not save/.test(p.byId['win-status'].textContent) && !p.byId['btn-retry'].has('d-none') && p.byId['btn-next'].has('d-none'));
    p.byId['btn-retry'].fire('click', {}); await pause(20);
    check('retry sends the same slides again and succeeds', p.calls.length === 2 && JSON.stringify(p.calls[1].body.moves) === JSON.stringify(L1.solution) && p.byId['btn-retry'].has('d-none') && !p.byId['btn-next'].has('d-none'));
    global.__fetchPlan = [{ ok: false, status: 500, json: { success: false, message: 'x' } }];
    p = loadPage(L1); playSolution(p, L1); await pause(950);
    p.key('r');
    check('after a failed save the player may still restart (the failed result is simply discarded)', p.byId['win-overlay'].has('d-none') && p.moves() === 0);
    global.__fetchPlan = [{ reject: true }];
    p = loadPage(L1); playSolution(p, L1); await pause(950);
    check('network failure shows a clear message and Retry', /Network problem/.test(p.byId['win-status'].textContent) && !p.byId['btn-retry'].has('d-none'));
    global.__fetchPlan = [{ ok: false, status: 422, json: { success: false, message: 'The submitted game is not a valid solution of this level.' } }];
    p = loadPage(L1); playSolution(p, L1); await pause(950);
    check('server rejection message is shown', /not a valid solution/.test(p.byId['win-status'].textContent));
    global.__fetchPlan = [{ ok: false, status: 409, json: { success: false, message: 'This level was changed after you opened it. Reload the level before playing again.' } }];
    p = loadPage(L1); playSolution(p, L1); await pause(950);
    check('stale board response switches Retry Save to Reload Level', /changed after you opened/.test(p.byId['win-status'].textContent) && p.byId['btn-retry'].textContent === 'Reload Level');
    p.byId['btn-retry'].fire('click', {});
    check('Reload Level reloads the current database version of the puzzle', global.location.reloaded === true);

    /* ---- last level has no next level; admin preview never saves ---- */
    const L6 = LEVELS[5];
    p = loadPage(L6, { nextLevelId: null }); playSolution(p, L6); await pause(950);
    check('level 6 solved through the UI with the optimal 20 moves', p.moves() === 20 && p.byId['win-moves'].textContent == 20);
    await pause(20);
    check('last level: no Next Level button', p.byId['btn-next'].has('d-none'));
    global.__fetchPlan = [];
    p = loadPage(L1, { canSave: false }); playSolution(p, L1); await pause(950);
    check('admin preview: overlay shows, nothing is sent to the server', !p.byId['win-overlay'].has('d-none') && p.calls.length === 0);
    check('admin preview: status says nothing was saved', /Admin preview/.test(p.byId['win-status'].textContent) && !p.byId['btn-next'].has('d-none'));

    /* ---- every bundled level can be played to the end through the UI ---- */
    for (const L of LEVELS) {
        p = loadPage(L); playSolution(p, L); await pause(900);
        check('level ' + L.id + ': solved through the UI in ' + L.best + ' moves and saved with the full slide list', p.moves() === L.best && p.calls.length === 1 && p.calls[0].body.moves.length === L.best);
    }

    /* ---- board sizing on phones (page padding 8px each side) ---- */
    const G = window.GridlockGame;
    for (const w of [320, 360, 375, 390, 412, 768, 1280]) {
        const area = Math.min(w - 16, 640), c = G.computeCellSize(area);
        check('board fits a ' + w + 'px screen (cell ' + c + 'px)', c * 6 + G.FRAME_W + G.EXIT_W + 4 <= area && c >= 32);
        if (w === 360 || w === 375) check('cells >= 44px at ' + w + 'px', c >= 44, 'cell=' + c);
    }
    /* ---- invalid level data is reported, not crashed ---- */
    p = loadPage(L1, { rows: ['xx', 'yy'] });
    check('invalid level data shows an error message instead of crashing', /cannot be played/.test(p.byId['game-board'].textContent) && p.byId['btn-undo'].disabled);

    console.log((failed === 0 ? 'ALL PASSED' : 'FAILURES') + ': ' + passed + ' checks passed, ' + failed + ' failed');
    process.exit(failed === 0 ? 0 : 1);
})();
