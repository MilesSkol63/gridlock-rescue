/**
 * assets/js/app.js  (MODIFIED)  - small helper shared by the management pages.
 * Fills the shared confirmation dialog (includes/confirm_modal.php) from a button's data-* attributes.
 * (The old Sokoban layout preview was removed; the level editor now lives in assets/js/level-editor.js.)
 */
document.addEventListener("DOMContentLoaded", function () {
  "use strict";

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
      var submit = document.getElementById("confirm-submit");
      submit.textContent = button.getAttribute("data-button") || "Confirm";
      submit.className =
        "btn " + (button.getAttribute("data-button-class") || "btn-danger");
    });
  }
});
