"""
sync.py — Background sync thread

Drains tap_queue rows to POST /api/rfid/tap on the Laravel server.
Runs every SYNC_INTERVAL seconds. Fully offline-safe — if the server
is unreachable, rows stay in SQLite and are retried automatically.
"""

import threading
import requests

from config import API_URL, API_KEY, DEVICE_ID, SYNC_INTERVAL
from local_store import get_unsynced, mark_synced

# Wired by main.py after TapDisplay is created
_display = None

# Set by reader.py immediately after a tap is enqueued — wakes the sync thread
# so it drains right away instead of waiting for the next SYNC_INTERVAL tick.
_wake_event = threading.Event()


def set_display(display) -> None:
    global _display
    _display = display


def wake() -> None:
    """Signal the sync thread to drain immediately (called from reader.py)."""
    _wake_event.set()


def _post_tap(row: dict) -> dict | None:
    """
    POST a single tap row to the Laravel API.
    Returns the parsed JSON response on HTTP 200.
    Returns None on network/timeout error (will retry).
    Raises ValueError on 4xx (permanent failure — do not retry).
    """
    try:
        resp = requests.post(
            f"{API_URL}/rfid/tap",
            json={
                'card_uid':  row['card_uid'],
                'device_id': DEVICE_ID,
                'tapped_at': row['tapped_at'],
                'local_id':  row['id'],
            },
            headers={'Authorization': f'Bearer {API_KEY}'},
            timeout=8,
        )
        if resp.status_code == 200:
            return resp.json()
        if 400 <= resp.status_code < 500:
            # Permanent error — mark synced with error so it's not retried
            raise ValueError(f"HTTP {resp.status_code}: {resp.text[:200]}")
        # 5xx — temporary, return None to retry next cycle
        return None
    except requests.exceptions.RequestException:
        return None   # network error — retry next cycle


class SyncThread(threading.Thread):
    def __init__(self) -> None:
        super().__init__(daemon=True)
        self._stop_event = threading.Event()

    def run(self) -> None:
        print('[SYNC] Sync thread started')
        while not self._stop_event.is_set():
            # Wake immediately when reader signals a tap, or after SYNC_INTERVAL
            # for retries / periodic catch-up.
            _wake_event.wait(timeout=SYNC_INTERVAL)
            _wake_event.clear()
            if self._stop_event.is_set():
                break
            self._drain()

    def stop(self) -> None:
        self._stop_event.set()

    def _drain(self) -> None:
        rows = get_unsynced(limit=50)
        for row in rows:
            try:
                response = _post_tap(row)
            except ValueError as e:
                # Permanent 4xx — mark synced to stop retrying
                mark_synced(row['id'], {'error': str(e), 'permanent': True})
                print(f"[SYNC] Permanent error for local_id={row['id']}: {e}")
                continue

            if response is None:
                # Temporary failure — stop draining this cycle, retry later
                break

            mark_synced(row['id'], response)

            # Push the server's response to the display
            if _display is not None:
                status = response.get('status', 'unknown')
                if status == 'ok':
                    _display.show_tap({
                        'status':           'ok',
                        'card_uid':         row['card_uid'],
                        'employee_number':  response.get('employee_number'),
                        'first_name':       response.get('employee_name', '').split(' ')[0] if response.get('employee_name') else None,
                        'last_name':        ' '.join(response.get('employee_name', '').split(' ')[1:]) or None,
                        'predicted_action': response.get('predicted_action', 'TAP RECORDED'),
                        'timestamp':        row['tapped_at'],
                    })
                elif status == 'duplicate':
                    print(f"[SYNC]  Duplicate tap ignored for {row['card_uid']}")
                    _display.show_duplicate(row['card_uid'])
                elif status == 'unknown':
                    _display.show_tap({'status': 'unknown', 'card_uid': row['card_uid']})
