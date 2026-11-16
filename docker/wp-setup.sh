#!/bin/sh
#
# Prepare the local WordPress test site.
#
# This script is idempotent: it installs WordPress only when needed, keeps the
# site URL in sync with WP_URL, and activates the plugin if it is inactive.

set -eu

WP_PATH="${WP_PATH:-/var/www/html}"
WP_URL="${WP_URL:-http://localhost:8090}"
WP_TITLE="${WP_TITLE:-Newsletter Publisher Test}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-adminpass}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.test}"
PLUGIN_SLUG="newsletter-wp-plugin"
INSTALL_LOG="/tmp/nlwp-core-install.log"

log() {
	printf '[wp-setup] %s\n' "$*"
}

cd "$WP_PATH"

log "Waiting for wp-config.php..."
attempt=0
while [ ! -f "$WP_PATH/wp-config.php" ]; do
	attempt=$((attempt + 1))
	if [ "$attempt" -ge 60 ]; then
		log "Timed out waiting for wp-config.php."
		exit 1
	fi
	sleep 2
done

if wp core is-installed --path="$WP_PATH" >/dev/null 2>&1; then
	log "WordPress is already installed."
else
	log "Waiting for the database and installing WordPress at $WP_URL..."

	attempt=0
	until wp core install \
		--path="$WP_PATH" \
		--url="$WP_URL" \
		--title="$WP_TITLE" \
		--admin_user="$WP_ADMIN_USER" \
		--admin_password="$WP_ADMIN_PASSWORD" \
		--admin_email="$WP_ADMIN_EMAIL" \
		--skip-email >"$INSTALL_LOG" 2>&1; do
		attempt=$((attempt + 1))
		if [ "$attempt" -ge 60 ]; then
			log "Timed out installing WordPress. Last output:"
			cat "$INSTALL_LOG" >&2 || true
			exit 1
		fi
		sleep 2
	done

	log "WordPress installed."
fi

log "Setting site URL to $WP_URL..."
wp option update home "$WP_URL" --path="$WP_PATH" >/dev/null
wp option update siteurl "$WP_URL" --path="$WP_PATH" >/dev/null

if wp plugin is-active "$PLUGIN_SLUG" --path="$WP_PATH" >/dev/null 2>&1; then
	log "Newsletter Publisher is already active."
else
	log "Activating Newsletter Publisher..."
	wp plugin activate "$PLUGIN_SLUG" --path="$WP_PATH"
fi

wp rewrite structure '/%postname%/' --path="$WP_PATH" >/dev/null 2>&1 || true

log "Ready: $WP_URL/wp-admin"
log "Admin user: $WP_ADMIN_USER"
log "Admin password: $WP_ADMIN_PASSWORD"
