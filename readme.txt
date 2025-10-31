=== PraisonPressGit ===
Contributors: mervinpraison
Tags: markdown, git, content-management, file-based, version-control
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Load WordPress content from files (Markdown, JSON, YAML) without database writes, with Git-based version control.

== Description ==

**PraisonPressGit** is a revolutionary WordPress plugin that enables file-based content management with Git version control integration. Store your posts, pages, and custom post types as Markdown files while maintaining full WordPress compatibility.

= Key Features =

* **File-Based Content Management** - Store all content as Markdown files
* **No Database Writes** - Pure read-only approach for content
* **Git Version Control** - Track changes with full Git integration
* **Dynamic Post Type Discovery** - Automatically registers post types from directory structure
* **Custom URL Routing** - Beautiful URLs for any post type (e.g., `/lyrics/song-name`)
* **YAML Front Matter** - Rich metadata support
* **Caching System** - Built-in performance optimization
* **Auto-Update Detection** - Content updates automatically when files change
* **WordPress Compatible** - Works with themes, plugins, and filters
* **Developer Friendly** - Clean, extensible architecture

= Perfect For =

* Developers who prefer Git workflows
* Teams collaborating on content
* Sites requiring version control
* Static site generators transitioning to WordPress
* Content stored in version control repositories

= How It Works =

1. Create a `content/` directory at your WordPress root
2. Add Markdown files with YAML front matter
3. Plugin automatically discovers and loads content
4. Create new post types by simply adding directories

= Example Post =

```markdown
---
title: "My Post Title"
slug: "my-post-slug"
author: "admin"
date: "2024-10-31 12:00:00"
status: "publish"
categories:
  - "General"
tags:
  - "example"
---

# Your content here

Write your content in **Markdown** format.
```

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Git (optional, for version control features)

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New
2. Search for "PraisonPressGit"
3. Click "Install Now"
4. Activate the plugin

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the downloaded ZIP file
4. Click "Install Now"
5. Activate the plugin

= After Installation =

1. Create a `content/` directory at your WordPress root level
2. Create subdirectories for post types (e.g., `content/posts/`, `content/pages/`)
3. Add Markdown files with YAML front matter
4. View your content on the frontend

= Configuration =

The plugin works out of the box with default settings. To customize the content directory location, add to `wp-config.php`:

`define('PRAISON_CONTENT_DIR', '/custom/path/to/content');`

Or use a filter:

`add_filter('praison_content_dir', function($dir) {
    return '/custom/path/to/content';
});`

== Frequently Asked Questions ==

= Does this replace the WordPress database? =

No. The WordPress database is still required for WordPress core functionality, user management, settings, etc. This plugin only replaces content storage (posts, pages) with file-based content.

= Can I use this with my existing WordPress site? =

Yes! The plugin works alongside existing database-based content. You can mix file-based and database-based content.

= What file formats are supported? =

Currently, Markdown (.md) files with YAML front matter are supported.

= How do I create a custom post type? =

Simply create a new directory in your content folder. For example, creating `content/recipes/` automatically registers a "recipes" post type.

= Does it work with WordPress themes? =

Yes! File-based posts work exactly like regular WordPress posts with all template tags and filters.

= Can I use WordPress plugins with this? =

Yes! The content is loaded as proper WP_Post objects, so plugins that modify content (like SEO plugins) work normally.

= How does caching work? =

The plugin uses WordPress transients for caching. Cache automatically invalidates when files are modified.

= How do I clear the cache? =

Go to PraisonPressGit → Clear Cache in the WordPress admin, or use the top admin bar menu.

= Is Git required? =

No, Git is optional. The plugin works without Git, but version control features require Git to be installed.

= How do I track version history? =

The plugin includes Git integration. If Git is installed, file changes are automatically tracked. View history in PraisonPressGit → Version History.

= Can I rollback to previous versions? =

Yes, if Git is available, you can rollback any file to a previous version from the Version History page.

== Screenshots ==

1. Admin dashboard showing file-based content statistics
2. Version history interface with Git commit tracking
3. Markdown file example with YAML front matter
4. Content directory structure

== Changelog ==

= 1.0.0 =
* Initial release
* File-based content management
* Markdown parser with YAML front matter support
* Dynamic custom post type discovery
* Custom URL routing
* Built-in caching system
* Git version control integration
* Admin interface with version history
* Auto-update detection for file changes
* WordPress filter compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of PraisonPressGit. Install and activate to start using file-based content management.

== Development ==

* GitHub Repository: https://github.com/MervinPraison/PraisonPressGit
* Report Issues: https://github.com/MervinPraison/PraisonPressGit/issues
* Author Website: https://mer.vin

== Credits ==

Developed by MervinPraison
