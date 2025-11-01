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
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }
    
    /**
     * Enqueue CSS
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
        
        // Check cache first (5 minute cache)
        $cacheKey = 'praisonpress_user_submissions_' . $userId;
        $userPRs = wp_cache_get($cacheKey, 'praisonpress');
        
        if ($userPRs !== false) {
            // Return cached data
            ob_start();
            $this->renderSubmissions($userPRs, $userName);
            return ob_get_clean();
        }
        
        // Get pagination parameters
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 5; // Show 5 submissions per page
        $offset = ($paged - 1) * $per_page;
        
        // Get user's submissions from database with pagination
        require_once PRAISON_PLUGIN_DIR . '/src/Database/SubmissionsTable.php';
        $submissionsTable = new \PraisonPress\Database\SubmissionsTable();
        $totalSubmissions = $submissionsTable->getUserSubmissionsCount($userId);
        $userSubmissions = $submissionsTable->getUserSubmissions($userId, null, $per_page, $offset);
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
                            'db_only' => true
                        );
                    }
                }
            }
        }
        
        // Cache the results for 5 minutes
        wp_cache_set($cacheKey, $userPRs, 'praisonpress', 300);
        
        ob_start();
        $this->renderSubmissions($userPRs, $userName);
        return ob_get_clean();
    }
    
    /**
     * Render submissions list
     */
    private function renderSubmissions($userPRs, $userName) {
        ?>
        <div class="praisonpress-my-submissions">
            <div class="praisonpress-submissions-header">
                <h2>My Submissions</h2>
                <p>Track the status of your content edit suggestions</p>
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
                                    // Try to find the post by title
                                    $post_query = new \WP_Query([
                                        'title' => $pr['db_post_title'],
                                        'post_type' => 'any',
                                        'posts_per_page' => 1,
                                        'post_status' => 'any'
                                    ]);
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
}
