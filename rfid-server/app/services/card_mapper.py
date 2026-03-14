from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import update
from app.models.rfid_card_mapping import RFIDCardMapping
from datetime import datetime, timezone
from typing import Optional
import logging

logger = logging.getLogger(__name__)


class CardMapperService:
    """
    Service for managing RFID card to employee mappings.
    
    Handles:
    - Card lookup by UID to get employee ID
    - Card registration for employees
    - Card deactivation (lost/stolen cards)  
    - Usage tracking and statistics
    """
    
    def __init__(self, db: AsyncSession):
        self.db = db
    
    async def get_employee_id_by_card(self, card_uid: str) -> Optional[int]:
        """
        Lookup employee_id by RFID card UID.
        Returns None if card not found or inactive.
        
        Args:
            card_uid: The RFID card UID to lookup
            
        Returns:
            Employee ID if found and active, None otherwise
        """
        try:
            result = await self.db.execute(
                select(RFIDCardMapping.employee_id)
                .where(
                    RFIDCardMapping.card_uid == card_uid,
                    RFIDCardMapping.is_active == True
                )
            )
            employee_id = result.scalar_one_or_none()
            
            if employee_id:
                # Update last_used_at and usage_count
                await self.db.execute(
                    update(RFIDCardMapping)
                    .where(
                        RFIDCardMapping.card_uid == card_uid,
                        RFIDCardMapping.is_active == True
                    )
                    .values(
                        last_used_at=datetime.now(timezone.utc),
                        usage_count=RFIDCardMapping.usage_count + 1,
                        updated_at=datetime.now(timezone.utc)
                    )
                )
                await self.db.commit()
                
                logger.debug(f"Card {card_uid} mapped to employee {employee_id}")
            else:
                logger.warning(f"Card {card_uid} not found or inactive")
            
            return employee_id
            
        except Exception as e:
            logger.error(f"Error looking up card {card_uid}: {str(e)}")
            await self.db.rollback()
            raise
    
    async def register_card(
        self, 
        card_uid: str, 
        employee_id: int, 
        card_type: str = "mifare",
        expires_at: Optional[datetime] = None,
        notes: Optional[str] = None
    ) -> RFIDCardMapping:
        """
        Register a new RFID card for an employee.
        Deactivates any existing active card for the employee.
        
        Args:
            card_uid: The RFID card UID to register
            employee_id: The employee ID to associate with the card
            card_type: Card technology type (default: mifare)
            expires_at: Optional expiration date for the card
            notes: Optional notes about the card
            
        Returns:
            The created card mapping record
        """
        try:
            # First, deactivate any existing active cards for this employee
            await self.db.execute(
                update(RFIDCardMapping)
                .where(
                    RFIDCardMapping.employee_id == employee_id,
                    RFIDCardMapping.is_active == True
                )
                .values(
                    is_active=False,
                    updated_at=datetime.now(timezone.utc)
                )
            )
            
            # Create new mapping
            new_mapping = RFIDCardMapping(
                card_uid=card_uid,
                employee_id=employee_id,
                card_type=card_type,
                issued_at=datetime.now(timezone.utc),
                expires_at=expires_at,
                is_active=True,
                usage_count=0,
                notes=notes
            )
            
            self.db.add(new_mapping)
            await self.db.commit()
            await self.db.refresh(new_mapping)
            
            logger.info(f"Registered card {card_uid} for employee {employee_id}")
            return new_mapping
            
        except Exception as e:
            logger.error(f"Error registering card {card_uid} for employee {employee_id}: {str(e)}")
            await self.db.rollback()
            raise
    
    async def deactivate_card(self, card_uid: str) -> bool:
        """
        Deactivate a card (lost, stolen, replaced).
        
        Args:
            card_uid: The card UID to deactivate
            
        Returns:
            True if card was found and deactivated, False otherwise
        """
        try:
            result = await self.db.execute(
                update(RFIDCardMapping)
                .where(RFIDCardMapping.card_uid == card_uid)
                .values(
                    is_active=False,
                    updated_at=datetime.now(timezone.utc)
                )
            )
            await self.db.commit()
            
            success = result.rowcount > 0
            if success:
                logger.info(f"Deactivated card {card_uid}")
            else:
                logger.warning(f"Card {card_uid} not found for deactivation")
                
            return success
            
        except Exception as e:
            logger.error(f"Error deactivating card {card_uid}: {str(e)}")
            await self.db.rollback()
            raise
    
    async def get_card_by_employee(self, employee_id: int) -> Optional[RFIDCardMapping]:
        """
        Get the active RFID card for an employee.
        
        Args:
            employee_id: The employee ID to lookup
            
        Returns:
            The active card mapping if found, None otherwise
        """
        try:
            result = await self.db.execute(
                select(RFIDCardMapping)
                .where(
                    RFIDCardMapping.employee_id == employee_id,
                    RFIDCardMapping.is_active == True
                )
            )
            mapping = result.scalar_one_or_none()
            
            if mapping:
                logger.debug(f"Found active card {mapping.card_uid} for employee {employee_id}")
            else:
                logger.debug(f"No active card found for employee {employee_id}")
                
            return mapping
            
        except Exception as e:
            logger.error(f"Error looking up card for employee {employee_id}: {str(e)}")
            raise
    
    async def get_card_details(self, card_uid: str) -> Optional[RFIDCardMapping]:
        """
        Get detailed information about a card.
        
        Args:
            card_uid: The card UID to lookup
            
        Returns:
            The card mapping record if found, None otherwise
        """
        try:
            result = await self.db.execute(
                select(RFIDCardMapping)
                .where(RFIDCardMapping.card_uid == card_uid)
            )
            mapping = result.scalar_one_or_none()
            
            return mapping
            
        except Exception as e:
            logger.error(f"Error getting card details for {card_uid}: {str(e)}")
            raise
    
    async def is_card_expired(self, card_uid: str) -> bool:
        """
        Check if a card is expired.
        
        Args:
            card_uid: The card UID to check
            
        Returns:
            True if card is expired, False otherwise
        """
        try:
            mapping = await self.get_card_details(card_uid)
            
            if not mapping:
                return True  # No card found = expired
                
            if not mapping.is_active:
                return True  # Inactive = expired
                
            if mapping.expires_at and mapping.expires_at < datetime.now(timezone.utc):
                return True  # Past expiration date = expired
                
            return False
            
        except Exception as e:
            logger.error(f"Error checking expiration for card {card_uid}: {str(e)}")
            return True  # Err on side of caution - treat as expired
