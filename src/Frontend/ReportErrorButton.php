<?php
namespace PraisonPress\Frontend;

/**
 * Report Error Button
 * Adds a "Report Error" button to frontend content pages
 */
class ReportErrorButton {
    
    /**
     * Register hooks
     */
    public function register() {
        // Add button to frontend
        add_action('wp_footer', [$this, 'renderButton']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // Register AJAX endpoints
        add_action('wp_ajax_praisonpress_get_content', [$this, 'ajaxGetContent']);
        add_action('wp_ajax_nopriv_praisonpress_get_content', [$this, 'ajaxGetContent']);
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueueAssets() {
        // Only on singular posts/pages
        if (!is_singular()) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'praisonpress-report-error',
            PRAISON_PLUGIN_URL . 'assets/css/report-error.css',
            [],
            '1.0.0'
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'praisonpress-report-error',
            PRAISON_PLUGIN_URL . 'assets/js/report-error.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('praisonpress-report-error', 'praisonpressData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('praisonpress_report_error'),
            'postId' => get_the_ID(),
            'postType' => get_post_type(),
        ]);
    }
    
    /**
     * Render the Report Error button
     */
    public function renderButton() {
        // Only on singular posts/pages
        if (!is_singular()) {
            return;
        }
        
        // Check if user is logged in (optional - you can remove this to allow anonymous reports)
        // if (!is_user_logged_in()) {
        //     return;
        // }
        
        ?>
        <div id="praisonpress-report-error-button" class="praisonpress-floating-button">
            <button type="button" class="praisonpress-btn" title="Report an error or suggest an edit">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" fill="currentColor"/>
                </svg>
                <span>Report Error</span>
            </button>
        </div>
        
        <!-- Modal for editing content -->
        <div id="praisonpress-edit-modal" class="praisonpress-modal" style="display: none;">
            <div class="praisonpress-modal-overlay"></div>
            <div class="praisonpress-modal-content">
                <div class="praisonpress-modal-header">
                    <h2>Suggest an Edit</h2>
                    <button type="button" class="praisonpress-modal-close">&times;</button>
                </div>
                <div class="praisonpress-modal-body">
                    <p class="praisonpress-help-text">
                        Found an error or want to improve this content? Edit it below and submit your changes. 
                        An admin will review your suggestion before it goes live.
                    </p>
                    <div class="praisonpress-editor-container">
                        <textarea id="praisonpress-content-editor" rows="20"></textarea>
                    </div>
                    <div class="praisonpress-form-group">
                        <label for="praisonpress-edit-description">Describe your changes (optional):</label>
                        <textarea id="praisonpress-edit-description" rows="3" placeholder="e.g., Fixed typo in second paragraph"></textarea>
                    </div>
                </div>
                <div class="praisonpress-modal-footer">
                    <button type="button" class="praisonpress-btn praisonpress-btn-secondary praisonpress-modal-close">Cancel</button>
                    <button type="button" id="praisonpress-submit-edit" class="praisonpress-btn praisonpress-btn-primary">Submit Edit</button>
                </div>
            </div>
        </div>
        
        <!-- Loading indicator -->
        <div id="praisonpress-loading" class="praisonpress-loading" style="display: none;">
            <div class="praisonpress-spinner"></div>
            <p>Loading content...</p>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to get post content
     */
    public function ajaxGetContent() {
        // Verify nonce
        check_ajax_referer('praisonpress_report_error', 'nonce');
        
        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }
        
        $post = get_post($postId);
        
        if (!$post) {
            wp_send_json_error(['message' => 'Post not found']);
        }
        
        // Get the raw markdown content if it's a file-based post
        $contentFile = $this->getContentFilePath($post);
        
        if ($contentFile && file_exists($contentFile)) {
            $content = file_get_contents($contentFile);
        } else {
            // Fallback to post content
            $content = $post->post_content;
        }
        
        wp_send_json_success([
            'content' => $content,
            'title' => $post->post_title,
            'post_type' => $post->post_type,
        ]);
    }
    
    /**
     * Get content file path for a post
     */
    private function getContentFilePath($post) {
        // Check if this is a file-based post
        $filePath = get_post_meta($post->ID, '_praison_file_path', true);
        
        if ($filePath && file_exists($filePath)) {
            return $filePath;
        }
        
        // Try to construct path based on post type and slug
        $contentDir = PRAISON_CONTENT_DIR;
        $postType = $post->post_type;
        $slug = $post->post_name;
        
        // Try various possible locations
        $possiblePaths = [
            $contentDir . '/' . $postType . '/' . $slug . '.md',
            $contentDir . '/' . $postType . '/' . $slug . '.markdown',
            $contentDir . '/' . $slug . '.md',
            $contentDir . '/' . $slug . '.markdown',
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
}
