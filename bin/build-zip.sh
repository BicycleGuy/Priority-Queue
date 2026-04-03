#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Switchboard — Build installable ZIP
#
# Creates a wp-priority-queue-plugin.zip ready for WP Admin → Plugins → Upload.
#
# Usage:
#   ./bin/build-zip.sh            # outputs to ./dist/
#   ./bin/build-zip.sh ~/Desktop  # outputs to specified directory
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_NAME="wp-priority-queue-plugin"

# Output directory
OUT_DIR="${1:-$PLUGIN_DIR/dist}"
mkdir -p "$OUT_DIR"

# Read version from plugin header
VERSION=$(grep -m1 'Version:' "$PLUGIN_DIR/wp-priority-queue-plugin.php" | sed 's/.*Version: *//' | tr -d '[:space:]')
if [[ -z "$VERSION" ]]; then
    VERSION="0.0.0"
fi

ZIP_FILE="$OUT_DIR/${PLUGIN_NAME}-${VERSION}.zip"

echo "Building Switchboard v${VERSION}..."

# Create temp staging directory
STAGE=$(mktemp -d)
STAGE_PLUGIN="$STAGE/$PLUGIN_NAME"
mkdir -p "$STAGE_PLUGIN"

# Copy plugin files, excluding dev/build artifacts
rsync -a \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.claude' \
    --exclude='.DS_Store' \
    --exclude='node_modules' \
    --exclude='bin' \
    --exclude='dist' \
    --exclude='mockups' \
    --exclude='docs' \
    --exclude='.env' \
    --exclude='*.md' \
    "$PLUGIN_DIR/" "$STAGE_PLUGIN/"

# Remove .env from relay (should never ship with credentials)
rm -f "$STAGE_PLUGIN/relay/.env"

# Build the zip
cd "$STAGE"
zip -r "$ZIP_FILE" "$PLUGIN_NAME/" -x "*/.DS_Store" "*/._*"

# Cleanup
rm -rf "$STAGE"

SIZE=$(du -h "$ZIP_FILE" | cut -f1)
echo ""
echo "Done: $ZIP_FILE ($SIZE)"
echo ""
echo "Install:"
echo "  1. WP Admin → Plugins → Add New → Upload Plugin"
echo "  2. Choose $ZIP_FILE"
echo "  3. Activate"
echo "  4. Complete the setup wizard"
