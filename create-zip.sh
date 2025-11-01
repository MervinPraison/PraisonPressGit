#!/bin/bash
# Simple WordPress.org ZIP creator - Only includes required files

cd "$(dirname "$0")/.."

zip -r praisonpressgit.zip praisonpressgit/ \
  -x "*.git*" \
  -x "*.DS_Store" \
  -x "praisonpressgit/.gitignore"

echo "✅ ZIP created: praisonpressgit.zip"
echo "Ready for WordPress.org submission"
