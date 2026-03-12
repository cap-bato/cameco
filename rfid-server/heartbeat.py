"""
heartbeat.py — Periodic device heartbeat via HTTP

POSTs to POST /api/rfid/heartbeat every 30 seconds so Laravel can update
rfid_devices.last_heartbeat and keep the device shown as 'online'.
No database credentials are used — only the API Bearer token.
"""

import threading
import requests

from config import API_URL, API_KEY, DEVICE_ID


def _post_heartbeat(status: str = 'online') -> None:
    """
    POST a heartbeat to Laravel. Non-fatal — prints a warning on failure.
    Sends status='offline' on clean shutdown so Laravel marks the device offline immediately.
    """
    try:
        requests.post(
            f"{API_URL}/rfid/heartbeat",
            json={'device_id': DEVICE_ID, 'status': status},
            headers={'Authorization': f'Bearer {API_KEY}'},
            timeout=8,
        )
    except Exception as e:
        print(f"[HEARTBEAT] Warning: {e}")


def set_device_status(status: str) -> None:
    """Send a one-shot heartbeat with an explicit status (e.g. 'offline' on shutdown)."""
    _post_heartbeat(status)


class HeartbeatThread(threading.Thread):
    def __init__(self, interval: int = 30):
        super().__init__(daemon=True)
        self.interval    = interval
        self._stop_event = threading.Event()

    def run(self) -> None:
        print('[HEARTBEAT] Heartbeat thread started')
        # Send immediately on startup so device shows online without waiting 30s
        _post_heartbeat('online')
        while not self._stop_event.wait(self.interval):
            _post_heartbeat('online')

    def stop(self) -> None:
        self._stop_event.set()
