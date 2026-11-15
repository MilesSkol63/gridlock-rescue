/**
 * assets/js/level-editor.js  (NEW)  - visual 6x6 level editor used by level_form.php.
 * Part 1 (EditorCore) is pure logic (testable in Node). Part 2 wires it to the page.
 * The editor keeps a list of vehicles and writes them as the SAME 6-row text the game and the PHP validator read
 * into the hidden field #layout. The server never trusts the editor: it validates the text again (includes/sliding.php).
 */
var EditorCore = (function () {
  "use strict";

  var BOARD = 6,
    EXIT_ROW = 2;
  var LETTERS = "ABCDEFGHIJKLMNOPQSTUVWXYZ".split(""); // every capital letter except R (the rescue vehicle)

  function engine() {
    return (typeof window !== "undefined" ? window : globalThis).GridlockEngine;
  }

  function cells(v) {
    var out = [];
    for (var i = 0; i < v.length; i++) {
      out.push(v.orient === "H" ? [v.row, v.col + i] : [v.row + i, v.col]);
    }
    return out;
  }
  function inBoard(v) {
    return cells(v).every(function (c) {
      return c[0] >= 0 && c[0] < BOARD && c[1] >= 0 && c[1] < BOARD;
    });
  }
  function overlaps(vehicles, v, ignoreId) {
    var taken = {};
    vehicles.forEach(function (o) {
      if (o.id !== ignoreId) {
        cells(o).forEach(function (c) {
          taken[c[0] + "," + c[1]] = true;
        });
      }
    });
    return cells(v).some(function (c) {
      return taken[c[0] + "," + c[1]];
    });
  }
  function canPlace(vehicles, v, ignoreId) {
    if (v.rescue && (v.orient !== "H" || v.row !== EXIT_ROW)) {
      return false;
    }
    return inBoard(v) && !overlaps(vehicles, v, ignoreId);
  }

  function defaultVehicles() {
    return [
      { id: "R", orient: "H", length: 2, row: EXIT_ROW, col: 0, rescue: true },
    ];
  }

  /** Rows (array of 6 strings) -> editable vehicles. Throws if the rows are not a valid board. */
  function fromRows(rows) {
    return engine()
      .parseLevel(rows)
      .map(function (v) {
        return {
          id: v.id,
          orient: v.orient,
          length: v.length,
          row: v.row,
          col: v.col,
          rescue: v.rescue,
        };
      });
  }
  /** Editable vehicles -> 6 row strings. */
  function toRows(vehicles) {
    var grid = [];
    for (var r = 0; r < BOARD; r++) {
      grid.push(new Array(BOARD + 1).join(".").split(""));
    }
    vehicles.forEach(function (v) {
      cells(v).forEach(function (c) {
        grid[c[0]][c[1]] = v.id;
      });
    });
    return grid.map(function (row) {
      return row.join("");
    });
  }

  function nextId(vehicles) {
    var used = {};
    vehicles.forEach(function (v) {
      used[v.id] = true;
    });
    for (var i = 0; i < LETTERS.length; i++) {
      if (!used[LETTERS[i]]) {
        return LETTERS[i];
      }
    }
    return null;
  }

  /** Add a vehicle that covers the clicked cell. Returns {vehicles, id} or {error}. */
  function addVehicle(vehicles, orient, length, row, col) {
    var id = nextId(vehicles);
    if (!id) {
      return { error: "All 25 vehicle letters are already used." };
    }
    for (var off = 0; off < length; off++) {
      var v = {
        id: id,
        orient: orient,
        length: length,
        row: orient === "H" ? row : row - off,
        col: orient === "H" ? col - off : col,
        rescue: false,
      };
      if (canPlace(vehicles, v, null)) {
        return { vehicles: vehicles.concat([v]), id: id };
      }
    }
    return {
      error:
        "There is no room for a " +
        (orient === "H" ? "horizontal" : "vertical") +
        " vehicle of length " +
        length +
        " there.",
    };
  }

  /** Change properties of one vehicle (row, col, length, orient). Returns {vehicles} or {error}. */
  function updateVehicle(vehicles, id, changes) {
    var current = null;
    vehicles.forEach(function (v) {
      if (v.id === id) {
        current = v;
      }
    });
    if (!current) {
      return { error: "No vehicle selected." };
    }
    var candidate = {};
    Object.keys(current).forEach(function (k) {
      candidate[k] = current[k];
    });
    Object.keys(changes).forEach(function (k) {
      candidate[k] = changes[k];
    });
    if (current.rescue && changes.orient && changes.orient !== "H") {
      return { error: "The rescue vehicle must stay horizontal." };
    }
    if (!canPlace(vehicles, candidate, id)) {
      return {
        error:
          "That change does not fit (it would leave the board or overlap another vehicle).",
      };
    }
    return {
      vehicles: vehicles.map(function (v) {
        return v.id === id ? candidate : v;
      }),
    };
  }
  function moveVehicle(vehicles, id, dRow, dCol) {
    var cur = vehicles.filter(function (v) {
      return v.id === id;
    })[0];
    if (!cur) {
      return { error: "No vehicle selected." };
    }
    if (cur.rescue && dRow !== 0) {
      return { error: "The rescue vehicle must stay in the exit row (row 3)." };
    }
    return updateVehicle(vehicles, id, {
      row: cur.row + dRow,
      col: cur.col + dCol,
    });
  }
  function rotateVehicle(vehicles, id) {
    var cur = vehicles.filter(function (v) {
      return v.id === id;
    })[0];
    if (!cur) {
      return { error: "No vehicle selected." };
    }
    if (cur.rescue) {
      return { error: "The rescue vehicle must stay horizontal." };
    }
    return updateVehicle(vehicles, id, {
      orient: cur.orient === "H" ? "V" : "H",
    });
  }
  function removeVehicle(vehicles, id) {
    var cur = vehicles.filter(function (v) {
      return v.id === id;
    })[0];
    if (!cur) {
      return { error: "No vehicle selected." };
    }
    if (cur.rescue) {
      return { error: "The rescue vehicle cannot be removed." };
    }
    return {
      vehicles: vehicles.filter(function (v) {
        return v.id !== id;
      }),
    };
  }

  return {
    BOARD: BOARD,
    cells: cells,
    canPlace: canPlace,
    defaultVehicles: defaultVehicles,
    fromRows: fromRows,
    toRows: toRows,
    nextId: nextId,
    addVehicle: addVehicle,
    updateVehicle: updateVehicle,
    moveVehicle: moveVehicle,
    rotateVehicle: rotateVehicle,
    removeVehicle: removeVehicle,
  };
})();

/* ============================ PART 2: PAGE WIRING ============================ */
(function () {
  "use strict";
  if (typeof document === "undefined") {
    return;
  }

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
  var INSET = 3;

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.getElementById("level-editor");
    if (!root || !window.GridlockEngine) {
      return;
    }
    var $ = function (id) {
      return document.getElementById(id);
    };
    var board = $("editor-board"),
      layoutInput = $("layout"),
      statusEl = $("editor-status"),
      checkEl = $("check-result"),
      parInput = $("par_moves");

    var vehicles,
      selectedId = null,
      orient = "H",
      length = 2,
      cell = 52,
      timer = null;

    try {
      vehicles = EditorCore.fromRows(
        layoutInput.value.replace(/\r\n?/g, "\n").trim().split("\n"),
      );
    } catch (err) {
      vehicles = EditorCore.defaultVehicles();
      statusEl.textContent =
        "The saved layout could not be read (" +
        err.message +
        "). Starting from an empty board.";
    }

    function say(msg) {
      statusEl.textContent = msg || "";
    }

    function fit() {
      cell = Math.max(
        34,
        Math.min(56, Math.floor((root.clientWidth - 24) / 6)),
      );
      root.style.setProperty("--cell", cell + "px");
    }

    function render() {
      board.innerHTML = "";
      var colorIndex = 0;
      vehicles
        .slice()
        .sort(function (a, b) {
          return a.id < b.id ? -1 : 1;
        })
        .forEach(function (v) {
          var b = document.createElement("button");
          b.type = "button";
          b.className =
            "sl-vehicle sl-static " +
            (v.orient === "H" ? "h" : "v") +
            " len" +
            v.length +
            (v.rescue ? " rescue" : "") +
            (v.id === selectedId ? " selected" : "");
          if (!v.rescue) {
            b.style.setProperty("--body", COLORS[colorIndex++ % COLORS.length]);
          }
          b.style.width =
            (v.orient === "H" ? v.length * cell : cell) - 2 * INSET + "px";
          b.style.height =
            (v.orient === "H" ? cell : v.length * cell) - 2 * INSET + "px";
          b.style.transform =
            "translate(" +
            (v.col * cell + INSET) +
            "px," +
            (v.row * cell + INSET) +
            "px)";
          b.setAttribute(
            "aria-label",
            (v.rescue ? "Rescue vehicle R" : "Vehicle " + v.id) +
              ", " +
              (v.orient === "H" ? "horizontal" : "vertical") +
              ", length " +
              v.length +
              ", row " +
              (v.row + 1) +
              ", column " +
              (v.col + 1),
          );
          var label = document.createElement("span");
          label.className = "sl-label";
          label.textContent = v.id;
          b.appendChild(label);
          b.addEventListener("click", function (e) {
            e.stopPropagation();
            selectedId = v.id;
            say("");
            render();
          });
          board.appendChild(b);
        });
      layoutInput.value = EditorCore.toRows(vehicles).join("\n");

      var sel = vehicles.filter(function (v) {
        return v.id === selectedId;
      })[0];
      $("sel-info").textContent = sel
        ? (sel.rescue ? "Rescue vehicle R" : "Vehicle " + sel.id) +
          " - " +
          (sel.orient === "H" ? "horizontal" : "vertical") +
          ", length " +
          sel.length
        : "Click a vehicle to select it, or click an empty square to add one.";
      [
        "sel-left",
        "sel-right",
        "sel-up",
        "sel-down",
        "sel-rotate",
        "sel-len2",
        "sel-len3",
        "sel-delete",
      ].forEach(function (id) {
        $(id).disabled = !sel;
      });
      $("tool-h").setAttribute("aria-pressed", String(orient === "H"));
      $("tool-v").setAttribute("aria-pressed", String(orient === "V"));
      $("tool-len2").setAttribute("aria-pressed", String(length === 2));
      $("tool-len3").setAttribute("aria-pressed", String(length === 3));
      scheduleCheck();
    }

    function apply(result) {
      if (result.error) {
        say(result.error);
        return false;
      }
      vehicles = result.vehicles;
      if (result.id) {
        selectedId = result.id;
      }
      say("");
      render();
      return true;
    }

    // click on an empty square: add a vehicle with the chosen orientation and length
    board.addEventListener("click", function (e) {
      var rect = board.getBoundingClientRect();
      var col = Math.floor((e.clientX - rect.left) / cell),
        row = Math.floor((e.clientY - rect.top) / cell);
      if (row < 0 || row > 5 || col < 0 || col > 5) {
        return;
      }
      apply(EditorCore.addVehicle(vehicles, orient, length, row, col));
    });

    $("tool-h").addEventListener("click", function () {
      orient = "H";
      render();
    });
    $("tool-v").addEventListener("click", function () {
      orient = "V";
      render();
    });
    $("tool-len2").addEventListener("click", function () {
      length = 2;
      render();
    });
    $("tool-len3").addEventListener("click", function () {
      length = 3;
      render();
    });
    $("sel-left").addEventListener("click", function () {
      apply(EditorCore.moveVehicle(vehicles, selectedId, 0, -1));
    });
    $("sel-right").addEventListener("click", function () {
      apply(EditorCore.moveVehicle(vehicles, selectedId, 0, 1));
    });
    $("sel-up").addEventListener("click", function () {
      apply(EditorCore.moveVehicle(vehicles, selectedId, -1, 0));
    });
    $("sel-down").addEventListener("click", function () {
      apply(EditorCore.moveVehicle(vehicles, selectedId, 1, 0));
    });
    $("sel-rotate").addEventListener("click", function () {
      apply(EditorCore.rotateVehicle(vehicles, selectedId));
    });
    $("sel-len2").addEventListener("click", function () {
      apply(EditorCore.updateVehicle(vehicles, selectedId, { length: 2 }));
    });
    $("sel-len3").addEventListener("click", function () {
      apply(EditorCore.updateVehicle(vehicles, selectedId, { length: 3 }));
    });
    $("sel-delete").addEventListener("click", function () {
      var r = EditorCore.removeVehicle(vehicles, selectedId);
      if (!r.error) {
        selectedId = null;
      }
      apply(r);
    });
    $("btn-clear").addEventListener("click", function () {
      vehicles = EditorCore.defaultVehicles();
      selectedId = null;
      say("Board cleared.");
      render();
    });
    $("btn-check").addEventListener("click", function () {
      runCheck(true);
    });
    window.addEventListener("resize", function () {
      fit();
      render();
    });

    // arrow keys move the selected vehicle one square while the editor has focus
    root.addEventListener("keydown", function (e) {
      var d = {
        ArrowLeft: [0, -1],
        ArrowRight: [0, 1],
        ArrowUp: [-1, 0],
        ArrowDown: [1, 0],
      }[e.key];
      if (
        !d ||
        !selectedId ||
        e.target.tagName === "INPUT" ||
        e.target.tagName === "TEXTAREA" ||
        e.target.tagName === "SELECT"
      ) {
        return;
      }
      e.preventDefault();
      apply(EditorCore.moveVehicle(vehicles, selectedId, d[0], d[1]));
    });

    /* ---- quick solvability check in the browser (the server repeats it with the PHP solver) ---- */
    function scheduleCheck() {
      window.clearTimeout(timer);
      checkEl.className = "editor-check pending";
      checkEl.textContent = "Checking the puzzle...";
      timer = window.setTimeout(function () {
        runCheck(false);
      }, 500);
    }
    function runCheck(manual) {
      var rows = EditorCore.toRows(vehicles),
        sol = null;
      try {
        sol = GridlockEngine.solve(rows, 200000);
      } catch (err) {
        sol = null;
      }
      if (sol) {
        checkEl.className = "editor-check ok";
        checkEl.textContent =
          "Solvable. Optimal solution: " + sol.moves.length + " moves.";
        if (parInput && (parInput.value === "" || manual)) {
          parInput.value = String(sol.moves.length);
        }
      } else {
        checkEl.className = "editor-check bad";
        checkEl.textContent =
          "Not solvable (or too complex to check): the rescue vehicle cannot leave the board. This level cannot be saved.";
      }
    }

    fit();
    render();
  });
})();

if (typeof module !== "undefined" && module.exports) {
  module.exports = EditorCore;
}
