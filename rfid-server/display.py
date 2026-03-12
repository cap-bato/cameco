"""
display.py — Gate PC tap-feedback UI

A fullscreen Tkinter window that shows tap results to the employee.
Runs on the main thread; reader.py fires show_tap() via thread-safe
root.after() so Tkinter is never touched from a background thread.

States:
  idle    — dark background, clock, "PLEASE TAP YOUR CARD"
  syncing — indigo, "TAP RECEIVED" / "Verifying..." (shown immediately on scan)
  success — green, employee name + ID + predicted action (TIME IN / TIME OUT)
  unknown — red, "UNKNOWN CARD"
  error   — amber, brief error message
"""

import tkinter as tk
from datetime import datetime
from zoneinfo import ZoneInfo

from config import TIMEZONE, DISPLAY_FULLSCREEN, DISPLAY_CLEAR_AFTER

# ── Colour palette ──────────────────────────────────────────────────────────
BG_IDLE      = '#0f172a'   # slate-900
BG_SYNCING   = '#1e1b4b'   # indigo-950
BG_SUCCESS   = '#14532d'   # green-900  (TIME IN)
BG_TIMEOUT   = '#1e3a5f'   # blue-950   (TIME OUT)
BG_DUPLICATE = '#312e2e'   # stone-900  (duplicate tap)
BG_UNKNOWN   = '#7f1d1d'   # red-900
BG_ERROR     = '#78350f'   # amber-900

FG_IDLE    = '#94a3b8'   # slate-400
FG_WHITE   = '#f8fafc'   # slate-50
FG_INDIGO  = '#a5b4fc'   # indigo-300
FG_GREEN   = '#86efac'   # green-300
FG_BLUE    = '#93c5fd'   # blue-300
FG_STONE   = '#d6d3d1'   # stone-300
FG_RED     = '#fca5a5'   # red-300
FG_AMBER   = '#fcd34d'   # amber-300


class TapDisplay:
    def __init__(self) -> None:
        self.root = tk.Tk()
        self.root.title('Cameco RFID Timekeeping')
        self.root.configure(bg=BG_IDLE)
        self.root.resizable(False, False)

        if DISPLAY_FULLSCREEN:
            self.root.attributes('-fullscreen', True)
        else:
            self.root.geometry('800x480')

        # Escape key exits fullscreen in dev mode
        self.root.bind('<Escape>', lambda _: self.root.destroy())

        self._clear_job: str | None = None   # pending after() cancel token
        self._build_widgets()

    # ── Widget construction ──────────────────────────────────────────────────

    def _build_widgets(self) -> None:
        # Top-right: clock
        self.clock_lbl = tk.Label(
            self.root, text='', font=('Segoe UI', 22, 'bold'),
            bg=BG_IDLE, fg=FG_IDLE, anchor='e',
        )
        self.clock_lbl.place(relx=1.0, rely=0.0, anchor='ne', x=-24, y=16)

        # Top-left: device ID
        self.device_lbl = tk.Label(
            self.root, text='', font=('Segoe UI', 16),
            bg=BG_IDLE, fg=FG_IDLE, anchor='w',
        )
        self.device_lbl.place(relx=0.0, rely=0.0, anchor='nw', x=24, y=16)

        # Centre: main action label (TIME IN / TIME OUT / PLEASE TAP)
        self.action_lbl = tk.Label(
            self.root, text='PLEASE TAP YOUR CARD',
            font=('Segoe UI', 52, 'bold'),
            bg=BG_IDLE, fg=FG_IDLE,
            wraplength=700, justify='center',
        )
        self.action_lbl.place(relx=0.5, rely=0.38, anchor='center')

        # Below action: employee name
        self.name_lbl = tk.Label(
            self.root, text='',
            font=('Segoe UI', 34),
            bg=BG_IDLE, fg=FG_WHITE,
            wraplength=700, justify='center',
        )
        self.name_lbl.place(relx=0.5, rely=0.58, anchor='center')

        # Below name: employee number / card UID
        self.id_lbl = tk.Label(
            self.root, text='',
            font=('Segoe UI', 20),
            bg=BG_IDLE, fg=FG_IDLE,
        )
        self.id_lbl.place(relx=0.5, rely=0.71, anchor='center')

        # Bottom-centre: timestamp of the tap
        self.time_lbl = tk.Label(
            self.root, text='',
            font=('Segoe UI', 16),
            bg=BG_IDLE, fg=FG_IDLE,
        )
        self.time_lbl.place(relx=0.5, rely=0.88, anchor='center')

    # ── Public API (called from reader.py via root.after) ────────────────────

    def show_tap(self, result: dict) -> None:
        """Thread-safe: schedule the UI update on the main Tkinter thread."""
        self.root.after(0, lambda: self._render(result))

    def show_idle(self) -> None:
        """Thread-safe: immediately return display to idle state."""
        self.root.after(0, self._render_idle)

    def show_duplicate(self, card_uid: str) -> None:
        """Thread-safe: show duplicate-tap feedback then auto-clear."""
        self.root.after(0, lambda: self._render_duplicate(card_uid))

    # ── Internal render ──────────────────────────────────────────────────────

    def _render(self, result: dict) -> None:
        # Cancel any pending auto-clear
        if self._clear_job is not None:
            self.root.after_cancel(self._clear_job)
            self._clear_job = None

        status = result.get('status', 'error')
        if status == 'ok':
            self._render_success(result)
            self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)
        elif status == 'unknown':
            self._render_unknown(result)
            self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)
        elif status == 'syncing':
            self._render_syncing(result)
            # No auto-clear — sync.py will overwrite with ok / unknown / error
        else:
            self._render_error(result.get('error', 'Unknown error'))
            self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)

    def _render_syncing(self, result: dict) -> None:
        self._set_bg(BG_SYNCING)
        self.action_lbl.config(text='TAP RECEIVED',  fg=FG_INDIGO, bg=BG_SYNCING, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='Verifying...',   fg=FG_WHITE,  bg=BG_SYNCING)
        self.id_lbl.config(   text='',               fg=FG_INDIGO, bg=BG_SYNCING)
        self.time_lbl.config( text=result.get('timestamp', '')[:19].replace('T', '  '),
                              fg=FG_INDIGO, bg=BG_SYNCING)
        self.clock_lbl.config(bg=BG_SYNCING)
        self.device_lbl.config(bg=BG_SYNCING)

    def _render_success(self, result: dict) -> None:
        action = result.get('predicted_action', 'TAP RECORDED')
        first  = result.get('first_name') or ''
        last   = result.get('last_name')  or ''
        name   = f"{first} {last}".strip() or result.get('card_uid', '')
        emp_no = result.get('employee_number') or f"Card: {result['card_uid']}"
        ts     = result.get('timestamp', '')[:19].replace('T', '  ')

        is_timeout = action == 'TIME OUT'
        bg  = BG_TIMEOUT if is_timeout else BG_SUCCESS
        fg  = FG_BLUE    if is_timeout else FG_GREEN

        self._set_bg(bg)
        self.action_lbl.config(text=action,  fg=fg,      bg=bg, font=('Segoe UI', 64, 'bold'))
        self.name_lbl.config( text=name,     fg=FG_WHITE, bg=bg)
        self.id_lbl.config(   text=emp_no,   fg=fg,       bg=bg)
        self.time_lbl.config( text=ts,       fg=fg,       bg=bg)
        self.clock_lbl.config(bg=bg)
        self.device_lbl.config(bg=bg)

    def _render_duplicate(self, card_uid: str) -> None:
        if self._clear_job is not None:
            self.root.after_cancel(self._clear_job)
        self._set_bg(BG_DUPLICATE)
        self.action_lbl.config(text='ALREADY RECORDED', fg=FG_STONE,  bg=BG_DUPLICATE, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='Tap registered within the last 15 seconds.', fg=FG_WHITE, bg=BG_DUPLICATE)
        self.id_lbl.config(   text=f'Card: {card_uid}',                           fg=FG_STONE, bg=BG_DUPLICATE)
        self.time_lbl.config( text='',                                             fg=FG_STONE, bg=BG_DUPLICATE)
        self.clock_lbl.config(bg=BG_DUPLICATE)
        self.device_lbl.config(bg=BG_DUPLICATE)
        self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)

    def _render_unknown(self, result: dict) -> None:
        card_uid = result.get('card_uid', '')

        self._set_bg(BG_UNKNOWN)
        self.action_lbl.config(text='UNKNOWN CARD',  fg=FG_RED,   bg=BG_UNKNOWN, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='Card not registered or deactivated.', fg=FG_WHITE, bg=BG_UNKNOWN)
        self.id_lbl.config(   text=f'UID: {card_uid}',                    fg=FG_RED,   bg=BG_UNKNOWN)
        self.time_lbl.config( text='Please contact HR to register your card.', fg=FG_RED, bg=BG_UNKNOWN)
        self.clock_lbl.config(bg=BG_UNKNOWN)
        self.device_lbl.config(bg=BG_UNKNOWN)

    def _render_error(self, message: str) -> None:
        self._set_bg(BG_ERROR)
        self.action_lbl.config(text='SYSTEM ERROR', fg=FG_AMBER, bg=BG_ERROR, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text=message[:80],   fg=FG_WHITE,  bg=BG_ERROR)
        self.id_lbl.config(   text='',             fg=FG_AMBER,  bg=BG_ERROR)
        self.time_lbl.config( text='Tap was NOT recorded. Please try again.', fg=FG_AMBER, bg=BG_ERROR)
        self.clock_lbl.config(bg=BG_ERROR)
        self.device_lbl.config(bg=BG_ERROR)

    def _render_idle(self) -> None:
        self._clear_job = None
        self._set_bg(BG_IDLE)
        self.action_lbl.config(text='PLEASE TAP YOUR CARD', fg=FG_IDLE, bg=BG_IDLE, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='',  fg=FG_WHITE, bg=BG_IDLE)
        self.id_lbl.config(   text='',  fg=FG_IDLE,  bg=BG_IDLE)
        self.time_lbl.config( text='',  fg=FG_IDLE,  bg=BG_IDLE)
        self.clock_lbl.config(bg=BG_IDLE)
        self.device_lbl.config(bg=BG_IDLE)

    def _set_bg(self, color: str) -> None:
        self.root.configure(bg=color)

    # ── Clock + device label ─────────────────────────────────────────────────

    def _tick(self) -> None:
        tz  = ZoneInfo(TIMEZONE)
        now = datetime.now(tz)
        self.clock_lbl.config(text=now.strftime('%I:%M:%S %p'))
        self.root.after(1000, self._tick)

    def set_device_label(self, device_id: str) -> None:
        self.device_lbl.config(text=f'Device: {device_id}')

    # ── Entry point ──────────────────────────────────────────────────────────

    def start(self) -> None:
        """Blocks — call after all background threads are started."""
        self._tick()
        self._render_idle()
        self.root.mainloop()
