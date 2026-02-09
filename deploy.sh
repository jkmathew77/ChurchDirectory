#!/bin/bash
#
# Community Directory — Deploy Script
#
# First-time setup:  bash deploy.sh /home/username/public_html
# Update plugin:     bash deploy.sh
# Rollback:          bash deploy.sh --rollback
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/.deploy-config"
PLUGIN_SRC="$SCRIPT_DIR/plugin/community-directory"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

info()  { echo -e "${GREEN}[OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!!]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Read version from plugin header
get_version() {
    grep -m1 'Version:' "$PLUGIN_SRC/community-directory.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]'
}

# ─── First-time setup ───
setup() {
    local wp_path="$1"

    # Validate WordPress installation
    if [ ! -f "$wp_path/wp-config.php" ]; then
        error "No WordPress installation found at $wp_path (wp-config.php missing)"
    fi

    if [ ! -d "$wp_path/wp-content/plugins" ]; then
        error "Plugins directory not found at $wp_path/wp-content/plugins"
    fi

    # Validate plugin source exists
    if [ ! -f "$PLUGIN_SRC/community-directory.php" ]; then
        error "Plugin source not found at $PLUGIN_SRC"
    fi

    local link_path="$wp_path/wp-content/plugins/community-directory"

    # Remove any existing directory or symlink
    if [ -L "$link_path" ]; then
        warn "Removing existing symlink at $link_path"
        rm "$link_path"
    elif [ -d "$link_path" ]; then
        warn "Removing existing directory at $link_path (was likely from ZIP upload)"
        rm -rf "$link_path"
    fi

    # Also clean up any duplicate folders from failed WordPress uploads
    for dup in "$wp_path/wp-content/plugins/community-directory-"*; do
        if [ -d "$dup" ]; then
            warn "Removing duplicate folder: $(basename "$dup")"
            rm -rf "$dup"
        fi
    done

    # Create symlink
    ln -s "$PLUGIN_SRC" "$link_path"

    # Save config
    echo "$wp_path" > "$CONFIG_FILE"

    local version
    version=$(get_version)

    echo ""
    info "Symlink created:"
    echo "    $link_path -> $PLUGIN_SRC"
    info "Plugin version: $version"
    echo ""
    echo "Next steps:"
    echo "  1. Go to WP Admin > Plugins"
    echo "  2. Find 'Community Directory' and click 'Activate'"
    echo ""
    echo "To update the plugin later, just run:"
    echo "  cd $SCRIPT_DIR && bash deploy.sh"
}

# ─── Update (git pull) ───
update() {
    if [ ! -f "$CONFIG_FILE" ]; then
        error "Not configured yet. Run: bash deploy.sh /path/to/wordpress"
    fi

    local wp_path
    wp_path=$(cat "$CONFIG_FILE")
    local link_path="$wp_path/wp-content/plugins/community-directory"

    # Verify symlink is intact
    if [ ! -L "$link_path" ]; then
        error "Symlink missing at $link_path. Run setup again: bash deploy.sh $wp_path"
    fi

    local old_version
    old_version=$(get_version)

    # Pull latest from git
    echo "Pulling latest changes..."
    cd "$SCRIPT_DIR"
    git pull origin main

    local new_version
    new_version=$(get_version)

    echo ""
    if [ "$old_version" != "$new_version" ]; then
        info "Updated: v$old_version -> v$new_version"
    else
        info "Up to date: v$new_version"
    fi

    # Verify plugin file is accessible through symlink
    if [ -f "$link_path/community-directory.php" ]; then
        info "Plugin accessible at $link_path"
    else
        error "Plugin file not accessible through symlink!"
    fi
}

# ─── Rollback ───
rollback() {
    echo "Recent commits:"
    git log --oneline -10
    echo ""
    echo "To rollback, run:"
    echo "  git checkout <commit-hash>"
    echo ""
    echo "To return to latest after rollback:"
    echo "  git checkout main && git pull"
}

# ─── Main ───
case "${1:-}" in
    --rollback)
        rollback
        ;;
    "")
        update
        ;;
    *)
        setup "$1"
        ;;
esac
