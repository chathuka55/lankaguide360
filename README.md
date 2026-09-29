# LankaGuide360

A Sri Lanka trip planner: travellers build a day-by-day itinerary with a routed map and a full
price breakdown, then a local travel agent reviews and confirms it.

- Specification: [docs/SRS.md](docs/SRS.md)
- Build plan (12 phases) and data sourcing: [docs/BUILD-PROMPTS.md](docs/BUILD-PROMPTS.md)
- Project rules for AI agents: [CLAUDE.md](CLAUDE.md)

## Stack

Laravel 13 · PHP 8.4 · MariaDB (XAMPP) · Blade + Alpine.js · Livewire 3 (Trip Builder) · Tailwind CSS v4 + Flowbite · Leaflet + OpenStreetMap

## Run locally (Windows + XAMPP)

Requirements: XAMPP with PHP 8.4 in `C:\xampp\php` (on PATH), Composer, Node.js 20+.
Start **MySQL** in the XAMPP Control Panel, then:

```sh
composer install
npm install
cp .env.example .env        # then check the DB_* values
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Create the databases once (phpMyAdmin or the MySQL CLI), collation `utf8mb4_unicode_ci`:
`lankaguide360_app` for the app and `lankaguide360_app_test` for the test suite.

Run in three terminals:

```sh
npm run dev
php artisan serve           # http://127.0.0.1:8000
php artisan queue:work
```

Seeded logins (change before going live): `admin@lankaguide360.test` and
`agent@lankaguide360.test`, password `password`.

## Tests

```sh
php artisan test
./vendor/bin/pint           # code style (PSR-12)
```
