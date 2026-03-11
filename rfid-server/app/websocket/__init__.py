# WebSocket Real-time Updates Package
#
# This package provides WebSocket functionality for real-time updates in the RFID server.
# 
# Components:
# - connection_manager: WebSocket connection management and message broadcasting
# - Real-time event streaming for RFID events
# - Device status monitoring and notifications  
# - Alert and notification system
# - Dashboard integration for live updates

from .connection_manager import websocket_manager

__all__ = ["websocket_manager"]