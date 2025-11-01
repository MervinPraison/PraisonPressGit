#!/usr/bin/env php
<?php
/**
 * Build Content Index for PraisonPress
 * 
 * This script scans markdown files and creates an optimized index for fast loading.
 * Use this during Docker image build to handle large content directories (100K+ files).
 * 
 * Usage:
 *   php build-index.php /path/to/content/directory [post-type]
 * 
 * Example:
 *   php build-index.php /var/www/html/content/lyrics lyrics
 *   php build-index.php /var/www/html/content/posts posts
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$contentDir = $argv[1] ?? null;
$postType = $argv[2] ?? 'posts';

if (!$contentDir || !is_dir($contentDir)) {
    echo "Usage: php build-index.php /path/to/content/directory [post-type]\n";
    echo "Example: php build-index.php /var/www/html/content/lyrics lyrics\n";
    exit(1);
}

echo "🔍 Building index for: {$contentDir}\n";
echo "📝 Post type: {$postType}\n\n";

/**
 * Parse YAML front matter from markdown content
 */
function parseFrontMatter($content) {
    // Match YAML front matter between --- delimiters
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
        return null;
    }
    
    $yaml = $matches[1];
    $metadata = [];
    
    // Parse each line
    $lines = explode("\n", $yaml);
    $currentKey = null;
    $currentArray = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Array item
        if (strpos($line, '- ') === 0) {
            if ($currentKey && $currentArray !== null) {
                $value = trim(substr($line, 2));
                $value = trim($value, '"\'');
                $metadata[$currentKey][] = $value;
            }
            continue;
        }
        
        // Key-value pair
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes
            $value = trim($value, '"\'');
            
            // Check if this starts an array
            if (empty($value)) {
                $currentKey = $key;
                $currentArray = [];
                $metadata[$key] = [];
            } else {
                $metadata[$key] = $value;
                $currentKey = null;
                $currentArray = null;
            }
        }
    }
    
    return $metadata;
}

/**
 * Recursively find all .md files
 */
function findMarkdownFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

// Find all markdown files
$startTime = microtime(true);
$files = findMarkdownFiles($contentDir);
$totalFiles = count($files);

echo "📊 Found {$totalFiles} markdown files\n";
echo "⏳ Processing files...\n\n";

$index = [];
$processed = 0;
$errors = 0;
$skipped = 0;

foreach ($files as $file) {
    $processed++;
    
    // Show progress every 1000 files
    if ($processed % 1000 === 0) {
        $percent = round(($processed / $totalFiles) * 100, 1);
        echo "   Progress: {$processed}/{$totalFiles} ({$percent}%)\n";
    }
    
    try {
        // Read file content
        $content = file_get_contents($file);
        if ($content === false) {
            $errors++;
            continue;
        }
        
        // Parse front matter
        $metadata = parseFrontMatter($content);
        if (!$metadata) {
            $skipped++;
            continue;
        }
        
        // Validate required fields
        if (empty($metadata['title']) || empty($metadata['slug'])) {
            $skipped++;
            continue;
        }
        
        // Calculate relative path from content directory
        $relativePath = str_replace($contentDir . '/', '', $file);
        
        // Build index entry
        $entry = [
            'file' => $relativePath,
            'title' => $metadata['title'],
            'slug' => $metadata['slug'],
            'date' => $metadata['date'] ?? date('Y-m-d H:i:s'),
            'status' => $metadata['status'] ?? 'publish',
            'author' => $metadata['author'] ?? 'admin',
            'excerpt' => $metadata['excerpt'] ?? '',
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
            'categories' => $metadata['categories'] ?? [],
            'tags' => $metadata['tags'] ?? [],
        ];
        
        // Add any custom fields
        foreach ($metadata as $key => $value) {
            if (!isset($entry[$key])) {
                $entry['custom'][$key] = $value;
            }
        }
        
        $index[] = $entry;
        
    } catch (Exception $e) {
        $errors++;
        echo "   ⚠️  Error processing {$file}: {$e->getMessage()}\n";
    }
}

// Sort by date (newest first)
usort($index, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Build index filename
$indexFile = $contentDir . '/_index.json';

// Save index
$jsonData = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (file_put_contents($indexFile, $jsonData) === false) {
    echo "\n❌ Failed to write index file: {$indexFile}\n";
    exit(1);
}

// Calculate statistics
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);
$indexed = count($index);
$fileSize = filesize($indexFile);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);

// Output results
echo "\n" . str_repeat('=', 60) . "\n";
echo "✅ Index built successfully!\n\n";
echo "📊 Statistics:\n";
echo "   Total files found: {$totalFiles}\n";
echo "   Successfully indexed: {$indexed}\n";
echo "   Skipped (no front matter): {$skipped}\n";
echo "   Errors: {$errors}\n";
echo "   Processing time: {$duration}s\n";
echo "   Index file size: {$fileSizeMB} MB\n";
echo "   Average: " . ($duration > 0 ? round($totalFiles / $duration) : $totalFiles) . " files/second\n\n";
echo "📁 Index saved to: {$indexFile}\n";
echo str_repeat('=', 60) . "\n";

// Create metadata file
$metaFile = $contentDir . '/_index.meta.json';
$meta = [
    'generated_at' => date('Y-m-d H:i:s'),
    'post_type' => $postType,
    'total_posts' => $indexed,
    'build_time_seconds' => $duration,
    'index_version' => '1.0.0',
];
file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

exit(0);
