#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Switchboard — Remote Installer
#
# Deploys the plugin + relay to a WordPress site via SSH, configures
# everything, and activates.
#
# Usage:
#   ./bin/install.sh --host <ssh-host> --path <wp-root> [options]
#
# Examples:
#   # Password auth (prompted or via --password):
#   ./bin/install.sh --host codex@104.236.224.6 \
#       --path /home/.../public_html \
#       --password 'kafkot-sohfuq-1meBki'
#
#   # Key auth:
#   ./bin/install.sh --host user@server.com \
#       --path /var/www/html \
#       --key ~/.ssh/id_ed25519
#
# Required:
#   --host          SSH user@host
#   --path          Absolute path to the WordPress root on the remote server
#
# Optional:
#   --password      SSH password (uses sshpass)
#   --key           SSH private key path
#   --google-id     Google OAuth client ID  (prompted if omitted)
#   --google-secret Google OAuth client secret (prompted if omitted)
#   --openai-key    OpenAI API key (skipped if omitted)
#   --admin-email   WP user email to assign the pq_manager role (prompted if omitted)
#   --relay-domain  Domain for the relay (defaults to site URL)
#   --skip-relay    Don't deploy the relay (if it's hosted elsewhere)
#   --dry-run       Show what would happen without doing it
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Defaults
SSH_HOST=""
WP_PATH=""
SSH_PASS=""
SSH_KEY=""
GOOGLE_ID=""
GOOGLE_SECRET=""
OPENAI_KEY=""
ADMIN_EMAIL=""
RELAY_DOMAIN=""
SKIP_RELAY=false
DRY_RUN=false

# ── Parse arguments ─────────────────────────────────────────────────────

while [[ $# -gt 0 ]]; do
    case "$1" in
        --host)           SSH_HOST="$2";       shift 2 ;;
        --path)           WP_PATH="$2";        shift 2 ;;
        --password)       SSH_PASS="$2";       shift 2 ;;
        --key)            SSH_KEY="$2";        shift 2 ;;
        --google-id)      GOOGLE_ID="$2";      shift 2 ;;
        --google-secret)  GOOGLE_SECRET="$2";  shift 2 ;;
        --openai-key)     OPENAI_KEY="$2";     shift 2 ;;
        --admin-email)    ADMIN_EMAIL="$2";    shift 2 ;;
        --relay-domain)   RELAY_DOMAIN="$2";   shift 2 ;;
        --skip-relay)     SKIP_RELAY=true;     shift ;;
        --dry-run)        DRY_RUN=true;        shift ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

# ── Validate required args ──────────────────────────────────────────────

if [[ -z "$SSH_HOST" ]]; then
    echo "Error: --host is required (e.g., user@server.com)" >&2
    exit 1
fi

if [[ -z "$WP_PATH" ]]; then
    echo "Error: --path is required (absolute path to WordPress root)" >&2
    exit 1
fi

# ── Prompt for missing values ───────────────────────────────────────────

if [[ -z "$GOOGLE_ID" ]]; then
    read -rp "Google OAuth Client ID: " GOOGLE_ID
fi

if [[ -z "$GOOGLE_SECRET" ]]; then
    read -rp "Google OAuth Client Secret: " GOOGLE_SECRET
fi

if [[ -z "$ADMIN_EMAIL" ]]; then
    read -rp "WP admin email (will get pq_manager role): " ADMIN_EMAIL
fi

# ── Build SSH/rsync commands ────────────────────────────────────────────

SSH_OPTS="-o StrictHostKeyChecking=no"
if [[ -n "$SSH_KEY" ]]; then
    SSH_OPTS="$SSH_OPTS -i $SSH_KEY"
fi

ssh_cmd() {
    if [[ -n "$SSH_PASS" ]]; then
        SSHPASS="$SSH_PASS" sshpass -e ssh $SSH_OPTS "$SSH_HOST" "$@"
    else
        ssh $SSH_OPTS "$SSH_HOST" "$@"
    fi
}

rsync_cmd() {
    local src="$1" dst="$2"
    if [[ -n "$SSH_PASS" ]]; then
        SSHPASS="$SSH_PASS" sshpass -e rsync -avz \
            --exclude='.git' --exclude='.claude' --exclude='.DS_Store' --exclude='bin' \
            "$src" "$SSH_HOST:$dst" \
            -e "ssh $SSH_OPTS"
    else
        rsync -avz \
            --exclude='.git' --exclude='.claude' --exclude='.DS_Store' --exclude='bin' \
            "$src" "$SSH_HOST:$dst" \
            -e "ssh $SSH_OPTS"
    fi
}

# ── Verify connection ───────────────────────────────────────────────────

echo "── Testing SSH connection to $SSH_HOST..."
if ! ssh_cmd "echo 'OK'" >/dev/null 2>&1; then
    echo "Error: Cannot connect to $SSH_HOST" >&2
    exit 1
fi
echo "   Connected."

# ── Verify WordPress ───────────────────────────────────────────────────

echo "── Verifying WordPress at $WP_PATH..."
SITE_URL=$(ssh_cmd "cd '$WP_PATH' && wp option get siteurl 2>/dev/null" 2>/dev/null || true)
if [[ -z "$SITE_URL" ]]; then
    echo "Error: wp-cli not available or not a WordPress install at $WP_PATH" >&2
    exit 1
fi
echo "   Found: $SITE_URL"

# Determine relay domain
if [[ -z "$RELAY_DOMAIN" ]]; then
    RELAY_DOMAIN="$SITE_URL"
fi
RELAY_URL="${RELAY_DOMAIN%/}/relay"

# ── Generate encryption key ────────────────────────────────────────────

ENCRYPTION_KEY=$(openssl rand -hex 32)
echo "── Generated relay encryption key."

# ── Dry run check ───────────────────────────────────────────────────────

if $DRY_RUN; then
    echo ""
    echo "═══ DRY RUN — would perform these steps: ═══"
    echo ""
    echo "1. Rsync plugin to: $WP_PATH/wp-content/plugins/wp-priority-queue-plugin/"
    if ! $SKIP_RELAY; then
        echo "2. Rsync relay to: $WP_PATH/relay/"
        echo "3. Create relay .env with:"
        echo "     RELAY_GOOGLE_CLIENT_ID=$GOOGLE_ID"
        echo "     RELAY_GOOGLE_CLIENT_SECRET=<hidden>"
        echo "     RELAY_ENCRYPTION_KEY=$ENCRYPTION_KEY"
        echo "     RELAY_BASE_URL=$RELAY_URL"
    fi
    echo "4. Activate plugin"
    echo "5. Set WP options:"
    echo "     wp_pq_relay_url = $RELAY_URL"
    echo "     wp_pq_relay_encryption_key = $ENCRYPTION_KEY"
    echo "     wp_pq_google_client_id = $GOOGLE_ID"
    echo "     wp_pq_google_client_secret = <hidden>"
    if [[ -n "$OPENAI_KEY" ]]; then
        echo "     wp_pq_openai_api_key = <hidden>"
    fi
    echo "6. Assign pq_manager role to: $ADMIN_EMAIL"
    echo "7. Run database migrations"
    echo ""
    exit 0
fi

# ── Step 1: Deploy plugin ──────────────────────────────────────────────

PLUGIN_PATH="$WP_PATH/wp-content/plugins/wp-priority-queue-plugin/"

echo "── Deploying plugin..."
rsync_cmd "$PLUGIN_DIR/" "$PLUGIN_PATH"
echo "   Done."

# ── Step 2: Deploy relay ───────────────────────────────────────────────

if ! $SKIP_RELAY; then
    RELAY_PATH="$WP_PATH/relay/"

    echo "── Deploying relay..."
    rsync_cmd "$PLUGIN_DIR/relay/" "$RELAY_PATH"

    echo "── Writing relay .env..."
    ssh_cmd "cat > '$RELAY_PATH.env' << 'ENVEOF'
RELAY_GOOGLE_CLIENT_ID=$GOOGLE_ID
RELAY_GOOGLE_CLIENT_SECRET=$GOOGLE_SECRET
RELAY_ENCRYPTION_KEY=$ENCRYPTION_KEY
RELAY_BASE_URL=$RELAY_URL
ENVEOF"
    echo "   Done."
fi

# ── Step 3: Activate plugin ────────────────────────────────────────────

echo "── Activating plugin..."
ssh_cmd "cd '$WP_PATH' && wp plugin activate wp-priority-queue-plugin 2>/dev/null || echo 'Already active'"
echo "   Done."

# ── Step 4: Set WP options ─────────────────────────────────────────────

echo "── Configuring Switchboard options..."

ssh_cmd "cd '$WP_PATH' && \
    wp option update wp_pq_relay_url '$RELAY_URL' && \
    wp option update wp_pq_relay_encryption_key '$ENCRYPTION_KEY' && \
    wp option update wp_pq_google_client_id '$GOOGLE_ID' && \
    wp option update wp_pq_google_client_secret '$GOOGLE_SECRET'"

if [[ -n "$OPENAI_KEY" ]]; then
    ssh_cmd "cd '$WP_PATH' && wp option update wp_pq_openai_api_key '$OPENAI_KEY'"
fi

echo "   Done."

# ── Step 5: Assign manager role ────────────────────────────────────────

echo "── Assigning pq_manager role to $ADMIN_EMAIL..."
ROLE_RESULT=$(ssh_cmd "cd '$WP_PATH' && wp eval '
    \$user = get_user_by(\"email\", \"$ADMIN_EMAIL\");
    if (!\$user) { echo \"ERROR: No user found with email $ADMIN_EMAIL\"; exit(1); }
    \$user->add_role(\"pq_manager\");
    echo \"Assigned pq_manager to user \" . \$user->ID . \" (\" . \$user->user_login . \")\";
' 2>&1")
echo "   $ROLE_RESULT"

# ── Step 6: Trigger migrations ─────────────────────────────────────────

echo "── Running database migrations..."
ssh_cmd "cd '$WP_PATH' && wp eval 'WP_PQ_Installer::activate();' 2>/dev/null || true"
echo "   Done."

# ── Step 7: Add redirect URI to Google Cloud Console reminder ──────────

CALLBACK_URL="$RELAY_URL/callback.php"

echo ""
echo "══════════════════════════════════════════════════════════════════"
echo "  Switchboard installed successfully on $SITE_URL"
echo "══════════════════════════════════════════════════════════════════"
echo ""
echo "  Plugin:  active"
echo "  Relay:   $RELAY_URL"
echo "  Manager: $ADMIN_EMAIL"
echo ""
echo "  ⚠  REQUIRED: Add this redirect URI in Google Cloud Console"
echo "     (APIs & Services → Credentials → your OAuth client):"
echo ""
echo "     $CALLBACK_URL"
echo ""
echo "  Portal URL: $SITE_URL/portal"
echo "══════════════════════════════════════════════════════════════════"
