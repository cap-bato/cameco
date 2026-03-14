from fastapi import APIRouter, WebSocket, WebSocketDisconnect, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import get_db
from app.websocket.connection_manager import websocket_manager
from app.services.event_processor import EventProcessorService
from typing import Optional
import logging
import json

router = APIRouter(prefix="/ws", tags=["WebSocket"])
logger = logging.getLogger(__name__)


@router.websocket("/events")
async def websocket_events_stream(
    websocket: WebSocket,
    client_id: Optional[str] = Query(None, description="Optional client identifier")
):
    """
    WebSocket endpoint for real-time RFID event streaming.
    
    Provides live updates of RFID events as they are processed.
    
    Message Types:
    - rfid_event: New RFID event processed
    - connection_established: Connection confirmation
    - message_history: Recent events for new connections
    - ping: Health check messages
    
    Example Usage:
    ws://127.0.0.1:8001/ws/events?client_id=dashboard-1
    """
    await websocket_manager.connect(websocket, "events", client_id)
    
    try:
        while True:
            # Listen for client messages (pongs, subscriptions, etc.)
            try:
                message = await websocket.receive_text()
                data = json.loads(message)
                
                # Handle client pong responses
                if data.get("type") == "pong":
                    logger.debug(f"Received pong from client {client_id}")
                
                # Handle subscription filter changes
                elif data.get("type") == "subscribe_filter":
                    # Future implementation: per-client filtering
                    logger.info(f"Client {client_id} updated filters: {data.get('filters')}")
                
                # Handle client-specific requests
                elif data.get("type") == "get_latest":
                    # Send latest events to this specific client
                    logger.info(f"Client {client_id} requested latest events")
                
            except json.JSONDecodeError:
                logger.warning(f"Invalid JSON from client {client_id}")
            except Exception as e:
                logger.debug(f"Non-critical error receiving from {client_id}: {e}")
                
    except WebSocketDisconnect:
        await websocket_manager.disconnect(websocket)
        logger.info(f"Client {client_id} disconnected from events stream")


@router.websocket("/devices")
async def websocket_device_status(
    websocket: WebSocket,
    client_id: Optional[str] = Query(None, description="Optional client identifier")
):
    """
    WebSocket endpoint for real-time device status monitoring.
    
    Provides live updates of RFID device status, heartbeats, and health.
    
    Message Types:
    - device_status: Device online/offline status changes
    - device_heartbeat: Device heartbeat notifications
    - device_alert: Device-related alerts (offline, errors)
    - connection_established: Connection confirmation
    - message_history: Recent device updates
    """
    await websocket_manager.connect(websocket, "devices", client_id)
    
    try:
        while True:
            try:
                message = await websocket.receive_text()
                data = json.loads(message)
                
                if data.get("type") == "pong":
                    logger.debug(f"Received pong from device client {client_id}")
                elif data.get("type") == "request_device_list":
                    # Send current device list to client
                    logger.info(f"Client {client_id} requested device list")
                
            except json.JSONDecodeError:
                logger.warning(f"Invalid JSON from device client {client_id}")
            except Exception as e:
                logger.debug(f"Non-critical error from device client {client_id}: {e}")
                
    except WebSocketDisconnect:
        await websocket_manager.disconnect(websocket)
        logger.info(f"Device client {client_id} disconnected")


@router.websocket("/alerts")
async def websocket_alerts(
    websocket: WebSocket,
    client_id: Optional[str] = Query(None, description="Optional client identifier")
):
    """
    WebSocket endpoint for real-time alerts and notifications.
    
    Provides critical alerts, warnings, and system notifications.
    
    Message Types:
    - alert: System alerts (high/critical priority)
    - warning: System warnings (medium priority)
    - info: Informational notifications (low priority)
    - connection_established: Connection confirmation
    """
    await websocket_manager.connect(websocket, "alerts", client_id)
    
    try:
        while True:
            try:
                message = await websocket.receive_text()
                data = json.loads(message)
                
                if data.get("type") == "pong":
                    logger.debug(f"Received pong from alert client {client_id}")
                elif data.get("type") == "ack_alert":
                    # Acknowledge alert receipt
                    alert_id = data.get("alert_id")
                    logger.info(f"Client {client_id} acknowledged alert {alert_id}")
                
            except json.JSONDecodeError:
                logger.warning(f"Invalid JSON from alert client {client_id}")
            except Exception as e:
                logger.debug(f"Non-critical error from alert client {client_id}: {e}")
                
    except WebSocketDisconnect:
        await websocket_manager.disconnect(websocket)
        logger.info(f"Alert client {client_id} disconnected")


@router.websocket("/dashboard")
async def websocket_dashboard(
    websocket: WebSocket,
    client_id: Optional[str] = Query(None, description="Optional client identifier")
):
    """
    WebSocket endpoint for general dashboard real-time updates.
    
    Aggregates events, device status, stats, and alerts for dashboard displays.
    
    Message Types:
    - rfid_event: RFID events (forwarded from events channel)
    - device_status: Device status updates (forwarded from devices channel)
    - stats_update: Real-time statistics updates
    - alert: High/critical alerts (forwarded from alerts channel)
    - connection_established: Connection confirmation
    - message_history: Recent mixed updates
    
    This is the recommended endpoint for web dashboards and monitoring UIs.
    """
    await websocket_manager.connect(websocket, "dashboard", client_id)
    
    try:
        while True:
            try:
                message = await websocket.receive_text()
                data = json.loads(message)
                
                if data.get("type") == "pong":
                    logger.debug(f"Received pong from dashboard client {client_id}")
                elif data.get("type") == "request_stats":
                    # Send current statistics to client
                    # This would be handled by a background task calling broadcast_stats_update
                    logger.info(f"Dashboard client {client_id} requested stats update")
                elif data.get("type") == "subscribe_events":
                    # Future: per-client event filtering
                    filters = data.get("filters", {})
                    logger.info(f"Dashboard client {client_id} updated event filters: {filters}")
                
            except json.JSONDecodeError:
                logger.warning(f"Invalid JSON from dashboard client {client_id}")
            except Exception as e:
                logger.debug(f"Non-critical error from dashboard client {client_id}: {e}")
                
    except WebSocketDisconnect:
        await websocket_manager.disconnect(websocket)
        logger.info(f"Dashboard client {client_id} disconnected")


# Utility endpoint for connection statistics
@router.get("/stats")
async def get_websocket_stats():
    """
    Get statistics about current WebSocket connections.
    
    Returns information about active connections, channels, and client details.
    Useful for monitoring and debugging WebSocket connectivity.
    """
    stats = websocket_manager.get_connection_stats()
    return {
        "status": "success",
        "websocket_stats": stats,
        "endpoints": [
            "/ws/events - Real-time RFID event stream",
            "/ws/devices - Device status monitoring",
            "/ws/alerts - Alert notifications",
            "/ws/dashboard - Combined dashboard updates"
        ]
    }