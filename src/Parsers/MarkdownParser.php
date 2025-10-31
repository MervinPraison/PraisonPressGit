<?php
namespace PraisonPress\Parsers;

/**
 * Simple Markdown to HTML parser
 * Supports basic Markdown syntax
 */
class MarkdownParser {
    
    /**
     * Parse Markdown to HTML
     * 
     * @param string $markdown Markdown content
     * @return string HTML content
     */
    public function parse($markdown) {
        // Use Parsedown if available, otherwise basic conversion
        if (class_exists('Parsedown')) {
            $parsedown = new \Parsedown();
            return $parsedown->text($markdown);
        }
        
        // Basic Markdown conversion
        return $this->basicParse($markdown);
    }
    
    /**
     * Basic Markdown parser (fallback)
     * 
     * @param string $markdown Markdown content
     * @return string HTML content
     */
    private function basicParse($markdown) {
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);
        
        // Italic
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);
        
        // Links [text](url)
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // Images ![alt](url)
        $html = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" />', $html);
        
        // Code blocks ```
        $html = preg_replace('/```([a-z]*)\n(.*?)\n```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
        
        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        
        // Unordered lists
        $html = preg_replace_callback('/^(\s*)-\s+(.+)$/m', function($matches) {
            return $matches[1] . '<li>' . $matches[2] . '</li>';
        }, $html);
        
        // Wrap lists
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
        
        // Paragraphs (lines separated by blank lines)
        $paragraphs = preg_split('/\n\n+/', $html);
        foreach ($paragraphs as $key => $para) {
            $para = trim($para);
            // Don't wrap if already has HTML tags
            if ($para && !preg_match('/^<[a-z]/i', $para)) {
                $paragraphs[$key] = '<p>' . $para . '</p>';
            } else {
                $paragraphs[$key] = $para;
            }
        }
        $html = implode("\n\n", $paragraphs);
        
        // Line breaks
        $html = str_replace("\n", '<br />', $html);
        
        return $html;
    }
}
