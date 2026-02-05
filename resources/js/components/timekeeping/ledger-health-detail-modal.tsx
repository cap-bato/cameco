import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CheckCircle2, AlertTriangle, XCircle, Activity, Clock, Shield, Wifi, WifiOff, Database, HardDrive, Zap, AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { LedgerHealthStatus } from './ledger-health-widget';

/**
 * Extended Ledger Health State with additional detail metrics
 */
interface LedgerHealthDetailState {
    status: LedgerHealthStatus;
    lastSequence: number;
    lastProcessedAgo: string;
    processingRate: number;
    integrityStatus: 'verified' | 'pending' | 'hash_mismatch_detected';
    devicesOnline: number;
    devicesOffline: number;
    backlog: number;
    // Additional detailed metrics
    uptime: string;
    totalEventsProcessed: number;
    averageLatency: number;
    peakProcessingRate: number;
    hashValidationRate: number;
    lastIntegrityCheck: string;
    databaseSize: string;
    ledgerAge: string;
    deviceDetails?: Array<{
        id: string;
        location: string;
        status: 'online' | 'offline' | 'maintenance';
        lastSeen: string;
        eventsToday: number;
    }>;
    recentErrors?: Array<{
        timestamp: string;
        type: string;
        message: string;
        severity: 'low' | 'medium' | 'high';
    }>;
}

interface LedgerHealthDetailModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    healthState: LedgerHealthDetailState;
}

/**
 * Get status configuration
 */
const getStatusConfig = (status: LedgerHealthStatus) => {
    const configs = {
        healthy: {
            badge: '🟢 HEALTHY',
            icon: CheckCircle2,
            iconColor: 'text-green-600',
            badgeBg: 'bg-green-100',
            badgeText: 'text-green-700',
        },
        warning: {
            badge: '🟡 WARNING',
            icon: AlertTriangle,
            iconColor: 'text-yellow-600',
            badgeBg: 'bg-yellow-100',
            badgeText: 'text-yellow-700',
        },
        critical: {
            badge: '🔴 CRITICAL',
            icon: XCircle,
            iconColor: 'text-red-600',
            badgeBg: 'bg-red-100',
            badgeText: 'text-red-700',
        },
    };
    return configs[status];
};

/**
 * Ledger Health Detail Modal Component
 * Displays comprehensive ledger health metrics and diagnostic information
 * 
 * @component
 */
export function LedgerHealthDetailModal({ 
    open, 
    onOpenChange, 
    healthState 
}: LedgerHealthDetailModalProps) {
    const config = getStatusConfig(healthState.status);
    const StatusIcon = config.icon;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <StatusIcon className={cn('h-6 w-6', config.iconColor)} />
                        <DialogTitle className="text-2xl">Ledger Health Details</DialogTitle>
                    </div>
                    <DialogDescription>
                        Comprehensive diagnostics and metrics for RFID event ledger system
                    </DialogDescription>
                    <Badge 
                        className={cn('w-fit font-bold', config.badgeBg, config.badgeText)}
                    >
                        {config.badge}
                    </Badge>
                </DialogHeader>

                <Tabs defaultValue="overview" className="mt-4">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="performance">Performance</TabsTrigger>
                        <TabsTrigger value="devices">Devices</TabsTrigger>
                        <TabsTrigger value="errors">Errors</TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview" className="space-y-4 mt-4">
                        <div className="grid grid-cols-2 gap-4">
                            {/* System Status */}
                            <div className="p-4 border rounded-lg bg-gray-50">
                                <div className="flex items-center gap-2 mb-3">
                                    <Activity className="h-5 w-5 text-blue-600" />
                                    <h3 className="font-semibold text-gray-900">System Status</h3>
                                </div>
                                <div className="space-y-2">
                                    <MetricRow label="Uptime" value={healthState.uptime} />
                                    <MetricRow label="Ledger Age" value={healthState.ledgerAge} />
                                    <MetricRow label="Last Processed" value={healthState.lastProcessedAgo} />
                                    <MetricRow label="Status" value={healthState.status.toUpperCase()} valueClassName={config.iconColor} />
                                </div>
                            </div>

                            {/* Processing Metrics */}
                            <div className="p-4 border rounded-lg bg-gray-50">
                                <div className="flex items-center gap-2 mb-3">
                                    <Zap className="h-5 w-5 text-amber-600" />
                                    <h3 className="font-semibold text-gray-900">Processing Metrics</h3>
                                </div>
                                <div className="space-y-2">
                                    <MetricRow 
                                        label="Current Rate" 
                                        value={`${healthState.processingRate} events/min`} 
                                    />
                                    <MetricRow 
                                        label="Peak Rate" 
                                        value={`${healthState.peakProcessingRate} events/min`} 
                                    />
                                    <MetricRow 
                                        label="Avg Latency" 
                                        value={`${healthState.averageLatency}ms`} 
                                    />
                                    <MetricRow 
                                        label="Backlog" 
                                        value={`${healthState.backlog.toLocaleString()} events`} 
                                        valueClassName={healthState.backlog > 500 ? 'text-red-600' : 'text-green-600'}
                                    />
                                </div>
                            </div>

                            {/* Integrity Status */}
                            <div className="p-4 border rounded-lg bg-gray-50">
                                <div className="flex items-center gap-2 mb-3">
                                    <Shield className="h-5 w-5 text-purple-600" />
                                    <h3 className="font-semibold text-gray-900">Integrity Status</h3>
                                </div>
                                <div className="space-y-2">
                                    <MetricRow 
                                        label="Chain Status" 
                                        value={healthState.integrityStatus === 'verified' ? '✅ Verified' : 
                                               healthState.integrityStatus === 'pending' ? '⏳ Pending' : 
                                               '❌ Mismatch Detected'}
                                        valueClassName={
                                            healthState.integrityStatus === 'verified' ? 'text-green-600' :
                                            healthState.integrityStatus === 'pending' ? 'text-yellow-600' :
                                            'text-red-600'
                                        }
                                    />
                                    <MetricRow 
                                        label="Validation Rate" 
                                        value={`${healthState.hashValidationRate}%`} 
                                    />
                                    <MetricRow 
                                        label="Last Check" 
                                        value={healthState.lastIntegrityCheck} 
                                    />
                                    <MetricRow 
                                        label="Sequence" 
                                        value={`#${healthState.lastSequence.toLocaleString()}`} 
                                    />
                                </div>
                            </div>

                            {/* Database Status */}
                            <div className="p-4 border rounded-lg bg-gray-50">
                                <div className="flex items-center gap-2 mb-3">
                                    <HardDrive className="h-5 w-5 text-indigo-600" />
                                    <h3 className="font-semibold text-gray-900">Database Status</h3>
                                </div>
                                <div className="space-y-2">
                                    <MetricRow 
                                        label="Total Events" 
                                        value={healthState.totalEventsProcessed.toLocaleString()} 
                                    />
                                    <MetricRow 
                                        label="Database Size" 
                                        value={healthState.databaseSize} 
                                    />
                                    <MetricRow 
                                        label="Devices Online" 
                                        value={`${healthState.devicesOnline}/${healthState.devicesOnline + healthState.devicesOffline}`} 
                                    />
                                    <MetricRow 
                                        label="Devices Offline" 
                                        value={healthState.devicesOffline.toString()} 
                                        valueClassName={healthState.devicesOffline > 0 ? 'text-red-600' : 'text-green-600'}
                                    />
                                </div>
                            </div>
                        </div>
                    </TabsContent>

                    {/* Performance Tab */}
                    <TabsContent value="performance" className="space-y-4 mt-4">
                        <div className="p-4 border rounded-lg bg-gray-50">
                            <h3 className="font-semibold text-gray-900 mb-4">Performance Metrics</h3>
                            <div className="space-y-3">
                                <ProgressMetric 
                                    label="Processing Rate" 
                                    current={healthState.processingRate}
                                    max={healthState.peakProcessingRate}
                                    unit="events/min"
                                    status={healthState.processingRate > 300 ? 'good' : healthState.processingRate > 100 ? 'warning' : 'critical'}
                                />
                                <ProgressMetric 
                                    label="Hash Validation" 
                                    current={healthState.hashValidationRate}
                                    max={100}
                                    unit="%"
                                    status={healthState.hashValidationRate > 95 ? 'good' : healthState.hashValidationRate > 80 ? 'warning' : 'critical'}
                                />
                                <ProgressMetric 
                                    label="Backlog Level" 
                                    current={healthState.backlog}
                                    max={2000}
                                    unit="events"
                                    status={healthState.backlog < 100 ? 'good' : healthState.backlog < 500 ? 'warning' : 'critical'}
                                    inverse
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <StatCard 
                                icon={<Zap className="h-5 w-5" />}
                                label="Peak Rate"
                                value={`${healthState.peakProcessingRate} events/min`}
                                status="good"
                            />
                            <StatCard 
                                icon={<Clock className="h-5 w-5" />}
                                label="Avg Latency"
                                value={`${healthState.averageLatency}ms`}
                                status={healthState.averageLatency < 100 ? 'good' : healthState.averageLatency < 500 ? 'warning' : 'critical'}
                            />
                            <StatCard 
                                icon={<Database className="h-5 w-5" />}
                                label="Total Events"
                                value={healthState.totalEventsProcessed.toLocaleString()}
                                status="good"
                            />
                        </div>
                    </TabsContent>

                    {/* Devices Tab */}
                    <TabsContent value="devices" className="space-y-4 mt-4">
                        <div className="space-y-3">
                            {healthState.deviceDetails && healthState.deviceDetails.length > 0 ? (
                                healthState.deviceDetails.map((device) => (
                                    <div key={device.id} className="p-4 border rounded-lg bg-gray-50">
                                        <div className="flex items-center justify-between mb-3">
                                            <div className="flex items-center gap-3">
                                                {device.status === 'online' ? (
                                                    <Wifi className="h-5 w-5 text-green-600" />
                                                ) : device.status === 'maintenance' ? (
                                                    <Activity className="h-5 w-5 text-yellow-600" />
                                                ) : (
                                                    <WifiOff className="h-5 w-5 text-red-600" />
                                                )}
                                                <div>
                                                    <h3 className="font-semibold text-gray-900">{device.id}</h3>
                                                    <p className="text-sm text-gray-500">{device.location}</p>
                                                </div>
                                            </div>
                                            <Badge 
                                                className={cn(
                                                    device.status === 'online' ? 'bg-green-100 text-green-700' :
                                                    device.status === 'maintenance' ? 'bg-yellow-100 text-yellow-700' :
                                                    'bg-red-100 text-red-700'
                                                )}
                                            >
                                                {device.status.toUpperCase()}
                                            </Badge>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2 text-sm">
                                            <MetricRow label="Last Seen" value={device.lastSeen} />
                                            <MetricRow label="Events Today" value={device.eventsToday.toLocaleString()} />
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="p-8 text-center text-gray-500">
                                    No device information available
                                </div>
                            )}
                        </div>
                    </TabsContent>

                    {/* Errors Tab */}
                    <TabsContent value="errors" className="space-y-4 mt-4">
                        <div className="space-y-3">
                            {healthState.recentErrors && healthState.recentErrors.length > 0 ? (
                                healthState.recentErrors.map((error, index) => (
                                    <div key={index} className={cn(
                                        'p-4 border-l-4 rounded-lg',
                                        error.severity === 'high' ? 'border-red-500 bg-red-50' :
                                        error.severity === 'medium' ? 'border-yellow-500 bg-yellow-50' :
                                        'border-blue-500 bg-blue-50'
                                    )}>
                                        <div className="flex items-start gap-3">
                                            <AlertCircle className={cn(
                                                'h-5 w-5 mt-0.5',
                                                error.severity === 'high' ? 'text-red-600' :
                                                error.severity === 'medium' ? 'text-yellow-600' :
                                                'text-blue-600'
                                            )} />
                                            <div className="flex-1">
                                                <div className="flex items-center justify-between mb-1">
                                                    <h4 className="font-semibold text-gray-900">{error.type}</h4>
                                                    <Badge 
                                                        className={cn(
                                                            error.severity === 'high' ? 'bg-red-100 text-red-700' :
                                                            error.severity === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                                                            'bg-blue-100 text-blue-700'
                                                        )}
                                                    >
                                                        {error.severity.toUpperCase()}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-gray-700 mb-2">{error.message}</p>
                                                <p className="text-xs text-gray-500">{error.timestamp}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="p-8 text-center">
                                    <CheckCircle2 className="h-12 w-12 text-green-500 mx-auto mb-3" />
                                    <p className="text-gray-600 font-medium">No recent errors</p>
                                    <p className="text-sm text-gray-500 mt-1">System is operating normally</p>
                                </div>
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}

// Helper Components

function MetricRow({ label, value, valueClassName }: { label: string; value: string; valueClassName?: string }) {
    return (
        <div className="flex justify-between items-center text-sm">
            <span className="text-gray-600">{label}:</span>
            <span className={cn('font-semibold', valueClassName || 'text-gray-900')}>{value}</span>
        </div>
    );
}

function ProgressMetric({ 
    label, 
    current, 
    max, 
    unit, 
    status,
    inverse = false
}: { 
    label: string; 
    current: number; 
    max: number; 
    unit: string; 
    status: 'good' | 'warning' | 'critical';
    inverse?: boolean;
}) {
    const percentage = Math.min((current / max) * 100, 100);
    const displayPercentage = inverse ? 100 - percentage : percentage;
    
    return (
        <div>
            <div className="flex justify-between items-center mb-2">
                <span className="text-sm font-medium text-gray-700">{label}</span>
                <span className="text-sm font-semibold text-gray-900">
                    {current.toLocaleString()} {unit}
                </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div 
                    className={cn(
                        'h-2.5 rounded-full transition-all duration-500',
                        status === 'good' ? 'bg-green-500' :
                        status === 'warning' ? 'bg-yellow-500' :
                        'bg-red-500'
                    )}
                    style={{ width: `${displayPercentage}%` }}
                />
            </div>
        </div>
    );
}

function StatCard({ 
    icon, 
    label, 
    value, 
    status 
}: { 
    icon: React.ReactNode; 
    label: string; 
    value: string; 
    status: 'good' | 'warning' | 'critical';
}) {
    return (
        <div className={cn(
            'p-4 border rounded-lg',
            status === 'good' ? 'bg-green-50 border-green-200' :
            status === 'warning' ? 'bg-yellow-50 border-yellow-200' :
            'bg-red-50 border-red-200'
        )}>
            <div className={cn(
                'mb-2',
                status === 'good' ? 'text-green-600' :
                status === 'warning' ? 'text-yellow-600' :
                'text-red-600'
            )}>
                {icon}
            </div>
            <p className="text-xs text-gray-600 mb-1">{label}</p>
            <p className="text-lg font-bold text-gray-900">{value}</p>
        </div>
    );
}
