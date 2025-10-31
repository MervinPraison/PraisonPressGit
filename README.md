# PraisonPressGit

**PraisonPressGit** - A WordPress Must-Use (MU) Plugin that loads content from files (Markdown, JSON, YAML) without database writes, with Git-based version control.

## Features

- **File-Based Content Management**: Store posts, pages, and custom post types as Markdown files
- **Version Control Ready**: Designed to work with Git for tracking content changes
- **No Database Writes**: Read-only approach - content stays in files
- **Dynamic Post Type Discovery**: Automatically registers custom post types based on directory structure
- **Custom URL Routing**: Support for custom post type routing (e.g., `/lyrics/xxx`, `/posts/xxx`)
- **Cache Management**: Built-in caching system for optimal performance
- **Front Matter Support**: YAML front matter for metadata
- **Markdown Parsing**: Full Markdown support with automatic HTML conversion

## Installation

This is a Must-Use (MU) plugin, which means it's automatically loaded by WordPress.

1. Copy the `praisonpressgit` folder and `praisonpress-loader.php` to your `wp-content/mu-plugins/` directory
2. WordPress will automatically activate it
3. Content directory will be created at `/content/` (root level, independent of WordPress)

## Directory Structure

```
/content/
├── posts/              # Blog posts
├── pages/              # Static pages
├── lyrics/             # Custom post type: Lyrics
├── recipes/            # Custom post type: Recipes
└── config/             # Configuration files
```

## Content Format

Create `.md` files with YAML front matter:

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

Write your content in Markdown format.
```

## Custom Post Types

Simply create a new directory in `/content/` to add a custom post type:

```bash
mkdir /content/lyrics
```

The plugin will automatically:
- Register the `lyrics` post type
- Create the URL route `/lyrics/{slug}`
- Load content from the directory

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Git (for version control)

## Author

**MervinPraison**  
Website: [https://mer.vin](https://mer.vin)

## License

GPL v2 or later

## Version

1.0.0
