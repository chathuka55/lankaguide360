#!/usr/bin/env python3
"""
LankaGuide 360 — destination / hotel photo fetcher.

Fills empty `image_url` columns using LEGAL image sources only:
  1. Unsplash API      (if UNSPLASH_ACCESS_KEY is set in .env)
  2. Pexels API        (if PEXELS_API_KEY is set in .env)
  3. Wikimedia Commons (keyless fallback — always available)

It NEVER scrapes Google Images (against their ToS and fragile).

Usage:
  pip install -r requirements.txt
  python fetch_photos.py                 # fetch destinations missing a photo
  python fetch_photos.py --what hotels   # fetch hotel photos instead
  python fetch_photos.py --dry-run       # show what would happen, download nothing
  python fetch_photos.py --limit 5       # cap number processed

DB connection uses env vars (DB_HOST/DB_NAME/DB_USER/DB_PASS), falling back to
standard XAMPP defaults (localhost / lankaguide360 / root / empty password).
Images are saved to ../assets/img/<slug>.jpg and the DB row is updated to
'assets/img/<slug>.jpg'.
"""

import argparse
import os
import re
import sys
import time
from pathlib import Path
from urllib.parse import quote

try:
    import requests
except ImportError:
    sys.exit("Missing dependency. Run: pip install -r requirements.txt")

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # .env is optional

try:
    import mysql.connector
except ImportError:
    sys.exit("Missing mysql-connector-python. Run: pip install -r requirements.txt")

ROOT = Path(__file__).resolve().parent.parent
IMG_DIR = ROOT / "assets" / "img"
USER_AGENT = "LankaGuide360-PhotoFetcher/1.0 (educational project)"

DB_CFG = {
    "host": os.getenv("DB_HOST", "localhost"),
    "database": os.getenv("DB_NAME", "lankaguide360"),
    "user": os.getenv("DB_USER", "root"),
    "password": os.getenv("DB_PASS", ""),
}

UNSPLASH_KEY = os.getenv("UNSPLASH_ACCESS_KEY")
PEXELS_KEY = os.getenv("PEXELS_API_KEY")


def slugify(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")


# ---------- Image source providers (return a direct image URL or None) ----------

def from_unsplash(query: str):
    if not UNSPLASH_KEY:
        return None
    try:
        r = requests.get(
            "https://api.unsplash.com/search/photos",
            params={"query": query, "per_page": 1, "orientation": "landscape"},
            headers={"Authorization": f"Client-ID {UNSPLASH_KEY}", "User-Agent": USER_AGENT},
            timeout=20,
        )
        r.raise_for_status()
        results = r.json().get("results", [])
        if results:
            return results[0]["urls"]["regular"]
    except Exception as e:
        print(f"    unsplash error: {e}")
    return None


def from_pexels(query: str):
    if not PEXELS_KEY:
        return None
    try:
        r = requests.get(
            "https://api.pexels.com/v1/search",
            params={"query": query, "per_page": 1, "orientation": "landscape"},
            headers={"Authorization": PEXELS_KEY, "User-Agent": USER_AGENT},
            timeout=20,
        )
        r.raise_for_status()
        photos = r.json().get("photos", [])
        if photos:
            return photos[0]["src"]["large"]
    except Exception as e:
        print(f"    pexels error: {e}")
    return None


def from_wikimedia(query: str):
    """Keyless fallback: search Wikimedia Commons for a freely-licensed image."""
    try:
        api = "https://commons.wikimedia.org/w/api.php"
        r = requests.get(api, params={
            "action": "query", "format": "json", "generator": "search",
            "gsrsearch": f"{query} Sri Lanka", "gsrnamespace": 6, "gsrlimit": 1,
            "prop": "imageinfo", "iiprop": "url", "iiurlwidth": 1024,
        }, headers={"User-Agent": USER_AGENT}, timeout=20)
        r.raise_for_status()
        pages = r.json().get("query", {}).get("pages", {})
        for _, page in pages.items():
            info = page.get("imageinfo", [])
            if info:
                return info[0].get("thumburl") or info[0].get("url")
    except Exception as e:
        print(f"    wikimedia error: {e}")
    return None


def find_image(query: str):
    for provider in (from_unsplash, from_pexels, from_wikimedia):
        url = provider(query)
        if url:
            return url, provider.__name__.replace("from_", "")
    return None, None


def download(url: str, dest: Path) -> bool:
    try:
        r = requests.get(url, headers={"User-Agent": USER_AGENT}, timeout=30, stream=True)
        r.raise_for_status()
        dest.parent.mkdir(parents=True, exist_ok=True)
        with open(dest, "wb") as f:
            for chunk in r.iter_content(8192):
                f.write(chunk)
        return True
    except Exception as e:
        print(f"    download error: {e}")
        return False


TABLES = {
    "destinations": {"name_col": "name", "slug_col": "slug", "query_suffix": "Sri Lanka landmark"},
    "hotels":       {"name_col": "name", "slug_col": None,    "query_suffix": "hotel Sri Lanka"},
    "vehicles":     {"name_col": "name", "slug_col": None,    "query_suffix": "vehicle"},
}


def main():
    ap = argparse.ArgumentParser(description="Fetch photos for LankaGuide 360.")
    ap.add_argument("--what", choices=list(TABLES.keys()), default="destinations")
    ap.add_argument("--limit", type=int, default=50)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--overwrite", action="store_true", help="Refetch even if image_url is set")
    args = ap.parse_args()

    cfg = TABLES[args.what]
    active_provider = "Unsplash" if UNSPLASH_KEY else ("Pexels" if PEXELS_KEY else "Wikimedia Commons (keyless)")
    print(f"LankaGuide 360 photo fetcher · table={args.what} · primary source={active_provider}")
    if args.dry_run:
        print("(dry-run: nothing will be downloaded or written)")

    try:
        conn = mysql.connector.connect(**DB_CFG)
    except Exception as e:
        sys.exit(f"DB connection failed ({DB_CFG['user']}@{DB_CFG['host']}/{DB_CFG['database']}): {e}")

    cur = conn.cursor(dictionary=True)
    where = "" if args.overwrite else "WHERE image_url IS NULL OR image_url = ''"
    cur.execute(f"SELECT id, {cfg['name_col']} AS name FROM {args.what} {where} LIMIT {int(args.limit)}")
    rows = cur.fetchall()
    print(f"{len(rows)} row(s) need a photo.\n")

    updated = 0
    for row in rows:
        name = row["name"]
        slug = slugify(name)
        query = f"{name} {cfg['query_suffix']}"
        print(f"- {name}")
        url, src = find_image(query)
        if not url:
            print("    no image found; skipping")
            continue
        print(f"    found via {src}: {url[:70]}...")
        rel = f"assets/img/{slug}.jpg"
        if args.dry_run:
            updated += 1
            continue
        if download(url, IMG_DIR / f"{slug}.jpg"):
            cur2 = conn.cursor()
            cur2.execute(f"UPDATE {args.what} SET image_url = %s WHERE id = %s", (rel, row["id"]))
            conn.commit()
            cur2.close()
            updated += 1
            print(f"    saved -> {rel}")
        time.sleep(0.5)  # be polite to the API

    cur.close()
    conn.close()
    print(f"\nDone. {updated} row(s) {'would be ' if args.dry_run else ''}updated.")


if __name__ == "__main__":
    main()
