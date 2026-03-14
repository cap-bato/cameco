#!/usr/bin/env python3
"""
Test script for Phase 2 Core Services

This script tests the complete RFID event processing pipeline:
1. Card Mapping Service  
2. Hash Chain Service
3. Deduplication Service
4. Event Processor Service

Validates that all services work correctly and integrate properly.
"""

import asyncio
import os
import sys
from datetime import datetime, timedelta, timezone

# Add the app directory to the Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from app.config import settings
from app.database import AsyncSessionLocal, init_db
from app.services import (
    CardMapperService,
    HashChainService, 
    DeduplicationService,
    EventProcessorService
)
from app.schemas.rfid_event import RFIDEventCreate


async def test_card_mapping_service():
    """Test the Card Mapping Service."""
    print("\n🔍 Testing Card Mapping Service...")
    
    async with AsyncSessionLocal() as db:
        service = CardMapperService(db)
        
        # Test card registration - use unique card UID each time
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
        test_card_uid = f"TEST-CARD-{timestamp}"
        test_employee_id = 999999  # Using a test employee ID
        
        try:
            # Register a test card
            mapping = await service.register_card(
                card_uid=test_card_uid,
                employee_id=test_employee_id,
                card_type="test_card",
                notes="Test card for Phase 2 validation"
            )
            print(f"  ✅ Card registered: {mapping.card_uid} → Employee {mapping.employee_id}")
            
            # Test card lookup
            found_employee_id = await service.get_employee_id_by_card(test_card_uid)
            assert found_employee_id == test_employee_id, f"Expected {test_employee_id}, got {found_employee_id}"
            print(f"  ✅ Card lookup successful: {test_card_uid} → Employee {found_employee_id}")
            
            # Test card details lookup
            details = await service.get_card_details(test_card_uid)
            assert details is not None, "Card details should be found"
            assert details.is_active == True, "Card should be active"
            print(f"  ✅ Card details retrieved: active={details.is_active}, usage_count={details.usage_count}")
            
            # Test deactivation
            success = await service.deactivate_card(test_card_uid)
            assert success == True, "Card deactivation should succeed"
            
            # Verify card is deactivated
            deactivated_lookup = await service.get_employee_id_by_card(test_card_uid)
            assert deactivated_lookup is None, "Deactivated card should not return employee ID"
            print(f"  ✅ Card deactivation successful: {test_card_uid}")
            
            print("  🎉 Card Mapping Service tests passed!")
            
        except Exception as e:
            print(f"  ❌ Card Mapping Service error: {e}")
            raise


async def test_hash_chain_service():
    """Test the Hash Chain Service.""" 
    print("\n🔗 Testing Hash Chain Service...")
    
    async with AsyncSessionLocal() as db:
        service = HashChainService(db)
        
        try:
            # Test basic hash computation
            test_payload = {
                "sequence_id": 1,
                "employee_rfid": "TEST-CARD-123456", 
                "device_id": "TEST-DEVICE",
                "scan_timestamp": datetime.now(timezone.utc).isoformat(),
                "event_type": "time_in"
            }
            
            # Test hash computation with genesis hash
            genesis_hash = service.genesis_hash
            computed_hash = service.compute_hash(genesis_hash, test_payload)
            assert len(computed_hash) == 64, f"Hash should be 64 chars, got {len(computed_hash)}"  # SHA-256 hex length
            print(f"  ✅ Hash computation successful: {computed_hash[:16]}...")
            
            # Test hash verification  
            is_valid = service.verify_hash(genesis_hash, test_payload, computed_hash)
            assert is_valid == True, "Hash verification should pass"
            print(f"  ✅ Hash verification passed")
            
            # Test tampering detection
            tampered_payload = test_payload.copy()
            tampered_payload["event_type"] = "time_out"  # Tamper with event type
            is_valid_tampered = service.verify_hash(genesis_hash, tampered_payload, computed_hash)
            assert is_valid_tampered == False, "Tampered hash should fail verification"
            print(f"  ✅ Tampering detection working")
            
            # Test deterministic hashing (same input = same output)
            computed_hash2 = service.compute_hash(genesis_hash, test_payload)
            assert computed_hash == computed_hash2, "Hash should be deterministic"
            print(f"  ✅ Hash determinism verified")
            
            # Test payload creation helper
            payload = service.create_payload(
                sequence_id=1,
                employee_rfid="TEST-CARD",
                device_id="TEST-DEVICE", 
                scan_timestamp=datetime.now(timezone.utc).isoformat(),
                event_type="time_in"
            )
            assert "sequence_id" in payload, "Payload should contain sequence_id"
            assert "employee_rfid" in payload, "Payload should contain employee_rfid"
            print(f"  ✅ Payload creation successful: {len(payload)} fields")
            
            print("  🎉 Hash Chain Service tests passed!")
            
        except Exception as e:
            print(f"  ❌ Hash Chain Service error: {e}")
            raise


async def test_deduplication_service():
    """Test the Deduplication Service."""
    print("\n🔁 Testing Deduplication Service...")
    
    async with AsyncSessionLocal() as db:
        service = DeduplicationService(db)
        
        try:
            # Clean up any existing test cache entries
            await service.force_cleanup_all()
            
            # Test initial state (no duplicates)
            test_employee_id = 999999
            test_device_id = "TEST-DEVICE"
            test_event_type = "time_in"
            test_timestamp = datetime.now(timezone.utc)
            
            is_dup_initial = await service.is_duplicate(
                test_employee_id, test_device_id, test_event_type, test_timestamp
            )
            assert is_dup_initial == False, "Initial check should not find duplicates"
            print(f"  ✅ Initial duplicate check: not duplicate")
            
            # Add to cache
            await service.add_to_cache(
                test_employee_id, test_device_id, test_event_type, test_timestamp, 1
            )
            print(f"  ✅ Added event to cache")
            
            # Test duplicate detection within window (5 seconds later)
            near_timestamp = test_timestamp + timedelta(seconds=5)
            is_dup_near = await service.is_duplicate(
                test_employee_id, test_device_id, test_event_type, near_timestamp
            )
            assert is_dup_near == True, "Events within window should be detected as duplicates"
            print(f"  ✅ Duplicate detection within window: duplicate found")
            
            # Test different employee (should not be duplicate)
            different_employee_id = 888888
            is_dup_diff_employee = await service.is_duplicate(
                different_employee_id, test_device_id, test_event_type, near_timestamp
            )
            assert is_dup_diff_employee == False, "Different employee should not be duplicate"
            print(f"  ✅ Different employee check: not duplicate")
            
            # Test different event type (should not be duplicate)
            is_dup_diff_type = await service.is_duplicate(
                test_employee_id, test_device_id, "time_out", near_timestamp
            )
            assert is_dup_diff_type == False, "Different event type should not be duplicate"
            print(f"  ✅ Different event type check: not duplicate")
            
            # Test cache statistics
            stats = await service.get_cache_stats()
            assert stats["active_entries"] > 0, "Should have active cache entries"
            print(f"  ✅ Cache stats retrieved: {stats['active_entries']} active entries")
            
            # Test cleanup
            cleanup_count = await service.force_cleanup_all()
            print(f"  ✅ Cache cleanup: removed {cleanup_count} entries")
            
            print("  🎉 Deduplication Service tests passed!")
            
        except Exception as e:
            print(f"  ❌ Deduplication Service error: {e}")
            raise


async def test_event_processor_service():
    """Test the Event Processor Service (full pipeline)."""
    print("\n🚀 Testing Event Processor Service...")
    
    async with AsyncSessionLocal() as db:
        service = EventProcessorService(db)
        
        try:
            # First register a test card for known employee - use unique card UID
            card_service = CardMapperService(db)
            timestamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
            test_card_uid = f"PIPELINE-TEST-{timestamp}"
            test_employee_id = 999998
            
            await card_service.register_card(
                card_uid=test_card_uid,
                employee_id=test_employee_id,
                notes="Test card for pipeline validation"
            )
            print(f"  ✅ Test card registered: {test_card_uid} → Employee {test_employee_id}")
            
            # Create test event
            test_event = RFIDEventCreate(
                card_uid=test_card_uid,
                device_id="TEST-DEVICE-001",
                event_type="time_in",
                timestamp=datetime.now(timezone.utc),
                device_signature=None
            )
            
            # Process the event
            result = await service.process_rfid_tap(test_event)
            
            assert result["status"] == "success", f"Event processing should succeed, got {result}"
            assert result["employee_id"] == test_employee_id, f"Should map to correct employee"
            assert "sequence_id" in result, "Should assign sequence ID"
            assert "hash" in result, "Should generate hash"
            print(f"  ✅ Event processed successfully: seq={result['sequence_id']}, hash={result['hash'][:16]}...")
            
            # Test duplicate detection by processing same event again
            duplicate_result = await service.process_rfid_tap(test_event)
            assert duplicate_result["status"] == "ignored", f"Duplicate should be ignored, got {duplicate_result['status']}"
            assert duplicate_result["reason"] == "duplicate", f"Should be marked as duplicate"
            print(f"  ✅ Duplicate detection working: {duplicate_result['reason']}")
            
            # Test unknown card rejection
            unknown_event = RFIDEventCreate(
                card_uid="UNKNOWN-CARD-12345",
                device_id="TEST-DEVICE-001",
                event_type="time_in",
                timestamp=datetime.now(timezone.utc)
            )
            
            unknown_result = await service.process_rfid_tap(unknown_event)
            assert unknown_result["status"] == "rejected", f"Unknown card should be rejected"
            assert unknown_result["reason"] == "unknown_card", f"Should be marked as unknown card"
            print(f"  ✅ Unknown card rejection working: {unknown_result['reason']}")
            
            # Test batch processing
            batch_events = [
                RFIDEventCreate(
                    card_uid=test_card_uid,
                    device_id="TEST-DEVICE-001",
                    event_type="time_out",
                    timestamp=datetime.now(timezone.utc) + timedelta(minutes=30),
                ),
                RFIDEventCreate(
                    card_uid="UNKNOWN-BATCH-CARD",
                    device_id="TEST-DEVICE-001", 
                    event_type="time_in",
                    timestamp=datetime.now(timezone.utc) + timedelta(hours=1),
                )
            ]
            
            batch_result = await service.process_rfid_batch(batch_events)
            assert batch_result.total == 2, "Should process 2 events"
            assert batch_result.processed >= 1, "Should process at least 1 valid event"
            assert batch_result.rejected >= 1, "Should reject at least 1 unknown card"
            print(f"  ✅ Batch processing: {batch_result.processed} processed, {batch_result.rejected} rejected")
            
            # Test ledger statistics
            stats = await service.get_ledger_stats()
            assert stats["total_events"] > 0, "Should have events in ledger"
            print(f"  ✅ Ledger stats: {stats['total_events']} total events, {stats['latest_sequence_id']} latest sequence")
            
            # Test latest events retrieval  
            latest = await service.get_latest_events(limit=5)
            assert len(latest) > 0, "Should have recent events"
            print(f"  ✅ Latest events: {len(latest)} retrieved")
            
            # Clean up test card
            await card_service.deactivate_card(test_card_uid)
            
            print("  🎉 Event Processor Service tests passed!")
            
        except Exception as e:
            print(f"  ❌ Event Processor Service error: {e}")
            raise


async def main():
    """Run all Phase 2 service tests."""
    print("🧪 Phase 2 Core Services Testing")
    print("=" * 50)
    
    try:
        # Initialize database
        await init_db()
        print("✅ Database initialized")
        
        # Run all service tests
        await test_card_mapping_service()
        await test_hash_chain_service() 
        await test_deduplication_service()
        await test_event_processor_service()
        
        print("\n🎉 ALL PHASE 2 SERVICE TESTS PASSED!")
        print("✅ Card Mapping Service: Working")
        print("✅ Hash Chain Service: Working")  
        print("✅ Deduplication Service: Working")
        print("✅ Event Processor Service: Working")
        print("\n🚀 Phase 2 is ready for Phase 3 (API Endpoints)!")
        
    except Exception as e:
        print(f"\n❌ PHASE 2 SERVICE TESTS FAILED!")
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    asyncio.run(main())
