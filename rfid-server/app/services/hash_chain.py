import hashlib
import json
from typing import Optional, Dict, Any
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import desc
from app.models.rfid_ledger import RFIDLedger
from app.config import settings
import logging

logger = logging.getLogger(__name__)


class HashChainService:
    """
    Service for managing tamper-resistant hash chains in the RFID ledger.
    
    Implements a blockchain-like hash chain where each event's hash depends on:
    - The previous event's hash
    - The current event's payload data
    
    This creates an immutable audit trail where any tampering with historical
    records will be detected by hash validation.
    """
    
    def __init__(self, db: AsyncSession):
        self.db = db
        self.hash_algorithm = settings.hash_algorithm
        self.genesis_hash = settings.genesis_hash
    
    async def get_last_hash(self) -> str:
        """
        Get the hash of the last ledger entry.
        Returns GENESIS_HASH if ledger is empty.
        
        Returns:
            The hash value of the most recent ledger entry
        """
        try:
            result = await self.db.execute(
                select(RFIDLedger.hash_chain)
                .order_by(desc(RFIDLedger.sequence_id))
                .limit(1)
            )
            last_hash = result.scalar_one_or_none()
            
            if last_hash:
                logger.debug(f"Last hash retrieved: {last_hash[:16]}...")
                return last_hash
            else:
                logger.debug("No previous entries found, using genesis hash")
                return self.genesis_hash
                
        except Exception as e:
            logger.error(f"Error retrieving last hash: {str(e)}")
            raise
    
    def compute_hash(self, prev_hash: str, payload: Dict[str, Any]) -> str:
        """
        Compute SHA-256 hash: hash(prev_hash || payload_json).
        
        The payload is serialized to a deterministic JSON string to ensure
        the same input always produces the same hash.
        
        Args:
            prev_hash: Hash of the previous ledger entry
            payload: Event data to hash
            
        Returns:
            SHA-256 hash as hexadecimal string
        """
        try:
            # Serialize payload to deterministic JSON string
            # sort_keys=True ensures consistent ordering
            # separators=(',', ':') ensures no extra whitespace
            payload_str = json.dumps(payload, sort_keys=True, separators=(',', ':'))
            
            # Combine previous hash with payload
            combined = f"{prev_hash}{payload_str}"
            
            # Compute hash based on configured algorithm
            if self.hash_algorithm.lower() == "sha256":
                hash_obj = hashlib.sha256(combined.encode('utf-8'))
            elif self.hash_algorithm.lower() == "sha512":
                hash_obj = hashlib.sha512(combined.encode('utf-8'))
            else:
                # Default to SHA-256
                hash_obj = hashlib.sha256(combined.encode('utf-8'))
                
            hash_value = hash_obj.hexdigest()
            
            logger.debug(f"Computed hash: {hash_value[:16]}... for payload with {len(payload)} fields")
            return hash_value
            
        except Exception as e:
            logger.error(f"Error computing hash: {str(e)}")
            raise
    
    async def generate_next_hash(self, payload: Dict[str, Any]) -> str:
        """
        Generate hash for the next ledger entry.
        
        Gets the previous hash and computes the new hash based on the payload.
        
        Args:
            payload: Event data for the new ledger entry
            
        Returns:
            Generated hash for the new entry
        """
        try:
            prev_hash = await self.get_last_hash()
            new_hash = self.compute_hash(prev_hash, payload)
            
            logger.debug(f"Generated new hash: {new_hash[:16]}... from prev: {prev_hash[:16]}...")
            return new_hash
            
        except Exception as e:
            logger.error(f"Error generating next hash: {str(e)}")
            raise
    
    def verify_hash(self, prev_hash: str, payload: Dict[str, Any], claimed_hash: str) -> bool:
        """
        Verify hash integrity by recomputing and comparing.
        
        Args:
            prev_hash: Hash of the previous entry
            payload: Event payload data
            claimed_hash: The hash claim to verify
            
        Returns:
            True if hash is valid, False if tampered
        """
        try:
            computed_hash = self.compute_hash(prev_hash, payload)
            is_valid = computed_hash == claimed_hash
            
            if is_valid:
                logger.debug(f"Hash verification passed: {claimed_hash[:16]}...")
            else:
                logger.warning(f"Hash verification FAILED! Expected: {computed_hash[:16]}..., Got: {claimed_hash[:16]}...")
                
            return is_valid
            
        except Exception as e:
            logger.error(f"Error verifying hash: {str(e)}")
            return False
    
    async def verify_chain_integrity(self, start_sequence: Optional[int] = None, end_sequence: Optional[int] = None) -> Dict[str, Any]:
        """
        Verify the integrity of the hash chain for a range of entries.
        
        Args:
            start_sequence: Starting sequence ID (None for beginning)
            end_sequence: Ending sequence ID (None for end)
            
        Returns:
            Dictionary with verification results and any issues found
        """
        try:
            # Build query for the range
            query = select(RFIDLedger).order_by(RFIDLedger.sequence_id)
            
            if start_sequence is not None:
                query = query.where(RFIDLedger.sequence_id >= start_sequence)
            if end_sequence is not None:
                query = query.where(RFIDLedger.sequence_id <= end_sequence)
                
            result = await self.db.execute(query)
            entries = result.scalars().all()
            
            if not entries:
                return {
                    "valid": True,
                    "entries_checked": 0,
                    "issues": [],
                    "message": "No entries found in specified range"
                }
            
            issues = []
            prev_hash = self.genesis_hash
            
            # Check each entry in sequence
            for i, entry in enumerate(entries):
                # Reconstruct payload for hash verification
                payload = {
                    "sequence_id": entry.sequence_id,
                    "employee_rfid": entry.employee_rfid,
                    "device_id": entry.device_id,
                    "scan_timestamp": entry.scan_timestamp.isoformat(),
                    "event_type": entry.event_type
                }
                
                # For first entry, get the actual previous hash from database
                if i == 0 and entry.sequence_id > 1:
                    prev_result = await self.db.execute(
                        select(RFIDLedger.hash_chain)
                        .where(RFIDLedger.sequence_id == entry.sequence_id - 1)
                    )
                    stored_prev_hash = prev_result.scalar_one_or_none()
                    if stored_prev_hash:
                        prev_hash = stored_prev_hash
                
                # Verify this entry's hash
                if not self.verify_hash(prev_hash, payload, entry.hash_chain):
                    issues.append({
                        "sequence_id": entry.sequence_id,
                        "issue": "hash_mismatch",
                        "expected_prev_hash": prev_hash,
                        "actual_hash": entry.hash_chain
                    })
                
                # Set up for next iteration
                prev_hash = entry.hash_chain
            
            return {
                "valid": len(issues) == 0,
                "entries_checked": len(entries),
                "issues": issues,
                "message": f"Verified {len(entries)} entries, {len(issues)} issues found"
            }
            
        except Exception as e:
            logger.error(f"Error verifying chain integrity: {str(e)}")
            return {
                "valid": False,
                "entries_checked": 0,
                "issues": [],
                "error": str(e)
            }
    
    def create_payload(self, sequence_id: int, employee_rfid: str, device_id: str, 
                      scan_timestamp: str, event_type: str) -> Dict[str, Any]:
        """
        Create a standardized payload dictionary for hash computation.
        
        Args:
            sequence_id: Sequence ID of the event
            employee_rfid: RFID card UID
            device_id: Device that recorded the event
            scan_timestamp: ISO timestamp of the event
            event_type: Type of event (time_in, time_out, etc.)
            
        Returns:
            Standardized payload dictionary
        """
        return {
            "sequence_id": sequence_id,
            "employee_rfid": employee_rfid,
            "device_id": device_id,
            "scan_timestamp": scan_timestamp,
            "event_type": event_type
        }