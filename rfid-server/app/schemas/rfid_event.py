from pydantic import BaseModel, Field
from datetime import datetime
from typing import Optional, Dict, Any


class RFIDEventCreate(BaseModel):
    """Schema for incoming RFID tap events from devices."""
    card_uid: str = Field(..., description="RFID card UID (hex string)")
    device_id: str = Field(..., description="Device ID (e.g., GATE-01)")
    event_type: str = Field(..., description="Event type: time_in, time_out, break_start, break_end")
    timestamp: datetime = Field(..., description="Scan timestamp (ISO 8601)")
    device_signature: Optional[str] = Field(None, description="Optional Ed25519 device signature")
    
    class Config:
        json_schema_extra = {
            "example": {
                "card_uid": "04:3A:B2:C5:D8",
                "device_id": "GATE-01",
                "event_type": "time_in",
                "timestamp": "2026-02-04T08:05:23Z"
            }
        }


class RFIDEventResponse(BaseModel):
    """Schema for RFID event processing responses."""
    status: str = Field(..., description="Processing status: success, rejected, ignored, error")
    sequence_id: Optional[int] = Field(None, description="Assigned sequence ID if successful")
    employee_id: Optional[int] = Field(None, description="Mapped employee ID if found")
    hash: Optional[str] = Field(None, description="Generated hash chain value if successful")
    reason: Optional[str] = Field(None, description="Reason for rejection/error if applicable")
    
    class Config:
        json_schema_extra = {
            "example": {
                "status": "success",
                "sequence_id": 12345,
                "employee_id": 789,
                "hash": "a1b2c3d4e5f6..."
            }
        }


class RFIDEventBatchRequest(BaseModel):
    """Schema for batch RFID event processing."""
    events: list[RFIDEventCreate] = Field(..., description="List of RFID events to process")
    
    class Config:
        json_schema_extra = {
            "example": {
                "events": [
                    {
                        "card_uid": "04:3A:B2:C5:D8",
                        "device_id": "GATE-01",
                        "event_type": "time_in",
                        "timestamp": "2026-02-04T08:05:23Z"
                    }
                ]
            }
        }


class RFIDEventBatchResponse(BaseModel):
    """Schema for batch RFID event processing responses."""
    total: int = Field(..., description="Total number of events in batch")
    processed: int = Field(..., description="Number of successfully processed events")
    ignored: int = Field(..., description="Number of ignored (duplicate) events")
    rejected: int = Field(..., description="Number of rejected events")
    errors: int = Field(..., description="Number of events with errors")
    results: list[RFIDEventResponse] = Field(..., description="Detailed results for each event")
    
    class Config:
        json_schema_extra = {
            "example": {
                "total": 10,
                "processed": 8,
                "ignored": 1,
                "rejected": 1,
                "errors": 0,
                "results": []
            }
        }