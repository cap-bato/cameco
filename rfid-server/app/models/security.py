from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Text, Index
from sqlalchemy.sql import func
from app.database import Base
from datetime import datetime


class APIKey(Base):
    """API Keys for device authentication."""
    __tablename__ = "api_keys"
    
    id = Column(BigInteger, primary_key=True, index=True)
    name = Column(String(255), nullable=False, comment="Human-readable name for the API key")
    key_hash = Column(String(255), unique=True, nullable=False, index=True, comment="Hashed API key")
    key_prefix = Column(String(32), nullable=False, index=True, comment="Key prefix for identification")
    device_id = Column(String(255), nullable=True, index=True, comment="Associated device ID (optional)")
    
    # Permissions
    is_active = Column(Boolean, default=True, index=True)
    can_submit_events = Column(Boolean, default=True, comment="Can submit RFID events")
    can_manage_devices = Column(Boolean, default=False, comment="Can register/manage devices")
    can_manage_mappings = Column(Boolean, default=False, comment="Can manage card mappings")
    can_access_admin = Column(Boolean, default=False, comment="Can access admin endpoints")
    
    # Rate limiting
    max_requests_per_minute = Column(BigInteger, default=100, comment="Rate limit per minute")
    
    # IP restrictions
    allowed_ips = Column(Text, nullable=True, comment="Comma-separated list of allowed IPs/CIDR blocks")
    
    # Usage tracking
    last_used_at = Column(DateTime(timezone=True), nullable=True, index=True)
    request_count_total = Column(BigInteger, default=0)
    request_count_today = Column(BigInteger, default=0)
    last_reset_date = Column(DateTime(timezone=True), nullable=True)
    
    # Metadata
    notes = Column(Text, nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    expires_at = Column(DateTime(timezone=True), nullable=True, comment="Optional expiration date")
    
    # Indexes for performance
    __table_args__ = (
        Index('idx_api_keys_active', 'is_active'),
        Index('idx_api_keys_device', 'device_id'),
        Index('idx_api_keys_last_used', 'last_used_at'),
        Index('idx_api_keys_expires', 'expires_at'),
    )


class APIKeyUsageLog(Base):
    """Log of API key usage for auditing."""
    __tablename__ = "api_key_usage_log"
    
    id = Column(BigInteger, primary_key=True, index=True)
    api_key_id = Column(BigInteger, nullable=False, index=True)
    key_prefix = Column(String(32), nullable=False)
    
    # Request details
    endpoint = Column(String(255), nullable=False)
    method = Column(String(10), nullable=False)
    ip_address = Column(String(45), nullable=False, index=True)  # IPv6 compatible
    user_agent = Column(Text, nullable=True)
    
    # Response details
    status_code = Column(BigInteger, nullable=False)
    response_time_ms = Column(BigInteger, nullable=True)
    
    # Metadata
    timestamp = Column(DateTime(timezone=True), server_default=func.now(), index=True)
    
    # Indexes for performance
    __table_args__ = (
        Index('idx_usage_timestamp', 'timestamp'),
        Index('idx_usage_api_key_date', 'api_key_id', 'timestamp'),
        Index('idx_usage_ip', 'ip_address'),
        Index('idx_usage_status', 'status_code'),
    )


class SecurityEvent(Base):
    """Security events for monitoring and alerting."""
    __tablename__ = "security_events"
    
    id = Column(BigInteger, primary_key=True, index=True)
    event_type = Column(String(100), nullable=False, index=True, comment="Type of security event")
    severity = Column(String(20), nullable=False, index=True, comment="low, medium, high, critical")
    
    # Event details
    description = Column(Text, nullable=False)
    ip_address = Column(String(45), nullable=False, index=True)
    user_agent = Column(Text, nullable=True)
    api_key_prefix = Column(String(32), nullable=True)
    
    # Request context
    endpoint = Column(String(255), nullable=True)
    method = Column(String(10), nullable=True)
    request_data = Column(Text, nullable=True, comment="Sanitized request data")
    
    # Resolution
    is_resolved = Column(Boolean, default=False, index=True)
    resolved_at = Column(DateTime(timezone=True), nullable=True)
    resolution_notes = Column(Text, nullable=True)
    
    # Metadata
    timestamp = Column(DateTime(timezone=True), server_default=func.now(), index=True)
    
    # Indexes for performance
    __table_args__ = (
        Index('idx_security_events_type_time', 'event_type', 'timestamp'),
        Index('idx_security_events_severity', 'severity'),
        Index('idx_security_events_unresolved', 'is_resolved', 'timestamp'),
    )