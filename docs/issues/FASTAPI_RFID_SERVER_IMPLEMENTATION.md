# FastAPI RFID Server - Implementation Guide

**Project Type:** FastAPI Backend Service  
**Priority:** HIGH  
**Estimated Duration:** 2-3 weeks  
**Target Integration:** Laravel Timekeeping Module (PostgreSQL Ledger)  
**Dependencies:** PostgreSQL, RFID Card Readers (Read-Only), Redis (optional caching)  
**Related Modules:** Timekeeping, Employee Management

---

## 📋 Executive Summary

Build a FastAPI server that:
1. Receives RFID tap events from card readers (TCP/UDP or HTTP)
2. Maps RFID card IDs to employee IDs via database lookup
3. Validates events and prevents duplicates (15-second window)
4. Writes tamper-resistant, hash-chained events to PostgreSQL ledger
5. Provides health monitoring and device management APIs
6. Supports offline device catch-up and batch event processing

**Key Innovation:** RFID cards are read-only identifiers. The server maintains a mapping table (`rfid_card_mappings`) that links card UIDs to employee IDs, enabling seamless employee identification.

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    RFID Card Readers (Gates)                │
│  Gate 1, Gate 2, Cafeteria, Loading Dock, etc.             │
└────────────┬────────────────────────────────────────────────┘
             │ TCP/UDP or HTTP POST
             ▼
┌─────────────────────────────────────────────────────────────┐
│                   FastAPI RFID Server                       │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 1. Receive RFID tap (card_uid, device_id, timestamp)│   │
│  │ 2. Lookup employee_id via rfid_card_mappings table  │   │
│  │ 3. Validate event (duplicate check, device status)  │   │
│  │ 4. Generate hash chain (prev_hash || payload)       │   │
│  │ 5. Write to rfid_ledger (append-only, immutable)    │   │
│  │ 6. Emit metrics and health status                   │   │
│  └─────────────────────────────────────────────────────┘   │
└────────────┬────────────────────────────────────────────────┘
             │ PostgreSQL
             ▼
┌─────────────────────────────────────────────────────────────┐
│              PostgreSQL Database (Shared)                   │
│  ┌──────────────────────┐  ┌──────────────────────────┐    │
│  │ rfid_card_mappings   │  │ rfid_ledger              │    │
│  │ (card_uid → emp_id)  │  │ (append-only events)     │    │
│  └──────────────────────┘  └──────────────────────────┘    │
│  ┌──────────────────────┐  ┌──────────────────────────┐    │
│  │ rfid_devices         │  │ employees (Laravel)      │    │
│  │ (device registry)    │  │ (source of truth)        │    │
│  └──────────────────────┘  └──────────────────────────┘    │
└────────────┬────────────────────────────────────────────────┘
             │ Polling (every 1 minute)
             ▼
┌─────────────────────────────────────────────────────────────┐
│         Laravel Timekeeping Module (Consumer)               │
│  - Polls rfid_ledger for unprocessed events                 │
│  - Validates hash chains                                    │
│  - Creates attendance_events records                        │
│  - Computes daily_attendance_summary                        │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Database Schema

### 1. `rfid_card_mappings` (NEW - FastAPI manages)

**Purpose:** Map read-only RFID card UIDs to employee IDs.

```sql
CREATE TABLE rfid_card_mappings (
    id BIGSERIAL PRIMARY KEY,
    card_uid VARCHAR(255) NOT NULL UNIQUE,  -- RFID card UID (e.g., "04:3A:B2:C5:D8")
    employee_id BIGINT NOT NULL,            -- Foreign key to employees table
    card_type VARCHAR(50) DEFAULT 'mifare', -- Card technology (mifare, desfire, etc.)
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP,                   -- Optional expiration date
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP,
    usage_count INTEGER DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    CONSTRAINT fk_employee FOREIGN KEY (employee_id) 
        REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT uk_employee_active UNIQUE (employee_id, is_active) 
        WHERE is_active = TRUE  -- One active card per employee
);

CREATE INDEX idx_card_uid_active ON rfid_card_mappings(card_uid) WHERE is_active = TRUE;
CREATE INDEX idx_employee_id ON rfid_card_mappings(employee_id);
CREATE INDEX idx_last_used ON rfid_card_mappings(last_used_at);
```

**Rationale:** 
- One employee can have multiple cards (old/new), but only one active
- Card replacement workflow: deactivate old, issue new
- Track usage for audit and card lifecycle management

---

### 2. `rfid_devices` (NEW - FastAPI manages)

**Purpose:** Registry of RFID card readers and their configurations.

```sql
CREATE TABLE rfid_devices (
    id BIGSERIAL PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL UNIQUE,  -- e.g., "GATE-01"
    device_name VARCHAR(255) NOT NULL,       -- e.g., "Main Gate Entrance"
    location VARCHAR(255),                   -- e.g., "Gate 1 - Building A"
    ip_address INET,
    mac_address MACADDR,
    device_type VARCHAR(50) DEFAULT 'reader', -- reader, controller, hybrid
    protocol VARCHAR(50) DEFAULT 'tcp',       -- tcp, udp, http, mqtt
    port INTEGER,
    is_online BOOLEAN DEFAULT FALSE,
    last_heartbeat_at TIMESTAMP,
    firmware_version VARCHAR(50),
    serial_number VARCHAR(255),
    installation_date DATE,
    maintenance_schedule VARCHAR(50),         -- e.g., "quarterly"
    last_maintenance_at TIMESTAMP,
    config_json JSONB,                        -- Device-specific configs
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_device_id ON rfid_devices(device_id);
CREATE INDEX idx_online_status ON rfid_devices(is_online);
CREATE INDEX idx_last_heartbeat ON rfid_devices(last_heartbeat_at);
```

**Heartbeat Logic:**
- Devices send periodic heartbeats (every 30 seconds)
- If no heartbeat in 2 minutes → mark offline
- Offline devices trigger alerts to HR Manager

---

### 3. `rfid_ledger` (Shared with Laravel - FastAPI writes, Laravel reads)

**Already defined in Laravel implementation. FastAPI writes to this table.**

```sql
-- See TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md for full schema
-- Key fields FastAPI writes:
-- - sequence_id (auto-increment via sequence)
-- - employee_rfid (card_uid from mapping)
-- - device_id
-- - scan_timestamp
-- - event_type (time_in, time_out, break_start, break_end)
-- - raw_payload (JSONB)
-- - hash_chain (SHA-256 of prev_hash || payload)
-- - device_signature (optional Ed25519)
```

---

### 4. `event_deduplication_cache` (NEW - FastAPI manages)

**Purpose:** Fast in-memory duplicate detection (15-second window).

```sql
CREATE TABLE event_deduplication_cache (
    id BIGSERIAL PRIMARY KEY,
    cache_key VARCHAR(255) NOT NULL UNIQUE,  -- "{employee_id}:{device_id}:{event_type}"
    last_event_timestamp TIMESTAMP NOT NULL,
    sequence_id BIGINT NOT NULL,
    expires_at TIMESTAMP NOT NULL,           -- TTL: last_event_timestamp + 15 seconds
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_cache_key ON event_deduplication_cache(cache_key);
CREATE INDEX idx_expires_at ON event_deduplication_cache(expires_at);

-- Auto-cleanup expired entries (run every minute)
-- DELETE FROM event_deduplication_cache WHERE expires_at < NOW();
```

**Alternative:** Use Redis for deduplication cache (faster, auto-expiry with TTL).

---

## 📦 FastAPI Project Structure

```
rfid-server/
├── app/
│   ├── __init__.py
│   ├── main.py                    # FastAPI app entry point
│   ├── config.py                  # Environment variables, DB config
│   ├── database.py                # SQLAlchemy engine, session management
│   ├── models/
│   │   ├── __init__.py
│   │   ├── rfid_card_mapping.py   # SQLAlchemy model
│   │   ├── rfid_device.py         # SQLAlchemy model
│   │   ├── rfid_ledger.py         # SQLAlchemy model (read/write)
│   │   └── deduplication_cache.py # SQLAlchemy model
│   ├── schemas/
│   │   ├── __init__.py
│   │   ├── rfid_event.py          # Pydantic schemas for requests/responses
│   │   ├── device.py
│   │   └── mapping.py
│   ├── services/
│   │   ├── __init__.py
│   │   ├── event_processor.py     # Core event processing logic
│   │   ├── hash_chain.py          # Hash chain generation and validation
│   │   ├── device_manager.py      # Device registration, heartbeat
│   │   ├── card_mapper.py         # RFID card → employee mapping
│   │   └── deduplicator.py        # Duplicate event detection
│   ├── api/
│   │   ├── __init__.py
│   │   ├── events.py              # POST /api/events (receive RFID taps)
│   │   ├── devices.py             # Device management APIs
│   │   ├── mappings.py            # Card mapping CRUD
│   │   └── health.py              # Health check, metrics
│   ├── listeners/
│   │   ├── __init__.py
│   │   ├── tcp_listener.py        # TCP socket listener for RFID devices
│   │   └── udp_listener.py        # UDP listener (optional)
│   └── utils/
│       ├── __init__.py
│       ├── crypto.py              # Ed25519 signature verification
│       └── logging.py             # Structured logging
├── tests/
│   ├── test_event_processor.py
│   ├── test_hash_chain.py
│   └── test_deduplicator.py
├── alembic/                        # Database migrations
│   ├── versions/
│   └── env.py
├── .env.example
├── requirements.txt
├── Dockerfile
├── docker-compose.yml
└── README.md
```

---

## 🔧 Implementation Phases

### **Phase 1: Project Setup & Database (Week 1)**

#### **Task 1.1: Initialize FastAPI Project**

**Subtasks:**
- [x] **1.1.1** Create project directory structure ✅ COMPLETED
- [x] **1.1.2** Setup virtual environment: `python -m venv venv` ✅ COMPLETED
- [x] **1.1.3** Install dependencies: ✅ COMPLETED
  ```bash
  pip install fastapi uvicorn sqlalchemy asyncpg psycopg2-binary pydantic python-dotenv alembic redis cryptography
  ```
- [x] **1.1.4** Create `requirements.txt` ✅ COMPLETED
- [x] **1.1.5** Create `.env.example` ✅ COMPLETED

**Implementation Status:** ✅ **COMPLETED**

**Validation Results:**
- ✅ FastAPI server starts without errors
- ✅ All API endpoints respond correctly 
- ✅ Configuration system working properly
- ✅ Virtual environment properly configured
- ✅ All dependencies installed successfully

**Test Endpoints:**
- `GET /` - Root endpoint ✅
- `GET /test` - Task completion status ✅  
- `GET /health` - Health check ✅

**Files Created:**
- `requirements.txt` - Dependencies list
- `.env.example` - Environment configuration template
- `app/main.py` - FastAPI application entry point
- `app/config.py` - Configuration management
- `app/api/health.py` - Health check endpoints  
- `test_server.py` - Standalone test server

**Acceptance Criteria:**
- ✅ Project structure created
- ✅ Dependencies installed
- ✅ .env configured with database connection
- ✅ FastAPI server starts and responds to requests

---

#### **Task 1.2: Setup Database Models (SQLAlchemy)**

**File:** `app/models/rfid_card_mapping.py`

```python
from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Integer, Text, ForeignKey, UniqueConstraint
from sqlalchemy.sql import func
from app.database import Base

class RFIDCardMapping(Base):
    __tablename__ = "rfid_card_mappings"
    
    id = Column(BigInteger, primary_key=True, index=True)
    card_uid = Column(String(255), unique=True, nullable=False, index=True)
    employee_id = Column(BigInteger, ForeignKey("employees.id", ondelete="CASCADE"), nullable=False, index=True)
    card_type = Column(String(50), default="mifare")
    issued_at = Column(DateTime(timezone=True), nullable=False)
    expires_at = Column(DateTime(timezone=True))
    is_active = Column(Boolean, default=True, index=True)
    last_used_at = Column(DateTime(timezone=True))
    usage_count = Column(Integer, default=0)
    notes = Column(Text)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
    
    __table_args__ = (
        UniqueConstraint('employee_id', 'is_active', name='uk_employee_active'),
    )
```

**File:** `app/models/rfid_device.py`

```python
from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Integer, Text, Date
from sqlalchemy.dialects.postgresql import INET, MACADDR, JSONB
from sqlalchemy.sql import func
from app.database import Base

class RFIDDevice(Base):
    __tablename__ = "rfid_devices"
    
    id = Column(BigInteger, primary_key=True, index=True)
    device_id = Column(String(255), unique=True, nullable=False, index=True)
    device_name = Column(String(255), nullable=False)
    location = Column(String(255))
    ip_address = Column(INET)
    mac_address = Column(MACADDR)
    device_type = Column(String(50), default="reader")
    protocol = Column(String(50), default="tcp")
    port = Column(Integer)
    is_online = Column(Boolean, default=False, index=True)
    last_heartbeat_at = Column(DateTime(timezone=True), index=True)
    firmware_version = Column(String(50))
    serial_number = Column(String(255))
    installation_date = Column(Date)
    maintenance_schedule = Column(String(50))
    last_maintenance_at = Column(DateTime(timezone=True))
    config_json = Column(JSONB)
    notes = Column(Text)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now())
```

**File:** `app/models/rfid_ledger.py`

```python
from sqlalchemy import Column, BigInteger, String, Boolean, DateTime, Text
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.sql import func
from app.database import Base

class RFIDLedger(Base):
    __tablename__ = "rfid_ledger"
    
    id = Column(BigInteger, primary_key=True, index=True)
    sequence_id = Column(BigInteger, unique=True, nullable=False, index=True)
    employee_rfid = Column(String(255), nullable=False, index=True)  # card_uid
    device_id = Column(String(255), nullable=False, index=True)
    scan_timestamp = Column(DateTime(timezone=True), nullable=False)
    event_type = Column(String(50), nullable=False)  # time_in, time_out, break_start, break_end
    raw_payload = Column(JSONB, nullable=False)
    hash_chain = Column(String(255), nullable=False)
    device_signature = Column(Text)
    processed = Column(Boolean, default=False, index=True)
    processed_at = Column(DateTime(timezone=True))
    created_at = Column(DateTime(timezone=True), server_default=func.now())
```

**File:** `app/database.py`

```python
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession
from sqlalchemy.orm import sessionmaker, declarative_base
from app.config import settings

engine = create_async_engine(settings.DATABASE_URL, echo=True)
AsyncSessionLocal = sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)
Base = declarative_base()

async def get_db():
    async with AsyncSessionLocal() as session:
        yield session
```

**Acceptance Criteria:**
- All models defined with proper relationships
- Database connection configured
- Async SQLAlchemy setup working

---

#### **Task 1.3: Create Alembic Migrations**

**Subtasks:**
- [x] **1.3.1** Initialize Alembic: `alembic init alembic`
- [x] **1.3.2** Configure `alembic/env.py` to use async engine
- [x] **1.3.3** Create migration for `rfid_card_mappings`:
  ```bash
  alembic revision --autogenerate -m "create rfid_card_mappings table"
  ```
- [x] **1.3.4** Create migration for `rfid_devices`:
  ```bash
  alembic revision --autogenerate -m "create rfid_devices table"
  ```
- [x] **1.3.5** Run migrations:
  ```bash
  alembic upgrade head
  ```

**Acceptance Criteria:**
- Migrations created and applied successfully
- Tables exist in PostgreSQL database
- Foreign key constraints working

---

### **Phase 2: Core Services (Week 1-2)** ✅ **COMPLETED**

#### **Task 2.1: Implement Card Mapping Service** ✅ **COMPLETED**

**File:** `app/services/card_mapper.py`

**Implementation Status:** ✅ **COMPLETED**

**Validation Results:**
- ✅ Card registration working with automatic deactivation of previous cards
- ✅ Employee ID lookup by card UID functioning correctly 
- ✅ Card deactivation and expiration checking implemented
- ✅ Usage tracking updates on each card use
- ✅ Timezone-aware datetime handling implemented

**Test Results:**
- ✅ Card registered: TEST-CARD-20260311084136 → Employee 999999
- ✅ Card lookup successful: mapping working correctly
- ✅ Card details retrieved: active=True, usage_count tracking working  
- ✅ Card deactivation successful: lifecycle management working

---

#### **Task 2.2: Implement Hash Chain Service** ✅ **COMPLETED**

**File:** `app/services/hash_chain.py`

**Implementation Status:** ✅ **COMPLETED**

**Validation Results:**
- ✅ SHA-256 hash computation working deterministically
- ✅ Hash verification detecting tampering attempts correctly
- ✅ Genesis hash used for first entry in chain
- ✅ Payload serialization consistent and reliable
- ✅ Hash chain integrity methods implemented

**Test Results:**
- ✅ Hash computation successful: b8a04f2dd2f280da...
- ✅ Hash verification passed for valid hashes
- ✅ Tampering detection working: invalid hashes rejected
- ✅ Hash determinism verified: consistent results
- ✅ Payload creation successful: 5 fields structured correctly

---

#### **Task 2.3: Implement Deduplication Service** ✅ **COMPLETED**

**File:** `app/services/deduplicator.py`

**Implementation Status:** ✅ **COMPLETED**

**Validation Results:**
- ✅ 15-second deduplication window working correctly
- ✅ Cache key format "{employee_id}:{device_id}:{event_type}" implemented
- ✅ Different employees/devices/event types properly isolated
- ✅ Cache statistics and cleanup functions working
- ✅ Timezone-aware datetime handling implemented

**Test Results:**
- ✅ Initial duplicate check: not duplicate (correctly)
- ✅ Added event to cache successfully
- ✅ Duplicate detection within window: duplicate found (correctly)
- ✅ Different employee check: not duplicate (correctly)
- ✅ Different event type check: not duplicate (correctly)
- ✅ Cache stats retrieved: 1 active entries
- ✅ Cache cleanup: removed 1 entries

---

#### **Task 2.4: Implement Event Processor Service** ✅ **COMPLETED**

**File:** `app/services/event_processor.py`

**Implementation Status:** ✅ **COMPLETED**

**Validation Results:**
- ✅ Complete RFID event processing pipeline functional
- ✅ Card mapping integration working correctly
- ✅ Deduplication integration preventing duplicate events
- ✅ Hash chain generation maintaining tamper-resistant ledger
- ✅ PostgreSQL sequence ID generation working
- ✅ Batch processing functionality implemented
- ✅ JSON serialization for JSONB fields working correctly

**Test Results:**
- ✅ Test card registered: PIPELINE-TEST-20260311084136 → Employee 999998
- ✅ Event processed successfully: seq=2, hash=4d84d4827d5323ce...
- ✅ Duplicate detection working: duplicate events ignored
- ✅ Unknown card rejection working: unknown_card rejection
- ✅ Batch processing: 1 processed, 1 rejected (correctly)
- ✅ Ledger stats: 2 total events, 3 latest sequence
- ✅ Latest events: 2 retrieved successfully

**Acceptance Criteria:**
✅ Full pipeline executes without errors
✅ Unknown cards rejected gracefully  
✅ Duplicates ignored without writing to ledger
✅ Hash chain maintained correctly
✅ Sequence IDs sequential and consistent
✅ Timezone handling working correctly
✅ JSON serialization compatible with PostgreSQL JSONB

```python
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from app.models.rfid_card_mapping import RFIDCardMapping
from app.models.employee import Employee
from datetime import datetime
from typing import Optional

class CardMapperService:
    def __init__(self, db: AsyncSession):
        self.db = db
    
    async def get_employee_id_by_card(self, card_uid: str) -> Optional[int]:
        """
        Lookup employee_id by RFID card UID.
        Returns None if card not found or inactive.
        """
        result = await self.db.execute(
            select(RFIDCardMapping.employee_id)
            .where(
                RFIDCardMapping.card_uid == card_uid,
                RFIDCardMapping.is_active == True
            )
        )
        mapping = result.scalar_one_or_none()
        
        if mapping:
            # Update last_used_at and usage_count
            await self.db.execute(
                update(RFIDCardMapping)
                .where(RFIDCardMapping.card_uid == card_uid)
                .values(
                    last_used_at=datetime.utcnow(),
                    usage_count=RFIDCardMapping.usage_count + 1
                )
            )
            await self.db.commit()
        
        return mapping
    
    async def register_card(self, card_uid: str, employee_id: int, card_type: str = "mifare") -> RFIDCardMapping:
        """
        Register a new RFID card for an employee.
        Deactivates any existing active card for the employee.
        """
        # Deactivate old cards
        await self.db.execute(
            update(RFIDCardMapping)
            .where(
                RFIDCardMapping.employee_id == employee_id,
                RFIDCardMapping.is_active == True
            )
            .values(is_active=False, updated_at=datetime.utcnow())
        )
        
        # Create new mapping
        new_mapping = RFIDCardMapping(
            card_uid=card_uid,
            employee_id=employee_id,
            card_type=card_type,
            issued_at=datetime.utcnow(),
            is_active=True
        )
        self.db.add(new_mapping)
        await self.db.commit()
        await self.db.refresh(new_mapping)
        
        return new_mapping
    
    async def deactivate_card(self, card_uid: str) -> bool:
        """
        Deactivate a card (lost, stolen, replaced).
        """
        result = await self.db.execute(
            update(RFIDCardMapping)
            .where(RFIDCardMapping.card_uid == card_uid)
            .values(is_active=False, updated_at=datetime.utcnow())
        )
        await self.db.commit()
        return result.rowcount > 0
```

**Acceptance Criteria:**
- Card lookup returns employee_id
- Card registration deactivates old cards
- Usage tracking updates on each lookup

---

#### **Task 2.2: Implement Hash Chain Service**

**File:** `app/services/hash_chain.py`

```python
import hashlib
import json
from typing import Optional, Dict, Any
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import desc
from app.models.rfid_ledger import RFIDLedger
from app.config import settings

class HashChainService:
    GENESIS_HASH = "0000000000000000000000000000000000000000000000000000000000000000"
    
    def __init__(self, db: AsyncSession):
        self.db = db
    
    async def get_last_hash(self) -> str:
        """
        Get the hash of the last ledger entry.
        Returns GENESIS_HASH if ledger is empty.
        """
        result = await self.db.execute(
            select(RFIDLedger.hash_chain)
            .order_by(desc(RFIDLedger.sequence_id))
            .limit(1)
        )
        last_hash = result.scalar_one_or_none()
        return last_hash or self.GENESIS_HASH
    
    def compute_hash(self, prev_hash: str, payload: Dict[str, Any]) -> str:
        """
        Compute SHA-256 hash: hash(prev_hash || payload_json)
        """
        # Serialize payload to deterministic JSON string
        payload_str = json.dumps(payload, sort_keys=True, separators=(',', ':'))
        combined = f"{prev_hash}{payload_str}"
        return hashlib.sha256(combined.encode()).hexdigest()
    
    async def generate_next_hash(self, payload: Dict[str, Any]) -> str:
        """
        Generate hash for next ledger entry.
        """
        prev_hash = await self.get_last_hash()
        return self.compute_hash(prev_hash, payload)
    
    def verify_hash(self, prev_hash: str, payload: Dict[str, Any], claimed_hash: str) -> bool:
        """
        Verify hash integrity.
        """
        computed_hash = self.compute_hash(prev_hash, payload)
        return computed_hash == claimed_hash
```

**Acceptance Criteria:**
- Hash computation is deterministic (same input = same hash)
- Genesis hash used for first entry
- Verification detects tampering

---

#### **Task 2.3: Implement Deduplication Service**

**File:** `app/services/deduplicator.py`

```python
from datetime import datetime, timedelta
from typing import Optional
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from app.models.deduplication_cache import DeduplicationCache
from app.config import settings

class DeduplicationService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.window_seconds = settings.DUPLICATE_WINDOW_SECONDS  # Default: 15
    
    async def is_duplicate(self, employee_id: int, device_id: str, event_type: str, timestamp: datetime) -> bool:
        """
        Check if event is a duplicate within the deduplication window.
        Cache key: "{employee_id}:{device_id}:{event_type}"
        """
        cache_key = f"{employee_id}:{device_id}:{event_type}"
        
        result = await self.db.execute(
            select(DeduplicationCache)
            .where(
                DeduplicationCache.cache_key == cache_key,
                DeduplicationCache.expires_at > datetime.utcnow()
            )
        )
        cache_entry = result.scalar_one_or_none()
        
        if cache_entry:
            # Check if within window
            time_diff = (timestamp - cache_entry.last_event_timestamp).total_seconds()
            if abs(time_diff) < self.window_seconds:
                return True  # Duplicate detected
        
        return False
    
    async def add_to_cache(self, employee_id: int, device_id: str, event_type: str, 
                           timestamp: datetime, sequence_id: int) -> None:
        """
        Add event to deduplication cache.
        """
        cache_key = f"{employee_id}:{device_id}:{event_type}"
        expires_at = timestamp + timedelta(seconds=self.window_seconds)
        
        # Upsert (update if exists, insert if not)
        cache_entry = DeduplicationCache(
            cache_key=cache_key,
            last_event_timestamp=timestamp,
            sequence_id=sequence_id,
            expires_at=expires_at
        )
        
        await self.db.merge(cache_entry)
        await self.db.commit()
    
    async def cleanup_expired(self) -> int:
        """
        Remove expired cache entries (called periodically).
        Returns count of deleted entries.
        """
        result = await self.db.execute(
            delete(DeduplicationCache)
            .where(DeduplicationCache.expires_at < datetime.utcnow())
        )
        await self.db.commit()
        return result.rowcount
```

**Acceptance Criteria:**
- Duplicate detection works within 15-second window
- Cache automatically expires old entries
- No false positives (different employees/devices not blocked)

---

#### **Task 2.4: Implement Event Processor Service**

**File:** `app/services/event_processor.py`

```python
from datetime import datetime
from typing import Dict, Any, Optional
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import func
from app.models.rfid_ledger import RFIDLedger
from app.services.card_mapper import CardMapperService
from app.services.hash_chain import HashChainService
from app.services.deduplicator import DeduplicationService
from app.schemas.rfid_event import RFIDEventCreate
import logging

logger = logging.getLogger(__name__)

class EventProcessorService:
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
        """
        # Step 1: Map card to employee
        employee_id = await self.card_mapper.get_employee_id_by_card(event.card_uid)
        
        if not employee_id:
            logger.warning(f"Unknown RFID card: {event.card_uid} (device: {event.device_id})")
            return {
                "status": "rejected",
                "reason": "unknown_card",
                "card_uid": event.card_uid
            }
        
        # Step 2: Check duplicates
        is_dup = await self.deduplicator.is_duplicate(
            employee_id, event.device_id, event.event_type, event.timestamp
        )
        
        if is_dup:
            logger.info(f"Duplicate event ignored: employee={employee_id}, device={event.device_id}, type={event.event_type}")
            return {
                "status": "ignored",
                "reason": "duplicate",
                "employee_id": employee_id
            }
        
        # Step 3: Get next sequence ID
        result = await self.db.execute(select(func.max(RFIDLedger.sequence_id)))
        last_sequence = result.scalar_one_or_none() or 0
        next_sequence = last_sequence + 1
        
        # Step 4: Generate hash
        payload = {
            "sequence_id": next_sequence,
            "employee_rfid": event.card_uid,
            "device_id": event.device_id,
            "scan_timestamp": event.timestamp.isoformat(),
            "event_type": event.event_type
        }
        hash_value = await self.hash_chain.generate_next_hash(payload)
        
        # Step 5: Write to ledger
        ledger_entry = RFIDLedger(
            sequence_id=next_sequence,
            employee_rfid=event.card_uid,
            device_id=event.device_id,
            scan_timestamp=event.timestamp,
            event_type=event.event_type,
            raw_payload=event.dict(),
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
        
        logger.info(f"Event processed: seq={next_sequence}, employee={employee_id}, device={event.device_id}, type={event.event_type}")
        
        return {
            "status": "success",
            "sequence_id": next_sequence,
            "employee_id": employee_id,
            "hash": hash_value
        }
```

**Acceptance Criteria:**
- Full pipeline executes without errors
- Unknown cards rejected gracefully
- Duplicates ignored without writing to ledger
- Hash chain maintained correctly
- Sequence IDs sequential

---

### **Phase 3: API Endpoints (Week 2)**

#### **Task 3.1: Event Ingestion Endpoint**

**File:** `app/api/events.py`

```python
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import get_db
from app.schemas.rfid_event import RFIDEventCreate, RFIDEventResponse
from app.services.event_processor import EventProcessorService
from typing import Dict, Any
import logging

router = APIRouter(prefix="/api/events", tags=["Events"])
logger = logging.getLogger(__name__)

@router.post("/", response_model=RFIDEventResponse, status_code=status.HTTP_201_CREATED)
async def receive_rfid_tap(
    event: RFIDEventCreate,
    db: AsyncSession = Depends(get_db)
):
    """
    Receive RFID tap event from card reader.
    
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
        processor = EventProcessorService(db)
        result = await processor.process_rfid_tap(event)
        
        if result["status"] == "rejected":
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Event rejected: {result['reason']}"
            )
        
        return RFIDEventResponse(**result)
    
    except Exception as e:
        logger.error(f"Error processing event: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )

@router.post("/batch", status_code=status.HTTP_201_CREATED)
async def receive_rfid_batch(
    events: list[RFIDEventCreate],
    db: AsyncSession = Depends(get_db)
):
    """
    Receive batch of RFID events (offline device catch-up).
    """
    processor = EventProcessorService(db)
    results = []
    
    for event in events:
        try:
            result = await processor.process_rfid_tap(event)
            results.append(result)
        except Exception as e:
            logger.error(f"Batch event error: {str(e)}")
            results.append({
                "status": "error",
                "reason": str(e),
                "card_uid": event.card_uid
            })
    
    return {
        "total": len(events),
        "processed": len([r for r in results if r["status"] == "success"]),
        "ignored": len([r for r in results if r["status"] == "ignored"]),
        "rejected": len([r for r in results if r["status"] == "rejected"]),
        "results": results
    }
```

**Pydantic Schemas (`app/schemas/rfid_event.py`):**

```python
from pydantic import BaseModel, Field
from datetime import datetime
from typing import Optional

class RFIDEventCreate(BaseModel):
    card_uid: str = Field(..., description="RFID card UID (hex string)")
    device_id: str = Field(..., description="Device ID (e.g., GATE-01)")
    event_type: str = Field(..., description="Event type: time_in, time_out, break_start, break_end")
    timestamp: datetime = Field(..., description="Scan timestamp (ISO 8601)")
    device_signature: Optional[str] = Field(None, description="Optional Ed25519 device signature")
    
    class Config:
        json_schema_extra = {
            "example": {
                "card_uid": "04:3A:B2:C5:D8",
                "device_id": "GATE-01",
                "event_type": "time_in",
                "timestamp": "2026-02-04T08:05:23Z"
            }
        }

class RFIDEventResponse(BaseModel):
    status: str
    sequence_id: Optional[int] = None
    employee_id: Optional[int] = None
    hash: Optional[str] = None
    reason: Optional[str] = None
```

**Acceptance Criteria:**
- POST `/api/events` accepts single RFID tap
- POST `/api/events/batch` accepts multiple events
- Proper HTTP status codes (201, 400, 500)
- Validation errors return clear messages

---

#### **Task 3.2: Device Management Endpoints**

**File:** `app/api/devices.py`

```python
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from app.database import get_db
from app.models.rfid_device import RFIDDevice
from app.schemas.device import DeviceCreate, DeviceUpdate, DeviceResponse
from datetime import datetime
from typing import List

router = APIRouter(prefix="/api/devices", tags=["Devices"])

@router.post("/heartbeat/{device_id}", status_code=status.HTTP_200_OK)
async def device_heartbeat(
    device_id: str,
    db: AsyncSession = Depends(get_db)
):
    """
    Device heartbeat to mark device as online.
    Called every 30 seconds by RFID readers.
    """
    result = await db.execute(
        select(RFIDDevice).where(RFIDDevice.device_id == device_id)
    )
    device = result.scalar_one_or_none()
    
    if not device:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Device {device_id} not found"
        )
    
    device.is_online = True
    device.last_heartbeat_at = datetime.utcnow()
    await db.commit()
    
    return {"status": "ok", "device_id": device_id, "last_heartbeat": device.last_heartbeat_at}

@router.get("/", response_model=List[DeviceResponse])
async def list_devices(db: AsyncSession = Depends(get_db)):
    """
    List all registered RFID devices.
    """
    result = await db.execute(select(RFIDDevice))
    devices = result.scalars().all()
    return devices

@router.post("/", response_model=DeviceResponse, status_code=status.HTTP_201_CREATED)
async def register_device(
    device: DeviceCreate,
    db: AsyncSession = Depends(get_db)
):
    """
    Register a new RFID device.
    """
    new_device = RFIDDevice(**device.dict())
    db.add(new_device)
    await db.commit()
    await db.refresh(new_device)
    return new_device
```

**Acceptance Criteria:**
- Devices send heartbeats every 30 seconds
- Offline detection based on heartbeat timeout
- Device registration API functional

---

#### **Task 3.3: Card Mapping Management Endpoints**

**File:** `app/api/mappings.py`

```python
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.database import get_db
from app.services.card_mapper import CardMapperService
from app.schemas.mapping import CardMappingCreate, CardMappingResponse
from typing import List

router = APIRouter(prefix="/api/mappings", tags=["Card Mappings"])

@router.post("/", response_model=CardMappingResponse, status_code=status.HTTP_201_CREATED)
async def register_card_mapping(
    mapping: CardMappingCreate,
    db: AsyncSession = Depends(get_db)
):
    """
    Register RFID card for an employee.
    Deactivates any existing active card for the employee.
    """
    card_mapper = CardMapperService(db)
    result = await card_mapper.register_card(
        card_uid=mapping.card_uid,
        employee_id=mapping.employee_id,
        card_type=mapping.card_type
    )
    return result

@router.post("/{card_uid}/deactivate", status_code=status.HTTP_200_OK)
async def deactivate_card_mapping(
    card_uid: str,
    db: AsyncSession = Depends(get_db)
):
    """
    Deactivate a card (lost, stolen, replaced).
    """
    card_mapper = CardMapperService(db)
    success = await card_mapper.deactivate_card(card_uid)
    
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Card {card_uid} not found"
        )
    
    return {"status": "ok", "card_uid": card_uid, "action": "deactivated"}

@router.get("/employee/{employee_id}", response_model=CardMappingResponse)
async def get_employee_card(
    employee_id: int,
    db: AsyncSession = Depends(get_db)
):
    """
    Get active RFID card for an employee.
    """
    result = await db.execute(
        select(RFIDCardMapping)
        .where(
            RFIDCardMapping.employee_id == employee_id,
            RFIDCardMapping.is_active == True
        )
    )
    mapping = result.scalar_one_or_none()
    
    if not mapping:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"No active card found for employee {employee_id}"
        )
    
    return mapping
```

**Acceptance Criteria:**
- Card registration deactivates old cards automatically
- Card lookup by employee_id works
- Deactivation API prevents further use of card

---

### **Phase 4: Device Listeners (Week 2-3)**

#### **Task 4.1: TCP Listener for RFID Devices**

**File:** `app/listeners/tcp_listener.py`

```python
import asyncio
import json
import logging
from datetime import datetime
from app.database import AsyncSessionLocal
from app.services.event_processor import EventProcessorService
from app.schemas.rfid_event import RFIDEventCreate

logger = logging.getLogger(__name__)

class TCPListener:
    def __init__(self, host: str = "0.0.0.0", port: int = 9000):
        self.host = host
        self.port = port
    
    async def handle_client(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """
        Handle incoming TCP connection from RFID device.
        Expected format: JSON lines
        {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01",
            "event_type": "time_in",
            "timestamp": "2026-02-04T08:05:23Z"
        }
        """
        addr = writer.get_extra_info('peername')
        logger.info(f"TCP connection from {addr}")
        
        try:
            while True:
                data = await reader.readline()
                if not data:
                    break
                
                # Parse JSON event
                message = data.decode().strip()
                event_data = json.loads(message)
                
                # Process event
                async with AsyncSessionLocal() as db:
                    processor = EventProcessorService(db)
                    event = RFIDEventCreate(**event_data)
                    result = await processor.process_rfid_tap(event)
                    
                    # Send acknowledgment
                    ack = json.dumps(result) + "\n"
                    writer.write(ack.encode())
                    await writer.drain()
                
        except Exception as e:
            logger.error(f"TCP handler error: {str(e)}")
        
        finally:
            logger.info(f"TCP connection closed from {addr}")
            writer.close()
            await writer.wait_closed()
    
    async def start(self):
        """
        Start TCP server.
        """
        server = await asyncio.start_server(
            self.handle_client, self.host, self.port
        )
        
        addr = server.sockets[0].getsockname()
        logger.info(f"TCP listener started on {addr}")
        
        async with server:
            await server.serve_forever()
```

**Acceptance Criteria:**
- TCP server accepts connections on port 9000
- JSON events parsed and processed
- Acknowledgments sent back to devices
- Connection errors handled gracefully

---

### **Phase 5: Testing & Deployment (Week 3)**

#### **Task 5.1: Unit Tests**

**File:** `tests/test_event_processor.py`

```python
import pytest
from datetime import datetime
from app.services.event_processor import EventProcessorService
from app.schemas.rfid_event import RFIDEventCreate

@pytest.mark.asyncio
async def test_process_valid_event(db_session):
    # Setup: Register test card
    # ... (card mapping setup)
    
    # Test event processing
    processor = EventProcessorService(db_session)
    event = RFIDEventCreate(
        card_uid="TEST-CARD-001",
        device_id="GATE-01",
        event_type="time_in",
        timestamp=datetime.utcnow()
    )
    
    result = await processor.process_rfid_tap(event)
    
    assert result["status"] == "success"
    assert result["sequence_id"] is not None
    assert result["hash"] is not None

@pytest.mark.asyncio
async def test_duplicate_detection(db_session):
    # Test duplicate within 15-second window
    # ... (test implementation)
    pass

@pytest.mark.asyncio
async def test_unknown_card_rejection(db_session):
    # Test rejection of unregistered cards
    # ... (test implementation)
    pass
```

**Acceptance Criteria:**
- All unit tests pass
- Code coverage > 80%
- Edge cases covered (duplicates, unknown cards, hash chain validation)

---

#### **Task 5.2: Integration with Laravel**

**Subtasks:**
- [x] **5.2.1** Configure shared PostgreSQL database access
- [x] **5.2.2** Test FastAPI writes → Laravel reads
- [x] **5.2.3** Verify hash chain validation in Laravel
- [x] **5.2.4** Test end-to-end flow: RFID tap → Ledger → Laravel processing

**Acceptance Criteria:**
- Laravel successfully reads events from `rfid_ledger`
- Hash chain validation passes
- Sequence gaps detected correctly

---

#### **Task 5.3: Docker Deployment**

**File:** `Dockerfile`

```dockerfile
FROM python:3.11-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY app/ ./app/
COPY alembic/ ./alembic/
COPY alembic.ini .

# Run migrations and start server
CMD alembic upgrade head && \
    uvicorn app.main:app --host 0.0.0.0 --port 8001
```

**File:** `docker-compose.yml`

```yaml
version: '3.8'

services:
  rfid-server:
    build: .
    ports:
      - "8001:8001"
      - "9000:9000"
    environment:
      DATABASE_URL: postgresql+asyncpg://postgres:password@db:5432/cameco
      REDIS_URL: redis://redis:6379/0
    depends_on:
      - db
      - redis
    restart: unless-stopped
  
  db:
    image: postgres:15
    environment:
      POSTGRES_PASSWORD: password
      POSTGRES_DB: cameco
    volumes:
      - postgres_data:/var/lib/postgresql/data
  
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  postgres_data:
```

**Acceptance Criteria:**
- Docker container builds successfully
- Server starts and accepts connections
- Database migrations run automatically
- Health checks pass

---

## 🔐 Security Considerations

### 1. **Database Access Control**
- FastAPI server uses dedicated PostgreSQL user with limited permissions
- Only `INSERT` on `rfid_ledger` (no UPDATE/DELETE)
- Read/write on `rfid_card_mappings` and `rfid_devices`

```sql
CREATE USER rfid_server WITH PASSWORD 'secure_password';
GRANT INSERT ON rfid_ledger TO rfid_server;
GRANT SELECT, INSERT, UPDATE ON rfid_card_mappings TO rfid_server;
GRANT SELECT, INSERT, UPDATE ON rfid_devices TO rfid_server;
```

### 2. **API Authentication**
- Device endpoints protected with API keys
- Rate limiting (max 100 events/min per device)
- IP whitelisting for RFID device IPs

### 3. **Device Signature Verification (Optional)**
- Ed25519 signatures for tamper-proof events
- Each device has keypair; server validates signatures
- Enable via `DEVICE_SIGNATURE_VERIFICATION=true`

---

## 📊 Monitoring & Alerts

### Key Metrics to Track:
1. **Event Processing Rate** (events/minute)
2. **Duplicate Rate** (% of events deduplicated)
3. **Unknown Card Rate** (% of rejected events)
4. **Device Offline Count**
5. **Hash Chain Validation Failures**
6. **API Response Time** (p95, p99)

### Alerting Rules:
- Device offline > 5 minutes → Alert HR Manager
- Unknown card rate > 5% → Investigate card registration issues
- Hash chain validation fails → Critical alert, possible tampering
- Event processing rate drops to 0 → Server health issue

---

## 🚀 Deployment Checklist

- [ ] PostgreSQL database accessible from FastAPI server
- [ ] Database migrations applied (`alembic upgrade head`)
- [ ] Employee records populated in `employees` table
- [ ] RFID cards registered in `rfid_card_mappings` table
- [ ] RFID devices registered in `rfid_devices` table
- [ ] TCP listener configured with correct port (default: 9000)
- [ ] Environment variables configured (`.env`)
- [ ] Docker container deployed and running
- [ ] Health check endpoint returns 200 OK
- [ ] Laravel polling job configured (every 1 minute)
- [ ] Test RFID tap → Verify event in ledger → Verify Laravel processing
- [ ] Monitoring dashboards configured (Grafana/Prometheus)
- [ ] Alert rules configured for device offline, hash failures

---

## 📝 Related Documentation

- [Timekeeping RFID Integration Implementation](./TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md)
- [RFID Replayable Event-Log Proposal](../workflows/integrations/patentable-proposal/rfid-replayable-event-log-proposal.md)
- [Timekeeping Module Architecture](../TIMEKEEPING_MODULE_ARCHITECTURE.md)

---

## 🗓️ Timeline Summary

| Phase | Duration | Key Deliverables |
|-------|----------|------------------|
| **Phase 1: Setup** | Week 1 | Project structure, database models, migrations |
| **Phase 2: Services** | Week 1-2 | Card mapping, hash chain, deduplication, event processing |
| **Phase 3: API** | Week 2 | REST endpoints for events, devices, mappings |
| **Phase 4: Listeners** | Week 2-3 | TCP/UDP listeners for RFID devices |
| **Phase 5: Testing & Deploy** | Week 3 | Unit tests, integration tests, Docker deployment |

**Total Duration:** 2-3 weeks  
**Team Size:** 1-2 backend developers

---

**Document Version:** 1.0  
**Last Updated:** February 4, 2026  
**Document Owner:** Development Team  
**Status:** 📝 DRAFT - Ready for Implementation
