/**
 * assets/js/sliding-engine.js (NEW) - Gridlock Rescue sliding-block ENGINE (pure logic: no DOM, no rendering).
 * Same rules as includes/sliding.php (the server replays every submitted game with that file).
 *
 * Board: 6x6. Vehicle = { id, orient: 'H'|'V', length: 2|3, row, col, rescue: bool }
 *   (row, col) is the top-left cell of the vehicle. Row 0 is the top.
 * Exit: on the right edge of EXIT_ROW. Only the rescue vehicle may leave the board, and only there.
 *   While exiting, its col goes up to BOARD (6) = completely off the board.
 * Moves: a vehicle slides along its own axis any distance in one go; one slide = one move.
 */
(function (root) {
  "use strict";

  var BOARD = 6;
  var EXIT_ROW = 2;

  /* ---------- level text -> vehicles ----------
       A level is 6 strings of 6 characters. '.' is empty, 'R' is the rescue vehicle,
       any other letter is a vehicle. Orientation and length are read from the shape. */
  function parseLevel(rows) {
    if (!Array.isArray(rows) || rows.length !== BOARD) {
      throw new Error("A level needs " + BOARD + " rows.");
    }
    rows.forEach(function (r) {
      if (typeof r !== "string" || r.length !== BOARD) {
        throw new Error("Each row needs " + BOARD + " characters.");
      }
    });
    var cells = {};
    for (var r = 0; r < BOARD; r++) {
      for (var c = 0; c < BOARD; c++) {
        var ch = rows[r][c];
        if (ch === ".") {
          continue;
        }
        if (!/^[A-Z]$/.test(ch)) {
          throw new Error('Invalid character "' + ch + '".');
        }
        (cells[ch] = cells[ch] || []).push([r, c]);
      }
    }
    var vehicles = [];
    Object.keys(cells)
      .sort()
      .forEach(function (id) {
        var list = cells[id];
        if (list.length < 2 || list.length > 3) {
          throw new Error("Vehicle " + id + " must use 2 or 3 cells.");
        }
        var sameRow = list.every(function (p) {
          return p[0] === list[0][0];
        });
        var sameCol = list.every(function (p) {
          return p[1] === list[0][1];
        });
        if (!sameRow && !sameCol) {
          throw new Error("Vehicle " + id + " is not a straight line.");
        }
        var orient = sameRow ? "H" : "V";
        var idx = orient === "H" ? 1 : 0;
        var sorted = list
          .map(function (p) {
            return p[idx];
          })
          .sort(function (a, b) {
            return a - b;
          });
        for (var i = 1; i < sorted.length; i++) {
          if (sorted[i] !== sorted[i - 1] + 1) {
            throw new Error("Vehicle " + id + " has gaps.");
          }
        }
        vehicles.push({
          id: id,
          orient: orient,
          length: list.length,
          row: Math.min.apply(
            null,
            list.map(function (p) {
              return p[0];
            }),
          ),
          col: Math.min.apply(
            null,
            list.map(function (p) {
              return p[1];
            }),
          ),
          rescue: id === "R",
        });
      });
    var rescue = vehicles.filter(function (v) {
      return v.rescue;
    });
    if (rescue.length !== 1) {
      throw new Error("A level needs exactly one rescue vehicle (R).");
    }
    if (rescue[0].orient !== "H" || rescue[0].row !== EXIT_ROW) {
      throw new Error("The rescue vehicle must be horizontal in the exit row.");
    }
    return vehicles;
  }

  /* ---------- low level helpers on a plain vehicle array ---------- */
  function cellsOf(v) {
    var out = [];
    for (var i = 0; i < v.length; i++) {
      out.push(v.orient === "H" ? [v.row, v.col + i] : [v.row + i, v.col]);
    }
    return out;
  }
  function occupancy(vehicles, skipId) {
    var grid = [];
    for (var r = 0; r < BOARD; r++) {
      grid.push(new Array(BOARD).fill(null));
    }
    vehicles.forEach(function (v) {
      if (v.id === skipId) {
        return;
      }
      cellsOf(v).forEach(function (p) {
        grid[p[0]][p[1]] = v.id;
      });
    });
    return grid;
  }
  function pos(v) {
    return v.orient === "H" ? v.col : v.row;
  }
  function withPos(v, p) {
    var n = {
      id: v.id,
      orient: v.orient,
      length: v.length,
      row: v.row,
      col: v.col,
      rescue: v.rescue,
    };
    if (v.orient === "H") {
      n.col = p;
    } else {
      n.row = p;
    }
    return n;
  }

  /** Lowest and highest position the vehicle can slide to (position = its top/left cell index). */
  function slideRange(vehicles, id) {
    var v = vehicles.filter(function (x) {
      return x.id === id;
    })[0];
    if (!v) {
      throw new Error("Unknown vehicle " + id);
    }
    var grid = occupancy(vehicles, id);
    var canExit = v.rescue && v.orient === "H" && v.row === EXIT_ROW;
    var p = pos(v),
      lo = p,
      hi = p;
    function free(q) {
      // can the vehicle stand with its start at q?
      return cellsOf(withPos(v, q)).every(function (c) {
        if (c[0] < 0 || c[1] < 0 || c[0] >= BOARD) {
          return false;
        }
        if (c[1] >= BOARD) {
          return canExit;
        } // only the rescue vehicle may pass the exit
        return grid[c[0]][c[1]] === null;
      });
    }
    while (free(lo - 1)) {
      lo--;
    }
    var maxStart = canExit ? BOARD : BOARD - v.length; // rescue: until completely off the board
    while (hi < maxStart && free(hi + 1)) {
      hi++;
    }
    return { min: lo, max: hi };
  }

  function isExited(v) {
    return v.rescue && v.col >= BOARD;
  }

  /* ---------- the game object ---------- */
  function createGame(levelRows) {
    var initial = parseLevel(levelRows);
    var vehicles, history, won;

    function clone(list) {
      return list.map(function (v) {
        return withPos(v, pos(v));
      });
    }
    function reset() {
      vehicles = clone(initial);
      history = [];
      won = false;
    }
    function find(id) {
      return vehicles.filter(function (v) {
        return v.id === id;
      })[0];
    }
    reset();

    return {
      getVehicles: function () {
        return clone(vehicles);
      },
      getVehicle: function (id) {
        var v = find(id);
        return v ? withPos(v, pos(v)) : null;
      },
      getMoves: function () {
        return history.length;
      },
      /** The accepted slides in order, [[vehicleId, newPosition], ...]. Undo and restart are already applied. */
      getHistory: function () {
        return history.map(function (h) {
          return [h.id, h.to];
        });
      },
      isWon: function () {
        return won;
      },
      canUndo: function () {
        return history.length > 0 && !won;
      },
      range: function (id) {
        return slideRange(vehicles, id);
      },

      /**
       * Slide vehicle `id` so its start cell is `to`. Returns {ok, reason, from, to, exited}.
       * One successful call = one move, however far it slides.
       * opts.merge = true continues the previous slide of the same vehicle in the same direction
       * (used by the keyboard so that several arrow presses count as ONE uninterrupted slide).
       */
      slide: function (id, to, opts) {
        if (won) {
          return { ok: false, reason: "finished" };
        }
        var v = find(id);
        if (!v) {
          return { ok: false, reason: "unknown" };
        }
        if (typeof to !== "number" || Math.floor(to) !== to) {
          return { ok: false, reason: "invalid" };
        }
        var from = pos(v);
        if (to === from) {
          return { ok: false, reason: "no-change" };
        }
        var range = slideRange(vehicles, id);
        if (to < range.min || to > range.max) {
          return { ok: false, reason: "blocked" };
        }

        var last = history[history.length - 1];
        var dir = to > from ? 1 : -1;
        if (opts && opts.merge && last && last.id === id && last.dir === dir) {
          last.to = to; // same slide continues: no new move
        } else {
          history.push({ id: id, from: from, to: to, dir: dir });
        }
        var moved = withPos(v, to);
        vehicles = vehicles.map(function (x) {
          return x.id === id ? moved : x;
        });
        if (isExited(moved)) {
          won = true;
        }
        return { ok: true, from: from, to: to, exited: won };
      },

      undo: function () {
        if (!this.canUndo()) {
          return false;
        }
        var h = history.pop();
        var v = find(h.id);
        vehicles = vehicles.map(function (x) {
          return x.id === h.id ? withPos(v, h.from) : x;
        });
        return true;
      },
      restart: reset,
    };
  }

  /* ---------- solver (used by tests and the level tools) ----------
       Breadth-first search over all positions; every move is "slide one vehicle to any reachable position",
       exactly the rule of the game, using the same slideRange() as the engine. */
  function stateKey(vehicles) {
    return vehicles
      .map(function (v) {
        return pos(v);
      })
      .join(",");
  }

  function solve(levelRows, limit) {
    var start = parseLevel(levelRows);
    var startKey = stateKey(start);
    var seen = {};
    seen[startKey] = null;
    var queue = [start],
      head = 0;
    limit = limit || 400000;
    while (head < queue.length) {
      var state = queue[head++];
      var key = stateKey(state);
      for (var i = 0; i < state.length; i++) {
        var v = state[i];
        var range = slideRange(state, v.id);
        for (var p = range.min; p <= range.max; p++) {
          if (p === pos(v)) {
            continue;
          }
          var next = state.slice();
          next[i] = withPos(v, p);
          var nk = stateKey(next);
          if (Object.prototype.hasOwnProperty.call(seen, nk)) {
            continue;
          }
          seen[nk] = { prev: key, id: v.id, to: p };
          if (isExited(next[i])) {
            // goal reached: rebuild the move list
            var moves = [],
              cur = nk;
            while (seen[cur]) {
              moves.push({ id: seen[cur].id, to: seen[cur].to });
              cur = seen[cur].prev;
            }
            return { moves: moves.reverse(), states: head };
          }
          queue.push(next);
          if (queue.length > limit) {
            return null;
          }
        }
      }
    }
    return null;
  }

  var api = {
    BOARD: BOARD,
    EXIT_ROW: EXIT_ROW,
    parseLevel: parseLevel,
    createGame: createGame,
    solve: solve,
    cellsOf: cellsOf,
  };
  if (typeof module !== "undefined" && module.exports) {
    module.exports = api;
  }
  root.GridlockEngine = api;
})(typeof window !== "undefined" ? window : globalThis);
