#!/bin/bash
# Simple WordPress.org ZIP creator - Only includes required files

cd "$(dirname "$0")/.."

zip -r praison-file-content-git.zip praison-file-content-git/ \
  -x "*.git*" \
  -x "*.DS_Store" \
  -x "praison-file-content-git/.gitignore" \
  -x "praison-file-content-git/create-zip.sh" \
  -x "praison-file-content-git/create-my-submissions-page.php" \
  -x "praison-file-content-git/.vscode/*" \
  -x "praison-file-content-git/site-config.ini" \
  -x "praison-file-content-git/*.bak"

echo "✅ ZIP created: praison-file-content-git.zip"
echo "Ready for WordPress.org submission"
