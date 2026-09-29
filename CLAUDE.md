# LankaGuide360 — project rules for Claude Code

## What we are building
A Sri Lanka trip-planner website. Travellers choose a tier (budget/premium/luxury),
interest categories, districts, places, dates, travellers, hotels, meals, vehicle and
guide. The system generates a day-by-day itinerary with a routed map, timings and a
full price breakdown. Registered users or guests submit it; an agent/admin reviews,
edits and approves it. Full spec: docs/SRS.md (always follow it).
Phase-by-phase build prompts and the data-sourcing plan: docs/BUILD-PROMPTS.md.

## Stack (do not change without asking)
- Laravel 13, PHP 8.4 (XAMPP's PHP was upgraded from 8.2; backup at C:\xampp\php82-backup),
  running on XAMPP (Apache + MariaDB 10.4) on Windows
- Blade templates + Alpine.js; Livewire 3 only for the trip builder wizard
- Tailwind CSS v4 via Vite (`@tailwindcss/vite`, config lives in resources/css/app.css —
  there is no tailwind.config.js), Flowbite 4 components, @tailwindcss/forms
- Leaflet.js + Leaflet.markercluster + OpenStreetMap tiles for maps (resources/js/map.js,
  loaded only on map pages); SortableJS for drag-and-drop
- MySQL database name: `lankaguide360_app` (utf8mb4_unicode_ci). The old plain-PHP project
  still owns the `lankaguide360` database — never touch it.
- Tests run against the MySQL database `lankaguide360_app_test` (phpunit.xml), PHPUnit 12
- Auth: Laravel Breeze (Blade). Roles: traveller, agent, admin (users.role, App\Enums\UserRole)
- PDF: barryvdh/laravel-dompdf. Queue driver: database. Mail: SMTP (Mailtrap locally;
  MAIL_MAILER=log until Mailtrap credentials are added)

## Architecture rules
- Controllers stay thin. Business logic lives in app/Services:
  ItineraryService, PricingService, SuggestionService, RoutingService, ChatbotService,
  and app/Services/Import/* for data importers.
- Validation in Form Request classes. Authorization with policies + RoleMiddleware
  (`role:admin,agent`) + gates `admin`, `agent`, `staff` (AppServiceProvider).
- All external HTTP calls go through the backend with Laravel Http client, a descriptive
  User-Agent (config('lankaguide.user_agent') = "LankaGuide360/1.0 (contact@lankaguide360.lk)"),
  timeouts, retries and caching. Never call external services from browser JavaScript.
- Table and column names exactly as in docs/SRS.md Section 7.
- Prices: DECIMAL, currency USD by default; never floats.
- Every model with a public page has a unique slug.

## Database notes (phase 2 decisions)
- One migration per SRS 7.3 table, same names and column types (SMALLINT/INT keys for
  master data, so foreign keys use unsignedSmallInteger/unsignedInteger, not foreignId).
- Additions beyond SRS 7.3: `media` (morph gallery, replaces `place_images`), `import_logs`;
  places + hotels have `status` (draft|published), `wikipedia_title`, `osm_id`; places also
  `source`, `wikipedia_url`; hotels also `slug`, `type`, `website`, `phone`, `address`,
  timestamps; `room_rates.is_estimate`; `provinces.slug`. `places.lat/lng` are nullable
  (the Wikipedia importer fills them).
- Enum columns cast to PHP enums in app/Enums (Tier, TripStatus, MealPlan, GuideType,
  CrowdLevel, BestTimeSlot, PublishStatus, PriceCategory, MediaSource, SenderRole).
- `Trip::tripDays()` is the itinerary relation — `trips.days` is the day-count column.
- Morph map (AppServiceProvider): place, hotel, district, package, trip, user.
- Scopes use `#[Scope]`: `published()`, `drafts()`, `tier()`, `inDistricts()`,
  `inCategories()`, `hiddenGems()`, `forPax()`, `validOn()`, `speaks()`.
- Settings: `Setting::get('tax_percent')` / `Setting::put(...)` (cached).
- Seeders are idempotent (firstOrCreate) and read arrays from database/seeders/data/*.php.
  Category → district links follow SRS 4.1 plus three documented extras in
  data/categories.php (Colombo + Trincomalee → Historical, Kegalle → Nature).
- Vehicle/guide rates and settings values are PLACEHOLDERS until the admin sets them.

## Data and images rules
- Only use freely licensed sources: Wikipedia/Wikivoyage (text, CC BY-SA),
  Wikimedia Commons (images, keep license + author + source URL),
  OpenStreetMap via Overpass/Nominatim (ODbL, show attribution), geoBoundaries (open data).
- Never scrape Google Images, Booking.com, TripAdvisor, Agoda or other sites whose
  terms forbid it. Admin-uploaded photos must record who owns them.
- Every imported image is downloaded, resized to WebP (1600px, 800px, 400px) and
  stored in storage/app/public/media with a row in the media table
  (source_url, author, license, license_url).
- Respect rate limits: Nominatim max 1 request/second; Overpass one query at a time;
  Wikimedia: sequential requests with a short delay.
- Imported records start as status=draft; an admin reviews and publishes them.

## Importers (phase 3)
- Code: app/Services/Import (one class per source, all extend `Importer`), commands in
  app/Console/Commands/Import, queued via `App\Jobs\RunImport`, names in `ImporterRegistry`.
- All HTTP goes through `ImportClient` (User-Agent, 20 s timeout, 3 tries on 429/5xx, per-service
  delays, raw responses cached in storage/app/import-cache; `--fresh` bypasses the cache).
- `lg:import-districts` writes public/geo/lk-districts.geojson (slug per feature) and must run
  before `lg:import-hotels` / `lg:suggest-places`, which query by district bbox and keep only
  results inside the polygon.
- Importers never publish and never overwrite published rows (Wikipedia only fills empty
  fields on published places; hotels keep admin tier/status). Every run writes import_logs.
- Wikipedia 404 → one search, suggestions logged as `not_found`; the title is never changed.
- Commons: CC0 / CC BY / CC BY-SA / public domain only, ≥ 1200 px, no maps/logos/SVG;
  WebP 400/800/1600 via `App\Services\Media\ImageProcessor` (reuse it for admin uploads).
- If overpass-api.de is busy (504), set OVERPASS_URL in .env to a mirror.
- Tests: extend tests/Feature/Import/ImportTestCase (Http::preventStrayRequests, Sleep::fake,
  fixtures in tests/Fixtures/import).

## Admin panel (phase 4)
- Controllers in app/Http/Controllers/Admin, one Form Request per form in
  app/Http/Requests/Admin, routes under `/admin` (`role:admin,agent`).
- Policies: `MasterDataPolicy` (agents view, admins change) for places, districts, categories,
  hotels, room rates, vehicles, guides, cuisines, packages; `AdminOnlyPolicy` for users,
  reviews, contact messages, media, import logs, settings. Agents see master-data forms
  read-only (`<fieldset disabled>`); every write is also blocked in the controller/request.
- Publishing rules live in `App\Services\Admin\PublishingService` (place needs coordinates +
  a category; hotel needs coordinates + town). Review queue and bulk actions both use it.
  "Reject" hides a record (`is_active = false`, still draft) so importers don't re-suggest it.
- Photos: `App\Services\Media\MediaLibrary` (upload, URL import, Commons, reject, cover,
  reorder). Media status is draft | published | rejected (`MediaStatus`); rejected Commons
  photos keep their row so the importer never fetches them again. `HasMedia::media()`
  excludes rejected; `allMedia()` includes them. Admin uploads/URL imports require owner,
  license and a "right to use" checkbox; URL imports refuse private/local addresses.
- Admin Blade components: resources/views/components/admin/* (input, select, textarea,
  checkbox, filters, bulk-bar, map-picker, tag-input, photo-card, media-manager, …).
  Shared CSS classes in app.css: .btn, .btn-primary, .form-control, .admin-card, .admin-table.
- Admin JS (resources/js/admin.js): Alpine components mapPicker, tagInput, sortableGallery,
  commonsSearch, bulkSelect. app.js starts Alpine on DOMContentLoaded so page bundles can
  register components first.
- Blade gotcha: a directive right after a letter (`Latitude@if`) is not compiled — add a space.

## Public site, trips and packages (phases 5, 7–12)
- Public controllers: Home, Destination (index/show/district + mapData), Contact, Newsletter,
  Package (index/show/customize), Credits, Seo (sitemap.xml + robots.txt, no extra package),
  Chatbot (`POST /api/chat`, throttle `chat` 20/10 min), Trip (submit, private page, PDF,
  messages, cancel, review, My Trips). `SiteCatalog` caches plain arrays only (Laravel 13
  refuses to unserialize Eloquent objects from the cache).
- Builder state: `App\Support\TripDraft` in the session (`trip_draft`); "Add to my trip"
  toggles `trip_draft.place_ids`. Packages load into it via `PackageService::toDraft()`.
- Itinerary engine: `ItineraryService::generate()/retime()` (SRS 5.3) + `RoutingService`
  (ORS when `ORS_API_KEY` is set, else haversine × 1.35 at 45 km/h, 30 km/h in the hills;
  cached in `route_cache`). Map data for Leaflet: `App\Support\TripMapData` → Alpine `tripMap`.
- Money: `App\Support\Money` (integer cents, bcmath). `PricingService::breakdown()` →
  `PriceBreakdown`; `SuggestionService` picks hotels, vehicle (luggage bumps a size), guide.
- Submission: `TripSubmissionService` (one transaction, reference `LG360-{year}-{00001}`,
  40-char `access_token`, queues TripSubmitted + NewTripRequest). Trip page access = owner,
  staff, or the emailed token link (then remembered in the session); anything else is 404.
- Workflow: `TripWorkflow::transition()` validates `TripStatus::TRANSITIONS`, needs a reason to
  reject and a final price to approve, writes `trip_status_history`, emails the traveller
  (PDF attached on approval when dompdf is installed — `TripPdf::available()`).
- Agent screens: `Admin\TripRequestController` (queue tabs, assign, status, hotels per night,
  vehicle/guide/meals, price lines, stops, messages, "Save as package" for admins).
  `internal_notes` are never shown to travellers (hidden attribute + not rendered).
- Packages are template trips (status draft, reference `PKG-…`, no travellers), seeded from
  database/seeders/data/packages.php. The agent queue never shows draft trips.
- Trip Builder: `App\Livewire\TripBuilder` (view livewire/trip-builder + trip-builder/step-1…8,
  summary, plan-notices) at `/plan`. The session TripDraft is the source of truth: every change
  is written back with `persist()`; changing tier, places, dates, days or arrival/departure
  clears the itinerary and hotels (`invalidate`). `?step=` is kept in the URL (clamped to
  completed steps + 1); `?category=` / `?place=` pre-fill. Step 5 → 6 runs
  `ItineraryService::generate()`; step 8 drag-and-drop calls `reorder()` → `retime()`.
  Step 8 submits a plain form to `TripController@store`.
- Livewire + Alpine are bundled in app.js from livewire.esm (`inject_assets` false, layouts use
  `@livewireStyles` / `@livewireScriptConfig`); page bundles still register components on
  `alpine:init`. resources/js/builder.js: `districtPicker` (step 3 map) and `itinerarySort`
  (SortableJS; undoes the DOM move and lets Livewire re-render). `tripMap` listens for
  `itinerary-updated` and `trip-map-filter`. `.leaflet-container` is isolated in app.css so
  Leaflet panes never cover sticky headers or the mobile summary sheet.
- The builder only offers published places with coordinates and published hotels with room
  rates: publish data in the admin review queue first.
- Security headers + CSP: `App\Http\Middleware\SecurityHeaders` (web group). Add any new
  external host to the CSP there. Local env logs lazy loading (N+1) to storage/logs.
- Deployment checklist: docs/DEPLOY.md.

## UI rules
- Mobile-first, responsive at sm/md/lg/xl. Brand colours: teal #0F766E primary
  (`primary-*` scale, 700 = #0F766E), amber #F59E0B accent (`accent-*`, 500 = #F59E0B),
  slate text. Flowbite's `brand` tokens point at the teal scale. Font: Inter (Google Fonts)
  with system fallback.
- Reusable Blade components in resources/views/components (navbar, footer, chat-widget,
  logo, icon, page-header, …). Layouts: `<x-app-layout title="…">` (public),
  `<x-admin-layout title="…">` (back office), `<x-guest-layout>` (auth screens).
- Every image: alt text, loading="lazy", width/height set, WebP with srcset.
- Show image credits (author + license) under or on hover for Commons photos.

## Quality rules
- PSR-12 (run ./vendor/bin/pint). Write PHPUnit feature tests for each feature.
- Run `php artisan test` before saying a task is done, and report the result.
- Never commit .env. Never hard-code keys. Use config() values from .env.
- When unsure about a requirement, read docs/SRS.md first, then ask me.

## Commands (PHP lives at C:\xampp\php — on PATH for new terminals)
- Dev: `npm run dev` + `php artisan serve` + `php artisan queue:work`
- Build: `npm run build`
- Tests: `php artisan test`
- Reset DB: `php artisan migrate:fresh --seed`
- Import data: `php artisan lg:import-districts`, `lg:import-places`,
  `lg:import-hotels`, `lg:import-images`, `lg:import-all`
- Seeded logins: admin@lankaguide360.test / agent@lankaguide360.test, password `password`

## Build progress
- [x] Phase 1 — Project skeleton, auth, roles, layouts
- [x] Phase 2 — Database, models, seed data
- [x] Phase 3 — Data importers (Wikipedia, Commons, OSM, geoBoundaries)
- [x] Phase 4 — Admin panel + review queue + media manager
- [x] Phase 5 — Public pages
- [x] Phase 6 — Trip Builder wizard (Livewire, all 8 steps)
- [x] Phase 7 — Itinerary engine + routing
- [x] Phase 8 — Hotels, meals, transport, guide, pricing
- [x] Phase 9 — Review page, map, submit, emails, PDF
- [x] Phase 10 — Agent approval workflow + My Trips
- [x] Phase 11 — AI chatbot
- [x] Phase 12 — Packages, SEO, speed, security, tests



Website	http://127.0.0.1:8000
Log in	http://127.0.0.1:8000/login
Admin panel	http://127.0.0.1:8000/admin
Database (phpMyAdmin)	http://localhost/phpmyadmin, then open the lankaguide360_app database


Logging in as admin

Open http://127.0.0.1:8000/login.
Email admin@lankaguide360.test, password password.
After login you're taken straight to the admin panel. You can also reach it any time from the user menu (top right), under "Admin panel".


The agent account is agent@lankaguide360.test with password password. It sees fewer menu items, and a normal registered traveller gets "403 Forbidden" on /admin.