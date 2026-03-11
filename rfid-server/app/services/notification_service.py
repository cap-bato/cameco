import asyncio
from typing import Dict, Any, Optional
from datetime import datetime, timezone
from app.websocket.connection_manager import websocket_manager
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.rfid_device import RFIDDevice
from app.models.rfid_ledger import RFIDLedger
import logging

logger = logging.getLogger(__name__)


class RealtimeNotificationService:
    """
    Service for handling real-time notifications and event streaming.
    
    Integrates with WebSocket manager to broadcast events as they occur.
    """

    def __init__(self):
        self.stats_cache = {}
        self.device_statuses = {}
        self.last_stats_update = None

    async def notify_rfid_event(self, event_result: Dict[str, Any], original_event: Dict[str, Any]):
        """
        Notify all subscribers about a new RFID event.
        
        Args:
            event_result: Result from EventProcessorService.process_rfid_tap()
            original_event: Original RFIDEventCreate data
        """
        try:
            # Construct notification payload
            notification = {
                "event_id": event_result.get("sequence_id"),
                "card_uid": original_event.get("card_uid"),
                "device_id": original_event.get("device_id"),
                "event_type": original_event.get("event_type"),
                "timestamp": original_event.get("timestamp"),
                "employee_id": event_result.get("employee_id"),
                "status": event_result.get("status"),
                "hash": event_result.get("hash"),
                "processing_time": datetime.now(timezone.utc).isoformat()
            }
            
            # Add reason for rejected/ignored events
            if event_result.get("reason"):
                notification["reason"] = event_result["reason"]
            
            # Broadcast to WebSocket subscribers
            await websocket_manager.broadcast_event(notification)
            
            # Send alerts for problematic events
            if event_result["status"] in ["rejected", "ignored"]:
                await self._send_event_alert(notification, event_result["status"])
                
            logger.debug(f"RFID event notification sent: {notification['event_id']}")
            
        except Exception as e:
            logger.error(f"Error sending RFID event notification: {str(e)}")

    async def notify_device_heartbeat(self, device_id: str, heartbeat_data: Dict[str, Any]):
        """
        Notify subscribers about device heartbeat received.
        
        Args:
            device_id: Device identifier
            heartbeat_data: Heartbeat payload with timestamp and metadata
        """
        try:
            notification = {
                "device_id": device_id,
                "heartbeat_timestamp": heartbeat_data.get("timestamp"),
                "status": "online",
                "metadata": heartbeat_data.get("metadata", {}),
                "notification_time": datetime.now(timezone.utc).isoformat()
            }
            
            # Update device status cache
            self.device_statuses[device_id] = {
                "status": "online",
                "last_heartbeat": heartbeat_data.get("timestamp"),
                "last_update": datetime.now(timezone.utc)
            }
            
            await websocket_manager.broadcast_device_status(notification)
            logger.debug(f"Device heartbeat notification sent: {device_id}")
            
        except Exception as e:
            logger.error(f"Error sending device heartbeat notification: {str(e)}")

    async def notify_device_offline(self, device_id: str, last_seen: Optional[datetime] = None):
        """
        Notify subscribers that a device has gone offline.
        
        Args:
            device_id: Device identifier
            last_seen: Last heartbeat timestamp
        """
        try:
            notification = {
                "device_id": device_id,
                "status": "offline",
                "last_seen": last_seen.isoformat() if last_seen else None,
                "notification_time": datetime.now(timezone.utc).isoformat(),
                "alert_level": "warning"
            }
            
            # Update device status cache
            self.device_statuses[device_id] = {
                "status": "offline",
                "last_heartbeat": last_seen,
                "last_update": datetime.now(timezone.utc)
            }
            
            await websocket_manager.broadcast_device_status(notification)
            
            # Also send as alert
            await websocket_manager.broadcast_alert({
                "alert_type": "device_offline",
                "device_id": device_id,
                "message": f"Device {device_id} has gone offline",
                "last_seen": notification["last_seen"],
                "requires_attention": True
            }, priority="high")
            
            logger.warning(f"Device offline notification sent: {device_id}")
            
        except Exception as e:
            logger.error(f"Error sending device offline notification: {str(e)}")

    async def notify_stats_update(self, stats_data: Dict[str, Any]):
        """
        Broadcast statistics update to dashboard subscribers.
        
        Args:
            stats_data: Statistics payload from EventProcessorService.get_ledger_stats()
        """
        try:
            # Enhance stats with real-time metrics
            enhanced_stats = {
                **stats_data,
                "device_status_summary": self._get_device_status_summary(),
                "last_updated": datetime.now(timezone.utc).isoformat(),
                "websocket_connections": websocket_manager.get_connection_stats()["total_connections"]
            }
            
            self.stats_cache = enhanced_stats
            self.last_stats_update = datetime.now(timezone.utc)
            
            await websocket_manager.broadcast_stats_update(enhanced_stats)
            logger.debug("Statistics update notification sent")
            
        except Exception as e:
            logger.error(f"Error sending stats update notification: {str(e)}")

    async def send_system_alert(self, alert_type: str, message: str, 
                               priority: str = "medium", **kwargs):
        """
        Send a system-wide alert notification.
        
        Args:
            alert_type: Type of alert (e.g., "security_breach", "system_error") 
            message: Human-readable alert message
            priority: Alert priority (low, medium, high, critical)
            **kwargs: Additional alert data
        """
        try:
            alert_data = {
                "alert_type": alert_type,
                "message": message,
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "source": "rfid_server",
                **kwargs
            }
            
            await websocket_manager.broadcast_alert(alert_data, priority)
            
            logger.info(f"System alert sent: {alert_type} - {message} (priority: {priority})")
            
        except Exception as e:
            logger.error(f"Error sending system alert: {str(e)}")

    async def _send_event_alert(self, event_data: Dict[str, Any], event_status: str):
        """Send alerts for problematic RFID events."""
        alert_messages = {
            "rejected": f"RFID card {event_data['card_uid']} rejected at device {event_data['device_id']}",
            "ignored": f"Duplicate RFID event ignored for card {event_data['card_uid']} at device {event_data['device_id']}"
        }
        
        priority = "high" if event_status == "rejected" else "low"
        
        await self.send_system_alert(
            alert_type=f"event_{event_status}",
            message=alert_messages.get(event_status, f"Event {event_status}"),
            priority=priority,
            card_uid=event_data["card_uid"],
            device_id=event_data["device_id"],
            event_type=event_data["event_type"],
            reason=event_data.get("reason")
        )

    def _get_device_status_summary(self) -> Dict[str, int]:
        """Get summary of device statuses."""
        summary = {"online": 0, "offline": 0, "unknown": 0}
        
        for device_id, status_info in self.device_statuses.items():
            status = status_info.get("status", "unknown")
            summary[status] = summary.get(status, 0) + 1
        
        return summary

    async def get_cached_stats(self) -> Optional[Dict[str, Any]]:
        """Get cached statistics data."""
        return self.stats_cache

    async def ping_all_connections(self):
        """Health check for all WebSocket connections."""
        try:
            await websocket_manager.ping_all_connections()
            logger.debug("WebSocket health check completed")
        except Exception as e:
            logger.error(f"Error during WebSocket health check: {str(e)}")


# Global notification service instance
notification_service = RealtimeNotificationService()


async def start_background_tasks():
    """
    Start background tasks for real-time monitoring.
    
    These tasks should be started when the application starts up.
    """
    logger.info("Starting real-time notification background tasks")
    
    # Start WebSocket health check task
    asyncio.create_task(websocket_health_checker())
    
    # Start device monitoring task
    asyncio.create_task(device_status_monitor())
    
    # Start stats updater task
    asyncio.create_task(stats_updater())


async def websocket_health_checker():
    """Background task to check WebSocket connection health."""
    while True:
        try:
            await notification_service.ping_all_connections()
            await asyncio.sleep(30)  # Check every 30 seconds
        except Exception as e:
            logger.error(f"WebSocket health checker error: {str(e)}")
            await asyncio.sleep(60)  # Wait longer on error


async def device_status_monitor():
    """Background task to monitor device online/offline status."""
    while True:
        try:
            # This would integrate with your device monitoring logic
            # For now, just run every 2 minutes
            await asyncio.sleep(120)
            
            # Future: Check device last heartbeat times and send offline notifications
            logger.debug("Device status monitor running")
            
        except Exception as e:
            logger.error(f"Device status monitor error: {str(e)}")
            await asyncio.sleep(180)


async def stats_updater():
    """Background task to periodically update statistics."""
    while True:
        try:
            # Update stats every 5 minutes
            await asyncio.sleep(300)
            
            # This would call EventProcessorService.get_ledger_stats()
            # and broadcast via notification_service.notify_stats_update()
            logger.debug("Stats updater running")
            
        except Exception as e:
            logger.error(f"Stats updater error: {str(e)}")
            await asyncio.sleep(600)