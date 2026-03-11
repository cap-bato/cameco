from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, update, and_, literal_column
from sqlalchemy.exc import IntegrityError
from app.database import get_db
from app.models.rfid_device import RFIDDevice
from app.schemas.device import DeviceCreate, DeviceUpdate, DeviceResponse, DeviceHeartbeat
from app.services.notification_service import notification_service
from typing import List, Optional
from datetime import datetime, timezone
import logging

router = APIRouter(prefix="/api/devices", tags=["Devices"])
logger = logging.getLogger(__name__)


@router.post("/", response_model=DeviceResponse, status_code=status.HTTP_201_CREATED)
async def register_device(
    device: DeviceCreate,
    db: AsyncSession = Depends(get_db)
):
    """
    Register a new RFID device.
    
    Request Body:
    {
        "device_id": "GATE-01",
        "name": "Main Entrance Gate",
        "location": "Building A - Main Entrance",
        "device_type": "entry_gate",
        "status": "active",
        "configuration": {
            "read_range": 10,
            "security_level": "high"
        }
    }
    """
    try:
        # Check if device already exists
        stmt = select(RFIDDevice).where(RFIDDevice.device_id == device.device_id)
        existing_device = await db.execute(stmt)
        if existing_device.scalar_one_or_none():
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail=f"Device with ID '{device.device_id}' already exists"
            )
        
        # Create new device
        new_device = RFIDDevice(
            device_id=device.device_id,
            device_name=device.name,
            location=device.location,
            device_type=device.device_type,
            config_json=device.configuration,
            created_at=datetime.now(timezone.utc),
            updated_at=datetime.now(timezone.utc)
        )
        
        db.add(new_device)
        await db.commit()
        await db.refresh(new_device)
        
        return DeviceResponse.model_validate(new_device)
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except IntegrityError as e:
        await db.rollback()
        logger.error(f"Database integrity error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Device registration failed due to database constraints"
        )
    except Exception as e:
        await db.rollback()
        logger.error(f"Error registering device: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/", response_model=List[DeviceResponse])
async def get_devices(
    status: Optional[str] = None,
    device_type: Optional[str] = None,
    db: AsyncSession = Depends(get_db)
):
    """
    Get list of all registered RFID devices with optional filtering.
    
    Query Parameters:
    - status: Filter by device status (active, inactive, maintenance)
    - device_type: Filter by device type (entry_gate, exit_gate, mobile_reader)
    """
    try:
        stmt = select(RFIDDevice).order_by(RFIDDevice.device_id)
        
        # Apply filters
        if status:
            stmt = stmt.where(RFIDDevice.status == status)
        if device_type:
            stmt = stmt.where(RFIDDevice.device_type == device_type)
        
        result = await db.execute(stmt)
        devices = result.scalars().all()
        
        return [DeviceResponse.model_validate(device) for device in devices]
    
    except Exception as e:
        logger.error(f"Error retrieving devices: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/{device_id}", response_model=DeviceResponse)
async def get_device(
    device_id: str,
    db: AsyncSession = Depends(get_db)
):
    """Get details of a specific RFID device."""
    try:
        stmt = select(RFIDDevice).where(RFIDDevice.device_id == device_id)
        result = await db.execute(stmt)
        device = result.scalar_one_or_none()
        
        if not device:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Device '{device_id}' not found"
            )
        
        return DeviceResponse.model_validate(device)
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error retrieving device {device_id}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.put("/{device_id}", response_model=DeviceResponse)
async def update_device(
    device_id: str,
    device_update: DeviceUpdate,
    db: AsyncSession = Depends(get_db)
):
    """
    Update an existing RFID device.
    
    Request Body:
    {
        "name": "Updated Gate Name",
        "location": "Updated Location",
        "status": "maintenance",
        "configuration": {
            "read_range": 15,
            "security_level": "medium"
        }
    }
    """
    try:
        # Check if device exists
        stmt = select(RFIDDevice).where(RFIDDevice.device_id == device_id)
        result = await db.execute(stmt)
        device = result.scalar_one_or_none()
        
        if not device:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Device '{device_id}' not found"
            )
        
        # Update device
        update_data = device_update.model_dump(exclude_unset=True)
        if update_data:
            update_data['updated_at'] = datetime.now(timezone.utc)
            
            stmt = update(RFIDDevice).where(
                RFIDDevice.device_id == device_id
            ).values(**update_data)
            
            await db.execute(stmt)
            await db.commit()
            await db.refresh(device)
        
        return DeviceResponse.model_validate(device)
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        await db.rollback()
        logger.error(f"Error updating device {device_id}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.delete("/{device_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_device(
    device_id: str,
    db: AsyncSession = Depends(get_db)
):
    """Delete an RFID device registration."""
    try:
        # Check if device exists
        stmt = select(RFIDDevice).where(RFIDDevice.device_id == device_id)
        result = await db.execute(stmt)
        device = result.scalar_one_or_none()
        
        if not device:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Device '{device_id}' not found"
            )
        
        await db.delete(device)
        await db.commit()
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        await db.rollback()
        logger.error(f"Error deleting device {device_id}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.post("/{device_id}/heartbeat", status_code=status.HTTP_200_OK)
async def device_heartbeat(
    device_id: str,
    heartbeat: DeviceHeartbeat,
    db: AsyncSession = Depends(get_db)
):
    """
    Receive heartbeat from RFID device to track online status.
    
    Request Body:
    {
        "timestamp": "2026-02-04T08:05:23Z",
        "status": "online",
        "metadata": {
            "signal_strength": 85,
            "battery_level": 92
        }
    }
    """
    try:
        # Check if device exists
        stmt = select(RFIDDevice).where(RFIDDevice.device_id == device_id)
        result = await db.execute(stmt)
        device = result.scalar_one_or_none()
        
        if not device:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Device '{device_id}' not found"
            )
        
        # Update last heartbeat
        stmt = update(RFIDDevice).where(
            RFIDDevice.device_id == device_id
        ).values(
            last_heartbeat_at=heartbeat.timestamp,
            updated_at=datetime.now(timezone.utc)
        )
        
        await db.execute(stmt)
        await db.commit()
        
        # Send real-time notification for device heartbeat
        try:
            await notification_service.notify_device_heartbeat(device_id, heartbeat.dict())
        except Exception as notify_error:
            logger.warning(f"Failed to send device heartbeat notification: {notify_error}")
        
        return {"message": f"Heartbeat received for device {device_id}"}
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        await db.rollback()
        logger.error(f"Error processing heartbeat for device {device_id}: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/status/online", response_model=List[DeviceResponse])
async def get_online_devices(
    db: AsyncSession = Depends(get_db)
):
    """
    Get list of devices that are currently online.
    A device is considered online if it has sent a heartbeat within the last 5 minutes.
    """
    try:
        # Calculate 5 minutes ago
        five_minutes_ago = datetime.now(timezone.utc).timestamp() - 300  # 5 minutes in seconds
        
        stmt = select(RFIDDevice).where(
            and_(
                RFIDDevice.last_heartbeat_at.isnot(None),
                literal_column("EXTRACT(EPOCH FROM last_heartbeat_at)") > five_minutes_ago
            )
        ).order_by(RFIDDevice.device_id)
        
        result = await db.execute(stmt)
        devices = result.scalars().all()
        
        return [DeviceResponse.model_validate(device) for device in devices]
    
    except Exception as e:
        logger.error(f"Error retrieving online devices: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )
