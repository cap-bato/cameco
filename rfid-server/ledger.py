import hashlib
import json
from datetime import datetime
from zoneinfo import ZoneInfo

from config import DEVICE_ID, TIMEZONE
from database import get_conn, put_conn


def _compact_json(obj: dict) -> str:
    """Compact JSON matching PHP json_encode() — sort_keys=True + no spaces."""
    return json.dumps(obj, separators=(',', ':'), sort_keys=True, ensure_ascii=False)


def _compute_hash(previous_hash: str | None, payload_json: str) -> str:
    """SHA256( (previous_hash or '') + payload_json )"""
    data = (previous_hash or '') + payload_json
    return hashlib.sha256(data.encode('utf-8')).hexdigest()


def _get_previous_hash(cur) -> tuple[str | None, int]:
    """
    Return (last hash_chain, next sequence_id) for DEVICE_ID.
    FOR UPDATE serialises concurrent writes from multiple threads.
    """
    cur.execute(
        """
        SELECT hash_chain, sequence_id
        FROM rfid_ledger
        WHERE device_id = %s
        ORDER BY sequence_id DESC
        LIMIT 1
        FOR UPDATE
        """,
        (DEVICE_ID,),
    )
    row = cur.fetchone()
    if row is None:
        return None, 1
    return row[0], row[1] + 1


def _lookup_card(cur, card_uid: str) -> dict | None:
    """Lookup card mapping with employee name; exclude soft-deleted rows."""
    cur.execute(
        """
        SELECT m.employee_id, m.is_active, e.first_name, e.last_name, e.employee_number
        FROM rfid_card_mappings m
        JOIN employees e ON e.id = m.employee_id
        WHERE m.card_uid = %s AND m.deleted_at IS NULL
        LIMIT 1
        """,
        (card_uid,),
    )
    row = cur.fetchone()
    if row is None:
        return None
    return {
        'employee_id':     row[0],
        'is_active':       row[1],
        'first_name':      row[2],
        'last_name':       row[3],
        'employee_number': row[4],
    }


def _get_last_attendance_event(cur, employee_id: int) -> str | None:
    """
    Return the event_type of the most recent attendance_events row for this
    employee today, or None if no event exists yet. Used by the display to
    predict TIME IN vs TIME OUT.
    """
    cur.execute(
        """
        SELECT event_type
        FROM attendance_events
        WHERE employee_id = %s AND event_date = CURRENT_DATE
        ORDER BY event_time DESC
        LIMIT 1
        """,
        (employee_id,),
    )
    row = cur.fetchone()
    return row[0] if row else None


def _update_card_usage(cur, card_uid: str, now: datetime) -> None:
    cur.execute(
        """
        UPDATE rfid_card_mappings
        SET last_used_at = %s, usage_count = usage_count + 1
        WHERE card_uid = %s AND deleted_at IS NULL
        """,
        (now, card_uid),
    )


def _insert_ledger_row(cur, *, sequence_id, card_uid, event_type,
                       now, raw_payload, hash_chain, prev_hash) -> None:
    cur.execute(
        """
        INSERT INTO rfid_ledger
            (sequence_id, employee_rfid, device_id, scan_timestamp, event_type,
             raw_payload, hash_chain, hash_previous, processed, created_at)
        VALUES (%s, %s, %s, %s, %s, %s::jsonb, %s, %s, false, %s)
        """,
        (
            sequence_id,
            card_uid,
            DEVICE_ID,
            now,
            event_type,
            json.dumps(raw_payload),
            hash_chain,
            prev_hash,
            now,
        ),
    )


def record_tap(card_uid: str) -> dict:
    """
    Called by reader.py on every card scan.
    Writes one rfid_ledger row; updates rfid_card_mappings on valid tap.
    Returns a feedback dict consumed by display.py.
    """
    tz  = ZoneInfo(TIMEZONE)
    now = datetime.now(tz).replace(tzinfo=None)

    conn = get_conn()
    try:
        with conn:                   # auto COMMIT or ROLLBACK
            with conn.cursor() as cur:
                mapping = _lookup_card(cur, card_uid)

                if mapping is None or not mapping['is_active']:
                    event_type       = 'unknown_card'
                    employee_id      = None
                    first_name       = None
                    last_name        = None
                    employee_number  = None
                    predicted_action = None
                else:
                    event_type      = 'tap'
                    employee_id     = mapping['employee_id']
                    first_name      = mapping['first_name']
                    last_name       = mapping['last_name']
                    employee_number = mapping['employee_number']

                    last_event       = _get_last_attendance_event(cur, employee_id)
                    predicted_action = 'TIME OUT' if last_event == 'time_in' else 'TIME IN'

                raw_payload = {
                    'card_uid':    card_uid,
                    'device_id':   DEVICE_ID,
                    'employee_id': employee_id,
                    'event_type':  event_type,
                    'timestamp':   now.isoformat(),
                }
                payload_json = _compact_json(raw_payload)
                prev_hash, next_seq = _get_previous_hash(cur)
                hash_chain = _compute_hash(prev_hash, payload_json)

                _insert_ledger_row(
                    cur,
                    sequence_id = next_seq,
                    card_uid    = card_uid,
                    event_type  = event_type,
                    now         = now,
                    raw_payload = raw_payload,
                    hash_chain  = hash_chain,
                    prev_hash   = prev_hash,
                )

                if event_type == 'tap':
                    _update_card_usage(cur, card_uid, now)

        put_conn(conn)

        return {
            'status':          'ok' if event_type == 'tap' else 'unknown',
            'card_uid':        card_uid,
            'event_type':      event_type,
            'sequence_id':     next_seq,
            'employee_id':     employee_id,
            'employee_number': employee_number,
            'first_name':      first_name,
            'last_name':       last_name,
            'predicted_action': predicted_action,
            'timestamp':       now.isoformat(),
        }

    except Exception:
        conn.close()    # discard broken connection — do not return to pool
        raise
