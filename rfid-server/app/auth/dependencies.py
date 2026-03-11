from fastapi import Request, HTTPException, status, Depends
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import get_db
from app.services.auth_service import AuthenticationService
from app.models.security import APIKey
from app.schemas.security import SecurityEventType, SecurityEventSeverity
from typing import Optional, Callable
import logging

logger = logging.getLogger(__name__)

# Bearer token scheme
bearer_scheme = HTTPBearer(auto_error=False)


class AuthenticationError(Exception):
    """Custom authentication error."""
    def __init__(self, message: str, status_code: int = status.HTTP_401_UNAUTHORIZED):
        self.message = message
        self.status_code = status_code


async def get_auth_service(db: AsyncSession = Depends(get_db)) -> AuthenticationService:
    """Dependency to get authentication service."""
    return AuthenticationService(db)


async def get_api_key_from_request(request: Request) -> Optional[str]:
    """Extract API key from request headers."""
    # Try Authorization header first (Bearer token)
    auth_header = request.headers.get("Authorization")
    if auth_header and auth_header.startswith("Bearer "):
        return auth_header.split(" ", 1)[1]
    
    # Try X-API-Key header
    api_key_header = request.headers.get("X-API-Key")
    if api_key_header:
        return api_key_header
    
    # Try query parameter (less secure, for testing)
    return request.query_params.get("api_key")


async def authenticate_api_key(
    request: Request,
    auth_service: AuthenticationService = Depends(get_auth_service)
) -> APIKey:
    """
    Authenticate API key from request.
    Returns APIKey object if valid, raises HTTPException if not.
    """
    # Extract API key
    api_key = await get_api_key_from_request(request)
    
    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="API key required. Provide via Authorization header, X-API-Key header, or api_key query parameter.",
            headers={"WWW-Authenticate": "Bearer"}
        )
    
    # Get client IP
    client_ip = get_client_ip(request)
    
    # Verify API key
    db_key, error_message = await auth_service.verify_api_key(
        api_key, client_ip, str(request.url.path)
    )
    
    if not db_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=error_message,
            headers={"WWW-Authenticate": "Bearer"}
        )
    
    return db_key


def require_permission(permission: str) -> Callable:
    """
    Dependency factory that requires specific permission.
    Usage: @app.post("/admin", dependencies=[Depends(require_permission("access_admin"))])
    """
    async def permission_checker(
        api_key: APIKey = Depends(authenticate_api_key),
        auth_service: AuthenticationService = Depends(get_auth_service)
    ) -> APIKey:
        if not await auth_service.check_permission(api_key, permission):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Permission '{permission}' required"
            )
        return api_key
    
    return permission_checker


# Pre-defined permission dependencies
RequireEventSubmission = require_permission("submit_events")
RequireDeviceManagement = require_permission("manage_devices")
RequireMappingManagement = require_permission("manage_mappings")
RequireAdminAccess = require_permission("access_admin")


async def optional_authentication(
    request: Request,
    auth_service: AuthenticationService = Depends(get_auth_service)
) -> Optional[APIKey]:
    """
    Optional authentication for endpoints that can work with or without auth.
    Returns APIKey if provided and valid, None if no API key provided.
    Raises exception only if API key is provided but invalid.
    """
    api_key = await get_api_key_from_request(request)
    
    if not api_key:
        return None
    
    client_ip = get_client_ip(request)
    db_key, error_message = await auth_service.verify_api_key(
        api_key, client_ip, str(request.url.path)
    )
    
    if not db_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=error_message,
            headers={"WWW-Authenticate": "Bearer"}
        )
    
    return db_key


def get_client_ip(request: Request) -> str:
    """
    Get the real client IP address, considering proxy headers.
    """
    # Check for forwarded headers (common in load balancers/proxies)
    forwarded_for = request.headers.get("X-Forwarded-For")
    if forwarded_for:
        # X-Forwarded-For can contain multiple IPs, take the first one
        return forwarded_for.split(",")[0].strip()
    
    # Check for real IP header
    real_ip = request.headers.get("X-Real-IP")
    if real_ip:
        return real_ip
    
    # Fallback to direct connection IP
    return request.client.host if request.client else "unknown"


async def get_user_agent(request: Request) -> str:
    """Get User-Agent header from request."""
    return request.headers.get("User-Agent", "unknown")


# Middleware dependencies for common use cases
async def authenticated_device(api_key: APIKey = Depends(RequireEventSubmission)) -> APIKey:
    """Dependency for endpoints that require device-level access."""
    return api_key


async def authenticated_admin(api_key: APIKey = Depends(RequireAdminAccess)) -> APIKey:
    """Dependency for admin endpoints."""
    return api_key


async def authenticated_manager(
    api_key: APIKey = Depends(require_permission("manage_devices"))
) -> APIKey:
    """Dependency for device/mapping management endpoints."""
    return api_key