from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    """Application settings loaded from environment variables."""
    
    # Database
    database_url: str = "postgresql+asyncpg://postgres:password@localhost:5432/cameco"
    
    # Redis (optional)
    redis_url: Optional[str] = "redis://localhost:6379/0"
    
    # Server
    host: str = "0.0.0.0"
    port: int = 8001
    
    # Security - API Authentication
    api_key_expiry_days: int = 365  # Default API key expiry
    max_api_keys_per_device: int = 5
    require_api_key_for_events: bool = True
    require_api_key_for_management: bool = True
    
    # Security - Rate Limiting
    default_rate_limit_per_minute: int = 100
    global_rate_limit_per_minute: int = 1000  # Global rate limit per IP
    rate_limit_burst_size: int = 10  # Allow short bursts
    
    # Security - IP Whitelisting
    enable_ip_whitelisting: bool = False
    global_allowed_ips: Optional[str] = None  # Comma-separated IPs/CIDR blocks
    
    # Security - Device Signature Verification
    device_signature_verification: bool = False
    ed25519_public_key: Optional[str] = None
    signature_grace_period_seconds: int = 30  # Allow clock skew
    
    # Security - Headers and HTTPS
    require_https: bool = False  # Set to True in production
    security_headers_enabled: bool = True
    cors_enabled: bool = True
    cors_origins: str = "*"  # Configure for production
    
    # Security - Session and Tokens
    session_timeout_hours: int = 24
    token_cleanup_interval_hours: int = 6
    
    # Deduplication
    duplicate_window_seconds: int = 15
    
    # Hash Chain
    hash_algorithm: str = "sha256"
    genesis_hash: str = "0000000000000000000000000000000000000000000000000000000000000000"
    
    # Logging
    log_level: str = "INFO"
    security_log_level: str = "WARNING"  # Separate log level for security events
    log_api_requests: bool = True
    log_failed_auth_attempts: bool = True
    
    # TCP Listener
    tcp_listener_host: str = "0.0.0.0"
    tcp_listener_port: int = 9000
    tcp_listener_enabled: bool = True
    
    # UDP Listener  
    udp_listener_host: str = "0.0.0.0"
    udp_listener_port: int = 9001
    udp_listener_enabled: bool = False  # Optional listener
    udp_send_acknowledgments: bool = False  # Whether to send UDP responses
    
    # Monitoring and Alerting
    metrics_enabled: bool = True
    alert_on_security_events: bool = True
    alert_webhook_url: Optional[str] = None
    
    # Database Security
    db_connection_timeout: int = 30
    db_pool_size: int = 10
    db_max_overflow: int = 20
    
    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = False
        # Allow extra environment variables to be ignored
        extra = "ignore"


settings = Settings()
