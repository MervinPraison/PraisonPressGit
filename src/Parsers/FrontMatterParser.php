<?php
namespace PraisonPress\Parsers;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Parse YAML front matter from Markdown files
 */
class FrontMatterParser {
    
    /**
     * Parse content with YAML front matter
     * 
     * @param string $content File content with front matter
     * @return array ['metadata' => array, 'content' => string]
     */
    public function parse($content) {
        $metadata = [];
        $body = $content;
        
        // Check for front matter (--- at start)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $matches)) {
            $yaml = $matches[1];
            $body = $matches[2];
            
            // Parse YAML (simple parser for basic structures)
            $metadata = $this->parseYaml($yaml);
        }
        
        return [
            'metadata' => $metadata,
            'content' => trim($body)
        ];
    }
    
    /**
     * Simple YAML parser for basic key-value pairs and lists
     * 
     * @param string $yaml YAML content
     * @return array Parsed data
     */
    private function parseYaml($yaml) {
        $result = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $inList = false;
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // List item (starts with - )
            if (preg_match('/^\s*-\s+(.+)$/', $line, $matches)) {
                if ($currentKey && $inList) {
                    // Remove quotes if present
                    $value = trim($matches[1], '"\'');
                    $result[$currentKey][] = $value;
                }
                continue;
            }
            
            // Key-value pair
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                if (empty($value)) {
                    // This might be followed by a list
                    $currentKey = $key;
                    $inList = true;
                    $result[$key] = [];
                } else {
                    $currentKey = $key;
                    $inList = false;
                    $result[$key] = $value;
                }
            }
        }
        
        return $result;
    }
}
