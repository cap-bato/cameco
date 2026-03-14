import asyncio
import os
import sys
from logging.config import fileConfig

from sqlalchemy import pool 
from sqlalchemy.ext.asyncio import create_async_engine
from alembic import context

# Add the app directory to sys.path
sys.path.append(os.path.dirname(os.path.dirname(__file__)))

# Import the FastAPI app configuration and models
from app.config import settings
from app.database import Base

# Import all models so Alembic can discover them
from app.models.rfid_card_mapping import RFIDCardMapping
from app.models.rfid_device import RFIDDevice
from app.models.rfid_ledger import RFIDLedger
from app.models.deduplication_cache import EventDeduplicationCache

# this is the Alembic Config object, which provides
# access to the values within the .ini file in use.
config = context.config

# Interpret the config file for Python logging.
# This line sets up loggers basically.
if config.config_file_name is not None:
    fileConfig(config.config_file_name)

# Set the target metadata for autogenerate support
target_metadata = Base.metadata

# Set the database URL from environment variables
config.set_main_option("sqlalchemy.url", settings.database_url.replace("+asyncpg", ""))

# other values from the config, defined by the needs of env.py,
# can be acquired:
# my_important_option = config.get_main_option("my_important_option")
# ... etc.


def run_migrations_offline() -> None:
    """Run migrations in 'offline' mode.

    This configures the context with just a URL
    and not an Engine, though an Engine is acceptable
    here as well.  By skipping the Engine creation
    we don't even need a DBAPI to be available.

    Calls to context.execute() here emit the given string to the
    script output.

    """
    url = config.get_main_option("sqlalchemy.url")
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
    )

    with context.begin_transaction():
        context.run_migrations()


def do_run_migrations(connection):
    """Run migrations with the given connection."""
    context.configure(
        connection=connection, 
        target_metadata=target_metadata,
        compare_type=True,
        compare_server_default=True
    )

    with context.begin_transaction():
        context.run_migrations()


def run_migrations_online() -> None:
    """Run migrations in 'online' mode.

    In this scenario we need to create an Engine
    and associate a connection with the context.

    """
    # Use the sync version of the database URL for Alembic
    database_url = settings.database_url.replace("+asyncpg", "")
    
    # Create sync engine for Alembic  
    from sqlalchemy import create_engine
    connectable = create_engine(database_url, poolclass=pool.NullPool)

    with connectable.connect() as connection:
        context.configure(
            connection=connection, 
            target_metadata=target_metadata,
            compare_type=True,
            compare_server_default=True
        )

        with context.begin_transaction():
            context.run_migrations()


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
