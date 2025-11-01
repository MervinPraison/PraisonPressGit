<?php
namespace PraisonPress\GitHub;

/**
 * GitHub OAuth Handler
 * Manages OAuth authentication flow with GitHub
 */
class OAuthHandler {
    
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    
    /**
     * Constructor
     */
    public function __construct($clientId = null, $clientSecret = null) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = admin_url('admin.php?page=praisonpress-settings&action=github-callback');
    }
    
    /**
     * Get authorization URL
     * @param array $scopes OAuth scopes
     * @return string Authorization URL
     */
    public function getAuthorizationUrl($scopes = ['repo']) {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => wp_create_nonce('github_oauth'),
        ];
        
        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     * @param string $code Authorization code
     * @return array|false Access token data or false on failure
     */
    public function getAccessToken($code) {
        $response = wp_remote_post('https://github.com/login/oauth/access_token', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            return $data;
        }
        
        return false;
    }
    
    /**
     * Store access token securely
     * @param string $token Access token
     */
    public function storeAccessToken($token) {
        update_option('praisonpress_github_token', $token, false);
    }
    
    /**
     * Get stored access token
     * @return string|false Access token or false if not found
     */
    public function getStoredAccessToken() {
        return get_option('praisonpress_github_token', false);
    }
    
    /**
     * Delete stored access token
     */
    public function deleteAccessToken() {
        delete_option('praisonpress_github_token');
    }
    
    /**
     * Check if connected to GitHub
     * @return bool
     */
    public function isConnected() {
        return !empty($this->getStoredAccessToken());
    }
    
    /**
     * Start Device Flow authentication
     * @return array|false Device code data or false on failure
     */
    public function startDeviceFlow() {
        $response = wp_remote_post('https://github.com/login/device/code', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'client_id' => $this->clientId,
                'scope' => 'repo',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['device_code']) && isset($data['user_code'])) {
            return $data;
        }
        
        return false;
    }
    
    /**
     * Poll for Device Flow token
     * @param string $deviceCode Device code from startDeviceFlow
     * @return array|false Token data or false if not ready/error
     */
    public function pollDeviceFlowToken($deviceCode) {
        $response = wp_remote_post('https://github.com/login/oauth/access_token', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'client_id' => $this->clientId,
                'device_code' => $deviceCode,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for errors
        if (isset($data['error'])) {
            if ($data['error'] === 'authorization_pending') {
                return ['pending' => true];
            }
            return false;
        }
        
        if (isset($data['access_token'])) {
            return $data;
        }
        
        return false;
    }
}
