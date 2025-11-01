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
        add_action('admin_menu', [$this, 'addMenuPage']);
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
            'praison-dashboard',
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
            '1.0.0'
        );
        
        wp_enqueue_script(
            'praison-export-js',
            plugins_url('../../assets/js/export.js', __FILE__),
            ['jquery'],
            '1.0.0',
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
        check_ajax_referer('praison_export_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'all';
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;
        
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
        
        // Store export job in transient
        $job_id = 'export_' . time() . '_' . wp_generate_password(8, false);
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
            'current_type' => $post_types[0],
            'current_page' => 1,
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        set_transient('praison_export_' . $job_id, $job_data, DAY_IN_SECONDS);
        
        // Schedule first batch
        wp_schedule_single_event(time(), 'praison_background_export', [$job_id, $post_types[0], 1]);
        
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
            error_log('PraisonPress Export: Job not found - ' . $job_id);
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
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        $job_data = get_transient('praison_export_' . $job_id);
        
        if (!$job_data) {
            wp_send_json_error(['message' => 'Job not found']);
        }
        
        $progress = $job_data['total_posts'] > 0 
            ? round(($job_data['processed'] / $job_data['total_posts']) * 100, 1)
            : 0;
        
        wp_send_json_success([
            'status' => $job_data['status'],
            'progress' => $progress,
            'processed' => $job_data['processed'],
            'total' => $job_data['total_posts'],
            'successful' => $job_data['successful'],
            'failed' => $job_data['failed'],
            'current_type' => $job_data['current_type'],
            'updated_at' => $job_data['updated_at'],
        ]);
    }
    
    /**
     * AJAX: Cancel export
     */
    public function ajaxCancelExport() {
        check_ajax_referer('praison_export_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
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
}
