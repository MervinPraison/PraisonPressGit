<?php
namespace PraisonPress\Git;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Git Manager for Version Control
 * Tracks changes to markdown files
 */
class GitManager {
    
    private $contentDir;
    private $gitAvailable = false;
    
    public function __construct() {
        $this->contentDir = PRAISON_CONTENT_DIR;
        $this->gitAvailable = $this->checkGitAvailable();
        
        // Initialize git repo if needed
        if ($this->gitAvailable && !$this->isGitRepo()) {
            $this->initRepo();
        }
    }
    
    /**
     * Check if git is available
     */
    private function checkGitAvailable() {
        exec('git --version 2>&1', $output, $return);
        return $return === 0;
    }
    
    /**
     * Check if content directory is a git repo
     */
    private function isGitRepo() {
        return file_exists($this->contentDir . '/.git');
    }
    
    /**
     * Initialize git repository
     */
    private function initRepo() {
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        exec('git init 2>&1', $output, $return);
        
        if ($return === 0) {
            // Create .gitignore
            file_put_contents('.gitignore', "*.log\n*.tmp\n.DS_Store\n");
            
            // Initial commit
            exec('git add .');
            exec('git commit -m "Initial commit - PraisonPress content" 2>&1');
        }
        
        chdir($oldDir);
        return $return === 0;
    }
    
    /**
     * Commit changes to a file
     */
    public function commitFile($file, $message = '') {
        if (!$this->gitAvailable) {
            return false;
        }
        
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        $relativePath = str_replace($this->contentDir . '/', '', $file);
        
        if (empty($message)) {
            $message = 'Updated ' . basename($file);
        }
        
        exec('git add ' . escapeshellarg($relativePath) . ' 2>&1', $output, $return);
        
        if ($return === 0) {
            exec('git commit -m ' . escapeshellarg($message) . ' 2>&1', $output, $return);
        }
        
        chdir($oldDir);
        return $return === 0;
    }
    
    /**
     * Get commit history
     */
    public function getHistory($limit = 50) {
        if (!$this->gitAvailable || !$this->isGitRepo()) {
            return [];
        }
        
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        // Use proper escaping for git log format
        $format = '--pretty=format:%H|%an|%ae|%at|%s';
        $command = sprintf('git log %s -%d 2>&1', escapeshellarg($format), (int)$limit);
        exec($command, $output, $return);
        
        chdir($oldDir);
        
        if ($return !== 0) {
            return [];
        }
        
        $history = [];
        foreach ($output as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 5) {
                $history[] = [
                    'hash' => $parts[0],
                    'author' => $parts[1],
                    'email' => $parts[2],
                    'timestamp' => (int)$parts[3],
                    'message' => $parts[4],
                    'date' => gmdate( 'Y-m-d H:i:s', (int) $parts[3] ),
                ];
            }
        }
        
        return $history;
    }
    
    /**
     * Get diff for a file
     */
    public function getDiff($file, $hash = 'HEAD') {
        if (!$this->gitAvailable) {
            return '';
        }
        
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        $relativePath = str_replace($this->contentDir . '/', '', $file);
        exec('git show ' . escapeshellarg($hash . ':' . $relativePath) . ' 2>&1', $output, $return);
        
        chdir($oldDir);
        
        if ($return !== 0) {
            return '';
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Rollback a file or entire repo to specific commit
     */
    public function rollback($file, $hash) {
        if (!$this->gitAvailable) {
            return false;
        }
        
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        // If file is null, rollback entire repository
        if ($file === null || empty($file)) {
            exec('git reset --hard ' . escapeshellarg($hash) . ' 2>&1', $output, $return);
            chdir($oldDir);
            return $return === 0;
        }
        
        $relativePath = str_replace($this->contentDir . '/', '', $file);
        
        // Get file content from commit
        exec('git show ' . escapeshellarg($hash . ':' . $relativePath) . ' 2>&1', $output, $return);
        
        if ($return === 0) {
            // Write content back to file
            file_put_contents($file, implode("\n", $output));
            
            // Commit the rollback
            exec('git add ' . escapeshellarg($relativePath));
            exec('git commit -m "Rollback ' . basename($file) . ' to ' . substr($hash, 0, 7) . '"');
        }
        
        chdir($oldDir);
        return $return === 0;
    }
    
    /**
     * Check if git is available
     */
    public function isAvailable() {
        return $this->gitAvailable;
    }
    
    /**
     * Get status
     */
    public function getStatus() {
        if (!$this->gitAvailable) {
            return [];
        }
        
        return [
            'available' => $this->gitAvailable,
            'repo' => $this->isGitRepo(),
            'path' => $this->contentDir,
        ];
    }
    
    /**
     * Get commit details including diff
     */
    public function getCommitDetails($hash) {
        if (!$this->gitAvailable || !$this->isGitRepo()) {
            return [];
        }
        
        $oldDir = getcwd();
        chdir($this->contentDir);
        
        // Get commit info
        $format = '--pretty=format:%H|%an|%ae|%at|%s';
        $command = sprintf('git show %s %s 2>&1', escapeshellarg($format), escapeshellarg($hash));
        exec($command, $output, $return);
        
        if ($return !== 0 || empty($output)) {
            chdir($oldDir);
            return [];
        }
        
        // Parse first line (commit info)
        $parts = explode('|', $output[0]);
        if (count($parts) !== 5) {
            chdir($oldDir);
            return [];
        }
        
        // Get files changed
        $filesCommand = sprintf('git show --name-only --pretty=format: %s 2>&1', escapeshellarg($hash));
        exec($filesCommand, $filesOutput, $filesReturn);
        $files = array_filter($filesOutput); // Remove empty lines
        
        // Get full diff
        $diffCommand = sprintf('git show %s 2>&1', escapeshellarg($hash));
        exec($diffCommand, $diffOutput, $diffReturn);
        $diff = implode("\n", $diffOutput);
        
        chdir($oldDir);
        
        return [
            'hash' => $parts[0],
            'author' => $parts[1],
            'email' => $parts[2],
            'timestamp' => (int)$parts[3],
            'message' => $parts[4],
            'date' => gmdate('Y-m-d H:i:s', (int)$parts[3]),
            'files' => $files,
            'diff' => $diff,
        ];
    }
}
