import pytest
from httpx import AsyncClient
from unittest.mock import AsyncMock, patch
from app.main import app


@pytest.mark.asyncio
async def test_tcp_start_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test TCP listener start endpoint."""
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager:
        mock_tcp_manager.start_listener = AsyncMock()
        
        response = await test_client.post(
            "/api/admin/listeners/tcp/start",
            headers=admin_token_headers
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert "TCP listener started" in data["message"]
        assert mock_tcp_manager.start_listener.called


@pytest.mark.asyncio 
async def test_tcp_stop_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test TCP listener stop endpoint."""
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager:
        mock_tcp_manager.stop_listener = AsyncMock()
        
        response = await test_client.post(
            "/api/admin/listeners/tcp/stop",
            headers=admin_token_headers  
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert "TCP listener stopped" in data["message"]
        assert mock_tcp_manager.stop_listener.called


@pytest.mark.asyncio
async def test_tcp_stats_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test TCP listener stats endpoint."""
    mock_stats = {
        "total_connections": 5,
        "active_connections": 2,
        "events_processed": 100,
        "events_rejected": 3,
        "uptime_seconds": 3600,
        "server_running": True
    }
    
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager:
        mock_tcp_manager.get_stats.return_value = mock_stats
        
        response = await test_client.get(
            "/api/admin/listeners/tcp/stats",
            headers=admin_token_headers
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["total_connections"] == 5
        assert data["active_connections"] == 2
        assert data["events_processed"] == 100
        assert data["server_running"] is True


@pytest.mark.asyncio
async def test_udp_start_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test UDP listener start endpoint."""
    with patch('app.api.listeners.udp_manager') as mock_udp_manager:
        mock_udp_manager.start_listener = AsyncMock()
        
        response = await test_client.post(
            "/api/admin/listeners/udp/start",
            headers=admin_token_headers
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert "UDP listener started" in data["message"]
        assert mock_udp_manager.start_listener.called


@pytest.mark.asyncio
async def test_udp_stop_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test UDP listener stop endpoint."""
    with patch('app.api.listeners.udp_manager') as mock_udp_manager:
        mock_udp_manager.stop_listener = AsyncMock()
        
        response = await test_client.post(
            "/api/admin/listeners/udp/stop",
            headers=admin_token_headers
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        assert "UDP listener stopped" in data["message"]
        assert mock_udp_manager.stop_listener.called


@pytest.mark.asyncio
async def test_udp_stats_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test UDP listener stats endpoint."""
    mock_stats = {
        "total_packets": 250,
        "events_processed": 245,
        "events_rejected": 5,
        "unique_senders": 8,
        "uptime_seconds": 7200,
        "server_running": True
    }
    
    with patch('app.api.listeners.udp_manager') as mock_udp_manager:
        mock_udp_manager.get_stats.return_value = mock_stats
        
        response = await test_client.get(
            "/api/admin/listeners/udp/stats",
            headers=admin_token_headers
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["total_packets"] == 250
        assert data["events_processed"] == 245
        assert data["unique_senders"] == 8
        assert data["server_running"] is True


@pytest.mark.asyncio
async def test_listeners_status_endpoint(test_client: AsyncClient, admin_token_headers):
    """Test combined listeners status endpoint."""
    mock_tcp_stats = {
        "events_processed": 100,
        "server_running": True,
        "active_connections": 2
    }
    
    mock_udp_stats = {
        "events_processed": 245,
        "server_running": True, 
        "unique_senders": 8
    }
    
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager, \
         patch('app.api.listeners.udp_manager') as mock_udp_manager:
        
        mock_tcp_manager.get_stats.return_value = mock_tcp_stats
        mock_udp_manager.get_stats.return_value = mock_udp_stats
        
        response = await test_client.get(
            "/api/admin/listeners/status",
            headers=admin_token_headers
        )
        
        assert response.status_code == 200
        data = response.json()
        assert "tcp" in data
        assert "udp" in data
        assert data["tcp"]["server_running"] is True
        assert data["udp"]["server_running"] is True


@pytest.mark.asyncio
async def test_tcp_start_with_parameters(test_client: AsyncClient, admin_token_headers):
    """Test TCP listener start with custom host/port."""
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager:
        mock_tcp_manager.start_listener = AsyncMock()
        
        response = await test_client.post(
            "/api/admin/listeners/tcp/start",
            headers=admin_token_headers,
            params={"host": "192.168.1.100", "port": "9005"}
        )
        
        assert response.status_code == 200
        mock_tcp_manager.start_listener.assert_called_with("192.168.1.100", 9005)


@pytest.mark.asyncio
async def test_udp_start_with_parameters(test_client: AsyncClient, admin_token_headers):
    """Test UDP listener start with custom host/port."""
    with patch('app.api.listeners.udp_manager') as mock_udp_manager:
        mock_udp_manager.start_listener = AsyncMock()
        
        response = await test_client.post(
            "/api/admin/listeners/udp/start", 
            headers=admin_token_headers,
            params={"host": "192.168.1.100", "port": "9006"}
        )
        
        assert response.status_code == 200
        mock_udp_manager.start_listener.assert_called_with("192.168.1.100", 9006)


@pytest.mark.asyncio
async def test_tcp_start_exception_handling(test_client: AsyncClient, admin_token_headers):
    """Test TCP listener start error handling."""
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager:
        mock_tcp_manager.start_listener.side_effect = Exception("Failed to bind to port")
        
        response = await test_client.post(
            "/api/admin/listeners/tcp/start",
            headers=admin_token_headers
        )
        
        assert response.status_code == 500
        data = response.json()
        assert data["detail"] == "Failed to start TCP listener: Failed to bind to port"


@pytest.mark.asyncio
async def test_udp_start_exception_handling(test_client: AsyncClient, admin_token_headers):
    """Test UDP listener start error handling."""
    with patch('app.api.listeners.udp_manager') as mock_udp_manager:
        mock_udp_manager.start_listener.side_effect = Exception("Port already in use")
        
        response = await test_client.post(
            "/api/admin/listeners/udp/start",
            headers=admin_token_headers
        )
        
        assert response.status_code == 500
        data = response.json()
        assert data["detail"] == "Failed to start UDP listener: Port already in use"


@pytest.mark.asyncio
async def test_unauthorized_access(test_client: AsyncClient):
    """Test that listener endpoints require admin authentication."""
    # Test TCP endpoints
    tcp_endpoints = [
        "/api/admin/listeners/tcp/start",
        "/api/admin/listeners/tcp/stop",
        "/api/admin/listeners/tcp/stats",
    ]
    
    for endpoint in tcp_endpoints:
        response = await test_client.post(endpoint)
        assert response.status_code == 401
        
    # Test UDP endpoints  
    udp_endpoints = [
        "/api/admin/listeners/udp/start",
        "/api/admin/listeners/udp/stop",
        "/api/admin/listeners/udp/stats",
    ]
    
    for endpoint in udp_endpoints:
        response = await test_client.post(endpoint)
        assert response.status_code == 401
    
    # Test status endpoint
    response = await test_client.get("/api/admin/listeners/status")
    assert response.status_code == 401


@pytest.mark.asyncio
async def test_non_admin_access_denied(test_client: AsyncClient, user_token_headers):
    """Test that listener endpoints deny access to non-admin users.""" 
    # Test TCP start endpoint
    response = await test_client.post(
        "/api/admin/listeners/tcp/start",
        headers=user_token_headers
    )
    assert response.status_code == 403
    
    # Test UDP stats endpoint
    response = await test_client.get(
        "/api/admin/listeners/udp/stats", 
        headers=user_token_headers
    )
    assert response.status_code == 403


@pytest.mark.asyncio
async def test_listeners_integration_flow(test_client: AsyncClient, admin_token_headers):
    """Test full integration flow: start, check status, stop."""
    with patch('app.api.listeners.tcp_manager') as mock_tcp_manager, \
         patch('app.api.listeners.udp_manager') as mock_udp_manager:
        
        mock_tcp_manager.start_listener = AsyncMock()
        mock_tcp_manager.stop_listener = AsyncMock()
        mock_tcp_manager.get_stats.return_value = {"server_running": True, "events_processed": 0}
        
        mock_udp_manager.start_listener = AsyncMock()
        mock_udp_manager.stop_listener = AsyncMock()  
        mock_udp_manager.get_stats.return_value = {"server_running": True, "events_processed": 0}
        
        # Start both listeners
        tcp_response = await test_client.post(
            "/api/admin/listeners/tcp/start",
            headers=admin_token_headers
        )
        assert tcp_response.status_code == 200
        
        udp_response = await test_client.post(
            "/api/admin/listeners/udp/start", 
            headers=admin_token_headers
        )
        assert udp_response.status_code == 200
        
        # Check combined status
        status_response = await test_client.get(
            "/api/admin/listeners/status",
            headers=admin_token_headers
        )
        assert status_response.status_code == 200
        data = status_response.json()
        assert data["tcp"]["server_running"] is True
        assert data["udp"]["server_running"] is True
        
        # Stop both listeners
        tcp_stop = await test_client.post(
            "/api/admin/listeners/tcp/stop",
            headers=admin_token_headers
        )
        assert tcp_stop.status_code == 200
        
        udp_stop = await test_client.post(
            "/api/admin/listeners/udp/stop",
            headers=admin_token_headers
        )
        assert udp_stop.status_code == 200