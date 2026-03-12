"""
local_store.py — SQLite offline buffer for RFID taps

Every tap is written here first, before any HTTP call.
The sync thread drains unsynced rows to the Laravel API.
"""

import sqlite3
import threading
import json
from pathlib import Path

from config import LOCAL_DB_PATH

_lock = threading.Lock()
_db_path = Path(__file__).parent / LOCAL_DB_PATH


def init_local_db() -> None:
    """Create the buffer.db file and tap_queue table if they don't exist."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.execute("""
            CREATE TABLE IF NOT EXISTS tap_queue (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                card_uid   TEXT    NOT NULL,
                tapped_at  TEXT    NOT NULL,
                synced     INTEGER NOT NULL DEFAULT 0,
                synced_at  TEXT,
                response   TEXT
            )
        """)
        con.commit()
        con.close()
    print(f"[LOCAL DB] Buffer ready at {_db_path}")


def enqueue_tap(card_uid: str, tapped_at: str) -> int:
    """
    Insert a pending tap row. Returns the new row id.
    Called synchronously on every card scan — must be fast.
    """
    with _lock:
        con = sqlite3.connect(_db_path)
        cur = con.execute(
            "INSERT INTO tap_queue (card_uid, tapped_at) VALUES (?, ?)",
            (card_uid, tapped_at),
        )
        local_id = cur.lastrowid
        con.commit()
        con.close()
    return local_id


def get_unsynced(limit: int = 50) -> list[dict]:
    """Return up to `limit` unsynced rows, oldest first."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.row_factory = sqlite3.Row
        rows = con.execute(
            "SELECT id, card_uid, tapped_at FROM tap_queue WHERE synced = 0 ORDER BY id LIMIT ?",
            (limit,),
        ).fetchall()
        con.close()
    return [dict(r) for r in rows]


def mark_synced(local_id: int, response: dict) -> None:
    """Mark a row as successfully synced and store the server response."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.execute(
            """
            UPDATE tap_queue
            SET synced = 1, synced_at = datetime('now'), response = ?
            WHERE id = ?
            """,
            (json.dumps(response), local_id),
        )
        con.commit()
        con.close()


def vacuum_old_synced(days: int = 7) -> None:
    """Delete synced rows older than `days` days. Safe to call periodically."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.execute(
            "DELETE FROM tap_queue WHERE synced = 1 AND synced_at < datetime('now', ?)",
            (f"-{days} days",),
        )
        con.commit()
        con.close()
