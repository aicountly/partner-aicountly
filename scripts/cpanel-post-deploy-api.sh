#!/usr/bin/env bash
# Post-deploy hook for the AICOUNTLY Partner Portal API on cPanel (run over SSH
# after rsync). Never creates, edits or overwrites .env — production secrets are
# managed only on the server.
#
# Usage: bash cpanel-post-deploy-api.sh /path/to/public_html/api

set -euo pipefail

APP_DIR="${1:-.}"
cd "$APP_DIR"

echo "==> Post-deploy in $(pwd)"

if [ -f .env ]; then
  echo ".env present — leaving server configuration untouched."
else
  echo "ERROR: missing .env in $(pwd)"
  echo
  echo "The application files (including .env.example) have just been deployed,"
  echo "so you can now create .env on the server and re-run the workflow:"
  echo
  echo "    cd $(pwd)"
  echo "    cp .env.example .env"
  echo "    nano .env          # or edit it in the cPanel File Manager"
  echo "    chmod 600 .env"
  echo
  echo "Set at least:"
  echo "    CI_ENVIRONMENT      = production"
  echo "    app.baseURL         = 'https://partner.aicountly.com/api/'"
  echo "    PARTNER_DB_*        = a database dedicated to this portal (this is the only place partner data lives)"
  echo "    PARTNER_ADMIN_KEY   = a random secret; set the SAME value as Engage's PARTNER_PORTAL_ADMIN_KEY"
  echo "    encryption.key      = $(php -r 'echo "hex2bin:".bin2hex(random_bytes(32));' 2>/dev/null || echo '<32 random bytes>')"
  echo "    cookie.secure       = true"
  echo
  echo "Deployment never creates or edits .env — production secrets stay on the server."
  exit 1
fi

if [ ! -d vendor/codeigniter4/framework ]; then
  echo "ERROR: vendor/codeigniter4/framework missing — the deployment did not include Composer dependencies."
  exit 1
fi

echo "==> Ensuring writable directories"
mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar
chmod -R 775 writable 2>/dev/null || chmod -R 777 writable

echo "==> Database migrations"
# This portal owns the Partner Master schema (table: partners). Nothing else
# creates or migrates it.
CI_ENVIRONMENT=production php spark migrate --all

echo "==> Clearing caches"
CI_ENVIRONMENT=production php spark cache:clear

if php -r 'exit(function_exists("opcache_reset") ? 0 : 1);'; then
  php -r 'opcache_reset(); echo "OPcache reset\n";' || true
fi

echo "==> Verifying the portal can reach the Partner Master"
php check-env.php

chmod 600 .env 2>/dev/null || true

echo "==> Post-deploy complete (.env was not modified)."
