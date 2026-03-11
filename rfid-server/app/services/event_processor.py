from datetime import datetime, timezone
from typing import Dict, Any, Optional, List
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import func, text
from app.models.rfid_ledger import RFIDLedger
from app.services.card_mapper import CardMapperService
from app.services.hash_chain import HashChainService
from app.services.deduplicator import DeduplicationService
from app.schemas.rfid_event import RFIDEventCreate, RFIDEventResponse, RFIDEventBatchResponse
from app.services.notification_service import notification_service
import logging

logger = logging.getLogger(__name__)


class EventProcessorService:
    """
    Main event processing service that orchestrates the complete RFID event pipeline.
    
    Pipeline:
    1. Map RFID card UID to employee ID via CardMapperService
    2. Check for duplicates via DeduplicationService
    3. Generate next sequence ID
    4. Compute hash chain via HashChainService
    5. Write event to immutable RFIDLedger
    6. Update deduplication cache
    
    This service ensures data integrity, prevents duplicates, and maintains
    the tamper-resistant hash chain for audit purposes.
    """
    
    def __init__(self, db: AsyncSession):
        self.db = db
        self.card_mapper = CardMapperService(db)
        self.hash_chain = HashChainService(db)
        self.deduplicator = DeduplicationService(db)
    
    async def process_rfid_tap(self, event: RFIDEventCreate) -> Dict[str, Any]:
        """
        Main event processing pipeline:
        1. Lookup employee_id via card_uid
        2. Check for duplicates
        3. Generate hash chain
        4. Write to ledger
        5. Update deduplication cache
        
        Args:
            event: The RFID event to process
            
        Returns:
            Dictionary with processing results
        """
        try:
            # Step 1: Map card to employee
            logger.debug(f"Processing RFID tap: card={event.card_uid}, device={event.device_id}, type={event.event_type}")
            
            employee_id = await self.card_mapper.get_employee_id_by_card(event.card_uid)
            
            if not employee_id:
                logger.warning(f"Unknown RFID card: {event.card_uid} (device: {event.device_id})")
                return {
                    "status": "rejected",
                    "reason": "unknown_card",
                    "card_uid": event.card_uid,
                    "device_id": event.device_id,
                    "timestamp": event.timestamp.isoformat()
                }
            
            # Step 2: Check duplicates
            is_duplicate = await self.deduplicator.is_duplicate(
                employee_id, event.device_id, event.event_type, event.timestamp
            )
            
            if is_duplicate:
                logger.info(f"Duplicate event ignored: employee={employee_id}, device={event.device_id}, type={event.event_type}")
                return {
                    "status": "ignored",
                    "reason": "duplicate",
                    "employee_id": employee_id,
                    "device_id": event.device_id,
                    "event_type": event.event_type,
                    "timestamp": event.timestamp.isoformat()
                }
            
            # Step 3: Get next sequence ID using PostgreSQL sequence
            next_sequence = await self._get_next_sequence_id()
            
            # Step 4: Generate hash chain
            payload = self.hash_chain.create_payload(
                sequence_id=next_sequence,
                employee_rfid=event.card_uid,
                device_id=event.device_id,
                scan_timestamp=event.timestamp.isoformat(),
                event_type=event.event_type
            )
            
            hash_value = await self.hash_chain.generate_next_hash(payload)
            
            # Step 5: Write to ledger
            # Convert event to JSON-serializable format for raw_payload
            raw_payload = event.dict()
            raw_payload['timestamp'] = event.timestamp.isoformat()  # Convert datetime to ISO string
            
            ledger_entry = RFIDLedger(
                sequence_id=next_sequence,
                employee_rfid=event.card_uid,
                device_id=event.device_id,
                scan_timestamp=event.timestamp,
                event_type=event.event_type,
                raw_payload=raw_payload,
                hash_chain=hash_value,
                device_signature=event.device_signature,
                processed=False
            )
            
            self.db.add(ledger_entry)
            await self.db.commit()
            await self.db.refresh(ledger_entry)
            
            # Step 6: Update deduplication cache
            await self.deduplicator.add_to_cache(
                employee_id, event.device_id, event.event_type,
                event.timestamp, next_sequence
            )
            
            logger.info(f"Event processed successfully: seq={next_sequence}, employee={employee_id}, device={event.device_id}, type={event.event_type}")
            
            result = {
                "status": "success",
                "sequence_id": next_sequence,
                "employee_id": employee_id,
                "hash": hash_value,
                "timestamp": event.timestamp.isoformat()
            }
            
            # Send real-time notification
            try:
                await notification_service.notify_rfid_event(result, event.dict())
            except Exception as notify_error:
                logger.warning(f"Failed to send real-time notification: {notify_error}")
            
            return result
            
        except Exception as e:
            logger.error(f"Error processing RFID event: {str(e)}")
            await self.db.rollback()
            return {
                "status": "error",
                "reason": f"processing_error: {str(e)}",
                "card_uid": event.card_uid,
                "device_id": event.device_id,
                "timestamp": event.timestamp.isoformat()
            }
    
    async def process_rfid_batch(self, events: List[RFIDEventCreate]) -> RFIDEventBatchResponse:
        """
        Process a batch of RFID events (useful for offline device catch-up).
        
        Each event is processed individually to maintain proper sequence ordering
        and hash chain integrity.
        
        Args:
            events: List of RFID events to process
            
        Returns:
            Batch processing results
        """
        results = []
        counts = {
            "processed": 0,
            "ignored": 0,
            "rejected": 0,
            "errors": 0
        }
        
        logger.info(f"Processing batch of {len(events)} RFID events")
        
        for i, event in enumerate(events):
            try:
                result = await self.process_rfid_tap(event)
                results.append(result)
                
                # Count results by status
                status = result.get("status", "error")
                if status == "success":
                    counts["processed"] += 1
                elif status == "ignored":
                    counts["ignored"] += 1
                elif status == "rejected":
                    counts["rejected"] += 1
                else:
                    counts["errors"] += 1
                
                # Log progress for large batches
                if (i + 1) % 100 == 0:
                    logger.info(f"Batch progress: {i + 1}/{len(events)} events processed")
                    
            except Exception as e:
                logger.error(f"Batch event {i} error: {str(e)}")
                results.append({
                    "status": "error",
                    "reason": f"batch_processing_error: {str(e)}",
                    "card_uid": getattr(event, 'card_uid', 'unknown'),
                    "device_id": getattr(event, 'device_id', 'unknown')
                })
                counts["errors"] += 1
        
        logger.info(f"Batch completed: {counts['processed']} processed, {counts['ignored']} ignored, {counts['rejected']} rejected, {counts['errors']} errors")
        
        return RFIDEventBatchResponse(
            total=len(events),
            processed=counts["processed"],
            ignored=counts["ignored"],
            rejected=counts["rejected"],
            errors=counts["errors"],
            results=results
        )
    
    async def _get_next_sequence_id(self) -> int:
        """
        Get the next sequence ID from PostgreSQL sequence.
        
        Uses the rfid_ledger_sequence_seq sequence to ensure proper ordering
        even under high concurrency.
        
        Returns:
            Next sequence ID
        """
        try:
            # Use PostgreSQL's nextval() function to get next sequence ID
            result = await self.db.execute(
                text("SELECT nextval('rfid_ledger_sequence_seq')")
            )
            sequence_id = result.scalar()
            
            logger.debug(f"Generated sequence ID: {sequence_id}")
            return sequence_id
            
        except Exception as e:
            logger.error(f"Error getting next sequence ID: {str(e)}")
            raise
    
    async def get_ledger_stats(self) -> Dict[str, Any]:
        """
        Get statistics about the RFID ledger.
        
        Returns:
            Dictionary with ledger statistics
        """
        try:
            # Total events
            total_result = await self.db.execute(
                select(func.count(RFIDLedger.id))
            )
            total_events = total_result.scalar_one()
            
            # Processed events
            processed_result = await self.db.execute(
                select(func.count(RFIDLedger.id))
                .where(RFIDLedger.processed == True)
            )
            processed_events = processed_result.scalar_one()
            
            # Latest sequence ID
            latest_result = await self.db.execute(
                select(func.max(RFIDLedger.sequence_id))
            )
            latest_sequence = latest_result.scalar_one() or 0
            
            # Events by type
            type_result = await self.db.execute(
                select(RFIDLedger.event_type, func.count(RFIDLedger.id))
                .group_by(RFIDLedger.event_type)
            )
            events_by_type = dict(type_result.fetchall())
            
            # Recent activity (last 24 hours)
            recent_result = await self.db.execute(
                select(func.count(RFIDLedger.id))
                .where(RFIDLedger.created_at > func.now() - text("interval '24 hours'"))
            )
            recent_events = recent_result.scalar_one()
            
            return {
                "total_events": total_events,
                "processed_events": processed_events,
                "unprocessed_events": total_events - processed_events,
                "latest_sequence_id": latest_sequence,
                "events_by_type": events_by_type,
                "recent_events_24h": recent_events,
                "last_updated": datetime.now(timezone.utc).isoformat()
            }
            
        except Exception as e:
            logger.error(f"Error getting ledger stats: {str(e)}")
            return {
                "total_events": 0,
                "processed_events": 0,
                "unprocessed_events": 0,
                "latest_sequence_id": 0,
                "events_by_type": {},
                "recent_events_24h": 0,
                "last_updated": datetime.now(timezone.utc).isoformat(),
                "error": str(e)
            }
    
    async def get_latest_events(self, limit: int = 10) -> List[Dict[str, Any]]:
        """
        Get the most recent RFID events from the ledger.
        
        Args:
            limit: Maximum number of events to return
            
        Returns:
            List of recent events with basic details
        """
        try:
            result = await self.db.execute(
                select(RFIDLedger)
                .order_by(RFIDLedger.sequence_id.desc())
                .limit(limit)
            )
            events = result.scalars().all()
            
            return [
                {
                    "sequence_id": event.sequence_id,
                    "employee_rfid": event.employee_rfid,
                    "device_id": event.device_id,
                    "event_type": event.event_type,
                    "scan_timestamp": event.scan_timestamp.isoformat(),
                    "processed": event.processed,
                    "created_at": event.created_at.isoformat()
                }
                for event in events
            ]
            
        except Exception as e:
            logger.error(f"Error getting latest events: {str(e)}")
            return []
