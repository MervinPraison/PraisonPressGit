/**
 * PraisonPress Export - Admin JavaScript
 */
(function($) {
    'use strict';
    
    let currentJobId = null;
    let statusCheckInterval = null;
    
    $(document).ready(function() {
        // Start export button
        $('#start-export-btn').on('click', startExport);
        
        // Cancel export button
        $('#cancel-export-btn').on('click', cancelExport);
        
        // New export buttons
        $('#new-export-btn, #new-export-btn-2').on('click', resetExport);
        
        // Update total count when post type changes
        $('#post-type-select').on('change', updateTotalCount);
    });
    
    function startExport() {
        const postType = $('#post-type-select').val();
        const batchSize = $('#batch-size').val();
        const pushToGithub = $('#push-to-github').is(':checked') ? '1' : '0';
        
        // Show progress section
        $('#export-config').hide();
        $('#export-progress').show();
        $('#status-text').text('Starting export...');
        
        // Start export via AJAX
        $.ajax({
            url: praisonExport.ajax_url,
            type: 'POST',
            data: {
                action: 'praison_start_export',
                nonce: praisonExport.nonce,
                post_type: postType,
                batch_size: batchSize,
                push_to_github: pushToGithub
            },
            success: function(response) {
                if (response.success) {
                    currentJobId = response.data.job_id;
                    
                    // Check if export completed synchronously (small dataset)
                    if (response.data.status === 'completed') {
                        $('#status-text').text('Export completed!');
                        showComplete(response.data);
                    } else {
                        // Background export - start polling
                        $('#status-text').text('Export running in background...');
                        statusCheckInterval = setInterval(checkExportStatus, 2000);
                    }
                } else {
                    // Show actual error from server
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to start export';
                    var debugInfo = response.data && response.data.debug ? ' (' + response.data.debug + ')' : '';
                    showError(errorMsg + debugInfo);
                }
            },
            error: function(xhr, status, error) {
                // Show actual network/server error
                var errorMsg = 'Request failed: ' + status;
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                    } catch(e) {
                        errorMsg += ' (Unable to parse response)';
                    }
                }
                console.error('Export AJAX Error:', xhr, status, error);
                showError(errorMsg);
            }
        });
    }
    
    function checkExportStatus() {
        if (!currentJobId) {
            clearInterval(statusCheckInterval);
            return;
        }
        
        $.ajax({
            url: praisonExport.ajax_url,
            type: 'POST',
            data: {
                action: 'praison_export_status',
                nonce: praisonExport.nonce,
                job_id: currentJobId
            },
            success: function(response) {
                if (response.success) {
                    updateProgress(response.data);
                    
                    // Check if complete
                    if (response.data.status === 'completed') {
                        clearInterval(statusCheckInterval);
                        showComplete(response.data);
                    }
                } else {
                    clearInterval(statusCheckInterval);
                    showError(response.data.message || 'Export job not found');
                }
            },
            error: function() {
                // Don't stop on network errors, just skip this check
                console.log('Status check failed, will retry...');
            }
        });
    }
    
    function updateProgress(data) {
        // Update progress bar
        $('.progress-fill').css('width', data.progress + '%');
        $('#progress-percentage').text(data.progress + '%');
        $('#progress-count').text(data.processed + ' / ' + data.total);
        
        // Update stats
        $('#current-type').text(data.current_type);
        $('#successful-count').text(data.successful);
        $('#failed-count').text(data.failed);
        $('#last-updated').text(data.updated_at);
        
        // Update status text
        $('#status-text').text('Exporting ' + data.current_type + '...');
    }
    
    function showComplete(data) {
        $('#export-progress').hide();
        $('#export-complete').show();
        
        // Update results
        $('#result-processed').text(data.processed);
        $('#result-successful').text(data.successful);
        $('#result-failed').text(data.failed);
        
        // Show GitHub push status if available
        if (data.github_push) {
            let pushHtml = '<div style="margin-top: 15px; padding: 10px; border-left: 4px solid ';
            if (data.github_push.success) {
                pushHtml += '#46b450; background: #ecf7ed;">';
                pushHtml += '<strong style="color: #46b450;">✓ GitHub Push:</strong> ';
            } else {
                pushHtml += '#dc3232; background: #f9e2e2;">';
                pushHtml += '<strong style="color: #dc3232;">✗ GitHub Push Failed:</strong> ';
            }
            pushHtml += data.github_push.message + '</div>';
            $('#export-complete .praison-card').append(pushHtml);
        }
    }
    
    function cancelExport() {
        if (!currentJobId) return;
        
        if (!confirm('Are you sure you want to cancel the export?')) {
            return;
        }
        
        $.ajax({
            url: praisonExport.ajax_url,
            type: 'POST',
            data: {
                action: 'praison_cancel_export',
                nonce: praisonExport.nonce,
                job_id: currentJobId
            },
            success: function(response) {
                clearInterval(statusCheckInterval);
                currentJobId = null;
                
                if (response.success) {
                    $('#status-text').html('<span style="color: red;">Export cancelled</span>');
                    $('.dashicons-update').removeClass('spin');
                    $('#cancel-export-btn').hide();
                    $('#new-export-btn').show();
                }
            }
        });
    }
    
    function resetExport() {
        currentJobId = null;
        clearInterval(statusCheckInterval);
        
        // Reset UI
        $('#export-progress').hide();
        $('#export-complete').hide();
        $('#export-config').show();
        
        // Reset progress
        $('.progress-fill').css('width', '0%');
        $('#progress-percentage').text('0%');
        $('#progress-count').text('0 / 0');
        $('#successful-count').text('0');
        $('#failed-count').text('0');
        $('#current-type').text('-');
        $('#last-updated').text('-');
        
        // Reset buttons
        $('#cancel-export-btn').show();
        $('#new-export-btn').hide();
    }
    
    function showError(message) {
        $('#status-text').html('<span style="color: red;">Error: ' + message + '</span>');
        $('.dashicons-update').removeClass('spin');
        $('#cancel-export-btn').hide();
        $('#new-export-btn').show();
    }
    
    function updateTotalCount() {
        // This would calculate total based on selected post type
        // For now, it's static from PHP
    }
    
})(jQuery);
