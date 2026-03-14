from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Text, Sequence
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.sql import func
from app.database import Base


class RFIDLedger(Base):
    """
    Immutable, append-only ledger of RFID tap events.
    
    Shared with Laravel - FastAPI writes, Laravel reads.
    Features:
    - Hash-chained events for tamper resistance
    - Sequential IDs via PostgreSQL sequence  
    - JSON payload for flexibility
    - Optional Ed25519 device signatures
    """
    __tablename__ = "rfid_ledger"
    
    id = Column(BigInteger, primary_key=True, index=True)
    sequence_id = Column(BigInteger, 
                        Sequence('rfid_ledger_sequence_seq'),
                        unique=True, nullable=False, index=True,
                        comment="Auto-increment sequence for hash chain ordering")
    employee_rfid = Column(String(255), nullable=False, index=True,
                          comment="RFID card UID from mapping table")
    device_id = Column(String(255), nullable=False, index=True,
                      comment="RFID device identifier")
    scan_timestamp = Column(DateTime(timezone=True), nullable=False,
                           comment="When the RFID tap occurred")
    event_type = Column(String(50), nullable=False,
                       comment="Event type: time_in, time_out, break_start, break_end")
    raw_payload = Column(JSONB, nullable=False,
                        comment="Original raw event data as JSON")
    hash_chain = Column(String(255), nullable=False,
                       comment="SHA-256 hash of prev_hash || payload for tamper detection")
    device_signature = Column(Text,
                             comment="Optional Ed25519 device signature for verification")
    processed = Column(Boolean, default=False, index=True,
                      comment="Has Laravel processed this event?")
    processed_at = Column(DateTime(timezone=True),
                         comment="When Laravel processed this event")
    created_at = Column(DateTime(timezone=True), server_default=func.now(),
                       comment="When record was created (immutable)")

    # Note: No updated_at column - this is append-only, immutable


# Key indexes for performance and integrity
# CREATE UNIQUE INDEX idx_rfid_ledger_sequence ON rfid_ledger(sequence_id);
# CREATE INDEX idx_rfid_ledger_employee_rfid ON rfid_ledger(employee_rfid);  
# CREATE INDEX idx_rfid_ledger_device_id ON rfid_ledger(device_id);
# CREATE INDEX idx_rfid_ledger_scan_timestamp ON rfid_ledger(scan_timestamp);
# CREATE INDEX idx_rfid_ledger_processed ON rfid_ledger(processed) WHERE processed = FALSE;