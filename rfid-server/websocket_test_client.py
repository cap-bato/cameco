"""
WebSocket Test Client for RFID Server Phase 4

This script tests the WebSocket real-time functionality by:
1. Connecting to WebSocket endpoints
2. Listening for real-time events
3. Testing event notifications
4. Validating device status updates

Usage:
    python websocket_test_client.py
"""

import asyncio
import websockets
import json
import logging
from datetime import datetime

# Set up logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


class WebSocketTestClient:
    def __init__(self, base_url="ws://127.0.0.1:8001"):
        self.base_url = base_url
        self.connections = {}
    
    async def connect_to_endpoint(self, endpoint, client_id):
        """Connect to a specific WebSocket endpoint."""
        uri = f"{self.base_url}/ws/{endpoint}?client_id={client_id}"
        
        try:
            logger.info(f"Connecting to {uri}")
            websocket = await websockets.connect(uri)
            self.connections[endpoint] = websocket
            logger.info(f"Connected to {endpoint} endpoint with client_id: {client_id}")
            return websocket
        except Exception as e:
            logger.error(f"Failed to connect to {endpoint}: {e}")
            return None
    
    async def listen_for_messages(self, endpoint, duration=10):
        """Listen for messages on a specific endpoint for a given duration."""
        websocket = self.connections.get(endpoint)
        if not websocket:
            logger.error(f"No connection to {endpoint}")
            return
        
        logger.info(f"Listening for messages on {endpoint} for {duration} seconds...")
        
        try:
            # Listen for messages with timeout
            await asyncio.wait_for(self._message_listener(websocket, endpoint), timeout=duration)
        except asyncio.TimeoutError:
            logger.info(f"Listening timeout reached for {endpoint}")
        except Exception as e:
            logger.error(f"Error listening to {endpoint}: {e}")
    
    async def _message_listener(self, websocket, endpoint):
        """Internal message listener."""
        async for message in websocket:
            try:
                data = json.loads(message)
                logger.info(f"[{endpoint}] Received: {data.get('type', 'unknown')} message")
                logger.debug(f"[{endpoint}] Full message: {json.dumps(data, indent=2, default=str)}")
                
                # Send pong response to pings
                if data.get('type') == 'ping':
                    pong_message = {
                        "type": "pong",
                        "timestamp": datetime.now().isoformat(),
                        "client_id": f"test-client-{endpoint}"
                    }
                    await websocket.send(json.dumps(pong_message))
                    logger.debug(f"[{endpoint}] Sent pong response")
                
            except json.JSONDecodeError:
                logger.warning(f"[{endpoint}] Received invalid JSON: {message}")
    
    async def send_test_message(self, endpoint, message):
        """Send a test message to an endpoint."""
        websocket = self.connections.get(endpoint)
        if not websocket:
            logger.error(f"No connection to {endpoint}")
            return
        
        try:
            await websocket.send(json.dumps(message))
            logger.info(f"[{endpoint}] Sent: {message.get('type', 'unknown')} message")
        except Exception as e:
            logger.error(f"Error sending to {endpoint}: {e}")
    
    async def close_connections(self):
        """Close all WebSocket connections."""
        for endpoint, websocket in self.connections.items():
            try:
                await websocket.close()
                logger.info(f"Closed connection to {endpoint}")
            except Exception as e:
                logger.error(f"Error closing {endpoint}: {e}")


async def test_websocket_endpoints():
    """Test all WebSocket endpoints."""
    client = WebSocketTestClient()
    
    # Test endpoints
    endpoints = [
        ("events", "test-events-client"),
        ("devices", "test-devices-client"),
        ("dashboard", "test-dashboard-client"),
        ("alerts", "test-alerts-client")
    ]
    
    try:
        # Connect to all endpoints
        for endpoint, client_id in endpoints:
            await client.connect_to_endpoint(endpoint, client_id)
        
        # Send test messages
        logger.info("Sending test messages...")
        
        # Test events subscription
        await client.send_test_message("events", {
            "type": "subscribe_filter",
            "filters": {"device_id": "GATE-01", "event_type": "time_in"}
        })
        
        # Test dashboard stats request
        await client.send_test_message("dashboard", {
            "type": "request_stats"
        })
        
        # Test alerts acknowledgment
        await client.send_test_message("alerts", {
            "type": "ack_alert",
            "alert_id": "test-alert-123"
        })
        
        # Listen for messages concurrently
        logger.info("Starting to listen for real-time messages...")
        tasks = []
        for endpoint, _ in endpoints:
            if endpoint in client.connections:
                task = asyncio.create_task(client.listen_for_messages(endpoint, 15))
                tasks.append(task)
        
        # Wait for all listeners to complete
        if tasks:
            await asyncio.gather(*tasks, return_exceptions=True)
        
    except Exception as e:
        logger.error(f"Test error: {e}")
    finally:
        await client.close_connections()


async def test_websocket_stats():
    """Test WebSocket statistics endpoint."""
    import aiohttp
    
    try:
        async with aiohttp.ClientSession() as session:
            async with session.get('http://127.0.0.1:8001/ws/stats') as response:
                if response.status == 200:
                    data = await response.json()
                    logger.info("WebSocket Statistics:")
                    logger.info(f"  Status: {data.get('status')}")
                    stats = data.get('websocket_stats', {})
                    logger.info(f"  Total Connections: {stats.get('total_connections', 0)}")
                    logger.info(f"  Connections by Type: {stats.get('connections_by_type', {})}")
                    logger.info(f"  Connected Clients: {len(stats.get('connected_clients', []))}")
                else:
                    logger.error(f"Failed to get stats: HTTP {response.status}")
    except Exception as e:
        logger.error(f"Error getting WebSocket stats: {e}")


async def main():
    """Main test function."""
    logger.info("🚀 Starting WebSocket Test Client for RFID Server Phase 4")
    logger.info("=" * 60)
    
    # Test 1: WebSocket Statistics
    logger.info("📊 Testing WebSocket Statistics Endpoint...")
    await test_websocket_stats()
    
    print()
    
    # Test 2: WebSocket Connections and Real-time Communication
    logger.info("🔌 Testing WebSocket Real-time Communication...")
    await test_websocket_endpoints()
    
    print()
    logger.info("✅ WebSocket tests completed!")
    logger.info("📖 Check the server logs and FastAPI docs at http://127.0.0.1:8001/docs")


if __name__ == "__main__":
    # Install required packages if needed
    try:
        import websockets
        import aiohttp
    except ImportError as e:
        print(f"❌ Missing required packages: {e}")
        print("💡 Install with: pip install websockets aiohttp")
        exit(1)
    
    # Run tests
    asyncio.run(main())