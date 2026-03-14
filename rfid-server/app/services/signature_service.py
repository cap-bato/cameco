import base64
import json
from datetime import datetime, timezone
from typing import Dict, Any, Optional
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from cryptography.exceptions import InvalidSignature
from app.config import settings
import logging

logger = logging.getLogger(__name__)


class SignatureVerificationError(Exception):
    """Exception raised when signature verification fails."""
    pass


class Ed25519SignatureService:
    """Service for Ed25519 digital signature verification."""
    
    def __init__(self):
        self.public_keys: Dict[str, Ed25519PublicKey] = {}
        self._load_default_public_key()
    
    def _load_default_public_key(self) -> None:
        """Load the default public key from configuration."""
        if settings.ed25519_public_key:
            try:
                self.add_public_key("default", settings.ed25519_public_key)
                logger.info("Default Ed25519 public key loaded successfully")
            except Exception as e:
                logger.error(f"Failed to load default Ed25519 public key: {str(e)}")
    
    def add_public_key(self, device_id: str, public_key_b64: str) -> None:
        """
        Add a public key for a specific device.
        
        Args:
            device_id: Unique identifier for the device
            public_key_b64: Base64-encoded public key bytes
        """
        try:
            # Decode base64 public key
            public_key_bytes = base64.b64decode(public_key_b64)
            
            # Create Ed25519 public key object
            public_key = Ed25519PublicKey.from_public_bytes(public_key_bytes)
            
            # Store the key
            self.public_keys[device_id] = public_key
            
            logger.info(f"Added Ed25519 public key for device: {device_id}")
            
        except Exception as e:
            raise SignatureVerificationError(f"Invalid public key for device {device_id}: {str(e)}")
    
    def verify_signature(self, payload: Dict[str, Any], signature_b64: str, device_id: str = "default") -> bool:
        """
        Verify Ed25519 signature for a payload.
        
        Args:
            payload: The data that was signed
            signature_b64: Base64-encoded signature
            device_id: Device ID to use for public key lookup
            
        Returns:
            True if signature is valid, False otherwise
        """
        try:
            # Get public key for device
            if device_id not in self.public_keys:
                logger.warning(f"No public key found for device: {device_id}")
                return False
            
            public_key = self.public_keys[device_id]
            
            # Normalize payload for consistent signing
            normalized_payload = self._normalize_payload(payload)
            
            # Decode signature
            signature_bytes = base64.b64decode(signature_b64)
            
            # Verify signature
            try:
                public_key.verify(signature_bytes, normalized_payload.encode('utf-8'))
                logger.debug(f"Signature verification successful for device: {device_id}")
                return True
            
            except InvalidSignature:
                logger.warning(f"Invalid signature for device: {device_id}")
                return False
                
        except Exception as e:
            logger.error(f"Signature verification error for device {device_id}: {str(e)}")
            return False
    
    def verify_rfid_event_signature(self, event_data: Dict[str, Any], signature: str, device_id: str) -> bool:
        """
        Verify signature specifically for RFID events.
        
        Args:
            event_data: RFID event data
            signature: Base64-encoded signature
            device_id: Device ID that signed the event
            
        Returns:
            True if signature is valid, False otherwise
        """
        if not settings.device_signature_verification:
            logger.debug("Device signature verification is disabled")
            return True
        
        if not signature:
            logger.warning(f"No signature provided for device: {device_id}")
            return False
        
        # Create signing payload (exclude signature field)
        signing_payload = {
            "card_uid": event_data.get("card_uid"),
            "device_id": event_data.get("device_id"),
            "event_type": event_data.get("event_type"),
            "timestamp": event_data.get("timestamp")
        }
        
        # Add timestamp if not present (some devices might not include it)
        if isinstance(signing_payload["timestamp"], str):
            # Keep as string for consistent signing
            pass
        elif isinstance(signing_payload["timestamp"], datetime):
            # Convert to ISO format string
            signing_payload["timestamp"] = signing_payload["timestamp"].isoformat()
        
        return self.verify_signature(signing_payload, signature, device_id)
    
    def _normalize_payload(self, payload: Dict[str, Any]) -> str:
        """
        Normalize payload for consistent signing/verification.
        
        This ensures that the same payload always produces the same string
        representation regardless of key ordering or formatting.
        """
        # Remove None values
        cleaned_payload = {k: v for k, v in payload.items() if v is not None}
        
        # Sort keys and create deterministic JSON representation
        normalized = json.dumps(cleaned_payload, sort_keys=True, separators=(',', ':'))
        
        return normalized
    
    def get_supported_devices(self) -> list[str]:
        """Get list of device IDs that have registered public keys."""
        return list(self.public_keys.keys())
    
    def remove_public_key(self, device_id: str) -> bool:
        """
        Remove public key for a device.
        
        Args:
            device_id: Device ID to remove
            
        Returns:
            True if key was removed, False if not found
        """
        if device_id in self.public_keys:
            del self.public_keys[device_id]
            logger.info(f"Removed Ed25519 public key for device: {device_id}")
            return True
        return False


# Create a global instance for use throughout the application
signature_service = Ed25519SignatureService()


def verify_device_signature(event_data: Dict[str, Any], signature: str, device_id: str) -> bool:
    """
    Convenience function for verifying device signatures.
    
    Args:
        event_data: Event data to verify
        signature: Base64-encoded signature
        device_id: Device ID that signed the event
        
    Returns:
        True if signature is valid or verification is disabled, False otherwise
    """
    return signature_service.verify_rfid_event_signature(event_data, signature, device_id)


def add_device_public_key(device_id: str, public_key_b64: str) -> None:
    """
    Add a public key for device signature verification.
    
    Args:
        device_id: Unique identifier for the device
        public_key_b64: Base64-encoded public key bytes
    """
    signature_service.add_public_key(device_id, public_key_b64)


def get_signature_verification_status() -> Dict[str, Any]:
    """
    Get the current status of signature verification.
    
    Returns:
        Dictionary with verification status and device information
    """
    return {
        "verification_enabled": settings.device_signature_verification,
        "supported_devices": signature_service.get_supported_devices(),
        "total_devices": len(signature_service.public_keys)
    }