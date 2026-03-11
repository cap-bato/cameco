from fastapi import APIRouter, Depends, HTTPException, status, Request
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import get_db
from app.schemas.rfid_event import RFIDEventCreate, RFIDEventResponse, RFIDEventBatchRequest, RFIDEventBatchResponse
from app.services.event_processor import EventProcessorService
from app.services.signature_service import verify_device_signature
from app.auth import RequireEventSubmission, get_client_ip, get_user_agent
from app.models.security import APIKey
from app.config import settings
from typing import List
import logging

router = APIRouter(prefix="/api/events", tags=["Events"])
logger = logging.getLogger(__name__)


@router.post("/", response_model=RFIDEventResponse, status_code=status.HTTP_201_CREATED)
async def receive_rfid_tap(
    event: RFIDEventCreate,
    request: Request,
    db: AsyncSession = Depends(get_db),
    api_key: APIKey = Depends(RequireEventSubmission)
):
    """
    Receive RFID tap event from card reader.
    
    Requires API key with 'submit_events' permission.
    
    Request Body:
    {
        "card_uid": "04:3A:B2:C5:D8",
        "device_id": "GATE-01",
        "event_type": "time_in",
        "timestamp": "2026-02-04T08:05:23Z",
        "device_signature": "optional_signature_string"
    }
    """
    try:
        # Verify device signature if required and provided
        if settings.device_signature_verification and event.device_signature:
            event_data = event.model_dump()
            if not verify_device_signature(event_data, event.device_signature, event.device_id):
                logger.warning(f"Invalid device signature for device: {event.device_id}")
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="Invalid device signature"
                )
        
        processor = EventProcessorService(db)
        result = await processor.process_rfid_tap(event)
        
        if result["status"] == "rejected":
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Event rejected: {result['reason']}"
            )
        
        # Log successful event submission
        client_ip = get_client_ip(request)
        logger.info(
            f"RFID event submitted by {api_key.key_prefix} from {client_ip}: "
            f"card={event.card_uid}, device={event.device_id}, type={event.event_type}"
        )
        
        return RFIDEventResponse(**result)
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error processing event: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.post("/batch", response_model=RFIDEventBatchResponse, status_code=status.HTTP_201_CREATED)
async def receive_rfid_batch(
    batch_request: RFIDEventBatchRequest,
    request: Request,
    db: AsyncSession = Depends(get_db),
    api_key: APIKey = Depends(RequireEventSubmission)
):
    """
    Receive batch of RFID events (offline device catch-up).
    
    Requires API key with 'submit_events' permission.
    
    Request Body:
    {
        "events": [
            {
                "card_uid": "04:3A:B2:C5:D8",
                "device_id": "GATE-01", 
                "event_type": "time_in",
                "timestamp": "2026-02-04T08:05:23Z"
            },
            ...
        ]
    }
    """
    try:
        # Verify signatures for all events if required
        if settings.device_signature_verification:
            for event in batch_request.events:
                if event.device_signature:
                    event_data = event.model_dump()
                    if not verify_device_signature(event_data, event.device_signature, event.device_id):
                        logger.warning(f"Invalid device signature for device: {event.device_id}")
                        # Continue processing other events, but log the error
        
        processor = EventProcessorService(db)
        batch_result = await processor.process_rfid_batch(batch_request.events)
        
        # Log successful batch submission
        client_ip = get_client_ip(request)
        logger.info(
            f"RFID batch submitted by {api_key.key_prefix} from {client_ip}: "
            f"{len(batch_request.events)} events"
        )
        
        return batch_result
    
    except Exception as e:
        logger.error(f"Error processing batch: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/stats")
async def get_ledger_stats(
    db: AsyncSession = Depends(get_db),
    api_key: APIKey = Depends(RequireEventSubmission)
):
    """
    Get statistics about the RFID ledger.
    
    Requires API key with 'submit_events' permission.
    """
    try:
        processor = EventProcessorService(db)
        stats = await processor.get_ledger_stats()
        return stats
    
    except Exception as e:
        logger.error(f"Error getting ledger stats: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/latest")
async def get_latest_events(
    limit: int = 10,
    db: AsyncSession = Depends(get_db),
    api_key: APIKey = Depends(RequireEventSubmission)
):
    """
    Get the most recent RFID events from the ledger.
    
    Requires API key with 'submit_events' permission.
    """
    try:
        if limit > 100:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Limit cannot exceed 100"
            )
        
        processor = EventProcessorService(db)
        events = await processor.get_latest_events(limit)
        return {"events": events}
    
    except HTTPException:
        raise  # Re-raise HTTP exceptions
    except Exception as e:
        logger.error(f"Error getting latest events: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )
