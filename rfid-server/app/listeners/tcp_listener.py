import asyncio
import json
import logging
from datetime import datetime
from typing import Optional, Dict, Any
from app.database import AsyncSessionLocal
from app.services.event_processor import EventProcessorService
from app.schemas.rfid_event import RFIDEventCreate
from app.config import settings

logger = logging.getLogger(__name__)

class TCPListener:
    """
    TCP server for receiving RFID events directly from card readers.
    
    Expected JSON format from devices:
    {
        "card_uid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01",
        "event_type": "time_in",
        "timestamp": "2026-02-04T08:05:23Z",
        "device_signature": "optional_signature"
    }
    """
    
    def __init__(self, host: str = "0.0.0.0", port: int = 9000):
        self.host = host
        self.port = port
        self.server = None
        self.connections = {}
        self.stats = {
            "total_connections": 0,
            "active_connections": 0,
            "events_processed": 0,
            "events_rejected": 0,
            "uptime": None
        }
        
    async def handle_client(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """
        Handle incoming TCP connection from RFID device.
        """
        addr = writer.get_extra_info('peername')
        client_id = f"{addr[0]}:{addr[1]}"
        
        logger.info(f"TCP connection established from {client_id}")
        self.stats["total_connections"] += 1
        self.stats["active_connections"] += 1
        self.connections[client_id] = {
            "connected_at": datetime.now(),
            "events_processed": 0,
            "last_event": None
        }
        
        try:
            while True:
                # Read data from client (line-based protocol)
                data = await reader.readline()
                if not data:
                    break
                
                # Parse JSON event
                try:
                    message = data.decode().strip()
                    if not message:
                        continue
                        
                    logger.debug(f"Received from {client_id}: {message}")
                    event_data = json.loads(message)
                    
                    # Process RFID event
                    result = await self._process_event(event_data, client_id)
                    
                    # Send acknowledgment back to device
                    ack = json.dumps(result) + "\n"
                    writer.write(ack.encode())
                    await writer.drain()
                    
                    # Update statistics
                    self.connections[client_id]["events_processed"] += 1
                    self.connections[client_id]["last_event"] = datetime.now()
                    
                    if result.get("status") == "success":
                        self.stats["events_processed"] += 1
                    else:
                        self.stats["events_rejected"] += 1
                        
                except json.JSONDecodeError as e:
                    # Invalid JSON - send error response
                    error_response = {
                        "status": "error",
                        "reason": "invalid_json",
                        "message": str(e)
                    }
                    ack = json.dumps(error_response) + "\n"
                    writer.write(ack.encode())
                    await writer.drain()
                    logger.warning(f"Invalid JSON from {client_id}: {message}")
                    
                except Exception as e:
                    # Other processing errors
                    error_response = {
                        "status": "error", 
                        "reason": "processing_error",
                        "message": str(e)
                    }
                    ack = json.dumps(error_response) + "\n"
                    writer.write(ack.encode())
                    await writer.drain()
                    logger.error(f"Error processing event from {client_id}: {str(e)}")
                
        except asyncio.CancelledError:
            logger.info(f"TCP connection cancelled for {client_id}")
        except Exception as e:
            logger.error(f"TCP handler error for {client_id}: {str(e)}")
        
        finally:
            logger.info(f"TCP connection closed from {client_id}")
            self.stats["active_connections"] -= 1
            if client_id in self.connections:
                del self.connections[client_id]
            writer.close()
            try:
                await writer.wait_closed()
            except Exception:
                pass
    
    async def _process_event(self, event_data: Dict[str, Any], client_id: str) -> Dict[str, Any]:
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
                
                logger.info(f"Event processed from {client_id}: {result.get('status')}")
                return result
                
        except Exception as e:
            logger.error(f"Event processing error from {client_id}: {str(e)}")
            return {
                "status": "error",
                "reason": "processing_failed",
                "message": str(e)
            }
    
    async def start(self):
        """
        Start TCP server for RFID device communication.
        """
        try:
            self.server = await asyncio.start_server(
                self.handle_client, 
                self.host, 
                self.port
            )
            
            self.stats["uptime"] = datetime.now()
            addr = self.server.sockets[0].getsockname()
            logger.info(f"TCP listener started on {addr[0]}:{addr[1]}")
            logger.info("Ready to accept RFID device connections...")
            
            async with self.server:
                await self.server.serve_forever()
                
        except Exception as e:
            logger.error(f"Failed to start TCP server: {str(e)}")
            raise
    
    async def stop(self):
        """
        Stop TCP server gracefully.
        """
        if self.server:
            logger.info("Stopping TCP server...")
            self.server.close()
            await self.server.wait_closed()
            self.server = None
            logger.info("TCP server stopped")
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Get TCP server statistics.
        """
        uptime_seconds = None
        if self.stats["uptime"]:
            uptime_seconds = (datetime.now() - self.stats["uptime"]).total_seconds()
            
        return {
            **self.stats,
            "uptime_seconds": uptime_seconds,
            "active_connections_list": list(self.connections.keys()),
            "server_running": self.server is not None
        }

class TCPManager:
    """
    Manager for TCP listener lifecycle.
    """
    
    def __init__(self):
        self.listener: Optional[TCPListener] = None
        self.listener_task: Optional[asyncio.Task] = None
    
    async def start_listener(self, host: str = None, port: int = None):
        """
        Start TCP listener in background task.
        """
        if self.listener_task and not self.listener_task.done():
            logger.warning("TCP listener already running")
            return
        
        host = host or settings.tcp_listener_host
        port = port or settings.tcp_listener_port
        
        self.listener = TCPListener(host, port)
        self.listener_task = asyncio.create_task(self.listener.start())
        
        # Give it a moment to start
        await asyncio.sleep(0.1)
        logger.info(f"TCP listener started on {host}:{port}")
    
    async def stop_listener(self):
        """
        Stop TCP listener.
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
        
        logger.info("TCP listener stopped")
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Get listener statistics.
        """
        if self.listener:
            return self.listener.get_stats()
        else:
            return {"server_running": False}

# Global TCP manager instance
tcp_manager = TCPManager()