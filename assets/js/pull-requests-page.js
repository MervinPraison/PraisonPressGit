jQuery(document).ready(function($) {
    var confirmCallback = null;
    
    // Show modal
    function showModal(title, message, callback) {
        $('#praisonpress-modal-title').text(title);
        $('#praisonpress-modal-message').text(message);
        confirmCallback = callback;
        $('#praisonpress-confirm-modal').fadeIn(200);
    }
    
    // Hide modal
    function hideModal() {
        $('#praisonpress-confirm-modal').fadeOut(200);
        confirmCallback = null;
    }
    
    // Merge PR button
    $('.praisonpress-merge-pr-btn').on('click', function() {
        var $btn = $(this);
        var prNumber = $btn.data('pr-number');
        var prTitle = $btn.data('pr-title');
        var mergeUrl = $btn.data('merge-url');
        
        showModal(
            'Merge Pull Request',
            'Are you sure you want to merge PR #' + prNumber + ': "' + prTitle + '"? This will merge the changes into the main branch and sync the content.',
            function() {
                window.location.href = mergeUrl;
            }
        );
    });
    
    // Close PR button
    $('.praisonpress-close-pr-btn').on('click', function() {
        var $btn = $(this);
        var prNumber = $btn.data('pr-number');
        var prTitle = $btn.data('pr-title');
        var closeUrl = $btn.data('close-url');
        
        showModal(
            'Close Pull Request',
            'Are you sure you want to close PR #' + prNumber + ': "' + prTitle + '"? This will reject the changes without merging.',
            function() {
                window.location.href = closeUrl;
            }
        );
    });
    
    // Confirm button
    $('.praisonpress-modal-confirm').on('click', function() {
        if (confirmCallback) {
            confirmCallback();
        }
        hideModal();
    });
    
    // Cancel button
    $('.praisonpress-modal-cancel, .praisonpress-modal-close').on('click', function() {
        hideModal();
    });
    
    // Close on overlay click
    $('.praisonpress-modal-overlay').on('click', function() {
        hideModal();
    });
    
    // Close on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#praisonpress-confirm-modal').is(':visible')) {
            hideModal();
        }
    });
});
