import hashlib
import secrets
import ipaddress
from datetime import datetime, timedelta, timezone
from typing import Optional, Dict, Any, List, Tuple
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import update, and_, or_, func
from app.models.security import APIKey, APIKeyUsageLog, SecurityEvent
from app.schemas.security import (
    APIKeyCreate, APIKeyCreatedResponse, APIKeyResponse, 
    SecurityEventCreate, SecurityEventType, SecurityEventSeverity,
    APIKeyUsageStats
)
import logging

logger = logging.getLogger(__name__)


class AuthenticationService:
    """Service for API key authentication and security management."""
    
    def __init__(self, db: AsyncSession):
        self.db = db
    
    async def create_api_key(self, api_key_data: APIKeyCreate) -> APIKeyCreatedResponse:
        """
        Create a new API key with secure random generation.
        Returns the plain-text key (only time it's shown).
        """
        # Generate secure random API key
        random_bytes = secrets.token_bytes(32)  # 256 bits of entropy
        api_key = f"ak_{random_bytes.hex()}"
        
        # Create key prefix for identification (first 8 chars after prefix)
        key_prefix = api_key[:8]
        
        # Hash the API key for storage
        key_hash = self._hash_api_key(api_key)
        
        # Create database record
        db_api_key = APIKey(
            name=api_key_data.name,
            key_hash=key_hash,
            key_prefix=key_prefix,
            device_id=api_key_data.device_id,
            can_submit_events=api_key_data.permissions.can_submit_events if api_key_data.permissions else True,
            can_manage_devices=api_key_data.permissions.can_manage_devices if api_key_data.permissions else False,
            can_manage_mappings=api_key_data.permissions.can_manage_mappings if api_key_data.permissions else False,
            can_access_admin=api_key_data.permissions.can_access_admin if api_key_data.permissions else False,
            max_requests_per_minute=api_key_data.max_requests_per_minute,
            allowed_ips=api_key_data.allowed_ips,
            expires_at=api_key_data.expires_at,
            notes=api_key_data.notes,
            is_active=True
        )
        
        self.db.add(db_api_key)
        await self.db.commit()
        await self.db.refresh(db_api_key)
        
        logger.info(f"Created new API key: {key_prefix} for {api_key_data.name}")
        
        return APIKeyCreatedResponse(
            api_key=api_key,
            key_info=APIKeyResponse.model_validate(db_api_key)
        )
    
    async def verify_api_key(self, api_key: str, ip_address: str, endpoint: str) -> Tuple[Optional[APIKey], Optional[str]]:
        """
        Verify an API key and check permissions.
        Returns (APIKey object, error_message).
        If verification fails, error_message explains why.
        """
        if not api_key or not api_key.startswith("ak_"):
            return None, "Invalid API key format"
        
        # Hash the provided key
        key_hash = self._hash_api_key(api_key)
        key_prefix = api_key[:8]
        
        # Look up the API key
        result = await self.db.execute(
            select(APIKey).where(APIKey.key_hash == key_hash)
        )
        db_key = result.scalar_one_or_none()
        
        if not db_key:
            await self._log_security_event(
                SecurityEventType.INVALID_API_KEY,
                SecurityEventSeverity.MEDIUM,
                f"Invalid API key attempted: {key_prefix}",
                ip_address,
                endpoint=endpoint,
                api_key_prefix=key_prefix
            )
            return None, "Invalid API key"
        
        # Check if key is active
        if not db_key.is_active:
            await self._log_security_event(
                SecurityEventType.INVALID_API_KEY,
                SecurityEventSeverity.MEDIUM,
                f"Inactive API key used: {key_prefix}",
                ip_address,
                endpoint=endpoint,
                api_key_prefix=key_prefix
            )
            return None, "API key is inactive"
        
        # Check expiration
        if db_key.expires_at and datetime.now(timezone.utc) > db_key.expires_at:
            await self._log_security_event(
                SecurityEventType.EXPIRED_API_KEY,
                SecurityEventSeverity.LOW,
                f"Expired API key used: {key_prefix}",
                ip_address,
                endpoint=endpoint,
                api_key_prefix=key_prefix
            )
            return None, "API key has expired"
        
        # Check IP restrictions
        if db_key.allowed_ips and not self._is_ip_allowed(ip_address, db_key.allowed_ips):
            await self._log_security_event(
                SecurityEventType.IP_BLOCKED,
                SecurityEventSeverity.HIGH,
                f"IP not allowed for API key {key_prefix}: {ip_address}",
                ip_address,
                endpoint=endpoint,
                api_key_prefix=key_prefix
            )
            return None, "IP address not allowed for this API key"
        
        # Check rate limiting
        if await self._is_rate_limited(db_key, ip_address):
            await self._log_security_event(
                SecurityEventType.RATE_LIMIT_EXCEEDED,
                SecurityEventSeverity.MEDIUM,
                f"Rate limit exceeded for API key {key_prefix}",
                ip_address,
                endpoint=endpoint,
                api_key_prefix=key_prefix
            )
            return None, "Rate limit exceeded"
        
        # Update usage statistics
        await self._update_usage_stats(db_key)
        
        return db_key, None
    
    async def check_permission(self, api_key: APIKey, permission: str) -> bool:
        """Check if API key has specific permission."""
        permission_map = {
            "submit_events": api_key.can_submit_events,
            "manage_devices": api_key.can_manage_devices,
            "manage_mappings": api_key.can_manage_mappings,
            "access_admin": api_key.can_access_admin
        }
        
        return permission_map.get(permission, False)
    
    async def log_api_usage(self, api_key: APIKey, endpoint: str, method: str, 
                           ip_address: str, status_code: int, response_time_ms: int = None,
                           user_agent: str = None) -> None:
        """Log API usage for auditing."""
        usage_log = APIKeyUsageLog(
            api_key_id=api_key.id,
            key_prefix=api_key.key_prefix,
            endpoint=endpoint,
            method=method,
            ip_address=ip_address,
            user_agent=user_agent,
            status_code=status_code,
            response_time_ms=response_time_ms
        )
        
        self.db.add(usage_log)
        await self.db.commit()
    
    async def get_api_key_stats(self) -> APIKeyUsageStats:
        """Get API key usage statistics."""
        # Count total and active keys
        total_result = await self.db.execute(select(func.count(APIKey.id)))
        total_keys = total_result.scalar()
        
        active_result = await self.db.execute(
            select(func.count(APIKey.id)).where(APIKey.is_active == True)
        )
        active_keys = active_result.scalar()
        
        # Count expired keys
        expired_result = await self.db.execute(
            select(func.count(APIKey.id)).where(
                and_(
                    APIKey.expires_at.isnot(None),
                    APIKey.expires_at < datetime.now(timezone.utc)
                )
            )
        )
        expired_keys = expired_result.scalar()
        
        # Get today's request count
        today_start = datetime.now(timezone.utc).replace(hour=0, minute=0, second=0, microsecond=0)
        today_result = await self.db.execute(
            select(func.sum(APIKey.request_count_today)).where(
                APIKey.last_reset_date >= today_start
            )
        )
        total_requests_today = today_result.scalar() or 0
        
        # Get all-time request count
        all_time_result = await self.db.execute(
            select(func.sum(APIKey.request_count_total))
        )
        total_requests_all_time = all_time_result.scalar() or 0
        
        # Get top keys by usage
        top_keys_result = await self.db.execute(
            select(APIKey.name, APIKey.key_prefix, APIKey.request_count_total)
            .where(APIKey.is_active == True)
            .order_by(APIKey.request_count_total.desc())
            .limit(5)
        )
        top_keys_by_usage = [
            {"name": row[0], "key_prefix": row[1], "request_count": row[2]}
            for row in top_keys_result.fetchall()
        ]
        
        return APIKeyUsageStats(
            total_keys=total_keys,
            active_keys=active_keys,
            expired_keys=expired_keys,
            total_requests_today=total_requests_today,
            total_requests_all_time=total_requests_all_time,
            top_keys_by_usage=top_keys_by_usage
        )
    
    def _hash_api_key(self, api_key: str) -> str:
        """Hash API key using SHA-256."""
        return hashlib.sha256(api_key.encode()).hexdigest()
    
    def _is_ip_allowed(self, ip_address: str, allowed_ips: str) -> bool:
        """Check if IP address is in allowed list."""
        try:
            ip = ipaddress.ip_address(ip_address)
            for allowed in allowed_ips.split(','):
                allowed = allowed.strip()
                if not allowed:
                    continue
                
                # Check if it's a CIDR block or single IP
                if '/' in allowed:
                    network = ipaddress.ip_network(allowed, strict=False)
                    if ip in network:
                        return True
                else:
                    if ip == ipaddress.ip_address(allowed):
                        return True
            return False
        except (ipaddress.AddressValueError, ValueError) as e:
            logger.warning(f"Invalid IP address format: {ip_address} or allowed_ips: {allowed_ips}")
            return False
    
    async def _is_rate_limited(self, api_key: APIKey, ip_address: str) -> bool:
        """Check if API key has exceeded rate limit."""
        # Get request count in last minute
        one_minute_ago = datetime.now(timezone.utc) - timedelta(minutes=1)
        
        result = await self.db.execute(
            select(func.count(APIKeyUsageLog.id))
            .where(
                and_(
                    APIKeyUsageLog.api_key_id == api_key.id,
                    APIKeyUsageLog.timestamp > one_minute_ago
                )
            )
        )
        request_count = result.scalar()
        
        return request_count >= api_key.max_requests_per_minute
    
    async def _update_usage_stats(self, api_key: APIKey) -> None:
        """Update API key usage statistics."""
        now = datetime.now(timezone.utc)
        today_start = now.replace(hour=0, minute=0, second=0, microsecond=0)
        
        # Reset daily count if needed
        if not api_key.last_reset_date or api_key.last_reset_date < today_start:
            api_key.request_count_today = 1
            api_key.last_reset_date = today_start
        else:
            api_key.request_count_today += 1
        
        api_key.request_count_total += 1
        api_key.last_used_at = now
        
        await self.db.commit()
    
    async def _log_security_event(self, event_type: SecurityEventType, severity: SecurityEventSeverity,
                                description: str, ip_address: str, user_agent: str = None,
                                api_key_prefix: str = None, endpoint: str = None,
                                method: str = None, request_data: str = None) -> None:
        """Log a security event."""
        security_event = SecurityEvent(
            event_type=event_type.value,
            severity=severity.value,
            description=description,
            ip_address=ip_address,
            user_agent=user_agent,
            api_key_prefix=api_key_prefix,
            endpoint=endpoint,
            method=method,
            request_data=request_data
        )
        
        self.db.add(security_event)
        await self.db.commit()
        
        logger.warning(f"Security event logged: {event_type.value} - {description}")