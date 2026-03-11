import pytest
import asyncio
import json
from unittest.mock import AsyncMock, patch, MagicMock
from app.listeners.tcp_listener import TCPListener, TCPManager
from datetime import datetime


@pytest.fixture
async def tcp_listener():
    """Create a TCP listener instance for testing."""
    listener = TCPListener(host="127.0.0.1", port=0)  # Use port 0 for random port
    yield listener
    # Cleanup
    if listener.server:
        await listener.stop()


@pytest.fixture
async def mock_event_processor():
    """Mock event processor service."""
    with patch('app.listeners.tcp_listener.EventProcessorService') as mock:
        processor_instance = AsyncMock()
        processor_instance.process_rfid_tap.return_value = {
            "status": "success",
            "event_id": 123,
            "message": "Event processed successfully"
        }
        mock.return_value = processor_instance
        yield processor_instance


@pytest.fixture
async def mock_db_session():
    """Mock database session."""
    with patch('app.listeners.tcp_listener.AsyncSessionLocal') as mock:
        session = AsyncMock()
        mock.return_value.__aenter__ = AsyncMock(return_value=session)
        mock.return_value.__aexit__ = AsyncMock(return_value=None)
        yield session


class TestTCPListener:
    
    def test_initialization(self):
        """Test TCP listener initialization."""
        listener = TCPListener("192.168.1.100", 9001)
        
        assert listener.host == "192.168.1.100"
        assert listener.port == 9001
        assert listener.server is None
        assert listener.connections == {}
        assert listener.stats["total_connections"] == 0
        assert listener.stats["active_connections"] == 0
        assert listener.stats["events_processed"] == 0
        assert listener.stats["events_rejected"] == 0
        assert listener.stats["uptime"] is None

    @pytest.mark.asyncio
    async def test_process_event_success(self, tcp_listener, mock_event_processor, mock_db_session):
        """Test successful event processing."""
        event_data = {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01", 
            "event_type": "time_in",
            "timestamp": "2026-02-04T08:05:23Z"
        }
        
        result = await tcp_listener._process_event(event_data, "192.168.1.100:12345")
        
        assert result["status"] == "success"
        assert result["event_id"] == 123
        assert mock_event_processor.process_rfid_tap.called

    @pytest.mark.asyncio 
    async def test_process_event_missing_fields(self, tcp_listener):
        """Test event processing with missing required fields."""
        event_data = {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01"
            # Missing event_type and timestamp
        }
        
        result = await tcp_listener._process_event(event_data, "192.168.1.100:12345")
        
        assert result["status"] == "error"
        assert result["reason"] == "missing_fields"
        assert "event_type" in result["missing_fields"]
        assert "timestamp" in result["missing_fields"]

    @pytest.mark.asyncio
    async def test_process_event_processor_exception(self, tcp_listener, mock_db_session):
        """Test event processing when processor raises exception."""
        with patch('app.listeners.tcp_listener.EventProcessorService') as mock:
            processor_instance = AsyncMock()
            processor_instance.process_rfid_tap.side_effect = Exception("Processing failed")
            mock.return_value = processor_instance
            
            event_data = {
                "card_uid": "04:3A:B2:C5:D8",
                "device_id": "GATE-01",
                "event_type": "time_in", 
                "timestamp": "2026-02-04T08:05:23Z"
            }
            
            result = await tcp_listener._process_event(event_data, "192.168.1.100:12345")
            
            assert result["status"] == "error"
            assert result["reason"] == "processing_failed"
            assert "Processing failed" in result["message"]

    @pytest.mark.asyncio
    async def test_handle_client_valid_json(self, tcp_listener, mock_event_processor, mock_db_session):
        """Test handling client with valid JSON message."""
        # Mock reader and writer
        reader = AsyncMock()
        writer = AsyncMock()
        writer.get_extra_info.return_value = ("192.168.1.100", 12345)
        
        # Simulate receiving one JSON message then EOF
        valid_event = {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01",
            "event_type": "time_in",
            "timestamp": "2026-02-04T08:05:23Z"
        }
        
        json_message = json.dumps(valid_event) + "\n"
        reader.readline.side_effect = [json_message.encode(), b'']  # EOF
        
        # Execute handler
        await tcp_listener.handle_client(reader, writer)
        
        # Verify response was sent
        assert writer.write.called
        written_data = writer.write.call_args[0][0].decode()
        response = json.loads(written_data.strip())
        assert response["status"] == "success"
        
        # Statistics should be updated
        assert tcp_listener.stats["events_processed"] == 1
        assert tcp_listener.stats["events_rejected"] == 0

    @pytest.mark.asyncio
    async def test_handle_client_invalid_json(self, tcp_listener):
        """Test handling client with invalid JSON."""
        reader = AsyncMock()
        writer = AsyncMock()
        writer.get_extra_info.return_value = ("192.168.1.100", 12345)
        
        # Send invalid JSON then EOF
        reader.readline.side_effect = [b'invalid json message\n', b'']
        
        await tcp_listener.handle_client(reader, writer)
        
        # Should send error response
        assert writer.write.called
        written_data = writer.write.call_args[0][0].decode()
        response = json.loads(written_data.strip())
        assert response["status"] == "error"
        assert response["reason"] == "invalid_json"

    def test_get_stats_no_uptime(self, tcp_listener):
        """Test statistics when server hasn't started."""
        stats = tcp_listener.get_stats()
        
        assert stats["total_connections"] == 0
        assert stats["active_connections"] == 0
        assert stats["events_processed"] == 0
        assert stats["events_rejected"] == 0
        assert stats["uptime_seconds"] is None
        assert stats["server_running"] is False
        assert stats["active_connections_list"] == []

    def test_get_stats_with_uptime(self, tcp_listener):
        """Test statistics with uptime."""
        tcp_listener.stats["uptime"] = datetime.now()
        tcp_listener.server = MagicMock()  # Simulate running server
        
        stats = tcp_listener.get_stats()
        
        assert stats["uptime_seconds"] is not None
        assert stats["uptime_seconds"] >= 0
        assert stats["server_running"] is True

    @pytest.mark.asyncio
    async def test_start_stop_server(self, tcp_listener):
        """Test starting and stopping the server."""
        # Use a background task to start the server since start() runs forever
        start_task = asyncio.create_task(tcp_listener.start())
        
        # Give it time to start
        await asyncio.sleep(0.1)
        
        # Server should be running
        assert tcp_listener.server is not None
        assert tcp_listener.stats["uptime"] is not None
        
        # Stop the server
        start_task.cancel()
        await tcp_listener.stop()
        
        assert tcp_listener.server is None


class TestTCPManager:
    
    @pytest.mark.asyncio
    async def test_initialization(self):
        """Test TCP manager initialization."""
        manager = TCPManager()
        
        assert manager.listener is None
        assert manager.listener_task is None

    @pytest.mark.asyncio  
    async def test_start_stop_listener(self):
        """Test starting and stopping listener through manager."""
        manager = TCPManager()
        
        # Start listener
        await manager.start_listener("127.0.0.1", 0)  # Random port
        
        assert manager.listener is not None
        assert manager.listener_task is not None
        assert not manager.listener_task.done()
        
        # Stop listener
        await manager.stop_listener()
        
        assert manager.listener is None
        assert manager.listener_task is None

    @pytest.mark.asyncio
    async def test_start_listener_already_running(self, caplog):
        """Test starting listener when already running.""" 
        manager = TCPManager()
        
        # Start first listener
        await manager.start_listener("127.0.0.1", 0)
        
        # Try to start again
        await manager.start_listener("127.0.0.1", 0)
        
        # Should log warning
        assert "already running" in caplog.text
        
        # Cleanup
        await manager.stop_listener()

    @pytest.mark.asyncio
    async def test_get_stats_no_listener(self):
        """Test getting stats when no listener is running."""
        manager = TCPManager()
        
        stats = manager.get_stats()
        
        assert stats["server_running"] is False

    @pytest.mark.asyncio
    async def test_get_stats_with_listener(self):
        """Test getting stats when listener is running."""
        manager = TCPManager()
        
        await manager.start_listener("127.0.0.1", 0)
        
        stats = manager.get_stats()
        
        assert "total_connections" in stats
        assert "active_connections" in stats
        assert "events_processed" in stats
        
        await manager.stop_listener()


@pytest.mark.asyncio
async def test_tcp_manager_global_instance():
    """Test that global TCP manager instance exists."""
    from app.listeners.tcp_listener import tcp_manager
    
    assert tcp_manager is not None
    assert isinstance(tcp_manager, TCPManager)