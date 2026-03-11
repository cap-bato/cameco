"""
Integration Test for Phase 4 WebSocket Real-time Updates

This test validates complete end-to-end functionality:
1. Registers a test RFID card
2. Connects to WebSocket event stream
3. Sends a test RFID event via REST API
4. Confirms real-time notification is received via WebSocket

Usage:
    python phase4_integration_test.py
"""

import asyncio
import websockets
import json
import aiohttp
import logging
from datetime import datetime, timezone

# Set up logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

BASE_URL = "http://127.0.0.1:8001"
WS_URL = "ws://127.0.0.1:8001"

async def register_test_card():
    """Register a test RFID card for testing."""
    card_data = {
        "card_uid": "TEST-CARD-PHASE4",
        "employee_id": 999,
        "card_type": "test",
        "is_active": True,
        "notes": "Phase 4 integration test card"
    }
    
    async with aiohttp.ClientSession() as session:
        try:
            async with session.post(f"{BASE_URL}/api/v1/mappings/", json=card_data) as response:
                if response.status == 201:
                    data = await response.json()
                    logger.info(f"✅ Test card registered: {card_data['card_uid']}")
                    return True
                elif response.status == 409:
                    logger.info(f"ℹ️  Test card already exists: {card_data['card_uid']}")
                    return True
                else:
                    logger.error(f"❌ Failed to register test card: HTTP {response.status}")
                    return False
        except Exception as e:
            logger.error(f"❌ Error registering test card: {e}")
            return False

async def listen_for_real_time_events():
    """Connect to WebSocket and wait for real-time events."""
    uri = f"{WS_URL}/ws/events?client_id=integration-test"
    events_received = []
    
    try:
        logger.info(f"🔌 Connecting to WebSocket: {uri}")
        async with websockets.connect(uri) as websocket:
            logger.info("✅ Connected to events WebSocket")
            
            # Wait for messages for 20 seconds
            try:
                await asyncio.wait_for(
                    collect_events(websocket, events_received), 
                    timeout=20.0
                )
            except asyncio.TimeoutError:
                logger.info("⏰ WebSocket listening timeout reached")
            
            return events_received
    
    except Exception as e:
        logger.error(f"❌ WebSocket connection error: {e}")
        return []

async def collect_events(websocket, events_received):
    """Collect events from WebSocket."""
    async for message in websocket:
        try:
            data = json.loads(message)
            message_type = data.get('type', 'unknown')
            logger.info(f"📨 WebSocket received: {message_type}")
            
            if message_type == 'rfid_event':
                events_received.append(data)
                logger.info(f"🎯 RFID Event captured via WebSocket!")
                logger.info(f"   Card UID: {data.get('data', {}).get('card_uid')}")
                logger.info(f"   Status: {data.get('data', {}).get('status')}")
                logger.info(f"   Employee ID: {data.get('data', {}).get('employee_id')}")
                break  # Stop listening after receiving the event
            elif message_type == 'connection_established':
                logger.info("✅ WebSocket connection established")
            else:
                logger.debug(f"📋 Other message: {message_type}")
                
        except json.JSONDecodeError:
            logger.warning(f"⚠️  Invalid JSON received: {message}")

async def send_test_rfid_event():
    """Send a test RFID event via REST API."""
    await asyncio.sleep(2)  # Give WebSocket time to connect
    
    event_data = {
        "card_uid": "TEST-CARD-PHASE4",
        "device_id": "GATE-TEST",
        "event_type": "time_in",
        "timestamp": datetime.now(timezone.utc).isoformat()
    }
    
    async with aiohttp.ClientSession() as session:
        try:
            logger.info(f"📤 Sending test RFID event...")
            logger.info(f"   Card: {event_data['card_uid']}")
            logger.info(f"   Device: {event_data['device_id']}")
            logger.info(f"   Type: {event_data['event_type']}")
            
            async with session.post(f"{BASE_URL}/api/v1/events/", json=event_data) as response:
                if response.status == 201:
                    data = await response.json()
                    logger.info(f"✅ RFID event processed successfully")
                    logger.info(f"   Sequence ID: {data.get('sequence_id')}")
                    logger.info(f"   Employee ID: {data.get('employee_id')}")
                    logger.info(f"   Hash: {data.get('hash', 'N/A')[:16]}...")
                    return data
                else:
                    error_data = await response.json()
                    logger.error(f"❌ Event processing failed: HTTP {response.status}")
                    logger.error(f"   Error: {error_data.get('detail', 'Unknown error')}")
                    return None
        except Exception as e:
            logger.error(f"❌ Error sending RFID event: {e}")
            return None

async def main():
    """Main integration test."""
    logger.info("🚀 Phase 4 Integration Test - WebSocket Real-time Updates")
    logger.info("=" * 70)
    
    # Step 1: Register test card
    logger.info("1️⃣  Registering test RFID card...")
    if not await register_test_card():
        logger.error("❌ Failed to register test card. Aborting test.")
        return False
    
    print()
    
    # Step 2: Start WebSocket listener and send event concurrently
    logger.info("2️⃣  Starting WebSocket listener...")
    logger.info("3️⃣  Sending test RFID event in 2 seconds...")
    
    # Run WebSocket listener and event sender concurrently
    websocket_task = asyncio.create_task(listen_for_real_time_events())
    event_task = asyncio.create_task(send_test_rfid_event())
    
    # Wait for both tasks to complete
    events_received, event_result = await asyncio.gather(websocket_task, event_task)
    
    print()
    
    # Step 3: Analyze results
    logger.info("4️⃣  Analyzing test results...")
    
    success = True
    
    if event_result:
        logger.info("✅ REST API Event Processing: PASS")
    else:
        logger.error("❌ REST API Event Processing: FAIL")
        success = False
    
    if events_received:
        logger.info("✅ WebSocket Real-time Notification: PASS")
        logger.info(f"   Received {len(events_received)} real-time event(s)")
    else:
        logger.error("❌ WebSocket Real-time Notification: FAIL")
        logger.error("   No real-time events received via WebSocket")
        success = False
    
    # Check if the WebSocket event matches the REST event
    if event_result and events_received:
        ws_event = events_received[0]
        rest_sequence_id = event_result.get('sequence_id')
        ws_sequence_id = ws_event.get('data', {}).get('event_id')
        
        if rest_sequence_id == ws_sequence_id:
            logger.info("✅ Event Correlation: PASS")
            logger.info(f"   REST and WebSocket events match (sequence {rest_sequence_id})")
        else:
            logger.warning("⚠️  Event Correlation: MISMATCH")
            logger.warning(f"   REST sequence: {rest_sequence_id}, WebSocket sequence: {ws_sequence_id}")
    
    print()
    
    # Final result
    if success:
        logger.info("🎉 Phase 4 Integration Test: PASSED")
        logger.info("✅ WebSocket real-time updates are working correctly!")
    else:
        logger.error("💥 Phase 4 Integration Test: FAILED")
        logger.error("❌ Some components are not working as expected")
    
    logger.info("📖 Check server logs and documentation at http://127.0.0.1:8001/docs")
    
    return success

if __name__ == "__main__":
    # Check dependencies
    try:
        import websockets
        import aiohttp
    except ImportError as e:
        print(f"❌ Missing required packages: {e}")
        print("💡 Install with: pip install websockets aiohttp")
        exit(1)
    
    # Run the integration test
    success = asyncio.run(main())
    exit(0 if success else 1)