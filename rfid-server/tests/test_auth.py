import pytest
from app.services.auth_service import AuthService
from app.models.user import User
from app.models.api_key import APIKey
from app.models.admin_log import AdminLog
import hashlib
import secrets
from datetime import datetime


@pytest.mark.asyncio
async def test_create_user_success(db_session):
    """Test successful user creation."""
    auth_service = AuthService(db_session)
    
    user = await auth_service.create_user(
        username="testuser",
        password="testpassword123",
        role="admin" 
    )
    
    assert user is not None
    assert user.username == "testuser"
    assert user.role == "admin"
    assert user.password_hash != "testpassword123"  # Should be hashed 
    assert user.created_at is not None


@pytest.mark.asyncio 
async def test_create_user_duplicate_username(db_session):
    """Test that duplicate usernames are rejected."""
    auth_service = AuthService(db_session)
    
    # Create first user
    await auth_service.create_user(
        username="testuser",
        password="testpassword123",
        role="admin"
    )
    
    # Try to create duplicate
    duplicate_user = await auth_service.create_user(
        username="testuser", 
        password="differentpassword",
        role="user"
    )
    
    assert duplicate_user is None


@pytest.mark.asyncio
async def test_authenticate_user_success(db_session):
    """Test successful user authentication."""
    auth_service = AuthService(db_session)
    
    # Create user
    await auth_service.create_user(
        username="testuser",
        password="testpassword123", 
        role="admin"
    )
    
    # Authenticate
    user = await auth_service.authenticate_user("testuser", "testpassword123")
    
    assert user is not None
    assert user.username == "testuser"


@pytest.mark.asyncio
async def test_authenticate_user_wrong_password(db_session):
    """Test authentication with wrong password."""
    auth_service = AuthService(db_session)
    
    # Create user
    await auth_service.create_user(
        username="testuser",
        password="testpassword123",
        role="admin"
    )
    
    # Try with wrong password
    user = await auth_service.authenticate_user("testuser", "wrongpassword")
    
    assert user is None


@pytest.mark.asyncio 
async def test_authenticate_user_nonexistent(db_session):
    """Test authentication for non-existent user."""
    auth_service = AuthService(db_session)
    
    user = await auth_service.authenticate_user("nonexistent", "password")
    
    assert user is None


@pytest.mark.asyncio
async def test_create_api_key_success(db_session):
    """Test successful API key creation."""
    auth_service = AuthService(db_session)
    
    # Create user first
    user = await auth_service.create_user(
        username="testuser",
        password="testpassword123",
        role="admin"
    )
    
    # Create API key
    api_key = await auth_service.create_api_key(
        user_id=user.id,
        name="Test API Key",
        permissions=["read", "write"]
    )
    
    assert api_key is not None
    assert api_key.name == "Test API Key"
    assert api_key.user_id == user.id
    assert api_key.permissions == ["read", "write"]
    assert len(api_key.key_hash) > 0
    assert api_key.created_at is not None
    assert api_key.is_active == True


@pytest.mark.asyncio
async def test_validate_api_key_success(db_session):
    """Test successful API key validation."""
    auth_service = AuthService(db_session)
    
    # Create user
    user = await auth_service.create_user(
        username="testuser",
        password="testpassword123",
        role="admin"
    )
    
    # Create API key  
    api_key = await auth_service.create_api_key(
        user_id=user.id,
        name="Test API Key",
        permissions=["read", "write"]
    )
    
    # The raw key should be returned during creation for this test
    # In production, you'd get the raw key immediately after creation
    raw_key = secrets.token_urlsafe(32)
    key_hash = hashlib.sha256(raw_key.encode()).hexdigest()
    
    # Update the API key with known hash for testing
    api_key.key_hash = key_hash
    await db_session.commit()
    
    # Validate the API key
    validated_key = await auth_service.validate_api_key(raw_key)
    
    assert validated_key is not None
    assert validated_key.id == api_key.id
    assert validated_key.user_id == user.id


@pytest.mark.asyncio
async def test_validate_api_key_invalid(db_session):
    """Test validation of invalid API key."""
    auth_service = AuthService(db_session)
    
    result = await auth_service.validate_api_key("invalid_key")
    
    assert result is None


@pytest.mark.asyncio
async def test_validate_api_key_inactive(db_session):
    """Test validation of inactive API key."""
    auth_service = AuthService(db_session)
    
    # Create user and API key
    user = await auth_service.create_user(
        username="testuser",
        password="testpassword123", 
        role="admin"
    )
    
    api_key = await auth_service.create_api_key(
        user_id=user.id,
        name="Test API Key",
        permissions=["read"]  
    )
    
    # Deactivate the key
    api_key.is_active = False
    await db_session.commit()
    
    # Try to validate
    raw_key = secrets.token_urlsafe(32)
    key_hash = hashlib.sha256(raw_key.encode()).hexdigest()
    api_key.key_hash = key_hash
    await db_session.commit()
    
    validated_key = await auth_service.validate_api_key(raw_key)
    
    assert validated_key is None


@pytest.mark.asyncio
async def test_log_admin_action(db_session):
    """Test admin action logging."""
    auth_service = AuthService(db_session)
    
    # Create admin user
    admin_user = await auth_service.create_user(
        username="admin",
        password="adminpassword",
        role="admin"
    )
    
    # Log an action
    await auth_service.log_admin_action(
        user_id=admin_user.id,
        action="user_created",
        details={"target_user": "testuser", "role": "user"}
    )
    
    # Verify log entry was created 
    from sqlalchemy import select
    result = await db_session.execute(
        select(AdminLog).where(AdminLog.user_id == admin_user.id)
    )
    log_entry = result.scalar_one_or_none()
    
    assert log_entry is not None
    assert log_entry.action == "user_created"
    assert log_entry.details == {"target_user": "testuser", "role": "user"}
    assert log_entry.created_at is not None


@pytest.mark.asyncio
async def test_get_user_by_id(db_session):
    """Test getting user by ID."""
    auth_service = AuthService(db_session)
    
    # Create user
    created_user = await auth_service.create_user(
        username="testuser",
        password="testpassword123",
        role="admin"
    )
    
    # Get user by ID
    retrieved_user = await auth_service.get_user_by_id(created_user.id)
    
    assert retrieved_user is not None
    assert retrieved_user.id == created_user.id
    assert retrieved_user.username == "testuser"


@pytest.mark.asyncio
async def test_get_user_by_id_nonexistent(db_session):
    """Test getting non-existent user by ID."""
    auth_service = AuthService(db_session)
    
    user = await auth_service.get_user_by_id(99999)
    
    assert user is None


@pytest.mark.asyncio
async def test_password_hashing(db_session):
    """Test that passwords are properly hashed."""
    auth_service = AuthService(db_session)
    
    password = "testpassword123"
    
    user = await auth_service.create_user(
        username="testuser",
        password=password,
        role="admin"
    )
    
    # Password should not be stored in plain text
    assert user.password_hash != password
    assert len(user.password_hash) > 20  # Hashed passwords are longer
    
    # Should be able to authenticate with original password
    authenticated = await auth_service.authenticate_user("testuser", password) 
    assert authenticated is not None