<?php
namespace PraisonPress\Admin;

use PraisonPress\Git\GitManager;

/**
 * Version History Admin Page
 */
class HistoryPage {
    
    private $gitManager;
    
    public function __construct() {
        $this->gitManager = new GitManager();
    }
    
    /**
     * Render history page
     */
    public function render() {
        // Check if viewing a specific commit
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['hash'])) {
            $this->renderCommitDetails(sanitize_text_field($_GET['hash']));
            return;
        }
        
        $history = $this->gitManager->getHistory(50);
        $status = $this->gitManager->getStatus();
        
        ?>
        <div class="wrap">
            <h1>📜 Version History</h1>
            
            <?php if (!$status['available']): ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ Git Not Available</strong></p>
                    <p>Git is not installed or not accessible. Version control features are disabled.</p>
                    <p>To enable version history, install Git: <code>brew install git</code> (macOS) or <code>apt-get install git</code> (Linux)</p>
                </div>
            <?php elseif (!$status['repo']): ?>
                <div class="notice notice-info">
                    <p><strong>ℹ️ Git Repository Not Initialized</strong></p>
                    <p>Initializing git repository at: <code><?php echo esc_html($status['path']); ?></code></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ Version Control Active</strong></p>
                    <p>Git repository: <code><?php echo esc_html($status['path']); ?></code></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($history)): ?>
                <div class="card">
                    <h2>No History Yet</h2>
                    <p>Version history will appear here once you start making changes to your content files.</p>
                    <p>Changes are automatically tracked when files are modified.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>Recent Changes (<?php echo count($history); ?>)</h2>
                    
                    <!-- Using native WordPress table styles -->
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Timeline</th>
                                <th style="width: 150px;">Date</th>
                                <th style="width: 120px;">Author</th>
                                <th>Message</th>
                                <th style="width: 100px;">Commit</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $index => $commit): ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <div style="width: 20px; height: 20px; background: <?php echo $index === 0 ? '#46b450' : '#0073aa'; ?>; border-radius: 50%; margin: 0 auto;"></div>
                                        <?php if ($index < count($history) - 1): ?>
                                            <div style="width: 2px; height: 30px; background: #ddd; margin: 0 auto;"></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($commit['date']); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html( human_time_diff( $commit['timestamp'] ) . ' ago' ); ?></small>
                                    </td>
                                    <td>
                                        <?php echo get_avatar($commit['email'], 32); ?>
                                        <strong><?php echo esc_html($commit['author']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($commit['message']); ?></strong>
                                    </td>
                                    <td>
                                        <code style="background: #f0f0f1; padding: 3px 6px; border-radius: 3px;">
                                            <?php echo esc_html( substr( $commit['hash'], 0, 7 ) ); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=praisonpress-history&action=view&hash=' . $commit['hash'] ) ); ?>" 
                                           class="button button-small">View</a>
                                        <?php if ($index > 0): ?>
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=praison_rollback&hash=' . $commit['hash'] ), 'praison_rollback' ) ); ?>" 
                                               class="button button-small"
                                               onclick="return confirm('Rollback to this version?');">Rollback</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="card" style="margin-top: 20px;">
                <h2>How Version Control Works</h2>
                <ul>
                    <li>🔄 <strong>Automatic Tracking:</strong> Changes to .md files are tracked automatically</li>
                    <li>📝 <strong>Commit History:</strong> Every change creates a commit with timestamp and author</li>
                    <li>↩️ <strong>Rollback:</strong> Restore any file to a previous version</li>
                    <li>📊 <strong>Diff View:</strong> See exactly what changed between versions</li>
                    <li>🌳 <strong>Git-Based:</strong> Standard Git repository - works with GitHub, GitLab, etc.</li>
                </ul>
            </div>
        </div>
        
        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .card h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Render commit details page
     */
    private function renderCommitDetails($hash) {
        $details = $this->gitManager->getCommitDetails($hash);
        
        if (empty($details)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Commit not found.</p></div></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>📜 Commit Details</h1>
            
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=praisonpress-history')); ?>" class="button">← Back to History</a></p>
            
            <div class="card">
                <h2>Commit Information</h2>
                <table class="form-table">
                    <tr>
                        <th>Commit Hash:</th>
                        <td><code><?php echo esc_html($details['hash']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Author:</th>
                        <td><?php echo esc_html($details['author']); ?> &lt;<?php echo esc_html($details['email']); ?>&gt;</td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td><?php echo esc_html($details['date']); ?></td>
                    </tr>
                    <tr>
                        <th>Message:</th>
                        <td><strong><?php echo esc_html($details['message']); ?></strong></td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Files Changed</h2>
                <?php if (!empty($details['files'])): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($details['files'] as $file): ?>
                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                                <span class="dashicons dashicons-media-document" style="color: #0073aa;"></span>
                                <code><?php echo esc_html($file); ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No files changed.</p>
                <?php endif; ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Diff</h2>
                <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; border: 1px solid #ddd; border-radius: 3px;"><?php echo esc_html($details['diff']); ?></pre>
            </div>
        </div>
        
        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .card h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }
}
