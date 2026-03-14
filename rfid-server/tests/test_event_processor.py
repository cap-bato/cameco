import pytest
from datetime import datetime, timezone, timedelta
from app.services.event_processor import EventProcessorService
from app.services.card_mapper import CardMapperService
from app.schemas.rfid_event import RFIDEventCreate


@pytest.mark.asyncio
async def test_process_valid_event(db_session):
    """Test processing a valid RFID event."""
    # Setup: Register test card
    card_mapper = CardMapperService(db_session)
    await card_mapper.register_card("TEST-CARD-001", 12345, "mifare")
    
    # Create test event
    event = RFIDEventCreate(
        card_uid="TEST-CARD-001",
        device_id="GATE-01",
        event_type="time_in",
        timestamp=datetime.now(timezone.utc)
    )
    
    # Process event
    processor = EventProcessorService(db_session)
    result = await processor.process_rfid_tap(event)
    
    # Assertions
    assert result["status"] == "success"
    assert "sequence_id" in result
    assert "employee_id" in result
    assert "hash" in result
    assert result["employee_id"] == 12345


@pytest.mark.asyncio
async def test_unknown_card_rejection(db_session):
    """Test that unknown cards are rejected."""
    event = RFIDEventCreate(
        card_uid="UNKNOWN-CARD",
        device_id="GATE-01", 
        event_type="time_in",
        timestamp=datetime.now(timezone.utc)
    )
    
    processor = EventProcessorService(db_session)
    result = await processor.process_rfid_tap(event)
    
    assert result["status"] == "rejected"
    assert result["reason"] == "unknown_card"


@pytest.mark.asyncio
async def test_duplicate_detection(db_session):
    """Test that duplicate events within window are ignored."""
    # Setup: Register test card
    card_mapper = CardMapperService(db_session)
    await card_mapper.register_card("TEST-DUP-001", 12346, "mifare")
    
    timestamp = datetime.now(timezone.utc)
    event = RFIDEventCreate(
        card_uid="TEST-DUP-001",
        device_id="GATE-01",
        event_type="time_in", 
        timestamp=timestamp
    )
    
    processor = EventProcessorService(db_session)
    
    # First event should succeed
    result1 = await processor.process_rfid_tap(event)
    assert result1["status"] == "success"
    
    # Second event with same parameters should be ignored
    event2 = RFIDEventCreate(
        card_uid="TEST-DUP-001", 
        device_id="GATE-01",
        event_type="time_in",
        timestamp=timestamp + timedelta(seconds=5)  # Within window
    )
    
    result2 = await processor.process_rfid_tap(event2)
    assert result2["status"] == "ignored"
    assert result2["reason"] == "duplicate"


@pytest.mark.asyncio  
async def test_different_event_types_not_duplicate(db_session):
    """Test that different event types are not considered duplicates."""
    # Setup: Register test card
    card_mapper = CardMapperService(db_session)
    await card_mapper.register_card("TEST-TYPES-001", 12347, "mifare")
    
    timestamp = datetime.now(timezone.utc)
    
    # Time in event
    event1 = RFIDEventCreate(
        card_uid="TEST-TYPES-001",
        device_id="GATE-01", 
        event_type="time_in",
        timestamp=timestamp
    )
    
    # Time out event (different type)
    event2 = RFIDEventCreate(
        card_uid="TEST-TYPES-001",
        device_id="GATE-01",
        event_type="time_out", 
        timestamp=timestamp + timedelta(seconds=5)
    )
    
    processor = EventProcessorService(db_session)
    
    # Both events should succeed
    result1 = await processor.process_rfid_tap(event1)
    assert result1["status"] == "success"
    
    result2 = await processor.process_rfid_tap(event2)
    assert result2["status"] == "success"


@pytest.mark.asyncio
async def test_batch_processing(db_session):
    """Test batch processing of multiple events.""" 
    # Setup: Register test cards
    card_mapper = CardMapperService(db_session)
    await card_mapper.register_card("BATCH-001", 12348, "mifare")
    await card_mapper.register_card("BATCH-002", 12349, "mifare")
    
    timestamp = datetime.now(timezone.utc)
    
    events = [
        RFIDEventCreate(
            card_uid="BATCH-001",
            device_id="GATE-01",
            event_type="time_in",
            timestamp=timestamp
        ),
        RFIDEventCreate(
            card_uid="BATCH-002", 
            device_id="GATE-02",
            event_type="time_in",
            timestamp=timestamp + timedelta(seconds=1)
        ),
        RFIDEventCreate(
            card_uid="UNKNOWN-BATCH",  # This should be rejected
            device_id="GATE-01",
            event_type="time_in", 
            timestamp=timestamp + timedelta(seconds=2)
        )
    ]
    
    processor = EventProcessorService(db_session)
    results = []
    
    for event in events:
        result = await processor.process_rfid_tap(event)
        results.append(result)
    
    # Check results
    assert len(results) == 3
    assert results[0]["status"] == "success"
    assert results[1]["status"] == "success"  
    assert results[2]["status"] == "rejected"
    
    # Check that sequence IDs are sequential
    assert results[1]["sequence_id"] == results[0]["sequence_id"] + 1


@pytest.mark.asyncio
async def test_sequence_id_generation(db_session):
    """Test that sequence IDs are properly generated."""
    # Setup: Register test card
    card_mapper = CardMapperService(db_session)
    await card_mapper.register_card("SEQ-TEST-001", 12350, "mifare")
    
    processor = EventProcessorService(db_session)
    previous_seq_id = None
    
    # Process multiple events and check sequence
    for i in range(5):
        event = RFIDEventCreate(
            card_uid="SEQ-TEST-001",
            device_id="GATE-01",
            event_type="time_in" if i % 2 == 0 else "time_out",
            timestamp=datetime.now(timezone.utc) + timedelta(seconds=i*20)  # Avoid duplicates
        )
        
        result = await processor.process_rfid_tap(event)
        assert result["status"] == "success"
        
        if previous_seq_id is not None:
            assert result["sequence_id"] == previous_seq_id + 1
        
        previous_seq_id = result["sequence_id"]


@pytest.mark.asyncio
async def test_hash_chain_generation(db_session):
    """Test that hash chains are properly generated."""  
    # Setup: Register test card
    card_mapper = CardMapperService(db_session)
    await card_mapper.register_card("HASH-TEST-001", 12351, "mifare")
    
    processor = EventProcessorService(db_session)
    
    # Process first event
    event1 = RFIDEventCreate(
        card_uid="HASH-TEST-001",
        device_id="GATE-01", 
        event_type="time_in",
        timestamp=datetime.now(timezone.utc)
    )
    
    result1 = await processor.process_rfid_tap(event1)
    assert result1["status"] == "success"
    assert "hash" in result1
    assert len(result1["hash"]) == 64  # SHA-256 hex string
    
    # Process second event
    event2 = RFIDEventCreate(
        card_uid="HASH-TEST-001",
        device_id="GATE-01",
        event_type="time_out",
        timestamp=datetime.now(timezone.utc) + timedelta(seconds=30)
    )
    
    result2 = await processor.process_rfid_tap(event2) 
    assert result2["status"] == "success"
    assert "hash" in result2
    assert len(result2["hash"]) == 64
    
    # Hash should be different
    assert result1["hash"] != result2["hash"]