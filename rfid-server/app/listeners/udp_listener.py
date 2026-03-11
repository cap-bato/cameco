import asyncio
import json
import logging
from datetime import datetime
from typing import Optional, Dict, Any, Tuple
from app.database import AsyncSessionLocal
from app.services.event_processor import EventProcessorService
from app.schemas.rfid_event import RFIDEventCreate
from app.config import settings

logger = logging.getLogger(__name__)

class UDPProtocol(asyncio.DatagramProtocol):
    """
    UDP protocol handler for RFID device communication.
    """
    
    def __init__(self, listener):
        self.listener = listener
        self.transport = None
        
    def connection_made(self, transport):
        self.transport = transport
        logger.info("UDP server transport ready")
        
    def datagram_received(self, data: bytes, addr: Tuple[str, int]):
        """
        Handle incoming UDP datagram from RFID device.
        """
        asyncio.create_task(self.listener._handle_datagram(data, addr))
        
    def error_received(self, exc):
        logger.error(f"UDP error received: {exc}")

class UDPListener:
    """
    UDP server for receiving RFID events from card readers.
    
    Expected JSON format from devices (same as TCP):
    {
        "card_uid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01", 
        "event_type": "time_in",
        "timestamp": "2026-02-04T08:05:23Z",
        "device_signature": "optional_signature"
    }
    """
    
    def __init__(self, host: str = "0.0.0.0", port: int = 9001):
        self.host = host
        self.port = port
        self.transport = None
        self.protocol = None
        self.stats = {
            "total_packets": 0,
            "events_processed": 0,
            "events_rejected": 0,
            "uptime": None,
            "unique_senders": set()
        }
        
    async def _handle_datagram(self, data: bytes, addr: Tuple[str, int]):
        """
        Process incoming UDP datagram.
        """
        sender_id = f"{addr[0]}:{addr[1]}"
        self.stats["total_packets"] += 1
        self.stats["unique_senders"].add(sender_id)
        
        try:
            # Decode and parse JSON
            message = data.decode().strip()
            logger.debug(f"UDP received from {sender_id}: {message}")
            
            if not message:
                return
                
            event_data = json.loads(message)
            
            # Process RFID event
            result = await self._process_event(event_data, sender_id)
            
            # Send response back (UDP is connectionless, so this is optional)
            if settings.udp_send_acknowledgments:
                response = json.dumps(result).encode()
                self.transport.sendto(response, addr)
            
            # Update statistics
            if result.get("status") == "success":
                self.stats["events_processed"] += 1
            else:
                self.stats["events_rejected"] += 1
                
        except json.JSONDecodeError as e:
            logger.warning(f"Invalid JSON from {sender_id}: {message}")
            if settings.udp_send_acknowledgments:
                error_response = {
                    "status": "error",
                    "reason": "invalid_json",
                    "message": str(e)
                }
                response = json.dumps(error_response).encode()
                self.transport.sendto(response, addr)
                
        except Exception as e:
            logger.error(f"UDP processing error from {sender_id}: {str(e)}")
            if settings.udp_send_acknowledgments:
                error_response = {
                    "status": "error",
                    "reason": "processing_error", 
                    "message": str(e)
                }
                response = json.dumps(error_response).encode()
                self.transport.sendto(response, addr)
    
    async def _process_event(self, event_data: Dict[str, Any], sender_id: str) -> Dict[str, Any]:
        """
        Process RFID event through the main pipeline.
        """
        try:
            # Validate required fields
            required_fields = ["card_uid", "device_id", "event_type", "timestamp"]
            missing_fields = [field for field in required_fields if field not in event_data]
            
            if missing_fields:
                return {
                    "status": "error",
                    "reason": "missing_fields", 
                    "missing_fields": missing_fields
                }
            
            # Create event object
            event = RFIDEventCreate(**event_data)
            
            # Process through main pipeline
            async with AsyncSessionLocal() as db:
                processor = EventProcessorService(db)
                result = await processor.process_rfid_tap(event)
                
                logger.info(f"UDP event processed from {sender_id}: {result.get('status')}")
                return result
                
        except Exception as e:
            logger.error(f"UDP event processing error from {sender_id}: {str(e)}")
            return {
                "status": "error",
                "reason": "processing_failed",
                "message": str(e)
            }
    
    async def start(self):
        """
        Start UDP server for RFID device communication.
        """
        try:
            loop = asyncio.get_event_loop()
            
            # Create UDP endpoint
            self.transport, self.protocol = await loop.create_datagram_endpoint(
                lambda: UDPProtocol(self),
                local_addr=(self.host, self.port)
            )
            
            self.stats["uptime"] = datetime.now()
            logger.info(f"UDP listener started on {self.host}:{self.port}")
            logger.info("Ready to accept RFID device UDP packets...")
            
            # Keep server running
            while True:
                await asyncio.sleep(1)
                
        except Exception as e:
            logger.error(f"Failed to start UDP server: {str(e)}")
            raise
    
    async def stop(self):
        """
        Stop UDP server.
        """
        if self.transport:
            logger.info("Stopping UDP server...")
            self.transport.close()
            self.transport = None
            self.protocol = None
            logger.info("UDP server stopped")
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Get UDP server statistics.
        """
        uptime_seconds = None
        if self.stats["uptime"]:
            uptime_seconds = (datetime.now() - self.stats["uptime"]).total_seconds()
            
        return {
            **self.stats,
            "uptime_seconds": uptime_seconds,
            "unique_senders": len(self.stats["unique_senders"]),
            "unique_sender_list": list(self.stats["unique_senders"]),
            "server_running": self.transport is not None
        }

class UDPManager:
    """
    Manager for UDP listener lifecycle.
    """
    
    def __init__(self):
        self.listener: Optional[UDPListener] = None
        self.listener_task: Optional[asyncio.Task] = None
    
    async def start_listener(self, host: str = None, port: int = None):
        """
        Start UDP listener in background task.
        """
        if self.listener_task and not self.listener_task.done():
            logger.warning("UDP listener already running")
            return
        
        host = host or settings.udp_listener_host
        port = port or settings.udp_listener_port
        
        self.listener = UDPListener(host, port)
        self.listener_task = asyncio.create_task(self.listener.start())
        
        # Give it a moment to start
        await asyncio.sleep(0.1)
        logger.info(f"UDP listener started on {host}:{port}")
    
    async def stop_listener(self):
        """
        Stop UDP listener.
        """
        if self.listener:
            await self.listener.stop()
            self.listener = None
        
        if self.listener_task:
            self.listener_task.cancel()
            try:
                await self.listener_task
            except asyncio.CancelledError:
                pass
            self.listener_task = None
        
        logger.info("UDP listener stopped")
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Get listener statistics.
        """
        if self.listener:
            return self.listener.get_stats()
        else:
            return {"server_running": False}

# Global UDP manager instance  
udp_manager = UDPManager()