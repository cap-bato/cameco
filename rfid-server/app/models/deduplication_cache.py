from sqlalchemy import Column, BigInteger, String, DateTime
from sqlalchemy.sql import func
from app.database import Base


class EventDeduplicationCache(Base):
    """
    Fast in-memory duplicate detection cache (15-second window).
    
    Purpose: Prevent duplicate RFID events within a configurable time window.
    Cache Key Format: "{employee_id}:{device_id}:{event_type}"
    
    Alternative: Use Redis for deduplication cache (faster, auto-expiry with TTL).
    """
    __tablename__ = "event_deduplication_cache"
    
    id = Column(BigInteger, primary_key=True, index=True)
    cache_key = Column(String(255), unique=True, nullable=False, index=True,
                      comment="Format: '{employee_id}:{device_id}:{event_type}'")
    last_event_timestamp = Column(DateTime(timezone=True), nullable=False,
                                 comment="Timestamp of the last event for this key")
    sequence_id = Column(BigInteger, nullable=False,
                        comment="Sequence ID of the last event")
    expires_at = Column(DateTime(timezone=True), nullable=False, index=True,
                       comment="TTL: last_event_timestamp + configured window (default 15 seconds)")
    created_at = Column(DateTime(timezone=True), server_default=func.now())

    # No updated_at - we replace entries rather than update them


# Index definitions for performance
# CREATE INDEX idx_cache_key ON event_deduplication_cache(cache_key);
# CREATE INDEX idx_expires_at ON event_deduplication_cache(expires_at);

# Auto-cleanup query (run every minute):
# DELETE FROM event_deduplication_cache WHERE expires_at < NOW();