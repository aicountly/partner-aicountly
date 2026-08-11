# partner-aicountly

AICOUNTLY **Partner Portal** — the partner-facing sign-in and dashboard for
`partner.aicountly.com`.

- Framework: CodeIgniter 4 (server-rendered), PHP 8.2+
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
├── index.php                     Front controller (flat cPanel layout)
├── .htaccess                     Rewrites + denies app/, vendor/, writable/, .env
├── dev-server.php                Router for `php -S` during local development
├── spark                         CodeIgniter CLI
├── check-env.php                 Verifies .env and the Partner Master connection
├── assets/app.css                Portal stylesheet (no build step)
├── app/
│   ├── Config/                   App, Database, Session, Cookie, Security, Filters, Routes
│   ├── Controllers/              Auth, Dashboard, Profile, Health
│   ├── Filters/                  PartnerAuthFilter, GuestFilter
│   ├── Libraries/PartnerAuth.php Credential verification + session handling
│   ├── Models/PartnersModel.php  Read-side view of engage_partners
│   └── Views/                    layouts, auth/login, dashboard, profile
├── scripts/
│   ├── cpanel-rsync.filters      rsync rules (protect .env and writable/)
│   └── cpanel-post-deploy.sh     spark commands + verification on the server
└── .github/workflows/
    └── deploy-production.yml     "Deploy To cPanel Production" (manual only)
```

## Routes

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/` | public | Redirects to `/dashboard` or `/login` |
| GET | `/login` | guests only | Login form |
| POST | `/login` | guests only | Verify credentials, start session |
| GET | `/dashboard` | partners | Authenticated dashboard |
| GET | `/profile` | partners | Read-only partner details |
| POST | `/logout` | partners | Destroy the session |
| GET | `/health` | public | Deployment probe (no secrets, no partner data) |

## Local setup

```bash
composer install
cp .env.example .env          # then fill in the database settings below
php spark key:generate        # writes encryption.key into .env
php -S 127.0.0.1:8081 dev-server.php
```

Then open <http://127.0.0.1:8081/login>.

`php spark serve` is not used here: the app is deployed flat (no `public/`
directory) to match the cPanel document root, so `dev-server.php` reproduces the
production `.htaccess` rules for PHP's built-in server.

Useful commands:

```bash
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
- CSRF protection on every state-changing request (`security.csrfProtection =
  session`, randomised tokens)
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
`${PROD_SSH_REMOTE_ROOT}/.env`. It is:

- never committed (see `.gitignore`)
- never copied from the repository
- never deleted by `rsync --delete`
- never edited by the post-deploy script
- verified byte-for-byte after every deployment

Create it once, by hand, before the first deploy:

```bash
cd "$PROD_SSH_REMOTE_ROOT"
cp .env.example .env
nano .env            # set app.baseURL, PARTNER_DB_*, encryption.key
chmod 600 .env
php spark key:generate   # if encryption.key is still empty
php check-env.php
```

The workflow fails early — before touching any files — if that `.env` is
missing.

### cPanel requirements

- PHP **8.2+** selected for the domain in MultiPHP, with `intl`, `mbstring`,
  `json`, `curl`, `pgsql` and `pdo_pgsql` enabled
- SSH access and `rsync` available for `PROD_SSH_USER`
- The deploy key authorised for that user
- Document root for `partner.aicountly.com` set to `PROD_SSH_REMOTE_ROOT`
- `mod_rewrite` enabled (the shipped `.htaccess` needs it)
- `writable/` writable by the web user (the post-deploy script sets 775/777)
