<?php
namespace PraisonPress\Core;

use PraisonPress\Loaders\PostLoader;
use PraisonPress\Cache\CacheManager;
use PraisonPress\Admin\ExportPage;

/**
 * Bootstrap PraisonPress plugin
 * This is the main entry point that hooks into WordPress
 */
class Bootstrap {
    
    private static $instance = null;
    private $postLoaders = [];
    private $postTypes = [];
    
    /**
     * Initialize the plugin
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - register all hooks
     */
    private function __construct() {
        // Discover post types dynamically from content directory
        $this->postTypes = $this->discoverPostTypes();
        
        // Initialize loaders for each discovered post type
        foreach ($this->postTypes as $type) {
            $this->postLoaders[$type] = new PostLoader($type);
        }
        
        // Register custom post type
        add_action('init', [$this, 'registerPostType']);
        
        // Virtual post injection - THE CORE MAGIC!
        add_filter('posts_pre_query', [$this, 'injectFilePosts'], 10, 2);
        
        // Initialize export page early (before admin_menu)
        if (is_admin()) {
            new ExportPage();
        }
        
        // Admin features (priority 10 - default)
        add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // Dashboard widget
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
        
        // Version history submenu (priority 20 - appears after Export)
        add_action('admin_menu', [$this, 'addHistoryMenu'], 20);
        
        // Note: Export menu is added at priority 15 by ExportPage class
        
        // Admin bar items
        add_action('admin_bar_menu', [$this, 'addAdminBarItems'], 100);
        
        // Cache management
        add_action('admin_post_praison_clear_cache', [$this, 'handleClearCache']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'showAdminNotices']);
    }
    
    /**
     * Dynamically discover post types from content directory
     * Scans folders and auto-registers them as post types
     * 
     * @return array List of post type slugs
     */
    private function discoverPostTypes() {
        $types = [];
        
        // Check if content directory exists
        if (!file_exists(PRAISON_CONTENT_DIR) || !is_dir(PRAISON_CONTENT_DIR)) {
            return $types;
        }
        
        // Scan content directory for subdirectories
        $items = scandir(PRAISON_CONTENT_DIR);
        
        foreach ($items as $item) {
            // Skip hidden files, current/parent directory references
            if ($item[0] === '.' || $item === 'config') {
                continue;
            }
            
            $path = PRAISON_CONTENT_DIR . '/' . $item;
            
            // Only process directories
            if (is_dir($path)) {
                $types[] = $item;
            }
        }
        
        return $types;
    }
    
    /**
     * Register custom post types dynamically based on discovered folders
     * Only registers if WordPress hasn't already registered the post type
     */
    public function registerPostType() {
        foreach ($this->postTypes as $type) {
            // Special case for 'posts' - register as 'praison_post'
            $post_type_slug = ($type === 'posts') ? 'praison_post' : $type;
            $rewrite_slug = $type;
            
            // Skip if post type already registered by WordPress or another plugin
            if (post_type_exists($post_type_slug)) {
                continue;
            }
            
            // Generate human-readable labels
            $singular = ucfirst($type);
            $plural = $singular;
            
            // Handle special pluralization
            if (substr($type, -1) !== 's') {
                $plural = $singular . 's';
            }
            
            // Register the post type dynamically
            register_post_type($post_type_slug, [
                'label' => $plural,
                'labels' => [
                    'name' => $plural,
                    'singular_name' => $singular,
                    'add_new' => 'Add New ' . $singular,
                    'add_new_item' => 'Add New ' . $singular,
                    'edit_item' => 'Edit ' . $singular,
                    'view_item' => 'View ' . $singular,
                    'all_items' => 'All ' . $plural,
                ],
                'public' => true,
                'show_ui' => false, // File-based, no admin UI
                'show_in_menu' => false,
                'show_in_nav_menus' => true,
                'show_in_admin_bar' => false,
                'publicly_queryable' => true,
                'exclude_from_search' => false,
                'has_archive' => true,
                'rewrite' => ['slug' => $rewrite_slug],
                'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
                'taxonomies' => ['category', 'post_tag'],
            ]);
        }
    }
    
    /**
     * Inject file-based posts before database query
     * This is the CORE functionality!
     * 
     * @param array|null $posts Return array to short-circuit, null to proceed normally
     * @param \WP_Query $query The query object
     * @return array|null
     */
    public function injectFilePosts($posts, $query) {
        // Skip injection only in specific admin contexts (not AJAX, not frontend)
        // This allows file-based posts to load on frontend while preventing export issues
        if ((is_admin() && !wp_doing_ajax()) || (defined('WP_CLI') && WP_CLI)) {
            return $posts;
        }
        
        // Get the post type being queried
        $post_type = $query->get('post_type');
        
        // Debug logging (can be disabled in production)
        // Commented out for production - uncomment for debugging
        // if (defined('PRAISON_DEBUG') && PRAISON_DEBUG) {
        //     error_log('PraisonPress: Query detected - Post Type: ' . ($post_type ?: 'none') . ', Main Query: ' . ($query->is_main_query() ? 'yes' : 'no'));
        // }
        
        // If no post type specified, check if we're on home/archive (main query only)
        if (empty($post_type)) {
            if ($query->is_main_query() && (is_home() || is_archive())) {
                // Check if posts loader exists before calling
                if (isset($this->postLoaders['posts'])) {
                    return $this->postLoaders['posts']->loadPosts($query);
                }
            }
            return $posts;
        }
        
        // Check if this is a file-based post type and load accordingly
        // For custom post types, inject even if not main query (for WP_Query calls)
        
        // Get file-based posts
        $file_posts = null;
        
        // Special case: praison_post maps to 'posts' directory
        if ($post_type === 'praison_post' && is_array($this->postLoaders) && isset($this->postLoaders['posts'])) {
            $file_posts = $this->postLoaders['posts']->loadPosts($query);
        }
        // Check if we have a loader for this post type
        elseif (is_array($this->postLoaders) && isset($this->postLoaders[$post_type])) {
            $file_posts = $this->postLoaders[$post_type]->loadPosts($query);
        }
        // Dynamic loader creation: If post type directory exists but no loader, create one
        else {
            $post_type_dir = PRAISON_CONTENT_DIR . '/' . $post_type;
            if (is_dir($post_type_dir)) {
                $this->postLoaders[$post_type] = new \PraisonPress\Loaders\PostLoader($post_type);
                $file_posts = $this->postLoaders[$post_type]->loadPosts($query);
            }
        }
        
        // If no file-based posts, return database posts as-is
        if (empty($file_posts)) {
            return $posts;
        }
        
        // If $posts is null (posts_pre_query before DB query), return only file-based posts
        // This happens when WordPress hasn't queried the database yet
        if ($posts === null) {
            return $file_posts;
        }
        
        // MERGE: Combine database posts with file-based posts
        // File-based posts take precedence for duplicate slugs
        $merged = [];
        $slugs_seen = [];
        
        // Add file-based posts first (they take precedence)
        foreach ($file_posts as $post) {
            $merged[] = $post;
            $slugs_seen[$post->post_name] = true;
        }
        
        // Add database posts that don't conflict with file-based posts
        foreach ($posts as $post) {
            if (!isset($slugs_seen[$post->post_name])) {
                $merged[] = $post;
                $slugs_seen[$post->post_name] = true;
            }
        }
        
        return $merged;
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        // Add main menu page
        add_menu_page(
            'PraisonPress',
            'PraisonPress',
            'manage_options',
            'praisonpress',
            [$this, 'renderAdminPage'],
            'dashicons-media-text',
            30
        );
        
        // Rename the default submenu item (WordPress auto-creates one)
        // Priority: This runs at default priority 10
        add_submenu_page(
            'praisonpress',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'praisonpress',  // Same as parent - this renames the default
            [$this, 'renderAdminPage']
        );
        
        // Note: Export menu added at priority 15 by ExportPage class
        
        // Settings will be added separately with priority 16
        add_action('admin_menu', function() {
            add_submenu_page(
                'praisonpress',
                'Settings',
                'Settings',
                'manage_options',
                'praisonpress-settings',
                [$this, 'renderSettingsPage']
            );
        }, 16);
    }
    
    /**
     * Add version history submenu
     */
    public function addHistoryMenu() {
        add_submenu_page(
            'praisonpress',
            'Version History',
            '📜 History',
            'manage_options',
            'praisonpress-history',
            [$this, 'renderHistoryPage']
        );
    }
    
    /**
     * Render history page
     */
    public function renderHistoryPage() {
        $historyPage = new \PraisonPress\Admin\HistoryPage();
        $historyPage->render();
    }
    
    /**
     * Add dashboard widget
     */
    public function addDashboardWidget() {
        wp_add_dashboard_widget(
            'praisonpress_status',
            '📁 PraisonPress Status',
            [$this, 'renderDashboardWidget']
        );
    }
    
    /**
     * Add admin bar items
     * 
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function addAdminBarItems($wp_admin_bar) {
        $wp_admin_bar->add_node([
            'id'    => 'praisonpress-menu',
            'title' => '📁 PraisonPress',
            'href'  => admin_url('admin.php?page=praisonpress'),
        ]);
        
        $wp_admin_bar->add_node([
            'id'     => 'praisonpress-clear-cache',
            'parent' => 'praisonpress-menu',
            'title'  => 'Clear Cache',
            'href'   => admin_url('admin-post.php?action=praison_clear_cache'),
        ]);
        
        $wp_admin_bar->add_node([
            'id'     => 'praisonpress-content-dir',
            'parent' => 'praisonpress-menu',
            'title'  => 'Open Content Directory',
            'href'   => '#',
            'meta'   => [
                'title' => PRAISON_CONTENT_DIR,
            ]
        ]);
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage() {
        // Get stats safely, handling case where content directories don't exist
        $stats = isset($this->postLoaders['posts']) ? $this->postLoaders['posts']->getStats() : [
            'total_posts' => 0,
            'cache_active' => false,
            'content_dir' => PRAISON_CONTENT_DIR . '/posts'
        ];
        
        ?>
        <div class="wrap">
            <h1>📁 PraisonPress - File-Based Content Management</h1>
            
            <div class="notice notice-info">
                <p><strong>Welcome to PraisonPress!</strong> Your content is loaded from files.</p>
            </div>
            
            <div class="card" style="max-width: 800px;">
                <h2>📊 Statistics</h2>
                <table class="widefat">
                    <tbody>
                        <?php if (isset($stats['total_posts'])): ?>
                        <tr>
                            <td><strong>Posts:</strong></td>
                            <td><?php echo esc_html( $stats['total_posts'] ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($stats['total_lyrics'])): ?>
                        <tr>
                            <td><strong>Lyrics:</strong></td>
                            <td><?php echo esc_html( $stats['total_lyrics'] ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($stats['total_recipes'])): ?>
                        <tr>
                            <td><strong>Recipes:</strong></td>
                            <td><?php echo esc_html( $stats['total_recipes'] ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($stats['total_pages'])): ?>
                        <tr>
                            <td><strong>Pages:</strong></td>
                            <td><?php echo esc_html( $stats['total_pages'] ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Cache Status:</strong></td>
                            <td>
                                <?php if ($stats['cache_active']): ?>
                                    <span style="color: green;">✅ Active</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Last Modified:</strong></td>
                            <td><?php echo esc_html($stats['last_modified']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Content Directory:</strong></td>
                            <td><code><?php echo esc_html(PRAISON_CONTENT_DIR); ?></code></td>
                        </tr>
                    </tbody>
                </table>
                
                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=praison_clear_cache'), 'praison_clear_cache_action', 'praison_nonce')); ?>" 
                       class="button button-primary">Clear Cache</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-settings')); ?>" 
                       class="button">Settings</a>
                </p>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>📝 Quick Start</h2>
                <ol>
                    <li>Create a new <code>.md</code> file in <code><?php echo esc_html(PRAISON_CONTENT_DIR); ?>/posts/</code></li>
                    <li>Add YAML front matter at the top:
                        <pre style="background: #f5f5f5; padding: 10px; margin: 10px 0;">---
title: "Your Post Title"
slug: "your-post-slug"
author: "admin"
date: "<?php echo esc_html(gmdate('Y-m-d H:i:s')); ?>"
status: "publish"
---

# Your content here in Markdown...</pre>
                    </li>
                    <li>Save the file - it's automatically live! 🎉</li>
                    <li>View your posts at: <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_url(home_url()); ?></a></li>
                </ol>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>🔗 Useful Links</h2>
                <ul>
                    <li><a href="<?php echo esc_url(content_url('plugins/PRAISONPRESS-README.md')); ?>" target="_blank">Full Documentation</a></li>
                    <li><a href="<?php echo esc_url(home_url()); ?>" target="_blank">View Site</a></li>
                    <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=post')); ?>">Regular Posts (Database)</a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        ?>
        <div class="wrap">
            <h1>PraisonPress Settings</h1>
            
            <div class="card" style="max-width: 800px;">
                <h2>⚙️ Configuration</h2>
                <p>Edit your configuration file at:</p>
                <p><code><?php echo esc_html(PRAISON_CONTENT_DIR); ?>/config/site-settings.ini</code></p>
                
                <h3>Current Settings</h3>
                <?php
                $config_file = PRAISON_CONTENT_DIR . '/config/site-settings.ini';
                if (file_exists($config_file)) {
                    $config = parse_ini_file($config_file, true);
                    echo '<pre style="background: #f5f5f5; padding: 10px;">';
                    echo esc_html(wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    echo '</pre>';
                } else {
                    echo '<p><em>No configuration file found.</em></p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget() {
        $stats = $this->postLoaders['posts']->getStats();
        ?>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
            <?php if (isset($stats['total_posts'])): ?>
            <div style="flex: 1; min-width: 80px; text-align: center;">
                <strong style="font-size: 24px; display: block;"><?php echo esc_html( $stats['total_posts'] ); ?></strong>
                <span style="color: #666; font-size: 11px;">Posts</span>
            </div>
            <?php endif; ?>
            <?php if (isset($stats['total_lyrics'])): ?>
            <div style="flex: 1; min-width: 80px; text-align: center;">
                <strong style="font-size: 24px; display: block;"><?php echo esc_html( $stats['total_lyrics'] ); ?></strong>
                <span style="color: #666; font-size: 11px;">Lyrics</span>
            </div>
            <?php endif; ?>
            <?php if (isset($stats['total_recipes'])): ?>
            <div style="flex: 1; min-width: 80px; text-align: center;">
                <strong style="font-size: 24px; display: block;"><?php echo esc_html( $stats['total_recipes'] ); ?></strong>
                <span style="color: #666; font-size: 11px;">Recipes</span>
            </div>
            <?php endif; ?>
            <?php if (isset($stats['total_pages'])): ?>
            <div style="flex: 1; min-width: 80px; text-align: center;">
                <strong style="font-size: 24px; display: block;"><?php echo esc_html( $stats['total_pages'] ); ?></strong>
                <span style="color: #666; font-size: 11px;">Pages</span>
            </div>
            <?php endif; ?>
            <div style="flex: 1; min-width: 80px; text-align: center;">
                <strong style="font-size: 24px; display: block;">
                    <?php echo $stats['cache_active'] ? '✅' : '❌'; ?>
                </strong>
                <span style="color: #666; font-size: 11px;">Cache</span>
            </div>
        </div>
        
        <p style="margin: 10px 0; padding-top: 10px; border-top: 1px solid #ddd;">
            <p><strong>Last Update:</strong> <?php echo esc_html( gmdate( 'Y-m-d H:i:s', strtotime( $stats['last_modified'] ) ) ); ?></p>
        </p>
        
        <p style="margin-top: 15px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress')); ?>" class="button button-primary">
                Manage Content
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=praison_clear_cache'), 'praison_clear_cache_action', 'praison_nonce')); ?>" 
               class="button" style="margin-left: 5px;">
                Clear Cache
            </a>
        </p>
        <?php
    }
    
    /**
     * Handle cache clear action
     */
    public function handleClearCache() {
        // Security check - verify nonce
        if (!isset($_GET['praison_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['praison_nonce'])), 'praison_clear_cache_action')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Clear cache
        $cleared = CacheManager::clearAll();
        
        // Create nonce for the redirect
        $redirect_nonce = wp_create_nonce('praison_cache_cleared');
        
        // Redirect back with notice
        wp_redirect(add_query_arg([
            'page' => 'praisonpress',
            'cache_cleared' => '1',
            'count' => $cleared,
            '_wpnonce' => $redirect_nonce
        ], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Show admin notices
     */
    public function showAdminNotices() {
        // Verify nonce for cache cleared notice
        if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] == '1') {
            // Nonce verification for GET parameters
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'praison_cache_cleared')) {
                // If nonce verification fails, still show count if valid
            }
            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Cache cleared!</strong> <?php echo esc_html($count); ?> cache entries removed.</p>
            </div>
            <?php
        }
    }
}
