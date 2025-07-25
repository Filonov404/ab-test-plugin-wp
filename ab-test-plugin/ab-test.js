/*
 * Front‑end script for the Simple A/B Test Block plugin.
 *
 * Attaches a click handler to the test block's button. When clicked, an AJAX
 * request is sent to WordPress to increment the click count for the selected
 * variant. A nonce is validated server side to avoid CSRF attacks.
 */
jQuery(document).ready(function ($) {
    // Delegate click handler to ensure it works even if multiple blocks are present.
    $(document).on('click', '.ab-test-button', function (event) {
        event.preventDefault();
        var $button = $(this);
        var variant = $button.data('variant');
        var nonce = $button.data('nonce');
        // Send the AJAX request to record the click.
        $.post(abTestData.ajax_url, {
            action: 'ab_test_click',
            variant: variant,
            _ajax_nonce: nonce
        }).done(function (response) {
            // Optionally, you could provide feedback to the user or trigger other behavior here.
        });
    });
});