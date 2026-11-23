/** tests/engine.test.js - automated rule tests for assets/js/sliding-engine.js and the six bundled levels.   node tests/engine.test.js */
const E = require("../public/assets/js/sliding-engine.js");
const LEVELS = require("./fixtures/sliding_levels.json"); // same six levels that database/*.sql inserts (checked by sql_seed.test.js)

let passed = 0,
  failed = 0;
function check(name, ok, detail) {
  if (ok) {
    passed++;
  } else {
    failed++;
    console.log("FAIL: " + name + (detail ? " -> " + detail : ""));
  }
}
function throws(fn) {
  try {
    fn();
    return false;
  } catch (e) {
    return true;
  }
}
const pos = (v) => (v.orient === "H" ? v.col : v.row);
const snapshot = (g) =>
  JSON.stringify(g.getVehicles().map((v) => [v.id, v.row, v.col]));

const BASE = ["......", "......", "RR....", "......", "......", "......"];

/* ---- 1. level parsing and the vehicle structure ---- */
{
  const v = E.parseLevel([
    "A.....",
    "A.....",
    "RR.BBB",
    "......",
    "......",
    "......",
  ]);
  const a = v.find((x) => x.id === "A"),
    b = v.find((x) => x.id === "B"),
    r = v.find((x) => x.id === "R");
  check(
    "vertical vehicle parsed",
    a.orient === "V" &&
      a.length === 2 &&
      a.row === 0 &&
      a.col === 0 &&
      !a.rescue,
  );
  check(
    "length-3 horizontal vehicle parsed",
    b.orient === "H" && b.length === 3 && b.col === 3 && b.row === 2,
  );
  check(
    "rescue vehicle flagged",
    r.rescue === true && r.orient === "H" && r.length === 2,
  );
  check(
    "every vehicle has id, orient, length, row, col, rescue",
    v.every((x) =>
      ["id", "orient", "length", "row", "col", "rescue"].every((k) => k in x),
    ),
  );
}
check(
  "reject 5 rows",
  throws(() => E.parseLevel(BASE.slice(1))),
);
check(
  "reject short row",
  throws(() => E.parseLevel([".....", ...BASE.slice(1)])),
);
check(
  "reject lowercase / symbol",
  throws(() => E.parseLevel(["a.....", ...BASE.slice(1)])),
);
check(
  "reject length-1 vehicle",
  throws(() =>
    E.parseLevel(["A.....", "......", "RR....", "......", "......", "......"]),
  ),
);
check(
  "reject length-4 vehicle",
  throws(() =>
    E.parseLevel(["AAAA..", "......", "RR....", "......", "......", "......"]),
  ),
);
check(
  "reject vehicle with a gap",
  throws(() =>
    E.parseLevel(["A.A...", "......", "RR....", "......", "......", "......"]),
  ),
);
check(
  "reject L-shaped vehicle",
  throws(() =>
    E.parseLevel(["AA....", "A.....", "RR....", "......", "......", "......"]),
  ),
);
check(
  "reject missing rescue vehicle",
  throws(() =>
    E.parseLevel(["AA....", "......", "......", "......", "......", "......"]),
  ),
);
check(
  "reject rescue vehicle outside the exit row",
  throws(() =>
    E.parseLevel(["......", "RR....", "......", "......", "......", "......"]),
  ),
);
check(
  "reject vertical rescue vehicle",
  throws(() =>
    E.parseLevel(["..R...", "..R...", "..R...", "......", "......", "......"]),
  ),
);

/* ---- 2. movement, collisions, boundaries ---- */
{
  const g = E.createGame([
    "A.....",
    "A.....",
    "RR.B..",
    "..CB..",
    "..C...",
    "EE....",
  ]);
  check(
    "vertical vehicle blocked below by the rescue vehicle",
    JSON.stringify(g.range("A")) === '{"min":0,"max":0}',
  );
  check(
    "horizontal vehicle range stays in its row",
    JSON.stringify(g.range("E")) === '{"min":0,"max":4}',
  );
  check(
    "cannot slide into another vehicle",
    g.slide("A", 2).ok === false && g.slide("A", 2).reason === "blocked",
  );
  check("cannot leave the board (top)", g.slide("B", -1).ok === false);
  check("cannot leave the board (bottom)", g.slide("C", 5).ok === false);
  check(
    "non-rescue vehicle cannot leave through the exit",
    g.slide("E", 6).ok === false && g.slide("E", 5).ok === false,
  );
  const j = E.createGame([
    "......",
    "......",
    "RR....",
    "FF.GG.",
    "......",
    "......",
  ]);
  check(
    "cannot jump over a vehicle",
    j.range("F").max === 1 &&
      j.slide("F", 4).ok === false &&
      j.getMoves() === 0,
  );
  check("rejected moves are not counted", g.getMoves() === 0);
  check(
    "fractional / invalid targets rejected",
    g.slide("E", 1.5).ok === false &&
      g.slide("E", "x").ok === false &&
      g.slide("Z", 1).ok === false,
  );
  check(
    "no-change slide rejected and not counted",
    g.slide("E", 0).ok === false && g.getMoves() === 0,
  );
  const r = g.slide("E", 3);
  check(
    "free slide of 3 cells is ONE move",
    r.ok && g.getMoves() === 1 && g.getVehicle("E").col === 3,
  );
  check("vehicle moved only along its axis", g.getVehicle("E").row === 5);
  check(
    "other vehicles unchanged",
    g.getVehicle("A").row === 0 && g.getVehicle("B").row === 2,
  );
}
{
  const g = E.createGame([
    "......",
    "......",
    "RR..AA",
    "......",
    "......",
    "......",
  ]);
  check(
    "rescue stops in front of a blocker in the exit lane",
    g.range("R").min === 0 && g.range("R").max === 2,
  );
  check(
    "rescue cannot pass the blocker",
    g.slide("R", 3).ok === false && g.slide("R", 6).ok === false,
  );
}

/* ---- 3. exit and victory ---- */
{
  const g = E.createGame([
    "A.....",
    "A.....",
    ".RR...",
    "......",
    "......",
    "......",
  ]);
  check("exit lane clear: rescue range reaches 6", g.range("R").max === 6);
  check(
    "moving next to the exit does not win",
    g.slide("R", 4).ok && !g.isWon(),
  );
  check(
    "victory only after leaving the board",
    g.slide("R", 6).exited === true && g.isWon(),
  );
  check(
    "input locked after victory",
    g.slide("A", 1).ok === false && g.slide("A", 1).reason === "finished",
  );
  check(
    "undo disabled after victory",
    g.undo() === false && g.canUndo() === false,
  );
  check("victory took 2 moves", g.getMoves() === 2);
  g.restart();
  check(
    "restart clears victory and moves",
    !g.isWon() && g.getMoves() === 0 && g.getVehicle("R").col === 1,
  );
}
{
  const g = E.createGame([
    "......",
    "......",
    "RR....",
    "......",
    "..AAA.",
    "......",
  ]);
  g.slide("A", 3);
  check("a non-rescue vehicle at the right edge does not win", !g.isWon());
  const h = E.createGame([
    "......",
    "......",
    ".RR...",
    "......",
    "......",
    "......",
  ]);
  check("rescue cannot leave through the left", h.slide("R", -1).ok === false);
  check(
    "whole slide from col 1 to the exit is one move",
    h.slide("R", 6).ok && h.isWon() && h.getMoves() === 1,
  );
}

/* ---- 4. undo and restart ---- */
{
  const g = E.createGame(LEVELS[1].rows);
  const start = snapshot(g);
  g.slide("B", 0);
  g.slide("E", 1);
  g.slide("C", 3);
  const afterThree = snapshot(g);
  check("3 slides = 3 moves", g.getMoves() === 3);
  check("undo 1 succeeds", g.undo() && g.getMoves() === 2);
  check(
    "redo the same slide gives the same state",
    g.slide("C", 3).ok && snapshot(g) === afterThree,
  );
  g.undo();
  g.undo();
  g.undo();
  check(
    "undo x3 restores the original board and 0 moves",
    snapshot(g) === start && g.getMoves() === 0,
  );
  check("undo with empty history is refused", g.undo() === false);
  g.slide("B", 0);
  g.slide("E", 1);
  g.restart();
  check(
    "restart restores the original board and 0 moves",
    snapshot(g) === start && g.getMoves() === 0,
  );
  check("restart is repeatable", (g.restart(), snapshot(g) === start));
}

/* ---- 5. keyboard-style merged slides ---- */
{
  const g = E.createGame([
    "......",
    "......",
    "RR....",
    "......",
    "..AA..",
    "......",
  ]);
  check(
    "first step is a move",
    g.slide("A", 3, { merge: true }).ok && g.getMoves() === 1,
  );
  check(
    "continuing in the same direction merges into the same move",
    g.slide("A", 4, { merge: true }).ok && g.getMoves() === 1,
  );
  check(
    "merged slide is one undo step",
    g.undo() && g.getVehicle("A").col === 2 && g.getMoves() === 0,
  );
  g.slide("A", 3, { merge: true });
  check(
    "reversing direction starts a new move",
    g.slide("A", 2, { merge: true }).ok && g.getMoves() === 2,
  );
  g.slide("A", 3);
  const m = g.getMoves();
  g.slide("R", 1, { merge: true });
  check("different vehicle starts a new move", g.getMoves() === m + 1);
}

/* ---- 5b. history sent to the server (getHistory) ---- */
{
  const g = E.createGame(LEVELS[0].rows);
  check("empty history at the start", JSON.stringify(g.getHistory()) === "[]");
  g.slide("B", 0);
  g.slide("C", 0);
  check(
    "history lists the accepted slides in order",
    JSON.stringify(g.getHistory()) === '[["B",0],["C",0]]',
  );
  g.undo();
  check(
    "undo removes the last slide from the history",
    JSON.stringify(g.getHistory()) === '[["B",0]]' && g.getMoves() === 1,
  );
  g.restart();
  check("restart empties the history", g.getHistory().length === 0);
  const k = E.createGame([
    "......",
    "......",
    "RR....",
    "......",
    "..AA..",
    "......",
  ]);
  k.slide("A", 3, { merge: true });
  k.slide("A", 4, { merge: true });
  check(
    "merged keyboard slides appear as ONE history entry with the final position",
    JSON.stringify(k.getHistory()) === '[["A",4]]',
  );
  check(
    "history length always equals the move count",
    k.getHistory().length === k.getMoves(),
  );
  const s = E.createGame(LEVELS[0].rows);
  LEVELS[0].solution.forEach((m) => s.slide(m[0], m[1]));
  check(
    "the stored solution equals the history of a finished game",
    JSON.stringify(s.getHistory()) === JSON.stringify(LEVELS[0].solution) &&
      s.isWon(),
  );
}

/* ---- 6. the six bundled levels ---- */
// An independent solver written for this test (cell grid, no shared code with the engine) to confirm "best".
function independentBest(rows) {
  const cells = {};
  rows.forEach((row, r) =>
    row.split("").forEach((ch, c) => {
      if (ch !== ".") (cells[ch] = cells[ch] || []).push([r, c]);
    }),
  );
  const vs = Object.keys(cells)
    .sort()
    .map((id) => {
      const l = cells[id],
        h = l[0][0] === l[1][0];
      return {
        id,
        h,
        len: l.length,
        fixed: h ? l[0][0] : l[0][1],
        start: h
          ? Math.min(...l.map((p) => p[1]))
          : Math.min(...l.map((p) => p[0])),
      };
    });
  const occ = (st) => {
    const s = new Set();
    vs.forEach((v, i) => {
      for (let k = 0; k < v.len; k++)
        s.add(v.h ? v.fixed + "," + (st[i] + k) : st[i] + k + "," + v.fixed);
    });
    return s;
  };
  const ri = vs.findIndex((v) => v.id === "R");
  const startState = vs.map((v) => v.start),
    seen = new Set([startState.join()]);
  let frontier = [startState],
    depth = 0;
  while (frontier.length) {
    depth++;
    const nextF = [];
    for (const st of frontier) {
      const o = occ(st);
      for (let i = 0; i < vs.length; i++) {
        const v = vs[i];
        const cellAt = (p) => (v.h ? v.fixed + "," + p : p + "," + v.fixed);
        const cur = new Set();
        for (let k = 0; k < v.len; k++) cur.add(cellAt(st[i] + k));
        const clear = (p) => {
          for (let k = 0; k < v.len; k++) {
            const q = p + k;
            if (v.h && i === ri && q >= 6) continue;
            if (q < 0 || q > 5) return false;
            const c = cellAt(q);
            if (o.has(c) && !cur.has(c)) return false;
          }
          return true;
        };
        for (const dir of [-1, 1]) {
          let p = st[i];
          for (;;) {
            p += dir;
            const lim = i === ri ? 6 : 6 - v.len;
            if (p < 0 || p > lim || !clear(p)) break;
            const ns = st.slice();
            ns[i] = p;
            if (i === ri && p >= 6) return depth;
            const key = ns.join();
            if (!seen.has(key)) {
              seen.add(key);
              nextF.push(ns);
            }
          }
        }
      }
    }
    frontier = nextF;
  }
  return null;
}
LEVELS.forEach((L) => {
  const name = "Level " + L.id + " (" + L.name + ")";
  const game = E.createGame(L.rows);
  const vs = game.getVehicles();
  check(
    name + ": has exactly one rescue vehicle",
    vs.filter((v) => v.rescue).length === 1,
  );
  check(
    name + ": uses vehicles of length 2",
    vs.some((v) => v.length === 2),
  );
  check(
    name + ": both orientations used",
    vs.some((v) => v.orient === "H") && vs.some((v) => v.orient === "V"),
  );
  let wonEarly = false,
    allOk = true;
  L.solution.forEach((m, i) => {
    const r = game.slide(m[0], m[1]);
    if (!r.ok) {
      allOk = false;
    }
    if (i < L.solution.length - 1 && game.isWon()) {
      wonEarly = true;
    }
  });
  check(name + ": stored solution replays legally through the engine", allOk);
  check(name + ": not won before the last move", !wonEarly);
  check(
    name + ": solved - rescue exited",
    game.isWon() && game.getVehicle("R").col === 6,
  );
  check(
    name + ": move count equals best (" + L.best + ")",
    game.getMoves() === L.best && L.solution.length === L.best,
  );
  check(
    name + ": engine solver agrees on the optimal length",
    E.solve(L.rows, 500000).moves.length === L.best,
  );
  check(
    name + ": independent solver agrees on the optimal length",
    independentBest(L.rows) === L.best,
    "independent=" + independentBest(L.rows),
  );
});
check(
  "vehicles of length 3 appear in the levels",
  LEVELS.some((L) => E.parseLevel(L.rows).some((v) => v.length === 3)),
);
check("six levels in the campaign", LEVELS.length === 6);
check(
  "ids are 1..6 in order and names are unique",
  LEVELS.every((L, i) => L.id === i + 1) &&
    new Set(LEVELS.map((L) => L.name)).size === 6,
);
check(
  "layouts are all different",
  new Set(LEVELS.map((L) => L.rows.join("/"))).size === 6,
);
check(
  "optimal moves rise strictly with every level",
  LEVELS.every((L, i) => i === 0 || L.best > LEVELS[i - 1].best),
  LEVELS.map((L) => L.best).join(","),
);
check(
  "curve is gradual: no step larger than 5 moves",
  LEVELS.every((L, i) => i === 0 || L.best - LEVELS[i - 1].best <= 5),
  LEVELS.map((L) => L.best).join(","),
);
check(
  "the three new puzzles sit between 10 and 20 moves",
  [2, 3, 4].every((i) => LEVELS[i].best > 10 && LEVELS[i].best < 20),
);
check(
  "difficulty labels never go down",
  ["Easy", "Medium", "Hard", "Expert"].length &&
    LEVELS.every(
      (L, i) =>
        i === 0 ||
        ["Easy", "Medium", "Hard", "Expert"].indexOf(L.difficulty) >=
          ["Easy", "Medium", "Hard", "Expert"].indexOf(
            LEVELS[i - 1].difficulty,
          ),
    ),
);
check(
  "structure grows: vehicles who must move more than once (revisits) is higher in the last two levels than in the first two",
  Math.min(LEVELS[4].metrics.revisits, LEVELS[5].metrics.revisits) >
    Math.max(LEVELS[0].metrics.revisits, LEVELS[1].metrics.revisits),
);
check(
  "vehicle count alone does not explain difficulty (levels 2 and 3 share a count, 5 and 6 share a count)",
  LEVELS[1].metrics.vehicles === LEVELS[2].metrics.vehicles &&
    LEVELS[4].metrics.vehicles === LEVELS[5].metrics.vehicles,
);
LEVELS.forEach((L) => {
  const vs = E.parseLevel(L.rows),
    sol = E.solve(L.rows, 500000);
  const movers = new Set(sol.moves.map((m) => m.id)).size;
  check(
    "Level " + L.id + ": stored metrics match a fresh measurement",
    L.metrics.vehicles === vs.length &&
      L.metrics.movers === movers &&
      L.metrics.revisits === sol.moves.length - movers,
  );
});

/* ---- 7. randomised play against invariants ---- */
{
  let seed = 99;
  const rnd = () => {
    seed = (seed * 1664525 + 1013904223) % 4294967296;
    return seed / 4294967296;
  };
  let steps = 0,
    bad = 0;
  LEVELS.forEach((L) => {
    const g = E.createGame(L.rows);
    for (let i = 0; i < 6000; i++) {
      const x = rnd();
      const vs = g.getVehicles();
      if (x < 0.75) {
        const v = vs[Math.floor(rnd() * vs.length)];
        g.slide(v.id, Math.floor(rnd() * 8) - 1);
      } else if (x < 0.93) {
        g.undo();
      } else {
        g.restart();
      }
      steps++;
      const grid = {};
      let ok = true;
      g.getVehicles().forEach((v) =>
        E.cellsOf(v).forEach((c) => {
          const inside = c[0] >= 0 && c[0] < 6 && c[1] >= 0 && c[1] < 6;
          if (!inside && !(v.rescue && c[0] === 2 && c[1] >= 6)) ok = false; // only the rescue vehicle may be off the right edge
          if (inside) {
            const k = c[0] + "," + c[1];
            if (grid[k]) ok = false;
            grid[k] = 1;
          }
        }),
      );
      g.getVehicles().forEach((v) => {
        const o = E.parseLevel(L.rows).find((w) => w.id === v.id);
        if (o.orient !== v.orient || o.length !== v.length) ok = false;
      });
      if (!ok) bad++;
      if (g.isWon()) g.restart();
    }
  });
  check(
    "random play: " +
      steps +
      " steps, no overlap / out-of-bounds / orientation change",
    bad === 0,
    bad + " violations",
  );
}

console.log(
  (failed === 0 ? "ALL PASSED" : "FAILURES") +
    ": " +
    passed +
    " checks passed, " +
    failed +
    " failed",
);
process.exit(failed === 0 ? 0 : 1);
