# FastAPI RFID Server

## Enterprise-Grade RFID Timekeeping System

A high-performance FastAPI microservice for RFID-based employee time tracking, integrating with Cameco's existing Laravel timekeeping system via shared PostgreSQL database.

## 🚀 Features

### Core Capabilities
- ✅ **Real-time RFID Event Processing** - Asynchronous handling of employee card scans
- ✅ **Cryptographic Hash Chain** - Immutable audit trail with SHA-256 hash chains
- ✅ **Duplicate Detection** - Smart deduplication with configurable time windows
- ✅ **Device Management** - Multi-device support with heartbeat monitoring
- ✅ **Direct Device Communication** - TCP/UDP listeners for local RFID readers
- ✅ **WebSocket Real-time Updates** - Live event streaming to connected clients
- ✅ **Comprehensive Security** - JWT authentication, API keys, admin logging

### Advanced Features
- 🔐 **Multi-layer Authentication** - JWT tokens + API keys for devices
- 📊 **Hash Chain Integrity** - Tamper-evident ledger with cryptographic verification
- 🔄 **Batch Processing** - Efficient bulk event handling
- 📡 **WebSocket Integration** - Real-time event broadcasting
- 🌐 **CORS Support** - Configurable cross-origin access for web frontends
- 📝 **Detailed Logging** - Comprehensive audit trails and admin action logs

---

## 📋 Table of Contents
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Installation](#installation)
- [Configuration](#configuration)
- [API Documentation](#api-documentation)
- [Device Integration](#device-integration)
- [Testing](#testing)
- [Deployment](#deployment)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)

---

## 🏗️ Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     RFID Devices Network                        │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐           │
│  │ GATE-01 │  │ GATE-02 │  │ GATE-03 │  │ Office  │           │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘           │
└───────┼────────────┼────────────┼────────────┼─────────────────┘
        │            │            │            │
        │ TCP/UDP:9000/9001      │            │
        ├────────────┴────────────┴────────────┘
        │
        ▼
┌──────────────────────────────────────────────────────────────────┐
│              FastAPI RFID Server (Port 8000)                     │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  API Layer                                                 │  │
│  │  • RESTful Endpoints                                       │  │
│  │  • WebSocket Connections                                   │  │
│  │  • JWT Authentication                                      │  │
│  └────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Device Listeners                                          │  │
│  │  • TCP Listener (Port 9000)                                │  │
│  │  • UDP Listener (Port 9001)                                │  │
│  └────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Services Layer                                            │  │
│  │  • Event Processor   • Hash Chain                          │  │
│  │  • Card Mapper       • Deduplicator                        │  │
│  │  • Auth Service      • Notification Service                │  │
│  └────────────────────────────────────────────────────────────┘  │
└────────────────────────────────┬─────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────┐
│              Shared PostgreSQL Database                          │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  RFID Tables                                               │  │
│  │  • rfid_access_events  • rfid_devices                      │  │
│  │  • rfid_time_entries   • rfid_event_log                    │  │
│  │  • rfid_chain_log      • admin_logs                        │  │
│  └────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Laravel Shared Tables (Read-Only Access)                  │  │
│  │  • employees           • rfid_card_mappings                │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Tech Stack
- **Framework**: FastAPI 2.0.0 (Python 3.11+)
- **Database**: PostgreSQL 15+ with async SQLAlchemy 2.0.35
- **Authentication**: JWT + PassLib (Bcrypt)
- **Real-time**: WebSockets
- **Deployment**: Docker + Docker Compose
- **Testing**: Pytest with async support

---

## ⚡ Quick Start

### Docker Deployment (Recommended)

```bash
# Clone repository
git clone <repo-url>
cd rfid-server

# Create environment file
cp .env.example .env
# Edit .env with your configuration

# Generate secure JWT secret
python -c "import secrets; print(secrets.token_urlsafe(32))"
# Update JWT_SECRET_KEY in .env

# Start services
docker-compose up -d

# Run database migrations
docker exec rfid-server alembic upgrade head

# Create admin user
docker exec -it rfid-server python scripts/create_admin.py

# Access API documentation
# Open browser: http://localhost:8000/docs
```

---

## 📦 Installation

### Prerequisites
- Python 3.11 or higher
- PostgreSQL 15 or higher
- Docker & Docker Compose (for containerized deployment)

### Local Development Setup

```bash
# Clone repository
git clone <repo-url>
cd rfid-server

# Create virtual environment
python -m venv venv

# Activate virtual environment
# Windows:
venv\Scripts\activate
# Linux/Mac:
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Setup environment
cp .env.example .env
# Edit .env with your configuration

# Run database migrations
alembic upgrade head

# Start development server
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

---

## ⚙️ Configuration

### Environment Variables

See [.env.example](.env.example) for all configuration options.

**Critical Settings:**
- `DATABASE_URL`: PostgreSQL connection string
- `JWT_SECRET_KEY`: **Must be changed in production!**  
- `TCP_LISTENER_ENABLED`: Enable TCP device listener (default: true)
- `UDP_LISTENER_ENABLED`: Enable UDP device listener (default: true)
- `DUPLICATE_WINDOW_SECONDS`: Deduplication time window (default: 30)

### Database Configuration

The RFID server uses a **shared database** with the Laravel application:
- Laravel handles `employees` and `rfid_card_mappings` tables
- RFID server manages its own tables via Alembic migrations
- Both systems can read/write to the database simultaneously

---

## 📖 API Documentation

### Interactive API Docs
- **Swagger UI**: http://localhost:8000/docs
- **ReDoc**: http://localhost:8000/redoc
- **OpenAPI JSON**: http://localhost:8000/openapi.json

### Key Endpoints

#### Authentication
```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "your-password"
}
```

#### RFID Events
```http
POST /api/events
Authorization: Bearer <jwt-token>
Content-Type: application/json

{
  "card_uid": "04:3A:B2:C5:D8",
  "device_id": "GATE-01",
  "event_type": "time_in",
  "timestamp": "2026-02-04T08:05:23Z"
}
```

#### Device Management
```http
POST /api/admin/devices
Authorization: Bearer <admin-jwt-token>
Content-Type: application/json

{
  "device_id": "GATE-01",
  "device_name": "Main Gate Scanner",
  "location": "Building A - Entrance"
}
```

#### Device Listeners (Admin Only)
```http
# Start TCP listener
POST /api/admin/listeners/tcp/start

# Get TCP statistics
GET /api/admin/listeners/tcp/stats

# Stop UDP listener  
POST /api/admin/listeners/udp/stop
```

### WebSocket
```javascript
const ws = new WebSocket('ws://localhost:8000/ws');
ws.onmessage = (event) => {
  const data = JSON.parse(event.data);
  console.log('RFID Event:', data);
};
```

---

## 🔌 Device Integration

### TCP Connection (Port 9000)

RFID devices connect via TCP and send JSON messages:

```json
{
  "card_uid": "04:3A:B2:C5:D8",
  "device_id": "GATE-01",
  "event_type": "time_in",
  "timestamp": "2026-02-04T08:05:23Z",
  "device_signature": "optional_signature"
}
```

Server responds with acknowledgment:
```json
{
  "status": "success",
  "event_id": 12345,
  "message": "Event processed successfully"
}
```

### UDP Connection (Port 9001)

For devices that prefer connectionless communication:
- Same JSON format as TCP
- Optional acknowledgments (configurable via `UDP_SEND_ACKNOWLEDGMENTS`)
- Suitable for broadcast scenarios

### Testing Device Connection

```bash
# Test TCP connection
echo '{"card_uid":"04:3A:B2:C5:D8","device_id":"TEST-01","event_type":"time_in","timestamp":"2026-02-04T08:05:23Z"}' | nc localhost 9000

# Test UDP connection
echo '{"card_uid":"04:3A:B2:C5:D8","device_id":"TEST-01","event_type":"time_in","timestamp":"2026-02-04T08:05:23Z"}' | nc -u localhost 9001
```

---

## 🧪 Testing

### Unit Tests

```bash
# Run all tests
pytest

# Run with coverage
pytest --cov=app --cov-report=html

# Run specific test file
pytest tests/test_event_processor.py -v

# Run specific test
pytest tests/test_event_processor.py::test_successful_event_processing -v
```

### Test Coverage
- Event processing pipeline
- Hash chain integrity
- Duplicate detection
- Authentication & authorization
- TCP/UDP device listeners
- API endpoints

---

## 🚀 Deployment

### Production Checklist
- [ ] Change `JWT_SECRET_KEY` to strong random value
- [ ] Update `CORS_ORIGINS` to production domains
- [ ] Configure SSL/TLS certificates
- [ ] Set `DEBUG=false`
- [ ] Configure firewall rules
- [ ] Setup database backups
- [ ] Configure monitoring and logging
- [ ] Test device connections
- [ ] Create admin users
- [ ] Review security settings

See [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) for complete deployment guide.

### Docker Production Deployment

```bash
# Use production compose file
docker-compose -f docker-compose.prod.yml up -d

# Check status
docker-compose ps
docker logs rfid-server-prod

# Health check
curl http://localhost:8000/health
```

---

## 📊 Monitoring

### Health Endpoints
```bash
# Application health
curl http://localhost:8000/health

# Device listener status
curl -H "Authorization: Bearer <token>" \
  http://localhost:8000/api/admin/listeners/status
```

### Logs
```bash
# Application logs
docker logs -f rfid-server

# Tail logs in volume
tail -f logs/rfid-server.log
```

### Metrics
- Total events processed
- Active TCP connections
- UDP packets received
- Duplicate events rejected
- Hash chain integrity status

---

## 🐛 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check database connectivity
psql -h localhost -U rfid_user -d rfid_db

# Verify DATABASE_URL in .env
echo $DATABASE_URL
```

**Device Cannot Connect**
```bash
# Check if ports are open
netstat -tlnp | grep :9000
netstat -ulnp | grep :9001

# Test TCP listener
telnet localhost 9000

# Check firewall rules
ufw status
```

**JWT Token Invalid**
- Ensure `JWT_SECRET_KEY` is correctly set
- Check token expiration (default 24 hours)
- Verify token format in Authorization header

**High Memory Usage**
- Increase Docker memory limits
- Optimize database connection pool size
- Check for memory leaks in logs

---

## 📁 Project Structure

```
rfid-server/
├── app/
│   ├── api/             # API endpoints
│   ├── models/          # Database models
│   ├── schemas/         # Pydantic schemas
│   ├── services/        # Business logic
│   ├── listeners/       # TCP/UDP device listeners
│   ├── database.py      # Database configuration
│   ├── config.py        # Settings management
│   └── main.py          # Application entry point
├── alembic/             # Database migrations
├── tests/               # Unit tests
├── scripts/             # Utility scripts
├── Dockerfile           # Container configuration
├── docker-compose.yml   # Development compose
├── requirements.txt     # Python dependencies
├── PRODUCTION_DEPLOYMENT.md  # Deployment guide
└── README.md           # This file
```

---

## 🤝 Contributing

1. Follow PEP 8 style guidelines
2. Write unit tests for new features
3. Update documentation
4. Test locally before committing
5. Create pull requests with clear descriptions

---

## 📄 License

Copyright © 2026 Cameco Corporation. All rights reserved.

---

## 📞 Support

- **Documentation**: http://localhost:8000/docs
- **Issues**: Contact IT Support
- **Emergency**: See PRODUCTION_DEPLOYMENT.md

---

## 🔄 Version History

- **v2.0.0** (2026-02-04): Phase 5 complete - TCP/UDP listeners, comprehensive testing
- **v1.5.0** (2026-02-03): Enhanced security with JWT and API keys
- **v1.0.0** (2026-02-01): Initial production release