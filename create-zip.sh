#!/bin/bash
# Simple WordPress.org ZIP creator - Only includes required files

cd "$(dirname "$0")/.."

zip -r praisonai-git-posts.zip praisonai-git-posts/ \
  -x "*.git*" \
  -x "*.DS_Store" \
  -x "praisonai-git-posts/.gitignore" \
  -x "praisonai-git-posts/create-zip.sh" \
  -x "praisonai-git-posts/create-my-submissions-page.php" \
  -x "praisonai-git-posts/.vscode/*" \
  -x "praisonai-git-posts/site-config.ini" \
  -x "praisonai-git-posts/*.bak"

echo "✅ ZIP created: praisonai-git-posts.zip"
echo "Ready for WordPress.org submission"
