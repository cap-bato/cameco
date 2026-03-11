import time
import logging
from fastapi import Request, Response
from starlette.middleware.base import BaseHTTPMiddleware
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import AsyncSessionLocal
from app.services.auth_service import AuthenticationService
from app.auth.dependencies import get_api_key_from_request, get_client_ip, get_user_agent
from typing import Callable

logger = logging.getLogger(__name__)


class AuthenticationMiddleware(BaseHTTPMiddleware):
    """
    Middleware for authentication logging and monitoring.
    """
    
    def __init__(self, app, skip_paths: list[str] = None):
        super().__init__(app)
        self.skip_paths = skip_paths or [
            "/docs",
            "/redoc", 
            "/openapi.json",
            "/favicon.ico",
            "/",
            "/test"
        ]
    
    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Process request through authentication middleware."""
        start_time = time.time()
        
        # Skip middleware for certain paths
        if any(request.url.path.startswith(path) for path in self.skip_paths):
            return await call_next(request)
        
        # Get request details
        client_ip = get_client_ip(request)
        user_agent = get_user_agent(request)
        
        # Process request
        response = await call_next(request)
        
        # Calculate response time
        response_time_ms = int((time.time() - start_time) * 1000)
        
        # Log API usage if authenticated
        await self._log_request(
            request, response, client_ip, user_agent, response_time_ms
        )
        
        return response
    
    async def _log_request(
        self, 
        request: Request, 
        response: Response,
        client_ip: str, 
        user_agent: str, 
        response_time_ms: int
    ) -> None:
        """Log authenticated API request."""
        try:
            # Check if request was authenticated
            api_key = await get_api_key_from_request(request)
            
            if api_key:
                async with AsyncSessionLocal() as db:
                    auth_service = AuthenticationService(db)
                    
                    # Get API key details for logging
                    key_hash = auth_service._hash_api_key(api_key)
                    from app.models.security import APIKey
                    from sqlalchemy.future import select
                    
                    result = await db.execute(
                        select(APIKey).where(APIKey.key_hash == key_hash)
                    )
                    db_key = result.scalar_one_or_none()
                    
                    if db_key:
                        # Log the API usage
                        await auth_service.log_api_usage(
                            api_key=db_key,
                            endpoint=str(request.url.path),
                            method=request.method,
                            ip_address=client_ip,
                            status_code=response.status_code,
                            response_time_ms=response_time_ms,
                            user_agent=user_agent
                        )
                        
                        # Log request info
                        logger.info(
                            f"API Request: {db_key.key_prefix} "
                            f"{request.method} {request.url.path} "
                            f"→ {response.status_code} "
                            f"({response_time_ms}ms) "
                            f"from {client_ip}"
                        )
            else:
                # Log unauthenticated requests to protected endpoints
                if not any(request.url.path.startswith(path) for path in ["/ws", "/api/v1/health"]):
                    logger.info(
                        f"Unauthenticated Request: {request.method} {request.url.path} "
                        f"→ {response.status_code} from {client_ip}"
                    )
        
        except Exception as e:
            logger.error(f"Error logging API request: {str(e)}")


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    """
    Middleware to add security headers to responses.
    """
    
    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Add security headers to response."""
        response = await call_next(request)
        
        # Add security headers
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains"
        response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate"
        response.headers["Pragma"] = "no-cache"
        response.headers["Expires"] = "0"
        
        # Remove server header for security
        if "server" in response.headers:
            del response.headers["server"]
        
        return response


class RateLimitMiddleware(BaseHTTPMiddleware):
    """
    Middleware for basic rate limiting based on IP address.
    """
    
    def __init__(self, app, requests_per_minute: int = 60):
        super().__init__(app)
        self.requests_per_minute = requests_per_minute
        self.request_counts = {}
    
    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Apply rate limiting based on IP address."""
        client_ip = get_client_ip(request)
        current_time = time.time()
        
        # Clean up old entries (older than 1 minute)
        self._cleanup_old_entries(current_time)
        
        # Check rate limit for this IP
        if client_ip not in self.request_counts:
            self.request_counts[client_ip] = []
        
        # Add current request
        self.request_counts[client_ip].append(current_time)
        
        # Check if rate limit exceeded
        if len(self.request_counts[client_ip]) > self.requests_per_minute:
            logger.warning(f"Rate limit exceeded for IP: {client_ip}")
            return Response(
                content='{"detail": "Rate limit exceeded"}',
                status_code=429,
                media_type="application/json"
            )
        
        return await call_next(request)
    
    def _cleanup_old_entries(self, current_time: float) -> None:
        """Remove entries older than 1 minute."""
        cutoff_time = current_time - 60  # 60 seconds ago
        
        for ip in list(self.request_counts.keys()):
            self.request_counts[ip] = [
                timestamp for timestamp in self.request_counts[ip] 
                if timestamp > cutoff_time
            ]
            
            # Remove empty entries
            if not self.request_counts[ip]:
                del self.request_counts[ip]