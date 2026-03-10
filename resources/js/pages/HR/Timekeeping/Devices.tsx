import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DeviceStatusDashboard } from '@/components/timekeeping/device-status-dashboard';
import { DeviceMapView } from '@/components/timekeeping/device-map-view';
import { ArrowLeft, LayoutGrid, Map as MapIcon } from 'lucide-react';

interface RecentScan {
    employeeName: string;
    eventType: 'time_in' | 'time_out' | 'break_start' | 'break_end' | 'overtime_start' | 'overtime_end';
    timestamp: string;
}

interface Device {
    id: string;
    location: string;
    status: 'online' | 'idle' | 'offline' | 'maintenance';
    lastScanAgo: string;
    lastScanTimestamp: string;
    scansToday: number;
    uptime: number;
    errorRate?: number;
    recentScans: RecentScan[];
}

interface Summary {
    totalDevices: number;
    onlineDevices: number;
    offlineDevices: number;
    maintenanceDevices: number;
    totalScansToday: number;
    avgUptime: number;
}

interface DevicesProps {
    devices: Device[];
    summary: Summary;
    filters: {
        status: string;
    };
}

export default function Devices({ devices, summary, filters }: DevicesProps) {
    const [view, setView] = useState<'grid' | 'map'>('grid');
    
    const getStatusBadgeVariant = (status: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            online: 'default',
            idle: 'secondary',
            offline: 'destructive',
            maintenance: 'outline',
        };
        return variants[status] || 'default';
    };

    return (
        <AppLayout>
            <Head title="Device Status - Timekeeping" />
            
            <div className="container mx-auto py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('hr.timekeeping.overview')}>
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Overview
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Device Status Dashboard</h1>
                            <p className="text-muted-foreground">
                                Monitor RFID edge devices and scan activity
                            </p>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        <Button
                            variant={view === 'grid' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setView('grid')}
                        >
                            <LayoutGrid className="h-4 w-4 mr-2" />
                            Grid View
                        </Button>
                        <Button
                            variant={view === 'map' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setView('map')}
                        >
                            <MapIcon className="h-4 w-4 mr-2" />
                            Map View
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Total Devices</CardDescription>
                            <CardTitle className="text-3xl">{summary.totalDevices}</CardTitle>
                        </CardHeader>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Online Devices</CardDescription>
                            <CardTitle className="text-3xl text-green-600">
                                {summary.onlineDevices}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-xs text-muted-foreground">
                                {summary.offlineDevices > 0 && (
                                    <span className="text-red-600">
                                        {summary.offlineDevices} offline
                                    </span>
                                )}
                                {summary.maintenanceDevices > 0 && (
                                    <span className="ml-2 text-amber-600">
                                        {summary.maintenanceDevices} maintenance
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Total Scans Today</CardDescription>
                            <CardTitle className="text-3xl">{summary.totalScansToday}</CardTitle>
                        </CardHeader>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Average Uptime</CardDescription>
                            <CardTitle className="text-3xl">{summary.avgUptime}%</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {/* Device Status Content */}
                {view === 'grid' ? (
                    <DeviceStatusDashboard devices={devices} />
                ) : (
                    <DeviceMapView devices={devices} />
                )}

                {/* Device List Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Device List</CardTitle>
                        <CardDescription>
                            Detailed view of all RFID edge devices
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {devices.map((device) => (
                                <div
                                    key={device.id}
                                    className="flex items-center justify-between p-4 rounded-lg border bg-card"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="flex flex-col">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{device.id}</span>
                                                <Badge variant={getStatusBadgeVariant(device.status)}>
                                                    {device.status}
                                                </Badge>
                                            </div>
                                            <span className="text-sm text-muted-foreground">
                                                {device.location}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div className="flex items-center gap-8">
                                        <div className="text-right">
                                            <div className="text-sm font-medium">{device.scansToday}</div>
                                            <div className="text-xs text-muted-foreground">Scans Today</div>
                                        </div>
                                        
                                        <div className="text-right">
                                            <div className="text-sm font-medium">{device.uptime}%</div>
                                            <div className="text-xs text-muted-foreground">Uptime</div>
                                        </div>
                                        
                                        <div className="text-right">
                                            <div className="text-sm font-medium">{device.lastScanAgo}</div>
                                            <div className="text-xs text-muted-foreground">Last Scan</div>
                                        </div>
                                        
                                        <Button variant="outline" size="sm">
                                            View Details
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
