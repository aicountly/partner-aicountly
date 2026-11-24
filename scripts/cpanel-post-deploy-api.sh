#!/usr/bin/env bash
# Post-deploy hook for the AICOUNTLY Partner Portal API on cPanel (run over SSH
# after rsync). Never creates, edits or overwrites .env — production secrets are
# managed only on the server.
#
# Usage: bash cpanel-post-deploy-api.sh /path/to/public_html/api

set -euo pipefail

# S01 (moved off cPanel 2026-11): the deploy identity's sandbox has no `php` on
# PATH; the CLI is Remi's /opt/remi/php84/root/usr/bin/php. Take the first PHP
# that is >= 8.2 (composer.json) with pgsql (CodeIgniter's Postgre driver), so
# the deploy neither guesses one path nor migrates with a PHP that cannot reach
# the DB. PHP_BIN, when set, is used only if it passes the same check.
# Same search as console-react-app server-php/bin/ops/find-php.sh.
php_ok() {
  [ -n "$1" ] && [ -x "$1" ] || return 1
  "$1" -r 'exit(PHP_VERSION_ID >= 80200 && extension_loaded("pgsql") ? 0 : 1);' >/dev/null 2>&1
}

find_php() {
  if [ -n "${PHP_BIN:-}" ]; then
    php_ok "$PHP_BIN" && { echo "$PHP_BIN"; return 0; }
    echo "ERROR: PHP_BIN=$PHP_BIN is not PHP >= 8.2 with pgsql" >&2
    return 1
  fi
  local c
  for c in \
    "$(command -v php 2>/dev/null || true)" \
    /opt/remi/php84/root/usr/bin/php /opt/remi/php83/root/usr/bin/php /opt/remi/php82/root/usr/bin/php \
    /usr/bin/php84 /usr/bin/php83 /usr/bin/php82 \
    /usr/local/bin/php /usr/bin/php \
    /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php; do
    if php_ok "$c"; then
      echo "$c"
      return 0
    fi
  done
  echo "ERROR: no PHP CLI >= 8.2 with pgsql found (looked on PATH, in /opt/remi/php8x, /usr/bin/php8x, /usr/local/bin, /usr/bin and /opt/cpanel)" >&2
  return 1
}

PHP="$(find_php)" || exit 1

APP_DIR="${1:-.}"
cd "$APP_DIR"

echo "==> Post-deploy in $(pwd)"
echo "Using PHP CLI: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

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
  echo "    encryption.key      = $("$PHP" -r 'echo "hex2bin:".bin2hex(random_bytes(32));' 2>/dev/null || echo '<32 random bytes>')"
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
CI_ENVIRONMENT=production "$PHP" spark migrate --all

echo "==> Clearing caches"
CI_ENVIRONMENT=production "$PHP" spark cache:clear

if "$PHP" -r 'exit(function_exists("opcache_reset") ? 0 : 1);'; then
  "$PHP" -r 'opcache_reset(); echo "OPcache reset\n";' || true
fi

echo "==> Verifying the portal can reach the Partner Master"
"$PHP" check-env.php

chmod 600 .env 2>/dev/null || true

echo "==> Post-deploy complete (.env was not modified)."
