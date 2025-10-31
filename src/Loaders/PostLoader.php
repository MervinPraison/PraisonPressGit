<?php
namespace PraisonPress\Loaders;

use PraisonPress\Parsers\MarkdownParser;
use PraisonPress\Parsers\FrontMatterParser;
use PraisonPress\Cache\CacheManager;

/**
 * Load posts from Markdown files
 */
class PostLoader {
    
    private $parser;
    private $frontMatterParser;
    private $postsDir;
    private $postType;
    
    public function __construct($postType = 'posts') {
        $this->parser = new MarkdownParser();
        $this->frontMatterParser = new FrontMatterParser();
        $this->postType = $postType;
        $this->postsDir = PRAISON_CONTENT_DIR . '/' . $postType;
    }
    
    /**
     * Load posts from files based on query
     * 
     * @param \WP_Query $query WordPress query object
     * @return array Array of WP_Post objects
     */
    public function loadPosts($query) {
        // Build cache key using actual post type
        $cache_key = CacheManager::getContentKey($this->postType, [
            'paged' => $query->get('paged'),
            'posts_per_page' => $query->get('posts_per_page'),
            's' => $query->get('s'),
        ]);
        
        // Check cache
        $cached = CacheManager::get($cache_key);
        if ($cached !== false && is_array($cached)) {
            $this->setPaginationVars($query, $cached);
            return $cached['posts'];
        }
        
        // Load from files
        $all_posts = $this->loadAllPosts();
        
        // Filter based on query
        $filtered_posts = $this->filterPosts($all_posts, $query);
        
        // Sort by date (newest first)
        usort($filtered_posts, function($a, $b) {
            return strtotime($b->post_date) - strtotime($a->post_date);
        });
        
        // Apply pagination
        $paginated_posts = $this->applyPagination($filtered_posts, $query);
        
        // Cache results
        $cache_data = [
            'posts' => $paginated_posts,
            'found_posts' => count($filtered_posts),
            'max_num_pages' => ceil(count($filtered_posts) / max(1, $query->get('posts_per_page') ?: 10))
        ];
        
        CacheManager::set($cache_key, $cache_data, 3600);
        
        // Set pagination vars
        $this->setPaginationVars($query, $cache_data);
        
        return $paginated_posts;
    }
    
    /**
     * Load all posts from files
     * 
     * @return array Array of WP_Post objects
     */
    private function loadAllPosts() {
        if (!file_exists($this->postsDir)) {
            return [];
        }
        
        $files = glob($this->postsDir . '/*.md');
        
        if (empty($files)) {
            return [];
        }
        
        $posts = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $parsed = $this->frontMatterParser->parse($content);
            
            // Create virtual WP_Post object
            $post = $this->createPostObject($parsed, $file);
            
            if ($post) {
                $posts[] = $post;
            }
        }
        
        return $posts;
    }
    
    /**
     * Create a WP_Post object from parsed data
     * 
     * @param array $parsed Parsed content with metadata
     * @param string $file File path
     * @return \WP_Post|null Post object or null on error
     */
    private function createPostObject($parsed, $file) {
        $metadata = $parsed['metadata'];
        
        // Required fields
        if (empty($metadata['title']) || empty($metadata['slug'])) {
            return null;
        }
        
        // Parse markdown content to HTML
        $content = $this->parser->parse($parsed['content']);
        
        // Get author ID
        $author_id = $this->getUserIdByLogin($metadata['author'] ?? 'admin');
        
        // Create post data
        $post_data = [
            'ID' => abs(crc32($metadata['slug'])), // Generate numeric ID from slug
            'post_author' => $author_id,
            'post_date' => $metadata['date'] ?? current_time('mysql'),
            'post_date_gmt' => $metadata['date'] ?? current_time('mysql', 1),
            'post_content' => $content,
            'post_title' => $metadata['title'],
            'post_excerpt' => $metadata['excerpt'] ?? '',
            'post_status' => $metadata['status'] ?? 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => $metadata['slug'],
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $metadata['modified'] ?? current_time('mysql'),
            'post_modified_gmt' => $metadata['modified'] ?? current_time('mysql', 1),
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => home_url('?praison_post=' . $metadata['slug']),
            'menu_order' => 0,
            'post_type' => $this->postType === 'posts' ? 'praison_post' : $this->postType,
            'post_mime_type' => '',
            'comment_count' => 0,
            'filter' => 'raw',
        ];
        
        // Create WP_Post object
        $post = new \WP_Post((object) $post_data);
        
        // Store additional metadata
        $post->_praison_file = $file;
        $post->_praison_categories = $metadata['categories'] ?? [];
        $post->_praison_tags = $metadata['tags'] ?? [];
        $post->_praison_featured_image = $metadata['featured_image'] ?? '';
        $post->_praison_custom_fields = $metadata['custom_fields'] ?? [];
        
        return $post;
    }
    
    /**
     * Filter posts based on query parameters
     * 
     * @param array $posts All posts
     * @param \WP_Query $query Query object
     * @return array Filtered posts
     */
    private function filterPosts($posts, $query) {
        $filtered = [];
        
        foreach ($posts as $post) {
            // Match post status
            $status = $query->get('post_status');
            if ($status && $status !== 'any' && $post->post_status !== $status) {
                continue;
            }
            
            // Match search query
            $search = $query->get('s');
            if ($search) {
                $haystack = strtolower($post->post_title . ' ' . $post->post_content);
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }
            
            $filtered[] = $post;
        }
        
        return $filtered;
    }
    
    /**
     * Apply pagination to posts
     * 
     * @param array $posts All posts
     * @param \WP_Query $query Query object
     * @return array Paginated posts
     */
    private function applyPagination($posts, $query) {
        $paged = max(1, $query->get('paged'));
        $posts_per_page = $query->get('posts_per_page') ?: get_option('posts_per_page', 10);
        
        if ($posts_per_page == -1) {
            return $posts;
        }
        
        $offset = ($paged - 1) * $posts_per_page;
        return array_slice($posts, $offset, $posts_per_page);
    }
    
    /**
     * Set pagination variables on query object
     * 
     * @param \WP_Query $query Query object
     * @param array $data Cache data with counts
     */
    private function setPaginationVars($query, $data) {
        $query->found_posts = $data['found_posts'];
        $query->max_num_pages = $data['max_num_pages'];
    }
    
    /**
     * Get user ID by login name
     * 
     * @param string $login User login
     * @return int User ID (defaults to 1 if not found)
     */
    private function getUserIdByLogin($login) {
        $user = get_user_by('login', $login);
        return $user ? $user->ID : 1;
    }
    
    /**
     * Get posts directly (for helper functions)
     * 
     * @param array $args Query arguments
     * @return array Array of WP_Post objects
     */
    public function getPosts($args = []) {
        $query = new \WP_Query($args);
        return $this->loadAllPosts();
    }
    
    /**
     * Get statistics about file-based posts
     * 
     * @return array Stats array
     */
    public function getStats() {
        $base_dir = PRAISON_CONTENT_DIR;
        $stats = [
            'cache_active' => CacheManager::isActive(),
            'last_modified' => $this->getLastModified(),
        ];
        
        // Dynamically scan all directories for markdown files
        if (file_exists($base_dir) && is_dir($base_dir)) {
            $items = scandir($base_dir);
            foreach ($items as $item) {
                // Skip hidden files and config directory
                if ($item[0] === '.' || $item === 'config') {
                    continue;
                }
                
                $full_path = $base_dir . '/' . $item;
                if (is_dir($full_path)) {
                    $count = count(glob($full_path . '/*.md'));
                    if ($count > 0) {
                        $stats['total_' . $item] = $count;
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Get last modification time of posts directory
     * 
     * @return string Formatted date or 'Never'
     */
    private function getLastModified() {
        if (!file_exists($this->postsDir)) {
            return 'Never';
        }
        
        $files = glob($this->postsDir . '/*.md');
        if (empty($files)) {
            return 'Never';
        }
        
        $mtimes = array_map('filemtime', $files);
        $latest = max($mtimes);
        
        return date('Y-m-d H:i:s', $latest);
    }
}
