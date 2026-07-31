# Selenium automation — Dispatcher & Performers

An end-to-end test suite that mirrors the app's own domain model:

- **`dispatcher.py`** — keeps a queue of booking scenarios and hands them out to
  Performer workers (optionally several in parallel), then prints a pass/fail
  summary.
- **`performer.py`** — a `Performer` drives one Chrome browser through the full
  workflow: Customer questionnaire -> itinerary -> submit booking ->
  Dispatcher assigns resources -> Manager approves -> Payment -> Confirmed.
- **`scenarios.py`** — the booking scenarios to run.
- **`config.py`** — base URL, demo credentials, timeouts (env-overridable).

## Prerequisites
1. App running (e.g. `php -S localhost:8000` from the project root, MySQL up).
2. Database imported from `database/lankaguide360.sql`.
3. Demo logins activated via `tools/generate-password-hash.php` (password
   `Lanka@123`).
4. Google Chrome installed (the driver is fetched automatically by
   `webdriver-manager`).

## Setup & run
```bash
pip install -r requirements.txt

python dispatcher.py                 # all scenarios, headless
python dispatcher.py --no-headless   # watch it click through the UI
python dispatcher.py --workers 3     # 3 performers in parallel
python dispatcher.py --base-url http://localhost:8080   # e.g. Docker
```

Screenshots for any failing step are saved to `automation/screenshots/`.

## Environment overrides (optional `.env`)
```
BASE_URL=http://localhost:8000
DEMO_PASSWORD=Lanka@123
DISPATCHER_EMAIL=dispatcher@lankaguide360.lk
MANAGER_EMAIL=manager@lankaguide360.lk
PAGE_TIMEOUT=20
```
