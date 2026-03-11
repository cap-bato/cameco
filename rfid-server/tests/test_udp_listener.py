import pytest
import asyncio
import json
from unittest.mock import AsyncMock, patch, MagicMock
from app.listeners.udp_listener import UDPListener, UDPManager, UDPProtocol
from datetime import datetime


@pytest.fixture
async def udp_listener():
    """Create a UDP listener instance for testing."""
    listener = UDPListener(host="127.0.0.1", port=0)  # Use port 0 for random port
    yield listener
    # Cleanup
    if listener.transport:
        await listener.stop()


@pytest.fixture
async def mock_event_processor():
    """Mock event processor service."""
    with patch('app.listeners.udp_listener.EventProcessorService') as mock:
        processor_instance = AsyncMock()
        processor_instance.process_rfid_tap.return_value = {
            "status": "success",
            "event_id": 456,
            "message": "UDP event processed successfully"
        }
        mock.return_value = processor_instance
        yield processor_instance


@pytest.fixture
async def mock_db_session():
    """Mock database session."""
    with patch('app.listeners.udp_listener.AsyncSessionLocal') as mock:
        session = AsyncMock()
        mock.return_value.__aenter__ = AsyncMock(return_value=session)
        mock.return_value.__aexit__ = AsyncMock(return_value=None)
        yield session


@pytest.fixture
async def mock_settings():
    """Mock settings for UDP configuration."""
    with patch('app.listeners.udp_listener.settings') as mock:
        mock.udp_send_acknowledgments = True
        mock.udp_listener_host = "127.0.0.1"
        mock.udp_listener_port = 9001
        yield mock


class TestUDPProtocol:
    
    def test_initialization(self):
        """Test UDP protocol initialization."""
        listener = UDPListener()
        protocol = UDPProtocol(listener)
        
        assert protocol.listener == listener
        assert protocol.transport is None

    def test_connection_made(self, caplog):
        """Test UDP protocol connection establishment."""
        listener = UDPListener()
        protocol = UDPProtocol(listener)
        transport = MagicMock()
        
        protocol.connection_made(transport)
        
        assert protocol.transport == transport
        assert "UDP server transport ready" in caplog.text

    def test_error_received(self, caplog):
        """Test UDP protocol error handling."""
        listener = UDPListener()
        protocol = UDPProtocol(listener)
        
        test_exception = Exception("Test UDP error")
        protocol.error_received(test_exception)
        
        assert "UDP error received: Test UDP error" in caplog.text

    @pytest.mark.asyncio
    async def test_datagram_received(self):
        """Test UDP datagram reception."""
        listener = UDPListener()
        listener._handle_datagram = AsyncMock()
        protocol = UDPProtocol(listener)
        
        test_data = b'{"test": "data"}'
        test_addr = ("192.168.1.100", 12345)
        
        protocol.datagram_received(test_data, test_addr)
        
        # Give asyncio task time to execute
        await asyncio.sleep(0.01)
        
        # Should have created task to handle datagram
        # Note: We can't directly test task creation, but we can verify
        # the method would be called in a real scenario


class TestUDPListener:
    
    def test_initialization(self):
        """Test UDP listener initialization."""
        listener = UDPListener("192.168.1.100", 9002)
        
        assert listener.host == "192.168.1.100"
        assert listener.port == 9002
        assert listener.transport is None
        assert listener.protocol is None
        assert listener.stats["total_packets"] == 0
        assert listener.stats["events_processed"] == 0
        assert listener.stats["events_rejected"] == 0
        assert listener.stats["uptime"] is None
        assert isinstance(listener.stats["unique_senders"], set)

    @pytest.mark.asyncio
    async def test_process_event_success(self, udp_listener, mock_event_processor, mock_db_session):
        """Test successful event processing."""
        event_data = {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01",
            "event_type": "time_in",
            "timestamp": "2026-02-04T08:05:23Z"
        }
        
        result = await udp_listener._process_event(event_data, "192.168.1.100:12345")
        
        assert result["status"] == "success"
        assert result["event_id"] == 456
        assert mock_event_processor.process_rfid_tap.called

    @pytest.mark.asyncio
    async def test_process_event_missing_fields(self, udp_listener):
        """Test event processing with missing required fields."""
        event_data = {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01"
            # Missing event_type and timestamp
        }
        
        result = await udp_listener._process_event(event_data, "192.168.1.100:12345")
        
        assert result["status"] == "error"
        assert result["reason"] == "missing_fields"
        assert "event_type" in result["missing_fields"]
        assert "timestamp" in result["missing_fields"]

    @pytest.mark.asyncio
    async def test_process_event_processor_exception(self, udp_listener, mock_db_session):
        """Test event processing when processor raises exception."""
        with patch('app.listeners.udp_listener.EventProcessorService') as mock:
            processor_instance = AsyncMock()
            processor_instance.process_rfid_tap.side_effect = Exception("UDP processing failed")
            mock.return_value = processor_instance
            
            event_data = {
                "card_uid": "04:3A:B2:C5:D8",
                "device_id": "GATE-01",
                "event_type": "time_in",
                "timestamp": "2026-02-04T08:05:23Z"
            }
            
            result = await udp_listener._process_event(event_data, "192.168.1.100:12345")
            
            assert result["status"] == "error"
            assert result["reason"] == "processing_failed"
            assert "UDP processing failed" in result["message"]

    @pytest.mark.asyncio
    async def test_handle_datagram_valid_json(self, udp_listener, mock_event_processor, mock_db_session, mock_settings):
        """Test handling datagram with valid JSON."""
        # Mock transport for sending responses
        udp_listener.transport = MagicMock()
        
        valid_event = {
            "card_uid": "04:3A:B2:C5:D8",
            "device_id": "GATE-01",
            "event_type": "time_in",
            "timestamp": "2026-02-04T08:05:23Z"
        }
        
        json_message = json.dumps(valid_event)
        test_addr = ("192.168.1.100", 12345)
        
        await udp_listener._handle_datagram(json_message.encode(), test_addr)
        
        # Statistics should be updated
        assert udp_listener.stats["total_packets"] == 1
        assert udp_listener.stats["events_processed"] == 1
        assert udp_listener.stats["events_rejected"] == 0
        assert "192.168.1.100:12345" in udp_listener.stats["unique_senders"]
        
        # Should send acknowledgment if configured
        assert udp_listener.transport.sendto.called

    @pytest.mark.asyncio
    async def test_handle_datagram_invalid_json(self, udp_listener, mock_settings):
        """Test handling datagram with invalid JSON."""
        udp_listener.transport = MagicMock()
        
        test_addr = ("192.168.1.100", 12345)
        
        await udp_listener._handle_datagram(b'invalid json message', test_addr)
        
        # Statistics should be updated
        assert udp_listener.stats["total_packets"] == 1
        assert udp_listener.stats["events_processed"] == 0
        assert udp_listener.stats["events_rejected"] == 1
        
        # Should send error response if acknowledgments enabled
        assert udp_listener.transport.sendto.called
        sent_data = udp_listener.transport.sendto.call_args[0][0]
        response = json.loads(sent_data.decode())
        assert response["status"] == "error"
        assert response["reason"] == "invalid_json"

    @pytest.mark.asyncio
    async def test_handle_datagram_no_acknowledgments(self, udp_listener, mock_event_processor, mock_db_session):
        """Test handling datagram when acknowledgments are disabled."""
        with patch('app.listeners.udp_listener.settings') as mock_settings:
            mock_settings.udp_send_acknowledgments = False
            
            udp_listener.transport = MagicMock()
            
            valid_event = {
                "card_uid": "04:3A:B2:C5:D8",
                "device_id": "GATE-01",
                "event_type": "time_in",
                "timestamp": "2026-02-04T08:05:23Z"
            }
            
            json_message = json.dumps(valid_event)
            test_addr = ("192.168.1.100", 12345)
            
            await udp_listener._handle_datagram(json_message.encode(), test_addr)
            
            # Should not send any response
            assert not udp_listener.transport.sendto.called

    @pytest.mark.asyncio
    async def test_handle_datagram_empty_message(self, udp_listener):
        """Test handling empty datagram."""
        test_addr = ("192.168.1.100", 12345)
        
        initial_packets = udp_listener.stats["total_packets"]
        await udp_listener._handle_datagram(b'', test_addr)
        
        # Should increment packet count but not process anything
        assert udp_listener.stats["total_packets"] == initial_packets + 1
        assert udp_listener.stats["events_processed"] == 0

    def test_get_stats_no_uptime(self, udp_listener):
        """Test statistics when server hasn't started."""
        stats = udp_listener.get_stats()
        
        assert stats["total_packets"] == 0
        assert stats["events_processed"] == 0
        assert stats["events_rejected"] == 0
        assert stats["uptime_seconds"] is None
        assert stats["unique_senders"] == 0
        assert stats["unique_sender_list"] == []
        assert stats["server_running"] is False

    def test_get_stats_with_uptime(self, udp_listener):
        """Test statistics with uptime."""
        udp_listener.stats["uptime"] = datetime.now()
        udp_listener.transport = MagicMock()  # Simulate running server
        udp_listener.stats["unique_senders"].add("192.168.1.100:12345")
        
        stats = udp_listener.get_stats()
        
        assert stats["uptime_seconds"] is not None
        assert stats["uptime_seconds"] >= 0
        assert stats["unique_senders"] == 1
        assert "192.168.1.100:12345" in stats["unique_sender_list"]
        assert stats["server_running"] is True

    @pytest.mark.asyncio
    async def test_stop_server(self, udp_listener):
        """Test stopping the server.""" 
        # Mock a transport
        udp_listener.transport = MagicMock()
        udp_listener.protocol = MagicMock()
        
        await udp_listener.stop()
        
        assert udp_listener.transport is None
        assert udp_listener.protocol is None


class TestUDPManager:
    
    @pytest.mark.asyncio
    async def test_initialization(self):
        """Test UDP manager initialization."""
        manager = UDPManager()
        
        assert manager.listener is None
        assert manager.listener_task is None

    @pytest.mark.asyncio
    async def test_start_stop_listener(self, mock_settings):
        """Test starting and stopping listener through manager."""
        manager = UDPManager()
        
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
    async def test_start_listener_already_running(self, caplog, mock_settings):
        """Test starting listener when already running."""
        manager = UDPManager()
        
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
        manager = UDPManager()
        
        stats = manager.get_stats()
        
        assert stats["server_running"] is False

    @pytest.mark.asyncio
    async def test_get_stats_with_listener(self, mock_settings):
        """Test getting stats when listener is running."""
        manager = UDPManager()
        
        await manager.start_listener("127.0.0.1", 0)
        
        stats = manager.get_stats()
        
        assert "total_packets" in stats
        assert "events_processed" in stats
        assert "events_rejected" in stats
        assert "unique_senders" in stats
        
        await manager.stop_listener()


@pytest.mark.asyncio
async def test_udp_manager_global_instance():
    """Test that global UDP manager instance exists."""
    from app.listeners.udp_listener import udp_manager
    
    assert udp_manager is not None
    assert isinstance(udp_manager, UDPManager)