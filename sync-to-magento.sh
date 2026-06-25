#!/bin/bash
#
# Syncs the local dev plugin to the Magento vendor directory.
#
# Usage:
#   ./sync-to-magento.sh          # one-shot sync
#   ./sync-to-magento.sh --watch  # continuous sync using fswatch
#

DEV_DIR="$(cd "$(dirname "$0")" && pwd)"
VENDOR_DIR="$HOME/Sites/magento2/vendor/violetio/magento2"

if [ ! -d "$VENDOR_DIR" ]; then
    echo "ERROR: Vendor directory not found at $VENDOR_DIR"
    echo "Is the Magento project set up at ~/Sites/magento2?"
    exit 1
fi

sync_files() {
    rsync -av --delete \
        --exclude='.git' \
        --exclude='.gitignore' \
        --exclude='.gitattributes' \
        --exclude='.opencode' \
        --exclude='sync-to-magento.sh' \
        "$DEV_DIR/" "$VENDOR_DIR/"
    echo "--- synced at $(date '+%H:%M:%S') ---"
}

if [ "$1" = "--watch" ]; then
    if ! command -v fswatch &> /dev/null; then
        echo "ERROR: fswatch not installed. Run: brew install fswatch"
        exit 1
    fi
    echo "Watching $DEV_DIR for changes..."
    sync_files
    fswatch -o \
        --exclude='\.git' \
        --exclude='\.opencode' \
        --exclude='sync-to-magento\.sh' \
        "$DEV_DIR" | while read -r; do
        sync_files
    done
else
    sync_files
fi
