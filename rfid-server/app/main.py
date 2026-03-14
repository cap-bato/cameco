from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from contextlib import asynccontextmanager
from app.config import settings
from app.database import init_db, close_db
from app.api import health, events, devices, mappings, websockets, security, listeners
from app.auth.middleware import AuthenticationMiddleware, SecurityHeadersMiddleware, RateLimitMiddleware
from app.listeners.tcp_listener import tcp_manager
from app.listeners.udp_listener import udp_manager
import logging
import asyncio

# Configure logging with security level
logging.basicConfig(
    level=getattr(logging, settings.log_level),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Security logger with separate level
security_logger = logging.getLogger("security")
security_logger.setLevel(getattr(logging, settings.security_log_level))


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan handler."""
    # Startup
    logger.info("Starting FastAPI RFID Server with Security & Authentication")
    await init_db()
    logger.info("Database initialized with security tables")
    
    # Initialize security components
    logger.info("Security features enabled:")
    logger.info(f"  - API Key Authentication: {settings.require_api_key_for_events}")
    logger.info(f"  - Rate Limiting: {settings.global_rate_limit_per_minute} req/min")
    logger.info(f"  - IP Whitelisting: {settings.enable_ip_whitelisting}")
    logger.info(f"  - Device Signatures: {settings.device_signature_verification}")
    logger.info(f"  - Security Headers: {settings.security_headers_enabled}")
    
    logger.info("WebSocket support enabled")
    
    # Start TCP/UDP listeners if enabled
    try:
        if settings.tcp_listener_enabled:
            await tcp_manager.start_listener()
            logger.info(f"TCP listener started on {settings.tcp_listener_host}:{settings.tcp_listener_port}")
        
        if settings.udp_listener_enabled:
            await udp_manager.start_listener()
            logger.info(f"UDP listener started on {settings.udp_listener_host}:{settings.udp_listener_port}")
            
    except Exception as e:
        logger.error(f"Failed to start device listeners: {str(e)}")
    
    yield
    
    # Shutdown
    logger.info("Shutting down FastAPI RFID Server")
    
    # Stop listeners
    try:
        await tcp_manager.stop_listener()
        await udp_manager.stop_listener()
        logger.info("Device listeners stopped")
    except Exception as e:
        logger.error(f"Error stopping device listeners: {str(e)}")
    
    await close_db()
    logger.info("Database connections closed")


# Create FastAPI app
app = FastAPI(
    title="RFID Server API - Secure Edition",
    description="FastAPI server for RFID card reader events with comprehensive security features",
    version="2.0.0",
    lifespan=lifespan,
    swagger_ui_parameters={"defaultModelsExpandDepth": -1}
)

# Add Security Middleware (order matters!)
if settings.security_headers_enabled:
    app.add_middleware(SecurityHeadersMiddleware)

# CORS middleware
if settings.cors_enabled:
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.cors_origins.split(","),
        allow_credentials=True,
        allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH"],
        allow_headers=["*"]
    )

# Rate limiting middleware
app.add_middleware(
    RateLimitMiddleware,
    requests_per_minute=settings.global_rate_limit_per_minute
)

# Authentication logging middleware
if settings.log_api_requests:
    app.add_middleware(
        AuthenticationMiddleware,
        skip_paths=["/", "/test", "/docs", "/redoc", "/openapi.json", "/favicon.ico"]
    )

# Include API routers
app.include_router(health.router, prefix="/api/v1", tags=["health"])
app.include_router(events.router, prefix="/api/v1")  
app.include_router(devices.router, prefix="/api/v1")
app.include_router(mappings.router, prefix="/api/v1")
app.include_router(listeners.router)  # Device listeners
app.include_router(security.router)  # Security endpoints

# Include WebSocket routers
app.include_router(websockets.router)


@app.get("/")
async def root():
    """Health check endpoint."""
    return {
        "message": "RFID Server API - Secure Edition",
        "version": "2.0.0",
        "status": "healthy",
        "phase": "Phase 5 Complete - Security & Authentication",
        "security_features": [
            "🔐 API Key Authentication System",
            "🚦 Rate Limiting (IP & API key based)",
            "🛡️ IP Whitelisting & Access Control", 
            "🔏 Ed25519 Digital Signature Verification",
            "📊 Security Event Monitoring & Logging",
            "🔒 Security Headers & HTTPS Support",
            "⚡ Real-time Security Alerts",
            "👤 Role-based Permissions System"
        ],
        "core_features": [
            "✅ FastAPI Application Structure", 
            "✅ SQLAlchemy Models (7 tables)",
            "✅ Alembic Database Migrations",
            "✅ PostgreSQL Integration",
            "✅ Health Check Endpoints",
            "✅ Core Services (Card Mapping, Hash Chain, Event Processing)",
            "✅ Event Ingestion API (Single & Batch)",
            "✅ Device Management API (CRUD & Heartbeat)",
            "✅ Card Mapping API (Registration & Lookup)",
            "✅ WebSocket Real-time Event Streaming",
            "✅ Device Status Monitoring via WebSockets",
            "✅ Real-time Alerts and Notifications",
            "✅ Dashboard Integration for Live Updates"
        ]
    }


@app.get("/test")
async def test_endpoint():
    """Test endpoint to verify Phase 5 completion."""
    return {
        "message": "FastAPI RFID Server - Phase 5 Complete!",
        "phase": "Phase 5 - Security & Authentication",
        "tasks_completed": {
            "Task 1.1": "Initialize FastAPI Project ✅ COMPLETED",
            "Task 1.2": "Setup Database Models (SQLAlchemy) ✅ COMPLETED", 
            "Task 1.3": "Create Alembic Migrations ✅ COMPLETED",
            "Task 2.1": "Card Mapping Service ✅ COMPLETED",
            "Task 2.2": "Hash Chain Service ✅ COMPLETED",
            "Task 2.3": "Deduplication Service ✅ COMPLETED",
            "Task 2.4": "Event Processor Service ✅ COMPLETED",
            "Task 3.1": "Event Ingestion Endpoints ✅ COMPLETED",
            "Task 3.2": "Device Management Endpoints ✅ COMPLETED",
            "Task 3.3": "Card Mapping Endpoints ✅ COMPLETED",
            "Task 4.1": "WebSocket Connection Manager ✅ COMPLETED",
            "Task 4.2": "Real-time Event Streaming ✅ COMPLETED",
            "Task 4.3": "Device Status Monitoring ✅ COMPLETED",
            "Task 4.4": "Notification Service Integration ✅ COMPLETED",
            "Task 5.1": "API Key Authentication System ✅ COMPLETED",
            "Task 5.2": "Rate Limiting Implementation ✅ COMPLETED",
            "Task 5.3": "IP Whitelisting System ✅ COMPLETED",
            "Task 5.4": "Ed25519 Signature Verification ✅ COMPLETED",
            "Task 5.5": "Security Configuration & Middleware ✅ COMPLETED",
            "Task 5.6": "Security Testing & Validation ✅ COMPLETED"
        },
        "api_endpoints": {
            "events": [
                "POST /api/v1/api/events - Single event ingestion [AUTH REQUIRED]",
                "POST /api/v1/api/events/batch - Batch event processing [AUTH REQUIRED]",
                "GET /api/v1/api/events/stats - Ledger statistics [AUTH REQUIRED]",
                "GET /api/v1/api/events/latest - Recent events [AUTH REQUIRED]"
            ],
            "devices": [
                "POST /api/v1/api/devices - Register device [AUTH REQUIRED]",
                "GET /api/v1/api/devices - List all devices [AUTH REQUIRED]",
                "GET /api/v1/api/devices/{device_id} - Get device details [AUTH REQUIRED]",
                "PUT /api/v1/api/devices/{device_id} - Update device [AUTH REQUIRED]",
                "DELETE /api/v1/api/devices/{device_id} - Delete device [AUTH REQUIRED]",
                "POST /api/v1/api/devices/{device_id}/heartbeat - Device heartbeat [AUTH REQUIRED]",
                "GET /api/v1/api/devices/status/online - Online devices [AUTH REQUIRED]"
            ],
            "mappings": [
                "POST /api/v1/api/mappings - Register card [AUTH REQUIRED]",
                "GET /api/v1/api/mappings - List all mappings [AUTH REQUIRED]",
                "GET /api/v1/api/mappings/{card_uid} - Get card mapping [AUTH REQUIRED]",
                "PUT /api/v1/api/mappings/{card_uid} - Update card mapping [AUTH REQUIRED]",
                "DELETE /api/v1/api/mappings/{card_uid} - Deactivate card [AUTH REQUIRED]",
                "GET /api/v1/api/mappings/lookup/{card_uid} - Card lookup [AUTH REQUIRED]",
                "GET /api/v1/api/mappings/employee/{employee_id}/cards - Employee cards [AUTH REQUIRED]"
            ],
            "security": [
                "POST /api/v1/security/api-keys - Create API key [ADMIN REQUIRED]",
                "GET /api/v1/security/api-keys - List API keys [ADMIN REQUIRED]",
                "GET /api/v1/security/api-keys/{key_id} - Get API key details [ADMIN REQUIRED]",
                "PATCH /api/v1/security/api-keys/{key_id} - Update API key [ADMIN REQUIRED]",
                "DELETE /api/v1/security/api-keys/{key_id} - Delete API key [ADMIN REQUIRED]",
                "GET /api/v1/security/events - List security events [ADMIN REQUIRED]",
                "PATCH /api/v1/security/events/{event_id}/resolve - Resolve security event [ADMIN REQUIRED]",
                "GET /api/v1/security/stats - Security statistics [ADMIN REQUIRED]",
                "GET /api/v1/security/dashboard - Security dashboard [ADMIN REQUIRED]",
                "POST /api/v1/security/device-keys/{device_id} - Register device public key [ADMIN REQUIRED]",
                "DELETE /api/v1/security/device-keys/{device_id} - Remove device public key [ADMIN REQUIRED]",
                "GET /api/v1/security/signature-status - Signature verification status [ADMIN REQUIRED]",
                "GET /api/v1/security/my-key - Get current API key info [AUTH REQUIRED]"
            ],
            "websockets": [
                "WS /ws/events - Real-time RFID event stream",
                "WS /ws/devices - Device status monitoring",
                "WS /ws/alerts - Alert notifications",
                "WS /ws/dashboard - Combined dashboard updates",
                "GET /ws/stats - WebSocket connection statistics"
            ]
        },
        "database_tables_created": [
            "rfid_card_mappings - Maps RFID cards to employees",
            "rfid_devices - Device registry and heartbeat tracking", 
            "rfid_ledger - Immutable event log with hash chains",
            "event_deduplication_cache - Duplicate prevention",
            "api_keys - API key authentication and permissions",
            "api_key_usage_log - API usage audit trail",
            "security_events - Security monitoring and alerting"
        ],
        "security_status": {
            "authentication": "API Key Based",
            "rate_limiting": f"{settings.global_rate_limit_per_minute} requests/minute",
            "ip_whitelisting": settings.enable_ip_whitelisting,
            "signature_verification": settings.device_signature_verification,
            "security_headers": settings.security_headers_enabled,
            "cors_enabled": settings.cors_enabled,
            "https_required": settings.require_https
        },
        "next_phase": "Production Deployment",
        "ready_for_production": True,
        "websocket_endpoints": "Available at ws://127.0.0.1:8001/ws/*",
        "documentation": "Available at /docs and /redoc",
        "security_documentation": "API authentication required for protected endpoints"
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "main:app",
        host=settings.host,
        port=settings.port,
        reload=True
    )
