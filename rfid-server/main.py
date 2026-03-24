"""
main.py — Cameco RFID Reader entry point

Startup sequence:
  1. Init local SQLite buffer (creates buffer.db if missing)
  2. POST /api/rfid/heartbeat  status=online
  3. Start HeartbeatThread    (POST /api/rfid/heartbeat every 30 s)
  4. Start SyncThread         (drain SQLite → POST /api/rfid/tap every 2 s)
  5. Create TapDisplay window
  6. Wire display into reader and sync threads
  7. Start keyboard listener  (background thread)
  8. Hand control to Tkinter mainloop (blocks until window closed)

Shutdown (Ctrl+C OR nssm stop CamecoRfidServer):
  - SIGINT / SIGTERM → _shutdown() runs before mainloop exits
  - Device set 'offline' via API, threads stopped, clean exit
"""

import signal
import sys

from config import DEVICE_ID
from local_store import init_local_db
from heartbeat import HeartbeatThread, set_device_status
from sync import SyncThread, set_display as sync_set_display
from reader import start_listener, set_display as reader_set_display
from display import TapDisplay

heartbeat = HeartbeatThread(interval=30)
sync      = SyncThread()


def _shutdown(sig=None, frame=None) -> None:
    print('\n[SHUTDOWN] Setting device offline...')
    heartbeat.stop()
    sync.stop()
    set_device_status('offline')
    sys.exit(0)


def main() -> None:
    signal.signal(signal.SIGINT,  _shutdown)
    signal.signal(signal.SIGTERM, _shutdown)

    print(f'[STARTUP] RFID Reader starting — Device: {DEVICE_ID}')

    init_local_db()
    set_device_status('online')

    heartbeat.start()
    sync.start()

    # Create display window BEFORE starting the listener so show_tap() is
    # available as soon as the first card is scanned.
    display = TapDisplay()
    display.set_device_label(DEVICE_ID)
    reader_set_display(display)
    sync_set_display(display)

    start_listener()

    # Blocks until the window is closed (or _shutdown() calls sys.exit)
    display.start()

    # If the window is closed manually (e.g. Alt+F4), do a clean shutdown
    _shutdown()


if __name__ == '__main__':
    main()
