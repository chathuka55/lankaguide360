#!/usr/bin/env python3
"""
Dispatcher — orchestrates the end-to-end Selenium suite.

It maintains a queue of booking scenarios and dispatches each one to a
Performer worker (optionally several in parallel). This mirrors the app's own
Dispatcher/Performer domain model: the dispatcher coordinates work, the
performers carry it out.

Usage:
  pip install -r requirements.txt
  python dispatcher.py                    # run all scenarios (headless)
  python dispatcher.py --no-headless      # watch the browser
  python dispatcher.py --workers 3        # 3 performers in parallel
  python dispatcher.py --base-url http://localhost:8080

Prereqs: the app must be running, the DB imported, and demo logins activated
(tools/generate-password-hash.php -> Lanka@123). Chrome must be installed.
"""
import argparse
import queue
import threading
import time

import config
from performer import Performer
from scenarios import SCENARIOS


class Dispatcher:
    def __init__(self, headless=True, workers=1):
        self.headless = headless
        self.workers = max(1, workers)
        self.q = queue.Queue()
        self.results = []
        self.lock = threading.Lock()

    def load(self, scenarios):
        for s in scenarios:
            self.q.put(s)

    def _worker(self, worker_id):
        name = f"performer-{worker_id}"
        while True:
            try:
                scenario = self.q.get_nowait()
            except queue.Empty:
                return
            performer = Performer(name, headless=self.headless)
            ok = performer.run(scenario)
            with self.lock:
                self.results.append({
                    "scenario": scenario["name"],
                    "worker": name,
                    "ok": ok,
                    "reference": performer.reference,
                    "steps": performer.steps,
                })
            self.q.task_done()

    def run(self):
        print(f"Dispatcher: {self.q.qsize()} scenario(s), {self.workers} performer(s), base={config.BASE_URL}\n")
        start = time.time()
        threads = [threading.Thread(target=self._worker, args=(i + 1,)) for i in range(self.workers)]
        for t in threads:
            t.start()
        for t in threads:
            t.join()
        self._summary(time.time() - start)
        return all(r["ok"] for r in self.results) if self.results else False

    def _summary(self, elapsed):
        print("\n" + "=" * 60)
        print("DISPATCHER SUMMARY")
        print("=" * 60)
        passed = sum(1 for r in self.results if r["ok"])
        for r in self.results:
            flag = "PASS" if r["ok"] else "FAIL"
            print(f"  [{flag}] {r['scenario']:<28} ref={r['reference'] or '-'}  ({r['worker']})")
        print("-" * 60)
        print(f"  {passed}/{len(self.results)} scenarios passed in {elapsed:.1f}s")
        print("=" * 60)


def main():
    ap = argparse.ArgumentParser(description="LankaGuide 360 Selenium dispatcher.")
    ap.add_argument("--no-headless", dest="headless", action="store_false")
    ap.add_argument("--headless", dest="headless", action="store_true")
    ap.set_defaults(headless=True)
    ap.add_argument("--workers", type=int, default=1)
    ap.add_argument("--base-url", default=None)
    args = ap.parse_args()

    if args.base_url:
        config.BASE_URL = args.base_url.rstrip("/")

    dispatcher = Dispatcher(headless=args.headless, workers=args.workers)
    dispatcher.load(SCENARIOS)
    ok = dispatcher.run()
    raise SystemExit(0 if ok else 1)


if __name__ == "__main__":
    main()
