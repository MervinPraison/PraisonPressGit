<?php
namespace PraisonPress\GitHub;

/**
 * GitHub API Client
 * Handles authentication and API requests to GitHub
 */
class GitHubClient {
    
    private $accessToken;
    private $apiBase = 'https://api.github.com';
    
    /**
     * Constructor
     * @param string $accessToken GitHub OAuth access token
     */
    public function __construct($accessToken = null) {
        $this->accessToken = $accessToken;
    }
    
    /**
     * Set access token
     */
    public function setAccessToken($token) {
        $this->accessToken = $token;
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->apiBase . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'PraisonPress-WordPress-Plugin',
            ],
            'timeout' => 30,
        ];
        
        // Add authorization if token is available
        if ($this->accessToken) {
            $args['headers']['Authorization'] = 'token ' . $this->accessToken;
        }
        
        // Add body for POST/PUT/PATCH
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
            $args['headers']['Content-Type'] = 'application/json';
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data' => $data,
            ];
        }
        
        return [
            'success' => false,
            'error' => isset($data['message']) ? $data['message'] : 'Unknown error',
            'code' => $code,
        ];
    }
    
    /**
     * Test connection to GitHub
     */
    public function testConnection() {
        return $this->request('/user');
    }
    
    /**
     * Get repository information
     * @param string $owner Repository owner
     * @param string $repo Repository name
     */
    public function getRepository($owner, $repo) {
        return $this->request("/repos/{$owner}/{$repo}");
    }
    
    /**
     * List pull requests
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $state State filter (open, closed, all)
     */
    public function listPullRequests($owner, $repo, $state = 'open') {
        return $this->request("/repos/{$owner}/{$repo}/pulls?state={$state}");
    }
    
    /**
     * Create pull request
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param array $data PR data (title, body, head, base)
     */
    public function createPullRequest($owner, $repo, $data) {
        return $this->request("/repos/{$owner}/{$repo}/pulls", 'POST', $data);
    }
    
    /**
     * Merge pull request
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param int $prNumber PR number
     * @param array $data Merge data (commit_title, commit_message, merge_method)
     */
    public function mergePullRequest($owner, $repo, $prNumber, $data = []) {
        return $this->request("/repos/{$owner}/{$repo}/pulls/{$prNumber}/merge", 'PUT', $data);
    }
    
    /**
     * Parse repository URL to get owner and repo name
     * @param string $url Repository URL
     * @return array|false Array with 'owner' and 'repo' or false on failure
     */
    public static function parseRepositoryUrl($url) {
        // Handle HTTPS URLs: https://github.com/owner/repo
        if (preg_match('#github\.com[:/]([^/]+)/([^/\.]+)#', $url, $matches)) {
            return [
                'owner' => $matches[1],
                'repo' => $matches[2],
            ];
        }
        
        return false;
    }
}
