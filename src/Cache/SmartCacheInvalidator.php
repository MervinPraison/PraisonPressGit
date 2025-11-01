<?php
namespace PraisonPress\Cache;

/**
 * Smart Cache Invalidator
 * Intelligently clears only the cache for affected files/posts after PR merge
 */
class SmartCacheInvalidator {
    
    /**
     * Clear cache for specific files that were changed in a PR
     * 
     * @param array $changedFiles Array of file paths that were changed
     * @return array Result with count of cleared cache entries
     */
    public static function clearForFiles($changedFiles) {
        if (empty($changedFiles)) {
            return [
                'success' => true,
                'cleared' => 0,
                'message' => 'No files to clear cache for'
            ];
        }
        
        $cleared = 0;
        $contentDir = PRAISON_CONTENT_DIR;
        
        foreach ($changedFiles as $file) {
            // Extract post type and slug from file path
            $fileInfo = self::parseFilePath($file, $contentDir);
            
            if ($fileInfo) {
                // Clear cache for this specific post
                $cleared += self::clearPostCache($fileInfo['post_type'], $fileInfo['slug']);
                
                // Clear archive cache for this post type
                $cleared += self::clearArchiveCache($fileInfo['post_type']);
            }
        }
        
        // Clear user submissions cache (so users see updated PR status)
        $cleared += self::clearUserSubmissionsCache();
        
        return [
            'success' => true,
            'cleared' => $cleared,
            'message' => "Cleared cache for {$cleared} entries"
        ];
    }
    
    /**
     * Parse file path to extract post type and slug
     * 
     * @param string $filePath Full or relative file path
     * @param string $contentDir Content directory path
     * @return array|null Array with post_type and slug, or null if invalid
     */
    private static function parseFilePath($filePath, $contentDir) {
        // Remove content directory prefix if present
        $relativePath = str_replace($contentDir . '/', '', $filePath);
        
        // Split into parts: post_type/[subdirs/]filename.md
        $parts = explode('/', $relativePath);
        
        if (count($parts) < 2) {
            return null;
        }
        
        $postType = $parts[0];
        $filename = end($parts);
        
        // Remove .md extension
        $filename = preg_replace('/\.md$/', '', $filename);
        
        // Remove date prefix if present (e.g., "2024-01-01-slug" -> "slug")
        $slug = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $filename);
        
        return [
            'post_type' => $postType,
            'slug' => $slug,
            'filename' => $filename
        ];
    }
    
    /**
     * Clear cache for a specific post
     * 
     * @param string $postType Post type
     * @param string $slug Post slug
     * @return int Number of cache entries cleared
     */
    private static function clearPostCache($postType, $slug) {
        global $wpdb;
        $cleared = 0;
        
        // Clear transient cache for this specific post
        $patterns = [
            '_transient_' . PRAISON_CACHE_GROUP . '_' . $postType . '_' . $slug . '%',
            '_transient_' . PRAISON_CACHE_GROUP . '_post_' . $slug . '%',
        ];
        
        foreach ($patterns as $pattern) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                    $pattern,
                    '_transient_timeout_' . str_replace('_transient_', '', $pattern)
                )
            );
            $cleared += $result ? $result : 0;
        }
        
        // Clear WordPress object cache if available
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($postType . '_' . $slug, PRAISON_CACHE_GROUP);
            wp_cache_delete('post_' . $slug, PRAISON_CACHE_GROUP);
        }
        
        return $cleared;
    }
    
    /**
     * Clear archive cache for a post type
     * 
     * @param string $postType Post type
     * @return int Number of cache entries cleared
     */
    private static function clearArchiveCache($postType) {
        global $wpdb;
        
        $pattern = '_transient_' . PRAISON_CACHE_GROUP . '_' . $postType . '_archive%';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $cleared = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                '_transient_timeout_' . str_replace('_transient_', '', $pattern)
            )
        );
        
        // Clear WordPress object cache
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($postType . '_archive', PRAISON_CACHE_GROUP);
        }
        
        return $cleared ? $cleared : 0;
    }
    
    /**
     * Clear user submissions cache for all users
     * (So they see updated PR status after merge)
     * 
     * @return int Number of cache entries cleared
     */
    private static function clearUserSubmissionsCache() {
        global $wpdb;
        
        $pattern = '_transient_praisonpress_user_submissions_%';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $cleared = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                '_transient_timeout_' . str_replace('_transient_', '', $pattern)
            )
        );
        
        return $cleared ? $cleared : 0;
    }
    
    /**
     * Get list of changed files from a PR
     * 
     * @param int $prNumber PR number
     * @param object $githubClient GitHub client instance
     * @return array Array of file paths
     */
    public static function getChangedFilesFromPR($prNumber, $githubClient) {
        $repoPath = get_option('praisonpress_github_repo', '');
        if (empty($repoPath)) {
            return [];
        }
        
        $repoParts = explode('/', $repoPath);
        if (count($repoParts) !== 2) {
            return [];
        }
        
        $owner = $repoParts[0];
        $repo = $repoParts[1];
        
        // Get PR files from GitHub API
        $files = $githubClient->request("/repos/{$owner}/{$repo}/pulls/{$prNumber}/files");
        
        if (!is_array($files)) {
            return [];
        }
        
        // Extract file paths
        $changedFiles = [];
        foreach ($files as $file) {
            if (isset($file['filename'])) {
                $changedFiles[] = $file['filename'];
            }
        }
        
        return $changedFiles;
    }
    
    /**
     * Clear cache after PR merge
     * This is the main method to call after merging a PR
     * 
     * @param int $prNumber PR number
     * @param object $githubClient GitHub client instance
     * @return array Result with success status and cleared count
     */
    public static function clearAfterPRMerge($prNumber, $githubClient) {
        // Get changed files from PR
        $changedFiles = self::getChangedFilesFromPR($prNumber, $githubClient);
        
        // Clear cache for those files
        return self::clearForFiles($changedFiles);
    }
}
