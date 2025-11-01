# PraisonPressGit

**PraisonPressGit** - A powerful WordPress plugin that loads content from files (Markdown, JSON, YAML) without database writes, featuring Git-based version control and cloud-native deployment support.

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/your-repo/praisonpressgit)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-green.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

---

## 🚀 Features

### Core Features

- **📤 Export to Markdown**: Convert existing WordPress content to Markdown files
  - Admin UI with progress tracking and background processing
  - CLI support for automation and large exports
  - Preserves all metadata, custom fields, taxonomies, categories, and tags
  - Full ACF (Advanced Custom Fields) support
  - Handles 50,000+ posts efficiently
- **File-Based Content Management**: Store posts, pages, and custom post types as Markdown files
- **Version Control Ready**: Designed to work with Git for tracking content changes  
- **No Database Writes**: Read-only approach - content stays in files
- **Dynamic Post Type Discovery**: Automatically registers custom post types based on directory structure
- **Custom URL Routing**: Support for custom post type routing (e.g., `/recipes/xxx`, `/posts/xxx`)
- **Cache Management**: Built-in caching system for optimal performance
- **Front Matter Support**: YAML front matter for comprehensive metadata
- **Markdown Parsing**: Full Markdown support with automatic HTML conversion

### Advanced Features

- **🚀 High Performance Index System**: Handle 100,000+ files with build-time indexing
- **☁️ Cloud-Native Ready**: Docker, Kubernetes, and multi-cloud deployment support
- **🔄 Separate Content Repos**: Keep content in separate Git repositories
- **🔒 Security Hardened**: All outputs escaped, nonce verification, timezone-safe
- **📊 Admin Dashboard**: Real-time statistics and content management
- **🎯 Smart Caching**: WordPress transients with automatic invalidation
- **🌐 Multi-Pod Support**: Horizontal scaling with shared storage
- **🔧 WP-CLI Support**: Command-line tools for automation

### Performance Features

- **Intelligent Caching**: Transient-based caching with configurable TTL
- **Index System**: Optional indexing for 100K+ files (50-100x faster)
- **Lazy Loading**: Content loaded on-demand, not at boot
- **Cache Invalidation**: Automatic detection of file changes
- **Memory Efficient**: Only loads requested content

### Developer Features

- **PSR-4 Autoloading**: Modern PHP namespace structure
- **Filters & Actions**: Extensive hook system for customization
- **WP Coding Standards**: Follows WordPress best practices
- **Git Integration**: Built for version control workflows
- **API Ready**: Programmatic access to all features

---

## 🔄 Content Management Workflows

PraisonPressGit supports two flexible workflows to fit your team's needs:

### Option 1: Admin → Files (Hybrid Approach)

```
1. Create posts in WordPress admin
2. Use Export feature to convert to .md files
3. Commit files to Git
4. Deploy to production
```

**Best for:**
- Teams familiar with WordPress
- Content editors who prefer GUI
- Gradual migration to file-based content

### Option 2: Files → Frontend (Pure Git Workflow)

```
1. Create .md files directly in /content/
2. Files automatically appear on frontend
3. Commit to Git
4. Deploy
```

**Best for:**
- Developers and technical writers
- Git-first workflows
- JAMstack architectures
- CI/CD pipelines

### Best of Both Worlds! ✅

You can mix and match both workflows:
- Use admin for quick edits and drafts
- Export to files for version control
- Create files directly for new content
- All content accessible on frontend

**Key Benefits:**
- 📝 Flexibility: Choose the workflow that fits each task
- 🔄 Reversible: Export admin posts to files anytime
- 🚀 Scalable: File-based content performs better at scale
- 📊 Trackable: Full Git history for all file-based content

---

## Installation

### Via WordPress.org (Recommended)

1. Go to **Plugins > Add New** in WordPress admin
2. Search for "PraisonPressGit"
3. Click **Install Now** and then **Activate**
4. Content directory will be created at `/content/` (root level, independent of WordPress)

### Manual Installation

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### For Developers

```bash
# Clone to plugins directory
cd wp-content/plugins/
git clone https://github.com/your-repo/praisonpressgit.git

# Or via WP-CLI
wp plugin install praisonpressgit --activate
```

## Directory Structure

```
/content/
├── posts/              # Blog posts
├── pages/              # Static pages
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
mkdir /content/recipes
```

The plugin will automatically:
- Register the `recipes` post type
- Create the URL route `/recipes/{slug}`
- Load content from the directory

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Git (for version control)

## Containerized Deployment

PraisonPressGit is **designed for cloud-native deployments** and works seamlessly in:
- **Docker** containers
- **Kubernetes** pods
- **Cloud platforms** (AWS, Azure, GCP)
- **Roots/Bedrock** WordPress structure

### Deployment Strategies:

#### Strategy 1: Bake Content into Image (Recommended for Infrequent Updates)
**Perfect for:** Static content, rarely updated sites, immutable infrastructure

✅ **Pros:**
- Fast pod startup (no volume mounts needed)
- Simple deployment
- Content versioned with image tags
- Works great with `kubectl rollout restart deployment/wordpress`

```dockerfile
FROM wordpress:latest
COPY ./praisonpressgit /var/www/html/wp-content/plugins/praisonpressgit
COPY ./content /var/www/html/content
RUN chown -R www-data:www-data /var/www/html/content
```

**Update workflow:** Build new image → Push to registry → `kubectl rollout restart deployment/wordpress`

**Separate Content Repository Pattern:**

Keep content in a separate Git repo for better organization:

```dockerfile
# Multi-stage build - fetch content from separate repo
FROM alpine/git AS content
ARG CONTENT_REPO_URL=https://github.com/your-org/content-repo.git
RUN git clone --depth 1 ${CONTENT_REPO_URL} /content

FROM wordpress:latest
COPY ./praisonpressgit /var/www/html/wp-content/plugins/praisonpressgit
COPY --from=content /content /var/www/html/content
```

**CI/CD:** Push to content repo → Trigger WordPress image rebuild → Auto-deploy

#### Strategy 2: Persistent Volumes (For Dynamic Content)
**Perfect for:** Frequently updated content, multi-author sites

✅ **Pros:**
- Content updates without rebuilding images
- Multi-pod deployments with ReadWriteMany (RWX) volumes
- Git sidecar container support
- Redis/Memcached integration for shared caching
- Health checks and autoscaling ready

### Quick Docker Setup:

```bash
# Using Docker Compose
docker-compose up -d

# Content persists in named volume: praison-content
```

### Kubernetes Deployment:

```bash
# Deploy with persistent volumes
kubectl apply -f k8s/praison-pvc.yaml
kubectl apply -f k8s/wordpress-deployment.yaml

# Scale horizontally
kubectl scale deployment wordpress --replicas=3
```

**📖 Full Documentation:** See [PRAISONPRESS-README.md](PRAISONPRESS-README.md#containerized-deployment) for complete Docker/Kubernetes configurations, storage options, and production best practices.

---

## 🏗️ Architecture

### How It Works

PraisonPressGit uses a **virtual post injection** system:

```
┌─────────────────────────────────────────┐
│         WordPress Request               │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│     PraisonPress Bootstrap              │
│  • Discovers post types dynamically     │
│  • Registers custom post types          │
│  • Hooks into posts_pre_query           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│         PostLoader                      │
│  • Checks for _index.json (fast)       │
│  • Falls back to directory scan         │
│  • Parses Markdown + YAML               │
│  • Creates WP_Post objects              │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│      WordPress Display                  │
│  • Theme renders posts normally         │
│  • No database queries for content      │
│  • Caching for performance              │
└─────────────────────────────────────────┘
```

### Components

- **Bootstrap.php**: Main entry point, discovers post types
- **PostLoader.php**: Loads and parses Markdown files (with index support)
- **CacheManager.php**: Transient-based caching
- **MarkdownParser.php**: Converts Markdown to HTML
- **FrontMatterParser.php**: Parses YAML metadata
- **Admin Pages**: Dashboard, history, and statistics

---

## 📊 Admin Features

### Dashboard Widget

- **Content Statistics**: Total posts, pages, and custom post types
- **Last Modified**: Track recent content changes
- **Cache Status**: View and clear cache
- **Quick Actions**: Access management tools

### Admin Menu

- **PraisonPress Dashboard**: Overview and statistics
- **Version History**: Git-based content tracking (if available)
- **Settings**: Configure cache and content directories
- **Clear Cache**: Manual cache invalidation

### Admin Bar Integration

- Quick access to PraisonPress features from admin bar
- Cache clear shortcut
- Content statistics at a glance

---

## ⚡ Performance & Scaling

### For Small Sites (< 1,000 files)

**Standard Setup** - No index needed

```yaml
Performance: ~0.5s load time
Memory: ~50MB
Cache: WordPress transients
```

### For Medium Sites (1,000 - 10,000 files)

**Index Recommended**

```yaml
Performance: ~0.2s load time (5x faster)
Memory: ~80MB
Cache: Transients + Object cache
```

### For Large Sites (10,000 - 100,000 files)

**Index Required**

```yaml
Performance: ~0.5-2s load time (50x faster)
Memory: ~150MB
Cache: Redis/Memcached recommended
Storage: RWX volumes for multi-pod
```

### Scaling Horizontally

```bash
# Kubernetes example - Scale to 10 pods
kubectl scale deployment/wordpress --replicas=10

# All pods share same content via RWX volume
# Or bake content into image for immutable infrastructure
```

---

## 🔌 API & Hooks

### Filters

```php
// Modify loaded posts
add_filter('praison_posts', function($posts) {
    // Your modifications
    return $posts;
});

// Customize cache duration
add_filter('praison_cache_ttl', function($ttl, $post_type) {
    if ($post_type === 'lyrics') {
        return 7200; // 2 hours for lyrics
    }
    return $ttl;
}, 10, 2);

// Modify content directory
add_filter('praison_content_dir', function($dir) {
    return '/custom/content/path';
});
```

### Actions

```php
// Before posts load
add_action('praison_before_load_posts', function() {
    // Your code
});

// After posts load
add_action('praison_after_load_posts', function($posts) {
    // Process posts
}, 10, 1);

// Cache cleared
add_action('praison_cache_cleared', function() {
    // Additional cleanup
});
```

### Helper Functions

```php
// Get posts
$posts = praison_get_posts([
    'posts_per_page' => 10,
    'post_type' => 'lyrics'
]);

// Get stats
$stats = praison_get_stats();

// Clear cache
praison_clear_cache();
```

---

## 🛠️ WP-CLI Commands

```bash
# Check plugin status
wp plugin is-active praisonpressgit

# Clear cache
wp cache flush

# Get content statistics
wp eval "print_r(praison_get_stats());"

# Test content loading
wp eval "
\$posts = praison_get_posts();
echo 'Loaded ' . count(\$posts) . ' posts';
"
```

---

## 🎯 Performance Optimization

### 1. Enable Object Cache

```php
// Install Redis Object Cache plugin
wp plugin install redis-cache --activate

// Configure in wp-config.php
define('WP_REDIS_HOST', 'redis-service');
define('WP_REDIS_PORT', 6379);
```

### 2. Use Index System (for 10K+ files)

See [Containerized Deployment](#containerized-deployment) section above.

### 3. Optimize Cache TTL

```php
// In wp-config.php
define('PRAISON_CACHE_TTL', 7200); // 2 hours
```

### 4. Preload Content

```php
// Warm up cache on deployment
wp eval "praison_get_posts(['posts_per_page' => -1]);"
```

---

## 🐛 Troubleshooting

### Posts Not Appearing

**Check:**
1. File has correct YAML front matter
2. File is in correct directory: `/content/posts/`
3. Cache is cleared
4. Post status is `publish`

**Debug:**
```php
// Enable debug mode in wp-config.php
define('PRAISON_DEBUG', true);

// Check what's loaded
wp eval "var_dump(praison_get_posts());"
```

### Slow Performance

**Solutions:**
1. Enable caching (Redis/Memcached)
2. Use index system for 1K+ files
3. Increase cache TTL
4. Check file system performance

### Cache Not Clearing

```bash
# Force clear all caches
wp cache flush
wp transient delete --all

# Check permissions
chmod -R 755 /content/
```

### Content Not Updating

```bash
# Clear cache after content changes
wp cache flush

# Or use admin dashboard "Clear Cache" button
```

---

## 🔐 Security

All security best practices implemented:

- ✅ **Output Escaping**: All outputs use `esc_html()`, `esc_url()`, `esc_attr()`
- ✅ **Nonce Verification**: Form submissions protected with nonces
- ✅ **Capability Checks**: Admin features require `manage_options`
- ✅ **Timezone Safe**: Uses `gmdate()` instead of `date()`
- ✅ **SQL Safe**: No direct database queries (read-only from files)
- ✅ **Input Sanitization**: All user inputs sanitized

---

## 📚 Additional Resources

- **GitHub Repository**: [Your GitHub URL]
- **Documentation**: See PRAISONPRESS-README.md for technical details
- **Support**: WordPress.org support forums
- **Issues**: Report on GitHub

---

## 🤝 Contributing

Contributions welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

---

## 📝 Changelog

### Version 1.0.0 (2024-10-31)

- ✅ Initial release
- ✅ File-based content management
- ✅ Dynamic post type discovery
- ✅ YAML front matter support
- ✅ Markdown parsing
- ✅ Cache management
- ✅ Admin dashboard
- ✅ Index system for large datasets
- ✅ Docker/Kubernetes support
- ✅ Security hardening
- ✅ WP-CLI integration

---

## 👤 Author

**MervinPraison**  
Website: [https://mer.vin](https://mer.vin)  
GitHub: [Your GitHub Profile]

---

## 📄 License

GPL v2 or later

---

## ⭐ Support

If you find this plugin helpful, please:
- ⭐ Star the repository
- 📢 Share with others
- 🐛 Report issues
- 💡 Suggest features

---

## 📤 Export to Markdown

**Production-Ready Feature** - Convert your existing WordPress content to Markdown files with full metadata preservation. Tested with large-scale exports (50,000+ posts).

### Quick Export

**CLI Export (All post types):**
```bash
php wp-content/plugins/praisonpressgit/scripts/export-to-markdown.php
```

**Admin Panel Export:**
- Go to **PraisonPress → Export**
- Select post type (or "All Post Types")
- Choose batch size (100 recommended for 50K+ posts)
- Click "Start Export"
- Background processing with real-time progress tracking

### What Gets Exported

✅ **All Content & Metadata:**
- Post title, content, excerpt, status, dates
- Categories, tags, and ALL custom taxonomies
- Featured images
- ALL custom fields (including ACF fields)
- Author information

✅ **ACF (Advanced Custom Fields) Support:**
- Automatically detects ACF plugin
- Exports ALL ACF field types (repeaters, flexible content, galleries, relationships)
- Uses ACF API for proper formatting
- Works with 50+ field groups

✅ **Large Exports (50K+ posts):**
- Background processing (WP-Cron)
- Configurable batch sizes
- Real-time progress tracking
- Safe for shared hosting
- Page-closeable (runs in background)

### Export Output Format

```yaml
---
title: "Post Title"
slug: "post-slug"
author: "admin"
date: "2024-10-31 12:00:00"
status: "publish"
categories:
  - "Category 1"
tags:
  - "tag1"
# All custom taxonomies (artist, album, book, etc.)
artist:
  - "Artist Name"
# All custom fields including ACF
custom_fields:
  field_name: "value"
  acf_repeater:
    - item: "value1"
    - item: "value2"
---

# Content in Markdown

Your content here...
```

---

**Made with ❤️ for WordPress developers who love Git and Markdown**
