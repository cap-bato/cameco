from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Integer, Text, Date
from sqlalchemy.dialects.postgresql import INET, MACADDR, JSONB
from sqlalchemy.sql import func
from app.database import Base


class RFIDDevice(Base):
    """
    Registry of RFID card readers and their configurations.
    
    Heartbeat Logic:
    - Devices send periodic heartbeats (every 30 seconds)
    - If no heartbeat in 2 minutes → mark offline  
    - Offline devices trigger alerts to HR Manager
    """
    __tablename__ = "rfid_devices"
    
    id = Column(BigInteger, primary_key=True, index=True)
    device_id = Column(String(255), unique=True, nullable=False, index=True,
                      comment="Device identifier (e.g., 'GATE-01')")
    device_name = Column(String(255), nullable=False,
                        comment="Human-readable name (e.g., 'Main Gate Entrance')")
    location = Column(String(255),
                     comment="Physical location (e.g., 'Gate 1 - Building A')")
    ip_address = Column(INET, comment="Device IP address")
    mac_address = Column(MACADDR, comment="Device MAC address")
    device_type = Column(String(50), default="reader",
                        comment="Device type: reader, controller, hybrid")
    protocol = Column(String(50), default="tcp",
                     comment="Communication protocol: tcp, udp, http, mqtt")
    port = Column(Integer, comment="Communication port number")
    is_online = Column(Boolean, default=False, index=True,
                      comment="Current online status based on heartbeat")
    last_heartbeat_at = Column(DateTime(timezone=True), index=True,
                              comment="Last received heartbeat timestamp")
    firmware_version = Column(String(50), comment="Device firmware version")
    serial_number = Column(String(255), comment="Device serial number")
    installation_date = Column(Date, comment="When device was installed")
    maintenance_schedule = Column(String(50), 
                                 comment="Maintenance frequency (e.g., 'quarterly')")
    last_maintenance_at = Column(DateTime(timezone=True),
                                comment="Last maintenance performed")
    config_json = Column(JSONB, comment="Device-specific configuration settings")
    notes = Column(Text, comment="Additional notes about the device")
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())


# Index definitions for performance
# CREATE INDEX idx_device_id ON rfid_devices(device_id);
# CREATE INDEX idx_online_status ON rfid_devices(is_online);
# CREATE INDEX idx_last_heartbeat ON rfid_devices(last_heartbeat_at);