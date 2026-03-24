"""
photo_cache.py — Download and cache employee profile photos.

Photos are fetched once per session and stored as PIL.Image objects.
All downloads happen in a background thread; the callback is called
on completion (schedule via root.after from display.py).

If the employee has no photo or the download fails, a generated grey
silhouette placeholder is returned instead of None.
"""
import io
import threading

import requests
from PIL import Image, ImageDraw

# employee_number → PIL.Image (square-resized to PHOTO_SIZE)
# A placeholder image is returned when there is no URL or the fetch fails.
_cache: dict[str, Image.Image] = {}
_lock  = threading.Lock()

PHOTO_SIZE = (180, 180)

# Generated once at import time — grey silhouette avatar
_PLACEHOLDER: Image.Image | None = None


def _make_placeholder() -> Image.Image:
    """Generate a grey silhouette avatar image (180×180 RGBA)."""
    w, h = PHOTO_SIZE
    img  = Image.new('RGBA', PHOTO_SIZE, (176, 176, 176, 255))   # grey bg
    draw = ImageDraw.Draw(img)
    # Head
    hcx, hcy, hr = w // 2, int(h * 0.32), int(h * 0.16)
    draw.ellipse((hcx - hr, hcy - hr, hcx + hr, hcy + hr), fill=(255, 255, 255, 255))
    # Body (bust)
    bcx, bcy  = w // 2, int(h * 0.82)
    brx, bry  = int(w * 0.46), int(h * 0.42)
    draw.ellipse((bcx - brx, bcy - bry, bcx + brx, bcy + bry), fill=(255, 255, 255, 255))
    return img


def get_photo(employee_number: str, url: str | None, callback) -> None:
    """
    Non-blocking.  If the photo is already cached, calls callback(image)
    immediately (synchronously).  Otherwise starts a background thread
    that fetches the image and calls callback(image) when done.

    callback always receives a PIL.Image (RGBA).  A generated grey
    silhouette is returned when there is no URL or the download fails.
    """
    global _PLACEHOLDER
    if _PLACEHOLDER is None:
        _PLACEHOLDER = _make_placeholder()

    with _lock:
        if employee_number in _cache:
            callback(_cache[employee_number])
            return

    def _fetch():
        img = None
        if url:
            try:
                resp = requests.get(url, timeout=5)
                resp.raise_for_status()
                raw = Image.open(io.BytesIO(resp.content)).convert('RGBA')
                img = raw.resize(PHOTO_SIZE, Image.LANCZOS)
            except Exception:
                pass   # network / decode error — fall back to placeholder
        result = img if img is not None else _PLACEHOLDER
        with _lock:
            _cache[employee_number] = result
        callback(result)

    threading.Thread(target=_fetch, daemon=True).start()
