import json
import asyncio
from typing import List, Dict, Set, Optional, Any
from fastapi import WebSocket
from datetime import datetime, timezone
import logging

logger = logging.getLogger(__name__)


class WebSocketManager:
    """
    WebSocket connection manager for handling real-time updates.
    Manages multiple client connections and message broadcasting.
    """

    def __init__(self):
        # Store active connections by connection type
        self.active_connections: Dict[str, Set[WebSocket]] = {
            "events": set(),  # Real-time event stream
            "devices": set(),  # Device status updates
            "alerts": set(),   # Critical alerts and notifications
            "dashboard": set()  # General dashboard updates
        }
        
        # Connection metadata
        self.connection_metadata: Dict[WebSocket, Dict[str, Any]] = {}
        
        # Message queue for reliable delivery
        self.message_queue: Dict[str, List[Dict[str, Any]]] = {
            "events": [],
            "devices": [],
            "alerts": [],
            "dashboard": []
        }
        
        # Keep last N messages for late connections
        self.MESSAGE_HISTORY_SIZE = 50

    async def connect(self, websocket: WebSocket, connection_type: str, client_id: Optional[str] = None):
        """Accept a new WebSocket connection and add to appropriate channel."""
        await websocket.accept()
        
        if connection_type not in self.active_connections:
            connection_type = "dashboard"  # Default fallback
        
        self.active_connections[connection_type].add(websocket)
        self.connection_metadata[websocket] = {
            "type": connection_type,
            "client_id": client_id,
            "connected_at": datetime.now(timezone.utc),
            "last_ping": datetime.now(timezone.utc)
        }
        
        logger.info(f"WebSocket connected: type={connection_type}, client_id={client_id}")
        
        # Send recent message history to new connection
        await self._send_message_history(websocket, connection_type)
        
        # Send connection confirmation
        await self._send_to_connection(websocket, {
            "type": "connection_established",
            "channel": connection_type,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "message": f"Connected to {connection_type} channel"
        })

    async def disconnect(self, websocket: WebSocket):
        """Remove a WebSocket connection from all channels."""
        metadata = self.connection_metadata.get(websocket, {})
        connection_type = metadata.get("type", "unknown")
        client_id = metadata.get("client_id", "unknown")
        
        # Remove from all connection sets
        for conn_type, connections in self.active_connections.items():
            connections.discard(websocket)
        
        # Remove metadata
        self.connection_metadata.pop(websocket, None)
        
        logger.info(f"WebSocket disconnected: type={connection_type}, client_id={client_id}")

    async def broadcast_event(self, event_data: Dict[str, Any]):
        """Broadcast RFID event to all event stream subscribers."""
        message = {
            "type": "rfid_event",
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "data": event_data
        }
        
        await self._broadcast_to_channel("events", message)
        await self._broadcast_to_channel("dashboard", message)  # Also send to dashboard
        
        # Add to message history
        self._add_to_history("events", message)

    async def broadcast_device_status(self, device_data: Dict[str, Any]):
        """Broadcast device status update to device channel subscribers."""
        message = {
            "type": "device_status",
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "data": device_data
        }
        
        await self._broadcast_to_channel("devices", message)
        await self._broadcast_to_channel("dashboard", message)  # Also send to dashboard
        
        # Add to message history
        self._add_to_history("devices", message)

    async def broadcast_alert(self, alert_data: Dict[str, Any], priority: str = "medium"):
        """Broadcast alert/notification to alert channel subscribers."""
        message = {
            "type": "alert",
            "priority": priority,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "data": alert_data
        }
        
        await self._broadcast_to_channel("alerts", message)
        
        # High priority alerts also go to dashboard
        if priority in ["high", "critical"]:
            await self._broadcast_to_channel("dashboard", message)
        
        # Add to message history
        self._add_to_history("alerts", message)

    async def broadcast_stats_update(self, stats_data: Dict[str, Any]):
        """Broadcast statistics update to dashboard subscribers."""
        message = {
            "type": "stats_update",
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "data": stats_data
        }
        
        await self._broadcast_to_channel("dashboard", message)
        
        # Add to message history
        self._add_to_history("dashboard", message)

    async def send_to_client(self, client_id: str, message: Dict[str, Any]):
        """Send message to a specific client by ID."""
        target_connections = [
            ws for ws, metadata in self.connection_metadata.items()
            if metadata.get("client_id") == client_id
        ]
        
        for websocket in target_connections:
            await self._send_to_connection(websocket, message)

    async def _broadcast_to_channel(self, channel: str, message: Dict[str, Any]):
        """Broadcast message to all connections in a specific channel."""
        if channel not in self.active_connections:
            return
        
        dead_connections = set()
        
        for websocket in self.active_connections[channel].copy():
            try:
                await self._send_to_connection(websocket, message)
            except Exception as e:
                logger.error(f"Error sending to WebSocket: {e}")
                dead_connections.add(websocket)
        
        # Clean up dead connections
        for websocket in dead_connections:
            await self.disconnect(websocket)

    async def _send_to_connection(self, websocket: WebSocket, message: Dict[str, Any]):
        """Send message to a specific WebSocket connection."""
        try:
            await websocket.send_text(json.dumps(message, default=str))
        except Exception as e:
            logger.error(f"Failed to send WebSocket message: {e}")
            raise

    async def _send_message_history(self, websocket: WebSocket, connection_type: str):
        """Send recent message history to a newly connected client."""
        if connection_type in self.message_queue:
            history = self.message_queue[connection_type][-self.MESSAGE_HISTORY_SIZE:]
            
            if history:
                history_message = {
                    "type": "message_history",
                    "channel": connection_type,
                    "timestamp": datetime.now(timezone.utc).isoformat(),
                    "data": {
                        "count": len(history),
                        "messages": history
                    }
                }
                await self._send_to_connection(websocket, history_message)

    def _add_to_history(self, channel: str, message: Dict[str, Any]):
        """Add message to channel history, maintaining size limit."""
        if channel in self.message_queue:
            self.message_queue[channel].append(message)
            
            # Trim to size limit
            if len(self.message_queue[channel]) > self.MESSAGE_HISTORY_SIZE:
                self.message_queue[channel] = self.message_queue[channel][-self.MESSAGE_HISTORY_SIZE:]

    def get_connection_stats(self) -> Dict[str, Any]:
        """Get statistics about current WebSocket connections."""
        stats = {
            "total_connections": sum(len(connections) for connections in self.active_connections.values()),
            "connections_by_type": {
                conn_type: len(connections) 
                for conn_type, connections in self.active_connections.items()
            },
            "connected_clients": []
        }
        
        # Add client details
        for websocket, metadata in self.connection_metadata.items():
            stats["connected_clients"].append({
                "client_id": metadata.get("client_id"),
                "type": metadata.get("type"),
                "connected_at": metadata.get("connected_at"),
                "last_ping": metadata.get("last_ping")
            })
        
        return stats

    async def ping_all_connections(self):
        """Send ping to all connections to check health."""
        ping_message = {
            "type": "ping",
            "timestamp": datetime.now(timezone.utc).isoformat()
        }
        
        dead_connections = set()
        
        for websocket in list(self.connection_metadata.keys()):
            try:
                await self._send_to_connection(websocket, ping_message)
                self.connection_metadata[websocket]["last_ping"] = datetime.now(timezone.utc)
            except Exception:
                dead_connections.add(websocket)
        
        # Clean up dead connections
        for websocket in dead_connections:
            await self.disconnect(websocket)


# Global WebSocket manager instance
websocket_manager = WebSocketManager()