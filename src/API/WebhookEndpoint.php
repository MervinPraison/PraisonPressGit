<?php
namespace PraisonPress\API;

use PraisonPress\GitHub\SyncManager;

/**
 * GitHub Webhook Endpoint
 * Handles incoming webhook events from GitHub
 */
class WebhookEndpoint {
    
    /**
     * Register webhook endpoint
     */
    public function register() {
        add_action('rest_api_init', [$this, 'registerRoute']);
    }
    
    /**
     * Register REST API route
     */
    public function registerRoute() {
        register_rest_route('praisonpress/v1', '/webhook/github', [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => [$this, 'verifyWebhook'],
        ]);
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhook($request) {
        // Get webhook secret from config
        $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
        if (!file_exists($config_file)) {
            return false;
        }
        
        $config = parse_ini_file($config_file, true);
        $webhookSecret = isset($config['github']['webhook_secret']) ? $config['github']['webhook_secret'] : '';
        
        // If no secret configured, allow (for testing)
        if (empty($webhookSecret)) {
            return true;
        }
        
        // Verify GitHub signature
        $signature = $request->get_header('X-Hub-Signature-256');
        if (empty($signature)) {
            return false;
        }
        
        $payload = $request->get_body();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Handle webhook event
     */
    public function handleWebhook($request) {
        $event = $request->get_header('X-GitHub-Event');
        $payload = $request->get_json_params();
        
        // Log webhook event
        error_log('PraisonPress: Received GitHub webhook event: ' . $event);
        
        // Handle push event
        if ($event === 'push') {
            return $this->handlePushEvent($payload);
        }
        
        // Handle pull request event
        if ($event === 'pull_request') {
            return $this->handlePullRequestEvent($payload);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Event received but not handled: ' . $event,
        ], 200);
    }
    
    /**
     * Handle push event
     */
    private function handlePushEvent($payload) {
        // Get repository URL and branch from config
        $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
        if (!file_exists($config_file)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Configuration not found',
            ], 500);
        }
        
        $config = parse_ini_file($config_file, true);
        $repoUrl = isset($config['github']['repository_url']) ? $config['github']['repository_url'] : '';
        $mainBranch = isset($config['github']['main_branch']) ? $config['github']['main_branch'] : 'main';
        
        // Initialize sync manager
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/SyncManager.php';
        require_once PRAISON_PLUGIN_DIR . '/src/Git/GitManager.php';
        
        $syncManager = new SyncManager($repoUrl, $mainBranch);
        
        // Handle push
        $result = $syncManager->handleWebhookPush($payload);
        
        if ($result['success']) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => $result['message'],
            ], 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'message' => $result['message'],
        ], 500);
    }
    
    /**
     * Handle pull request event
     */
    private function handlePullRequestEvent($payload) {
        // Store PR information for admin review
        $action = isset($payload['action']) ? $payload['action'] : '';
        $prNumber = isset($payload['pull_request']['number']) ? $payload['pull_request']['number'] : 0;
        
        // Log for now (will implement PR review UI later)
        error_log('PraisonPress: Pull request ' . $action . ' #' . $prNumber);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Pull request event received',
        ], 200);
    }
}
