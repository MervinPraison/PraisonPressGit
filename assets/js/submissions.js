jQuery(document).ready(function($) {
    
    // Handle Approve & Merge button click
    $('.approve-pr').on('click', function() {
        const $btn = $(this);
        const prNumber = $btn.data('pr-number');
        const prTitle = $btn.data('pr-title');
        
        // Show confirmation modal
        showConfirmModal(
            'Approve & Merge Pull Request',
            `Are you sure you want to approve and merge PR #${prNumber}: "${prTitle}"?`,
            function() {
                // User confirmed, proceed with merge
                mergePR(prNumber, $btn);
            }
        );
    });
    
    // Merge PR via AJAX
    function mergePR(prNumber, $btn) {
        // Disable button and show loading
        $btn.prop('disabled', true);
        $btn.html('<span class="dashicons dashicons-update dashicons-spin"></span> Merging...');
        
        $.ajax({
            url: praisonSubmissions.ajax_url,
            type: 'POST',
            data: {
                action: 'praison_merge_pr_frontend',
                nonce: praisonSubmissions.nonce,
                pr_number: prNumber
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showSuccessModal(
                        'Pull Request Merged!',
                        response.data.message || 'The pull request has been successfully merged and content has been synced.',
                        function() {
                            // Reload page to show updated status
                            window.location.reload();
                        }
                    );
                } else {
                    // Show error
                    showErrorModal(
                        'Merge Failed',
                        response.data.message || 'Failed to merge the pull request. Please try again.'
                    );
                    // Re-enable button
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-yes-alt"></span> Approve & Merge');
                }
            },
            error: function() {
                showErrorModal(
                    'Error',
                    'An error occurred while merging the pull request. Please try again.'
                );
                // Re-enable button
                $btn.prop('disabled', false);
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> Approve & Merge');
            }
        });
    }
    
    // Show confirmation modal
    function showConfirmModal(title, message, onConfirm) {
        const modal = $(`
            <div class="praison-modal-overlay">
                <div class="praison-modal confirm-modal">
                    <div class="praison-modal-header">
                        <h2>${title}</h2>
                        <button class="praison-modal-close">&times;</button>
                    </div>
                    <div class="praison-modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="praison-modal-footer">
                        <button class="button button-secondary praison-modal-cancel">Cancel</button>
                        <button class="button button-primary praison-modal-confirm">Approve & Merge</button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.fadeIn(200);
        
        // Handle confirm
        modal.find('.praison-modal-confirm').on('click', function() {
            modal.fadeOut(200, function() {
                modal.remove();
            });
            onConfirm();
        });
        
        // Handle cancel/close
        modal.find('.praison-modal-cancel, .praison-modal-close, .praison-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                modal.fadeOut(200, function() {
                    modal.remove();
                });
            }
        });
        
        // ESC key to close
        $(document).on('keyup.praisonModal', function(e) {
            if (e.key === 'Escape') {
                modal.fadeOut(200, function() {
                    modal.remove();
                });
                $(document).off('keyup.praisonModal');
            }
        });
    }
    
    // Show success modal
    function showSuccessModal(title, message, onClose) {
        const modal = $(`
            <div class="praison-modal-overlay">
                <div class="praison-modal success-modal">
                    <div class="praison-modal-header success-header">
                        <h2>${title}</h2>
                        <button class="praison-modal-close">&times;</button>
                    </div>
                    <div class="praison-modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="praison-modal-footer">
                        <button class="button button-primary praison-modal-ok">OK</button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.fadeIn(200);
        
        // Handle OK
        modal.find('.praison-modal-ok, .praison-modal-close').on('click', function() {
            modal.fadeOut(200, function() {
                modal.remove();
            });
            if (onClose) onClose();
        });
    }
    
    // Show error modal
    function showErrorModal(title, message) {
        const modal = $(`
            <div class="praison-modal-overlay">
                <div class="praison-modal error-modal">
                    <div class="praison-modal-header error-header">
                        <h2>${title}</h2>
                        <button class="praison-modal-close">&times;</button>
                    </div>
                    <div class="praison-modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="praison-modal-footer">
                        <button class="button button-primary praison-modal-ok">OK</button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.fadeIn(200);
        
        // Handle OK
        modal.find('.praison-modal-ok, .praison-modal-close').on('click', function() {
            modal.fadeOut(200, function() {
                modal.remove();
            });
        });
    }
});
