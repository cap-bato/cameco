import { Head, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { SummaryCard } from '@/components/timekeeping/summary-card';
import { LedgerHealthWidget, mockHealthStates } from '@/components/timekeeping/ledger-health-widget';
import { TimeLogsStream, mockTimeLogs } from '@/components/timekeeping/time-logs-stream';
import { LogsFilterPanel, LogsFilterConfig, defaultFilters } from '@/components/timekeeping/logs-filter-panel';
import { EventReplayControl } from '@/components/timekeeping/event-replay-control';
import { ChevronDown, ChevronUp, Filter, RefreshCw } from 'lucide-react';

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

    // State for Live Event Stream visibility and filters
    const [showEventStream, setShowEventStream] = useState(true);
    const [showFilterPanel, setShowFilterPanel] = useState(true);
    const [filters, setFilters] = useState<LogsFilterConfig>(defaultFilters);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [lastRefreshTime, setLastRefreshTime] = useState(new Date());
    
    // State for replay mode (Task 1.8.3)
    const [replayMode, setReplayMode] = useState(false);
    const [replayEvents, setReplayEvents] = useState<typeof mockTimeLogs>([]);

    // Auto-refresh effect (simulates real-time updates every 5 seconds)
    useEffect(() => {
        if (!autoRefresh) return;

        const intervalId = setInterval(() => {
            setLastRefreshTime(new Date());
            // In production, this would fetch new data from the server
            console.log('Auto-refresh: Fetching new events...', new Date().toISOString());
        }, 5000);

        return () => clearInterval(intervalId);
    }, [autoRefresh]);

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

    // Filter logs based on comprehensive filter configuration
    const filteredLogs = useMemo(() => {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const last7Days = new Date(today);
        last7Days.setDate(last7Days.getDate() - 7);

        return mockTimeLogs.filter((log) => {
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
    }, [filters]);

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
                <LedgerHealthWidget healthState={mockHealthStates.healthy} />

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
                                        Last refreshed: {lastRefreshTime.toLocaleTimeString()} • Updates every 5 seconds
                                    </div>
                                )}
                            </CardHeader>
                        </Card>

                        {/* Event Stream Component */}
                        {showEventStream && (
                            <TimeLogsStream 
                                logs={replayMode ? replayEvents : filteredLogs} 
                                maxHeight="calc(100vh - 500px)"
                                showLiveIndicator={!replayMode}
                                autoScroll={!replayMode}
                            />
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
