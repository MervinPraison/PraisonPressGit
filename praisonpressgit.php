<?php
/**
 * Plugin Name: PraisonPressGit
 * Description: Load WordPress content from files (Markdown, JSON, YAML) without database writes, with Git-based version control
 * Version: 1.0.0
 * Author: MervinPraison
 * Author URI: https://mer.vin
 * License: GPL v2 or later
 * Text Domain: praisonpressgit
 */

defined('ABSPATH') or die('Direct access not allowed');

// Define constants
define('PRAISON_VERSION', '1.0.0');
define('PRAISON_PLUGIN_DIR', __DIR__);
define('PRAISON_PLUGIN_URL', trailingslashit(plugins_url('', __FILE__)));

// Content directory - Hybrid approach for maximum flexibility:
// 1. Can be overridden in wp-config.php: define('PRAISON_CONTENT_DIR', '/custom/path');
// 2. Can be filtered: add_filter('praison_content_dir', function($dir) { return '/custom/path'; });
// 3. Defaults to: ABSPATH . 'content' (root level, independent of WordPress)
if (!defined('PRAISON_CONTENT_DIR')) {
    $default_content_dir = ABSPATH . 'content';
    define('PRAISON_CONTENT_DIR', apply_filters('praison_content_dir', $default_content_dir));
}

define('PRAISON_CACHE_GROUP', 'praisonpress');

// Simple autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'PraisonPress\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $file = PRAISON_PLUGIN_DIR . '/src/' . str_replace(['PraisonPress\\', '\\'], ['', '/'], $class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Bootstrap the plugin
add_action('plugins_loaded', function() {
    if (class_exists('PraisonPress\\Core\\Bootstrap')) {
        PraisonPress\Core\Bootstrap::init();
    }
}, 1);

// Installation - create directories
register_activation_hook(__FILE__, 'praison_install');

function praison_install() {
    // Create content directory at root level (independent of WordPress)
    $directories = [
        PRAISON_CONTENT_DIR,
        PRAISON_CONTENT_DIR . '/posts',
        PRAISON_CONTENT_DIR . '/pages',
        PRAISON_CONTENT_DIR . '/lyrics',
        PRAISON_CONTENT_DIR . '/recipes',
        PRAISON_CONTENT_DIR . '/config',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            file_put_contents($dir . '/.gitkeep', '');
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Helper functions for easy access
function praison_get_posts($args = []) {
    if (class_exists('PraisonPress\\Loaders\\PostLoader')) {
        $loader = new PraisonPress\Loaders\PostLoader();
        return $loader->getPosts($args);
    }
    return [];
}

function praison_clear_cache() {
    if (class_exists('PraisonPress\\Cache\\CacheManager')) {
        PraisonPress\Cache\CacheManager::clearAll();
    }
}

function praison_get_stats() {
    if (class_exists('PraisonPress\\Loaders\\PostLoader')) {
        $loader = new PraisonPress\Loaders\PostLoader();
        return $loader->getStats();
    }
    return [];
}
