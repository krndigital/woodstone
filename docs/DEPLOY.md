# Deploy, backup and monitoring (step 15)

> On cPanel shared hosting follow `docs/CPANEL.md` instead.

Target: one Ubuntu 24.04 server (NepalHost VPS or the restaurant's local server), PHP 8.3, PostgreSQL 16, Redis, Nginx, HTTPS.

## 1. Server packages (server)

```
sudo apt update && sudo apt install -y nginx postgresql-16 redis-server supervisor unzip git \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-curl
```

Install Composer 2 and Node 22 LTS from their official instructions.

## 2. Database (server)

Create the role with a password typed at a hidden prompt (never on the command line):

```
sudo -u postgres createuser --pwprompt woodstone && sudo -u postgres createdb -O woodstone woodstone
```

## 3. App (server)

```
sudo mkdir -p /var/www/woodstone && sudo chown $USER /var/www/woodstone
git clone https://github.com/krndigital/woodstone.git /var/www/woodstone && cd /var/www/woodstone
cp .env.example .env   # then edit: see the table below
composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan key:generate && php artisan migrate --force && php artisan storage:link
php artisan woodstone:create-owner          # owner password typed at a hidden prompt
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache
```

Production `.env` values (secrets only in `.env`, never in git, chat or logs):

| Key                                             | Value                                                               |
| ----------------------------------------------- | ------------------------------------------------------------------- |
| APP_ENV / APP_DEBUG                             | `production` / `false`                                              |
| APP_URL                                         | `https://your-domain`                                               |
| APP_TIMEZONE                                    | `Asia/Kathmandu`                                                    |
| DB_CONNECTION                                   | `pgsql` (+ DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD) |
| SESSION_DRIVER / CACHE_STORE / QUEUE_CONNECTION | `redis`                                                             |
| SESSION_SECURE_COOKIE / SESSION_ENCRYPT         | `true` / `true`                                                     |
| LOG_STACK / LOG_LEVEL                           | `daily` / `warning`                                                 |

## 4. Nginx + HTTPS (server)

Standard Laravel site pointing at `/var/www/woodstone/public`, `client_max_body_size 8m;`, then
`sudo certbot --nginx -d your-domain`. HSTS is sent by the app once HTTPS is on.

## 5. Queue worker and scheduler (server)

Supervisor program: `php /var/www/woodstone/artisan queue:work --sleep=3 --tries=3 --max-time=3600` as `www-data`.
Cron for `www-data`: `* * * * * cd /var/www/woodstone && php artisan schedule:run >> /dev/null 2>&1`
The scheduler runs the daily backup at 02:30.

## 6. Backups

- `php artisan woodstone:backup` writes `storage/app/backups/woodstone-YYYYMMDD-HHMMSS.sql.gz` (PostgreSQL) and keeps 14.
- Copy that folder off the server every day (rclone/rsync to another machine or cloud storage).
- **Restore test every month** on a separate database:
  `gunzip -c woodstone-....sql.gz | psql -h 127.0.0.1 -U woodstone woodstone_restore_test`
  then open the app pointed at it and check the last day's bills.

## 7. Updates (server)

Back up first, then:

```
php artisan woodstone:backup && php artisan down && git pull && composer install --no-dev -o && npm ci && npm run build && php artisan migrate --force && php artisan optimize && php artisan up
```

## 8. Monitoring

- Uptime check on `https://your-domain/up` every minute (UptimeRobot or similar). It returns 500 if the database is down.
- `storage/logs/laravel-*.log` (daily). Watch for `ERROR`.
- Audit log: owner report **Voids, discounts and audit**.
- Disk: alert at 80 % (`df -h`), backups grow daily.

## 9. Security in place

Nonce-based Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS;
rate limits on login, PIN, manager approval, void, settle, guest QR calls; all money append-only with audit log;
staff citizenship numbers encrypted; QR tokens random and regenerable.

## 10. Load test (staging only)

`k6 run -e BASE=https://staging-domain -e USER=waiter -e PASS=... tests/load/k6-pos.js`; target p95 under 800 ms with 100 users.
