# Hosting LankaGuide360 for free

## Which host?

| Host | Fits this app? | Why |
|---|---|---|
| **alwaysdata Free** (recommended) | **Yes** | PHP, MySQL/MariaDB, SSH, scheduled tasks and a free `yourname.alwaysdata.net` address. 1 GB disk (this app needs about 450 MB), 256 MB RAM. Free for **personal use**: fine for a demo or a student project; a real business should move to the paid Plus plan (same setup). |
| Vercel | No (not without a rewrite) | Runs Laravel only as short-lived containers: no MySQL (only Postgres add-ons, and the app uses MySQL features), no permanent disk for the 320 MB of photos, no queue worker or per-minute scheduler. |
| InfinityFree / 000webhost-style hosts | Not recommended | No SSH or Composer, no cron, strict PHP limits; Laravel 13 with PHP 8.4 is unreliable there. |
| Render / Railway / Koyeb free tiers | Partly | Need Docker and an external MySQL; free instances sleep and lose uploaded files. |

The rest of this guide is for **alwaysdata**. Everything below runs over SSH, so you don't need
FTP. Check alwaysdata's own help pages if a menu name has changed.

## 1. Create the account and the pieces

1. Sign up at <https://www.alwaysdata.com> → choose the **Free** plan. Your account name becomes
   the address, e.g. `lankaguide360.alwaysdata.net`.
2. **Databases → MySQL**: create a database (e.g. `lankaguide360_app`) and a user. Note the host
   (`mysql-<account>.alwaysdata.net`), database name, user and password.
3. **Remote access → SSH**: enable password login for your SSH user.
4. **Environment** (or the site's settings): choose **PHP 8.4**.

## 2. Put the code on the server

```sh
ssh <account>@ssh-<account>.alwaysdata.net
cd ~
git clone https://github.com/chathuka55/lankaguide360.git lankaguide360
cd lankaguide360
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env` (`nano .env`):

```env
APP_NAME=LankaGuide360
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<account>.alwaysdata.net
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true

DB_HOST=mysql-<account>.alwaysdata.net
DB_DATABASE=<account>_lankaguide360_app
DB_USERNAME=<account>
DB_PASSWORD=<database password>

QUEUE_CONNECTION=database
# + the MAIL_* lines from docs/EMAIL.md
```

Build the CSS/JS on your PC and upload it (the free plan runs out of memory in `npm ci`).
In Git Bash, in the project folder:

```sh
bash scripts/upload-build.sh <account>@ssh-<account>.alwaysdata.net
```

## 3. Copy your data (database and photos)

Photos and database rows are not in git. On your PC (Git Bash), in the project folder:

```sh
bash scripts/export-for-hosting.sh
```

It creates `storage/app/private/hosting-export/` with `database.sql.gz` and `media.tar.gz`.
Upload and import them:

```sh
scp storage/app/private/hosting-export/* <account>@ssh-<account>.alwaysdata.net:~/
ssh <account>@ssh-<account>.alwaysdata.net
cd ~/lankaguide360
gunzip -c ~/database.sql.gz | mysql -h mysql-<account>.alwaysdata.net -u <account> -p <account>_lankaguide360_app
tar -xzf ~/media.tar.gz -C storage/app/public
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

(Starting empty instead: `php artisan migrate --force --seed`, then `php artisan lg:import-all`,
`php artisan lg:publish-ready` and, for a demo, `php artisan lg:demo-hotels`.)

## 4. Point the website at Laravel's `public` folder

**Web → Sites → Add a site**: type **PHP**, address `<account>.alwaysdata.net`, root directory
`/lankaguide360/public`. Turn on **Force HTTPS**.

## 5. Scheduled task (emails and queue)

**Advanced → Scheduled tasks → Add**: every minute, command

```sh
php /home/<account>/lankaguide360/artisan schedule:run
```

## 6. Check

- Open `https://<account>.alwaysdata.net` and log in as admin.
- **Change the seeded passwords** (Admin → Users) — the defaults are public in the README.
- Admin → Settings → Email → **Send test email**.
- Before real customers: remove the demo hotels (`php artisan lg:demo-hotels --remove`) and set
  real rates. See the checklist in [DEPLOY.md](DEPLOY.md).

## Updating the live site later

On your PC, after `git push`:

```sh
bash scripts/upload-build.sh <account>@ssh-<account>.alwaysdata.net   # CSS/JS
ssh <account>@ssh-<account>.alwaysdata.net 'cd ~/lankaguide360 && bash scripts/deploy.sh'
```

`deploy.sh` stops with a warning if the uploaded build is missing a page bundle (that page
would show a server error). New vehicle types get a photo with `php artisan lg:vehicle-photos`.
