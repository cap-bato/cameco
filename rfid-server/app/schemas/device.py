from pydantic import BaseModel, Field
from datetime import datetime, date
from typing import Optional, Dict, Any
from ipaddress import IPv4Address


class DeviceCreate(BaseModel):
    """Schema for creating new RFID devices."""
    device_id: str = Field(..., description="Unique device identifier")
    name: str = Field(..., description="Human readable device name")
    location: Optional[str] = Field(None, description="Physical location description")
    ip_address: Optional[IPv4Address] = Field(None, description="Device IP address")
    mac_address: Optional[str] = Field(None, description="Device MAC address")
    device_type: str = Field("reader", description="Device type: reader, controller, hybrid")
    protocol: str = Field("tcp", description="Communication protocol: tcp, udp, http, mqtt")
    port: Optional[int] = Field(None, description="Communication port number")
    firmware_version: Optional[str] = Field(None, description="Device firmware version")
    serial_number: Optional[str] = Field(None, description="Device serial number")
    installation_date: Optional[date] = Field(None, description="Installation date")
    maintenance_schedule: Optional[str] = Field(None, description="Maintenance schedule")
    configuration: Optional[Dict[str, Any]] = Field(None, description="Device-specific configuration")
    notes: Optional[str] = Field(None, description="Additional notes")
    
    class Config:
        json_schema_extra = {
            "example": {
                "device_id": "GATE-01",
                "name": "Main Gate Entrance",
                "location": "Gate 1 - Building A",
                "ip_address": "192.168.1.100",
                "device_type": "reader",
                "protocol": "tcp",
                "port": 9000
            }
        }


class DeviceUpdate(BaseModel):
    """Schema for updating existing RFID devices."""
    name: Optional[str] = Field(None, description="Human readable device name")
    location: Optional[str] = Field(None, description="Physical location description")
    ip_address: Optional[IPv4Address] = Field(None, description="Device IP address")
    mac_address: Optional[str] = Field(None, description="Device MAC address")
    device_type: Optional[str] = Field(None, description="Device type")
    protocol: Optional[str] = Field(None, description="Communication protocol")
    port: Optional[int] = Field(None, description="Communication port number")
    firmware_version: Optional[str] = Field(None, description="Device firmware version")
    serial_number: Optional[str] = Field(None, description="Device serial number")
    installation_date: Optional[date] = Field(None, description="Installation date")
    maintenance_schedule: Optional[str] = Field(None, description="Maintenance schedule")
    last_maintenance_at: Optional[datetime] = Field(None, description="Last maintenance timestamp")
    configuration: Optional[Dict[str, Any]] = Field(None, description="Device-specific configuration")
    notes: Optional[str] = Field(None, description="Additional notes")


class DeviceResponse(BaseModel):
    """Schema for RFID device responses."""
    id: int
    device_id: str
    device_name: str
    location: Optional[str]
    ip_address: Optional[str]
    mac_address: Optional[str]
    device_type: str
    protocol: str
    port: Optional[int]
    is_online: bool
    last_heartbeat_at: Optional[datetime]
    firmware_version: Optional[str]
    serial_number: Optional[str]
    installation_date: Optional[date]
    maintenance_schedule: Optional[str]
    last_maintenance_at: Optional[datetime]
    config_json: Optional[Dict[str, Any]]
    notes: Optional[str]
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True


class DeviceHeartbeatResponse(BaseModel):
    """Schema for device heartbeat responses."""
    status: str = Field(..., description="Heartbeat status")
    device_id: str = Field(..., description="Device identifier")
    last_heartbeat: datetime = Field(..., description="Last heartbeat timestamp")
    
    class Config:
        json_schema_extra = {
            "example": {
                "status": "ok",
                "device_id": "GATE-01",
                "last_heartbeat": "2026-02-04T08:05:23Z"
            }
        }


class DeviceHeartbeat(BaseModel):
    """Schema for device heartbeat requests."""
    timestamp: datetime = Field(..., description="Heartbeat timestamp")
    status: str = Field("online", description="Device status")
    metadata: Optional[Dict[str, Any]] = Field(None, description="Additional device metadata")
    
    class Config:
        json_schema_extra = {
            "example": {
                "timestamp": "2026-02-04T08:05:23Z",
                "status": "online",
                "metadata": {
                    "signal_strength": 85,
                    "battery_level": 92
                }
            }
        }