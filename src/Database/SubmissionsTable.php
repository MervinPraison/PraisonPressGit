<?php
namespace PraisonPress\Database;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Submissions Table Manager
 * Handles database operations for PR submissions tracking
 */
class SubmissionsTable {
    
    private $tableName;
    
    public function __construct() {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'praisonpress_submissions';
    }
    
    /**
     * Create the submissions table
     */
    public function createTable() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            pr_number int(11) NOT NULL,
            pr_url varchar(500) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            post_title varchar(500) DEFAULT NULL,
            status varchar(20) DEFAULT 'open',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY pr_number (pr_number),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Save a new submission
     * 
     * @param int $userId WordPress user ID
     * @param int $prNumber GitHub PR number
     * @param string $prUrl GitHub PR URL
     * @param int $postId Related post ID (optional)
     * @param string $postTitle Post title (optional)
     * @return int|false Insert ID or false on failure
     */
    public function saveSubmission($userId, $prNumber, $prUrl, $postId = null, $postTitle = null) {
        global $wpdb;
        
        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $this->tableName,
            array(
                'user_id' => $userId,
                'pr_number' => $prNumber,
                'pr_url' => $prUrl,
                'post_id' => $postId,
                'post_title' => $postTitle,
                'status' => 'open'
            ),
            array('%d', '%d', '%s', '%d', '%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get submissions for a specific user
     * 
     * @param int $userId WordPress user ID
     * @param string $status Filter by status (optional)
     * @param int $limit Limit number of results (default: 50)
     * @param int $offset Offset for pagination (default: 0)
     * @return array Array of submission objects
     */
    public function getUserSubmissions($userId, $status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'praisonpress_submissions';
        
        if ($status) {
            $sql = $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $userId,
                $status,
                $limit,
                $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $userId,
                $limit,
                $offset
            );
        }
        
        return $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
    
    /**
     * Get total count of user submissions
     * 
     * @param int $userId WordPress user ID
     * @param string $status Filter by status (optional)
     * @return int Total count
     */
    public function getUserSubmissionsCount($userId, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'praisonpress_submissions';
        
        if ($status) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $userId,
                $status
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $userId
            );
        }
        
        return (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
    
    /**
     * Get all submissions (for admin view)
     * 
     * @param string $status Filter by status (optional)
     * @param int $limit Limit number of results
     * @param int $offset Offset for pagination
     * @return array Array of submission objects
     */
    public function getAllSubmissions($status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'praisonpress_submissions';
        
        if ($status) {
            $sql = $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $status,
                $limit,
                $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $limit,
                $offset
            );
        }
        
        return $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
    
    /**
     * Get total count of all submissions (for admin view)
     * 
     * @param string $status Filter by status (optional)
     * @return int Total count
     */
    public function getAllSubmissionsCount($status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'praisonpress_submissions';
        
        if ($status) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $status
            );
            return (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        
        // No parameters, safe to use directly
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
    
    /**
     * Update submission status
     * 
     * @param int $prNumber GitHub PR number
     * @param string $status New status (open, merged, closed)
     * @return bool Success status
     */
    public function updateStatus($prNumber, $status) {
        global $wpdb;
        
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->tableName,
            array('status' => $status),
            array('pr_number' => $prNumber),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get submission by PR number
     * 
     * @param int $prNumber GitHub PR number
     * @return object|null Submission object or null
     */
    public function getSubmissionByPR($prNumber) {
        global $wpdb;
        $table = $wpdb->prefix . 'praisonpress_submissions';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE pr_number = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $prNumber
        );
        return $wpdb->get_row($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
    
    /**
     * Delete old submissions (cleanup)
     * 
     * @param int $days Delete submissions older than X days
     * @return int Number of rows deleted
     */
    public function deleteOldSubmissions($days = 90) {
        global $wpdb;
        $table = $wpdb->prefix . 'praisonpress_submissions';
        
        $sql = $wpdb->prepare(
            "DELETE FROM `{$table}` WHERE status IN ('merged', 'closed') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $days
        );
        
        return $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
}
