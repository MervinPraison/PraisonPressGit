<?php
/**
 * Export to Markdown - Admin Page
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap praison-export-page">
    <h1><?php echo esc_html__('Export to Markdown', 'praisonpressgit'); ?></h1>
    
    <div class="praison-export-info">
        <div class="notice notice-info">
            <p>
                <strong><?php echo esc_html__('Export your WordPress content to Markdown files', 'praisonpressgit'); ?></strong>
            </p>
            <p><?php echo esc_html__('This tool exports posts, pages, and custom post types to Markdown files with YAML front matter, preserving all metadata, categories, tags, and custom fields.', 'praisonpressgit'); ?></p>
            <p>
                <strong><?php echo esc_html__('For large exports (10K+ posts):', 'praisonpressgit'); ?></strong>
                <?php echo esc_html__('The export runs in the background. You can close this page and check back later.', 'praisonpressgit'); ?>
            </p>
        </div>
    </div>
    
    <div class="praison-export-container">
        <!-- Export Configuration -->
        <div class="praison-card" id="export-config" style="display: block;">
            <h2><?php echo esc_html__('Export Configuration', 'praisonpressgit'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post-type-select"><?php echo esc_html__('Content to Export', 'praisonpressgit'); ?></label>
                    </th>
                    <td>
                        <select id="post-type-select" class="regular-text">
                            <option value="all"><?php echo esc_html__('All Post Types', 'praisonpressgit'); ?></option>
                            <?php foreach ($post_types as $post_type): ?>
                                <option value="<?php echo esc_attr($post_type->name); ?>">
                                    <?php echo esc_html($post_type->label); ?> 
                                    (<?php echo esc_html($post_counts[$post_type->name]); ?> <?php echo esc_html__('posts', 'praisonpressgit'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Select which content to export', 'praisonpressgit'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="batch-size"><?php echo esc_html__('Batch Size', 'praisonpressgit'); ?></label>
                    </th>
                    <td>
                        <select id="batch-size">
                            <option value="50">50 <?php echo esc_html__('posts per batch', 'praisonpressgit'); ?></option>
                            <option value="100" selected>100 <?php echo esc_html__('posts per batch', 'praisonpressgit'); ?></option>
                            <option value="250">250 <?php echo esc_html__('posts per batch', 'praisonpressgit'); ?></option>
                            <option value="500">500 <?php echo esc_html__('posts per batch', 'praisonpressgit'); ?></option>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Smaller batches = safer for shared hosting. Larger batches = faster export.', 'praisonpressgit'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Output Directory', 'praisonpressgit'); ?></th>
                    <td>
                        <code><?php echo esc_html(WP_CONTENT_DIR . '/../content/'); ?></code>
                        <p class="description">
                            <?php echo esc_html__('Files will be organized by post type (e.g., /content/posts/, /content/pages/)', 'praisonpressgit'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Push to GitHub', 'praisonpressgit'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="push-to-github" name="push_to_github" value="1" />
                            <?php echo esc_html__('Automatically push exported files to GitHub after export completes', 'praisonpressgit'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Commits all exported files with message "Exported N posts to Markdown" and pushes to the configured repository', 'praisonpressgit'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Total Posts', 'praisonpressgit'); ?></th>
                    <td>
                        <strong id="total-posts-count">
                            <?php 
                            $praison_total = array_sum($post_counts);
                            echo esc_html(number_format($praison_total)); 
                            ?>
                        </strong> <?php echo esc_html__('posts across all types', 'praisonpressgit'); ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" id="start-export-btn" class="button button-primary button-large">
                    <?php echo esc_html__('Start Export', 'praisonpressgit'); ?>
                </button>
            </p>
        </div>
        
        <!-- Export Progress -->
        <div class="praison-card" id="export-progress" style="display: none;">
            <h2><?php echo esc_html__('Export Progress', 'praisonpressgit'); ?></h2>
            
            <div class="export-status">
                <p class="status-message">
                    <span class="dashicons dashicons-update spin"></span>
                    <strong id="status-text"><?php echo esc_html__('Starting export...', 'praisonpressgit'); ?></strong>
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
                        <span class="stat-label"><?php echo esc_html__('Current:', 'praisonpressgit'); ?></span>
                        <span class="stat-value" id="current-type">-</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Successful:', 'praisonpressgit'); ?></span>
                        <span class="stat-value" id="successful-count">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Failed:', 'praisonpressgit'); ?></span>
                        <span class="stat-value" id="failed-count">0</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo esc_html__('Last Updated:', 'praisonpressgit'); ?></span>
                        <span class="stat-value" id="last-updated">-</span>
                    </div>
                </div>
            </div>
            
            <p class="submit">
                <button type="button" id="cancel-export-btn" class="button">
                    <?php echo esc_html__('Cancel Export', 'praisonpressgit'); ?>
                </button>
                <button type="button" id="new-export-btn" class="button" style="display: none;">
                    <?php echo esc_html__('Start New Export', 'praisonpressgit'); ?>
                </button>
            </p>
        </div>
        
        <!-- Export Complete -->
        <div class="praison-card" id="export-complete" style="display: none;">
            <h2><?php echo esc_html__('Export Complete!', 'praisonpressgit'); ?></h2>
            
            <div class="notice notice-success">
                <p><strong><?php echo esc_html__('Your content has been exported successfully!', 'praisonpressgit'); ?></strong></p>
            </div>
            
            <div class="export-results">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Metric', 'praisonpressgit'); ?></th>
                            <th><?php echo esc_html__('Count', 'praisonpressgit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo esc_html__('Total Posts Processed', 'praisonpressgit'); ?></td>
                            <td><strong id="result-processed">0</strong></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Successfully Exported', 'praisonpressgit'); ?></td>
                            <td><strong id="result-successful">0</strong></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Failed', 'praisonpressgit'); ?></td>
                            <td><strong id="result-failed">0</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <p class="description">
                <?php echo esc_html__('Files have been exported to:', 'praisonpressgit'); ?>
                <code><?php echo esc_html(WP_CONTENT_DIR . '/../content/'); ?></code>
            </p>
            
            <p class="submit">
                <button type="button" id="new-export-btn-2" class="button button-primary">
                    <?php echo esc_html__('Start New Export', 'praisonpressgit'); ?>
                </button>
            </p>
        </div>
    </div>
    
    <div class="praison-help">
        <h3><?php echo esc_html__('Need Help?', 'praisonpressgit'); ?></h3>
        <ul>
            <li><strong><?php echo esc_html__('CLI Export:', 'praisonpressgit'); ?></strong> 
                <code>php wp-content/plugins/praisonpressgit/scripts/export-to-markdown.php</code>
            </li>
            <li><strong><?php echo esc_html__('WP-CLI:', 'praisonpressgit'); ?></strong> 
                <code>wp eval-file wp-content/plugins/praisonpressgit/scripts/export-to-markdown.php</code>
            </li>
            <li><strong><?php echo esc_html__('Documentation:', 'praisonpressgit'); ?></strong> 
                See EXPORT-GUIDE.md for detailed information
            </li>
        </ul>
    </div>
</div>
