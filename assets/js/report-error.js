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
                    post_id: praisonpressData.postId,
                    post_type: praisonpressData.postType,
                    post_slug: praisonpressData.postSlug
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        $editor.val(response.data.content);
                        $modal.fadeIn(200);
                        $editor.focus();
                        
                        // Store data for PR creation
                        window.praisonpressPostTitle = response.data.title || '';
                        window.praisonpressFilePath = response.data.file_path || '';
                    } else {
                        showErrorModal('Error', 'Error loading content: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    $loading.hide();
                    showErrorModal('Error', 'Error loading content. Please try again.');
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
                showErrorModal('Validation Error', 'Content cannot be empty.');
                return;
            }
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Creating pull request...');
            
            // Create pull request via AJAX
            $.ajax({
                url: praisonpressData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'praisonpress_submit_edit',
                    nonce: praisonpressData.nonce,
                    content: content,
                    description: description,
                    post_id: praisonpressData.postId,
                    post_type: praisonpressData.postType,
                    post_slug: praisonpressData.postSlug,
                    post_title: window.praisonpressPostTitle || '',
                    file_path: window.praisonpressFilePath || ''
                },
                success: function(response) {
                    if (response.success) {
                        let message = response.data.message;
                        
                        if (response.data.pr_url) {
                            message += '\n\nView pull request: ' + response.data.pr_url;
                            
                            // Show success with link
                            const prLink = '<a href="' + response.data.pr_url + '" target="_blank" style="color: #0073aa; text-decoration: underline;">View Pull Request #' + response.data.pr_number + '</a>';
                            const successHtml = '<div style="padding: 20px; text-align: center;">' +
                                '<h3 style="color: #00a32a; margin-bottom: 10px;">✅ Pull Request Created!</h3>' +
                                '<p>' + response.data.message + '</p>' +
                                '<p style="margin-top: 15px;">' + prLink + '</p>' +
                                '<p style="margin-top: 15px; font-size: 14px; color: #666;">An admin will review your changes soon.</p>' +
                                '</div>';
                            
                            $('.praisonpress-modal-body').html(successHtml);
                            $('.praisonpress-modal-footer').hide();
                            
                            // Auto-close after 10 seconds
                            setTimeout(function() {
                                closeModal();
                                location.reload(); // Refresh to show updated content
                            }, 10000);
                        } else {
                            showErrorModal('Success', message);
                            setTimeout(function() {
                                closeModal();
                                location.reload();
                            }, 3000);
                        }
                    } else {
                        showErrorModal('Error', response.data.message || 'Failed to create pull request');
                    }
                    
                    $submitBtn.prop('disabled', false).text('Submit Edit');
                },
                error: function() {
                    showErrorModal('Error', 'Error creating pull request. Please try again.');
                    $submitBtn.prop('disabled', false).text('Submit Edit');
                }
            });
        }
        
        /**
         * Show error modal
         */
        function showErrorModal(title, message) {
            // Remove existing error modal if any
            $('#praisonpress-error-modal').remove();
            
            // Create modal HTML
            var modalHtml = '<div id="praisonpress-error-modal" style="display: none;">' +
                '<div class="praisonpress-modal-overlay"></div>' +
                '<div class="praisonpress-modal-dialog">' +
                    '<div class="praisonpress-modal-content">' +
                        '<div class="praisonpress-modal-header" style="background: #dc3232; border-color: #dc3232;">' +
                            '<h3 style="margin: 0; color: white;">' + title + '</h3>' +
                            '<button type="button" class="praisonpress-error-close" style="background: none; border: none; color: white; font-size: 28px; line-height: 1; cursor: pointer;">&times;</button>' +
                        '</div>' +
                        '<div class="praisonpress-modal-body">' +
                            '<p style="margin: 0; white-space: pre-wrap;">' + message + '</p>' +
                        '</div>' +
                        '<div class="praisonpress-modal-footer" style="text-align: right;">' +
                            '<button type="button" class="button praisonpress-error-close">Close</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            $('body').append(modalHtml);
            $('#praisonpress-error-modal').fadeIn(200);
            
            // Close button handlers
            $('.praisonpress-error-close').on('click', function() {
                $('#praisonpress-error-modal').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Close on overlay click
            $('#praisonpress-error-modal .praisonpress-modal-overlay').on('click', function() {
                $('#praisonpress-error-modal').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Escape key
            $(document).on('keydown.errormodal', function(e) {
                if (e.key === 'Escape') {
                    $('#praisonpress-error-modal').fadeOut(200, function() {
                        $(this).remove();
                    });
                    $(document).off('keydown.errormodal');
                }
            });
        }
    });
    
})(jQuery);
