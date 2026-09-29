# Deploying LankaGuide360

A checklist for putting the site on a Linux or Windows server. The local XAMPP setup is in
[README.md](../README.md).

## 1. Server requirements

- PHP 8.4 with the extensions `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` (with WebP),
  `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`
- MySQL 8 or MariaDB 10.4+, database `lankaguide360_app` with collation `utf8mb4_unicode_ci`
- Composer 2 and Node.js 20+ (Node is only needed to build assets; it can run on your PC instead)
- A web server (Apache or nginx) whose document root is the `public/` folder
- HTTPS certificate (Let's Encrypt)

## 2. Install

```sh
git clone <repo> lankaguide360 && cd lankaguide360
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

## 3. `.env` for production

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain` (used in emails, the sitemap and canonical links) |
| `DB_*` | production database and a user with rights only on it |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | real SMTP account |
| `MAIL_FROM_ADDRESS` | an address on your domain |
| `ADMIN_EMAIL` | inbox that receives new trip requests and contact messages |
| `CONTACT_*` | public phone, WhatsApp, email and office location |
| `QUEUE_CONNECTION` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `HF_TOKEN` | Hugging Face token for the chatbot (optional: without it the chatbot answers from FAQs) |
| `ORS_API_KEY` | OpenRouteService key for road routes (optional: without it distances are estimated) |

Never commit `.env`.

## 4. Database and files

```sh
php artisan migrate --force
php artisan db:seed --force        # idempotent: safe to re-run
php artisan storage:link
```

Then **change the seeded passwords** (`admin@lankaguide360.test`, `agent@lankaguide360.test`) or
delete those users after creating real accounts in Admin → Users. Set real rates in
Admin → Settings, Vehicles and Guides: the seeded values are placeholders.

Folders the web server must be able to write: `storage/` and `bootstrap/cache/`.

## 5. Optimise

```sh
php artisan optimize        # caches config, routes, views and events
```

Run `php artisan optimize` again after every deploy (and `php artisan optimize:clear` if a
config change does not show up).

## 6. Queue worker

Emails (trip confirmations, status changes, messages, contact form) and imports run on the
queue. Keep one worker running:

- **Linux (Supervisor)**

  ```ini
  [program:lankaguide-queue]
  command=php /var/www/lankaguide360/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  user=www-data
  ```

- **Windows**: a Task Scheduler task "At startup" running
  `C:\xampp\php\php.exe C:\path\to\artisan queue:work --tries=3`.

After each deploy run `php artisan queue:restart` so workers load the new code.

## 7. Data imports (first launch)

Run once, in this order, then review drafts in Admin → Review queue and publish them:

```sh
php artisan lg:import-districts     # must run first (writes public/geo/lk-districts.geojson)
php artisan lg:import-places
php artisan lg:import-images
php artisan lg:import-hotels
php artisan lg:suggest-places
```

or `php artisan lg:import-all`. Respect the rate limits in CLAUDE.md; if Overpass returns 504,
set `OVERPASS_URL` to a mirror and re-run for the failed districts only (`--district=jaffna`).

## 8. Security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS enforced; `SESSION_SECURE_COOKIE=true`
- [ ] Seeded passwords changed
- [ ] `.env` not readable from the web (document root is `public/`)
- [ ] Database user has no rights on other databases (the old `lankaguide360` database stays untouched)
- [ ] Backups: nightly database dump plus `storage/app/public/media`
- [ ] Security headers are sent by `App\Http\Middleware\SecurityHeaders` (CSP, nosniff,
      frame options, referrer policy, HSTS on HTTPS). If you add an external script, font or
      image host, add it to the CSP there.

## 9. SEO

- `https://your-domain/sitemap.xml` lists every public page (cached for an hour).
- `robots.txt` is served by the app: it allows crawling only when `APP_ENV=production` and
  blocks `/admin`, `/trips/`, `/my-trips` and `/api/`. Delete any static `public/robots.txt`.
- Submit the sitemap in Google Search Console.

## 10. After each deploy

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```
