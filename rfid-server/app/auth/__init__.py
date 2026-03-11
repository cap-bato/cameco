# Authentication module
from .dependencies import (
    authenticate_api_key,
    optional_authentication,
    require_permission,
    RequireEventSubmission,
    RequireDeviceManagement,
    RequireMappingManagement,
    RequireAdminAccess,
    authenticated_device,
    authenticated_admin,
    authenticated_manager,
    get_client_ip,
    get_user_agent,
    get_auth_service
)
from .middleware import AuthenticationMiddleware

__all__ = [
    "authenticate_api_key",
    "optional_authentication", 
    "require_permission",
    "RequireEventSubmission",
    "RequireDeviceManagement",
    "RequireMappingManagement", 
    "RequireAdminAccess",
    "authenticated_device",
    "authenticated_admin",
    "authenticated_manager",
    "get_client_ip",
    "get_user_agent",
    "get_auth_service",
    "AuthenticationMiddleware"
]