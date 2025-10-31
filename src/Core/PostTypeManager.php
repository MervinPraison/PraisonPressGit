<?php
namespace PraisonPress\Core;

/**
 * Manage multiple custom post types for file-based content
 */
class PostTypeManager {
    
    private static $registered_types = [];
    
    /**
     * Register a file-based post type
     * 
     * @param string $post_type Post type slug (e.g., 'lyrics', 'recipes')
     * @param array $args Configuration arguments
     */
    public static function register($post_type, $args = []) {
        $defaults = [
            'label' => ucfirst($post_type),
            'directory' => PRAISON_CONTENT_DIR . '/' . $post_type,
            'slug' => $post_type,
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'public' => true,
            'has_archive' => true,
            'show_in_menu' => false,
            'icon' => 'dashicons-media-text',
        ];
        
        $config = array_merge($defaults, $args);
        self::$registered_types[$post_type] = $config;
        
        // Register with WordPress
        add_action('init', function() use ($post_type, $config) {
            register_post_type($post_type, [
                'label' => $config['label'],
                'public' => $config['public'],
                'publicly_queryable' => true,
                'show_ui' => false,
                'show_in_menu' => $config['show_in_menu'],
                'show_in_nav_menus' => true,
                'show_in_admin_bar' => false,
                'has_archive' => $config['has_archive'],
                'rewrite' => ['slug' => $config['slug']],
                'supports' => $config['supports'],
            ]);
        });
        
        // Create directory if it doesn't exist
        if (!file_exists($config['directory'])) {
            wp_mkdir_p($config['directory']);
        }
    }
    
    /**
     * Get all registered post types
     * 
     * @return array
     */
    public static function getRegisteredTypes() {
        return self::$registered_types;
    }
    
    /**
     * Get configuration for a specific post type
     * 
     * @param string $post_type
     * @return array|null
     */
    public static function getConfig($post_type) {
        return self::$registered_types[$post_type] ?? null;
    }
    
    /**
     * Check if a post type is file-based
     * 
     * @param string $post_type
     * @return bool
     */
    public static function isFileBased($post_type) {
        return isset(self::$registered_types[$post_type]);
    }
    
    /**
     * Get directory for a post type
     * 
     * @param string $post_type
     * @return string|null
     */
    public static function getDirectory($post_type) {
        $config = self::getConfig($post_type);
        return $config ? $config['directory'] : null;
    }
}
