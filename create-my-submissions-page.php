<?php
/**
 * One-time script to create the My Submissions page
 * Run this file once, then delete it
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Check if page already exists
$praison_existing_page = get_page_by_path('my-submissions');

if ($praison_existing_page) {
    echo "✅ Page 'My Submissions' already exists!\n";
    echo "URL: " . esc_url(get_permalink($praison_existing_page->ID)) . "\n";
    exit;
}

// Create the page
$praison_page_data = array(
    'post_title'    => 'My Submissions',
    'post_content'  => '[praisonpress_my_submissions]',
    'post_status'   => 'publish',
    'post_type'     => 'page',
    'post_author'   => 1,
    'post_name'     => 'my-submissions'
);

$praison_page_id = wp_insert_post($praison_page_data);

if ($praison_page_id) {
    echo "✅ SUCCESS! Page created!\n\n";
    echo "Page ID: " . esc_html($praison_page_id) . "\n";
    echo "Page URL: " . esc_url(get_permalink($praison_page_id)) . "\n\n";
    echo "You can now delete this file: create-my-submissions-page.php\n";
} else {
    echo "❌ ERROR: Failed to create page\n";
}
