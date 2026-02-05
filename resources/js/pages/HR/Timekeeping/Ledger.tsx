import { Head, usePage, router } from '@inertiajs/react';
import { useState, useMemo, useEffect, useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { LedgerHealthWidget } from '@/components/timekeeping/ledger-health-widget';
import { TimeLogsStream } from '@/components/timekeeping/time-logs-stream';
import { LogsFilterPanel, LogsFilterConfig, defaultFilters } from '@/components/timekeeping/logs-filter-panel';
import { EventReplayControl } from '@/components/timekeeping/event-replay-control';
import { DeviceStatusDashboard } from '@/components/timekeeping/device-status-dashboard';
import { ChevronDown, ChevronUp, Filter, RefreshCw, Download } from 'lucide-react';

// Declare route as a global function
declare global {
    function route(name: string, params?: Record<string, string | number>): string;
}

interface AttendanceEvent {
    id: number;
    sequence_id: number;
    employee_id: string;
    employee_name: string;
    event_type: 'time_in' | 'time_out' | 'break_start' | 'break_end';
    timestamp: string;
    device_id: string;
    device_location: string;
    verified: boolean;
    rfid_card: string;
    hash_chain?: string;
    latency_ms?: number;
    source: string;
}

interface LedgerHealthStatus {
    status: string;
    last_sequence_id: number;
    events_today: number;
    devices_online: number;
    devices_offline: number;
    last_sync: string;
    avg_latency_ms: number;
    hash_verification: {
        total_checked: number;
        passed: number;
        failed: number;
    };
    performance: {
        events_per_hour: number;
        avg_processing_time_ms: number;
        queue_depth: number;
    };
    alerts: Array<{ severity: string; message: string; timestamp: string }>;
}

interface PaginatedLogs {
    data: AttendanceEvent[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

export default function TimekeepingLedger() {
    const page = usePage();
    
    // Get props from Inertia (passed from controller)
    const logs = (page.props as { logs?: PaginatedLogs }).logs || {
        data: [],
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
        from: 0,
        to: 0,
        next_page_url: null,
        prev_page_url: null,
    };
    const ledgerHealth = (page.props as { ledgerHealth?: LedgerHealthStatus }).ledgerHealth || null;
    const appliedFilters = (page.props as { filters?: Record<string, unknown> }).filters || {};
    
    // State for UI controls
    const [showFilterPanel, setShowFilterPanel] = useState(true);
    const [showDeviceStatus, setShowDeviceStatus] = useState(false);
    const [filters, setFilters] = useState<LogsFilterConfig>({
        ...defaultFilters,
        ...appliedFilters,
    });
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    
    // State for Live/Replay mode toggle
    const [replayMode, setReplayMode] = useState(false);
    const [replayEvents, setReplayEvents] = useState<Array<{
        id: number;
        sequenceId: number;
        employeeId: string;
        employeeName: string;
        eventType: 'time_in' | 'time_out' | 'break_start' | 'break_end';
        timestamp: string;
        deviceId: string;
        deviceLocation: string;
        employeePhoto?: string;
        rfidCard: string;
        verified: boolean;
        hashChain?: string;
        latencyMs?: number;
    }>>([]);

    // Auto-refresh effect (reload Inertia page every 30 seconds in Live mode)
    useEffect(() => {
        if (!autoRefresh || replayMode) return;

        const interval = setInterval(() => {
            router.reload({ only: ['logs', 'ledgerHealth'] });
            setLastRefreshTime(new Date());
        }, 30000); // 30 seconds

        return () => clearInterval(interval);
    }, [autoRefresh, replayMode]);

    // Transform controller health status to widget format
    const transformedHealthState = useMemo(() => {
        if (!ledgerHealth) return null;

        const lastSyncDate = new Date(ledgerHealth.last_sync);
        const minutesAgo = Math.floor((new Date().getTime() - lastSyncDate.getTime()) / 60000);

        // Map controller status to widget status type
        const mapStatus = (status: string): 'healthy' | 'warning' | 'critical' => {
            if (status === 'degraded') return 'warning';
            if (status === 'healthy' || status === 'warning' || status === 'critical') return status;
            return 'warning'; // default fallback
        };

        return {
            status: mapStatus(ledgerHealth.status),
            lastSequence: ledgerHealth.last_sequence_id,
            lastProcessedAgo: `${minutesAgo}m ago`,
            processingRate: ledgerHealth.performance.events_per_hour,
            integrityStatus: ledgerHealth.hash_verification.failed === 0 ? 'verified' as const : 'hash_mismatch_detected' as const,
            devicesOnline: ledgerHealth.devices_online,
            devicesOffline: ledgerHealth.devices_offline,
            backlog: ledgerHealth.performance.queue_depth,
            processingRateHistory: [
                ledgerHealth.performance.events_per_hour,
                ledgerHealth.performance.events_per_hour - 5,
                ledgerHealth.performance.events_per_hour + 3,
                ledgerHealth.performance.events_per_hour - 2,
                ledgerHealth.performance.events_per_hour + 1,
                ledgerHealth.performance.events_per_hour,
                ledgerHealth.performance.events_per_hour + 2,
                ledgerHealth.performance.events_per_hour - 1,
                ledgerHealth.performance.events_per_hour,
                ledgerHealth.performance.events_per_hour + 3,
                ledgerHealth.performance.events_per_hour - 2,
                ledgerHealth.performance.events_per_hour,
            ]
        };
    }, [ledgerHealth]);

    // Convert controller logs to TimeLogEntry format for the stream
    const convertedLogs = useMemo(() => {
        return logs.data.map(log => ({
            id: log.id,
            sequenceId: log.sequence_id,
            employeeId: log.employee_id,
            employeeName: log.employee_name,
            employeePhoto: undefined,
            rfidCard: log.rfid_card,
            eventType: log.event_type,
            timestamp: log.timestamp,
            deviceId: log.device_id,
            deviceLocation: log.device_location,
            verified: log.verified,
            hashChain: log.hash_chain,
            latencyMs: log.latency_ms,
        }));
    }, [logs.data]);

    // Handler for filter changes - reloads page with new filters as query params
    const handleFiltersChange = (newFilters: LogsFilterConfig) => {
        setFilters(newFilters);
        
        // Convert filters to query params and reload
        const queryParams: Record<string, string | number> = {};
        
        if (newFilters.customDateFrom) {
            queryParams.date_from = newFilters.customDateFrom;
        }
        if (newFilters.customDateTo) {
            queryParams.date_to = newFilters.customDateTo;
        }
        if (newFilters.deviceLocations.length > 0 && !newFilters.deviceLocations.includes('all')) {
            queryParams.device_id = newFilters.deviceLocations[0];
        }
        if (newFilters.eventTypes.length > 0) {
            queryParams.event_type = newFilters.eventTypes[0];
        }
        if (newFilters.employeeSearch) {
            queryParams.employee_search = newFilters.employeeSearch;
        }
        
        router.visit(route('timekeeping.ledger.index'), {
            data: queryParams,
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Handler for clearing all filters
    const handleClearFilters = () => {
        setFilters(defaultFilters);
        router.visit(route('timekeeping.ledger.index'), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Handler for replay visible events change
    const handleReplayVisibleEventsChange = useCallback((events: Array<{
        id: number;
        sequenceId: number;
        employeeId: string;
        employeeName: string;
        eventType: 'time_in' | 'time_out' | 'break_start' | 'break_end';
        timestamp: string;
        deviceId: string;
        deviceLocation: string;
    }>) => {
        const convertedEvents = events.map(event => ({
            ...event,
            employeePhoto: undefined,
            rfidCard: '****-****',
            verified: true,
            hashChain: undefined,
            latencyMs: undefined
        }));
        setReplayEvents(convertedEvents);
    }, []);

    // Toggle replay mode
    const handleToggleLiveReplayMode = () => {
        setReplayMode(!replayMode);
        if (!replayMode) {
            setAutoRefresh(false); // Disable auto-refresh when entering replay mode
        }
    };

    // Export handler for CSV/JSON
    const handleExport = (format: 'csv' | 'json') => {
        const dataToExport = replayMode ? replayEvents : convertedLogs;
        
        if (format === 'json') {
            const jsonStr = JSON.stringify(dataToExport, null, 2);
            const blob = new Blob([jsonStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ledger-events-${new Date().toISOString()}.json`;
            a.click();
            URL.revokeObjectURL(url);
        } else {
            // CSV export
            const headers = ['Sequence ID', 'Employee ID', 'Employee Name', 'Event Type', 'Timestamp', 'Device ID', 'Device Location', 'Verified'];
            const csvRows = [
                headers.join(','),
                ...dataToExport.map(log => [
                    log.sequenceId,
                    log.employeeId,
                    `"${log.employeeName}"`,
                    log.eventType,
                    log.timestamp,
                    log.deviceId,
                    `"${log.deviceLocation}"`,
                    log.verified
                ].join(','))
            ];
            const csvStr = csvRows.join('\n');
            const blob = new Blob([csvStr], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ledger-events-${new Date().toISOString()}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
    };

    return (
        <AppLayout>
            <Head title="RFID Ledger - Timekeeping" />

            <div className="py-6 space-y-6">
                {/* Ledger Health Widget */}
                {ledgerHealth && transformedHealthState ? (
                    <LedgerHealthWidget healthState={transformedHealthState} />
                ) : null}

                {/* Live Mode / Replay Mode Toggle */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-lg">Event Stream Mode</CardTitle>
                            <div className="flex items-center gap-3">
                                <Button
                                    variant={!replayMode ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => !replayMode || handleToggleLiveReplayMode()}
                                    className="gap-2"
                                >
                                    🟢 Live Mode
                                </Button>
                                <Button
                                    variant={replayMode ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => replayMode || handleToggleLiveReplayMode()}
                                    className="gap-2"
                                >
                                    📺 Replay Mode
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Main Content: Filters + Event Stream */}
                <div className="grid gap-6 lg:grid-cols-4">
                    {/* Left Sidebar: Filters */}
                    {showFilterPanel && (
                        <div className="lg:col-span-1">
                            <LogsFilterPanel
                                filters={filters}
                                onFiltersChange={handleFiltersChange}
                                onClearFilters={handleClearFilters}
                            />
                        </div>
                    )}

                    {/* Right Content: Event Stream */}
                    <div className={showFilterPanel ? 'lg:col-span-3' : 'lg:col-span-4'}>
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between flex-wrap gap-2">
                                    <div className="flex items-center gap-3">
                                        <CardTitle>Event Stream</CardTitle>
                                        {!replayMode && (
                                            <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
                                                <span className="animate-pulse mr-1">●</span> Live
                                            </Badge>
                                        )}
                                        {replayMode && (
                                            <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">
                                                📺 Replay
                                            </Badge>
                                        )}
                                    </div>
                                    
                                    <div className="flex items-center gap-2">
                                        {/* Filter Panel Toggle */}
                                        <Button
                                            variant={showFilterPanel ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => setShowFilterPanel(!showFilterPanel)}
                                            className="h-8 gap-2"
                                        >
                                            <Filter className="h-3 w-3" />
                                            <span className="text-xs">
                                                {showFilterPanel ? 'Hide' : 'Show'} Filters
                                            </span>
                                        </Button>

                                        {/* Auto-Refresh Toggle (Live Mode Only) */}
                                        {!replayMode && (
                                            <Button
                                                variant={autoRefresh ? 'default' : 'outline'}
                                                size="sm"
                                                onClick={() => setAutoRefresh(!autoRefresh)}
                                                className="h-8 gap-2"
                                            >
                                                <RefreshCw className={`h-3 w-3 ${autoRefresh ? 'animate-spin' : ''}`} />
                                                <span className="text-xs">
                                                    {autoRefresh ? 'Auto ON' : 'Auto OFF'}
                                                </span>
                                            </Button>
                                        )}

                                        {/* Export Dropdown */}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-8 gap-2"
                                            onClick={() => handleExport('json')}
                                        >
                                            <Download className="h-3 w-3" />
                                            <span className="text-xs">Export</span>
                                        </Button>
                                    </div>
                                </div>

                                {/* Last refresh time indicator (Live Mode Only) */}
                                {autoRefresh && !replayMode && (
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        Last refreshed: {lastRefreshTime.toLocaleTimeString()} • Updates every 30 seconds
                                    </div>
                                )}
                            </CardHeader>
                        </Card>

                        {/* Event Stream Component */}
                        <div className="mt-4">
                            <TimeLogsStream 
                                logs={replayMode ? replayEvents : convertedLogs} 
                                maxHeight="calc(100vh - 500px)"
                                showLiveIndicator={!replayMode}
                                autoScroll={!replayMode}
                            />
                            
                            {/* Pagination */}
                            {!replayMode && logs.total > logs.per_page && (
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="text-sm text-muted-foreground">
                                        Showing {logs.from} to {logs.to} of {logs.total} events
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {logs.prev_page_url && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => router.visit(logs.prev_page_url!)}
                                            >
                                                ← Previous
                                            </Button>
                                        )}
                                        <span className="text-sm">
                                            Page {logs.current_page} of {logs.last_page}
                                        </span>
                                        {logs.next_page_url && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => router.visit(logs.next_page_url!)}
                                            >
                                                Next →
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Event Replay Control (Replay Mode Only) */}
                {replayMode && (
                    <EventReplayControl 
                        className="mt-6" 
                        onVisibleEventsChange={handleReplayVisibleEventsChange}
                    />
                )}

                {/* Device Status Dashboard (Collapsible) */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-lg">Device Status Dashboard</CardTitle>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowDeviceStatus(!showDeviceStatus)}
                                className="h-8 w-8 p-0"
                            >
                                {showDeviceStatus ? (
                                    <ChevronUp className="h-4 w-4" />
                                ) : (
                                    <ChevronDown className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </CardHeader>
                    {showDeviceStatus && (
                        <CardContent>
                            <DeviceStatusDashboard />
                        </CardContent>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
