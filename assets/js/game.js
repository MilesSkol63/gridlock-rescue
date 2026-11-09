/**
 * assets/js/game.js  (NEW)  - Gridlock Rescue game engine + page wiring.
 *
 * Part 1 (createEngine) is pure game logic with NO browser code, so it can also be tested in Node.
 * Part 2 (startGame) connects the engine to play.php: keyboard, buttons, drawing and saving.
 *
 * Event loop (turn based, no timers):
 *   Player input -> validate move -> update state -> render -> check win -> save if won
 *
 * Coordinates are always (row, col); row 0 is the TOP row, so "up" means row - 1.
 * Level text: # wall  - floor  . target  $ box  * box on target  @ player  + player on target
 * Rules: only successful moves count; undo subtracts one; restart resets to 0.
 */

/* ============================== PART 1: ENGINE ============================== */

function createEngine(layoutText) {
  "use strict";

  // ---- static layer: never changes after parsing ----
  const lines = String(layoutText)
    .replace(/\r\n?/g, "\n")
    .split("\n")
    .filter(function (l) {
      return l.length > 0;
    });
  const rows = lines.length;
  let cols = 0;
  lines.forEach(function (l) {
    cols = Math.max(cols, l.length);
  });

  const wall = [];
  const target = [];
  let startPlayer = null;
  const startBoxes = [];
  let playerCount = 0;
  let targetCount = 0;

  for (let r = 0; r < rows; r++) {
    wall.push([]);
    target.push([]);
    for (let c = 0; c < cols; c++) {
      const ch = c < lines[r].length ? lines[r][c] : "#"; // short rows are padded with wall
      wall[r][c] = ch === "#";
      target[r][c] = ch === "." || ch === "*" || ch === "+";
      if (target[r][c]) {
        targetCount++;
      }
      if (ch === "@" || ch === "+") {
        startPlayer = { row: r, col: c };
        playerCount++;
      }
      if (ch === "$" || ch === "*") {
        startBoxes.push({ row: r, col: c });
      }
    }
  }

  let error = "";
  if (rows === 0) {
    error = "The level is empty.";
  } else if (playerCount !== 1) {
    error = "The level must contain exactly one player.";
  } else if (startBoxes.length === 0) {
    error = "The level has no boxes.";
  } else if (startBoxes.length !== targetCount) {
    error = "The level needs the same number of boxes and targets.";
  }

  // ---- dynamic layer: changes every move ----
  let player = null;
  let boxes = [];
  let history = []; // one snapshot per successful move; moves === history.length
  let won = false;

  function isWall(r, c) {
    return r < 0 || c < 0 || r >= rows || c >= cols || wall[r][c]; // outside the grid counts as wall
  }
  function isTarget(r, c) {
    return !isWall(r, c) && target[r][c];
  }
  function findBox(r, c) {
    for (let i = 0; i < boxes.length; i++) {
      if (boxes[i].row === r && boxes[i].col === c) {
        return i;
      }
    }
    return -1;
  }
  function snapshot() {
    return {
      player: { row: player.row, col: player.col },
      boxes: boxes.map(function (b) {
        return { row: b.row, col: b.col };
      }),
    };
  }
  function allBoxesOnTargets() {
    return (
      boxes.length > 0 &&
      boxes.every(function (b) {
        return isTarget(b.row, b.col);
      })
    );
  }

  function restart() {
    if (error) {
      return;
    }
    player = { row: startPlayer.row, col: startPlayer.col };
    boxes = startBoxes.map(function (b) {
      return { row: b.row, col: b.col };
    });
    history = [];
    won = false;
  }

  /** Try to move the player by (dr, dc). Returns 'blocked', 'moved' or 'pushed'. */
  function tryMove(dr, dc) {
    if (error || won) {
      return "blocked";
    }

    const nextR = player.row + dr;
    const nextC = player.col + dc;
    if (isWall(nextR, nextC)) {
      return "blocked";
    }

    const boxIndex = findBox(nextR, nextC);
    let result = "moved";
    if (boxIndex !== -1) {
      const beyondR = nextR + dr;
      const beyondC = nextC + dc;
      if (isWall(beyondR, beyondC) || findBox(beyondR, beyondC) !== -1) {
        return "blocked"; // wall or another box behind it: no move, no count
      }
      history.push(snapshot()); // save state BEFORE changing anything
      boxes[boxIndex] = { row: beyondR, col: beyondC };
      result = "pushed";
    } else {
      history.push(snapshot());
    }
    player = { row: nextR, col: nextC };
    if (allBoxesOnTargets()) {
      won = true;
    }
    return result;
  }

  function undo() {
    if (error || won || history.length === 0) {
      return false;
    }
    const snap = history.pop();
    player = { row: snap.player.row, col: snap.player.col };
    boxes = snap.boxes.map(function (b) {
      return { row: b.row, col: b.col };
    });
    return true;
  }

  restart();

  return {
    error: error,
    rows: rows,
    cols: cols,
    isWall: isWall,
    isTarget: isTarget,
    findBox: findBox,
    tryMove: tryMove,
    undo: undo,
    restart: restart,
    getPlayer: function () {
      return { row: player.row, col: player.col };
    },
    getBoxes: function () {
      return boxes.map(function (b) {
        return { row: b.row, col: b.col };
      });
    },
    getMoves: function () {
      return history.length;
    },
    canUndo: function () {
      return !won && history.length > 0;
    },
    isWon: function () {
      return won;
    },
  };
}

/* ============================ PART 2: PAGE WIRING ============================ */

function startGame() {
  "use strict";

  const data = JSON.parse(document.getElementById("level-data").textContent);
  const engine = createEngine(data.layout);

  const boardEl = document.getElementById("game-board");
  const wrapEl = document.getElementById("board-wrap");
  const moveEl = document.getElementById("move-count");
  const bestEl = document.getElementById("best-moves");
  const undoBtn = document.getElementById("btn-undo");
  const restartBtn = document.getElementById("btn-restart");
  const overlay = document.getElementById("win-overlay");
  const winMovesEl = document.getElementById("win-moves");
  const winStatusEl = document.getElementById("win-status");
  const newBestEl = document.getElementById("win-new-best");
  const nextBtn = document.getElementById("btn-next");
  const replayBtn = document.getElementById("btn-replay");
  const retryBtn = document.getElementById("btn-retry");

  let bestMoves = data.bestMoves; // null when not completed yet (or admin preview)
  let finalMoves = 0; // move count at the moment of winning
  let saving = false;

  if (engine.error) {
    boardEl.textContent = "This level cannot be played: " + engine.error;
    undoBtn.disabled = true;
    restartBtn.disabled = true;
    return;
  }

  /* ---- rendering ---- */
  function fitCellSize() {
    const available = wrapEl.clientWidth - 32;
    const size = Math.floor(available / engine.cols);
    const cell = Math.max(22, Math.min(56, size));
    boardEl.style.setProperty("--cell", cell + "px");
  }

  function render() {
    const p = engine.getPlayer();
    const boxes = engine.getBoxes();
    boardEl.style.setProperty("--cols", engine.cols);
    boardEl.innerHTML = "";
    for (let r = 0; r < engine.rows; r++) {
      for (let c = 0; c < engine.cols; c++) {
        const cell = document.createElement("div");
        let cls = engine.isWall(r, c) ? "cell wall" : "cell floor";
        if (engine.isTarget(r, c)) {
          cls += " target";
        }
        if (engine.findBox(r, c) !== -1) {
          cls += " box";
        }
        if (p.row === r && p.col === c) {
          cls += " player";
        }
        cell.className = cls;
        boardEl.appendChild(cell);
      }
    }
    moveEl.textContent = engine.getMoves();
    bestEl.textContent = bestMoves === null ? "\u2014" : bestMoves;
    undoBtn.disabled = !engine.canUndo();
    boardEl.setAttribute(
      "aria-label",
      "Puzzle board, " +
        engine.getMoves() +
        " moves, " +
        boxes.length +
        " boxes",
    );
  }

  /* ---- one turn: input -> validate -> update -> render -> check win ---- */
  function handleMove(dr, dc) {
    const result = engine.tryMove(dr, dc);
    if (result === "blocked") {
      return;
    } // blocked moves change nothing and are not counted
    render();
    if (engine.isWon()) {
      winLevel();
    }
  }

  function handleUndo() {
    if (engine.undo()) {
      render();
    }
  }

  function handleRestart() {
    if (saving) {
      return;
    } // wait until the result of the finished game is saved
    engine.restart();
    overlay.classList.add("d-none");
    newBestEl.classList.add("d-none");
    retryBtn.classList.add("d-none");
    nextBtn.classList.add("d-none");
    render();
  }

  /* ---- winning and saving ---- */
  function winLevel() {
    finalMoves = engine.getMoves();
    winMovesEl.textContent = finalMoves;
    overlay.classList.remove("d-none");
    nextBtn.classList.add("d-none");
    retryBtn.classList.add("d-none");
    newBestEl.classList.add("d-none");

    if (!data.canSave) {
      winStatusEl.textContent = "Admin preview: nothing was saved.";
      if (data.nextLevelId) {
        nextBtn.classList.remove("d-none");
      }
      nextBtn.focus();
      return;
    }
    saveResult();
  }

  function showSaveFailure(message) {
    winStatusEl.textContent = message;
    retryBtn.classList.remove("d-none");
    retryBtn.focus();
  }

  function saveResult() {
    if (saving) {
      return;
    } // ignore double clicks while a request is running
    saving = true;
    retryBtn.classList.add("d-none");
    winStatusEl.textContent = "Saving...";

    fetch(data.saveUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": data.csrf,
      },
      // Only the level and the move count are sent. The server decides WHO the player is.
      body: JSON.stringify({ level_id: data.levelId, moves: finalMoves }),
    })
      .then(function (response) {
        return response.json().then(
          function (body) {
            return { ok: response.ok, body: body };
          },
          function () {
            return { ok: false, body: null };
          },
        );
      })
      .then(function (reply) {
        saving = false;
        if (reply.ok && reply.body && reply.body.success) {
          bestMoves = reply.body.best_moves;
          winStatusEl.textContent = reply.body.message;
          if (reply.body.is_new_best) {
            newBestEl.classList.remove("d-none");
          }
          if (reply.body.next_level_id) {
            nextBtn.classList.remove("d-none");
            nextBtn.focus();
          }
          bestEl.textContent = bestMoves;
        } else {
          showSaveFailure(
            reply.body && reply.body.message
              ? reply.body.message
              : "Could not save your progress.",
          );
        }
      })
      .catch(function () {
        saving = false;
        showSaveFailure("Network problem: your progress was not saved.");
      });
  }

  /* ---- input ---- */
  const KEY_DIRECTIONS = {
    ArrowUp: [-1, 0],
    w: [-1, 0],
    W: [-1, 0],
    ArrowDown: [1, 0],
    s: [1, 0],
    S: [1, 0],
    ArrowLeft: [0, -1],
    a: [0, -1],
    A: [0, -1],
    ArrowRight: [0, 1],
    d: [0, 1],
    D: [0, 1],
  };

  document.addEventListener("keydown", function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
      return;
    }
    const tag = event.target && event.target.tagName;
    if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") {
      return;
    }

    const dir = KEY_DIRECTIONS[event.key];
    if (dir) {
      event.preventDefault(); // arrow keys must not scroll the page
      handleMove(dir[0], dir[1]);
    } else if (event.key === "z" || event.key === "Z") {
      handleUndo();
    } else if (event.key === "r" || event.key === "R") {
      handleRestart();
    }
  });

  document.querySelectorAll("[data-dir]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const d = { up: [-1, 0], down: [1, 0], left: [0, -1], right: [0, 1] }[
        btn.getAttribute("data-dir")
      ];
      handleMove(d[0], d[1]);
    });
  });

  undoBtn.addEventListener("click", handleUndo);
  restartBtn.addEventListener("click", handleRestart);
  replayBtn.addEventListener("click", handleRestart);
  retryBtn.addEventListener("click", saveResult);
  nextBtn.addEventListener("click", function () {
    window.location.href =
      data.playUrl + "?id=" + encodeURIComponent(data.nextLevelId);
  });
  window.addEventListener("resize", fitCellSize);

  fitCellSize();
  render();
}

if (typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", function () {
    // game.js is also loaded by level_form.php (for the preview); only start a game when a level is present
    if (document.getElementById("level-data")) {
      startGame();
    }
  });
}
if (typeof module !== "undefined") {
  module.exports = { createEngine: createEngine };
}
