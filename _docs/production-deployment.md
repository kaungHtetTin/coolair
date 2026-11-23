# Production setup: coolair.k2studioapp.com

The production URL is **https://coolair.k2studioapp.com/**. The repository includes a domain-specific `.env.production.example`; the normal `.env` remains suitable for local XAMPP development. Do not upload the local `.env`, local SQLite database, test accounts, backups, `tests/artifacts`, `node_modules`, `.git`, or temporary PDF/cache files to production.

## Server requirements and document root

Use PHP 8.2 or newer with PDO SQLite, BCMath, fileinfo, mbstring, GD, DOM and sessions. Install the locked Composer dependencies. The deployed application needs persistent local storage for SQLite, uploads, PDF temporary files and logs; it is a single-server installation. Node/npm is needed only where frontend assets are built.

Prefer a domain document root pointing to the project's **public/** directory. The rest of the project should sit outside that public document root. Enable rewrite rules and `.htaccess` overrides. If the hosting account instead places the complete project in its domain web root, retain **both** supplied `.htaccess` files: the root file routes requests into `public/` and denies private application/storage/development paths; `public/.htaccess` handles application routes and blocks private file extensions. Keep the visible app URL at the domain root, not `/public/`.

Install/verify the domain's SSL certificate and enable the hosting panel's HTTPS redirect so static assets also use HTTPS. The application separately enforces HTTPS and the configured canonical host for PHP routes. It sends HSTS on secure production responses and uses host-only Secure/HttpOnly/SameSite=Lax session cookies.

## First installation

Run these commands in the deployed project directory, using the PHP executable configured for this website:

```sh
# First installation only: never overwrite an existing live .env.
cp -n .env.production.example .env
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
php bin/console deploy:prepare
php bin/console migrate
php bin/console db:seed
php bin/console user:create administrator
```

Enter the initial office password at the prompt; there are no default credentials. The production profile intentionally disables browser-based first-user setup. Staff accounts are created by the office administrator after signing in. If the host has no SSH/terminal, execute these steps through an authorized private terminal/deployment process before opening the site; do not create a public web-based installer.

Build frontend assets on the deployment/build machine:

```sh
npm ci
npm run build
```

Deploy the complete `public/build/` directory, including **.vite/manifest.json**, along with `public/branding/`, `public/icons/`, `public/manifest.webmanifest`, the PHP code, translations, Myanmar fonts, migrations, catalog seed source files and installed `vendor/`. Keep the original directory structure. Hidden files such as `.htaccess`, `public/.user.ini` and `.vite/manifest.json` must be included by the upload tool. The `.user.ini` supplies PHP-FPM/CGI defaults for private error logging and cookie-only sessions; if the hosting PHP handler does not use this file, set display/startup errors off and error logging on in its PHP settings. Application production errors are also hidden independently by the runtime configuration.

The live `.env` should contain these settings (the supplied template includes them):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://coolair.k2studioapp.com
APP_TIMEZONE=Asia/Yangon
APP_ALLOW_SETUP=false
APP_REQUIRE_HTTPS=true
SESSION_NAME=cool_air_production_session
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=
DB_PATH=storage/database/app.sqlite
```

`DB_PATH` is relative to the project directory, or can be an absolute path to a persistent private SQLite file. It is not a database hostname or URL. The PHP account needs write permission on both that file and its parent directory for SQLite journal files. It also needs write permission on `storage/logs`, `storage/uploads`, `storage/pdf`, `storage/cache` and `storage/backups`. `deploy:prepare` creates missing storage directories and preserves existing files. Grant the PHP account access rather than making directories world-writable.

## Reverse proxies

Direct HTTPS is detected from the web server's `HTTPS`/port information. Forwarded-protocol headers are accepted only when `REMOTE_ADDR` exactly matches an IP in the comma-separated `TRUSTED_PROXIES` setting. Leave it empty unless the host actually terminates TLS at a reverse proxy and PHP otherwise sees HTTP. In that case, get the real proxy IP addresses from the host and ensure it overwrites `X-Forwarded-Proto` with one value, `https`. Do not use `*`, guess addresses, disable secure cookies or turn off HTTPS enforcement to address a redirect loop. The production tests use explicit loopback proxy addresses only inside an isolated test server.

## Readiness and verification

```sh
php bin/console deploy:check
```

This returns a nonzero exit code when production configuration, PHP extensions, writable storage, PDF dependencies, required assets, database migrations, seeded catalog/pricing or an active office administrator is missing. It does not create a database or modify business records. Run it after installation and after every release. It checks the CLI environment; confirm the website uses the same PHP version/extensions and file permissions.

After upload, verify the actual HTTPS site:

- Sign-in works at `/`, `/office` and `/staff`, and authentication survives refresh.
- HTTP redirects to HTTPS. Production session cookies are Secure/HttpOnly/SameSite=Lax; debug exceptions are not displayed.
- Requests to `/.env`, `/.git/config`, `/storage/database/app.sqlite`, `/storage/backups/` and `/vendor/autoload.php` return 403/404 and never return file contents.
- Language/theme toggles, catalog search and staff restrictions work.
- Download an invoice/receipt PDF, print a large quotation, and verify the supplied letterhead appears on every page.
- The install manifest at `/manifest?lang=en` (or `my`), icons and built assets load from the domain root. The PWA remains online-only.

## Updates and backups

Preserve the live `.env`, SQLite database and uploads across releases. Never replace them with development files. Before applying migrations, run:

```sh
php bin/console db:backup
```

Copy the resulting backup to private off-server storage and back up `storage/uploads/` alongside it. The SQLite backup command uses `VACUUM INTO` for a consistent database copy. It does not include attachments. Keep private backups out of `public/`.

Then deploy code/dependencies/built assets, run migrations and `deploy:check`, and verify the site. The catalog seed is idempotent and preserves office edits if source catalog updates need to be imported. Never drop/reinitialize production tables or reseed customer transactions. If a rollback is needed, restore matching database and upload backups through a maintenance window.

The live server still needs these files/settings to be uploaded and the commands above to be run; local preparation does not change the hosting account.
