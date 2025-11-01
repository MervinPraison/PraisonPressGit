#!/bin/bash

# Create WordPress.org submission ZIP (clean method)
# This excludes optional build tools that are not required for WordPress.org

echo "Creating WordPress.org submission ZIP (clean)..."

cd "$(dirname "$0")/.."

# Create temp directory
TEMP_DIR=$(mktemp -d)
TARGET_DIR="$TEMP_DIR/praisonpressgit"

echo "Copying files to temporary directory..."

# Create target structure
mkdir -p "$TARGET_DIR"

# Copy core plugin files
cp praisonpressgit/praisonpressgit.php "$TARGET_DIR/"
cp praisonpressgit/LICENSE "$TARGET_DIR/"
cp praisonpressgit/README.md "$TARGET_DIR/"
cp praisonpressgit/readme.txt "$TARGET_DIR/"
cp praisonpressgit/PRAISONPRESS-README.md "$TARGET_DIR/"

# Copy src directory
cp -r praisonpressgit/src "$TARGET_DIR/"

# Copy empty directories
mkdir -p "$TARGET_DIR/views"
mkdir -p "$TARGET_DIR/assets/css"
mkdir -p "$TARGET_DIR/assets/js"

# Create ZIP from temp directory
cd "$TEMP_DIR"
zip -r praisonpressgit-1.0.0.zip praisonpressgit/

# Move ZIP to original location
mv praisonpressgit-1.0.0.zip "$(dirname "$0")/../"

# Cleanup
cd - > /dev/null
rm -rf "$TEMP_DIR"

echo ""
echo "✅ ZIP created: praisonpressgit-1.0.0.zip"
echo ""
echo "This ZIP is ready for WordPress.org submission!"
echo "It includes ONLY:"
echo "  ✅ Core plugin files (praisonpressgit.php, README, LICENSE)"
echo "  ✅ Source code (src/ directory)"
echo "  ✅ Documentation (README.md, readme.txt, PRAISONPRESS-README.md)"
echo "  ✅ Empty asset directories"
echo ""
echo "It EXCLUDES (available separately on GitHub):"
echo "  ❌ scripts/ (optional build tools)"
echo "  ❌ Dockerfile.indexed (optional - for large datasets)"
echo "  ❌ README-INDEX.md (advanced documentation)"
echo "  ❌ .git, .gitignore, and other dev files"
echo ""
echo "Submit to: https://wordpress.org/plugins/developers/add/"
