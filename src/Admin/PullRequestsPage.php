<?php
namespace PraisonPress\Admin;

/**
 * Pull Requests Admin Page
 * Displays and manages GitHub pull requests from WordPress admin
 */
class PullRequestsPage {
    
    private $githubClient;
    private $repoOwner;
    private $repoName;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load config
        $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
        if (file_exists($config_file)) {
            $config = parse_ini_file($config_file, true);
            $repoUrl = isset($config['github']['repository_url']) ? $config['github']['repository_url'] : '';
            $this->parseRepoUrl($repoUrl);
        }
        
        // Initialize GitHub client
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/GitHubClient.php';
        $accessToken = get_option('praisonpress_github_token', '');
        $this->githubClient = new \PraisonPress\GitHub\GitHubClient($accessToken);
    }
    
    /**
     * Parse repository URL
     */
    private function parseRepoUrl($url) {
        if (preg_match('#github\.com[:/]([^/]+)/([^/\.]+)#', $url, $matches)) {
            $this->repoOwner = $matches[1];
            $this->repoName = $matches[2];
        }
    }
    
    /**
     * Register admin page
     */
    public function register() {
        add_action('admin_menu', [$this, 'addAdminMenu'], 25);
        add_action('admin_post_praisonpress_merge_pr', [$this, 'handleMergePR']);
        add_action('admin_post_praisonpress_close_pr', [$this, 'handleClosePR']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_submenu_page(
            'praisonpress',
            'Pull Requests',
            'Pull Requests',
            'manage_options',
            'praisonpress-pull-requests',
            [$this, 'renderPage']
        );
    }
    
    /**
     * Render page
     */
    public function renderPage() {
        // Check if viewing single PR
        if (isset($_GET['pr']) && !empty($_GET['pr'])) {
            $this->renderPRDetail(intval($_GET['pr']));
            return;
        }
        
        // List all PRs
        $this->renderPRList();
    }
    
    /**
     * Render PR list
     */
    private function renderPRList() {
        // Get current state filter
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : 'open';
        if (!in_array($state, ['open', 'closed', 'all'])) {
            $state = 'open';
        }
        
        // Get pull requests
        $prs = $this->getPullRequests($state);
        
        // Debug: Show raw response
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo '<div class="notice notice-info"><pre>';
            echo 'Repository: ' . esc_html($this->repoOwner . '/' . $this->repoName) . "\n";
            echo 'Response: ' . esc_html(print_r($prs, true));
            echo '</pre></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>Pull Requests</h1>
            
            <!-- State Filter Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=praisonpress-pull-requests&state=open" class="nav-tab <?php echo $state === 'open' ? 'nav-tab-active' : ''; ?>">
                    Open
                </a>
                <a href="?page=praisonpress-pull-requests&state=closed" class="nav-tab <?php echo $state === 'closed' ? 'nav-tab-active' : ''; ?>">
                    Closed
                </a>
                <a href="?page=praisonpress-pull-requests&state=all" class="nav-tab <?php echo $state === 'all' ? 'nav-tab-active' : ''; ?>">
                    All
                </a>
            </h2>
            
            <?php if (empty($this->repoOwner) || empty($this->repoName)): ?>
                <div class="notice notice-error">
                    <p>GitHub repository not configured. Please configure in <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-settings')); ?>">Settings</a>.</p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <?php if (!$this->githubClient->isAuthenticated()): ?>
                <div class="notice notice-error">
                    <p>GitHub not connected. Please connect in <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-settings')); ?>">Settings</a>.</p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <?php if (isset($prs['error'])): ?>
                <div class="notice notice-error">
                    <p>Error loading pull requests: <?php echo esc_html($prs['error']); ?></p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <?php if (empty($prs) || !is_array($prs)): ?>
                <div class="notice notice-info">
                    <p>No <?php echo esc_html($state); ?> pull requests.</p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">PR #</th>
                        <th>Title</th>
                        <th style="width: 120px;">Author</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 150px;">Created</th>
                        <th style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prs as $pr): ?>
                        <?php
                        // Safely get values with defaults
                        $prNumber = isset($pr['number']) ? $pr['number'] : '?';
                        $prTitle = isset($pr['title']) ? $pr['title'] : 'Untitled';
                        $prAuthor = isset($pr['user']['login']) ? $pr['user']['login'] : 'Unknown';
                        $prMergeable = isset($pr['mergeable']) ? $pr['mergeable'] : null;
                        $prCreated = isset($pr['created_at']) ? $pr['created_at'] : '';
                        
                        // Determine status badge
                        $prState = isset($pr['state']) ? $pr['state'] : 'open';
                        $merged = isset($pr['merged']) && $pr['merged'];
                        
                        if ($merged) {
                            $statusBadge = '<span style="background: #8250df; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">✓ Merged</span>';
                        } elseif ($prState === 'closed') {
                            $statusBadge = '<span style="background: #d73a49; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">✗ Closed</span>';
                        } else {
                            $statusBadge = '<span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">● Open</span>';
                        }
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($prNumber); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-pull-requests&pr=' . $prNumber)); ?>">
                                    <?php echo esc_html($prTitle); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($prAuthor); ?></td>
                            <td>
                                <?php if ($prMergeable === true): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> Mergeable
                                <?php elseif ($prMergeable === false): ?>
                                    <span class="dashicons dashicons-warning" style="color: #dba617;"></span> Conflicts
                                <?php else: ?>
                                    <span class="dashicons dashicons-info" style="color: #72aee6;"></span> Checking...
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($prCreated) {
                                    echo esc_html(human_time_diff(strtotime($prCreated), current_time('timestamp')));
                                    echo ' ago';
                                } else {
                                    echo 'Unknown';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-pull-requests&pr=' . $pr['number'])); ?>" class="button">
                                    View Details
                                </a>
                                <a href="<?php echo esc_url($pr['html_url']); ?>" class="button" target="_blank">
                                    View on GitHub
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render PR detail
     */
    private function renderPRDetail($prNumber) {
        // Get PR details
        $pr = $this->getPullRequest($prNumber);
        
        if (isset($pr['error'])) {
            ?>
            <div class="wrap">
                <h1>Pull Request #<?php echo esc_html($prNumber); ?></h1>
                <div class="notice notice-error">
                    <p>Error loading pull request: <?php echo esc_html($pr['error']); ?></p>
                </div>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-pull-requests')); ?>">&larr; Back to Pull Requests</a></p>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Pull Request #<?php echo esc_html($pr['number']); ?>: <?php echo esc_html($pr['title']); ?></h1>
            
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-pull-requests')); ?>">&larr; Back to Pull Requests</a></p>
            
            <div class="card" style="max-width: 100%;">
                <h2>Details</h2>
                <table class="form-table">
                    <tr>
                        <th>Author:</th>
                        <td><?php echo esc_html($pr['user']['login']); ?></td>
                    </tr>
                    <tr>
                        <th>Created:</th>
                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($pr['created_at']))); ?></td>
                    </tr>
                    <tr>
                        <th>Branch:</th>
                        <td><code><?php echo esc_html($pr['head']['ref']); ?></code> → <code><?php echo esc_html($pr['base']['ref']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php if ($pr['mergeable']): ?>
                                <span style="color: #00a32a;"><span class="dashicons dashicons-yes-alt"></span> Ready to merge</span>
                            <?php else: ?>
                                <span style="color: #dba617;"><span class="dashicons dashicons-warning"></span> Has conflicts</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Changes:</th>
                        <td>
                            <span style="color: #00a32a;">+<?php echo esc_html($pr['additions']); ?></span> 
                            <span style="color: #d63638;">-<?php echo esc_html($pr['deletions']); ?></span>
                            (<?php echo esc_html($pr['changed_files']); ?> file<?php echo $pr['changed_files'] !== 1 ? 's' : ''; ?>)
                        </td>
                    </tr>
                </table>
                
                <?php if (!empty($pr['body'])): ?>
                    <h3>Description</h3>
                    <div style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
                        <?php echo nl2br(esc_html($pr['body'])); ?>
                    </div>
                <?php endif; ?>
                
                <h3>Actions</h3>
                <p>
                    <?php if ($pr['mergeable']): ?>
                        <button type="button"
                                class="button button-primary praisonpress-merge-pr-btn"
                                data-pr-number="<?php echo esc_attr($prNumber); ?>"
                                data-pr-title="<?php echo esc_attr($pr['title']); ?>"
                                data-merge-url="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=praisonpress_merge_pr&pr=' . $prNumber), 'merge_pr_' . $prNumber)); ?>">
                            <span class="dashicons dashicons-yes-alt"></span> Merge Pull Request
                        </button>
                    <?php else: ?>
                        <button class="button button-primary" disabled>
                            <span class="dashicons dashicons-warning"></span> Cannot Merge (Has Conflicts)
                        </button>
                    <?php endif; ?>
                    
                    <button type="button"
                            class="button praisonpress-close-pr-btn"
                            data-pr-number="<?php echo esc_attr($prNumber); ?>"
                            data-pr-title="<?php echo esc_attr($pr['title']); ?>"
                            data-close-url="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=praisonpress_close_pr&pr=' . $prNumber), 'close_pr_' . $prNumber)); ?>">
                        <span class="dashicons dashicons-dismiss"></span> Close Pull Request
                    </button>
                    
                    <a href="<?php echo esc_url($pr['html_url']); ?>" class="button" target="_blank">
                        <span class="dashicons dashicons-external"></span> View on GitHub
                    </a>
                </p>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Files Changed</h2>
                <?php
                $files = $this->getPRFiles($prNumber);
                if (isset($files['error'])):
                ?>
                    <p>Error loading files: <?php echo esc_html($files['error']); ?></p>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <div class="praisonpress-file-diff" style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px;">
                            <div class="file-header" style="background: #f6f8fa; padding: 10px 15px; border-bottom: 1px solid #ddd;">
                                <strong><?php echo esc_html($file['filename']); ?></strong>
                                <span style="margin-left: 15px; color: #00a32a;">+<?php echo esc_html($file['additions']); ?></span>
                                <span style="color: #d63638;">-<?php echo esc_html($file['deletions']); ?></span>
                                <span style="margin-left: 15px; color: #666;"><?php echo esc_html($file['status']); ?></span>
                            </div>
                            <?php if (isset($file['patch'])): ?>
                                <div class="file-diff" style="background: #fff; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto;">
                                    <?php
                                    $lines = explode("\n", $file['patch']);
                                    foreach ($lines as $line):
                                        $lineStyle = '';
                                        $lineClass = '';
                                        
                                        if (strpos($line, '+') === 0 && strpos($line, '+++') !== 0) {
                                            $lineStyle = 'background: #e6ffed; color: #24292f;';
                                            $lineClass = 'diff-add';
                                        } elseif (strpos($line, '-') === 0 && strpos($line, '---') !== 0) {
                                            $lineStyle = 'background: #ffebe9; color: #24292f;';
                                            $lineClass = 'diff-remove';
                                        } elseif (strpos($line, '@@') === 0) {
                                            $lineStyle = 'background: #f0f8ff; color: #0969da;';
                                            $lineClass = 'diff-header';
                                        } else {
                                            $lineStyle = 'background: #fff; color: #24292f;';
                                            $lineClass = 'diff-context';
                                        }
                                    ?>
                                        <div class="<?php echo esc_attr($lineClass); ?>" style="<?php echo esc_attr($lineStyle); ?> padding: 2px 10px; border-left: 3px solid transparent;"><?php echo esc_html($line); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="padding: 15px; color: #666; font-style: italic;">
                                    No diff available for this file.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Confirmation Modal -->
        <div id="praisonpress-confirm-modal" style="display: none;">
            <div class="praisonpress-modal-overlay"></div>
            <div class="praisonpress-modal-dialog">
                <div class="praisonpress-modal-content">
                    <div class="praisonpress-modal-header">
                        <h2 id="praisonpress-modal-title">Confirm Action</h2>
                        <button type="button" class="praisonpress-modal-close">&times;</button>
                    </div>
                    <div class="praisonpress-modal-body">
                        <p id="praisonpress-modal-message"></p>
                    </div>
                    <div class="praisonpress-modal-footer">
                        <button type="button" class="button praisonpress-modal-cancel">Cancel</button>
                        <button type="button" class="button button-primary praisonpress-modal-confirm">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .praisonpress-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
            }
            .praisonpress-modal-dialog {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                border-radius: 4px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                z-index: 100001;
                max-width: 500px;
                width: 90%;
            }
            .praisonpress-modal-header {
                padding: 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .praisonpress-modal-header h2 {
                margin: 0;
                font-size: 18px;
            }
            .praisonpress-modal-close {
                background: none;
                border: none;
                font-size: 28px;
                line-height: 1;
                cursor: pointer;
                color: #666;
            }
            .praisonpress-modal-close:hover {
                color: #000;
            }
            .praisonpress-modal-body {
                padding: 20px;
            }
            .praisonpress-modal-body p {
                margin: 0;
                font-size: 14px;
                line-height: 1.6;
            }
            .praisonpress-modal-footer {
                padding: 15px 20px;
                border-top: 1px solid #ddd;
                text-align: right;
            }
            .praisonpress-modal-footer .button {
                margin-left: 10px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var confirmCallback = null;
            
            // Show modal
            function showModal(title, message, callback) {
                $('#praisonpress-modal-title').text(title);
                $('#praisonpress-modal-message').text(message);
                confirmCallback = callback;
                $('#praisonpress-confirm-modal').fadeIn(200);
            }
            
            // Hide modal
            function hideModal() {
                $('#praisonpress-confirm-modal').fadeOut(200);
                confirmCallback = null;
            }
            
            // Merge PR button
            $('.praisonpress-merge-pr-btn').on('click', function() {
                var $btn = $(this);
                var prNumber = $btn.data('pr-number');
                var prTitle = $btn.data('pr-title');
                var mergeUrl = $btn.data('merge-url');
                
                showModal(
                    'Merge Pull Request',
                    'Are you sure you want to merge PR #' + prNumber + ': "' + prTitle + '"? This will merge the changes into the main branch and sync the content.',
                    function() {
                        window.location.href = mergeUrl;
                    }
                );
            });
            
            // Close PR button
            $('.praisonpress-close-pr-btn').on('click', function() {
                var $btn = $(this);
                var prNumber = $btn.data('pr-number');
                var prTitle = $btn.data('pr-title');
                var closeUrl = $btn.data('close-url');
                
                showModal(
                    'Close Pull Request',
                    'Are you sure you want to close PR #' + prNumber + ': "' + prTitle + '"? This will reject the changes without merging.',
                    function() {
                        window.location.href = closeUrl;
                    }
                );
            });
            
            // Confirm button
            $('.praisonpress-modal-confirm').on('click', function() {
                if (confirmCallback) {
                    confirmCallback();
                }
                hideModal();
            });
            
            // Cancel button
            $('.praisonpress-modal-cancel, .praisonpress-modal-close').on('click', function() {
                hideModal();
            });
            
            // Close on overlay click
            $('.praisonpress-modal-overlay').on('click', function() {
                hideModal();
            });
            
            // Close on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#praisonpress-confirm-modal').is(':visible')) {
                    hideModal();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get pull requests
     */
    private function getPullRequests($state = 'open') {
        if (empty($this->repoOwner) || empty($this->repoName)) {
            return ['error' => 'Repository not configured'];
        }
        
        $endpoint = '/repos/' . $this->repoOwner . '/' . $this->repoName . '/pulls?state=' . $state;
        $response = $this->githubClient->get($endpoint);
        
        // Debug: Log the response
        error_log('PR List Response: ' . print_r($response, true));
        
        // Unwrap the response (GitHubClient wraps it in success/data)
        if (isset($response['success']) && $response['success'] && isset($response['data'])) {
            return $response['data'];
        }
        
        // If there's an error, return it
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return $response;
    }
    
    /**
     * Get single pull request
     */
    private function getPullRequest($prNumber) {
        if (empty($this->repoOwner) || empty($this->repoName)) {
            return ['error' => 'Repository not configured'];
        }
        
        $endpoint = '/repos/' . $this->repoOwner . '/' . $this->repoName . '/pulls/' . $prNumber;
        $response = $this->githubClient->get($endpoint);
        
        // Unwrap the response
        if (isset($response['success']) && $response['success'] && isset($response['data'])) {
            return $response['data'];
        }
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return $response;
    }
    
    /**
     * Get PR files
     */
    private function getPRFiles($prNumber) {
        if (empty($this->repoOwner) || empty($this->repoName)) {
            return ['error' => 'Repository not configured'];
        }
        
        $endpoint = '/repos/' . $this->repoOwner . '/' . $this->repoName . '/pulls/' . $prNumber . '/files';
        $response = $this->githubClient->get($endpoint);
        
        // Unwrap the response
        if (isset($response['success']) && $response['success'] && isset($response['data'])) {
            return $response['data'];
        }
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return $response;
    }
    
    /**
     * Handle merge PR
     */
    public function handleMergePR() {
        $prNumber = isset($_GET['pr']) ? intval($_GET['pr']) : 0;
        
        if (!$prNumber || !check_admin_referer('merge_pr_' . $prNumber)) {
            wp_die('Invalid request');
        }
        
        // Merge PR
        $endpoint = '/repos/' . $this->repoOwner . '/' . $this->repoName . '/pulls/' . $prNumber . '/merge';
        $result = $this->githubClient->put($endpoint, [
            'commit_title' => 'Merge pull request #' . $prNumber,
            'merge_method' => 'merge',
        ]);
        
        // Check if merge was successful (GitHub API returns 'merged' key)
        $merged = (isset($result['success']) && $result['success'] && isset($result['data']['merged']) && $result['data']['merged'])
                  || (isset($result['merged']) && $result['merged']);
        
        if ($merged) {
            // Auto-sync after successful merge
            $this->triggerAutoSync();
            
            wp_redirect(add_query_arg([
                'page' => 'praisonpress-pull-requests',
                'merged' => '1',
            ], admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg([
            'page' => 'praisonpress-pull-requests',
            'pr' => $prNumber,
            'error' => 'merge_failed',
        ], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Handle close PR
     */
    public function handleClosePR() {
        $prNumber = isset($_GET['pr']) ? intval($_GET['pr']) : 0;
        
        if (!$prNumber || !check_admin_referer('close_pr_' . $prNumber)) {
            wp_die('Invalid request');
        }
        
        // Close PR
        $endpoint = '/repos/' . $this->repoOwner . '/' . $this->repoName . '/pulls/' . $prNumber;
        $result = $this->githubClient->patch($endpoint, [
            'state' => 'closed',
        ]);
        
        if (isset($result['success']) && $result['success']) {
            // Auto-sync after successful close
            $this->triggerAutoSync();
            
            wp_send_json_success([
                'message' => 'Pull request closed successfully! Content synced from GitHub.',
                'redirect' => admin_url('admin.php?page=praisonpress-pull-requests')
            ]);
        } else {
            wp_redirect(add_query_arg([
                'page' => 'praisonpress-pull-requests',
                'pr' => $prNumber,
                'error' => 'close_failed',
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Trigger auto-sync after PR merge
     */
    private function triggerAutoSync() {
        try {
            // Load SyncManager
            require_once PRAISON_PLUGIN_DIR . '/src/GitHub/SyncManager.php';
            require_once PRAISON_PLUGIN_DIR . '/src/Git/GitManager.php';
            
            $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
            if (file_exists($config_file)) {
                $config = parse_ini_file($config_file, true);
                $repoUrl = isset($config['github']['repository_url']) ? $config['github']['repository_url'] : '';
                $mainBranch = isset($config['github']['main_branch']) ? $config['github']['main_branch'] : 'main';
                
                if ($repoUrl) {
                    $syncManager = new \PraisonPress\GitHub\SyncManager($repoUrl, $mainBranch);
                    $syncManager->setupRemote();
                    $result = $syncManager->pullFromRemote();
                    
                    if ($result) {
                        error_log('PraisonPress: Auto-sync successful after PR merge');
                        return true;
                    } else {
                        error_log('PraisonPress: Auto-sync failed');
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('PraisonPress: Auto-sync exception: ' . $e->getMessage());
        }
        
        return false;
    }
}
