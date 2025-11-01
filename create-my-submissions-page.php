<?php
/**
 * One-time script to create the My Submissions page
 * Run this file once, then delete it
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Check if page already exists
$existing_page = get_page_by_path('my-submissions');

if ($existing_page) {
    echo "✅ Page 'My Submissions' already exists!\n";
    echo "URL: " . get_permalink($existing_page->ID) . "\n";
    exit;
}

// Create the page
$page_data = array(
    'post_title'    => 'My Submissions',
    'post_content'  => '[praisonpress_my_submissions]',
    'post_status'   => 'publish',
    'post_type'     => 'page',
    'post_author'   => 1,
    'post_name'     => 'my-submissions'
);

$page_id = wp_insert_post($page_data);

if ($page_id) {
    echo "✅ SUCCESS! Page created!\n\n";
    echo "Page ID: " . $page_id . "\n";
    echo "Page URL: " . get_permalink($page_id) . "\n\n";
    echo "You can now delete this file: create-my-submissions-page.php\n";
} else {
    echo "❌ ERROR: Failed to create page\n";
}
