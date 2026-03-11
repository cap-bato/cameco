from datetime import datetime, timedelta, timezone
from typing import Optional
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import delete, func
from app.models.deduplication_cache import EventDeduplicationCache
from app.config import settings
import logging

logger = logging.getLogger(__name__)


class DeduplicationService:
    """
    Service for detecting and preventing duplicate RFID events.
    
    Prevents duplicate events within a configurable time window (default: 15 seconds)
    by maintaining a cache of recent events keyed by employee_id:device_id:event_type.
    
    Cache entries automatically expire after the configured window to prevent
    memory growth and maintain deduplication accuracy.
    """
    
    def __init__(self, db: AsyncSession):
        self.db = db
        self.window_seconds = settings.duplicate_window_seconds  # Default: 15
    
    async def is_duplicate(
        self, 
        employee_id: int, 
        device_id: str, 
        event_type: str, 
        timestamp: datetime
    ) -> bool:
        """
        Check if event is a duplicate within the deduplication window.
        
        Args:
            employee_id: Employee ID from card mapping
            device_id: RFID device identifier
            event_type: Type of event (time_in, time_out, etc.)
            timestamp: Timestamp of the new event
            
        Returns:
            True if event is a duplicate (should be ignored), False otherwise
        """
        cache_key = self._build_cache_key(employee_id, device_id, event_type)
        
        try:
            # Look for existing cache entry that hasn't expired
            result = await self.db.execute(
                select(EventDeduplicationCache)
                .where(
                    EventDeduplicationCache.cache_key == cache_key,
                    EventDeduplicationCache.expires_at > datetime.now(timezone.utc)
                )
            )
            cache_entry = result.scalar_one_or_none()
            
            if cache_entry:
                # Check if within the deduplication window
                time_diff = abs((timestamp - cache_entry.last_event_timestamp).total_seconds())
                
                if time_diff < self.window_seconds:
                    logger.info(f"Duplicate detected for {cache_key}: time_diff={time_diff:.2f}s < {self.window_seconds}s")
                    return True  # Duplicate detected
                else:
                    logger.debug(f"Event not duplicate for {cache_key}: time_diff={time_diff:.2f}s >= {self.window_seconds}s")
            else:
                logger.debug(f"No recent cache entry found for {cache_key}")
            
            return False  # Not a duplicate
            
        except Exception as e:
            logger.error(f"Error checking for duplicate {cache_key}: {str(e)}")
            # In case of error, err on the side of processing the event
            return False
    
    async def add_to_cache(
        self, 
        employee_id: int, 
        device_id: str, 
        event_type: str,
        timestamp: datetime, 
        sequence_id: int
    ) -> None:
        """
        Add event to deduplication cache.
        
        This method uses upsert logic to either insert a new cache entry
        or update an existing one with the new event details.
        
        Args:
            employee_id: Employee ID from card mapping
            device_id: RFID device identifier
            event_type: Type of event (time_in, time_out, etc.)
            timestamp: Timestamp of the event
            sequence_id: Sequence ID of the stored event
        """
        cache_key = self._build_cache_key(employee_id, device_id, event_type)
        expires_at = timestamp + timedelta(seconds=self.window_seconds)
        
        try:
            # Check if entry already exists
            result = await self.db.execute(
                select(EventDeduplicationCache)
                .where(EventDeduplicationCache.cache_key == cache_key)
            )
            existing_entry = result.scalar_one_or_none()
            
            if existing_entry:
                # Update existing entry
                existing_entry.last_event_timestamp = timestamp
                existing_entry.sequence_id = sequence_id
                existing_entry.expires_at = expires_at
                logger.debug(f"Updated cache entry for {cache_key}")
            else:
                # Create new entry
                cache_entry = EventDeduplicationCache(
                    cache_key=cache_key,
                    last_event_timestamp=timestamp,
                    sequence_id=sequence_id,
                    expires_at=expires_at
                )
                self.db.add(cache_entry)
                logger.debug(f"Created new cache entry for {cache_key}")
            
            await self.db.commit()
            
        except Exception as e:
            logger.error(f"Error adding to cache {cache_key}: {str(e)}")
            await self.db.rollback()
            raise
    
    async def cleanup_expired(self) -> int:
        """
        Remove expired cache entries (called periodically).
        
        This method should be called regularly (e.g., every minute) to clean up
        expired cache entries and prevent the cache table from growing indefinitely.
        
        Returns:
            Count of deleted entries
        """
        try:
            current_time = datetime.now(timezone.utc)
            
            result = await self.db.execute(
                delete(EventDeduplicationCache)
                .where(EventDeduplicationCache.expires_at < current_time)
            )
            
            await self.db.commit()
            deleted_count = result.rowcount
            
            if deleted_count > 0:
                logger.info(f"Cleaned up {deleted_count} expired cache entries")
            else:
                logger.debug("No expired cache entries to clean up")
            
            return deleted_count
            
        except Exception as e:
            logger.error(f"Error cleaning up expired cache entries: {str(e)}")
            await self.db.rollback()
            raise
    
    async def get_cache_stats(self) -> dict:
        """
        Get statistics about the deduplication cache.
        
        Returns:
            Dictionary with cache statistics
        """
        try:
            current_time = datetime.now(timezone.utc)
            
            # Total entries
            total_result = await self.db.execute(
                select(func.count(EventDeduplicationCache.id))
            )
            total_entries = total_result.scalar_one()
            
            # Active (non-expired) entries
            active_result = await self.db.execute(
                select(func.count(EventDeduplicationCache.id))
                .where(EventDeduplicationCache.expires_at > current_time)
            )
            active_entries = active_result.scalar_one()
            
            # Expired entries
            expired_entries = total_entries - active_entries
            
            return {
                "total_entries": total_entries,
                "active_entries": active_entries,
                "expired_entries": expired_entries,
                "window_seconds": self.window_seconds,
                "last_checked": current_time.isoformat()
            }
            
        except Exception as e:
            logger.error(f"Error getting cache stats: {str(e)}")
            return {
                "total_entries": 0,
                "active_entries": 0,
                "expired_entries": 0,
                "window_seconds": self.window_seconds,
                "last_checked": datetime.now(timezone.utc).isoformat(),
                "error": str(e)
            }
    
    def _build_cache_key(self, employee_id: int, device_id: str, event_type: str) -> str:
        """
        Build standardized cache key for deduplication.
        
        Args:
            employee_id: Employee ID
            device_id: Device ID
            event_type: Event type
            
        Returns:
            Cache key in format "{employee_id}:{device_id}:{event_type}"
        """
        return f"{employee_id}:{device_id}:{event_type}"
    
    async def force_cleanup_all(self) -> int:
        """
        Force cleanup of all cache entries (useful for testing/debugging).
        
        Returns:
            Count of deleted entries
        """
        try:
            result = await self.db.execute(
                delete(EventDeduplicationCache)
            )
            
            await self.db.commit()
            deleted_count = result.rowcount
            
            logger.warning(f"Force deleted ALL {deleted_count} cache entries")
            return deleted_count
            
        except Exception as e:
            logger.error(f"Error force cleaning all cache entries: {str(e)}")
            await self.db.rollback()
            raise