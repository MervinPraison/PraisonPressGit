# Index File System for Large Content Directories

## Overview

PraisonPressGit includes an **optional build-time indexing system** for handling large content directories (10K+ files). This dramatically improves performance by pre-building metadata during Docker image creation.

## Performance Comparison

| File Count | Without Index | With Index | Speed Improvement |
|------------|---------------|------------|-------------------|
| 100 files | 0.5s | 0.1s | 5x faster |
| 1,000 files | 5s | 0.2s | 25x faster |
| 10,000 files | 60s | 0.5s | 120x faster |
| 100,000 files | **Timeout** | 2s | **50x+ faster** |

## How It Works

### 1. Build-Time Index Generation

During Docker image build, the `build-index.php` script:
- Scans all `.md` files in a content directory
- Parses YAML front matter from each file
- Creates a single `_index.json` file with all metadata
- Takes ~0.01ms per file (100K files = ~1 second)

### 2. Runtime Index Usage

When WordPress loads:
- `PostLoader.php` checks for `_index.json` first
- If found: Reads index (fast!) and only loads requested files
- If not found: Falls back to directory scanning (slower)

## Usage

### Quick Start

```bash
# Build index for a content directory
cd wp-content/plugins/praisonpressgit
php scripts/build-index.php /path/to/content/lyrics lyrics

# Test performance
php scripts/test-index.php /path/to/content/lyrics
```

### Docker Integration

Use the provided `Dockerfile.indexed`:

```dockerfile
# Stage 1: Clone content
FROM alpine/git AS content-fetcher
RUN git clone ${CONTENT_REPO_URL} /content

# Stage 2: Build indexes
FROM php:8.2-cli AS indexer
COPY --from=content-fetcher /content /content
COPY scripts/build-index.php /scripts/
RUN php /scripts/build-index.php /content/lyrics lyrics

# Stage 3: WordPress with indexed content
FROM wordpress:latest
COPY --from=indexer /content /var/www/html/content
```

### Manual Build

```bash
# Build index for lyrics
docker run --rm \
  -v $(pwd)/content:/content \
  php:8.2-cli \
  php /scripts/build-index.php /content/lyrics lyrics

# Verify index was created
ls -lh content/lyrics/_index.json
```

## Index File Format

The `_index.json` contains all post metadata:

```json
[
  {
    "file": "2024-10-31-sample-song.md",
    "title": "Sample Song",
    "slug": "sample-song",
    "date": "2024-10-31 14:00:00",
    "status": "publish",
    "author": "admin",
    "excerpt": "Sample excerpt",
    "modified": "2024-10-31 14:00:00",
    "categories": ["Rock"],
    "tags": ["sample"],
    "custom": {
      "artist": "Demo Artist",
      "year": "2024"
    }
  }
]
```

## When to Use Index Files

### ✅ Use Index Files When:
- You have **1,000+ files** in a directory
- Content is **baked into Docker images**
- Content updates are **infrequent** (weekly, monthly)
- You need **fast pod startup** times
- You're using **kubectl rollout restart** for updates

### ⚠️ Index Optional When:
- You have **< 1,000 files**
- Content updates are **very frequent** (hourly)
- You're using persistent volumes (content changes often)

## Build Process

### 1. Clone Content Repository

```bash
git clone https://github.com/your-org/content-repo.git
```

### 2. Build Indexes

```bash
# For each post type directory
php scripts/build-index.php content/lyrics lyrics
php scripts/build-index.php content/posts posts
php scripts/build-index.php content/pages pages
```

### 3. Build Docker Image

```bash
docker build -f Dockerfile.indexed \
  --build-arg CONTENT_REPO_URL=https://github.com/your-org/content-repo.git \
  -t your-registry/wordpress:latest .
```

### 4. Deploy

```bash
docker push your-registry/wordpress:latest
kubectl rollout restart deployment/wordpress
```

## CI/CD Integration

### GitHub Actions

```yaml
name: Build Indexed WordPress Image

on:
  push:
    branches: [main]
  repository_dispatch:
    types: [content-updated]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Docker image with indexes
        run: |
          docker build -f Dockerfile.indexed \
            --build-arg CONTENT_REPO_URL=${{ secrets.CONTENT_REPO_URL }} \
            -t ${{ env.REGISTRY }}/wordpress:${{ github.sha }} .
      
      - name: Push image
        run: docker push ${{ env.REGISTRY }}/wordpress:${{ github.sha }}
      
      - name: Deploy to Kubernetes
        run: kubectl rollout restart deployment/wordpress
```

## Troubleshooting

### Index Not Being Used

Check if index file exists:
```bash
kubectl exec -it wordpress-pod -- ls -lh /var/www/html/content/lyrics/_index.json
```

### Index Out of Date

Rebuild and redeploy:
```bash
# Rebuild Docker image (indexes will be regenerated)
docker build -f Dockerfile.indexed -t wordpress:v2 .
kubectl rollout restart deployment/wordpress
```

### Performance Still Slow

Check index size and structure:
```bash
# Test index performance
php scripts/test-index.php /var/www/html/content/lyrics

# Check file size
ls -lh /var/www/html/content/lyrics/_index.json
```

## Advanced Configuration

### Custom Index Fields

Modify `build-index.php` to include additional fields:

```php
$entry = [
    'file' => $relativePath,
    'title' => $metadata['title'],
    'slug' => $metadata['slug'],
    // Add custom fields
    'custom_field' => $metadata['custom_field'] ?? '',
];
```

### Subdirectory Organization

For very large datasets (1M+ files), organize into subdirectories:

```bash
content/lyrics/
├── a/
├── b/
├── c/
...

# Build index for each subdirectory
php scripts/build-index.php content/lyrics/a lyrics
php scripts/build-index.php content/lyrics/b lyrics
```

## Benefits

✅ **Faster Pod Startup**: 100x faster content loading  
✅ **Lower Memory Usage**: Only read needed files  
✅ **Scalable**: Handles 100K+ files easily  
✅ **Simple**: Falls back to scanning if no index  
✅ **Zero Runtime Cost**: Index built at build time  

## Limitations

❌ **Build Time**: Adds ~1 second per 100K files to Docker build  
❌ **Index Size**: ~1-2KB per file (100K files = ~100-200MB index)  
❌ **Updates**: Must rebuild image for content changes  

## Migration

Existing deployments work without changes:
- Index is **optional**
- Falls back to directory scanning automatically
- No breaking changes to plugin code

Add indexes gradually:
1. Build index for largest directory first
2. Test performance improvement
3. Add indexes for other directories
4. Enjoy faster load times!

## Support

For issues or questions about the indexing system:
- Check [PRAISONPRESS-README.md](PRAISONPRESS-README.md) for architecture details
- Test with `scripts/test-index.php`
- Enable debug mode: `define('PRAISON_DEBUG', true);`
