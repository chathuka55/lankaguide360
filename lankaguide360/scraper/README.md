# Photo scraper

Fills empty `image_url` columns (destinations, hotels, vehicles) using **legal**
image sources. It never scrapes Google Images.

## Sources (in priority order)
1. **Unsplash API** — set `UNSPLASH_ACCESS_KEY`
2. **Pexels API** — set `PEXELS_API_KEY`
3. **Wikimedia Commons** — keyless fallback, always works

## Setup
```bash
pip install -r requirements.txt
cp .env.example .env      # optional: add API keys
```

## Run
```bash
python fetch_photos.py                 # destinations missing a photo
python fetch_photos.py --what hotels   # hotels
python fetch_photos.py --what vehicles # vehicles
python fetch_photos.py --dry-run       # preview only
python fetch_photos.py --overwrite     # refetch everything
```
Images are saved to `../assets/img/<slug>.jpg` and the matching DB row is updated.

## Licensing note
Unsplash and Pexels images are free to use under their licenses; Wikimedia
Commons files carry their own (mostly CC) licenses — check attribution
requirements before any commercial/public use.
