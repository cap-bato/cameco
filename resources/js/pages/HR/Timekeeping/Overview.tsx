import { Head, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { SummaryCard } from '@/components/timekeeping/summary-card';
import { LedgerHealthWidget } from '@/components/timekeeping/ledger-health-widget';
import { TimeLogsStream } from '@/components/timekeeping/time-logs-stream';
import { LogsFilterPanel, LogsFilterConfig, defaultFilters } from '@/components/timekeeping/logs-filter-panel';
import { EventReplayControl } from '@/components/timekeeping/event-replay-control';
import { ChevronDown, ChevronUp, Filter, RefreshCw, AlertCircle } from 'lucide-react';
import { 
    fetchTimeLogs, 
    fetchLedgerHealth,
    TimeLogFilters,
    LedgerHealthStatus
} from '@/services/mock-timekeeping-api';
import type { AttendanceEvent } from '@/types/timekeeping-pages';

interface StatusDistribution {
    status: string;
    count: number;
    percentage: number;
}

interface TopIssue {
    issue: string;
    count: number;
    trend: 'up' | 'down' | 'stable';
}

interface Analytics {
    summary: {
        total_employees: number;
        average_attendance_rate: number;
        average_late_rate: number;
        average_absent_rate: number;
        compliance_score: number;
    };
    status_distribution: StatusDistribution[];
    top_issues: TopIssue[];
}

export default function TimekeepingOverview() {
    const page = usePage();
    const analytics = (page.props as { analytics?: Analytics }).analytics || {
        summary: { total_employees: 0, average_attendance_rate: 0, average_late_rate: 0, average_absent_rate: 0, compliance_score: 0 },
        status_distribution: [],
        top_issues: [],
    };

    // State for API data
    const [timeLogs, setTimeLogs] = useState<AttendanceEvent[]>([]);
    const [ledgerHealth, setLedgerHealth] = useState<LedgerHealthStatus | null>(null);
    const [isLoadingLogs, setIsLoadingLogs] = useState(true);
    const [isLoadingHealth, setIsLoadingHealth] = useState(true);
    const [logsError, setLogsError] = useState<string | null>(null);
    const [healthError, setHealthError] = useState<string | null>(null);
    
    // State for Live Event Stream visibility and filters
    const [showEventStream, setShowEventStream] = useState(true);
    const [showFilterPanel, setShowFilterPanel] = useState(true);
    const [filters, setFilters] = useState<LogsFilterConfig>(defaultFilters);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    
    // State for replay mode (Task 1.8.3)
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

    // Fetch time logs from API (Task 2.2.1)
    const loadTimeLogs = useCallback(async () => {
        setIsLoadingLogs(true);
        setLogsError(null);
        
        try {
            const apiFilters: TimeLogFilters = {
                date_from: filters.customDateFrom || undefined,
                date_to: filters.customDateTo || undefined,
                device_id: filters.deviceLocations.length > 0 && !filters.deviceLocations.includes('all') 
                    ? filters.deviceLocations[0] 
                    : undefined,
                event_type: filters.eventTypes.length > 0 ? filters.eventTypes[0] : undefined,
                page: 1,
                per_page: 50,
            };

            const response = await fetchTimeLogs(apiFilters);
            setTimeLogs(response.data);
            setLastRefreshTime(new Date());
        } catch (error: unknown) {
            console.error('Failed to load time logs:', error);
            setLogsError(error instanceof Error ? error.message : 'Failed to load time logs');
        } finally {
            setIsLoadingLogs(false);
        }
    }, [filters]);

    // Fetch ledger health from API (Task 2.2.1)
    const loadLedgerHealth = useCallback(async () => {
        setIsLoadingHealth(true);
        setHealthError(null);
        
        try {
            const health = await fetchLedgerHealth();
            setLedgerHealth(health);
        } catch (error: unknown) {
            console.error('Failed to load ledger health:', error);
            setHealthError(error instanceof Error ? error.message : 'Failed to load health status');
        } finally {
            setIsLoadingHealth(false);
        }
    }, []);

    // Initial data load
    useEffect(() => {
        loadTimeLogs();
        loadLedgerHealth();
    }, [loadTimeLogs, loadLedgerHealth]);

    // Auto-refresh effect (polls API every 30 seconds when enabled)
    useEffect(() => {
        if (!autoRefresh) return;

        const intervalId = setInterval(() => {
            loadTimeLogs();
            loadLedgerHealth();
        }, 30000); // 30 seconds

        return () => clearInterval(intervalId);
    }, [autoRefresh, loadTimeLogs, loadLedgerHealth]);

    // Retry handler for errors
    const handleRetryLogs = () => {
        loadTimeLogs();
    };

    const handleRetryHealth = () => {
        loadLedgerHealth();
    };

    // Transform API health status to widget format
    const transformedHealthState = useMemo(() => {
        if (!ledgerHealth) return null;

        return {
            status: ledgerHealth.status,
            lastSequence: ledgerHealth.metrics.last_sequence_id,
            lastProcessedAgo: ledgerHealth.metrics.last_processed_at 
                ? `${Math.floor((Date.now() - new Date(ledgerHealth.metrics.last_processed_at).getTime()) / 60000)}m ago`
                : 'Never',
            processingRate: ledgerHealth.performance.events_per_hour,
            integrityStatus: ledgerHealth.metrics.hash_chain_intact ? 'verified' as const : 'hash_mismatch_detected' as const,
            devicesOnline: ledgerHealth.metrics.device_sync_status.online,
            devicesOffline: ledgerHealth.metrics.device_sync_status.offline,
            backlog: ledgerHealth.metrics.pending_events,
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

    // Handler for View Logs action
    const handleViewLogs = useCallback((filterType: string) => {
        // In production, this would navigate to the full attendance logs page with pre-applied filters
        console.log(`Navigating to logs with filter: ${filterType}`);
        // Example: router.visit('/hr/timekeeping/attendance', { data: { filter: filterType } });
    }, []);

    // Handler for filter changes
    const handleFiltersChange = (newFilters: LogsFilterConfig) => {
        setFilters(newFilters);
    };

    // Handler for clearing all filters
    const handleClearFilters = () => {
        setFilters(defaultFilters);
    };

    // Handler for replay visible events change (Task 1.8.3)
    // Convert ReplayEvent[] to TimeLogEntry[] format for the stream
    const handleReplayVisibleEventsChange = useCallback((events: { 
        id: number;
        sequenceId: number;
        employeeId: string;
        employeeName: string;
        eventType: 'time_in' | 'time_out' | 'break_start' | 'break_end';
        timestamp: string;
        deviceId: string;
        deviceLocation: string;
    }[]) => {
        // Convert replay events to TimeLogEntry format
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

    // Handler to toggle replay mode
    const handleToggleReplayMode = () => {
        setReplayMode(!replayMode);
        if (!replayMode) {
            setAutoRefresh(false); // Disable auto-refresh when entering replay mode
        }
    };

    // Convert time logs to TimeLogEntry format for the stream
    const convertedLogs = useMemo(() => {
        return timeLogs.map(log => ({
            id: log.id,
            sequenceId: log.id,
            employeeId: `EMP-${log.id}`,
            employeeName: `Employee ${log.id}`,
            employeePhoto: undefined,
            rfidCard: '****-0000',
            eventType: log.event_type,
            timestamp: log.timestamp,
            deviceId: log.device_id || 'UNKNOWN',
            deviceLocation: log.device_location || 'Unknown Location',
            verified: true,
            hashChain: undefined,
            latencyMs: undefined,
        }));
    }, [timeLogs]);

    // Filter logs based on comprehensive filter configuration
    const filteredLogs = useMemo(() => {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const last7Days = new Date(today);
        last7Days.setDate(last7Days.getDate() - 7);

        return convertedLogs.filter((log) => {
            // 1. Date range filter
            const logDate = new Date(log.timestamp);
            let dateMatch = true;
            
            switch (filters.dateRange) {
                case 'today':
                    dateMatch = logDate >= today;
                    break;
                case 'yesterday':
                    dateMatch = logDate >= yesterday && logDate < today;
                    break;
                case 'this_week':
                    dateMatch = logDate >= last7Days;
                    break;
                case 'custom':
                    if (filters.customDateFrom && filters.customDateTo) {
                        const fromDate = new Date(filters.customDateFrom);
                        const toDate = new Date(filters.customDateTo);
                        toDate.setHours(23, 59, 59, 999); // Include entire end date
                        dateMatch = logDate >= fromDate && logDate <= toDate;
                    }
                    break;
                default:
                    dateMatch = true;
            }

            if (!dateMatch) return false;

            // 2. Department filter (mock implementation - would need department data in log)
            // For now, we'll skip this as mockTimeLogs doesn't have department field
            // In production, this would check: log.department === filters.department
            if (filters.department !== 'all') {
                // Mock: randomly assign some employees to departments for demo
                const mockDepartments = ['production', 'admin', 'sales', 'warehouse', 'quality', 'maintenance'];
                const employeeDept = mockDepartments[parseInt(log.employeeId.slice(-1)) % mockDepartments.length];
                if (employeeDept !== filters.department) return false;
            }

            // 3. Event type filter
            if (filters.eventTypes.length > 0 && !filters.eventTypes.includes(log.eventType)) {
                return false;
            }

            // 4. Verification status filter
            if (filters.verificationStatus !== 'all') {
                const logVerificationStatus = log.verified ? 'verified' : 'failed';
                if (logVerificationStatus !== filters.verificationStatus) {
                    // Check for pending status (could be based on latency or other criteria)
                    if (filters.verificationStatus === 'pending' && log.latencyMs && log.latencyMs > 500) {
                        // Allow through if latency suggests pending
                    } else {
                        return false;
                    }
                }
            }

            // 5. Device location filter
            if (filters.deviceLocations.length > 0 && !filters.deviceLocations.includes('all')) {
                if (!filters.deviceLocations.includes(log.deviceId)) {
                    return false;
                }
            }

            // 6. Employee search filter
            if (filters.employeeSearch) {
                const searchLower = filters.employeeSearch.toLowerCase();
                const nameMatch = log.employeeName.toLowerCase().includes(searchLower);
                const idMatch = log.employeeId.toLowerCase().includes(searchLower);
                if (!nameMatch && !idMatch) {
                    return false;
                }
            }

            // 7. Sequence range filter
            if (filters.sequenceRangeFrom && log.sequenceId < filters.sequenceRangeFrom) {
                return false;
            }
            if (filters.sequenceRangeTo && log.sequenceId > filters.sequenceRangeTo) {
                return false;
            }

            // 8. Latency threshold filter
            if (filters.latencyThreshold && log.latencyMs) {
                if (log.latencyMs <= filters.latencyThreshold) {
                    return false;
                }
            }

            // 9. Violation type filter
            if (filters.violationType && filters.violationType !== 'all') {
                // Mock violation detection based on event type and time
                // In production, this would check actual violation records
                const hour = new Date(log.timestamp).getHours();
                let hasViolation = false;

                switch (filters.violationType) {
                    case 'late_arrival':
                        hasViolation = log.eventType === 'time_in' && hour > 8;
                        break;
                    case 'early_departure':
                        hasViolation = log.eventType === 'time_out' && hour < 17;
                        break;
                    case 'extended_break':
                        hasViolation = log.eventType === 'break_end' && hour > 13;
                        break;
                    case 'missing_punch':
                        // Would need paired event detection
                        hasViolation = false;
                        break;
                }

                if (!hasViolation) return false;
            }

            // All filters passed
            return true;
        });
    }, [convertedLogs, filters]);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'Overview', href: '/hr/timekeeping/overview' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col">
                    <h1 className="text-3xl font-bold">Attendance Overview</h1>
                    <p className="text-gray-600">Monitor attendance metrics and trends </p>
                </div>

                {/* Ledger Health Widget - Full Width at Top */}
                {isLoadingHealth ? (
                    <Card>
                        <CardHeader>
                            <Skeleton className="h-6 w-48" />
                            <Skeleton className="h-4 w-64 mt-2" />
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-4">
                                <Skeleton className="h-24" />
                                <Skeleton className="h-24" />
                                <Skeleton className="h-24" />
                                <Skeleton className="h-24" />
                            </div>
                        </CardContent>
                    </Card>
                ) : healthError ? (
                    <Card className="border-red-200 bg-red-50">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <AlertCircle className="h-5 w-5 text-red-600" />
                                    <div>
                                        <p className="font-medium text-red-900">Failed to load ledger health</p>
                                        <p className="text-sm text-red-700">{healthError}</p>
                                    </div>
                                </div>
                                <Button variant="outline" size="sm" onClick={handleRetryHealth}>
                                    Retry
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : transformedHealthState ? (
                    <LedgerHealthWidget healthState={transformedHealthState} />
                ) : null}

                {/* Two Column Layout: Summary Cards + Live Event Stream */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column: Summary Cards and Other Widgets */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* Summary Cards */}
                        <div className="grid gap-4 grid-cols-1">
                            <SummaryCard
                                title="Attendance Rate"
                                value={`${analytics.summary.average_attendance_rate}%`}
                                description={`of ${analytics.summary.total_employees} employees`}
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('attendance')}
                            />
                            <SummaryCard
                                title="Late Rate"
                                value={`${analytics.summary.average_late_rate}%`}
                                description="late arrivals"
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('late')}
                            />
                            <SummaryCard
                                title="Absent Rate"
                                value={`${analytics.summary.average_absent_rate}%`}
                                description="absent employees"
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('absent')}
                            />
                            <SummaryCard
                                title="Compliance Score"
                                value={analytics.summary.compliance_score}
                                description="overall compliance"
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('compliance')}
                            />
                        </div>

                        {/* Status Distribution */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Status Distribution</CardTitle>
                                <CardDescription>Attendance status breakdown</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {analytics.status_distribution.map((status: StatusDistribution) => (
                                        <div key={status.status} className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="capitalize text-sm font-medium">{status.status}</div>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <div className="text-right">
                                                    <div className="text-sm font-semibold">{status.count}</div>
                                                    <div className="text-xs text-muted-foreground">{status.percentage}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Top Issues */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Top Issues</CardTitle>
                                <CardDescription>Most common attendance issues</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {analytics.top_issues.map((issue: TopIssue) => (
                                        <div key={issue.issue} className="flex items-center justify-between p-2 rounded-lg bg-muted">
                                            <div className="flex items-center gap-3">
                                                <div className="text-sm font-medium">{issue.issue}</div>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <div className="text-sm font-semibold">{issue.count}</div>
                                                <div className={`text-xs px-2 py-1 rounded ${
                                                    issue.trend === 'up' ? 'bg-red-100 text-red-700' :
                                                    issue.trend === 'down' ? 'bg-green-100 text-green-700' :
                                                    'bg-blue-100 text-blue-700'
                                                }`}>
                                                    {issue.trend}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Live Event Stream & Filters */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* Filters Panel */}
                        {showFilterPanel && (
                            <LogsFilterPanel
                                filters={filters}
                                onFiltersChange={handleFiltersChange}
                                onClearFilters={handleClearFilters}
                            />
                        )}

                        {/* Section Header with Toggle */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between flex-wrap gap-2">
                                    <div className="flex items-center gap-3">
                                        <CardTitle>Live Event Stream</CardTitle>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setShowEventStream(!showEventStream)}
                                            className="h-8 w-8 p-0"
                                        >
                                            {showEventStream ? (
                                                <ChevronUp className="h-4 w-4" />
                                            ) : (
                                                <ChevronDown className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                    
                                    <div className="flex items-center gap-3">
                                        <CardDescription className="mt-0 hidden sm:block">
                                            {filteredLogs.length} event{filteredLogs.length !== 1 ? 's' : ''} • Real-time RFID attendance monitoring
                                        </CardDescription>
                                        
                                        {/* Filter Panel Toggle */}
                                        <div className="flex items-center gap-2 border-l pl-3">
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
                                        </div>

                                        {/* Auto-Refresh Toggle */}
                                        <div className="flex items-center gap-2 border-l pl-3">
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
                                        </div>
                                    </div>
                                </div>

                                {/* Last refresh time indicator */}
                                {autoRefresh && showEventStream && (
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        Last refreshed: {lastRefreshTime.toLocaleTimeString()} • Updates every 30 seconds
                                    </div>
                                )}
                            </CardHeader>
                        </Card>

                        {/* Event Stream Component */}
                        {showEventStream && (
                            <>
                                {isLoadingLogs ? (
                                    <Card>
                                        <CardContent className="pt-6">
                                            <div className="space-y-4">
                                                {[...Array(5)].map((_, i) => (
                                                    <div key={i} className="flex gap-4">
                                                        <Skeleton className="h-12 w-12 rounded-full" />
                                                        <div className="flex-1 space-y-2">
                                                            <Skeleton className="h-4 w-3/4" />
                                                            <Skeleton className="h-3 w-1/2" />
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ) : logsError ? (
                                    <Card className="border-red-200 bg-red-50">
                                        <CardContent className="pt-6">
                                            <div className="flex flex-col items-center justify-center gap-4 py-8">
                                                <AlertCircle className="h-12 w-12 text-red-600" />
                                                <div className="text-center">
                                                    <p className="font-medium text-red-900 mb-2">Failed to load events</p>
                                                    <p className="text-sm text-red-700 mb-4">{logsError}</p>
                                                    <Button variant="outline" onClick={handleRetryLogs}>
                                                        <RefreshCw className="h-4 w-4 mr-2" />
                                                        Retry
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ) : (
                                    <TimeLogsStream 
                                        logs={replayMode ? replayEvents : filteredLogs} 
                                        maxHeight="calc(100vh - 500px)"
                                        showLiveIndicator={!replayMode}
                                        autoScroll={!replayMode}
                                    />
                                )}
                            </>
                        )}

                        {/* Replay Mode Toggle */}
                        <div className="flex items-center justify-center gap-2 mt-4">
                            <Button
                                variant={replayMode ? 'default' : 'outline'}
                                size="sm"
                                onClick={handleToggleReplayMode}
                                className="gap-2"
                            >
                                {replayMode ? '📺 Replay Mode Active' : '📺 Enable Replay Mode'}
                            </Button>
                            {replayMode && (
                                <Badge variant="secondary">
                                    Showing {replayEvents.length} events
                                </Badge>
                            )}
                        </div>

                        {/* Event Replay Control (Task 1.8.3 & 1.8.4) */}
                        {replayMode && (
                            <EventReplayControl 
                                className="mt-6" 
                                onVisibleEventsChange={handleReplayVisibleEventsChange}
                            />
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
