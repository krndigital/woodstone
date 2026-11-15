# Install on cPanel hosting

For a shared or reseller cPanel account. For your own Ubuntu server use `docs/DEPLOY.md`.

## What the host must have

- PHP **8.3 or newer** with: pdo_mysql (or pdo_pgsql), mbstring, openssl, xml, ctype, fileinfo, bcmath, zip, gd.
  Set it in **cPanel → MultiPHP Manager** (or _Select PHP Version_).
- A **MySQL 8 / MariaDB 10.6+** database (tested in CI on MySQL 8), or PostgreSQL if the host offers it.
- **Terminal** in cPanel (or SSH). Without it, ask the host to run the two commands in step 5 once.
- **Cron Jobs** (for daily backup and reminders).
- No Composer, Node, Redis or queue worker needed. Sessions, cache and queue use the database.

## 1. Get the package

GitHub → **Actions → cpanel-package** → newest green run → **Artifacts → woodstone-cpanel**.
It contains everything (PHP packages and the built screens). Unzip once on your computer: inside is `woodstone-cpanel.zip`.

## 2. Make the database

cPanel → **MySQL® Databases**: create database `cpuser_woodstone`, user `cpuser_ws` with a strong password,
add the user to the database with **ALL PRIVILEGES**.

## 3. Upload

cPanel → **File Manager** → your home folder (NOT `public_html`) → new folder `woodstone` →
upload `woodstone-cpanel.zip` into it → **Extract** → delete the zip.

## 4. Point the domain at `woodstone/public`

- Subdomain or addon domain (e.g. `rms.example.com`): cPanel → **Domains** → _Manage_ → Document Root = `woodstone/public`.
- Only the main domain and the root cannot be changed: set the document root to `woodstone` instead.
  The `.htaccess` in that folder sends everything into `public/` and blocks `.env`, `storage`, `vendor`.

Turn on **SSL/TLS Status → Run AutoSSL** so the site opens on https.

## 5. Settings and install

In File Manager, copy `woodstone/.env.example` to `woodstone/.env` and edit it:

```
APP_NAME="WoodStone RMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rms.example.com
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpuser_woodstone
DB_USERNAME=cpuser_ws
DB_PASSWORD=the-password-from-step-2

SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
```

Then cPanel → **Terminal**:

```
cd ~/woodstone && php artisan woodstone:install && php artisan woodstone:install
```

The first run makes the app key; the second migrates, links storage, caches, and asks you to create the
owner (password is typed hidden). If `php` is too old there, use the full path, for example
`/opt/cpanel/ea-php83/root/usr/bin/php artisan woodstone:install`.

## 6. Cron

cPanel → **Cron Jobs** → _Once Per Minute_ →

```
cd ~/woodstone && php artisan schedule:run >> /dev/null 2>&1
```

This runs the daily database backup (02:30, kept in `storage/app/backups`, newest 14). Download a copy
off the server weekly, or turn on cPanel's own **Backup**.

## 7. Check

Open the site → sign in as the owner → **Settings** (restaurant name, PAN, fiscal year) → **Users** (add staff).
`https://rms.example.com/up` shows a green page when the app and database are fine.

## Updating later

1. Take a backup: `cd ~/woodstone && php artisan woodstone:backup`
2. Download the new package (step 1). Extract it **over** `~/woodstone` (keeps `.env` and `storage/`, which are not in the zip).
3. `cd ~/woodstone && php artisan woodstone:install`

## Limits on shared hosting

- Kitchen screen and guest calls refresh by polling (every few seconds); live push (Reverb) is not possible on shared hosting and not needed.
- Photo and receipt uploads need symlinks allowed (`public/storage`). The install command warns if not.
- Keep the database on the same cPanel account; remote databases slow every screen.
