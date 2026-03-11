from fastapi import APIRouter
from datetime import datetime

router = APIRouter()


@router.get("/health")
async def health_check():
    """Health check endpoint."""
    return {
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat(),
        "service": "RFID Server",
        "version": "1.0.0"
    }


@router.get("/metrics")
async def get_metrics():
    """Get server metrics."""
    return {
        "message": "Metrics collection - Phase 1 implementation pending",
        "timestamp": datetime.utcnow().isoformat(),
        "phase": "Phase 1 - Basic Setup Complete"
    }


@router.get("/status")
async def get_status():
    """Get detailed server status."""
    return {
        "server": "running",
        "database": "pending Phase 1 Task 1.2",
        "redis": "not implemented",
        "devices": "pending Phase 2",
        "timestamp": datetime.utcnow().isoformat(),
        "current_phase": "Phase 1 - Task 1.1 Complete"
    }
