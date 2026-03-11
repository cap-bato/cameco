from fastapi import APIRouter, Depends, HTTPException, status, Query, Request
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import desc, and_, or_
from app.database import get_db
from app.auth import RequireAdminAccess, authenticate_api_key, get_client_ip, get_user_agent, get_auth_service
from app.services.auth_service import AuthenticationService
from app.services.signature_service import signature_service, add_device_public_key, get_signature_verification_status
from app.models.security import APIKey, SecurityEvent
from app.schemas.security import (
    APIKeyCreate, APIKeyCreatedResponse, APIKeyResponse, APIKeyListResponse, APIKeyUpdate,
    SecurityEventResponse, APIKeyUsageStats, SecurityDashboard
)
from typing import List, Optional
from datetime import datetime, timezone
import logging

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/api/v1/security", tags=["Security"])


@router.post("/api-keys", response_model=APIKeyCreatedResponse, status_code=status.HTTP_201_CREATED)
async def create_api_key(
    api_key_data: APIKeyCreate,
    request: Request,
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess),
    auth_service: AuthenticationService = Depends(get_auth_service)
):
    """
    Create a new API key. Requires admin access.
    
    The generated API key will only be shown once in the response.
    """
    try:
        result = await auth_service.create_api_key(api_key_data)
        
        logger.info(f"Admin {admin_key.key_prefix} created API key: {result.key_info.name}")
        return result
        
    except Exception as e:
        logger.error(f"Failed to create API key: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to create API key"
        )


@router.get("/api-keys", response_model=List[APIKeyListResponse])
async def list_api_keys(
    active_only: bool = Query(True, description="Return only active keys"),
    limit: int = Query(50, ge=1, le=100, description="Maximum number of results"),
    offset: int = Query(0, ge=0, description="Offset for pagination"),
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    List API keys. Requires admin access.
    """
    try:
        query = select(APIKey)
        
        if active_only:
            query = query.where(APIKey.is_active == True)
        
        query = query.order_by(desc(APIKey.created_at)).limit(limit).offset(offset)
        result = await db.execute(query)
        api_keys = result.scalars().all()
        
        return [APIKeyListResponse.model_validate(key) for key in api_keys]
        
    except Exception as e:
        logger.error(f"Failed to list API keys: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to retrieve API keys"
        )


@router.get("/api-keys/{key_id}", response_model=APIKeyResponse)
async def get_api_key(
    key_id: int,
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get specific API key details. Requires admin access.
    """
    result = await db.execute(select(APIKey).where(APIKey.id == key_id))
    api_key = result.scalar_one_or_none()
    
    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="API key not found"
        )
    
    return APIKeyResponse.model_validate(api_key)


@router.patch("/api-keys/{key_id}", response_model=APIKeyResponse)
async def update_api_key(
    key_id: int,
    update_data: APIKeyUpdate,
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Update an API key. Requires admin access.
    """
    result = await db.execute(select(APIKey).where(APIKey.id == key_id))
    api_key = result.scalar_one_or_none()
    
    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="API key not found"
        )
    
    # Update fields
    for field, value in update_data.model_dump(exclude_unset=True).items():
        setattr(api_key, field, value)
    
    api_key.updated_at = datetime.now(timezone.utc)
    
    await db.commit()
    await db.refresh(api_key)
    
    logger.info(f"Admin {admin_key.key_prefix} updated API key: {api_key.key_prefix}")
    return APIKeyResponse.model_validate(api_key)


@router.delete("/api-keys/{key_id}", status_code=status.HTTP_200_OK)
async def delete_api_key(
    key_id: int,
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Deactivate an API key (soft delete). Requires admin access.
    """
    result = await db.execute(select(APIKey).where(APIKey.id == key_id))
    api_key = result.scalar_one_or_none()
    
    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="API key not found"
        )
    
    api_key.is_active = False
    api_key.updated_at = datetime.now(timezone.utc)
    
    await db.commit()
    
    logger.info(f"Admin {admin_key.key_prefix} deactivated API key: {api_key.key_prefix}")
    return {"status": "success", "message": "API key deactivated"}


@router.get("/events", response_model=List[SecurityEventResponse])
async def list_security_events(
    event_type: Optional[str] = Query(None, description="Filter by event type"),
    severity: Optional[str] = Query(None, description="Filter by severity"),
    unresolved_only: bool = Query(False, description="Return only unresolved events"),
    hours: int = Query(24, ge=1, le=168, description="Events from last X hours"),
    limit: int = Query(100, ge=1, le=500, description="Maximum number of results"),
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    List security events. Requires admin access.
    """
    try:
        # Build query
        query = select(SecurityEvent)
        conditions = []
        
        # Time filter
        since = datetime.now(timezone.utc) - timezone.timedelta(hours=hours)
        conditions.append(SecurityEvent.timestamp >= since)
        
        if event_type:
            conditions.append(SecurityEvent.event_type == event_type)
        
        if severity:
            conditions.append(SecurityEvent.severity == severity)
        
        if unresolved_only:
            conditions.append(SecurityEvent.is_resolved == False)
        
        if conditions:
            query = query.where(and_(*conditions))
        
        query = query.order_by(desc(SecurityEvent.timestamp)).limit(limit)
        
        result = await db.execute(query)
        events = result.scalars().all()
        
        return [SecurityEventResponse.model_validate(event) for event in events]
        
    except Exception as e:
        logger.error(f"Failed to list security events: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to retrieve security events"
        )


@router.patch("/events/{event_id}/resolve", status_code=status.HTTP_200_OK)
async def resolve_security_event(
    event_id: int,
    resolution_notes: Optional[str] = None,
    db: AsyncSession = Depends(get_db),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Mark a security event as resolved. Requires admin access.
    """
    result = await db.execute(select(SecurityEvent).where(SecurityEvent.id == event_id))
    event = result.scalar_one_or_none()
    
    if not event:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Security event not found"
        )
    
    event.is_resolved = True
    event.resolved_at = datetime.now(timezone.utc)
    event.resolution_notes = resolution_notes
    
    await db.commit()
    
    logger.info(f"Admin {admin_key.key_prefix} resolved security event: {event_id}")
    return {"status": "success", "message": "Security event resolved"}


@router.get("/stats", response_model=APIKeyUsageStats)
async def get_security_stats(
    auth_service: AuthenticationService = Depends(get_auth_service),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get API key usage statistics. Requires admin access.
    """
    stats = await auth_service.get_api_key_stats()
    return stats


@router.get("/dashboard", response_model=SecurityDashboard)
async def get_security_dashboard(
    db: AsyncSession = Depends(get_db),
    auth_service: AuthenticationService = Depends(get_auth_service),
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get security dashboard data. Requires admin access.
    """
    # Get API key stats
    api_key_stats = await auth_service.get_api_key_stats()
    
    # Get recent security events
    recent_events_result = await db.execute(
        select(SecurityEvent)
        .order_by(desc(SecurityEvent.timestamp))
        .limit(20)
    )
    recent_events = [
        SecurityEventResponse.model_validate(event) 
        for event in recent_events_result.scalars().all()
    ]
    
    # Count blocked IPs (placeholder - would need implementation based on requirements)
    blocked_ips_count = 0
    
    # Count rate limited requests today (placeholder)
    rate_limited_requests_today = 0
    
    return SecurityDashboard(
        api_key_stats=api_key_stats,
        recent_security_events=recent_events,
        blocked_ips_count=blocked_ips_count,
        rate_limited_requests_today=rate_limited_requests_today
    )


@router.post("/device-keys/{device_id}", status_code=status.HTTP_201_CREATED)
async def register_device_public_key(
    device_id: str,
    public_key_b64: str,
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Register a public key for device signature verification. Requires admin access.
    """
    try:
        add_device_public_key(device_id, public_key_b64)
        
        logger.info(f"Admin {admin_key.key_prefix} registered public key for device: {device_id}")
        return {
            "status": "success", 
            "message": f"Public key registered for device: {device_id}"
        }
        
    except Exception as e:
        logger.error(f"Failed to register device public key: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid public key: {str(e)}"
        )


@router.delete("/device-keys/{device_id}", status_code=status.HTTP_200_OK)
async def remove_device_public_key(
    device_id: str,
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Remove a device's public key. Requires admin access.
    """
    success = signature_service.remove_public_key(device_id)
    
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Public key not found for device"
        )
    
    logger.info(f"Admin {admin_key.key_prefix} removed public key for device: {device_id}")
    return {"status": "success", "message": f"Public key removed for device: {device_id}"}


@router.get("/signature-status")
async def get_signature_status(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get signature verification status. Requires admin access.
    """
    return get_signature_verification_status()


@router.get("/my-key", response_model=APIKeyResponse)
async def get_my_api_key_info(
    current_key: APIKey = Depends(authenticate_api_key)
):
    """
    Get information about the current API key.
    """
    return APIKeyResponse.model_validate(current_key)