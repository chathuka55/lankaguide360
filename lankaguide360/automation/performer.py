"""
Performer — a Selenium worker that drives a single browser through the full
LankaGuide 360 booking workflow for one scenario:

    Customer questionnaire -> itinerary -> submit booking
      -> Dispatcher assigns resources -> Manager approves
      -> Payment -> Confirmed

Each Performer is independent (its own WebDriver), so a Dispatcher can run
several in parallel. Every step logs PASS/FAIL and screenshots on error.
"""
import time
import traceback
from datetime import date, timedelta
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC

import config

SHOT_DIR = Path(__file__).resolve().parent / "screenshots"


class Performer:
    def __init__(self, name: str, headless: bool = True):
        self.name = name
        self.headless = headless
        self.driver = None
        self.wait = None
        self.steps = []          # list of (step, ok, detail)
        self.reference = None
        self.booking_url = None

    # ---- lifecycle -------------------------------------------------------
    def start(self):
        opts = Options()
        if self.headless:
            opts.add_argument("--headless=new")
        opts.add_argument("--window-size=1366,900")
        opts.add_argument("--no-sandbox")
        opts.add_argument("--disable-dev-shm-usage")
        try:
            from webdriver_manager.chrome import ChromeDriverManager
            self.driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=opts)
        except Exception:
            # Fall back to a Chrome/chromedriver already on PATH.
            self.driver = webdriver.Chrome(options=opts)
        self.wait = WebDriverWait(self.driver, config.PAGE_TIMEOUT)

    def stop(self):
        if self.driver:
            self.driver.quit()

    # ---- helpers ---------------------------------------------------------
    def _log(self, step, ok, detail=""):
        self.steps.append((step, ok, detail))
        flag = "PASS" if ok else "FAIL"
        print(f"  [{self.name}] {flag}  {step}" + (f" — {detail}" if detail else ""))

    def _shot(self, tag):
        try:
            SHOT_DIR.mkdir(exist_ok=True)
            self.driver.save_screenshot(str(SHOT_DIR / f"{self.name}_{tag}_{int(time.time())}.png"))
        except Exception:
            pass

    def _go(self, path):
        self.driver.get(config.url(path))

    def _click(self, by, sel):
        el = self.wait.until(EC.element_to_be_clickable((by, sel)))
        el.click()
        return el

    def _login(self, email, password):
        # Ensure a clean session, then sign in.
        self._go("logout.php")
        self._go("login.php")
        self.wait.until(EC.presence_of_element_located((By.NAME, "email")))
        self.driver.find_element(By.NAME, "email").send_keys(email)
        self.driver.find_element(By.NAME, "password").send_keys(password)
        self.driver.find_element(By.CSS_SELECTOR, "button[type=submit], button.btn-lg-primary").click()
        time.sleep(config.SHORT_WAIT)

    def _row_with_reference(self):
        """Find the <tr> that contains this scenario's booking reference."""
        return self.wait.until(EC.presence_of_element_located(
            (By.XPATH, f"//tr[td[contains(., '{self.reference}')]]")))

    # ---- workflow stages -------------------------------------------------
    def stage_customer(self, scenario):
        self._go("trip-planner.php")
        self.wait.until(EC.presence_of_element_located((By.NAME, "days")))

        # Interests (checkboxes)
        for slug in scenario.get("interests", []):
            try:
                self.driver.find_element(By.CSS_SELECTOR, f"input[name='interests[]'][value='{slug}']").click()
            except Exception:
                pass
        # Budget / days / travellers / style
        Select(self.driver.find_element(By.NAME, "budget")).select_by_value(scenario.get("budget", "medium"))
        days = self.driver.find_element(By.NAME, "days")
        days.clear(); days.send_keys(str(scenario.get("days", 5)))
        trav = self.driver.find_element(By.NAME, "travelers")
        trav.clear(); trav.send_keys(str(scenario.get("travelers", 2)))

        self.driver.find_element(By.CSS_SELECTOR, "form.planner-shell button[type=submit], form.planner-shell button.btn-lg-primary").click()

        # Click through to the full package/itinerary page.
        link = self.wait.until(EC.element_to_be_clickable((By.PARTIAL_LINK_TEXT, "Full package")))
        link.click()
        self._log("customer: itinerary generated", True)

        # Submit booking from the itinerary page.
        self._click(By.PARTIAL_LINK_TEXT, "Submit booking")
        self.wait.until(EC.presence_of_element_located((By.NAME, "full_name")))

        # Fill guest / customer details.
        start = date.today() + timedelta(days=30)
        end = start + timedelta(days=scenario.get("days", 5))
        self._set("full_name", scenario["name"])
        self._set("email", scenario["email"])
        self._set("phone", scenario.get("phone", "+94770000000"))
        self._set("country", scenario.get("country", "Sri Lanka"))
        self._set("travel_date", start.strftime("%Y-%m-%d"))
        self._set("end_date", end.strftime("%Y-%m-%d"))
        self.driver.find_element(By.CSS_SELECTOR, "form.planner-shell button[type=submit], form button.btn-lg-primary").click()

        # We land on booking-status.php?ref=...&new=1
        self.wait.until(EC.url_contains("booking-status.php"))
        self.reference = self._extract_reference()
        self._log("customer: booking submitted", bool(self.reference), self.reference or "no reference")
        return bool(self.reference)

    def _set(self, name, value):
        el = self.driver.find_element(By.NAME, name)
        try:
            el.clear()
        except Exception:
            pass
        el.send_keys(value)

    def _extract_reference(self):
        # Prefer the URL query, fall back to the page heading text.
        from urllib.parse import urlparse, parse_qs
        qs = parse_qs(urlparse(self.driver.current_url).query)
        if "ref" in qs:
            return qs["ref"][0]
        try:
            el = self.driver.find_element(By.XPATH, "//*[contains(text(),'LG360-')]")
            import re
            m = re.search(r"LG360-\d+", el.text)
            return m.group(0) if m else None
        except Exception:
            return None

    def stage_dispatcher(self, scenario):
        self._login(config.STAFF["dispatcher"], config.DEMO_PASSWORD)
        self._go("dispatcher/queue.php")
        row = self._row_with_reference()
        # Pick up (if still submitted) else open.
        try:
            row.find_element(By.XPATH, ".//button[contains(., 'Pick up')]").click()
        except Exception:
            row.find_element(By.XPATH, ".//a[contains(., 'Open')]").click()
        self.wait.until(EC.presence_of_element_located((By.NAME, "guide_id")))
        # Defaults are pre-selected from the proposed package; forward to manager.
        self.driver.find_element(By.XPATH, "//button[@name='action' and @value='send']").click()
        self.wait.until(EC.url_contains("dispatcher/queue.php"))
        self._log("dispatcher: assigned & forwarded", True)
        return True

    def stage_manager(self, scenario):
        self._login(config.STAFF["manager"], config.DEMO_PASSWORD)
        self._go("manager/approvals.php")
        row = self._row_with_reference()
        row.find_element(By.XPATH, ".//a[contains(., 'Review')]").click()
        self.wait.until(EC.presence_of_element_located((By.NAME, "base_price_lkr")))
        self.driver.find_element(By.XPATH, "//button[@name='action' and @value='approve']").click()
        self.wait.until(EC.url_contains("manager/approvals.php"))
        self._log("manager: approved -> awaiting payment", True)
        return True

    def stage_payment(self, scenario):
        # Log out of staff, track the booking as the customer, then pay.
        self._go("logout.php")
        self._go(f"booking-status.php?ref={self.reference}")
        pay = self.wait.until(EC.element_to_be_clickable((By.PARTIAL_LINK_TEXT, "Pay now")))
        pay.click()
        self.wait.until(EC.presence_of_element_located((By.NAME, "card_number")))
        self._set("card_number", "4242424242424242")
        self._set("expiry", "12/29")
        self._set("cvv", "123")
        self.driver.find_element(By.CSS_SELECTOR, "form.planner-shell button.btn-lg-primary").click()
        ok = self.wait.until(EC.presence_of_element_located((By.XPATH, "//*[contains(text(),'Booking confirmed')]")))
        self._log("payment: booking confirmed", bool(ok))
        return bool(ok)

    # ---- run one scenario end-to-end ------------------------------------
    def run(self, scenario) -> bool:
        print(f"[{self.name}] scenario '{scenario['name']}' starting…")
        try:
            self.start()
            if not self.stage_customer(scenario):
                self._shot("customer"); return False
            self.stage_dispatcher(scenario)
            self.stage_manager(scenario)
            ok = self.stage_payment(scenario)
            return ok
        except Exception as e:
            self._log("exception", False, str(e).splitlines()[0])
            self._shot("error")
            traceback.print_exc()
            return False
        finally:
            self.stop()
