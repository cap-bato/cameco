from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Integer, Text
from sqlalchemy.sql import func
from app.database import Base


class RFIDCardMapping(Base):
    """
    Map read-only RFID card UIDs to employee IDs.
    
    Business Rules:
    - One employee can have multiple cards (old/new), but only one active
    - Card replacement workflow: deactivate old, issue new
    - Track usage for audit and card lifecycle management
    """
    __tablename__ = "rfid_card_mappings"
    
    id = Column(BigInteger, primary_key=True, index=True)
    card_uid = Column(String(255), unique=True, nullable=False, index=True, 
                     comment="RFID card UID (e.g., '04:3A:B2:C5:D8')")
    employee_id = Column(BigInteger, nullable=False, index=True, 
                        comment="Foreign key to employees table (constraint to be added after Laravel migration)")    
    card_type = Column(String(50), default="mifare", 
                      comment="Card technology (mifare, desfire, etc.)")
    issued_at = Column(DateTime(timezone=True), nullable=False,
                      comment="When card was issued to employee")
    expires_at = Column(DateTime(timezone=True), 
                       comment="Optional expiration date")
    is_active = Column(Boolean, default=True, index=True,
                      comment="Only one active card per employee")
    last_used_at = Column(DateTime(timezone=True),
                         comment="Last time card was used for scanning")
    usage_count = Column(Integer, default=0,
                        comment="Number of times card has been used")
    notes = Column(Text, comment="Additional notes about the card")
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    __table_args__ = (
        # Note: Partial unique constraint (only one active card per employee) 
        # will be created via raw SQL in migration since SQLAlchemy doesn't 
        # directly support WHERE clause in UniqueConstraint
        # SQL: CREATE UNIQUE INDEX uk_employee_active ON rfid_card_mappings(employee_id) WHERE is_active = true;
    )


# Index definitions for performance
# CREATE INDEX idx_card_uid_active ON rfid_card_mappings(card_uid) WHERE is_active = TRUE;
# CREATE INDEX idx_employee_id ON rfid_card_mappings(employee_id);  
# CREATE INDEX idx_last_used ON rfid_card_mappings(last_used_at);