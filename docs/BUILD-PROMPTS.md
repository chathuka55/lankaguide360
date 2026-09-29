# LankaGuide360 — Claude Code build prompts

Twelve prompts take the project from an empty folder to a working site. Run them in order,
one per fresh Claude Code session in plan mode, then check the listed results before committing.
Project rules: [../CLAUDE.md](../CLAUDE.md). Specification: [SRS.md](SRS.md).

| Phase | Builds | Depends on |
|---|---|---|
| 1 | Project skeleton, auth, roles, layout | — |
| 2 | Database, models, seed data | 1 |
| 3 | Data importers (Wikipedia, Commons, OSM) | 2 |
| 4 | Admin panel + review queue + media manager | 3 |
| 5 | Public pages: Home, Destinations, About, Contact | 4 |
| 6 | Trip Builder steps 1–5 | 5 |
| 7 | Itinerary engine + routing | 6 |
| 8 | Hotels, meals, transport, guide, pricing | 7 |
| 9 | Review page, map, submit, emails, PDF | 8 |
| 10 | Agent approval workflow + My Trips | 9 |
| 11 | AI chatbot | 5 |
| 12 | Packages, SEO, speed, security, tests | all |

---

## 3. Data sourcing: photos, places and hotels

The site fills itself with places, hotels and photos from free, openly licensed sources that
need no API key: Wikipedia, Wikimedia Commons, OpenStreetMap and geoBoundaries. The admin then
reviews and publishes.

The prompts deliberately do not scrape Google Images, Booking.com or TripAdvisor. Their terms
forbid automated scraping, they block it with CAPTCHAs so scripts break often, and their photos
belong to photographers and hotels, so using them on a business site invites takedown notices.
Commons photos come with a license you can show next to each image, which keeps the site safe.

**Nothing reaches the site until an admin approves it.**

```
Wikipedia            Wikimedia Commons     OpenStreetMap            geoBoundaries
descriptions,coords  place photos          hotels, POIs, coords     district shapes
text: CC BY-SA       license per image     Overpass, ODbL           GeoJSON, open data
        \                  |                      |                      /
         └──────── Artisan import commands (lg:import-*) ──────────────┘
                   rate-limited, cached, descriptive User-Agent
                              |
        Staging database                         Admin manual input
        records saved as draft                   upload own or partner hotel photos
        photos resized to WebP, credit stored    record owner and license; enter prices
                              \                  /
                           Admin review queue
               check facts, pick cover photo, set categories, tier and prices
                                     |
                           Published on the site
                 Destinations, hotels, Trip Builder, Home page
```

Every import lands as a draft; the admin is the gate, so a wrong photo or closed hotel never
goes live by itself.

### 3.1 Sources and what each provides

| Data | Source (free, no key) | How the importer gets it | License to show |
|---|---|---|---|
| District boundaries (25) | geoBoundaries, Sri Lanka ADM2 GeoJSON | Download once, store in `public/geo/lk-districts.geojson`, seed district centroids | Open data, credit geoBoundaries |
| Place descriptions + coordinates | Wikipedia REST API `https://en.wikipedia.org/api/rest_v1/page/summary/{Title}` | One call per place title from the seed list; saves extract, coordinates, page URL | CC BY-SA, link to article |
| Travel tips (best time, getting there) | Wikivoyage API `https://en.wikivoyage.org/w/api.php` | Section text for each district town | CC BY-SA, link to page |
| Place photos | Wikimedia Commons API `https://commons.wikimedia.org/w/api.php` (search in File namespace, `prop=imageinfo`, `iiprop=url\|extmetadata`, `iiurlwidth=1600`) | Up to 6 images per place; keeps author, license, source URL | Per image, shown as credit |
| Hotels, guesthouses, resorts | OpenStreetMap via Overpass `https://overpass-api.de/api/interpreter` (`tourism=hotel\|guest_house\|hostel\|resort` inside Sri Lanka) | One query per district; saves name, coords, stars, website, phone | ODbL, "© OpenStreetMap contributors" |
| Extra attractions, waterfalls, peaks, beaches | Overpass (`tourism=attraction\|viewpoint`, `natural=waterfall\|peak\|beach`, `historic=*`) | Suggests new places for admin approval, good for Hidden Gems | ODbL |
| Address lookup | Nominatim `https://nominatim.openstreetmap.org` | Only for admin-typed addresses, max 1 request/second | ODbL |

### 3.2 What free sources cannot give (admin enters it)

- **Hotel prices and room types:** no free source has live rates. The admin enters rate bands per
  hotel; the seeder adds placeholder bands per tier flagged "estimate".
- **Hotel tier:** auto-guessed from OSM (`stars` 5 → Luxury, 3–4 → Premium, guest house/hostel/no
  stars → Budget); admin corrects.
- **Hotel photos:** OSM has none. Use Commons if a photo exists, otherwise ask partner hotels for
  their photos and upload them with the owner recorded.
- **Entry fees, opening hours, visit duration:** seeded from a starter list, then admin-maintained.
- **Vehicle and guide rates:** admin price tables.

---

## 4. Phase prompts

### Phase 1 — Project skeleton ✅ done 2026-09-29

```
Read CLAUDE.md and docs/SRS.md sections 1, 3 and 6 first.
Create the Laravel 13 project in the current folder for LankaGuide360:
1. composer create-project laravel/laravel . ; configure .env for MySQL database
   lankaguide360 on 127.0.0.1:3306, user root, empty password, QUEUE_CONNECTION=database.
2. Install Laravel Breeze with the Blade stack. Install and configure Tailwind v4 via Vite,
   Flowbite, Alpine.js, Leaflet, Leaflet.markercluster, SortableJS. Add Inter font.
3. Add a `role` enum column (traveller, agent, admin; default traveller) plus phone,
   country, age to users. Create RoleMiddleware registered as alias `role`, and
   Gate/Policy helpers isAdmin(), isAgent().
4. Build layouts/app.blade.php with: sticky responsive navbar (logo text "LankaGuide360",
   Home, Destinations, Plan a Trip, About, Contact, Login/Register or My Trips/user menu,
   mobile hamburger), a slot for page content, a full footer (logo + tagline, quick
   links, top categories, contact info with WhatsApp link, social icons, newsletter
   field, copyright + Privacy/Terms links) and a placeholder floating chat button.
5. Build layouts/admin.blade.php with a collapsible sidebar and top bar.
6. Create routes and placeholder views for /, /destinations, /plan, /about, /contact,
   /my-trips (auth), /admin (role:admin,agent).
7. Brand colours from CLAUDE.md as Tailwind theme variables.
8. Feature tests: guest sees home; guest is redirected from /admin; traveller gets 403
   on /admin; admin gets 200.
Run the tests and `npm run build`, then report.
```

**Check:** `php artisan serve` shows the navbar and footer on every page, the mobile menu works at 375 px width, tests pass.

### Phase 2 — Database, models and seed data ✅ done 2026-09-29

```
Read docs/SRS.md section 7 (database) and section 4.1 (category → district mapping).
1. Create migrations for every table in SRS 7.3 in dependency order, exactly as named.
   Add these extra tables:
   - media: id, mediable_type, mediable_id (morphs), path, variants (JSON: 400/800/1600
     webp paths), width, height, alt, caption, source (commons|upload|url), source_url,
     author, license, license_url, is_cover (bool), sort_order, status (draft|published).
   - import_logs: id, importer, target_type, target_id, status, message, created_at.
   Add `status` (draft|published) and `wikipedia_title`, `osm_id` (nullable, unique)
   to places and hotels; add `osm_id`, `website`, `phone`, `address` to hotels.
2. Eloquent models with all relationships (belongsTo, hasMany, belongsToMany,
   morphMany media with cover() helper), casts for JSON/enums/dates, and scopes:
   published(), tier($tier), inDistricts($ids), inCategories($ids), hiddenGems().
3. Seeders (data in database/seeders/data/*.php arrays):
   - 9 provinces and all 25 districts of Sri Lanka with correct province and centroid lat/lng.
   - 6 categories: Beach & Coastal, Historical & Cultural, Hill Country,
     Nature & Wildlife, Hiking & Adventure, Hidden Gems (with icons) and the
     category_district links from SRS 4.1.
   - A starter list of at least 100 real, well-known Sri Lankan places across all
     categories: name, district, categories, exact English Wikipedia article title,
     approximate visit_minutes, best_time_slot, crowd_level, is_hidden_gem. Only include
     places you are confident exist; mark coordinates null (the importer fills them).
   - Vehicles and guides per SRS 5.2 / 5.5 with placeholder rates; cuisines; settings
     (service_fee_budget/premium/luxury %, tax %, usd_lkr rate).
   - admin@lankaguide360.test and agent@lankaguide360.test, password "password".
4. Factories for trips, trip_days, trip_stops for tests.
5. Test: migrate:fresh --seed runs clean; 25 districts; every district in a category
   mapping exists; relationships load.
```

**Check:** phpMyAdmin shows all tables; places has 100+ draft rows; tests pass.

### Phase 3 — Data importers (no paid APIs) ✅ done 2026-09-29

Built as specified, with these choices: `--limit` on `lg:import-images` caps the number of
places (`--per-place` sets photos per place, default 6); every command also takes `--fresh`
and `--queue`; Wikivoyage travel tips (§3.1) are not imported yet.

```
Read CLAUDE.md "Data and images rules" and the Data sourcing section of docs/BUILD-PROMPTS.md.
Build importers in app/Services/Import using Laravel's Http client with a descriptive
User-Agent, 20 s timeout, retry(3, 2000), and caching of raw responses in
storage/app/import-cache (so re-runs don't re-download).
1. GeoBoundariesImporter: download the Sri Lanka ADM2 GeoJSON from geoBoundaries,
   simplify if > 2 MB, save to public/geo/lk-districts.geojson, match features to
   districts by name (handle spelling variants like Monaragala/Moneragala), update centroids.
2. WikipediaImporter: for each place with wikipedia_title, call the REST summary
   endpoint; save short_description (first 2 sentences), description (extract),
   lat/lng if present, wikipedia_url. If a title 404s, try the search API once and
   log the result for admin review instead of guessing.
3. CommonsImageImporter: for each place, search Wikimedia Commons File namespace using
   the place name + "Sri Lanka", take up to 6 images with width >= 1200, skip SVG/maps/
   logos, read extmetadata (Artist, LicenseShortName, LicenseUrl, ImageDescription),
   only accept CC0, CC BY, CC BY-SA and public domain. Download, convert with
   intervention/image v3 to WebP at 1600/800/400 px, save under
   storage/app/public/media/{type}/{id}/, create media rows (status draft), strip HTML
   from author names. First image becomes cover unless one exists.
4. OverpassHotelImporter: per district (use the district name area or its bounding box
   from the GeoJSON), query tourism=hotel|guest_house|hostel|resort|apartment with
   `out center tags;`. Upsert by osm_id: name, lat/lng, stars, website, phone,
   addr:* fields, town (addr:city or nearest district town). Guess tier from stars and
   type (5 → luxury, 3-4 → premium, else budget), kid_friendly false by default.
   Skip unnamed features. Wait 10 s between districts.
5. OverpassPlaceSuggester: query tourism=attraction|viewpoint, natural=waterfall|peak|
   beach, historic=* per district; insert new ones (not matching existing names within
   300 m) as draft places with source=osm for admin review; flag low-profile ones as
   hidden-gem candidates.
6. Artisan commands: lg:import-districts, lg:import-places {--only=slug},
   lg:import-images {--place=} {--limit=}, lg:import-hotels {--district=},
   lg:suggest-places, lg:import-all. Each shows a progress bar, writes import_logs,
   and is also dispatchable as a queued job.
7. Tests using Http::fake with saved sample JSON for each source.
Do not use Google, Booking.com, TripAdvisor or any site that forbids scraping.
```

**Check:** `php artisan lg:import-all` fills descriptions, coordinates, 3–6 photos per major place with credits, and hundreds of hotels; storage/app/public/media has WebP files.

### Phase 4 — Admin and agent panel ✅ done 2026-09-29

Built as specified. Choices: districts are edit-only (the 25 are fixed); packages pick an
existing template trip until "Save as package" arrives in phase 12; photos chosen or uploaded
by an admin are published immediately, importer photos stay drafts; rejected Commons photos
are remembered (media status `rejected`).

```
Read docs/SRS.md sections 3.1, 4 (FR-20, FR-26) and CLAUDE.md.
Build the admin panel under /admin using layouts/admin.blade.php:
1. Dashboard: counts (trips by status, drafts awaiting review, places, hotels), latest
   requests, latest import logs.
2. CRUD with search, filters, pagination and bulk publish/unpublish for: places (with
   category checkboxes, map picker for lat/lng using Leaflet, opening hours, fees, visit
   minutes, crowd level, hidden gem), districts, categories, hotels (tier, stars,
   kid_friendly, amenities as tag chips) with nested room_rates, vehicles, guides,
   cuisines, packages, reviews/testimonials, contact messages, users (role change,
   admin only), settings.
3. Review queue page "Imported content": tabs Places / Hotels / Photos showing draft
   items side by side with their source link and license; buttons Publish, Edit,
   Reject. Photos grid lets admin set cover, reorder (SortableJS), edit alt text, delete.
4. Media manager on place/hotel edit pages: upload images (validate type/size, convert
   to WebP variants, require owner/credit and license fields), "Search Wikimedia
   Commons" modal that shows results with license and imports the chosen ones, and
   "Import from URL" that requires the admin to tick "I have the right to use this
   image" and fill owner + license.
5. Import tools page: buttons to run each importer as a queued job, with last run time
   and log output.
6. Agents see only Dashboard, Trip requests (built in phase 10), and read-only master
   data; admins see everything. Enforce with policies, not just hidden links.
7. Flash messages, confirm dialogs for deletes, validation errors inline.
Feature tests for authorization and one CRUD flow each for places and hotels.
```

**Check:** log in as admin, publish 20 places and their cover photos, edit a hotel's tier and add a room rate; the agent account cannot open Users.

### Phase 5 — Public pages ✅ done 2026-09-29

```
Read docs/SRS.md 3.3, 3.4, FR-01, FR-02, FR-29. Only published records are shown.
1. Home page: full-screen hero (cover photo from a featured place, dark gradient
   overlay, headline "Plan your perfect Sri Lanka journey", subtext, big "Start
   Planning" button to /plan, category quick chips linking to /plan?category=slug);
   How it works (3 steps); Suggested trip packages (cards: image, name, days, tier
   badge, from-price, Book Now + Customize buttons); Explore by category (6 tiles);
   Top destinations (8 cards); Hidden gems strip; Why choose us; Testimonials carousel
   (approved reviews); animated stats counter; newsletter; footer.
2. /destinations: filters (category, district, province, hidden gems) with query-string
   state, search, grid/map toggle (Leaflet with markercluster, popup cards), pagination.
3. /destinations/{district}/{place}: photo gallery with lightbox and credits,
   description, facts box (time needed, fee, hours, best time, crowd level), mini map,
   nearby places (haversine within 30 km), nearby hotels by tier, "Add to my trip"
   (adds to builder session and shows toast), Wikipedia attribution link.
4. /districts/{slug}: district intro, places by category, hotels, map with the district
   polygon from lk-districts.geojson.
5. /about and /contact (form stored in contact_messages, emailed to admin, throttled,
   honeypot field). Contact page shows office map, phone, WhatsApp, email, FAQ.
6. Map attribution "© OpenStreetMap contributors"; photo credits "Photo: {author},
   {license}, via Wikimedia Commons" with links.
7. SEO basics: unique titles/meta descriptions, Open Graph image = cover photo.
Make every page responsive and check at 375, 768 and 1280 px.
```

**Check:** Home looks complete on phone and desktop, filters combine correctly, place pages show credited photos.

### Phase 6 — Trip Builder steps 1–5 ✅ done 2026-09-29 (Livewire; the wizard also covers steps 6–8)

```
Read docs/SRS.md section 5.1 and 5.2 and FR-03 to FR-08, FR-27.
Build the wizard at /plan as a Livewire 3 component TripBuilder with a TripDraft value
object stored in the session (and in trips.status=draft for logged-in users).
1. Progress stepper (8 steps, current highlighted, completed clickable), Back/Next,
   and a live summary panel (right sidebar on lg+, collapsible bottom sheet on mobile)
   showing tier, categories, districts, places count, days, travellers, running total.
2. Step 1 Travel style: 3 large cards Budget / Premium / Luxury with icon, one-line
   description and "from $X per person per day" read from settings.
3. Step 2 Interests: 6 category chips, multi-select, at least one required.
4. Step 3 Districts: Leaflet map using lk-districts.geojson; districts linked to the
   chosen categories are coloured and clickable, others greyed; plus cards grouped by
   province with photo and place count. Selecting on map or card stays in sync.
5. Step 4 Places: tabs per selected district; place cards (photo, category badges, time
   needed, fee, crowd level, hidden gem badge, Add toggle); filters by category;
   counter "N places ≈ D days recommended" (sum of visit time + estimated driving / 8 h).
   Pre-tick places passed via ?place= or "Add to my trip".
6. Step 5 Dates and travellers: start date (not in the past), days 1-21 prefilled with
   the recommendation, adults, children with individual ages, infants, arrival and
   departure point (BIA Katunayake default, Colombo, Mattala), luggage count.
7. Validation per step with inline messages; refreshing the page keeps all choices.
8. Tests: category → districts filter, district → places filter, session persistence.
```

**Check:** pick Beach + Hill Country → only matching districts light up → choose Galle and Kandy → their places appear → refresh keeps everything.

### Phase 7 — Itinerary engine and routing ✅ done 2026-09-29

```
Read docs/SRS.md section 5.3 and diagram 8.2.
1. RoutingService::leg(from, to): returns km, minutes, geometry (array of lat/lng).
   - If ORS_API_KEY is set in .env, call OpenRouteService driving-car (free key) from the backend.
   - Otherwise use a free offline estimate: haversine distance × 1.35 road factor;
     average speed 45 km/h, 30 km/h if either point is above 800 m altitude or in
     Nuwara Eliya/Badulla/Kandy hill areas; geometry = straight line.
   - Cache every leg in route_cache (rounded coordinates) forever; admin can clear it.
2. ItineraryService::generate(TripDraft): implement SRS 5.3 exactly:
   load places → order districts as a loop from the arrival point by nearest neighbour
   on centroids → order places inside each district by nearest neighbour → fill days
   under the tier's daily hour limit (budget 10, premium 9, luxury 8) respecting opening
   hours and best_time_slot (sunrise places first thing, sunset places last) → lunch
   12:30-13:30 → overnight town = nearest town with a published hotel of the tier →
   last day ends at the departure point.
   If the user's days are more than needed, add relaxed/beach days in the best town;
   if fewer, drop lowest-priority places (hidden gems last to drop only if chosen) and
   return a `dropped` list with reasons.
3. Return a DTO: days[] with date, title (e.g. "Kandy → Nuwara Eliya"), stops[] with
   arrive/depart times, leg km and minutes, overnight town, drive totals, warnings[]
   (e.g. "Day 3 has 7 h driving").
4. Wire it into the builder: after step 5, call generate and show a loading state.
5. Unit tests with fixed places: correct day count, no day over the limit, sunrise
   place first, deterministic output.
```

**Check:** 10 places in 4 districts over 6 days gives sensible days with times, no day over the limit, and warnings when days are too few.

### Phase 8 — Hotels, meals, transport, guide and pricing ✅ done 2026-09-29

```
Read docs/SRS.md 5.2, 5.4, 5.5, FR-10 to FR-13, FR-16 and diagram 8.3.
1. Step 6 Stays and meals: for each night, SuggestionService returns 3 published
   hotels in the overnight town matching the tier (filters: kid-friendly auto-on when
   children exist, pool, beach-front), with cover photo, stars and the room rate for
   that date and meal plan. User can swap hotels, choose room type. Meal plan radio
   (RO/BB/HB/FB) defaulted by tier, cuisine chips, dietary notes.
2. Step 7 Transport and guide: vehicle suggested from total travellers and luggage per
   SRS 5.5 and tier, with child seat when a child is under 4; allow override only to a
   vehicle with enough seats. Guide type and language per tier defaults.
3. PricingService::breakdown(TripDraft): line items for accommodation (per night,
   rooms = ceil(adults/2) + extra beds), transport (day rate × days + km × km rate),
   guide, meals not included in room rates, entry tickets (foreign adult/child fees),
   activities, service fee % by tier, tax %, total, per person, LKR equivalent.
   Everything from DB/settings, no hard-coded prices. Money with bcmath or integer cents.
4. Summary panel updates live on every change.
5. Unit tests for pricing with known inputs, and vehicle selection edge cases (3, 4, 9,
   10 travellers; child under 4).
```

**Check:** switching Budget → Luxury changes hotels, vehicle and total; the sum of lines equals the total.

### Phase 9 — Review page, map, submission, emails, PDF ✅ done 2026-09-29

```
Read docs/SRS.md FR-14 to FR-19, FR-25, diagrams 8.4 and 8.7.
1. Step 8 Review: day-by-day timeline (cards with times, drive time/km between stops,
   hotel, meals); Leaflet map with numbered markers, each day's route in its own colour
   with a day filter; SortableJS drag to reorder stops or move to another day, then
   re-run timing and pricing; price breakdown table grouped by category with total and
   per person, labelled "Estimate — final price confirmed by your agent"; Edit links
   back to each step; special requests textarea; consent checkbox.
2. Submit: logged-in users submit directly. Guests see a form: full name, country
   (select), age, email, phone with country code, WhatsApp (optional), companions'
   names/ages, plus an optional "create an account with this email" password field.
3. On submit (DB transaction): create trip with reference LG360-{year}-{00001},
   random 40-char access_token, status submitted; copy days, stops, hotels,
   travellers, price items; write trip_status_history.
4. Confirmation page with reference and next steps. Queued emails: to traveller
   (summary + signed link /trips/{reference}?token=...), to all agents (new request).
5. /trips/{reference}: read-only plan view for the owner or a valid token; status
   badge and timeline of status history; Download PDF (dompdf template with map
   snapshot replaced by a day list and static route table).
6. Rate-limit submissions; tests for guest and registered submission and token access.
```

**Check:** as a guest, submit a trip, receive the Mailtrap email, open the link in a private window, download the PDF.

### Phase 10 — Agent approval workflow and My Trips ✅ done 2026-09-29

```
Read docs/SRS.md FR-20 to FR-22, FR-30, diagrams 8.5 and 8.8.
1. /admin/trips request queue: tabs by status, search by reference/name/email, filters
   by date and tier, "Assign to me" and assign-to-agent.
2. Trip detail for agents: full plan, map, travellers' contact (click-to-WhatsApp and
   mailto), editable days/stops/hotels/vehicle/guide and price lines (recalculate or
   override final_total with a reason), internal notes, message thread.
3. State machine per diagram 8.8 in a TripStatus enum with allowed transitions only;
   every change writes trip_status_history and sends the traveller an email (approved
   email attaches the PDF; rejected email includes the reason).
4. Messages: traveller and agent thread on the trip page, email notification on new
   message, unread badge.
5. /my-trips for travellers: list with status badges, view, cancel before confirmed,
   messages, leave a review after completed (goes to admin approval).
6. Tests: invalid transitions are refused; travellers cannot see others' trips.
```

**Check:** agent opens the guest trip, changes a hotel, approves with a final price; the traveller gets the approval email and sees the new status.

### Phase 11 — AI chatbot (Hugging Face) ✅ done 2026-09-29

```
Read docs/SRS.md section 6.5 and diagram 8.6.
1. Chat widget component (Alpine.js) on every public page: floating button, panel
   with message list, typing indicator, suggested questions ("Best beaches in
   December?", "Plan a 7-day trip", "Hidden gems near Kandy"), link buttons, mobile
   full-screen mode, conversation kept in sessionStorage.
2. POST /api/chat (throttle 20 per 10 min) → ChatbotService:
   - Retrieve context: FULLTEXT search over published places, districts, packages and
     an faqs table (create it with 20 seeded FAQs: visa/ETA, best time, currency,
     dress code at temples, safety, how the builder works, prices, cancellation).
   - Call https://router.huggingface.co/v1/chat/completions with HF_TOKEN and HF_MODEL
     from .env, system prompt: LankaGuide360 Sri Lanka travel assistant, answer briefly
     and warmly, only recommend places from the context, add markdown links to site
     URLs from the context, suggest the Trip Builder (/plan) for planning, never invent
     prices, say when unsure.
   - 15 s timeout; on failure answer from the best-matching FAQ.
   - Save to chat_logs; strip any HTML; convert markdown links to button chips in UI.
3. Admin page to read chat logs and edit FAQs.
4. Tests with Http::fake for success and fallback.
```

**Check:** ask "Where can I see elephants?" and get an answer with a link to a real place page; with HF_TOKEN removed, the FAQ fallback still answers.

### Phase 12 — Packages, SEO, speed, security, tests ✅ done 2026-09-29 (sitemap built in-house, no spatie package needed)

```
Read docs/SRS.md section 9 (non-functional requirements) and FR-23.
1. Packages: admin builds a package by creating a template trip in the builder and
   clicking "Save as package". Seed 6 packages (e.g. 5-day Cultural Triangle, 7-day Hill
   Country & Beaches, 10-day Classic Sri Lanka, 4-day South Coast Beaches, 6-day
   Wildlife & Safari, 8-day Hidden Gems), each in a tier. Home "Book Now" opens the
   review step with the template copied; "Customize" opens step 4.
2. SEO: spatie/laravel-sitemap sitemap.xml, robots.txt, canonical URLs, schema.org
   TouristAttraction/Hotel/TravelAgency JSON-LD, breadcrumb markup.
3. Speed: eager loading everywhere (enable Model::preventLazyLoading in local), cache
   category/district lists, responsive WebP srcset, lazy images, defer scripts,
   `php artisan optimize`. Target Lighthouse 90+ mobile on Home and a place page.
4. Security: review against NFR-05 to NFR-10; security headers middleware; ensure no
   secrets in JS; file upload validation; signed URLs; login throttling.
5. Accessibility: labels, focus styles, alt text, keyboard-usable wizard and chat.
6. Legal pages: Privacy Policy, Terms, Image credits page listing all Commons media.
7. Run the full test suite, fix failures, and write docs/DEPLOY.md for moving from
   XAMPP to shared hosting or a VPS.
Report a checklist of what passed.
```

**Check:** Lighthouse mobile 90+ on Home, all tests green, sitemap lists every published place.
