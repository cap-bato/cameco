"""create rfid tables only

Revision ID: 9984172f74bd
Revises: 
Create Date: 2026-03-11 12:36:50.083686

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql


# revision identifiers, used by Alembic.
revision: str = '9984172f74bd'
down_revision: Union[str, Sequence[str], None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create RFID tables for FastAPI server."""
    
    # 1. Create rfid_card_mappings table
    op.create_table('rfid_card_mappings',
        sa.Column('id', sa.BigInteger(), nullable=False),
        sa.Column('card_uid', sa.String(length=255), nullable=False, comment="RFID card UID (e.g., '04:3A:B2:C5:D8')"),
        sa.Column('employee_id', sa.BigInteger(), nullable=False, comment='Foreign key to employees table (constraint added separately)'),
        sa.Column('card_type', sa.String(length=50), nullable=True, default='mifare', comment='Card technology (mifare, desfire, etc.)'),
        sa.Column('issued_at', sa.DateTime(timezone=True), nullable=False, comment='When card was issued to employee'),
        sa.Column('expires_at', sa.DateTime(timezone=True), nullable=True, comment='Optional expiration date'),
        sa.Column('is_active', sa.Boolean(), nullable=True, default=True, comment='Only one active card per employee'),
        sa.Column('last_used_at', sa.DateTime(timezone=True), nullable=True, comment='Last time card was used for scanning'),
        sa.Column('usage_count', sa.Integer(), nullable=True, default=0, comment='Number of times card has been used'),
        sa.Column('notes', sa.Text(), nullable=True, comment='Additional notes about the card'),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=True),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    
    # Create indexes for rfid_card_mappings 
    op.create_index('ix_rfid_card_mappings_id', 'rfid_card_mappings', ['id'])
    op.create_index('ix_rfid_card_mappings_card_uid', 'rfid_card_mappings', ['card_uid'], unique=True)
    op.create_index('ix_rfid_card_mappings_employee_id', 'rfid_card_mappings', ['employee_id'])
    op.create_index('ix_rfid_card_mappings_is_active', 'rfid_card_mappings', ['is_active'])
    op.create_index('ix_rfid_card_mappings_last_used_at', 'rfid_card_mappings', ['last_used_at'])
    
    # Create partial unique index for active cards (one active card per employee)
    op.execute("CREATE UNIQUE INDEX uk_employee_active ON rfid_card_mappings(employee_id) WHERE is_active = true")

    # 2. Create rfid_devices table  
    op.create_table('rfid_devices',
        sa.Column('id', sa.BigInteger(), nullable=False),
        sa.Column('device_id', sa.String(length=255), nullable=False, comment="Device identifier (e.g., 'GATE-01')"),
        sa.Column('device_name', sa.String(length=255), nullable=False, comment="Human-readable name (e.g., 'Main Gate Entrance')"),
        sa.Column('location', sa.String(length=255), nullable=True, comment="Physical location (e.g., 'Gate 1 - Building A')"),
        sa.Column('ip_address', postgresql.INET(), nullable=True, comment='Device IP address'),
        sa.Column('mac_address', postgresql.MACADDR(), nullable=True, comment='Device MAC address'),
        sa.Column('device_type', sa.String(length=50), nullable=True, default='reader', comment='Device type: reader, controller, hybrid'),
        sa.Column('protocol', sa.String(length=50), nullable=True, default='tcp', comment='Communication protocol: tcp, udp, http, mqtt'),
        sa.Column('port', sa.Integer(), nullable=True, comment='Communication port number'),
        sa.Column('is_online', sa.Boolean(), nullable=True, default=False, comment='Current online status based on heartbeat'),
        sa.Column('last_heartbeat_at', sa.DateTime(timezone=True), nullable=True, comment='Last received heartbeat timestamp'),
        sa.Column('firmware_version', sa.String(length=50), nullable=True, comment='Device firmware version'),
        sa.Column('serial_number', sa.String(length=255), nullable=True, comment='Device serial number'),
        sa.Column('installation_date', sa.Date(), nullable=True, comment='When device was installed'),
        sa.Column('maintenance_schedule', sa.String(length=50), nullable=True, comment="Maintenance frequency (e.g., 'quarterly')"),
        sa.Column('last_maintenance_at', sa.DateTime(timezone=True), nullable=True, comment='Last maintenance performed'),
        sa.Column('config_json', postgresql.JSONB(astext_type=sa.Text()), nullable=True, comment='Device-specific configuration settings'),
        sa.Column('notes', sa.Text(), nullable=True, comment='Additional notes about the device'),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=True),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    
    # Create indexes for rfid_devices
    op.create_index('ix_rfid_devices_id', 'rfid_devices', ['id'])
    op.create_index('ix_rfid_devices_device_id', 'rfid_devices', ['device_id'], unique=True)
    op.create_index('ix_rfid_devices_is_online', 'rfid_devices', ['is_online'])
    op.create_index('ix_rfid_devices_last_heartbeat_at', 'rfid_devices', ['last_heartbeat_at'])

    # 3. Create rfid_ledger table
    # First create sequence for sequence_id
    op.execute("CREATE SEQUENCE rfid_ledger_sequence_seq")
    
    op.create_table('rfid_ledger',
        sa.Column('id', sa.BigInteger(), nullable=False),
        sa.Column('sequence_id', sa.BigInteger(), nullable=False, server_default=sa.text("nextval('rfid_ledger_sequence_seq')"), comment='Auto-increment sequence for hash chain ordering'),
        sa.Column('employee_rfid', sa.String(length=255), nullable=False, comment='RFID card UID from mapping table'),
        sa.Column('device_id', sa.String(length=255), nullable=False, comment='RFID device identifier'),
        sa.Column('scan_timestamp', sa.DateTime(timezone=True), nullable=False, comment='When the RFID tap occurred'),
        sa.Column('event_type', sa.String(length=50), nullable=False, comment='Event type: time_in, time_out, break_start, break_end'),
        sa.Column('raw_payload', postgresql.JSONB(astext_type=sa.Text()), nullable=False, comment='Original raw event data as JSON'),
        sa.Column('hash_chain', sa.String(length=255), nullable=False, comment='SHA-256 hash of prev_hash || payload for tamper detection'),
        sa.Column('device_signature', sa.Text(), nullable=True, comment='Optional Ed25519 device signature for verification'),
        sa.Column('processed', sa.Boolean(), nullable=True, default=False, comment='Has Laravel processed this event?'),
        sa.Column('processed_at', sa.DateTime(timezone=True), nullable=True, comment='When Laravel processed this event'),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=True, comment='When record was created (immutable)'),
        sa.PrimaryKeyConstraint('id')
    )
    
    # Create indexes for rfid_ledger
    op.create_index('ix_rfid_ledger_id', 'rfid_ledger', ['id'])
    op.create_index('ix_rfid_ledger_sequence_id', 'rfid_ledger', ['sequence_id'], unique=True)
    op.create_index('ix_rfid_ledger_employee_rfid', 'rfid_ledger', ['employee_rfid'])
    op.create_index('ix_rfid_ledger_device_id', 'rfid_ledger', ['device_id'])
    op.create_index('ix_rfid_ledger_scan_timestamp', 'rfid_ledger', ['scan_timestamp'])
    op.create_index('ix_rfid_ledger_processed', 'rfid_ledger', ['processed'])

    # 4. Create event_deduplication_cache table
    op.create_table('event_deduplication_cache',
        sa.Column('id', sa.BigInteger(), nullable=False),
        sa.Column('cache_key', sa.String(length=255), nullable=False, comment="Format: '{employee_id}:{device_id}:{event_type}'"),
        sa.Column('last_event_timestamp', sa.DateTime(timezone=True), nullable=False, comment='Timestamp of the last event for this key'),
        sa.Column('sequence_id', sa.BigInteger(), nullable=False, comment='Sequence ID of the last event'),
        sa.Column('expires_at', sa.DateTime(timezone=True), nullable=False, comment='TTL: last_event_timestamp + configured window (default 15 seconds)'),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    
    # Create indexes for event_deduplication_cache
    op.create_index('ix_event_deduplication_cache_id', 'event_deduplication_cache', ['id'])
    op.create_index('ix_event_deduplication_cache_cache_key', 'event_deduplication_cache', ['cache_key'], unique=True)
    op.create_index('ix_event_deduplication_cache_expires_at', 'event_deduplication_cache', ['expires_at'])


def downgrade() -> None:
    """Drop RFID tables."""
    # Drop tables in reverse order (due to dependencies)
    op.drop_table('event_deduplication_cache')
    op.drop_table('rfid_ledger')
    op.execute("DROP SEQUENCE IF EXISTS rfid_ledger_sequence_seq")
    op.drop_table('rfid_devices')
    op.drop_table('rfid_card_mappings')
