import pytest
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.orm import sessionmaker
from app.database import Base
from app.config import settings, Settings
from httpx import AsyncClient
import asyncio


# Test database URL (use a separate test database)
TEST_DATABASE_URL = "postgresql+asyncpg://postgres:password@localhost:5432/cameco_test"


@pytest.fixture(scope="session")
def event_loop():
    """Create an instance of the default event loop for the test session."""
    loop = asyncio.new_event_loop()
    yield loop
    loop.close()


@pytest.fixture(scope="session")
async def test_engine():
    """Create test database engine."""
    engine = create_async_engine(TEST_DATABASE_URL, echo=True)
    
    # Create all tables
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    
    yield engine
    
    # Clean up
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
    await engine.dispose()


@pytest.fixture
async def db_session(test_engine):
    """Create a fresh database session for each test."""
    async_session = sessionmaker(
        test_engine, class_=AsyncSession, expire_on_commit=False
    )
    
    async with async_session() as session:
        # Start a transaction
        transaction = await session.begin()
        
        yield session
        
        # Rollback the transaction to clean up
        await transaction.rollback()


@pytest.fixture
def test_settings():
    """Override settings for testing."""
    original_settings = settings
    
    # Override specific settings for testing
    test_settings = Settings(
        database_url=TEST_DATABASE_URL,
        duplicate_window_seconds=5,  # Shorter for testing
        tcp_listener_enabled=False,  # Don't start listeners in tests
        udp_listener_enabled=False
    )
    
    yield test_settings
    
    # Restore original settings
    return original_settings


@pytest.fixture
async def test_client():
    """Create a test HTTP client."""
    from app.main import app
    async with AsyncClient(app=app, base_url="http://test") as client:
        yield client


@pytest.fixture
async def admin_token_headers(db_session):
    """Create admin user and return authorization headers."""
    from app.services.auth_service import AuthService
    auth_service = AuthService(db_session)
    
    # Create admin user
    admin_user = await auth_service.create_user(
        username="test_admin",
        password="admin_password",
        role="admin"
    )
    
    # Generate JWT token (simplified - in real implementation use proper JWT)
    return {"Authorization": f"Bearer test_admin_token"}


@pytest.fixture
async def user_token_headers(db_session):
    """Create regular user and return authorization headers."""
    from app.services.auth_service import AuthService
    auth_service = AuthService(db_session)
    
    # Create regular user
    user = await auth_service.create_user(
        username="test_user",
        password="user_password",
        role="user"
    )
    
    # Generate JWT token (simplified - in real implementation use proper JWT)
    return {"Authorization": f"Bearer test_user_token"}