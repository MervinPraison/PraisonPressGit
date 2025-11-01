# PraisonPressGit

**PraisonPressGit** - A WordPress Must-Use (MU) Plugin that loads content from files (Markdown, JSON, YAML) without database writes, with Git-based version control.

## Features

- **File-Based Content Management**: Store posts, pages, and custom post types as Markdown files
- **Version Control Ready**: Designed to work with Git for tracking content changes
- **No Database Writes**: Read-only approach - content stays in files
- **Dynamic Post Type Discovery**: Automatically registers custom post types based on directory structure
- **Custom URL Routing**: Support for custom post type routing (e.g., `/recipes/xxx`, `/posts/xxx`)
- **Cache Management**: Built-in caching system for optimal performance
- **Front Matter Support**: YAML front matter for metadata
- **Markdown Parsing**: Full Markdown support with automatic HTML conversion

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

## Author

**MervinPraison**  
Website: [https://mer.vin](https://mer.vin)

## License

GPL v2 or later

## Version

1.0.0
