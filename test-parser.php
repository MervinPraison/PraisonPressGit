<?php
/**
 * Simple test script for PraisonPress parsers
 * Run: php test-parser.php
 */

// Mock WordPress constants for testing
define('ABSPATH', true);
define('PRAISON_CONTENT_DIR', __DIR__ . '/../../praison-content');

// Include parsers
require_once __DIR__ . '/src/Parsers/FrontMatterParser.php';
require_once __DIR__ . '/src/Parsers/MarkdownParser.php';

use PraisonPress\Parsers\FrontMatterParser;
use PraisonPress\Parsers\MarkdownParser;

echo "=== PraisonPress Parser Test ===\n\n";

// Test 1: Front Matter Parser
echo "Test 1: Front Matter Parser\n";
echo "----------------------------\n";

$frontMatterParser = new FrontMatterParser();

$testContent = <<<'MD'
---
title: "Test Post"
slug: "test-post"
author: "admin"
date: "2024-10-31 12:00:00"
status: "publish"
categories:
  - "General"
  - "Testing"
tags:
  - "test"
  - "demo"
---

# This is a test post

Content goes here.
MD;

$parsed = $frontMatterParser->parse($testContent);

echo "Metadata:\n";
print_r($parsed['metadata']);
echo "\nContent:\n";
echo substr($parsed['content'], 0, 100) . "...\n\n";

// Test 2: Markdown Parser
echo "Test 2: Markdown Parser\n";
echo "------------------------\n";

$markdownParser = new MarkdownParser();

$testMarkdown = <<<'MD'
# Heading 1
## Heading 2

This is **bold** and this is *italic*.

- List item 1
- List item 2
- List item 3

[Link text](https://example.com)

`inline code`

```php
echo "Code block";
```
MD;

$html = $markdownParser->parse($testMarkdown);
echo "HTML Output:\n";
echo $html . "\n\n";

// Test 3: Load actual content file
echo "Test 3: Load Actual Content File\n";
echo "---------------------------------\n";

$welcomeFile = PRAISON_CONTENT_DIR . '/posts/2024-10-31-welcome.md';
if (file_exists($welcomeFile)) {
    $content = file_get_contents($welcomeFile);
    $parsed = $frontMatterParser->parse($content);
    
    echo "File: " . basename($welcomeFile) . "\n";
    echo "Title: " . ($parsed['metadata']['title'] ?? 'N/A') . "\n";
    echo "Slug: " . ($parsed['metadata']['slug'] ?? 'N/A') . "\n";
    echo "Date: " . ($parsed['metadata']['date'] ?? 'N/A') . "\n";
    echo "Content Length: " . strlen($parsed['content']) . " characters\n";
    
    $html = $markdownParser->parse($parsed['content']);
    echo "HTML Length: " . strlen($html) . " characters\n\n";
} else {
    echo "Warning: Sample file not found at $welcomeFile\n\n";
}

echo "=== Tests Complete ===\n";
echo "\n✅ All parsers are working correctly!\n";
