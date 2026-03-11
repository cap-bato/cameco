import pytest
from app.services.hash_chain import HashChainService
import json


@pytest.mark.asyncio
async def test_genesis_hash(db_session):
    """Test that genesis hash is used for first entry."""
    service = HashChainService(db_session)
    
    # Should return genesis hash when no entries exist
    last_hash = await service.get_last_hash()
    assert last_hash == service.GENESIS_HASH


@pytest.mark.asyncio
async def test_hash_computation_deterministic(db_session):
    """Test that hash computation is deterministic."""
    service = HashChainService(db_session)
    
    payload = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01",
        "scan_timestamp": "2026-02-04T08:05:23Z",
        "event_type": "time_in"
    }
    
    prev_hash = service.GENESIS_HASH
    
    # Compute hash multiple times
    hash1 = service.compute_hash(prev_hash, payload)
    hash2 = service.compute_hash(prev_hash, payload)
    hash3 = service.compute_hash(prev_hash, payload)
    
    # Should be identical
    assert hash1 == hash2 == hash3
    assert len(hash1) == 64  # SHA-256 hex string


def test_hash_verification():
    """Test hash verification functionality."""
    service = HashChainService(None)  # No DB needed for this test
    
    payload = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01",
        "scan_timestamp": "2026-02-04T08:05:23Z", 
        "event_type": "time_in"
    }
    
    prev_hash = service.GENESIS_HASH
    correct_hash = service.compute_hash(prev_hash, payload)
    
    # Verification should pass
    assert service.verify_hash(prev_hash, payload, correct_hash) == True
    
    # Wrong hash should fail
    wrong_hash = "abc123"
    assert service.verify_hash(prev_hash, payload, wrong_hash) == False


def test_hash_changes_with_different_payload():
    """Test that different payloads produce different hashes."""
    service = HashChainService(None)
    
    payload1 = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01",
        "scan_timestamp": "2026-02-04T08:05:23Z",
        "event_type": "time_in"
    }
    
    payload2 = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8", 
        "device_id": "GATE-01",
        "scan_timestamp": "2026-02-04T08:05:24Z",  # Different timestamp
        "event_type": "time_in"
    }
    
    prev_hash = service.GENESIS_HASH
    hash1 = service.compute_hash(prev_hash, payload1)
    hash2 = service.compute_hash(prev_hash, payload2)
    
    assert hash1 != hash2


def test_hash_changes_with_different_prev_hash():
    """Test that different previous hashes produce different results."""
    service = HashChainService(None)
    
    payload = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01", 
        "scan_timestamp": "2026-02-04T08:05:23Z",
        "event_type": "time_in"
    }
    
    prev_hash1 = service.GENESIS_HASH
    prev_hash2 = "1234567890abcdef" * 4  # Different previous hash
    
    hash1 = service.compute_hash(prev_hash1, payload)
    hash2 = service.compute_hash(prev_hash2, payload)
    
    assert hash1 != hash2


def test_payload_serialization_consistency():
    """Test that payload serialization is consistent."""
    service = HashChainService(None)
    
    # Same data in different order should produce same hash
    payload1 = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01"
    }
    
    payload2 = {
        "device_id": "GATE-01", 
        "employee_rfid": "04:3A:B2:C5:D8",
        "sequence_id": 1
    }
    
    prev_hash = service.GENESIS_HASH
    hash1 = service.compute_hash(prev_hash, payload1)
    hash2 = service.compute_hash(prev_hash, payload2)
    
    # Should be the same because JSON is sorted
    assert hash1 == hash2


def test_tampering_detection():
    """Test that tampering is detected."""
    service = HashChainService(None)
    
    original_payload = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01",
        "scan_timestamp": "2026-02-04T08:05:23Z",
        "event_type": "time_in"
    }
    
    tampered_payload = {
        "sequence_id": 1,
        "employee_rfid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01", 
        "scan_timestamp": "2026-02-04T08:05:23Z",
        "event_type": "time_out"  # Changed event type
    }
    
    prev_hash = service.GENESIS_HASH
    original_hash = service.compute_hash(prev_hash, original_payload)
    
    # Original should verify
    assert service.verify_hash(prev_hash, original_payload, original_hash) == True
    
    # Tampered payload with original hash should fail
    assert service.verify_hash(prev_hash, tampered_payload, original_hash) == False


@pytest.mark.asyncio
async def test_hash_chain_sequence(db_session):
    """Test a sequence of hash chain operations."""
    service = HashChainService(db_session)
    
    payloads = [
        {
            "sequence_id": 1,
            "employee_rfid": "04:3A:B2:C5:D8", 
            "device_id": "GATE-01",
            "scan_timestamp": "2026-02-04T08:05:23Z",
            "event_type": "time_in"
        },
        {
            "sequence_id": 2,
            "employee_rfid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01", 
            "scan_timestamp": "2026-02-04T08:05:24Z",
            "event_type": "time_out"
        }
    ]
    
    previous_hash = service.GENESIS_HASH
    
    for payload in payloads:
        # Generate hash
        current_hash = await service.generate_next_hash(payload)
        
        # Should be valid
        assert len(current_hash) == 64
        assert current_hash != previous_hash
        
        # Should verify
        assert service.verify_hash(previous_hash, payload, current_hash) == True
        
        previous_hash = current_hash