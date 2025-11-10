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
        
        // Add defer strategy to script
        add_filter('script_loader_tag', [$this, 'addDeferStrategy'], 10, 3);
        
        // Register AJAX endpoints
        add_action('wp_ajax_praisonpress_get_content', [$this, 'ajaxGetContent']);
        add_action('wp_ajax_nopriv_praisonpress_get_content', [$this, 'ajaxGetContent']);
        
        add_action('wp_ajax_praisonpress_submit_edit', [$this, 'ajaxSubmitEdit']);
        add_action('wp_ajax_nopriv_praisonpress_submit_edit', [$this, 'ajaxSubmitEdit']);
    }
    
    /**
     * Add defer loading strategy to script
     */
    public function addDeferStrategy($tag, $handle, $src) {
        if ('praisonpress-report-error' === $handle) {
            return str_replace(' src=', ' defer src=', $tag);
        }
        return $tag;
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
            '1.0.4'
        );
        
        // Enqueue scripts with defer strategy
        wp_enqueue_script(
            'praisonpress-report-error',
            PRAISON_PLUGIN_URL . 'assets/js/report-error.js',
            ['jquery'],
            '1.0.1',
            [
                'in_footer' => true,
                'strategy' => 'defer',
            ]
        );
        
        // Pass data to JavaScript
        global $post;
        wp_localize_script('praisonpress-report-error', 'praisonpressData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('praisonpress_report_error'),
            'postId' => get_the_ID(),
            'postType' => get_post_type(),
            'postSlug' => isset($post->post_name) ? $post->post_name : '',
        ]);
    }
    
    /**
     * Render the Report Error button
     */
    public function renderButton() {
        // Only show to logged-in users
        if (!is_user_logged_in()) {
            return;
        }
        
        // Only on singular posts/pages
        if (!is_singular()) {
            return;
        }
        
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
                <div class="praisonpress-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="praisonpress-btn praisonpress-btn-secondary praisonpress-modal-close" style="min-width: 80px; font-size: 14px; padding: 8px 16px;">Cancel</button>
                    <button type="button" id="praisonpress-submit-edit" class="praisonpress-btn praisonpress-btn-primary" style="min-width: 100px; font-size: 14px; padding: 8px 16px;">Submit Edit</button>
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
        
        $postId = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $postType = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'post';
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }
        
        // Try to get post - works for both DB and file-based posts
        global $wp_query;
        $originalQuery = $wp_query;
        
        // Create a new query for this specific post
        $args = [
            'post_type' => $postType,
            'p' => $postId,
            'posts_per_page' => 1,
        ];
        
        $query = new \WP_Query($args);
        
        if (!$query->have_posts()) {
            // Try by slug if ID doesn't work (for file-based posts)
            $postSlug = sanitize_title(get_query_var('name'));
            if (empty($postSlug) && isset($_POST['post_slug'])) {
                $postSlug = sanitize_title(wp_unslash($_POST['post_slug']));
            }
            
            if ($postSlug) {
                $args = [
                    'post_type' => $postType,
                    'name' => $postSlug,
                    'posts_per_page' => 1,
                ];
                $query = new \WP_Query($args);
            }
        }
        
        if (!$query->have_posts()) {
            wp_send_json_error(['message' => 'Post not found. Post ID: ' . $postId . ', Type: ' . $postType]);
        }
        
        $query->the_post();
        $post = $query->post; // Use query post object for file-based posts
        
        // Get the raw markdown content if it's a file-based post
        $contentFile = $this->getContentFilePath($post);
        
        // error_log('ReportError - Post: ' . $post->post_title . ', File path: ' . ($contentFile ? $contentFile : 'NOT FOUND'));
        
        if ($contentFile && file_exists($contentFile)) {
            $content = file_get_contents($contentFile);
            // error_log('ReportError - Loading from FILE: ' . $contentFile);
        } else {
            // Fallback to post content
            $content = $post->post_content;
            // error_log('ReportError - Loading from DATABASE (file not found'));
        }
        
        // Restore original query
        $wp_query = $originalQuery;
        wp_reset_postdata();
        
        // Ensure we have a valid file path
        if (!$contentFile) {
            // Try to construct the path
            $contentFile = $this->getContentFilePath($post);
        }
        
        wp_send_json_success([
            'content' => $content,
            'title' => $post->post_title,
            'post_type' => $post->post_type,
            'file_path' => $contentFile ? $contentFile : '',
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
        
        // Try with date prefix (e.g., 2025-11-01-slug.md)
        if (is_dir($contentDir . '/' . $postType)) {
            $files = glob($contentDir . '/' . $postType . '/*' . $slug . '.md');
            if (!empty($files)) {
                return $files[0];
            }
        }
        
        // Try recursive search in subdirectories (e.g., a/amazing-grace.md)
        if (is_dir($contentDir . '/' . $postType)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contentDir . '/' . $postType, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $filename = $file->getBasename('.md');
                    // Check if filename matches slug (with or without date prefix)
                    if ($filename === $slug || preg_match('/\d{4}-\d{2}-\d{2}-' . preg_quote($slug, '/') . '$/', $filename)) {
                        return $file->getPathname();
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * AJAX handler to submit edit and create PR
     */
    public function ajaxSubmitEdit() {
        // Verify nonce
        check_ajax_referer('praisonpress_report_error', 'nonce');
        
        // Get data
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $postId = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $postType = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'post';
        $postSlug = isset($_POST['post_slug']) ? sanitize_title(wp_unslash($_POST['post_slug'])) : '';
        $postTitle = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
        $filePath = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';
        
        // Validate
        if (empty($content)) {
            wp_send_json_error(['message' => 'Content cannot be empty']);
        }
        
        // If no file path, create one for database posts
        if (empty($filePath)) {
            // Construct file path for database post
            $contentDir = PRAISON_CONTENT_DIR;
            $filePath = $contentDir . '/' . $postType . '/' . $postSlug . '.md';
            
            // Ensure directory exists
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
        
        // Security check: ensure file path is within content directory
        $realPath = realpath(dirname($filePath));
        $contentDirReal = realpath(PRAISON_CONTENT_DIR);
        
        if (!$realPath || strpos($realPath, $contentDirReal) !== 0) {
            wp_send_json_error(['message' => 'Invalid file path']);
        }
        
        // Initialize PR manager
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/PullRequestManager.php';
        require_once PRAISON_PLUGIN_DIR . '/src/Git/GitManager.php';
        
        $prManager = new \PraisonPress\GitHub\PullRequestManager();
        
        // Prepare post data
        $postData = [
            'id' => $postId,
            'type' => $postType,
            'slug' => $postSlug,
            'title' => $postTitle,
        ];
        
        // Create pull request
        $result = $prManager->createPullRequest($filePath, $content, $description, $postData);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'pr_url' => isset($result['pr_url']) ? $result['pr_url'] : '',
                'pr_number' => isset($result['pr_number']) ? $result['pr_number'] : '',
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}
