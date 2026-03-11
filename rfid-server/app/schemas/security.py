from pydantic import BaseModel, Field, ConfigDict
from datetime import datetime
from typing import Optional, List
from enum import Enum


class APIKeyPermissions(BaseModel):
    """API Key permissions structure."""
    can_submit_events: bool = Field(True, description="Can submit RFID events")
    can_manage_devices: bool = Field(False, description="Can register/manage devices")
    can_manage_mappings: bool = Field(False, description="Can manage card mappings")
    can_access_admin: bool = Field(False, description="Can access admin endpoints")


class APIKeyCreate(BaseModel):
    """Schema for creating a new API key."""
    name: str = Field(..., min_length=1, max_length=255, description="Human-readable name for the API key")
    device_id: Optional[str] = Field(None, description="Associated device ID (optional)")
    permissions: Optional[APIKeyPermissions] = Field(default_factory=APIKeyPermissions, description="API key permissions")
    max_requests_per_minute: int = Field(100, ge=1, le=10000, description="Rate limit per minute")
    allowed_ips: Optional[str] = Field(None, description="Comma-separated list of allowed IPs/CIDR blocks")
    expires_at: Optional[datetime] = Field(None, description="Optional expiration date")
    notes: Optional[str] = Field(None, description="Optional notes")
    
    model_config = ConfigDict(
        json_schema_extra={
            "example": {
                "name": "Main Gate Reader",
                "device_id": "GATE-01", 
                "permissions": {
                    "can_submit_events": True,
                    "can_manage_devices": False,
                    "can_manage_mappings": False,
                    "can_access_admin": False
                },
                "max_requests_per_minute": 100,
                "allowed_ips": "192.168.1.100,10.0.0.0/24",
                "notes": "API key for main gate RFID reader"
            }
        }
    )


class APIKeyResponse(BaseModel):
    """Schema for API key response (without sensitive data)."""
    id: int
    name: str
    key_prefix: str
    device_id: Optional[str]
    is_active: bool
    can_submit_events: bool
    can_manage_devices: bool
    can_manage_mappings: bool
    can_access_admin: bool
    max_requests_per_minute: int
    allowed_ips: Optional[str]
    last_used_at: Optional[datetime]
    request_count_total: int
    request_count_today: int
    notes: Optional[str]
    created_at: datetime
    updated_at: datetime
    expires_at: Optional[datetime]
    
    model_config = ConfigDict(from_attributes=True)


class APIKeyListResponse(BaseModel):
    """Schema for listing API keys."""
    id: int
    name: str
    key_prefix: str
    device_id: Optional[str]
    is_active: bool
    last_used_at: Optional[datetime]
    request_count_total: int
    created_at: datetime
    expires_at: Optional[datetime]
    
    model_config = ConfigDict(from_attributes=True)


class APIKeyUpdate(BaseModel):
    """Schema for updating an API key."""
    name: Optional[str] = Field(None, min_length=1, max_length=255)
    is_active: Optional[bool] = None
    can_submit_events: Optional[bool] = None
    can_manage_devices: Optional[bool] = None
    can_manage_mappings: Optional[bool] = None
    can_access_admin: Optional[bool] = None
    max_requests_per_minute: Optional[int] = Field(None, ge=1, le=10000)
    allowed_ips: Optional[str] = None
    expires_at: Optional[datetime] = None
    notes: Optional[str] = None


class APIKeyCreatedResponse(BaseModel):
    """Schema for API key creation response (includes the actual key)."""
    api_key: str = Field(..., description="The generated API key (only shown once)")
    key_info: APIKeyResponse = Field(..., description="API key information")
    
    model_config = ConfigDict(
        json_schema_extra={
            "example": {
                "api_key": "ak_1234567890abcdef1234567890abcdef12345678",
                "key_info": {
                    "id": 1,
                    "name": "Main Gate Reader",
                    "key_prefix": "ak_123456",
                    "device_id": "GATE-01",
                    "is_active": True,
                    "can_submit_events": True,
                    "can_manage_devices": False,
                    "can_manage_mappings": False,
                    "can_access_admin": False,
                    "max_requests_per_minute": 100,
                    "allowed_ips": "192.168.1.100",
                    "request_count_total": 0,
                    "notes": "API key for main gate RFID reader"
                }
            }
        }
    )


class SecurityEventSeverity(str, Enum):
    """Security event severity levels."""
    LOW = "low"
    MEDIUM = "medium" 
    HIGH = "high"
    CRITICAL = "critical"


class SecurityEventType(str, Enum):
    """Types of security events."""
    INVALID_API_KEY = "invalid_api_key"
    RATE_LIMIT_EXCEEDED = "rate_limit_exceeded"
    IP_BLOCKED = "ip_blocked"
    EXPIRED_API_KEY = "expired_api_key"
    PERMISSION_DENIED = "permission_denied"
    SIGNATURE_VERIFICATION_FAILED = "signature_verification_failed"
    SUSPICIOUS_ACTIVITY = "suspicious_activity"
    BRUTE_FORCE_ATTEMPT = "brute_force_attempt"


class SecurityEventCreate(BaseModel):
    """Schema for creating a security event."""
    event_type: SecurityEventType
    severity: SecurityEventSeverity
    description: str = Field(..., min_length=1)
    ip_address: str = Field(..., description="Source IP address")
    user_agent: Optional[str] = None
    api_key_prefix: Optional[str] = None
    endpoint: Optional[str] = None
    method: Optional[str] = None
    request_data: Optional[str] = None


class SecurityEventResponse(BaseModel):
    """Schema for security event response."""
    id: int
    event_type: str
    severity: str
    description: str
    ip_address: str
    user_agent: Optional[str]
    api_key_prefix: Optional[str]
    endpoint: Optional[str]
    method: Optional[str]
    is_resolved: bool
    resolved_at: Optional[datetime]
    resolution_notes: Optional[str]
    timestamp: datetime
    
    model_config = ConfigDict(from_attributes=True)


class APIKeyUsageStats(BaseModel):
    """Statistics for API key usage."""
    total_keys: int
    active_keys: int
    expired_keys: int
    total_requests_today: int
    total_requests_all_time: int
    top_keys_by_usage: List[dict]


class SecurityDashboard(BaseModel):
    """Security dashboard data."""
    api_key_stats: APIKeyUsageStats
    recent_security_events: List[SecurityEventResponse]
    blocked_ips_count: int
    rate_limited_requests_today: int