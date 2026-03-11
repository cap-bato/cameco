# FastAPI RFID Server - Production Deployment Guide

## Table of Contents
1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Environment Setup](#environment-setup)
3. [Database Configuration](#database-configuration)
4. [Docker Deployment](#docker-deployment)
5. [Security Configuration](#security-configuration)
6. [Monitoring and Logging](#monitoring-and-logging)
7. [Device Integration](#device-integration)
8. [Troubleshooting](#troubleshooting)

## Pre-Deployment Checklist

### System Requirements
- **OS**: Linux (Ubuntu 20.04+ recommended) or Windows Server 2019+
- **CPU**: 2+ cores (4+ recommended for high traffic)
- **RAM**: 4GB minimum (8GB+ recommended)
- **Storage**: 20GB+ available disk space
- **Network**: Static IP address, open ports 8000, 9000, 9001

### Prerequisites
- [ ] Docker and Docker Compose installed
- [ ] PostgreSQL database accessible (shared Cameco DB or dedicated instance)
- [ ] SSL certificates for HTTPS (production)
- [ ] RFID device network configuration
- [ ] Backup and recovery procedures defined

## Environment Setup

### 1. Clone and Configure
```bash
# Clone the repository
git clone <repository-url>
cd rfid-server

# Create environment file
cp .env.example .env
```

### 2. Environment Variables
Edit `.env` file with production values:

```bash
# Database Configuration (Use shared Cameco DB)
DATABASE_URL=postgresql+asyncpg://username:password@cameco-db:5432/timekeeper_db

# Server Configuration
HOST=0.0.0.0
PORT=8000
DEBUG=false
LOG_LEVEL=INFO

# Security - GENERATE STRONG VALUES
JWT_SECRET_KEY=your-super-secret-jwt-key-minimum-32-characters
JWT_ALGORITHM=HS256
JWT_EXPIRE_MINUTES=1440

# Device Listeners
TCP_LISTENER_ENABLED=true
TCP_LISTENER_HOST=0.0.0.0
TCP_LISTENER_PORT=9000
UDP_LISTENER_ENABLED=true
UDP_LISTENER_HOST=0.0.0.0
UDP_LISTENER_PORT=9001
UDP_SEND_ACKNOWLEDGMENTS=true

# CORS - Restrict to your domains
CORS_ORIGINS=["https://timekeeper.cameco.com", "https://admin.cameco.com"]
```

### 3. Generate Secure JWT Secret
```bash
# Generate a secure JWT secret key
python -c "import secrets; print(secrets.token_urlsafe(32))"
```

## Database Configuration

### Option A: Using Shared Cameco Database
```bash
# Run database migrations on shared database
alembic upgrade head
```

Ensure the RFID tables are created in the shared `timekeeper_db`:
- `rfid_access_events`
- `rfid_devices`
- `rfid_time_entries`
- `rfid_event_log` 
- `rfid_chain_log`
- `admin_logs`
- `api_keys`

### Option B: Dedicated RFID Database
If using a separate database, modify `docker-compose.yml` to include PostgreSQL service.

## Docker Deployment

### 1. Production Docker Compose
Create `docker-compose.prod.yml`:

```yaml
version: '3.8'
services:
  rfid-server:
    build: .
    container_name: rfid-server-prod
    ports:
      - "8000:8000"
      - "9000:9000" 
      - "9001:9001"
    env_file:
      - .env
    volumes:
      - ./logs:/app/logs
      - ./data:/app/data
    restart: unless-stopped
    deploy:
      resources:
        limits:
          memory: 2G
          cpus: '1.5'
        reservations:
          memory: 1G
          cpus: '0.5'
```

### 2. Deploy Application
```bash
# Build and start services
docker-compose -f docker-compose.prod.yml up -d

# Verify deployment
docker-compose ps
docker logs rfid-server-prod

# Run database migrations
docker exec rfid-server-prod alembic upgrade head
```

### 3. Create Admin User
```bash
# Create initial admin user
docker exec -it rfid-server-prod python -c "
import asyncio
from app.database import AsyncSessionLocal
from app.services.auth import AuthService

async def create_admin():
    async with AsyncSessionLocal() as db:
        auth = AuthService(db)
        user = await auth.create_user(
            username='admin',
            password='secure-admin-password',
            role='admin'
        )
        print(f'Admin user created: {user.username}')

asyncio.run(create_admin())
"
```

## Security Configuration

### 1. Firewall Rules
```bash
# Allow only required ports
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP (redirect to HTTPS)  
ufw allow 443/tcp   # HTTPS
ufw allow 8000/tcp  # API (if direct access needed)
ufw allow 9000/tcp  # TCP device listener
ufw allow 9001/udp  # UDP device listener
ufw enable
```

### 2. SSL/TLS Configuration
For production, use Nginx reverse proxy with SSL:

```nginx
# /etc/nginx/sites-available/rfid-server
server {
    listen 443 ssl http2;
    server_name rfid.cameco.com;
    
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 3. API Key Security
```bash
# Generate secure API keys for devices
curl -X POST "https://rfid.cameco.com/api/admin/api-keys" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GATE-01-Reader",
    "permissions": ["write"],
    "expires_at": "2025-12-31T23:59:59Z"
  }'
```

## Monitoring and Logging

### 1. Log Configuration
```yaml
# docker-compose.prod.yml logging section
services:
  rfid-server:
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
```

### 2. Health Checks
```bash
# Application health endpoint
curl https://rfid.cameco.com/health

# Device listener health
curl https://rfid.cameco.com/api/admin/listeners/status
```

### 3. Performance Monitoring
```bash
# Container resource usage
docker stats rfid-server-prod

# Application logs
docker logs -f rfid-server-prod

# Database connections
docker exec rfid-server-prod python -c "
from app.database import engine
print(f'DB Pool: {engine.pool.status()}')
"
```

## Device Integration

### 1. RFID Device Configuration
Configure RFID devices to connect to:
- **TCP Endpoint**: `rfid.cameco.com:9000`
- **UDP Endpoint**: `rfid.cameco.com:9001` (optional)

### 2. Device Message Format
```json
{
    "card_uid": "04:3A:B2:C5:D8",
    "device_id": "GATE-01",
    "event_type": "time_in",
    "timestamp": "2026-02-04T08:05:23Z",
    "device_signature": "optional_signature"
}
```

### 3. Testing Device Connection
```bash
# Test TCP connection
echo '{"card_uid":"04:3A:B2:C5:D8","device_id":"TEST-01","event_type":"time_in","timestamp":"2026-02-04T08:05:23Z"}' | nc rfid.cameco.com 9000

# Test UDP connection  
echo '{"card_uid":"04:3A:B2:C5:D8","device_id":"TEST-01","event_type":"time_in","timestamp":"2026-02-04T08:05:23Z"}' | nc -u rfid.cameco.com 9001
```

## Backup and Recovery

### 1. Database Backup
```bash
# Automated daily backup
0 2 * * * docker exec postgres pg_dump -U rfid_user rfid_db > /backups/rfid_$(date +\%Y\%m\%d).sql
```

### 2. Configuration Backup
```bash
# Backup configuration files
tar -czf rfid-config-$(date +%Y%m%d).tar.gz .env docker-compose.prod.yml nginx/
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   ```bash
   # Check database connectivity
   docker exec rfid-server-prod pg_isready -h cameco-db -p 5432 -U username
   
   # Verify environment variables
   docker exec rfid-server-prod printenv | grep DATABASE
   ```

2. **Device Connection Issues**
   ```bash
   # Check if ports are open
   netstat -tlnp | grep :9000
   
   # Check TCP listener stats
   curl -H "Authorization: Bearer TOKEN" https://rfid.cameco.com/api/admin/listeners/tcp/stats
   ```

3. **High Memory Usage**
   ```bash
   # Check memory usage
   docker exec rfid-server-prod free -h
   
   # Restart if needed
   docker restart rfid-server-prod
   ```

4. **SSL Certificate Issues**
   ```bash
   # Check certificate expiry
   openssl x509 -in cert.pem -text -noout | grep "Not After"
   
   # Renew Let's Encrypt certificates
   certbot renew --nginx
   ```

### Performance Tuning

1. **Database Connection Pool**
   ```python
   # app/config.py optimization for high load
   DB_POOL_SIZE = 20
   DB_MAX_OVERFLOW = 10
   DB_POOL_TIMEOUT = 30
   ```

2. **Uvicorn Workers**
   ```bash
   # Multi-worker deployment
   CMD ["python", "-m", "uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "4"]
   ```

## Maintenance

### Regular Tasks
- [ ] Monitor disk space and logs
- [ ] Review security logs weekly
- [ ] Update dependencies monthly
- [ ] Test backup recovery quarterly
- [ ] Review device connection logs
- [ ] Performance metrics analysis

### Updates and Upgrades
```bash
# Update application
git pull origin main
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d

# Run migrations if needed
docker exec rfid-server-prod alembic upgrade head
```

## Support Contacts
- **System Administrator**: admin@cameco.com
- **IT Support**: support@cameco.com
- **Emergency Contact**: +1-XXX-XXX-XXXX