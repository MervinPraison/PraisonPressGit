#!/bin/bash

# Create WordPress.org submission ZIP
# This excludes hidden files and dev files that WP.org doesn't allow

echo "Creating WordPress.org submission ZIP..."

cd "$(dirname "$0")/.."

zip -r praisonpressgit-1.0.0.zip praisonpressgit/ \
  -x "*.git*" \
  -x "*.gitignore" \
  -x "*/.DS_Store" \
  -x "*/node_modules/*" \
  -x "*/vendor/*" \
  -x "*/.vscode/*" \
  -x "*/.idea/*" \
  -x "*CREATE-WP-ORG-ZIP.sh"

echo ""
echo "✅ ZIP created: praisonpressgit-1.0.0.zip"
echo ""
echo "This ZIP is ready for WordPress.org submission!"
echo "It excludes:"
echo "  - .git directory"
echo "  - .gitignore (hidden files not allowed)"
echo "  - .DS_Store and other OS files"
echo "  - node_modules, vendor"
echo "  - IDE config files"
echo ""
echo "Submit to: https://wordpress.org/plugins/developers/add/"
