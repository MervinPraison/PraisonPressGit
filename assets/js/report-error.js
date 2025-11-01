/**
 * Report Error Button JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $button = $('#praisonpress-report-error-button button');
        const $modal = $('#praisonpress-edit-modal');
        const $editor = $('#praisonpress-content-editor');
        const $description = $('#praisonpress-edit-description');
        const $submitBtn = $('#praisonpress-submit-edit');
        const $loading = $('#praisonpress-loading');
        
        // Open modal on button click
        $button.on('click', function() {
            openModal();
        });
        
        // Close modal
        $('.praisonpress-modal-close').on('click', function() {
            closeModal();
        });
        
        // Close on overlay click
        $('.praisonpress-modal-overlay').on('click', function() {
            closeModal();
        });
        
        // Close on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closeModal();
            }
        });
        
        // Submit edit
        $submitBtn.on('click', function() {
            submitEdit();
        });
        
        /**
         * Open modal and load content
         */
        function openModal() {
            $loading.show();
            
            // Load content via AJAX
            $.ajax({
                url: praisonpressData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'praisonpress_get_content',
                    nonce: praisonpressData.nonce,
                    post_id: praisonpressData.postId
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        $editor.val(response.data.content);
                        $modal.fadeIn(200);
                        $editor.focus();
                    } else {
                        alert('Error loading content: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $loading.hide();
                    alert('Error loading content. Please try again.');
                }
            });
        }
        
        /**
         * Close modal
         */
        function closeModal() {
            $modal.fadeOut(200);
            $editor.val('');
            $description.val('');
        }
        
        /**
         * Submit edit
         */
        function submitEdit() {
            const content = $editor.val();
            const description = $description.val();
            
            if (!content.trim()) {
                alert('Content cannot be empty.');
                return;
            }
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Submitting...');
            
            // TODO: Phase 5 - Create pull request with the edited content
            // For now, just show a success message
            
            // Simulate API call
            setTimeout(function() {
                alert('Thank you for your contribution! Your edit will be reviewed by an admin.');
                closeModal();
                $submitBtn.prop('disabled', false).text('Submit Edit');
            }, 1000);
            
            // In Phase 5, we'll implement:
            // 1. Create a new branch
            // 2. Commit the changes
            // 3. Push to GitHub
            // 4. Create a pull request
            // 5. Show success message with PR link
        }
    });
    
})(jQuery);
