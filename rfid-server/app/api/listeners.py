from fastapi import APIRouter, HTTPException, status, Depends
from app.auth import RequireAdminAccess, authenticate_api_key
from app.models.security import APIKey
from app.listeners.tcp_listener import tcp_manager
from app.listeners.udp_listener import udp_manager
from app.config import settings
from typing import Dict, Any
import asyncio
import logging

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/api/v1/listeners", tags=["Device Listeners"])

@router.get("/status")
async def get_listeners_status(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get status of TCP and UDP listeners.
    Requires admin access.
    """
    return {
        "tcp": tcp_manager.get_stats(),
        "udp": udp_manager.get_stats(),
        "settings": {
            "tcp_enabled": settings.tcp_listener_enabled,
            "tcp_host": settings.tcp_listener_host,
            "tcp_port": settings.tcp_listener_port,
            "udp_enabled": settings.udp_listener_enabled,
            "udp_host": settings.udp_listener_host, 
            "udp_port": settings.udp_listener_port
        }
    }

@router.post("/tcp/start")
async def start_tcp_listener(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Start TCP listener for RFID devices.
    Requires admin access.
    """
    try:
        if not settings.tcp_listener_enabled:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="TCP listener is disabled in configuration"
            )
        
        await tcp_manager.start_listener()
        
        logger.info(f"Admin {admin_key.key_prefix} started TCP listener")
        return {
            "status": "success",
            "message": f"TCP listener started on {settings.tcp_listener_host}:{settings.tcp_listener_port}"
        }
        
    except Exception as e:
        logger.error(f"Failed to start TCP listener: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to start TCP listener: {str(e)}"
        )

@router.post("/tcp/stop") 
async def stop_tcp_listener(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Stop TCP listener.
    Requires admin access.
    """
    try:
        await tcp_manager.stop_listener()
        
        logger.info(f"Admin {admin_key.key_prefix} stopped TCP listener")
        return {
            "status": "success",
            "message": "TCP listener stopped"
        }
        
    except Exception as e:
        logger.error(f"Failed to stop TCP listener: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to stop TCP listener: {str(e)}"
        )

@router.post("/udp/start")
async def start_udp_listener(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Start UDP listener for RFID devices.
    Requires admin access.
    """
    try:
        if not settings.udp_listener_enabled:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="UDP listener is disabled in configuration"
            )
        
        await udp_manager.start_listener()
        
        logger.info(f"Admin {admin_key.key_prefix} started UDP listener")
        return {
            "status": "success", 
            "message": f"UDP listener started on {settings.udp_listener_host}:{settings.udp_listener_port}"
        }
        
    except Exception as e:
        logger.error(f"Failed to start UDP listener: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to start UDP listener: {str(e)}"
        )

@router.post("/udp/stop")
async def stop_udp_listener(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Stop UDP listener.
    Requires admin access.
    """
    try:
        await udp_manager.stop_listener()
        
        logger.info(f"Admin {admin_key.key_prefix} stopped UDP listener") 
        return {
            "status": "success",
            "message": "UDP listener stopped"
        }
        
    except Exception as e:
        logger.error(f"Failed to stop UDP listener: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to stop UDP listener: {str(e)}"
        )

@router.get("/tcp/stats")
async def get_tcp_stats(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get detailed TCP listener statistics.
    Requires admin access.
    """
    return tcp_manager.get_stats()

@router.get("/udp/stats")
async def get_udp_stats(
    admin_key: APIKey = Depends(RequireAdminAccess)
):
    """
    Get detailed UDP listener statistics.
    Requires admin access.
    """
    return udp_manager.get_stats()