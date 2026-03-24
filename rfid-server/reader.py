import threading
from datetime import datetime
from zoneinfo import ZoneInfo
from pynput import keyboard

from config import DEVICE_ID, TIMEZONE
from local_store import enqueue_tap
from sync import wake as sync_wake

_buffer: list[str] = []
_lock = threading.Lock()

# Set by main.py after display is created
_display = None


def set_display(display) -> None:
    """Register the TapDisplay instance."""
    global _display
    _display = display


def _on_press(key):
    with _lock:
        if key == keyboard.Key.enter:
            uid = ''.join(_buffer).strip()
            _buffer.clear()
            if uid:
                _fire(uid)
        else:
            try:
                char = key.char
                if char and char.isprintable():
                    _buffer.append(char)
            except AttributeError:
                pass   # special key — ignore


def _fire(card_uid: str) -> None:
    """
    Write the tap to the local SQLite buffer immediately.
    The sync thread will POST it to Laravel in the background.
    Show a 'syncing' placeholder on the display so the employee
    gets instant feedback; the display is updated again when
    the server response arrives via sync.py.
    """
    tz = ZoneInfo(TIMEZONE)
    tapped_at = datetime.now(tz).replace(tzinfo=None).isoformat()

    try:
        local_id = enqueue_tap(card_uid, tapped_at)
        print(f"[QUEUED]  {card_uid}  local_id={local_id}  {tapped_at}")

        # Wake the sync thread immediately so it POSTs without waiting
        sync_wake()

        # Show a neutral 'tap received' state immediately
        # The sync thread overwrites this with the server's response
        if _display is not None:
            _display.show_tap({
                'status':    'syncing',
                'card_uid':  card_uid,
                'timestamp': tapped_at,
            })

    except Exception as e:
        print(f"[ERROR]   {card_uid} — {e}")
        if _display is not None:
            _display.show_tap({'status': 'error', 'card_uid': card_uid, 'error': str(e)})


def start_listener() -> keyboard.Listener:
    listener = keyboard.Listener(on_press=_on_press)
    listener.daemon = True
    listener.start()
    print("[READER] Listening for RFID input (USB HID keyboard mode)...")
    return listener
