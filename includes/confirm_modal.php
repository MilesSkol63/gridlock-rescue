<?php
/**
 * includes/confirm_modal.php  (NEW)  - ONE reusable Bootstrap confirmation dialog.
 * A page includes it once; every destructive button on the page opens it with data attributes
 * (data-title, data-message, data-action, data-id, data-button) and assets/js/app.js fills it in.
 * The dialog posts to the CURRENT page with the action, the id and the CSRF token.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
$confirm_action_url = $confirm_action_url ?? basename($_SERVER['SCRIPT_NAME']);
?>
    <div class="modal fade" id="confirm-modal" tabindex="-1" aria-labelledby="confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="<?php echo e($confirm_action_url); ?>" class="modal-content" id="confirm-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="confirm-action" value="">
                <input type="hidden" name="id" id="confirm-id" value="">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="confirm-title">Please confirm</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirm-message"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-navy" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirm-submit">Confirm</button>
                </div>
            </form>
        </div>
    </div>
