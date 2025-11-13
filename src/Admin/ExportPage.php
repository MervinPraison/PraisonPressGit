<?php
namespace PraisonPress\Admin;

/**
 * Export Page - Admin UI for exporting content to Markdown
 * 
 * Handles large exports (50K+ posts) using background processing
 */
class ExportPage {
    
    /**
     * Initialize export page
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addMenuPage'], 15); // Priority 15 - after Dashboard (10), before History (20)
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_praison_start_export', [$this, 'ajaxStartExport']);
        add_action('wp_ajax_praison_export_status', [$this, 'ajaxExportStatus']);
        add_action('wp_ajax_praison_cancel_export', [$this, 'ajaxCancelExport']);
        add_action('praison_background_export', [$this, 'backgroundExport'], 10, 3);
    }
    
    /**
     * Add admin menu page
     */
    public function addMenuPage() {
        add_submenu_page(
            'praisonai-git-posts',  // Fixed: matches the parent menu slug in Bootstrap.php
            'Export to Markdown',
            'Export',
            'manage_options',
            'praison-export',
            [$this, 'renderPage']
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueScripts($hook) {
        if ($hook !== 'praisonpress_page_praison-export') {
            return;
        }
        
        wp_enqueue_style(
            'praison-export-css',
            plugins_url('../../assets/css/export.css', __FILE__),
            [],
            '1.0.2'
        );
        
        wp_enqueue_script(
            'praison-export-js',
            plugins_url('../../assets/js/export.js', __FILE__),
            ['jquery'],
            '1.0.3',  // Fixed shebang breaking JSON response
            true
        );
        
        wp_localize_script('praison-export-js', 'praisonExport', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('praison_export_nonce'),
        ]);
    }
    
    /**
     * AJAX: Start export process
     */
    public function ajaxStartExport() {
        // Verify nonce
        if (!check_ajax_referer('praison_export_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => 'Security check failed. Please refresh the page and try again.',
                'debug' => 'Nonce verification failed'
            ]);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'You do not have permission to export content.',
                'debug' => 'User lacks manage_options capability'
            ]);
            return;
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'all';
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;
        $push_to_github = isset($_POST['push_to_github']) && $_POST['push_to_github'] === '1';
        
        // Get total posts to export
        if ($post_type === 'all') {
            $post_types = get_post_types(['public' => true], 'names');
            unset($post_types['attachment']);
        } else {
            $post_types = [$post_type];
        }
        
        $total_posts = 0;
        $post_counts = [];
        
        foreach ($post_types as $type) {
            $count = wp_count_posts($type);
            $post_count = $count->publish + $count->draft + $count->pending + $count->future + $count->private;
            $post_counts[$type] = $post_count;
            $total_posts += $post_count;
        }
        
        // For small datasets (< 100 posts), do synchronous export (faster, more reliable)
        if ($total_posts < 100) {
            require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'scripts/export-to-markdown.php';
            
            $successful = 0;
            $failed = 0;
            
            foreach ($post_types as $type) {
                $output_dir = WP_CONTENT_DIR . '/../content/' . $type;
                if (!is_dir($output_dir)) {
                    wp_mkdir_p($output_dir);
                }
                
                $args = [
                    'post_type' => $type,
                    'posts_per_page' => -1,
                    'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                ];
                
                $query = new \WP_Query($args);
                
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post = get_post();
                        
                        if (export_post_to_markdown($post, $output_dir)) {
                            $successful++;
                        } else {
                            $failed++;
                        }
                    }
                    
                    wp_reset_postdata();
                }
            }
            
            $processed = $successful + $failed;
            
            // Push to GitHub if requested
            $github_push = null;
            if ($push_to_github) {
                $github_push = $this->pushToGitHub($successful);
            }
            
            // Return immediate success for small exports
            $response = [
                'job_id' => 'sync_' . time(),
                'total_posts' => $total_posts,
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed,
                'status' => 'completed',
                'progress' => $total_posts > 0 ? round(($processed / $total_posts) * 100) : 100,
                'message' => sprintf('Export completed! %d posts exported successfully.', $successful)
            ];
            
            if ($github_push) {
                $response['github_push'] = $github_push;
            }
            
            wp_send_json_success($response);
            return;
        }
        
        // Store export job in transient
        $job_id = 'export_' . time() . '_' . wp_generate_password(8, false);
        $first_post_type = reset($post_types); // Get first element from array
        $job_data = [
            'id' => $job_id,
            'status' => 'started',
            'post_types' => $post_types,
            'post_counts' => $post_counts,
            'total_posts' => $total_posts,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'batch_size' => $batch_size,
            'current_type' => $first_post_type, // Fixed: properly get first post type
            'current_page' => 1,
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'push_to_github' => $push_to_github,
        ];
        
        set_transient('praison_export_' . $job_id, $job_data, DAY_IN_SECONDS);
        
        // Schedule first batch
        wp_schedule_single_event(time(), 'praison_background_export', [$job_id, $first_post_type, 1]);
        
        wp_send_json_success([
            'job_id' => $job_id,
            'total_posts' => $total_posts,
            'message' => 'Export started successfully'
        ]);
    }
    
    /**
     * Background export processing
     */
    public function backgroundExport($job_id, $post_type, $page) {
        $job_data = get_transient('praison_export_' . $job_id);
        
        if (!$job_data) {
            // error_log('PraisonPress Export: Job not found - ' . $job_id);
            return;
        }
        
        // Check if cancelled
        if ($job_data['status'] === 'cancelled') {
            delete_transient('praison_export_' . $job_id);
            return;
        }
        
        $batch_size = $job_data['batch_size'];
        $output_dir = WP_CONTENT_DIR . '/../content/' . $post_type;
        
        // Ensure output directory exists
        if (!is_dir($output_dir)) {
            wp_mkdir_p($output_dir);
        }
        
        // Get posts for this batch
        $args = [
            'post_type' => $post_type,
            'posts_per_page' => $batch_size,
            'paged' => $page,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        $query = new \WP_Query($args);
        
        if ($query->have_posts()) {
            // Load export functions
            require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'scripts/export-to-markdown.php';
            
            while ($query->have_posts()) {
                $query->the_post();
                $post = get_post();
                
                if (export_post_to_markdown($post, $output_dir)) {
                    $job_data['successful']++;
                } else {
                    $job_data['failed']++;
                }
                
                $job_data['processed']++;
            }
            
            wp_reset_postdata();
            
            // Update job data
            $job_data['current_page'] = $page;
            $job_data['updated_at'] = current_time('mysql');
            set_transient('praison_export_' . $job_id, $job_data, DAY_IN_SECONDS);
            
            // Schedule next batch
            if ($query->max_num_pages > $page) {
                // More pages in current post type
                wp_schedule_single_event(time() + 2, 'praison_background_export', [$job_id, $post_type, $page + 1]);
            } else {
                // Move to next post type
                $current_index = array_search($post_type, $job_data['post_types']);
                if (isset($job_data['post_types'][$current_index + 1])) {
                    $next_type = $job_data['post_types'][$current_index + 1];
                    $job_data['current_type'] = $next_type;
                    $job_data['current_page'] = 1;
                    set_transient('praison_export_' . $job_id, $job_data, DAY_IN_SECONDS);
                    wp_schedule_single_event(time() + 2, 'praison_background_export', [$job_id, $next_type, 1]);
                } else {
                    // All done!
                    $job_data['status'] = 'completed';
                    $job_data['completed_at'] = current_time('mysql');
                    set_transient('praison_export_' . $job_id, $job_data, DAY_IN_SECONDS);
                }
            }
        }
    }
    
    /**
     * AJAX: Get export status
     */
    public function ajaxExportStatus() {
        check_ajax_referer('praison_export_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $job_data = get_transient('praison_export_' . $job_id);
        
        if (!$job_data) {
            wp_send_json_error(['message' => 'Job not found']);
        }
        
        $progress = $job_data['total_posts'] > 0 
            ? round(($job_data['processed'] / $job_data['total_posts']) * 100, 1)
            : 0;
        
        $response = [
            'status' => $job_data['status'],
            'progress' => $progress,
            'processed' => $job_data['processed'],
            'total' => $job_data['total_posts'],
            'successful' => $job_data['successful'],
            'failed' => $job_data['failed'],
            'current_type' => $job_data['current_type'],
            'updated_at' => $job_data['updated_at'],
        ];
        
        if (isset($job_data['github_push'])) {
            $response['github_push'] = $job_data['github_push'];
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: Cancel export
     */
    public function ajaxCancelExport() {
        check_ajax_referer('praison_export_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $job_data = get_transient('praison_export_' . $job_id);
        
        if ($job_data) {
            $job_data['status'] = 'cancelled';
            set_transient('praison_export_' . $job_id, $job_data, HOUR_IN_SECONDS);
            wp_send_json_success(['message' => 'Export cancelled']);
        } else {
            wp_send_json_error(['message' => 'Job not found']);
        }
    }
    
    /**
     * Render export page
     */
    public function renderPage() {
        // Get available post types
        $post_types = get_post_types(['public' => true], 'objects');
        unset($post_types['attachment']);
        
        // Get post counts
        $post_counts = [];
        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type->name);
            $post_counts[$post_type->name] = $count->publish + $count->draft + $count->pending + $count->future + $count->private;
        }
        
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'views/export-page.php';
    }
    
    /**
     * Push exported files to GitHub
     * 
     * @param int $post_count Number of posts exported
     * @return array Result with success status and message
     */
    private function pushToGitHub($post_count) {
        try {
            require_once PRAISON_PLUGIN_DIR . '/src/Git/GitManager.php';
            require_once PRAISON_PLUGIN_DIR . '/src/GitHub/SyncManager.php';
            
            $contentDir = PRAISON_CONTENT_DIR;
            
            // Initialize Git manager
            $gitManager = new \PraisonPress\Git\GitManager($contentDir);
            
            // Check if Git is initialized
            if (!$gitManager->isGitRepo()) {
                return [
                    'success' => false,
                    'message' => 'Content directory is not a Git repository'
                ];
            }
            
            // Add all changes
            $gitManager->add('.');
            
            // Check if there are changes to commit
            $status = $gitManager->status();
            if (strpos($status, 'nothing to commit') !== false) {
                return [
                    'success' => true,
                    'message' => 'No changes to push (all files already in sync)'
                ];
            }
            
            // Commit changes
            $commitMessage = sprintf('Exported %d posts to Markdown', $post_count);
            $gitManager->commit($commitMessage);
            
            // Push to remote
            $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
            if (file_exists($config_file)) {
                $config = parse_ini_file($config_file, true);
                $repoUrl = isset($config['github']['repository_url']) ? $config['github']['repository_url'] : '';
                $mainBranch = isset($config['github']['main_branch']) ? $config['github']['main_branch'] : 'main';
                
                if ($repoUrl) {
                    $syncManager = new \PraisonPress\GitHub\SyncManager($repoUrl, $mainBranch);
                    $syncManager->setupRemote();
                    
                    // Push to remote
                    $pushResult = $gitManager->push('origin', $mainBranch);
                    
                    if ($pushResult) {
                        return [
                            'success' => true,
                            'message' => sprintf('Successfully pushed %d exported posts to GitHub', $post_count)
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to push to GitHub. Check error logs for details.'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'message' => 'GitHub repository URL not configured'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Site configuration file not found'
                ];
            }
        } catch (\Exception $e) {
            // error_log('PraisonPress Export: GitHub push failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error pushing to GitHub: ' . $e->getMessage()
            ];
        }
    }
}
