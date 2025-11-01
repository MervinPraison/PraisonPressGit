<?php
namespace PraisonPress\GitHub;

use PraisonPress\Git\GitManager;

/**
 * Pull Request Manager
 * Handles creation of pull requests for content edits
 */
class PullRequestManager {
    
    private $gitManager;
    private $githubClient;
    private $contentDir;
    private $repoUrl;
    private $mainBranch;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->gitManager = new GitManager();
        $this->contentDir = PRAISON_CONTENT_DIR;
        
        // Load config
        $config_file = PRAISON_PLUGIN_DIR . '/site-config.ini';
        if (file_exists($config_file)) {
            $config = parse_ini_file($config_file, true);
            $this->repoUrl = isset($config['github']['repository_url']) ? $config['github']['repository_url'] : '';
            $this->mainBranch = isset($config['github']['main_branch']) ? $config['github']['main_branch'] : 'main';
        }
        
        // Initialize GitHub client with stored access token
        require_once PRAISON_PLUGIN_DIR . '/src/GitHub/GitHubClient.php';
        $accessToken = get_option('praisonpress_github_token', '');
        $this->githubClient = new GitHubClient($accessToken);
    }
    
    /**
     * Create a pull request for content edit
     * 
     * @param string $filePath Path to the file being edited
     * @param string $content New content
     * @param string $description Description of changes
     * @param array $postData Post metadata
     * @return array Result with success status and PR URL
     */
    public function createPullRequest($filePath, $content, $description = '', $postData = []) {
        // Validate inputs
        if (empty($filePath) || empty($content)) {
            return [
                'success' => false,
                'message' => 'File path and content are required',
            ];
        }
        
        // Check if GitHub is connected
        if (!$this->githubClient->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'GitHub is not connected. Please connect in plugin settings.',
            ];
        }
        
        // Generate branch name
        $branchName = $this->generateBranchName($postData);
        
        // Step 1: Create and checkout new branch
        $branchResult = $this->createBranch($branchName);
        if (!$branchResult['success']) {
            return $branchResult;
        }
        
        // Step 2: Save content to file
        $saveResult = $this->saveContent($filePath, $content);
        if (!$saveResult['success']) {
            // Rollback: switch back to main branch
            $this->switchBranch($this->mainBranch);
            return $saveResult;
        }
        
        // Step 3: Commit changes
        $commitMessage = $this->generateCommitMessage($postData, $description);
        $commitResult = $this->commitChanges($filePath, $commitMessage);
        if (!$commitResult['success']) {
            // Rollback: switch back to main branch
            $this->switchBranch($this->mainBranch);
            return $commitResult;
        }
        
        // Step 4: Push to remote
        $pushResult = $this->pushBranch($branchName);
        if (!$pushResult['success']) {
            // Rollback: switch back to main branch
            $this->switchBranch($this->mainBranch);
            return $pushResult;
        }
        
        // Step 5: Create pull request via GitHub API
        $prResult = $this->createGitHubPR($branchName, $commitMessage, $description, $postData);
        
        // Switch back to main branch
        $this->switchBranch($this->mainBranch);
        
        return $prResult;
    }
    
    /**
     * Generate branch name
     */
    private function generateBranchName($postData) {
        $slug = isset($postData['slug']) ? $postData['slug'] : 'content';
        $timestamp = time();
        return 'edit-' . $slug . '-' . $timestamp;
    }
    
    /**
     * Generate commit message
     */
    private function generateCommitMessage($postData, $description) {
        $title = isset($postData['title']) ? $postData['title'] : 'Content';
        $message = 'Edit: ' . $title;
        
        if (!empty($description)) {
            $message .= "\n\n" . $description;
        }
        
        return $message;
    }
    
    /**
     * Create and checkout new branch
     */
    private function createBranch($branchName) {
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        // First, ensure we're on main and pull latest changes to prevent conflicts
        exec('git checkout main 2>&1', $output, $return);
        exec('git pull origin main 2>&1', $output, $return);
        
        // Create and checkout new branch from updated main
        exec('git checkout -b ' . escapeshellarg($branchName) . ' 2>&1', $output, $return);
        
        chdir($oldDir);
        
        if ($return === 0) {
            return [
                'success' => true,
                'message' => 'Branch created: ' . $branchName,
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to create branch: ' . implode("\n", $output),
        ];
    }
    
    /**
     * Switch to a branch
     */
    private function switchBranch($branchName) {
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        exec('git checkout ' . escapeshellarg($branchName) . ' 2>&1', $output, $return);
        
        chdir($oldDir);
        
        return $return === 0;
    }
    
    /**
     * Save content to file
     */
    private function saveContent($filePath, $content) {
        // Ensure file path is within content directory
        $realPath = realpath(dirname($filePath));
        $contentDirReal = realpath($this->contentDir);
        
        if (strpos($realPath, $contentDirReal) !== 0) {
            return [
                'success' => false,
                'message' => 'Invalid file path',
            ];
        }
        
        // Save content
        $result = file_put_contents($filePath, $content);
        
        if ($result !== false) {
            return [
                'success' => true,
                'message' => 'Content saved',
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to save content',
        ];
    }
    
    /**
     * Commit changes
     */
    private function commitChanges($filePath, $message) {
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        // Get relative path
        $relativePath = str_replace($this->contentDir . '/', '', $filePath);
        
        // Stage file
        exec('git add ' . escapeshellarg($relativePath) . ' 2>&1', $output, $return);
        
        if ($return !== 0) {
            chdir($oldDir);
            return [
                'success' => false,
                'message' => 'Failed to stage file: ' . implode("\n", $output),
            ];
        }
        
        // Commit
        exec('git commit -m ' . escapeshellarg($message) . ' 2>&1', $output, $return);
        
        chdir($oldDir);
        
        if ($return === 0) {
            return [
                'success' => true,
                'message' => 'Changes committed',
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to commit: ' . implode("\n", $output),
        ];
    }
    
    /**
     * Push branch to remote
     */
    private function pushBranch($branchName) {
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        // Push branch
        exec('git push -u origin ' . escapeshellarg($branchName) . ' 2>&1', $output, $return);
        
        chdir($oldDir);
        
        if ($return === 0) {
            return [
                'success' => true,
                'message' => 'Branch pushed to remote',
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to push branch: ' . implode("\n", $output),
        ];
    }
    
    /**
     * Create pull request via GitHub API
     */
    private function createGitHubPR($branchName, $title, $description, $postData) {
        // Extract owner and repo from URL
        $repoInfo = $this->parseRepoUrl($this->repoUrl);
        if (!$repoInfo) {
            return [
                'success' => false,
                'message' => 'Invalid repository URL',
            ];
        }
        
        // Prepare PR data
        $prData = [
            'title' => $title,
            'body' => $this->generatePRBody($description, $postData),
            'head' => $branchName,
            'base' => $this->mainBranch,
        ];
        
        // Create PR via API
        $endpoint = '/repos/' . $repoInfo['owner'] . '/' . $repoInfo['repo'] . '/pulls';
        $response = $this->githubClient->post($endpoint, $prData);
        
        // Unwrap the response (GitHubClient wraps it in success/data)
        $prData = $response;
        if (isset($response['success']) && $response['success'] && isset($response['data'])) {
            $prData = $response['data'];
        }
        
        if (isset($prData['html_url'])) {
            return [
                'success' => true,
                'message' => 'Pull request created successfully',
                'pr_url' => $prData['html_url'],
                'pr_number' => $prData['number'],
            ];
        }
        
        // Enhanced error logging
        $errorMessage = 'Failed to create pull request: ';
        if (isset($prData['message'])) {
            $errorMessage .= $prData['message'];
        } elseif (isset($response['error'])) {
            $errorMessage .= $response['error'];
        } else {
            $errorMessage .= 'Unknown error';
        }
        
        // Add detailed error info if available
        if (isset($prData['errors'])) {
            $errorMessage .= ' - Errors: ' . json_encode($prData['errors']);
        }
        
        // Log full response for debugging
        error_log('GitHub PR Creation Failed: ' . json_encode($response));
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'debug' => $prData,
        ];
    }
    
    /**
     * Generate PR body
     */
    private function generatePRBody($description, $postData) {
        $body = "## Content Edit Suggestion\n\n";
        
        if (isset($postData['title'])) {
            $body .= "**Post:** " . $postData['title'] . "\n";
        }
        
        if (isset($postData['type'])) {
            $body .= "**Type:** " . $postData['type'] . "\n";
        }
        
        if (!empty($description)) {
            $body .= "\n### Changes:\n" . $description . "\n";
        }
        
        $body .= "\n---\n*This pull request was automatically created by the PraisonPress plugin.*";
        
        return $body;
    }
    
    /**
     * Parse repository URL
     */
    private function parseRepoUrl($url) {
        // Extract owner and repo from GitHub URL
        // Format: https://github.com/owner/repo
        if (preg_match('#github\.com[:/]([^/]+)/([^/\.]+)#', $url, $matches)) {
            return [
                'owner' => $matches[1],
                'repo' => $matches[2],
            ];
        }
        
        return false;
    }
    
    /**
     * Save submission to database for user tracking
     * 
     * @param int $prNumber GitHub PR number
     * @param string $prUrl GitHub PR URL
     * @param int $postId Related post ID
     * @param string $postTitle Post title
     */
    private function saveSubmissionToDatabase($prNumber, $prUrl, $postId = null, $postTitle = null) {
        require_once PRAISON_PLUGIN_DIR . '/src/Database/SubmissionsTable.php';
        $submissionsTable = new \PraisonPress\Database\SubmissionsTable();
        
        $userId = get_current_user_id();
        if ($userId) {
            $submissionsTable->saveSubmission($userId, $prNumber, $prUrl, $postId, $postTitle);
        }
    }
}
