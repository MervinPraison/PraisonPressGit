<?php
namespace PraisonPress\Frontend;

use PraisonPress\GitHub\GitHubClient;

/**
 * My Submissions Page
 * Frontend page for users to view their submitted error reports (PRs)
 */
class MySubmissionsPage {
    
    private $githubClient;
    
    public function __construct() {
        // Load GitHub access token from WordPress options
        $accessToken = get_option('praisonpress_github_token', '');
        $this->githubClient = new GitHubClient($accessToken);
    }
    
    /**
     * Register hooks
     */
    public function register() {
        // Add shortcode for the page
        add_shortcode('praisonpress_my_submissions', [$this, 'renderPage']);
        
        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handler for merging PRs from frontend
        add_action('wp_ajax_praison_merge_pr_frontend', [$this, 'ajaxMergePR']);
    }
    
    /**
     * Enqueue CSS and JS
     */
    public function enqueueAssets() {
        // Only enqueue on pages with the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'praisonpress_my_submissions')) {
            wp_enqueue_style(
                'praisonpress-my-submissions',
                PRAISON_PLUGIN_URL . 'assets/css/my-submissions.css',
                [],
                '1.0.0'
            );
            
            wp_enqueue_script(
                'praisonpress-submissions',
                PRAISON_PLUGIN_URL . 'assets/js/submissions.js',
                ['jquery'],
                '1.0.0',
                true
            );
            
            wp_localize_script('praisonpress-submissions', 'praisonSubmissions', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('praison_merge_pr_nonce')
            ]);
        }
    }
    
    /**
     * Render the My Submissions page
     */
    public function renderPage() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="praisonpress-notice praisonpress-notice-error">
                <p>You must be logged in to view your submissions.</p>
            </div>';
        }
        
        $currentUser = wp_get_current_user();
        $userId = $currentUser->ID;
        $userName = $currentUser->display_name;
        $isAdmin = current_user_can('manage_options');
        
        // Allow admins to view all submissions or filter by user
        $viewUserId = $userId; // Default to current user
        if ($isAdmin && isset($_GET['user_id']) && !empty($_GET['user_id'])) {
            $viewUserId = intval($_GET['user_id']);
            $viewUser = get_user_by('id', $viewUserId);
            if ($viewUser) {
                $userName = $viewUser->display_name;
            }
        } elseif ($isAdmin && isset($_GET['view']) && $_GET['view'] === 'all') {
            $viewUserId = null; // View all users
            $userName = 'All Users';
        }
        
        // Check cache first (5 minute cache)
        $cacheKey = 'praisonpress_user_submissions_' . ($viewUserId ?: 'all');
        $userPRs = wp_cache_get($cacheKey, 'praisonpress');
        
        if ($userPRs !== false) {
            // Return cached data
            ob_start();
            $this->renderSubmissions($userPRs, $userName, $isAdmin, $viewUserId);
            return ob_get_clean();
        }
        
        // Get pagination parameters
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 5; // Show 5 submissions per page
        $offset = ($paged - 1) * $per_page;
        
        // Get user's submissions from database with pagination
        require_once PRAISON_PLUGIN_DIR . '/src/Database/SubmissionsTable.php';
        $submissionsTable = new \PraisonPress\Database\SubmissionsTable();
        
        if ($viewUserId === null) {
            // Admin viewing all submissions
            $totalSubmissions = $submissionsTable->getAllSubmissionsCount();
            $userSubmissions = $submissionsTable->getAllSubmissions(null, $per_page, $offset);
        } else {
            // Viewing specific user's submissions
            $totalSubmissions = $submissionsTable->getUserSubmissionsCount($viewUserId);
            $userSubmissions = $submissionsTable->getUserSubmissions($viewUserId, null, $per_page, $offset);
        }
        $totalPages = ceil($totalSubmissions / $per_page);
        
        // Get PR details from GitHub for each submission
        $repoPath = get_option('praisonpress_github_repo', '');
        $userPRs = [];
        
        if (!empty($repoPath) && !empty($userSubmissions)) {
            $repoParts = explode('/', $repoPath);
            if (count($repoParts) === 2) {
                $owner = $repoParts[0];
                $repo = $repoParts[1];
                
                foreach ($userSubmissions as $submission) {
                    // Get PR details from GitHub
                    $prResponse = $this->githubClient->getPullRequest($owner, $repo, $submission->pr_number);
                    
                    // Unwrap the response (GitHubClient wraps it in success/data)
                    $prDetails = null;
                    if (isset($prResponse['success']) && $prResponse['success'] && isset($prResponse['data'])) {
                        $prDetails = $prResponse['data'];
                    } elseif (isset($prResponse['number'])) {
                        // Already unwrapped
                        $prDetails = $prResponse;
                    }
                    
                    if ($prDetails && isset($prDetails['number'])) {
                        // Add database info to PR data
                        $prDetails['db_id'] = $submission->id;
                        $prDetails['db_post_title'] = $submission->post_title;
                        $prDetails['db_post_type'] = $submission->post_type;
                        $prDetails['db_created_at'] = $submission->created_at;
                        $userPRs[] = $prDetails;
                    } else {
                        // PR not found on GitHub, use database info
                        $userPRs[] = array(
                            'number' => $submission->pr_number,
                            'html_url' => $submission->pr_url,
                            'title' => $submission->post_title ?: 'Content Edit',
                            'state' => $submission->status,
                            'created_at' => $submission->created_at,
                            'user' => array('login' => $userName),
                            'body' => '',
                            'merged_at' => null,
                            'db_only' => true,
                            'db_post_title' => $submission->post_title,
                            'db_post_type' => $submission->post_type
                        );
                    }
                }
            }
        }
        
        // Cache the results for 5 minutes
        wp_cache_set($cacheKey, $userPRs, 'praisonpress', 300);
        
        ob_start();
        $this->renderSubmissions($userPRs, $userName, $isAdmin, $viewUserId);
        return ob_get_clean();
    }
    
    /**
     * Render submissions list
     */
    private function renderSubmissions($userPRs, $userName) {
        ?>
        <div class="praisonpress-my-submissions">
            <div class="praisonpress-submissions-header">
                <h2><?php echo $viewUserId === null ? 'All Submissions' : ($viewUserId === get_current_user_id() ? 'My Submissions' : esc_html($userName) . "'s Submissions"); ?></h2>
                <p>Track the status of content edit suggestions</p>
                
                <?php if ($isAdmin): ?>
                    <div class="praisonpress-admin-filters">
                        <a href="<?php echo esc_url(remove_query_arg(['view', 'user_id', 'paged'])); ?>" class="filter-btn <?php echo $viewUserId === get_current_user_id() ? 'active' : ''; ?>">
                            My Submissions
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('view', 'all', remove_query_arg(['user_id', 'paged']))); ?>" class="filter-btn <?php echo $viewUserId === null ? 'active' : ''; ?>">
                            All Users
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($userPRs)): ?>
                <div class="praisonpress-notice praisonpress-notice-info">
                    <p>You haven't submitted any content edits yet.</p>
                    <p>Click the "Report Error" button on any page to suggest improvements!</p>
                </div>
            <?php else: ?>
                <div class="praisonpress-submissions-list">
                    <?php foreach ($userPRs as $pr): 
                        $prNumber = $pr['number'];
                        $prTitle = esc_html($pr['title']);
                        $prState = $pr['state']; // open, closed
                        $prMerged = isset($pr['merged_at']) && $pr['merged_at'];
                        $prUrl = $pr['html_url'];
                        $prCreated = date('M j, Y', strtotime($pr['created_at']));
                        $prAuthor = isset($pr['user']['login']) ? $pr['user']['login'] : 'Unknown';
                        
                        // Determine status
                        if ($prMerged) {
                            $status = 'merged';
                            $statusLabel = 'Merged';
                            $statusClass = 'status-merged';
                        } elseif ($prState === 'closed') {
                            $status = 'closed';
                            $statusLabel = 'Closed';
                            $statusClass = 'status-closed';
                        } else {
                            $status = 'open';
                            $statusLabel = 'Pending Review';
                            $statusClass = 'status-open';
                        }
                    ?>
                        <div class="praisonpress-submission-item">
                            <div class="submission-header">
                                <h3 class="submission-title">
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="<?php echo esc_url($prUrl); ?>" target="_blank" rel="noopener">
                                            <?php echo $prTitle; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo $prTitle; ?>
                                    <?php endif; ?>
                                </h3>
                                <span class="submission-status <?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </div>
                            <div class="submission-meta">
                                <span class="submission-number">#<?php echo $prNumber; ?></span>
                                <span class="submission-date">Submitted on <?php echo $prCreated; ?></span>
                                <span class="submission-author">by <?php echo esc_html($prAuthor); ?></span>
                            </div>
                            <?php if (!empty($pr['body'])): ?>
                                <div class="submission-description">
                                    <?php echo esc_html(wp_trim_words($pr['body'], 30)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="submission-actions">
                                <a href="<?php echo esc_url($prUrl . '/files'); ?>" target="_blank" rel="noopener" class="submission-action-btn view-diff">
                                    <span class="dashicons dashicons-media-code"></span> View Diff
                                </a>
                                
                                <?php if (!empty($pr['db_post_title'])): ?>
                                    <?php 
                                    // Get post type from database (important for file-based posts)
                                    $post_type = !empty($pr['db_post_type']) ? $pr['db_post_type'] : 'any';
                                    $slug = sanitize_title($pr['db_post_title']);
                                    
                                    // For file-based posts, search by slug with specific post type
                                    $post_query = new \WP_Query([
                                        'name' => $slug,
                                        'post_type' => $post_type,
                                        'posts_per_page' => 1,
                                        'post_status' => 'any'
                                    ]);
                                    
                                    // If not found and post_type was specific, try with 'any'
                                    if (!$post_query->have_posts() && $post_type !== 'any') {
                                        $post_query = new \WP_Query([
                                            'name' => $slug,
                                            'post_type' => 'any',
                                            'posts_per_page' => 1,
                                            'post_status' => 'any'
                                        ]);
                                    }
                                    
                                    if ($post_query->have_posts()): 
                                        $post_query->the_post();
                                        $page_url = get_permalink();
                                        wp_reset_postdata();
                                    ?>
                                        <a href="<?php echo esc_url($page_url); ?>" target="_blank" class="submission-action-btn view-page">
                                            <span class="dashicons dashicons-admin-page"></span> View Page
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (current_user_can('manage_options')): ?>
                                    <?php if ($prState === 'open' && empty($pr['merged_at'])): ?>
                                        <button type="button" class="submission-action-btn approve-pr" data-pr-number="<?php echo esc_attr($prNumber); ?>" data-pr-title="<?php echo esc_attr($prTitle); ?>">
                                            <span class="dashicons dashicons-yes-alt"></span> Approve & Merge
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-pull-requests&pr=' . $prNumber)); ?>" class="submission-action-btn admin-review">
                                        <span class="dashicons dashicons-admin-tools"></span> Review in Admin
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for merging PR from frontend
     */
    public function ajaxMergePR() {
        // Verify nonce
        check_ajax_referer('praison_merge_pr_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $prNumber = isset($_POST['pr_number']) ? intval($_POST['pr_number']) : 0;
        
        if (!$prNumber) {
            wp_send_json_error(['message' => 'Invalid PR number']);
        }
        
        // Load required classes
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/PullRequestManager.php';
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/SyncManager.php';
        
        // Get repo info from config
        $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
        if (!file_exists($config_file)) {
            wp_send_json_error(['message' => 'Configuration file not found']);
        }
        
        $config = parse_ini_file($config_file, true);
        $repoUrl = isset($config['github']['repository_url']) ? $config['github']['repository_url'] : '';
        $mainBranch = isset($config['github']['main_branch']) ? $config['github']['main_branch'] : 'main';
        
        if (empty($repoUrl)) {
            wp_send_json_error(['message' => 'Repository URL not configured']);
        }
        
        // Parse repo URL
        if (!preg_match('#github\.com[:/]([^/]+)/([^/\.]+)#', $repoUrl, $matches)) {
            wp_send_json_error(['message' => 'Invalid repository URL']);
        }
        
        $owner = $matches[1];
        $repo = $matches[2];
        
        // Initialize GitHub client
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/GitHubClient.php';
        $accessToken = get_option('praisonpress_github_token', '');
        $githubClient = new \PraisonPress\GitHub\GitHubClient($accessToken);
        
        // Merge the PR via GitHub API
        $result = $githubClient->mergePullRequest($owner, $repo, $prNumber);
        
        if ($result['success']) {
            // Sync content after merge
            $syncManager = new \PraisonPress\GitHub\SyncManager($repoUrl, $mainBranch);
            $syncManager->pullFromRemote();
            
            // Clear cache
            require_once PRAISON_PLUGIN_DIR . '/src/Cache/CacheManager.php';
            $cacheManager = new \PraisonPress\Cache\CacheManager();
            $cacheManager->clearAll();
            
            wp_send_json_success([
                'message' => sprintf('Pull request #%d has been successfully merged and content has been synced!', $prNumber)
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? 'Failed to merge pull request'
            ]);
        }
    }
}
