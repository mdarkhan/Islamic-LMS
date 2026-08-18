# Production Preflight

Run this on cPanel before migration or traffic cutover:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan app:preflight --production
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
/opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:list
/opt/cpanel/ea-php83/root/usr/bin/php artisan app:verify-integrity
/opt/cpanel/ea-php83/root/usr/bin/php artisan legacy:verify
```

Replace the PHP path with the one reported by cPanel MultiPHP Manager/Terminal.

## PHP and extensions

Required: PHP 8.3+, PDO MySQL, OpenSSL, fileinfo, tokenizer, DOM/XMLReader, ZIP (XLSX import), BCMath
(Zakat), JSON, and a working `Normalizer` implementation. Native ctype, mbstring, and intl are
recommended; the locked dependency set includes compatible polyfills. cURL, GD, and Imagick are not
currently required.

## Production environment

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
APP_TIMEZONE=Asia/Dhaka
APP_LOCALE=bn

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=sync

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
USTAZ_EMAIL=
```

Keep secrets only in `.env` with mode `600`. Generate `APP_KEY` once per environment; do not rotate
it during an ordinary deploy. If behind a cPanel/reverse proxy, verify Laravel receives HTTPS and
only configure trusted proxies to the host's documented proxy addresses.

After editing `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan app:preflight --production
```

## cPanel layout

Preferred:

```text
/home/USERNAME/masudalimi-app/    Laravel application, .env, vendor, storage
/home/USERNAME/public_html/       domain document root pointing to masudalimi-app/public
```

Better still, set the domain/subdomain document root directly to
`/home/USERNAME/masudalimi-app/public`. Never expose `.env`, `vendor`, `storage`, legacy exports,
credential CSVs, SQL dumps, or backups. Do not copy ignored `quiz-api.php` into the new public root.

## Install and assets

```bash
composer install --no-dev --optimize-autoloader
```

Do not run `composer update` in production. Build locally with `npm ci && npm run build`, then upload
the generated `public/build/`; no Node process runs on cPanel. Run `php artisan storage:link` only
because public CMS uploads use the public disk. If the host forbids symlinks, ask the host to permit
the link or configure a documented public upload path; never move private import/credential files.

## Scheduler and SMTP

One cPanel cron, every minute:

```cron
* * * * * cd /home/USERNAME/masudalimi-app && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Verify the scheduler timestamp in cPanel/Laravel logs. Send only the canned diagnostic:

```bash
php artisan app:mail-test --to=owner@example.com
```

Confirm Bengali subject/body rendering and receipt, then test the public Ask Ustaz form. The form
does not persist a fallback copy, so SMTP failure is launch-critical for that feature.

## Logs and monitoring

Use `storage/logs/laravel.log` (or daily files), the cPanel PHP error log, and cPanel resource/error
metrics. Do not add paid monitoring for this cutover. Logs must not contain passwords, SMTP/DB
credentials, Ask Ustaz questions, Zakat inputs, answer keys, or session tokens.
