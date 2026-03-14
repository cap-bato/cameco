from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import get_db
from app.services.card_mapper import CardMapperService
from app.schemas.mapping import CardMappingCreate, CardMappingUpdate, CardMappingResponse, CardLookupResponse
from typing import List, Optional
import logging

router = APIRouter(prefix="/api/mappings", tags=["Card Mappings"])
logger = logging.getLogger(__name__)


@router.post("/", response_model=CardMappingResponse, status_code=status.HTTP_201_CREATED)
async def register_card(
    mapping: CardMappingCreate,
    db: AsyncSession = Depends(get_db)
):
    """
    Register a new RFID card mapping.
    
    Request Body:
    {
        "card_uid": "04:3A:B2:C5:D8",
        "employee_id": "EMP001",
        "card_type": "standard",
        "access_levels": ["entry", "exit"],
        "expiry_date": "2024-12-31T23:59:59Z",
        "is_active": true
    }
    """
    try:
        mapper = CardMapperService(db)
        result = await mapper.register_card(mapping)
        
        if result["status"] == "error":
            status_code = status.HTTP_400_BAD_REQUEST
            if "already exists" in result["message"]:
                status_code = status.HTTP_409_CONFLICT
            raise HTTPException(
                status_code=status_code,
                detail=result["message"]
            )
        
        return CardMappingResponse(**result["mapping"])
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error registering card: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/", response_model=List[CardMappingResponse])
async def get_card_mappings(
    employee_id: Optional[str] = None,
    card_type: Optional[str] = None,
    is_active: Optional[bool] = None,
    db: AsyncSession = Depends(get_db)
):
    """
    Get RFID card mappings with optional filtering.
    
    Query Parameters:
    - employee_id: Filter by employee ID
    - card_type: Filter by card type (standard, admin, temp, visitor)
    - is_active: Filter by active status
    """
    try:
        mapper = CardMapperService(db)
        mappings = await mapper.get_mappings(employee_id, card_type, is_active)
        return mappings
    
    except Exception as e:
        logger.error(f"Error retrieving card mappings: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/{card_uid}", response_model=CardMappingResponse)
async def get_card_mapping(
    card_uid: str,
    db: AsyncSession = Depends(get_db)
):
    """Get details of a specific RFID card mapping."""
    try:
        mapper = CardMapperService(db)
        mapping = await mapper.get_mapping_by_uid(card_uid)
        
        if not mapping:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Card mapping for UID '{card_uid}' not found"
            )
        
        return mapping
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error retrieving card mapping {card_uid}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.put("/{card_uid}", response_model=CardMappingResponse)
async def update_card_mapping(
    card_uid: str,
    mapping_update: CardMappingUpdate,
    db: AsyncSession = Depends(get_db)
):
    """
    Update an existing RFID card mapping.
    
    Request Body:
    {
        "card_type": "admin",
        "access_levels": ["entry", "exit", "admin"],
        "expiry_date": "2025-12-31T23:59:59Z",
        "is_active": true
    }
    """
    try:
        mapper = CardMapperService(db)
        result = await mapper.update_mapping(card_uid, mapping_update)
        
        if result["status"] == "error":
            status_code = status.HTTP_404_NOT_FOUND if "not found" in result["message"] else status.HTTP_400_BAD_REQUEST
            raise HTTPException(
                status_code=status_code,
                detail=result["message"]
            )
        
        return CardMappingResponse(**result["mapping"])
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error updating card mapping {card_uid}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.delete("/{card_uid}", status_code=status.HTTP_200_OK)
async def deactivate_card(
    card_uid: str,
    db: AsyncSession = Depends(get_db)
):
    """
    Deactivate an RFID card (soft delete - sets is_active to false).
    The card mapping is preserved for historical purposes.
    """
    try:
        mapper = CardMapperService(db)
        result = await mapper.deactivate_card(card_uid)
        
        if result["status"] == "error":
            status_code = status.HTTP_404_NOT_FOUND if "not found" in result["message"] else status.HTTP_400_BAD_REQUEST
            raise HTTPException(
                status_code=status_code,
                detail=result["message"]
            )
        
        return {"message": f"Card {card_uid} deactivated successfully"}
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error deactivating card {card_uid}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/lookup/{card_uid}", response_model=CardLookupResponse)
async def lookup_card(
    card_uid: str,
    db: AsyncSession = Depends(get_db)
):
    """
    Lookup RFID card information for access control decisions.
    Returns card details, employee info, and access levels.
    """
    try:
        mapper = CardMapperService(db)
        lookup_result = await mapper.lookup_card(card_uid)
        
        if lookup_result["status"] == "not_found":
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Card '{card_uid}' not found"
            )
        
        return CardLookupResponse(**lookup_result)
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error looking up card {card_uid}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/employee/{employee_id}/cards", response_model=List[CardMappingResponse])
async def get_employee_cards(
    employee_id: str,
    include_inactive: bool = False,
    db: AsyncSession = Depends(get_db)
):
    """
    Get all RFID cards associated with an employee.
    
    Query Parameters:
    - include_inactive: Include inactive/deactivated cards
    """
    try:
        mapper = CardMapperService(db)
        cards = await mapper.get_employee_cards(employee_id, include_inactive)
        return cards
    
    except Exception as e:
        logger.error(f"Error retrieving cards for employee {employee_id}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )
