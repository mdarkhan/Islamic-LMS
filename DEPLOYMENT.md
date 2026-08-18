# DEPLOYMENT.md — cPanel

No Docker, no Node runtime in production. Frontend assets are built locally and
uploaded.

---

## 1. Hosting requirements

| Requirement | Value |
|---|---|
| **PHP** | **8.3 or newer** (the framework requires it — verify in cPanel → MultiPHP Manager before starting) |
| MySQL / MariaDB | MySQL 8.0+ or MariaDB 10.4+ |
| Extensions | `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `curl`, `zip`, `intl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |
| Composer | Available in cPanel Terminal, or upload `vendor/` built locally |
| HTTPS | Required |

`intl` is **not optional** — Bengali NFC normalisation (`Normalizer`, used by slugs and text
comparison) depends on it. `bcmath` is **not optional** — the Zakat calculator does all money
arithmetic with it. The Hijri calendar and sunset use pure PHP + the core `date` extension
(`date_sun_info`); no prayer-time API is required.

> **If the host cannot offer PHP 8.3**, stop and say so before going further. The
> application would need to be pinned to an older Laravel release, which is a
> deliberate decision, not a workaround to apply silently.

---

## 2. Before the first deploy

Complete `SECURITY.md` §0 first:

1. **Rotate the MySQL password** — the legacy one is compromised.
2. **Make both Google Sheets private.**
3. Take a full database backup and verify the dump restores.

---

## 3. Database

cPanel → MySQL® Databases:

```
Database : <account>_masudalimi     charset utf8mb4, collation utf8mb4_unicode_ci
User     : <account>_masudapp       a NEW user, not the legacy one
Privileges: ALL on that database only
```

Do not reuse the legacy database user.

---

## 4. Document root

The web root must point at `public/`, so `.env`, `storage/` and `vendor/` are
unreachable over HTTP.

```
/home/<account>/masudalimi/          ← application (outside public_html)
/home/<account>/public_html          ← symlink or docroot → masudalimi/public
```

If the docroot cannot be changed, point the domain at `public/` via cPanel →
Domains → Document Root. Do **not** work around it by moving `index.php` to the
root — that exposes the whole application tree.

---

## 5. Deploy

```bash
cd ~/masudalimi

composer install --no-dev --optimize-autoloader

cp .env.example .env      # first deploy only
# edit .env: APP_ENV=production, APP_DEBUG=false, DB_*, MAIL_*, USTAZ_EMAIL
php artisan key:generate  # first deploy only — regenerating invalidates sessions

php artisan migrate --force
php artisan db:seed --force   # first deploy only: roles, settings, courses

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Assets are built **locally** and the resulting `public/build/` uploaded:

```bash
npm ci && npm run build      # on your machine, then upload public/build/
```

After any `.env` change, re-run `php artisan config:cache` — cached config
ignores `.env`.

---

## 6. Permissions

```bash
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

Nothing else should be group- or world-writable.

---

## 7. Scheduler

Required for the hourly `imports:cleanup` sweep (abandoned quiz/student import uploads
and expired credential exports) and, later, expiring abandoned attempts and scheduled
result release. This single cron entry drives everything — no long-running worker.
cPanel → Cron Jobs, every minute:

```
* * * * * cd /home/<account>/masudalimi && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confirm the PHP binary path in cPanel — it is often version-specific, e.g.
`/opt/cpanel/ea-php83/root/usr/bin/php`.

Import files are also pruned opportunistically on each import preview, so cleanup still
happens even if the cron is briefly misconfigured — but the cron is the reliable path.

---

## 8. Mail

cPanel → Email Accounts, create the sender, then in `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=mail.<yourdomain>
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<the mailbox>
MAIL_PASSWORD=<its password>
MAIL_FROM_ADDRESS=<the mailbox>
USTAZ_EMAIL=<where Ask Ustaz questions go>
```

Send a test before launch — Ask Ustaz questions are **not stored anywhere**, so a
silent mail failure loses them permanently. The admin **Settings → Mail** panel reports
configured/incomplete for the mailer, the from-address and `USTAZ_EMAIL` (never the
secret values), which is the quickest pre-launch check.

---

## 8b. Public-module configuration (admin, not `.env`)

These are edited in **/admin/settings** by an admin and stored in the `settings` table
(`SettingService`) — no `.env` change or redeploy needed:

- **Zakat reference** — gold price/gram, silver price/gram, default Nisab basis, currency
  symbol. The calculator shows the "last updated" stamp; until the rates are set it warns
  that the Nisab cannot be computed. Set these before promoting the Zakat calculator.
- **Calendar** — institutional latitude/longitude (default Dhaka `23.8103, 90.4125`),
  timezone (`Asia/Dhaka`), and `hijri_offset_days` (−1/0/+1) to align the calculated Hijri
  date with the local moon sighting.
- **General** — site name, tagline, Telegram URL.

**Caching:** settings, and any public caching, use the configured cache driver — `file`
or `database` is fine on cPanel; **Redis is not required**. `SettingService` invalidates
its cache on every save, so admin edits take effect immediately.

---

## 9. Backups

Before every deploy that includes a migration:

```bash
mysqldump -u <user> -p --single-transaction --default-character-set=utf8mb4 \
  <database> > backup-$(date +%F-%H%M).sql
```

Verify by restoring into a scratch database. A dump you have never restored is not
a backup.

`quiz_submissions` (legacy) is never dropped by any migration in this project.

---

## 10. Rollback

```bash
php artisan down
php artisan migrate:rollback --step=1     # only if the deploy added migrations
# restore the previous code, then:
php artisan config:cache && php artisan up
```

For a data-affecting problem, restore the dump taken in §9. Migration imports are
idempotent and scoped by marker columns, so a single import can be reverted
without touching the legacy sources (`MIGRATION.md` §6).

---

## 11. Post-deploy checklist

- [ ] `https://` loads; `http://` redirects
- [ ] `/.env` returns 404, not file contents
- [ ] `/storage/logs/laravel.log` is not reachable
- [ ] `APP_DEBUG=false` (force an error and confirm no stack trace shows)
- [ ] Login works with a Bengali-numeral roll number
- [ ] A quiz start debits exactly one point; a refresh debits none
- [ ] Bengali and Arabic render correctly on a fresh record
- [ ] Ask Ustaz sends mail, and writes nothing to the database
- [ ] Scheduler ran within the last two minutes
- [ ] Legacy `quiz-api.php` write path is removed or the file is offline
