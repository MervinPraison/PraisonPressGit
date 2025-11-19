<?php
/**
 * Export to Markdown - Admin Page
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap praison-export-page">
    <h1><?php echo esc_html__('Export to Markdown', 'praison-file-content-git'); ?></h1>
    
    <div class="praison-export-info">
        <div class="notice notice-info">
            <p>
                <strong><?php echo esc_html__('Export your WordPress content to Markdown files', 'praison-file-content-git'); ?></strong>
            </p>
            <p><?php echo esc_html__('This tool exports posts, pages, and custom post types to Markdown files with YAML front matter, preserving all metadata, categories, tags, and custom fields.', 'praison-file-content-git'); ?></p>
            <p>
                <strong><?php echo esc_html__('For large exports (10K+ posts):', 'praison-file-content-git'); ?></strong>
                <?php echo esc_html__('The export runs in the background. You can close this page and check back later.', 'praison-file-content-git'); ?>
            </p>
        </div>
    </div>
    
    <div class="praison-export-container">
        <!-- Export Configuration -->
        <div class="praison-card" id="export-config" style="display: block;">
            <h2><?php echo esc_html__('Export Configuration', 'praison-file-content-git'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post-type-select"><?php echo esc_html__('Content to Export', 'praison-file-content-git'); ?></label>
                    </th>
                    <td>
                        <select id="post-type-select" class="regular-text">
                            <option value="all"><?php echo esc_html__('All Post Types', 'praison-file-content-git'); ?></option>
                            <?php foreach ($post_types as $post_type): ?>
                                <option value="<?php echo esc_attr($post_type->name); ?>">
                                    <?php echo esc_html($post_type->label); ?> 
                                    (<?php echo esc_html($post_counts[$post_type->name]); ?> <?php echo esc_html__('posts', 'praison-file-content-git'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Select which content to export', 'praison-file-content-git'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="batch-size"><?php echo esc_html__('Batch Size', 'praison-file-content-git'); ?></label>
                    </th>
                    <td>
                        <select id="batch-size">
                            <option value="50">50 <?php echo esc_html__('posts per batch', 'praison-file-content-git'); ?></option>
                            <option value="100" selected>100 <?php echo esc_html__('posts per batch', 'praison-file-content-git'); ?></option>
                            <option value="250">250 <?php echo esc_html__('posts per batch', 'praison-file-content-git'); ?></option>
                            <option value="500">500 <?php echo esc_html__('posts per batch', 'praison-file-content-git'); ?></option>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Smaller batches = safer for shared hosting. Larger batches = faster export.', 'praison-file-content-git'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Output Directory', 'praison-file-content-git'); ?></th>
                    <td>
                        <code><?php echo esc_html(WP_CONTENT_DIR . '/../content/'); ?></code>
                        <p class="description">
                            <?php echo esc_html__('Files will be organized by post type (e.g., /content/posts/, /content/pages/)', 'praison-file-content-git'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Push to GitHub', 'praison-file-content-git'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="push-to-github" name="push_to_github" value="1" />
                            <?php echo esc_html__('Automatically push exported files to GitHub after export completes', 'praison-file-content-git'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Commits all exported files with message "Exported N posts to Markdown" and pushes to the configured repository', 'praison-file-content-git'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Total Posts', 'praison-file-content-git'); ?></th>
                    <td>
                        <strong id="total-posts-count">
                            <?php 
                            $praison_total = array_sum($post_counts);
                            echo esc_html(number_format($praison_total)); 
                            ?>
                        </strong> <?php echo esc_html__('posts across all types', 'praison-file-content-git'); ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" id="start-export-btn" class="button button-primary button-large">
                    <?php echo esc_html__('Start Export', 'praison-file-content-git'); ?>
                </button>
            </p>
        </div>
        
        <!-- Export Progress -->
        <div class="praison-card" id="export-progress" style="display: none;">
            <h2><?php echo esc_html__('Export Progress', 'praison-file-content-git'); ?></h2>
            
            <div class="export-status">
                <p class="status-message">
                    <span class="dashicons dashicons-update spin"></span>
                    <strong id="status-text"><?php echo esc_html__('Starting export...', 'praison-file-content-git'); ?></strong>
                </p>
                
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-text">
                        <span id="progress-percentage">0%</span>
                        <span id="progress-count">0 / 0</span>
                    </div>
                </div>
                
                <div class="export-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Current:', 'praison-file-content-git'); ?></span>
                        <span class="stat-value" id="current-type">-</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Successful:', 'praison-file-content-git'); ?></span>
                        <span class="stat-value" id="successful-count">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Failed:', 'praison-file-content-git'); ?></span>
                        <span class="stat-value" id="failed-count">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Last Updated:', 'praison-file-content-git'); ?></span>
                        <span class="stat-value" id="last-updated">-</span>
                    </div>
                </div>
            </div>
            
            <p class="submit">
                <button type="button" id="cancel-export-btn" class="button">
                    <?php echo esc_html__('Cancel Export', 'praison-file-content-git'); ?>
                </button>
                <button type="button" id="new-export-btn" class="button" style="display: none;">
                    <?php echo esc_html__('Start New Export', 'praison-file-content-git'); ?>
                </button>
            </p>
        </div>
        
        <!-- Export Complete -->
        <div class="praison-card" id="export-complete" style="display: none;">
            <h2><?php echo esc_html__('Export Complete!', 'praison-file-content-git'); ?></h2>
            
            <div class="notice notice-success">
                <p><strong><?php echo esc_html__('Your content has been exported successfully!', 'praison-file-content-git'); ?></strong></p>
            </div>
            
            <div class="export-results">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Metric', 'praison-file-content-git'); ?></th>
                            <th><?php echo esc_html__('Count', 'praison-file-content-git'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo esc_html__('Total Posts Processed', 'praison-file-content-git'); ?></td>
                            <td><strong id="result-processed">0</strong></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Successfully Exported', 'praison-file-content-git'); ?></td>
                            <td><strong id="result-successful">0</strong></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Failed', 'praison-file-content-git'); ?></td>
                            <td><strong id="result-failed">0</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <p class="description">
                <?php echo esc_html__('Files have been exported to:', 'praison-file-content-git'); ?>
                <code><?php echo esc_html(WP_CONTENT_DIR . '/../content/'); ?></code>
            </p>
            
            <p class="submit">
                <button type="button" id="new-export-btn-2" class="button button-primary">
                    <?php echo esc_html__('Start New Export', 'praison-file-content-git'); ?>
                </button>
            </p>
        </div>
    </div>
    
    <div class="praison-help">
        <h3><?php echo esc_html__('Need Help?', 'praison-file-content-git'); ?></h3>
        <ul>
            <li><strong><?php echo esc_html__('CLI Export:', 'praison-file-content-git'); ?></strong> 
                <code>php wp-content/plugins/praison-file-content-git/scripts/export-to-markdown.php</code>
            </li>
            <li><strong><?php echo esc_html__('WP-CLI:', 'praison-file-content-git'); ?></strong> 
                <code>wp eval-file wp-content/plugins/praison-file-content-git/scripts/export-to-markdown.php</code>
            </li>
            <li><strong><?php echo esc_html__('Documentation:', 'praison-file-content-git'); ?></strong> 
                See EXPORT-GUIDE.md for detailed information
            </li>
        </ul>
    </div>
</div>
