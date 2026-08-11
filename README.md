# partner-aicountly

AICOUNTLY **Partner Portal** — the partner-facing sign-in and dashboard for
`partner.aicountly.com`.

- Frontend: Vite + React 19 SPA (`web/`) served from the document root
- Backend: CodeIgniter 4 JSON API (`server-php/`) served from `/api`, PHP 8.2+
- Database: **this portal owns the Partner Master** (table `partners`) — it is
  the single source of truth for partner data. Engage stores no partner rows
  of its own; its Add/Edit/Delete/List screens call this portal's admin API
- Auth: independent session-based login for partners (no JWT, no SSO, no
  connection to `my.aicountly.com`)

## Ownership: this portal owns the data, Engage owns the admin screen

This is the single source of truth for partner data. There is no signup,
registration or public account-creation endpoint here, and no other service
holds a copy of this table — Engage's Partner Master screens
(`engage.aicountly.org` → **Partners → Partner master**) do not store partner
rows themselves; they call this portal's **admin API** to Add/Edit/Delete/List,
authenticated by a shared secret rather than the partner-facing session
cookie. The production deploy workflow fails the build if a signup/registration
route is ever added to the *public* API.

```
ENGAGE.AICOUNTLY.ORG                              PARTNER.AICOUNTLY.COM
  Partners → Partner master (React UI)              owns the data
        |                                                 ^
        | superadmin JWT                                  |
        v                                                  |
  PartnersController  ──── X-Partner-Admin-Key ────  Admin API
  (no local table)         (shared secret)           (v1/admin/partners/*)
                                                             |
                                                             v
                                                     Partner database
                                                     (table: partners)
                                                             |
                                                             v
                                                     Public API
                                                     (v1/auth/login, session)
                                                             |
                                                             v
                                                       Partner dashboard
```

A partner can sign in only when **all** of these hold. Every failure returns the
same generic message so the login form cannot be used to discover which email
addresses belong to a partner:

| Partner state | Sign-in |
|---|---|
| Active, password set | allowed |
| Wrong password | denied |
| Unknown email | denied |
| Inactive / deactivated | denied |
| Deleted (soft-deleted) | denied |
| No password set yet | denied |
| Locked after 5 failed attempts | denied for 15 minutes |

Deactivating or deleting a partner (from Engage's admin screen, which writes
here through the admin API) also ends any session that is already open — the
signed-in partner is re-checked against this table on every request.

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
│       ├── Controllers/Api/V1/         Auth, Profile (public — partner-facing)
│       ├── Controllers/Api/V1/Admin/   Partners CRUD (Engage-facing, admin key)
│       ├── Filters/                    PartnerAuth, AdminToken, Cors, CsrfToken
│       ├── Libraries/PartnerAuth       credential verification + session
│       ├── Models/PartnersModel        the Partner Master (table: partners)
│       └── Database/Migrations/        owns this schema — nothing else creates it
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

There is deliberately no signup, registration or public write endpoint.

### Admin API (Engage-facing)

Everything under `/api/v1/admin/partners` is called only by Engage's Partner
Master screen, never by a browser directly. It is authenticated by a shared
secret (`X-Partner-Admin-Key`, checked against `PARTNER_ADMIN_KEY`), **not**
the partner session cookie or CSRF — this is a server-to-server call, so
there's no browser session to hold a CSRF token.

```
GET    /api/v1/admin/partners                  ?q=&status=&partner_type=&country=
                                                &has_portal_access=&include_deleted=
                                                &only_deleted=&page=&limit=
POST   /api/v1/admin/partners                  optional: password, or generate: true
GET    /api/v1/admin/partners/{id}
PUT    /api/v1/admin/partners/{id}
DELETE /api/v1/admin/partners/{id}             soft delete
POST   /api/v1/admin/partners/{id}/activate
POST   /api/v1/admin/partners/{id}/deactivate
POST   /api/v1/admin/partners/{id}/password    {"password":"..."} or {"generate":true}
POST   /api/v1/admin/partners/{id}/unlock
POST   /api/v1/admin/partners/{id}/restore
```

Same response contract as the public API. `password_hash` is never returned.
A generated password is returned exactly once, as `generated_password`, and
is never stored in clear text.

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

This portal has its **own dedicated database** — do not point it at Engage's.
It owns the Partner Master (table `partners`) end to end: schema, reads and
writes.

```
PARTNER_DB_HOST     = 127.0.0.1
PARTNER_DB_PORT     = 5432
PARTNER_DB_NAME     = partner_aicountly
PARTNER_DB_USER     = <a role with full rights on this database>
PARTNER_DB_PASSWORD = <password>
PARTNER_DB_DRIVER   = Postgre
```

CodeIgniter's native `database.default.*` names work too; if both are present
the `PARTNER_DB_*` values win. Credentials come only from `.env` — nothing is
hardcoded.

These values go in `server-php/.env` locally, and in
`${PROD_SSH_REMOTE_ROOT}/api/.env` on the server.

### Admin key setup

Generate a random secret and set it as `PARTNER_ADMIN_KEY` here **and** as
`PARTNER_PORTAL_ADMIN_KEY` in Engage's `api/.env` — same value, both sides:

```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
```

Also set `PARTNER_PORTAL_API_URL` in Engage's `api/.env` to this portal's API
base (e.g. `https://partner.aicountly.com/api`). Without both configured, the
admin routes return `401`/`503` and Engage's Partner Master screen fails
loudly rather than silently.

### Migrations

This repository owns its schema — `php spark migrate` creates and updates the
`partners` table. Nothing else creates it; Engage's migrations do not touch
partner data. The post-deploy script always runs `php spark migrate --all`
followed by `php spark cache:clear` on every deploy.

## Security

- Passwords are hashed with `password_hash()` on write (create, or the
  set/generate-password admin endpoint) and verified on login; no plaintext
  password is ever stored or logged
- The admin API (`/api/v1/admin/*`) is authenticated by a shared secret
  (`X-Partner-Admin-Key`), separate from the partner session cookie — a
  partner session can never reach it, and the admin key can never authenticate
  a partner login
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
9. Runs `scripts/cpanel-post-deploy-api.sh` over SSH: fixes `writable/`
   permissions, runs `php spark migrate --all` (this portal owns the Partner
   Master schema), runs `php spark cache:clear`, resets OPcache, then runs
   `check-env.php`
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
PARTNER_DB_HOST     = 127.0.0.1         # this portal's OWN database — not Engage's
PARTNER_DB_PORT     = 5432
PARTNER_DB_NAME     = partner_aicountly
PARTNER_DB_USER     = <db role>
PARTNER_DB_PASSWORD = <db password>
PARTNER_DB_DRIVER   = Postgre
PARTNER_ADMIN_KEY   = <random secret; must match Engage's PARTNER_PORTAL_ADMIN_KEY>
encryption.key      = <32 random bytes, see below>
cookie.secure       = true
```

After the first deploy, run `php spark migrate` (the post-deploy script does
this automatically) to create the `partners` table — this portal owns that
schema and nothing else creates it.

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
