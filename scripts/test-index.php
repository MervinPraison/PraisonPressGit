#!/usr/bin/env php
<?php
/**
 * Test Index Performance
 * 
 * Compare performance between indexed and non-indexed loading
 * 
 * Usage:
 *   php test-index.php /path/to/content/directory
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$contentDir = $argv[1] ?? null;

if (!$contentDir || !is_dir($contentDir)) {
    echo "Usage: php test-index.php /path/to/content/directory\n";
    exit(1);
}

echo "🧪 Testing index performance\n";
echo "Directory: {$contentDir}\n\n";

// Test 1: Check if index exists
$indexFile = $contentDir . '/_index.json';
$hasIndex = file_exists($indexFile);

echo "📊 Index Status:\n";
if ($hasIndex) {
    $indexSize = filesize($indexFile);
    $indexSizeMB = round($indexSize / 1024 / 1024, 2);
    $indexData = json_decode(file_get_contents($indexFile), true);
    $indexCount = count($indexData);
    
    echo "   ✅ Index file exists\n";
    echo "   📁 Size: {$indexSizeMB} MB\n";
    echo "   📝 Entries: {$indexCount}\n\n";
} else {
    echo "   ❌ No index file found\n";
    echo "   💡 Run: php build-index.php {$contentDir}\n\n";
    exit(1);
}

// Test 2: Measure index load time
echo "⏱️  Performance Test:\n\n";

// Test reading index
echo "   Testing index file load...\n";
$start = microtime(true);
$indexData = json_decode(file_get_contents($indexFile), true);
$indexLoadTime = round((microtime(true) - $start) * 1000, 2);
echo "   ✅ Loaded {$indexCount} entries in {$indexLoadTime}ms\n\n";

// Test scanning directory (for comparison)
echo "   Testing directory scan (for comparison)...\n";
$start = microtime(true);
$files = glob($contentDir . '/*.md');
if (empty($files)) {
    // Try recursive
    $files = glob($contentDir . '/**/*.md');
}
$fileCount = count($files);
$scanTime = round((microtime(true) - $start) * 1000, 2);
echo "   ✅ Found {$fileCount} files in {$scanTime}ms\n\n";

// Calculate improvement
$improvement = round($scanTime / $indexLoadTime, 1);

echo "📈 Results:\n";
echo "   Index load time: {$indexLoadTime}ms\n";
echo "   Directory scan time: {$scanTime}ms\n";
echo "   Speed improvement: {$improvement}x faster\n\n";

// Memory usage
$memoryUsage = round(memory_get_peak_usage() / 1024 / 1024, 2);
echo "💾 Memory usage: {$memoryUsage} MB\n\n";

// Sample data
if ($indexCount > 0) {
    echo "📋 Sample Index Entry:\n";
    $sample = $indexData[0];
    echo json_encode($sample, JSON_PRETTY_PRINT) . "\n\n";
}

// Recommendations
echo "💡 Recommendations:\n";
if ($indexCount > 1000) {
    echo "   ✅ Index is highly recommended for {$indexCount} files\n";
} elseif ($indexCount > 100) {
    echo "   ⚠️  Index is beneficial for {$indexCount} files\n";
} else {
    echo "   ℹ️  Index optional for {$indexCount} files (small dataset)\n";
}

if ($indexLoadTime > 100) {
    echo "   ⚠️  Consider optimizing index structure\n";
}

if ($improvement < 2) {
    echo "   ⚠️  Limited performance gain - check file structure\n";
}

echo "\n✅ Test complete!\n";
