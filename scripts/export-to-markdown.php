#!/usr/bin/env php
<?php
/**
 * Export WordPress Content to Markdown
 * 
 * Exports posts, pages, and custom post types to Markdown files
 * with YAML front matter including all metadata.
 * 
 * Usage:
 *   php export-to-markdown.php [post-type] [output-directory]
 * 
 * Examples:
 *   php export-to-markdown.php post /path/to/output/posts
 *   php export-to-markdown.php page /path/to/output/pages
 *   php export-to-markdown.php lyrics /path/to/output/lyrics
 * 
 * WP-CLI Usage:
 *   wp eval-file export-to-markdown.php
 */

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    // Try to find wp-load.php
    $wp_load_paths = [
        __DIR__ . '/../../../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
        dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',
    ];
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die("Error: Could not find WordPress installation.\n");
    }
}

/**
 * Convert HTML content to Markdown
 * 
 * @param string $html HTML content
 * @return string Markdown content
 */
function html_to_markdown($html) {
    // Basic HTML to Markdown conversion
    // For production, consider using a library like league/html-to-markdown
    
    $markdown = $html;
    
    // Headers
    $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', '# $1', $markdown);
    $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', '## $1', $markdown);
    $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', '### $1', $markdown);
    $markdown = preg_replace('/<h4[^>]*>(.*?)<\/h4>/is', '#### $1', $markdown);
    $markdown = preg_replace('/<h5[^>]*>(.*?)<\/h5>/is', '##### $1', $markdown);
    $markdown = preg_replace('/<h6[^>]*>(.*?)<\/h6>/is', '###### $1', $markdown);
    
    // Bold and Italic
    $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $markdown);
    $markdown = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '**$1**', $markdown);
    $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $markdown);
    $markdown = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '*$1*', $markdown);
    
    // Links
    $markdown = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $markdown);
    
    // Images
    $markdown = preg_replace('/<img[^>]*src=["\']([^"\']*)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is', '![$2]($1)', $markdown);
    $markdown = preg_replace('/<img[^>]*src=["\']([^"\']*)["\'][^>]*\/?>/is', '![]($1)', $markdown);
    
    // Lists
    $markdown = preg_replace('/<li[^>]*>(.*?)<\/li>/is', '- $1', $markdown);
    $markdown = preg_replace('/<ul[^>]*>|<\/ul>/is', '', $markdown);
    $markdown = preg_replace('/<ol[^>]*>|<\/ol>/is', '', $markdown);
    
    // Code
    $markdown = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '`$1`', $markdown);
    $markdown = preg_replace('/<pre[^>]*>(.*?)<\/pre>/is', "```\n$1\n```", $markdown);
    
    // Paragraphs
    $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $markdown);
    
    // Line breaks
    $markdown = preg_replace('/<br\s*\/?>/is', "\n", $markdown);
    
    // Remove remaining HTML tags
    $markdown = strip_tags($markdown);
    
    // Clean up whitespace
    $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
    $markdown = trim($markdown);
    
    return $markdown;
}

/**
 * Export a single post to Markdown file
 * 
 * @param WP_Post $post Post object
 * @param string $output_dir Output directory
 * @return bool Success status
 */
function export_post_to_markdown($post, $output_dir) {
    // Get post metadata
    $author = get_userdata($post->post_author);
    $author_login = $author ? $author->user_login : 'admin';
    
    // Get categories
    $categories = [];
    $post_categories = get_the_category($post->ID);
    foreach ($post_categories as $cat) {
        $categories[] = $cat->name;
    }
    
    // Get tags
    $tags = [];
    $post_tags = get_the_tags($post->ID);
    if ($post_tags) {
        foreach ($post_tags as $tag) {
            $tags[] = $tag->name;
        }
    }
    
    // Get custom taxonomies
    $taxonomies = get_object_taxonomies($post->post_type, 'objects');
    $custom_taxonomies = [];
    foreach ($taxonomies as $tax) {
        if (!in_array($tax->name, ['category', 'post_tag'])) {
            $terms = get_the_terms($post->ID, $tax->name);
            if ($terms && !is_wp_error($terms)) {
                $custom_taxonomies[$tax->name] = [];
                foreach ($terms as $term) {
                    $custom_taxonomies[$tax->name][] = $term->name;
                }
            }
        }
    }
    
    // Get all custom fields (post meta)
    $custom_fields = [];
    
    // Check if ACF is active
    $has_acf = function_exists('get_fields');
    
    if ($has_acf) {
        // Use ACF API to get all fields (handles complex field types properly)
        $acf_fields = get_fields($post->ID);
        if ($acf_fields) {
            foreach ($acf_fields as $key => $value) {
                $custom_fields[$key] = $value;
            }
        }
    }
    
    // Also get standard post meta (non-ACF custom fields)
    $all_meta = get_post_meta($post->ID);
    foreach ($all_meta as $key => $value) {
        // Skip WordPress internal fields and ACF internal fields
        if (strpos($key, '_') !== 0 && !isset($custom_fields[$key])) {
            $custom_fields[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }
    }
    
    // Get featured image
    $featured_image = '';
    if (has_post_thumbnail($post->ID)) {
        $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
    }
    
    // Build YAML front matter
    $yaml = "---\n";
    $yaml .= "title: \"" . addslashes($post->post_title) . "\"\n";
    $yaml .= "slug: \"" . $post->post_name . "\"\n";
    $yaml .= "author: \"" . $author_login . "\"\n";
    $yaml .= "date: \"" . $post->post_date . "\"\n";
    $yaml .= "modified: \"" . $post->post_modified . "\"\n";
    $yaml .= "status: \"" . $post->post_status . "\"\n";
    $yaml .= "post_type: \"" . $post->post_type . "\"\n";
    
    if (!empty($post->post_excerpt)) {
        $yaml .= "excerpt: \"" . addslashes($post->post_excerpt) . "\"\n";
    }
    
    if (!empty($categories)) {
        $yaml .= "categories:\n";
        foreach ($categories as $cat) {
            $yaml .= "  - \"" . addslashes($cat) . "\"\n";
        }
    }
    
    if (!empty($tags)) {
        $yaml .= "tags:\n";
        foreach ($tags as $tag) {
            $yaml .= "  - \"" . addslashes($tag) . "\"\n";
        }
    }
    
    if (!empty($custom_taxonomies)) {
        foreach ($custom_taxonomies as $tax_name => $terms) {
            $yaml .= $tax_name . ":\n";
            foreach ($terms as $term) {
                $yaml .= "  - \"" . addslashes($term) . "\"\n";
            }
        }
    }
    
    if (!empty($featured_image)) {
        $yaml .= "featured_image: \"" . $featured_image . "\"\n";
    }
    
    if (!empty($custom_fields)) {
        $yaml .= "custom_fields:\n";
        foreach ($custom_fields as $key => $value) {
            if (is_array($value)) {
                $yaml .= "  " . $key . ":\n";
                foreach ($value as $v) {
                    $yaml .= "    - \"" . addslashes($v) . "\"\n";
                }
            } else {
                $yaml .= "  " . $key . ": \"" . addslashes($value) . "\"\n";
            }
        }
    }
    
    $yaml .= "---\n\n";
    
    // Convert content to Markdown
    $content = $post->post_content;
    $markdown_content = html_to_markdown($content);
    
    // Combine YAML and content
    $full_content = $yaml . $markdown_content;
    
    // Generate filename
    $date_prefix = date('Y-m-d', strtotime($post->post_date));
    $filename = $date_prefix . '-' . $post->post_name . '.md';
    $filepath = $output_dir . '/' . $filename;
    
    // Ensure output directory exists
    if (!is_dir($output_dir)) {
        mkdir($output_dir, 0755, true);
    }
    
    // Write file
    $result = file_put_contents($filepath, $full_content);
    
    if ($result !== false) {
        echo "✅ Exported: {$filename} ({$post->post_type})\n";
        return true;
    } else {
        echo "❌ Failed: {$filename}\n";
        return false;
    }
}

/**
 * Main export function
 * 
 * @param string $post_type Post type to export
 * @param string $output_dir Output directory
 */
function export_posts_to_markdown($post_type = 'post', $output_dir = null) {
    if (!$output_dir) {
        $output_dir = WP_CONTENT_DIR . '/praison-export/' . $post_type;
    }
    
    echo "\n🚀 Starting export...\n";
    echo "📝 Post Type: {$post_type}\n";
    echo "📁 Output Directory: {$output_dir}\n\n";
    
    // Get all posts of this type
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo "⚠️  No posts found for post type: {$post_type}\n";
        return;
    }
    
    $total = $query->found_posts;
    $success = 0;
    $failed = 0;
    
    echo "📊 Found {$total} posts to export\n\n";
    
    while ($query->have_posts()) {
        $query->the_post();
        $post = get_post();
        
        if (export_post_to_markdown($post, $output_dir)) {
            $success++;
        } else {
            $failed++;
        }
    }
    
    wp_reset_postdata();
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✅ Export complete!\n\n";
    echo "📊 Statistics:\n";
    echo "   Total posts: {$total}\n";
    echo "   Successful: {$success}\n";
    echo "   Failed: {$failed}\n";
    echo "   Output directory: {$output_dir}\n";
    echo str_repeat('=', 60) . "\n";
}

/**
 * Export all post types
 */
function export_all_post_types($base_output_dir = null) {
    if (!$base_output_dir) {
        $base_output_dir = WP_CONTENT_DIR . '/../content';
    }
    
    echo "\n🚀 Exporting ALL post types...\n";
    echo "📁 Base output directory: {$base_output_dir}\n\n";
    
    // Get all public post types
    $post_types = get_post_types(['public' => true], 'objects');
    
    $total_exported = 0;
    $results = [];
    
    foreach ($post_types as $post_type) {
        // Skip attachments
        if ($post_type->name === 'attachment') {
            continue;
        }
        
        $output_dir = $base_output_dir . '/' . $post_type->name;
        
        echo "\n" . str_repeat('-', 60) . "\n";
        echo "📝 Processing: {$post_type->label} ({$post_type->name})\n";
        echo str_repeat('-', 60) . "\n";
        
        ob_start();
        export_posts_to_markdown($post_type->name, $output_dir);
        $output = ob_get_clean();
        
        echo $output;
        
        // Count exports
        preg_match('/Successful: (\d+)/', $output, $matches);
        $count = $matches[1] ?? 0;
        $total_exported += (int)$count;
        
        $results[$post_type->name] = (int)$count;
    }
    
    echo "\n\n";
    echo str_repeat('=', 60) . "\n";
    echo "✅ ALL EXPORTS COMPLETE!\n\n";
    echo "📊 Summary:\n";
    foreach ($results as $type => $count) {
        echo "   {$type}: {$count} posts\n";
    }
    echo "\n   Total: {$total_exported} posts exported\n";
    echo "   Output directory: {$base_output_dir}\n";
    echo str_repeat('=', 60) . "\n";
    
    return $results;
}

// CLI execution
if (php_sapi_name() === 'cli' && isset($argc)) {
    // Check if any arguments provided
    if ($argc === 1) {
        // No arguments - export all post types
        echo "\n💡 No arguments provided - exporting ALL post types\n";
        echo "Usage: php export-to-markdown.php [post-type] [output-directory]\n";
        echo "       php export-to-markdown.php (exports all to /content/)\n\n";
        
        export_all_post_types();
    } else {
        // Arguments provided - export specific post type
        $post_type = isset($argv[1]) ? $argv[1] : 'post';
        $output_dir = isset($argv[2]) ? $argv[2] : WP_CONTENT_DIR . '/../content/' . $post_type;
        
        export_posts_to_markdown($post_type, $output_dir);
    }
}
