from pydantic import BaseModel, Field
from datetime import datetime
from typing import Optional


class CardMappingCreate(BaseModel):
    """Schema for creating new card mappings."""
    card_uid: str = Field(..., description="RFID card UID (hex string)")
    employee_id: int = Field(..., description="Employee ID to map card to")
    card_type: str = Field("mifare", description="Card technology type")
    expires_at: Optional[datetime] = Field(None, description="Optional card expiration date")
    notes: Optional[str] = Field(None, description="Additional notes about the card")
    
    class Config:
        json_schema_extra = {
            "example": {
                "card_uid": "04:3A:B2:C5:D8",
                "employee_id": 123,
                "card_type": "mifare",
                "expires_at": "2027-02-04T00:00:00Z"
            }
        }


class CardMappingUpdate(BaseModel):
    """Schema for updating existing card mappings."""
    card_type: Optional[str] = Field(None, description="Card technology type")
    expires_at: Optional[datetime] = Field(None, description="Card expiration date")
    is_active: Optional[bool] = Field(None, description="Whether card is active")
    notes: Optional[str] = Field(None, description="Additional notes about the card")


class CardMappingResponse(BaseModel):
    """Schema for card mapping responses."""
    id: int
    card_uid: str
    employee_id: int
    card_type: str
    issued_at: datetime
    expires_at: Optional[datetime]
    is_active: bool
    last_used_at: Optional[datetime]
    usage_count: int
    notes: Optional[str]
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True


class CardDeactivationResponse(BaseModel):
    """Schema for card deactivation responses."""
    status: str = Field(..., description="Operation status")
    card_uid: str = Field(..., description="Card UID that was deactivated")
    action: str = Field(..., description="Action performed")
    
    class Config:
        json_schema_extra = {
            "example": {
                "status": "ok",
                "card_uid": "04:3A:B2:C5:D8",
                "action": "deactivated"
            }
        }


class EmployeeCardLookupResponse(BaseModel):
    """Schema for employee card lookup responses."""
    employee_id: int
    card_uid: str
    card_type: str
    issued_at: datetime
    expires_at: Optional[datetime]
    last_used_at: Optional[datetime]
    usage_count: int
    
    class Config:
        from_attributes = True


class CardLookupResponse(BaseModel):
    """Schema for card lookup responses used in access control decisions."""
    status: str = Field(..., description="Lookup status: found, not_found, expired, inactive")
    card_uid: str = Field(..., description="RFID card UID")
    employee_id: Optional[int] = Field(None, description="Associated employee ID")
    card_type: Optional[str] = Field(None, description="Card technology type")
    access_levels: Optional[list] = Field(None, description="Access levels granted")
    is_active: Optional[bool] = Field(None, description="Whether card is currently active")
    expires_at: Optional[datetime] = Field(None, description="Card expiration date")
    
    class Config:
        json_schema_extra = {
            "example": {
                "status": "found",
                "card_uid": "04:3A:B2:C5:D8",
                "employee_id": 123,
                "card_type": "standard",
                "access_levels": ["entry", "exit"],
                "is_active": True,
                "expires_at": "2027-02-04T00:00:00Z"
            }
        }