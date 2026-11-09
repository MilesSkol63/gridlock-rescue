/**
 * assets/js/app.js  (NEW)  - small helpers shared by the management pages.
 *  1. Fills the shared confirmation dialog (includes/confirm_modal.php) from a button's data-* attributes.
 *  2. Draws the live layout preview on level_form.php (uses createEngine() from game.js).
 */
document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  /* ---- 1. confirmation dialog ---- */
  var modal = document.getElementById("confirm-modal");
  if (modal) {
    modal.addEventListener("show.bs.modal", function (event) {
      var button = event.relatedTarget;
      if (!button) {
        return;
      }
      document.getElementById("confirm-title").textContent =
        button.getAttribute("data-title") || "Please confirm";
      document.getElementById("confirm-message").textContent =
        button.getAttribute("data-message") || "";
      document.getElementById("confirm-action").value =
        button.getAttribute("data-action") || "";
      document.getElementById("confirm-id").value =
        button.getAttribute("data-id") || "";
      document.getElementById("confirm-submit").textContent =
        button.getAttribute("data-button") || "Confirm";
    });
  }

  /* ---- 2. level layout preview (read only; the server validates again when saving) ---- */
  var area = document.getElementById("layout");
  var preview = document.getElementById("layout-preview");
  var note = document.getElementById("preview-note");
  if (area && preview && typeof createEngine === "function") {
    var drawPreview = function () {
      var engine = createEngine(area.value);
      preview.innerHTML = "";
      if (engine.error) {
        note.textContent = "Preview: " + engine.error;
        return;
      }
      note.textContent =
        engine.rows + " x " + engine.cols + " grid, preview only";
      var cell = Math.max(14, Math.min(32, Math.floor(300 / engine.cols)));
      preview.style.setProperty("--cols", engine.cols);
      preview.style.setProperty("--cell", cell + "px");
      var p = engine.getPlayer();
      for (var r = 0; r < engine.rows; r++) {
        for (var c = 0; c < engine.cols; c++) {
          var div = document.createElement("div");
          var cls = engine.isWall(r, c) ? "cell wall" : "cell floor";
          if (engine.isTarget(r, c)) {
            cls += " target";
          }
          if (engine.findBox(r, c) !== -1) {
            cls += " box";
          }
          if (p.row === r && p.col === c) {
            cls += " player";
          }
          div.className = cls;
          preview.appendChild(div);
        }
      }
    };
    area.addEventListener("input", drawPreview);
    drawPreview();
  }
});
