#!/usr/bin/env bash
# Post-deploy hook for the AICOUNTLY Partner Portal on cPanel (run over SSH
# after rsync). Never creates, edits or overwrites .env — production secrets are
# managed only on the server.
#
# Usage: bash cpanel-post-deploy.sh /path/to/document_root

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
  echo "    app.baseURL         = 'https://partner.aicountly.com/'"
  echo "    PARTNER_DB_*        = the PostgreSQL database Engage uses (engage_partners lives there)"
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
# The Partner Portal owns no schema of its own: the Partner Master
# (engage_partners) is created and migrated by Engage. Migrations run only if
# this repository ever gains its own migration files.
shopt -s nullglob
MIGRATIONS=(app/Database/Migrations/*.php)
shopt -u nullglob
if [ ${#MIGRATIONS[@]} -gt 0 ]; then
  echo "Found ${#MIGRATIONS[@]} migration file(s) — running php spark migrate --all"
  CI_ENVIRONMENT=production php spark migrate --all
else
  echo "No migrations in this repository — skipping (schema is owned by Engage)."
fi

echo "==> Clearing caches"
CI_ENVIRONMENT=production php spark cache:clear

if php -r 'exit(function_exists("opcache_reset") ? 0 : 1);'; then
  php -r 'opcache_reset(); echo "OPcache reset\n";' || true
fi

echo "==> Verifying the portal can reach the Partner Master"
php check-env.php

chmod 600 .env 2>/dev/null || true

echo "==> Post-deploy complete (.env was not modified)."
