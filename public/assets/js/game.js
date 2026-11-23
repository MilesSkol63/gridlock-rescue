/**
 * assets/js/game.js  (REPLACED - was the Sokoban engine)  - rendering and input for the sliding-block game on play.php.
 * Every move is validated by GridlockEngine (assets/js/sliding-engine.js). The level comes from the database as JSON
 * (#level-data). When the rescue vehicle leaves the board, the accepted slides are sent to save_progress.php, which
 * replays them on the server before anything is stored.
 */
(function () {
  "use strict";

  var dataEl = document.getElementById("level-data");
  if (!dataEl || !window.GridlockEngine) {
    return;
  }
  var E = window.GridlockEngine;
  var data = JSON.parse(dataEl.textContent);

  var INSET = 4; // gap between a vehicle and its cell edge (px)
  var EXIT_W = 32; // must match .sl-frame in style.css
  var FRAME_W = 12;
  var COLORS = [
    "#4A90D9",
    "#F2B134",
    "#3DBE7A",
    "#8E6BD8",
    "#EE8A3C",
    "#2BB5C9",
    "#D96BA5",
    "#8FA3B8",
    "#6BAA3A",
    "#C9A227",
    "#5F6FD8",
    "#B5651D",
  ];

  var $ = function (id) {
    return document.getElementById(id);
  };
  var boardEl = $("game-board"),
    area = $("board-area"),
    moveEl = $("move-count"),
    undoBtn = $("btn-undo"),
    restartBtn = $("btn-restart"),
    overlay = $("win-overlay"),
    winStatus = $("win-status"),
    nextBtn = $("btn-next"),
    retryBtn = $("btn-retry"),
    replayBtn = $("btn-replay"),
    newBest = $("win-new-best");

  var game,
    els = {},
    cell = 72,
    selectedId = null,
    kbChain = null,
    drag = null,
    busy = false,
    saving = false,
    needsReload = false;
  var bestMoves = data.bestMoves; // null until this player completes the level (always null in admin preview)
  var finalMoves = 0,
    finalSlides = [];

  var pos = function (v) {
    return v.orient === "H" ? v.col : v.row;
  };

  try {
    game = E.createGame(data.rows);
  } catch (err) {
    boardEl.textContent = "This level cannot be played: " + err.message;
    undoBtn.disabled = true;
    restartBtn.disabled = true;
    return;
  }

  /* ---------- sizing ---------- */
  function computeCellSize(areaWidth) {
    var avail = Math.min(areaWidth, 640) - FRAME_W - EXIT_W - 4;
    return Math.max(32, Math.min(84, Math.floor(avail / 6)));
  }
  function fitBoard() {
    cell = computeCellSize(area.clientWidth);
    area.style.setProperty("--cell", cell + "px");
    placeAll(false);
  }

  /* ---------- board ---------- */
  function buildVehicles() {
    boardEl.innerHTML = "";
    els = {};
    var colorIndex = 0;
    game.getVehicles().forEach(function (v) {
      var b = document.createElement("button");
      b.type = "button";
      b.className =
        "sl-vehicle " +
        (v.orient === "H" ? "h" : "v") +
        " len" +
        v.length +
        (v.rescue ? " rescue" : "");
      if (!v.rescue) {
        b.style.setProperty("--body", COLORS[colorIndex++ % COLORS.length]);
      }
      b.dataset.id = v.id;
      [
        ["glass"],
        v.length === 3 ? ["glass2"] : null,
        ["lamp a"],
        ["lamp b"],
        v.rescue ? ["siren-bar"] : null,
      ].forEach(function (p) {
        if (p) {
          var s = document.createElement("span");
          s.className = p[0];
          b.appendChild(s);
        }
      });
      attachInput(b, v.id);
      boardEl.appendChild(b);
      els[v.id] = b;
    });
    placeAll(false);
  }

  function setTransform(el, v, p) {
    var x = (v.orient === "H" ? p : v.col) * cell + INSET;
    var y = (v.orient === "H" ? v.row : p) * cell + INSET;
    el.style.width =
      (v.orient === "H" ? v.length * cell : cell) - 2 * INSET + "px";
    el.style.height =
      (v.orient === "H" ? cell : v.length * cell) - 2 * INSET + "px";
    el.style.transform = "translate(" + x + "px," + y + "px)";
  }

  function placeAll(animate) {
    game.getVehicles().forEach(function (v) {
      var el = els[v.id];
      if (!el) {
        return;
      }
      if (!animate) {
        el.style.transition = "none";
      }
      setTransform(el, v, pos(v));
      if (!animate) {
        void el.offsetWidth;
        el.style.transition = "";
      }
      el.setAttribute(
        "aria-label",
        (v.rescue ? "Red rescue vehicle" : "Vehicle " + v.id) +
          ", " +
          (v.orient === "H" ? "moves left and right" : "moves up and down") +
          ", row " +
          (v.row + 1) +
          ", column " +
          (v.col + 1),
      );
    });
  }

  function updateHud() {
    moveEl.textContent = game.getMoves();
    undoBtn.disabled = !game.canUndo() || busy;
  }

  function select(id) {
    if (selectedId !== id) {
      kbChain = null;
    }
    selectedId = id;
    Object.keys(els).forEach(function (k) {
      els[k].classList.toggle("selected", k === id);
    });
  }
  function shake(id) {
    var el = els[id];
    if (!el) {
      return;
    }
    el.classList.remove("blocked");
    void el.offsetWidth;
    el.classList.add("blocked");
  }

  /* ---------- moves (always validated by the engine) ---------- */
  function doSlide(id, target, merge) {
    var res = game.slide(id, target, { merge: merge });
    if (!res.ok) {
      if (res.reason === "blocked") {
        shake(id);
      }
      placeAll(true);
      return res;
    }
    placeAll(true);
    updateHud();
    if (res.exited) {
      handleWin();
    }
    return res;
  }

  function attachInput(el, id) {
    el.addEventListener("focus", function () {
      select(id);
    });
    el.addEventListener("pointerdown", function (e) {
      if (game.isWon() || busy || e.button > 0) {
        return;
      }
      var v = game.getVehicle(id);
      el.setPointerCapture(e.pointerId);
      drag = {
        id: id,
        v: v,
        x: e.clientX,
        y: e.clientY,
        start: pos(v),
        range: game.range(id),
        f: pos(v),
        moved: false,
      };
      kbChain = null;
      select(id);
      el.classList.add("dragging");
    });
    el.addEventListener("pointermove", function (e) {
      if (!drag || drag.id !== id) {
        return;
      }
      var delta =
        drag.v.orient === "H" ? e.clientX - drag.x : e.clientY - drag.y;
      if (Math.abs(delta) > 4) {
        drag.moved = true;
      }
      drag.f = Math.max(
        drag.range.min,
        Math.min(drag.range.max, drag.start + delta / cell),
      );
      setTransform(el, drag.v, drag.f);
    });
    var finish = function (e) {
      if (!drag || drag.id !== id) {
        return;
      }
      var d = drag;
      drag = null;
      el.classList.remove("dragging");
      if (e.type === "pointercancel" || !d.moved) {
        placeAll(true);
        return;
      }
      var target = Math.round(d.f);
      if (target === d.start) {
        placeAll(true);
        return;
      }
      doSlide(id, target, false); // the engine re-checks the whole slide
    };
    el.addEventListener("pointerup", finish);
    el.addEventListener("pointercancel", finish);
  }

  document.addEventListener("keydown", function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey) {
      return;
    }
    var tag = e.target && e.target.tagName;
    if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") {
      return;
    }
    var k = e.key;
    if (k === "z" || k === "Z") {
      doUndo();
      return;
    }
    if (k === "r" || k === "R") {
      doRestart();
      return;
    }
    var dir = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[k];
    if (!dir || !selectedId || game.isWon() || busy) {
      return;
    }
    var v = game.getVehicle(selectedId);
    var horizontalKey = k === "ArrowLeft" || k === "ArrowRight";
    if ((v.orient === "H") !== horizontalKey) {
      e.preventDefault();
      shake(selectedId);
      return;
    } // wrong axis
    e.preventDefault();
    var range = game.range(selectedId),
      cur = pos(v);
    var target = e.shiftKey ? (dir > 0 ? range.max : range.min) : cur + dir;
    var merge =
      !e.shiftKey &&
      kbChain &&
      kbChain.id === selectedId &&
      kbChain.dir === dir;
    var res = doSlide(selectedId, target, merge);
    kbChain = res.ok && !e.shiftKey ? { id: selectedId, dir: dir } : null;
  });

  function doUndo() {
    if (busy || !game.undo()) {
      return;
    }
    kbChain = null;
    placeAll(true);
    updateHud();
  }
  function doRestart() {
    if (needsReload) {
      window.location.reload();
      return;
    }
    if (busy || saving) {
      return;
    }
    game.restart();
    kbChain = null;
    overlay.classList.add("d-none");
    newBest.classList.add("d-none");
    retryBtn.classList.add("d-none");
    retryBtn.textContent = "Retry Save";
    needsReload = false;
    nextBtn.classList.add("d-none");
    Object.keys(els).forEach(function (k) {
      els[k].classList.remove("exiting");
    });
    placeAll(false);
    updateHud();
    select("R");
  }

  /* ---------- victory and saving ---------- */
  function ratingText(moves) {
    if (moves <= data.par) {
      return "Perfect rescue - optimal solution!";
    }
    if (moves <= data.par + 3) {
      return "Great rescue! Almost optimal.";
    }
    return "Rescued! Can you do it in fewer moves?";
  }

  function handleWin() {
    busy = true;
    finalMoves = game.getMoves();
    finalSlides = game.getHistory(); // undo and restart are already applied
    els.R.classList.add("exiting");
    updateHud();
    window.setTimeout(function () {
      $("win-moves").textContent = finalMoves;
      $("win-rating").textContent = ratingText(finalMoves);
      overlay.classList.remove("d-none");
      nextBtn.classList.add("d-none");
      retryBtn.classList.add("d-none");
      newBest.classList.add("d-none");
      busy = false;
      if (!data.canSave) {
        winStatus.textContent = "Admin preview: nothing was saved.";
        if (data.nextLevelId) {
          nextBtn.classList.remove("d-none");
          nextBtn.focus();
        }
        return;
      }
      saveResult();
    }, 750);
  }

  function showSaveFailure(message) {
    winStatus.textContent = message;
    retryBtn.classList.remove("d-none");
    retryBtn.focus();
  }

  function saveResult() {
    if (saving) {
      return;
    }
    saving = true;
    retryBtn.classList.add("d-none");
    winStatus.textContent = "Saving...";
    fetch(data.saveUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": data.csrf,
      },
      // The layout hash prevents an already-open game from saving against a board
      // that an administrator changed after the player loaded this page.
      // The server still replays every accepted slide and decides who the player is.
      body: JSON.stringify({
        level_id: data.levelId,
        layout_hash: data.layoutHash,
        moves: finalSlides,
      }),
    })
      .then(function (response) {
        return response.json().then(
          function (body) {
            return { ok: response.ok, status: response.status, body: body };
          },
          function () {
            return { ok: false, status: response.status, body: null };
          },
        );
      })
      .then(function (reply) {
        saving = false;
        if (reply.ok && reply.body && reply.body.success) {
          bestMoves = reply.body.best_moves;
          $("your-best").textContent = bestMoves;
          winStatus.textContent = reply.body.message;
          if (reply.body.is_new_best) {
            newBest.classList.remove("d-none");
          }
          if (reply.body.next_level_id) {
            nextBtn.classList.remove("d-none");
            nextBtn.focus();
          }
        } else if (reply.status === 409) {
          needsReload = true;
          winStatus.textContent =
            reply.body && reply.body.message
              ? reply.body.message
              : "This level has changed. Reload it before playing again.";
          retryBtn.textContent = "Reload Level";
          retryBtn.classList.remove("d-none");
          retryBtn.focus();
        } else {
          retryBtn.textContent = "Retry Save";
          showSaveFailure(
            reply.body && reply.body.message
              ? reply.body.message
              : "Could not save your progress.",
          );
        }
      })
      .catch(function () {
        saving = false;
        retryBtn.textContent = "Retry Save";
        showSaveFailure("Network problem: your progress was not saved.");
      });
  }

  undoBtn.addEventListener("click", doUndo);
  restartBtn.addEventListener("click", doRestart);
  replayBtn.addEventListener("click", doRestart);
  retryBtn.addEventListener("click", function () {
    if (needsReload) {
      window.location.reload();
      return;
    }
    saveResult();
  });
  nextBtn.addEventListener("click", function () {
    window.location.href =
      data.playUrl + "?id=" + encodeURIComponent(data.nextLevelId);
  });
  window.addEventListener("resize", fitBoard);

  window.GridlockGame = {
    computeCellSize: computeCellSize,
    EXIT_W: EXIT_W,
    FRAME_W: FRAME_W,
  };
  $("best-known").textContent = data.par;
  $("your-best").innerHTML = bestMoves === null ? "&mdash;" : bestMoves;
  buildVehicles();
  fitBoard();
  updateHud();
  select("R");
})();
