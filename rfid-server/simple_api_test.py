#!/usr/bin/env python3
"""
Simple API test for Phase 4 - WebSocket Real-time Updates
Tests basic API endpoints before WebSocket functionality
"""

import asyncio
import aiohttp
import logging

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

BASE_URL = "http://127.0.0.1:8001"

async def test_api_endpoints():
    """Test basic API endpoints"""
    async with aiohttp.ClientSession() as session:
        
        # Test 1: Health check
        logger.info("🔍 Testing health endpoint...")
        try:
            async with session.get(f"{BASE_URL}/api/v1/health/check") as response:
                if response.status == 200:
                    data = await response.json()
                    logger.info(f"✅ Health check passed: {data}")
                else:
                    logger.error(f"❌ Health check failed: HTTP {response.status}")
                    return False
        except Exception as e:
            logger.error(f"❌ Health check error: {e}")
            return False

        # Test 2: Test endpoint
        logger.info("🔍 Testing test endpoint...")
        try:
            async with session.get(f"{BASE_URL}/test") as response:
                if response.status == 200:
                    data = await response.json()
                    logger.info(f"✅ Test endpoint passed: {data.get('message', 'No message')}")
                else:
                    logger.error(f"❌ Test endpoint failed: HTTP {response.status}")
                    return False
        except Exception as e:
            logger.error(f"❌ Test endpoint error: {e}")
            return False

        # Test 3: WebSocket stats endpoint
        logger.info("🔍 Testing WebSocket stats endpoint...")
        try:
            async with session.get(f"{BASE_URL}/ws/stats") as response:
                if response.status == 200:
                    data = await response.json()
                    logger.info(f"✅ WebSocket stats passed: {data}")
                else:
                    logger.error(f"❌ WebSocket stats failed: HTTP {response.status}")
                    return False
        except Exception as e:
            logger.error(f"❌ WebSocket stats error: {e}")
            return False

        # Test 4: Try registering a test card (simple test)
        logger.info("🔍 Testing card registration endpoint...")
        card_data = {
            "card_uid": "SIMPLE-TEST-CARD",
            "employee_id": 888,
            "card_type": "test",
            "is_active": True,
            "notes": "Simple API test card"
        }
        
        try:
            async with session.post(f"{BASE_URL}/api/v1/mappings/", json=card_data, timeout=aiohttp.ClientTimeout(total=10)) as response:
                if response.status in [201, 409]:  # Created or Conflict (already exists)
                    if response.status == 201:
                        data = await response.json()
                        logger.info(f"✅ Card registration passed: {card_data['card_uid']}")
                    else:
                        logger.info(f"ℹ️  Card already exists: {card_data['card_uid']}")
                else:
                    logger.error(f"❌ Card registration failed: HTTP {response.status}")
                    error_text = await response.text()
                    logger.error(f"Error details: {error_text}")
                    return False
        except Exception as e:
            logger.error(f"❌ Card registration error: {e}")
            return False

        return True

async def main():
    """Main test function"""
    logger.info("🚀 Simple API Test for Phase 4")
    logger.info("=" * 50)
    
    success = await test_api_endpoints()
    
    if success:
        logger.info("✅ All API tests passed! Phase 4 endpoints are working correctly")
        return True
    else:
        logger.error("❌ Some API tests failed")
        return False

if __name__ == "__main__":
    success = asyncio.run(main())
    exit(0 if success else 1)