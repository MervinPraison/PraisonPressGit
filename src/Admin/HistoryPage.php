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
        <div class="wrap" style="max-width: 100%; margin-right: 0;">
            <h1>📜 Version History</h1>
            
            <?php if (isset($_GET['rollback']) && $_GET['rollback'] === 'success'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅ Rollback Successful!</strong></p>
                    <p>Content has been rolled back to the selected version.</p>
                </div>
            <?php elseif (isset($_GET['rollback']) && $_GET['rollback'] === 'error'): ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>❌ Rollback Failed</strong></p>
                    <p>Unable to rollback to the selected version. Please try again.</p>
                </div>
            <?php endif; ?>
            
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
                <div class="card" style="overflow-x: auto; max-width: none;">
                    <h2>Recent Changes (<?php echo count($history); ?>)</h2>
                    
                    <!-- Using native WordPress table styles -->
                    <table class="wp-list-table widefat striped" style="width: 100%; table-layout: auto;">
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
                                            <button type="button" 
                                                    class="button button-small praisonpress-rollback-btn" 
                                                    data-hash="<?php echo esc_attr($commit['hash']); ?>"
                                                    data-message="<?php echo esc_attr($commit['message']); ?>"
                                                    data-nonce="<?php echo esc_attr(wp_create_nonce('rollback_' . $commit['hash'])); ?>">
                                                Rollback
                                            </button>
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
        
        <!-- Rollback Confirmation Modal -->
        <div id="praisonpress-rollback-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 4px; max-width: 500px; width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
                <div style="background: #0073aa; color: white; padding: 15px 20px; border-radius: 4px 4px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 18px;">Confirm Rollback</h2>
                    <button type="button" class="praisonpress-modal-close" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                </div>
                <div style="padding: 20px;">
                    <p id="praisonpress-rollback-message" style="margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;"></p>
                    <p style="margin: 0; font-size: 13px; color: #d63638;"><strong>Warning:</strong> This will revert your content to this version. Current changes will be preserved in history.</p>
                </div>
                <div style="padding: 15px 20px; border-top: 1px solid #ddd; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="button praisonpress-modal-close">Cancel</button>
                    <button type="button" id="praisonpress-confirm-rollback" class="button button-primary">Rollback</button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var rollbackHash = '';
            var rollbackNonce = '';
            
            $('.praisonpress-rollback-btn').on('click', function() {
                var $btn = $(this);
                rollbackHash = $btn.data('hash');
                rollbackNonce = $btn.data('nonce');
                var message = $btn.data('message');
                
                $('#praisonpress-rollback-message').text('Rollback to: ' + message);
                $('#praisonpress-rollback-modal').css('display', 'flex').hide().fadeIn(200);
            });
            
            $('.praisonpress-modal-close').on('click', function() {
                $('#praisonpress-rollback-modal').fadeOut(200);
            });
            
            $('#praisonpress-rollback-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).fadeOut(200);
                }
            });
            
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#praisonpress-rollback-modal').is(':visible')) {
                    $('#praisonpress-rollback-modal').fadeOut(200);
                }
            });
            
            $('#praisonpress-confirm-rollback').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Rolling back...');
                
                var url = '<?php echo esc_url(admin_url('admin-post.php')); ?>?action=praison_rollback&hash=' + rollbackHash + '&_wpnonce=' + rollbackNonce;
                window.location.href = url;
            });
        });
        </script>
        
        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                width: 100%;
                box-sizing: border-box;
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
                <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                    <div style="font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto;">
                        <?php
                        $lines = explode("\n", $details['diff']);
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
                            } elseif (strpos($line, 'diff --git') === 0 || strpos($line, 'index ') === 0) {
                                $lineStyle = 'background: #f6f8fa; color: #666;';
                                $lineClass = 'diff-meta';
                            } else {
                                $lineStyle = 'background: #fff; color: #24292f;';
                                $lineClass = 'diff-context';
                            }
                        ?>
                            <div class="<?php echo esc_attr($lineClass); ?>" style="<?php echo esc_attr($lineStyle); ?> padding: 2px 10px; border-left: 3px solid transparent;"><?php echo esc_html($line); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                width: 100%;
                box-sizing: border-box;
            }
            .card h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }
}
