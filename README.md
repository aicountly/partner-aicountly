# partner-aicountly

AICOUNTLY **Partner Portal** — the partner-facing sign-in and dashboard for
`partner.aicountly.com`.

- Frontend: Vite + React 19 SPA (`web/`) served from the document root
- Backend: CodeIgniter 4 JSON API (`server-php/`) served from `/api`, PHP 8.2+
- Database: the **Partner Master owned by Engage** (`engage_partners` in the
  Engage PostgreSQL database) — this repository defines no partner schema of
  its own
- Auth: independent session-based login for partners (no JWT, no SSO, no
  connection to `my.aicountly.com`)

## There is no signup

Partner accounts are created, edited, activated/deactivated, deleted and given
credentials **only** in Engage (`engage.aicountly.org` → **Partners → Partner
master**). This portal has no signup page, no registration page, no public
account-creation endpoint, and no partner CRUD. The production deploy workflow
fails the build if a signup/registration route is ever added.

```
ENGAGE.AICOUNTLY.ORG
        |
        |  Add / Edit / Delete / Activate / Set password
        v
   PARTNER MASTER  (engage_partners)
        |
        v
   Partner database  (PostgreSQL)
        |
        v
PARTNER.AICOUNTLY.COM
        |
        |  Authentication (email + password, session)
        v
   Partner dashboard
```

A partner can sign in only when **all** of these hold. Every failure returns the
same generic message so the login form cannot be used to discover which email
addresses belong to a partner:

| Partner state in Engage | Sign-in |
|---|---|
| Active, password set | allowed |
| Wrong password | denied |
| Unknown email | denied |
| Inactive / deactivated | denied |
| Deleted (soft-deleted) | denied |
| No password set yet | denied |
| Locked after 5 failed attempts | denied for 15 minutes |

Deactivating or deleting a partner in Engage also ends any session that is
already open — the signed-in partner is re-checked against the Partner Master on
every request.

## Repository layout

```
partner-aicountly/
├── web/                            Vite + React 19 SPA  ->  public_html/
│   ├── src/pages/                  Login, Dashboard, Profile
│   ├── src/lib/api.js              axios: session cookie + CSRF header
│   ├── src/lib/auth.jsx            auth context (asks the API who you are)
│   └── public/.htaccess            SPA routing; never swallows /api
├── server-php/                     CodeIgniter 4 API    ->  public_html/api/
│   ├── index.php  .htaccess  spark  check-env.php  .env.example
│   └── app/
│       ├── Controllers/Api/V1/     Auth, Profile
│       ├── Filters/                PartnerAuth, Cors, CsrfToken
│       ├── Libraries/PartnerAuth   credential verification + session
│       └── Models/PartnersModel    read-side view of engage_partners
├── scripts/
│   ├── cpanel-rsync-api.filters    rsync rules (protect api/.env, writable/)
│   └── cpanel-post-deploy-api.sh   spark commands + verification on the server
└── .github/workflows/
    └── deploy-production.yml       "Deploy To cPanel Production" (manual only)
```

This mirrors the Engage layout, so both portals deploy the same way:

| Local | cPanel |
|---|---|
| `web/dist/` | `${PROD_SSH_REMOTE_ROOT}/` |
| `server-php/` | `${PROD_SSH_REMOTE_ROOT}/api/` |

## API

The SPA and the API are the same origin (`partner.aicountly.com` and
`partner.aicountly.com/api`), which is what keeps the session cookie
first-party. Responses follow the Engage contract:

```
{ "ok": true,  "data": ... }
{ "ok": false, "error": "message", "details": ... }
```

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/api/health` | public | Deployment probe (no secrets, no partner data) |
| POST | `/api/v1/auth/login` | public | Verify credentials, start the session |
| GET | `/api/v1/me` | partners | The signed-in partner |
| GET | `/api/v1/profile` | partners | Read-only partner details |
| POST | `/api/v1/auth/logout` | partners | Destroy the session |

There is deliberately no signup, registration or partner-write endpoint.

## Local setup

Two processes: the API and the Vite dev server. Vite proxies `/api` to the API
so the session cookie stays first-party, exactly as in production.

```bash
# API — http://127.0.0.1:8081
cd server-php
composer install
cp .env.example .env          # fill in the database settings below
php spark key:generate        # writes encryption.key into .env
php -S 127.0.0.1:8081 dev-server.php

# SPA — http://127.0.0.1:5173
cd web
npm install
npm run dev
```

Then open <http://127.0.0.1:5173>.

`php spark serve` is not used: the API is deployed flat into `public_html/api`
(no `public/` directory), so `dev-server.php` reproduces the production
`.htaccess` rules for PHP's built-in server.

Useful commands:

```bash
cd server-php
php check-env.php     # masked config dump + Partner Master connection test
php spark routes      # confirm no signup/registration route exists
php spark cache:clear # clear the file cache (also clears login throttling)
```

### Database setup

The portal does **not** have its own database. Point it at the same PostgreSQL
database Engage uses and it will read the `engage_partners` table:

```
PARTNER_DB_HOST     = 127.0.0.1
PARTNER_DB_PORT     = 5432
PARTNER_DB_NAME     = engage_aicountly
PARTNER_DB_USER     = <role that can read engage_partners>
PARTNER_DB_PASSWORD = <password>
PARTNER_DB_DRIVER   = Postgre
```

CodeIgniter's native `database.default.*` names work too; if both are present
the `PARTNER_DB_*` values win. Credentials come only from `.env` — nothing is
hardcoded.

The database role needs `SELECT` on `engage_partners` plus `UPDATE` on its login
bookkeeping columns (`last_login_at`, `last_login_ip`, `failed_attempts`,
`locked_until`). If you grant read-only access the portal still authenticates
correctly; it just logs a warning and skips the bookkeeping.

The `engage_partners` table itself is created by **Engage's** migrations
(`2026-08-11-000070_CreateEngagePartners`) — do not create it here.

These values go in `server-php/.env` locally, and in
`${PROD_SSH_REMOTE_ROOT}/api/.env` on the server.

### Migrations

This repository intentionally ships **no migrations**: the Partner Master schema
belongs to Engage, so there is no duplicate partner schema to keep in sync. The
post-deploy script checks `app/Database/Migrations/` and runs
`php spark migrate --all` only if this repository ever gains migration files of
its own. It always runs `php spark cache:clear`.

## Security

- Passwords are verified against the bcrypt hashes Engage writes with
  `password_hash()`; no plaintext password is ever stored or logged
- Session-based auth with a fresh session ID on login (fixation defence) and
  full session destruction on logout
- CSRF protection on every state-changing request. The token stays in the
  server-side session and is published as an `X-CSRF-TOKEN` response header
  that the SPA echoes back — it is deliberately never put in a
  JavaScript-readable cookie, so the session cookie keeps `HttpOnly`
- No token in `localStorage`: an XSS in the SPA cannot read the credential
- Login throttling: 10 attempts per minute per IP, plus a 15-minute account lock
  after 5 consecutive failures (an admin can clear it from Engage)
- Generic authentication errors — the form never reveals whether an account
  exists
- Secure cookies in production (`Secure`, `HttpOnly`, `SameSite=Strict`) and
  HTTPS forced when `CI_ENVIRONMENT=production`
- Output escaped in views; all database access goes through CodeIgniter's query
  builder (parameterised)
- `.env` is git-ignored, never committed, and never touched by deployment

## Production deployment

Deployment is **manual only**. There is no push trigger — pushing to any branch
never deploys.

```
GitHub → Actions → Deploy To cPanel Production → Run workflow
```

That is the whole flow — the workflow takes no inputs and asks for no confirmation.

### Required GitHub secrets

| Secret | Purpose |
|---|---|
| `PROD_SSH_HOST` | cPanel SSH host |
| `PROD_SSH_PORT` | cPanel SSH port |
| `PROD_SSH_USER` | cPanel SSH username |
| `PROD_SSH_PRIVATE_KEY` | Deploy key (PEM, no passphrase) |
| `PROD_SSH_REMOTE_ROOT` | Document root for partner.aicountly.com |

Nothing about the host, port, user, key or destination path is hardcoded in the
workflow.

### What the workflow does

1. Checks that all five `PROD_SSH_*` secrets are present
2. Checks out the repository
3. Sets up PHP 8.2 with `intl`, `mbstring`, `pgsql`, `pdo_pgsql`
4. `composer install --no-dev --optimize-autoloader`
5. Validates the app: lints every PHP file, asserts no signup/registration
   route, asserts no committed `.env`, asserts `.env.example` exists, loads the
   route table
6. Opens SSH (host key pinned via `ssh-keyscan`, falling back to `accept-new`
   only if the keyscan cannot reach the host) and verifies remote `php` and
   `rsync`, the deployment directory, and that the server's `.env` already exists
7. Records a SHA-256 fingerprint of the production `.env`
8. `rsync -avz --delete` to `${{ secrets.PROD_SSH_REMOTE_ROOT }}`, so files
   deleted from git are removed from production
9. Runs `scripts/cpanel-post-deploy.sh` over SSH: fixes `writable/` permissions,
   runs `php spark migrate --all` **if** this repo has migrations, runs
   `php spark cache:clear`, resets OPcache, then runs `check-env.php`
10. Re-fingerprints the production `.env` and **fails the deployment** if it
    changed
11. Verifies the deployed files and that `REVISION` matches the deployed commit
12. Probes `https://partner.aicountly.com/health` and fails if it does not
    return HTTP 200

Any failing step fails the workflow.

### What `--delete` will never remove

`scripts/cpanel-rsync.filters` protects, in addition to the explicit
`--exclude='.env'`:

- `.env` (protected *and* excluded, so it is never copied or deleted)
- `writable/session/`, `writable/cache/`, `writable/logs/`, `writable/uploads/`,
  `writable/debugbar/` — partner sessions survive a release
- cPanel-owned paths that sit in the document root: `cgi-bin/`, `.well-known/`,
  `php.ini`, `.user.ini`, `error_log`, `.htpasswd*`, `.cpanel/`, `.quarantine/`

### Production environment file

The production `.env` lives **only** on the server, at
`${PROD_SSH_REMOTE_ROOT}/api/.env`. It is:

- never committed (see `.gitignore`)
- never copied from the repository
- never deleted by `rsync --delete`
- never edited by the post-deploy script
- verified byte-for-byte after every deployment

### First deploy

The deployment ships the application **and** `.env.example`, then stops at the
post-deploy step because `.env` does not exist yet — the same sequence the
Engage deployment uses. So the first run is expected to end red, with the
command list you need printed in the log:

```
ERROR: missing .env in /home/.../public_html/api
    cd /home/.../public_html/api
    cp .env.example .env
    nano .env
    chmod 600 .env
```

It even prints a freshly generated `encryption.key` value to paste in. Follow
those steps over SSH or in the cPanel File Manager, then re-run the workflow —
the second run goes green.

Deployment never creates or edits `.env`: production secrets are only ever
entered on the server.

```bash
cd "$PROD_SSH_REMOTE_ROOT/api"
cp .env.example .env
nano .env            # set app.baseURL, PARTNER_DB_*, encryption.key
chmod 600 .env
```

Minimum required values:

```
CI_ENVIRONMENT      = production
app.baseURL         = 'https://partner.aicountly.com/api/'
PARTNER_DB_HOST     = 127.0.0.1        # the host running Engage's PostgreSQL
PARTNER_DB_PORT     = 5432
PARTNER_DB_NAME     = engage_aicountly # the SAME database Engage uses
PARTNER_DB_USER     = <db role>
PARTNER_DB_PASSWORD = <db password>
PARTNER_DB_DRIVER   = Postgre
encryption.key      = <32 random bytes, see below>
cookie.secure       = true
```

Generate the encryption key with either:

```bash
php spark key:generate                                   # once vendor/ is deployed
php -r "echo 'hex2bin:'.bin2hex(random_bytes(32)).PHP_EOL;"   # works before the first deploy
```

Then re-run the workflow. After the first successful deploy you can verify the
configuration on the server with `php check-env.php`, which masks secrets and
tests the connection to the Partner Master.

### cPanel requirements

- PHP **8.2+** selected for the domain in MultiPHP, with `intl`, `mbstring`,
  `json`, `curl`, `pgsql` and `pdo_pgsql` enabled
- SSH access and `rsync` available for `PROD_SSH_USER`
- The deploy key authorised for that user
- Document root for `partner.aicountly.com` set to `PROD_SSH_REMOTE_ROOT`
- `mod_rewrite` enabled (the shipped `.htaccess` needs it)
- `writable/` writable by the web user (the post-deploy script sets 775/777)
