import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Plus,
    RefreshCw,
    Wifi,
    WifiOff,
    AlertTriangle,
    CheckCircle2,
    Clock,
    MoreHorizontal,
    Eye,
    Edit2,
    Activity,
    Trash2,
    AlertCircle,
    Key,
    Copy,
} from 'lucide-react';

interface DeviceConfig {
    device_type?: 'reader' | 'controller' | 'hybrid';
    ip_address?: string;
    port?: number;
    mac_address?: string;
    firmware_version?: string;
    connection_timeout?: number;
    serial_number?: string;
    installation_date?: string;
    notes?: string;
    maintenance_schedule?: string;
    next_maintenance_date?: string;
    maintenance_reminder?: boolean;
    maintenance_notes?: string;
}

interface Device {
    id: number;
    device_id: string;
    device_name: string;
    location: string;
    status: 'online' | 'offline' | 'maintenance';
    last_heartbeat: string | null;
    has_api_key: boolean;
    config: DeviceConfig | null;
    created_at: string;
}

interface TimekeepingDevicesProps {
    devices: Device[];
    stats: {
        total_devices: number;
        online_devices: number;
        offline_devices: number;
        maintenance_due: number;
    };
    flash?: {
        generated_api_key?: string;
        for_device_id?: string;
    };
}

function isMaintenanceDue(device: Device): boolean {
    const d = device.config?.next_maintenance_date;
    if (!d) return false;
    return new Date(d) <= new Date();
}



export default function TimekeepingDevicesIndex({
    devices,
    stats,
}: TimekeepingDevicesProps) {
    const { flash } = usePage().props as { flash?: { generated_api_key?: string; for_device_id?: string } };

    // API Key modal — shown once after generateKey redirect
    const [showApiKeyModal, setShowApiKeyModal] = useState(false);
    const [generatedApiKey, setGeneratedApiKey] = useState<string | null>(null);
    const [generatedForDeviceId, setGeneratedForDeviceId] = useState<string | null>(null);
    const [keyCopied, setKeyCopied] = useState(false);

    useEffect(() => {
        if (flash?.generated_api_key) {
            setGeneratedApiKey(flash.generated_api_key);
            setGeneratedForDeviceId(flash.for_device_id ?? null);
            setShowApiKeyModal(true);
            setKeyCopied(false);
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.generated_api_key]);

    const handleCopyKey = () => {
        if (generatedApiKey) {
            navigator.clipboard.writeText(generatedApiKey);
            setKeyCopied(true);
            setTimeout(() => setKeyCopied(false), 2000);
        }
    };

    const [showRegistrationModal, setShowRegistrationModal] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [registrationFormErrors, setRegistrationFormErrors] = useState<Record<string, string>>({});

    const defaultRegisterForm = { deviceId: '', deviceName: '', location: '', notes: '' };
    const [registerForm, setRegisterForm] = useState(defaultRegisterForm);

    const handleRegisterField = (field: string, value: string) => {
        setRegisterForm(prev => ({ ...prev, [field]: value }));
        setRegistrationFormErrors(prev => ({ ...prev, [field]: '' }));
    };

    const [selectedTab, setSelectedTab] = useState('all');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [showCriticalOnly, setShowCriticalOnly] = useState(false);
    const [showLast24hIssues, setShowLast24hIssues] = useState(false);
    const [lastUpdated, setLastUpdated] = useState<Date>(new Date());

    const hasRecentIssue = (device: Device): boolean => {
        if (!device.last_heartbeat) return false;
        const hbTime = new Date(device.last_heartbeat).getTime();
        const oneDayMs = 24 * 60 * 60 * 1000;
        return device.status !== 'online' && (Date.now() - hbTime) <= oneDayMs;
    };

    const isCritical = (device: Device): boolean => {
        return device.status === 'offline' || (device.status === 'maintenance' && isMaintenanceDue(device));
    };

    /**
     * Filter devices based on selected tab
     */
    const getFilteredDevices = () => {
        let filtered = devices;

        // Apply tab filter
        switch (selectedTab) {
            case 'active':
                filtered = filtered.filter(d => d.status === 'online');
                break;
            case 'offline':
                filtered = filtered.filter(d => d.status === 'offline');
                break;
            case 'maintenance':
                filtered = filtered.filter(d => d.status === 'maintenance' || isMaintenanceDue(d));
                break;
            default:
                break;
        }

        // Apply critical only filter
        if (showCriticalOnly) {
            filtered = filtered.filter(d => isCritical(d));
        }

        // Apply last 24h issues filter
        if (showLast24hIssues) {
            filtered = filtered.filter(d => hasRecentIssue(d));
        }

        return filtered;
    };

    /**
     * Handle page refresh
     */
    const handleRefresh = () => {
        setIsRefreshing(true);
        setLastUpdated(new Date());
        router.reload({
            onFinish: () => setIsRefreshing(false),
        });
    };

    const handleRegisterDevice = () => {
        setShowRegistrationModal(true);
        setRegisterForm(defaultRegisterForm);
        setRegistrationFormErrors({});
    };



    const handleRegisterDeviceSubmit = () => {
        const errors: Record<string, string> = {};
        if (!registerForm.deviceId.trim()) errors.deviceId = 'Device ID is required';
        else if (devices.some(d => d.device_id.toLowerCase() === registerForm.deviceId.trim().toLowerCase())) errors.deviceId = 'Device ID already exists';
        if (!registerForm.deviceName.trim()) errors.deviceName = 'Device name is required';
        if (!registerForm.location.trim()) errors.location = 'Location is required';
        if (Object.keys(errors).length > 0) { setRegistrationFormErrors(errors); return; }

        setIsSubmitting(true);
        router.post('/system/timekeeping/devices', {
            device_id: registerForm.deviceId.trim(),
            device_name: registerForm.deviceName.trim(),
            location: registerForm.location.trim(),
            config: registerForm.notes ? { notes: registerForm.notes } : null,
        }, {
            onSuccess: () => {
                setShowRegistrationModal(false);
                setRegisterForm(defaultRegisterForm);
                setRegistrationFormErrors({});
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    /**
     * Get status badge variant
     */
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'online':
                return <Badge variant="default" className="bg-green-600">Online</Badge>;
            case 'offline':
                return <Badge variant="destructive">Offline</Badge>;
            case 'maintenance':
                return <Badge variant="secondary">Maintenance</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    /**
     * Get status icon
     */
    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'online':
                return <Wifi className="h-4 w-4 text-green-600" />;
            case 'offline':
                return <WifiOff className="h-4 w-4 text-red-600" />;
            case 'maintenance':
                return <AlertTriangle className="h-4 w-4 text-yellow-600" />;
            default:
                return <CheckCircle2 className="h-4 w-4 text-gray-400" />;
        }
    };

    const filteredDevices = getFilteredDevices();

    return (
        <AppLayout>
            <Head title="Device Management" />

            <div className="space-y-6 p-6">
                {/* ================== SECTION: Page Header ================== */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight dark:text-foreground">
                            Device Management
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Monitor and manage RFID scanners and readers
                        </p>
                    </div>

                    {/* ================== SECTION: Action Buttons ================== */}
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            onClick={handleRegisterDevice}
                            className="gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            Register Device
                        </Button>
                        <Button
                            onClick={handleRefresh}
                            variant="outline"
                            className="gap-2"
                            disabled={isRefreshing}
                        >
                            <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* ================== SECTION: Status Dashboard ================== */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Total Devices Card */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Total Devices</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_devices}</div>
                            <p className="text-xs text-muted-foreground">All registered devices</p>
                        </CardContent>
                    </Card>

                    {/* Online Devices Card */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Online</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.online_devices}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.total_devices > 0
                                    ? `${((stats.online_devices / stats.total_devices) * 100).toFixed(0)}% operational`
                                    : 'No devices'}
                            </p>
                        </CardContent>
                    </Card>

                    {/* Offline Devices Card */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Offline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.offline_devices}</div>
                            <p className="text-xs text-muted-foreground">Requires attention</p>
                        </CardContent>
                    </Card>

                    {/* Maintenance Due Card */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Maintenance Due</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{stats.maintenance_due}</div>
                            <p className="text-xs text-muted-foreground">Schedule required</p>
                        </CardContent>
                    </Card>
                </div>

                {/* ================== SECTION: Quick Filters ================== */}
                <Card className="bg-muted/30">
                    <CardHeader>
                        <CardTitle className="text-base">Quick Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="critical-only"
                                    checked={showCriticalOnly}
                                    onCheckedChange={(checked) => setShowCriticalOnly(checked as boolean)}
                                />
                                <label htmlFor="critical-only" className="text-sm font-medium cursor-pointer">
                                    Show Critical Only
                                </label>
                                <Badge variant="destructive" className="ml-2">
                                    Offline or Error
                                </Badge>
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="last-24h"
                                    checked={showLast24hIssues}
                                    onCheckedChange={(checked) => setShowLast24hIssues(checked as boolean)}
                                />
                                <label htmlFor="last-24h" className="text-sm font-medium cursor-pointer">
                                    Last 24h Issues
                                </label>
                                <Badge variant="outline" className="ml-2">
                                    Recent problems
                                </Badge>
                            </div>

                            <div className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                                <Clock className="h-3 w-3" />
                                Last updated: {lastUpdated.toLocaleTimeString()}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ================== SECTION: Tab Navigation ================== */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Device List</CardTitle>
                                <CardDescription>
                                    Manage and monitor all registered RFID devices
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="text-right">
                                    <div className="text-xs text-muted-foreground flex items-center gap-1">
                                        <Clock className="h-3 w-3" />
                                        Updated: {lastUpdated.toLocaleTimeString()}
                                    </div>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={handleRefresh}
                                    disabled={isRefreshing}
                                    className="gap-2"
                                >
                                    <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                    {isRefreshing ? 'Syncing...' : 'Refresh'}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Tabs */}
                        <Tabs value={selectedTab} onValueChange={setSelectedTab} className="w-full">
                            <div className="mb-4">
                                <TabsList className="grid w-full max-w-md grid-cols-4">
                                    <TabsTrigger value="all">
                                        All
                                        <span className="ml-2 text-xs">
                                            ({devices.length})
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="active">
                                        Online
                                        <span className="ml-2 text-xs">
                                            ({devices.filter(d => d.status === 'online').length})
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="offline">
                                        Offline
                                        <span className="ml-2 text-xs">
                                            ({devices.filter(d => d.status === 'offline').length})
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="maintenance">
                                        Service
                                        <span className="ml-2 text-xs">
                                            ({devices.filter(d => d.status === 'maintenance' || isMaintenanceDue(d)).length})
                                        </span>
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            {/* Tab: All Devices */}
                            <TabsContent value="all" className="space-y-4">
                                {filteredDevices.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center p-8 text-center">
                                        <WifiOff className="h-8 w-8 text-muted-foreground mb-2" />
                                        <p className="text-muted-foreground">No devices registered yet</p>
                                        <Button
                                            onClick={handleRegisterDevice}
                                            variant="link"
                                            className="mt-2"
                                        >
                                            Register your first device
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {filteredDevices.map(device => (
                                            <DeviceRow key={device.id} device={device} statusIcon={getStatusIcon} statusBadge={getStatusBadge} />
                                        ))}
                                    </div>
                                )}
                            </TabsContent>

                            {/* Tab: Online Devices */}
                            <TabsContent value="active" className="space-y-4">
                                {filteredDevices.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center p-8 text-center">
                                        <Wifi className="h-8 w-8 text-muted-foreground mb-2" />
                                        <p className="text-muted-foreground">No online devices</p>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {filteredDevices.map(device => (
                                            <DeviceRow key={device.id} device={device} statusIcon={getStatusIcon} statusBadge={getStatusBadge} />
                                        ))}
                                    </div>
                                )}
                            </TabsContent>

                            {/* Tab: Offline Devices */}
                            <TabsContent value="offline" className="space-y-4">
                                {filteredDevices.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center p-8 text-center">
                                        <CheckCircle2 className="h-8 w-8 text-green-600 mb-2" />
                                        <p className="text-muted-foreground">All devices are online!</p>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {filteredDevices.map(device => (
                                            <DeviceRow key={device.id} device={device} statusIcon={getStatusIcon} statusBadge={getStatusBadge} />
                                        ))}
                                    </div>
                                )}
                            </TabsContent>

                            {/* Tab: Maintenance Devices */}
                            <TabsContent value="maintenance" className="space-y-4">
                                {filteredDevices.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center p-8 text-center">
                                        <CheckCircle2 className="h-8 w-8 text-green-600 mb-2" />
                                        <p className="text-muted-foreground">No devices requiring maintenance</p>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {filteredDevices.map(device => (
                                            <DeviceRow key={device.id} device={device} statusIcon={getStatusIcon} statusBadge={getStatusBadge} />
                                        ))}
                                    </div>
                                )}
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>

                {/* ================== SECTION: Help Text ================== */}
                <Card className="bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800">
                    <CardHeader>
                        <CardTitle className="text-base">Device Management Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground space-y-2">
                        <p>
                            • Register new RFID scanners using the <strong>Register Device</strong> button
                        </p>
                        <p>
                            • After creating a device, click <strong>&#8943; → Generate API Key</strong> to get the bearer token for the gate PC
                        </p>
                        <p>
                            • The API key is shown <strong>only once</strong> — copy it immediately and add it to the gate PC&apos;s <code>.env</code> as <code>API_KEY</code>
                        </p>
                        <p>
                            • Monitor real-time device status: Online, Offline, or Maintenance
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* ========== API Key Display Modal ========== */}
            <Dialog open={showApiKeyModal} onOpenChange={(open) => { if (!open) { setShowApiKeyModal(false); setGeneratedApiKey(null); } }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5 text-green-600" />
                            API Key Generated
                        </DialogTitle>
                        <DialogDescription>
                            {generatedForDeviceId && (
                                <>Device: <strong>{generatedForDeviceId}</strong><br /></>
                            )}
                            This key will only be shown <strong>once</strong>. Copy it now and add it to the gate PC&apos;s <code>.env</code> as <code>API_KEY</code>.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <div className="relative">
                            <input
                                type="text"
                                readOnly
                                value={generatedApiKey ?? ''}
                                className="w-full rounded-md border bg-muted px-3 py-3 font-mono text-sm text-foreground select-all cursor-text"
                                onClick={(e) => (e.target as HTMLInputElement).select()}
                            />
                        </div>
                        <div className="mt-3 p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 rounded-md">
                            <div className="flex items-start gap-2">
                                <AlertTriangle className="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" />
                                <p className="text-xs text-amber-800 dark:text-amber-400">
                                    This API key will <strong>not be shown again</strong>. If lost, generate a new one — the previous key will be permanently invalidated and the gate PC must be reconfigured.
                                </p>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setShowApiKeyModal(false); setGeneratedApiKey(null); }}>
                            Close
                        </Button>
                        <Button
                            onClick={handleCopyKey}
                            className={keyCopied ? 'bg-green-600 hover:bg-green-700' : ''}
                        >
                            {keyCopied ? (
                                <><CheckCircle2 className="h-4 w-4 mr-1" />Copied!</>
                            ) : (
                                <><Copy className="h-4 w-4 mr-1" />Copy Key</>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ========== DEVICE REGISTRATION MODAL ========== */}
            <Dialog open={showRegistrationModal} onOpenChange={(open) => { if (!open) { setShowRegistrationModal(false); setRegisterForm(defaultRegisterForm); setRegistrationFormErrors({}); } }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Register New Device</DialogTitle>
                        <DialogDescription>
                            Add a new RFID reader to the system. After saving, generate an API key so the gate PC can connect.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Device ID <span className="text-red-500">*</span></label>
                            <input
                                type="text"
                                value={registerForm.deviceId}
                                onChange={(e) => handleRegisterField('deviceId', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.deviceId ? 'border-red-500' : ''}`}
                                placeholder="e.g. GATE-01"
                            />
                            {registrationFormErrors.deviceId && <p className="text-xs text-red-500">{registrationFormErrors.deviceId}</p>}
                            <p className="text-xs text-muted-foreground">Must match <code>DEVICE_ID</code> in the gate PC&apos;s <code>.env</code></p>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Device Name <span className="text-red-500">*</span></label>
                            <input
                                type="text"
                                value={registerForm.deviceName}
                                onChange={(e) => handleRegisterField('deviceName', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.deviceName ? 'border-red-500' : ''}`}
                                placeholder="e.g. Main Gate Reader"
                            />
                            {registrationFormErrors.deviceName && <p className="text-xs text-red-500">{registrationFormErrors.deviceName}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Location <span className="text-red-500">*</span></label>
                            <input
                                type="text"
                                value={registerForm.location}
                                onChange={(e) => handleRegisterField('location', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.location ? 'border-red-500' : ''}`}
                                placeholder="e.g. Building A - Main Entrance"
                            />
                            {registrationFormErrors.location && <p className="text-xs text-red-500">{registrationFormErrors.location}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Notes (Optional)</label>
                            <textarea
                                value={registerForm.notes}
                                onChange={(e) => handleRegisterField('notes', e.target.value)}
                                className="w-full px-3 py-2 border rounded-md text-sm"
                                placeholder="Any notes about this device"
                                rows={2}
                            />
                        </div>

                        <div className="rounded-md border border-blue-200 bg-blue-50 dark:bg-blue-950/20 dark:border-blue-900 p-3 flex items-start gap-2">
                            <Key className="h-4 w-4 text-blue-600 flex-shrink-0 mt-0.5" />
                            <p className="text-xs text-blue-800 dark:text-blue-300">
                                After saving, use <strong>⋯ → Generate API Key</strong> to get the bearer token for the gate PC.
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <button
                            type="button"
                            onClick={() => { setShowRegistrationModal(false); setRegisterForm(defaultRegisterForm); setRegistrationFormErrors({}); }}
                            className="px-4 py-2 text-sm border rounded-md hover:bg-muted"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={handleRegisterDeviceSubmit}
                            disabled={isSubmitting}
                            className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
                        >
                            {isSubmitting ? 'Saving...' : 'Register Device'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

/**
 * Device Row Component - Subtask 1.2.3: Add Row Actions
 */
function DeviceRow({
    device,
    statusIcon: getStatusIcon,
    statusBadge: getStatusBadge,
}: {
    device: Device;
    statusIcon: (status: string) => React.ReactNode;
    statusBadge: (status: string) => React.ReactNode;
}) {
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showTestModal, setShowTestModal] = useState(false);
    const [showDeactivateConfirm, setShowDeactivateConfirm] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    const [isTestingConnection, setIsTestingConnection] = useState(false);
    const [testResult, setTestResult] = useState<{ success: boolean; message: string; details?: string } | null>(null);
    const [timelineFilter, setTimelineFilter] = useState<'all' | 'heartbeat' | 'scan' | 'config' | 'maintenance' | 'status'>('all');
    const [timelinePage, setTimelinePage] = useState(1);

    // Edit form state
    const [editForm, setEditForm] = useState({
        device_name: device.device_name,
        location: device.location,
        status: device.status,
        notes: device.config?.notes ?? '',
    });

    const maintenanceDue = isMaintenanceDue(device);

    const rowClass =
        device.status === 'offline'
            ? 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900'
            : device.status === 'maintenance' || maintenanceDue
                ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900'
                : 'bg-white dark:bg-background border-border';

    const handleDelete = () => {
        setIsProcessing(true);
        router.delete(`/system/timekeeping/devices/${device.id}`, {
            onFinish: () => { setIsProcessing(false); setShowDeactivateConfirm(false); },
        });
    };

    const handleSaveEdit = () => {
        setIsProcessing(true);
        router.patch(`/system/timekeeping/devices/${device.id}`, editForm, {
            onSuccess: () => { setShowEditModal(false); setIsProcessing(false); },
            onError: () => setIsProcessing(false),
        });
    };

    const handleGenerateKey = () => {
        setIsProcessing(true);
        router.post(`/system/timekeeping/devices/${device.id}/generate-key`, {}, {
            onFinish: () => setIsProcessing(false),
        });
    };

    const handleRevokeKey = () => {
        if (!confirm(`Revoke API key for "${device.device_name}"? The gate PC will stop working until a new key is generated.`)) return;
        setIsProcessing(true);
        router.post(`/system/timekeeping/devices/${device.id}/revoke-key`, {}, {
            onFinish: () => setIsProcessing(false),
        });
    };

    const numericDeviceId = device.id;
    const mockStats = {
        scansToday: 120 + numericDeviceId * 9,
        scansWeek: 820 + numericDeviceId * 41,
        scansMonth: 3600 + numericDeviceId * 125,
        avgResponseTimeMs: device.status === 'online' ? 42 : device.status === 'maintenance' ? 65 : 110,
    };

    const uptimeLabel =
        device.status === 'online'
            ? '99.8% (last 30 days)'
            : device.status === 'maintenance'
                ? '96.5% (scheduled service window)'
                : '89.2% (degraded)';

    const lastMaintenance = maintenanceDue ? '2025-12-20' : '2026-02-15';
    const nextScheduledMaintenance = maintenanceDue ? 'Due now' : device.config?.next_maintenance_date ?? '—';
    const maintenanceHistory = maintenanceDue
        ? ['Routine maintenance completed - 2025-12-20', 'Firmware check performed - 2025-09-20']
        : ['Routine maintenance completed - 2026-02-15', 'Connectivity calibration - 2025-11-30'];

    const timelineEvents = [
        {
            id: `${device.id}-evt-1`,
            type: 'heartbeat' as const,
            title: 'Heartbeat received',
            description: 'Device heartbeat received and latency is within threshold.',
            timestamp: '2026-03-04 09:45',
        },
        {
            id: `${device.id}-evt-2`,
            type: 'scan' as const,
            title: 'Scan processed',
            description: 'RFID card scan accepted and attendance event recorded.',
            timestamp: '2026-03-04 09:30',
        },
        {
            id: `${device.id}-evt-3`,
            type: 'config' as const,
            title: 'Configuration changed',
            description: 'Network timeout updated during configuration review.',
            timestamp: '2026-03-04 08:20',
        },
        {
            id: `${device.id}-evt-4`,
            type: 'maintenance' as const,
            title: 'Maintenance performed',
            description: 'Routine inspection completed and logs synchronized.',
            timestamp: '2026-03-03 17:10',
        },
        {
            id: `${device.id}-evt-5`,
            type: 'status' as const,
            title: 'Device went offline/online',
            description: 'Temporary disconnect detected and service restored.',
            timestamp: '2026-03-03 14:50',
        },
        {
            id: `${device.id}-evt-6`,
            type: 'scan' as const,
            title: 'Scan processed',
            description: 'High-volume badge scan burst processed successfully.',
            timestamp: '2026-03-03 12:05',
        },
        {
            id: `${device.id}-evt-7`,
            type: 'heartbeat' as const,
            title: 'Heartbeat received',
            description: 'Device health check passed with stable response.',
            timestamp: '2026-03-03 09:15',
        },
        {
            id: `${device.id}-evt-8`,
            type: 'maintenance' as const,
            title: 'Maintenance performed',
            description: 'Firmware verification and reader calibration complete.',
            timestamp: '2026-03-02 16:40',
        },
    ];

    const filteredTimelineEvents = timelineFilter === 'all'
        ? timelineEvents
        : timelineEvents.filter((event) => event.type === timelineFilter);

    const timelineItemsPerPage = 4;
    const totalTimelinePages = Math.max(1, Math.ceil(filteredTimelineEvents.length / timelineItemsPerPage));
    const safeTimelinePage = Math.min(timelinePage, totalTimelinePages);
    const paginatedTimelineEvents = filteredTimelineEvents.slice(
        (safeTimelinePage - 1) * timelineItemsPerPage,
        safeTimelinePage * timelineItemsPerPage,
    );

    const handleTestConnection = () => {
        setIsTestingConnection(true);
        setTestResult(null);
        setTimeout(() => {
            const success = device.status === 'online';
            setTestResult(
                success
                    ? { success: true, message: 'Connection successful', details: device.config?.ip_address ? `Device reachable at ${device.config.ip_address}` : 'Device is online' }
                    : { success: false, message: 'Connection failed', details: 'Device unreachable. Check network and power status.' }
            );
            setIsTestingConnection(false);
        }, 1800);
    };

    const handleTestNowFromDetails = () => {
        setShowDetailsModal(false);
        setShowTestModal(true);
        handleTestConnection();
    };

    return (
        <>
            <div className={`rounded-lg border p-4 transition-all hover:shadow-md hover:scale-[1.01] ${rowClass}`}>
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                        {getStatusIcon(device.status)}
                        <div className="min-w-0">
                            <p className="font-medium truncate">{device.device_name}</p>
                            <p className="text-sm text-muted-foreground truncate">
                                {device.location}
                                {device.config?.ip_address && (
                                    <> &bull; {device.config.ip_address}{device.config?.port ? `:${device.config.port}` : ''}</>
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="hidden sm:block text-right">
                        <p className="text-sm capitalize">{device.config?.device_type ?? '—'}</p>
                        <p className="text-xs text-muted-foreground hidden md:block">
                            {device.config?.firmware_version ? `v${device.config.firmware_version}` : '—'}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {getStatusBadge(device.status)}
                        {device.has_api_key && (
                            <Badge variant="outline" className="text-xs hidden sm:flex items-center gap-1">
                                <Key className="h-3 w-3" /> Key
                            </Badge>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => {
                                        setTimelineFilter('all');
                                        setTimelinePage(1);
                                        setShowDetailsModal(true);
                                    }}
                                >
                                    <Eye className="h-4 w-4 mr-2" />
                                    View Details
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setShowEditModal(true)}>
                                    <Edit2 className="h-4 w-4 mr-2" />
                                    Edit Settings
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleGenerateKey} disabled={isProcessing}>
                                    <Key className="h-4 w-4 mr-2" />
                                    Generate API Key
                                </DropdownMenuItem>
                                {device.has_api_key && (
                                    <DropdownMenuItem onClick={handleRevokeKey} disabled={isProcessing} className="text-amber-600">
                                        <AlertCircle className="h-4 w-4 mr-2" />
                                        Revoke API Key
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => setShowDeactivateConfirm(true)} className="text-red-600">
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete Device
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            <Dialog open={showDetailsModal} onOpenChange={setShowDetailsModal}>
                <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Device Details</DialogTitle>
                        <DialogDescription>{device.device_name}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Overview</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Device ID</p>
                                    <p className="font-medium font-mono">{device.device_id}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Status</p>
                                    <p className="font-medium capitalize">{device.status}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Uptime</p>
                                    <p className="font-medium">{uptimeLabel}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Last Heartbeat</p>
                                    <p className="font-medium">{device.last_heartbeat || 'No heartbeat reported'}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Configuration</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Device Name</p>
                                    <p className="font-medium">{device.device_name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Type</p>
                                    <p className="font-medium capitalize">{device.config?.device_type ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">IP Address</p>
                                    <p className="font-medium font-mono">{device.config?.ip_address ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Port</p>
                                    <p className="font-medium">{device.config?.port ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Protocol</p>
                                    <p className="font-medium">TCP</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Firmware</p>
                                    <p className="font-medium">{device.config?.firmware_version ? `v${device.config.firmware_version}` : '—'}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Statistics</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Scans Today</p>
                                    <p className="font-medium">{mockStats.scansToday}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Scans This Week</p>
                                    <p className="font-medium">{mockStats.scansWeek}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Scans This Month</p>
                                    <p className="font-medium">{mockStats.scansMonth}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Avg Response Time</p>
                                    <p className="font-medium">{mockStats.avgResponseTimeMs} ms</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Maintenance</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-muted-foreground">Last Maintenance</p>
                                        <p className="font-medium">{lastMaintenance}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Next Scheduled</p>
                                        <p className="font-medium">{nextScheduledMaintenance}</p>
                                    </div>
                                </div>
                                <div>
                                    <p className="text-muted-foreground mb-1">History</p>
                                    <ul className="space-y-1">
                                        {maintenanceHistory.map((entry) => (
                                            <li key={entry} className="text-xs text-muted-foreground">• {entry}</li>
                                        ))}
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Location</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Site</p>
                                    <p className="font-medium">{device.location}</p>
                                </div>
                                <div className="rounded-md border bg-muted/30 p-4 text-xs text-muted-foreground">
                                    Map preview is available when device coordinates are configured.
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <div className="flex items-center justify-between gap-3">
                                    <CardTitle className="text-base">Activity Timeline</CardTitle>
                                    <div className="flex items-center gap-2">
                                        <label className="text-xs text-muted-foreground">Filter</label>
                                        <select
                                            value={timelineFilter}
                                            onChange={(e) => {
                                                setTimelineFilter(e.target.value as 'all' | 'heartbeat' | 'scan' | 'config' | 'maintenance' | 'status');
                                                setTimelinePage(1);
                                            }}
                                            className="h-8 rounded-md border px-2 text-xs"
                                        >
                                            <option value="all">All Events</option>
                                            <option value="heartbeat">Heartbeat</option>
                                            <option value="scan">Scan</option>
                                            <option value="config">Configuration</option>
                                            <option value="maintenance">Maintenance</option>
                                            <option value="status">Status</option>
                                        </select>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                {paginatedTimelineEvents.length === 0 ? (
                                    <p className="text-xs text-muted-foreground">No timeline events for the selected filter.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {paginatedTimelineEvents.map((event) => (
                                            <div key={event.id} className="flex gap-3 rounded-md border p-3">
                                                <div className="pt-0.5">
                                                    <Clock className="h-4 w-4 text-muted-foreground" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium text-sm">{event.title}</p>
                                                        <Badge variant="outline" className="text-[10px] uppercase tracking-wide">
                                                            {event.type}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground mt-1">{event.description}</p>
                                                    <p className="text-[11px] text-muted-foreground mt-2">{event.timestamp}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="flex items-center justify-between">
                                    <p className="text-xs text-muted-foreground">
                                        Page {safeTimelinePage} of {totalTimelinePages}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setTimelinePage((prev) => Math.max(1, prev - 1))}
                                            disabled={safeTimelinePage === 1}
                                        >
                                            Previous
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setTimelinePage((prev) => Math.min(totalTimelinePages, prev + 1))}
                                            disabled={safeTimelinePage >= totalTimelinePages}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowDetailsModal(false);
                                setShowEditModal(true);
                            }}
                        >
                            Edit
                        </Button>
                        <Button onClick={handleTestNowFromDetails}>
                            Test Now
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showEditModal} onOpenChange={setShowEditModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Device Settings</DialogTitle>
                        <DialogDescription>{device.device_name}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2">
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Device Name</label>
                            <input
                                type="text"
                                value={editForm.device_name}
                                onChange={(e) => setEditForm(f => ({ ...f, device_name: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-md text-sm"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Location</label>
                            <input
                                type="text"
                                value={editForm.location}
                                onChange={(e) => setEditForm(f => ({ ...f, location: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-md text-sm"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Status</label>
                            <select
                                value={editForm.status}
                                onChange={(e) => setEditForm(f => ({ ...f, status: e.target.value as Device['status'] }))}
                                className="w-full px-3 py-2 border rounded-md text-sm"
                            >
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Notes</label>
                            <textarea
                                value={editForm.notes}
                                onChange={(e) => setEditForm(f => ({ ...f, notes: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-md text-sm"
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowEditModal(false)} disabled={isProcessing}>
                            Cancel
                        </Button>
                        <Button onClick={handleSaveEdit} disabled={isProcessing || !editForm.device_name.trim() || !editForm.location.trim()}>
                            {isProcessing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showTestModal} onOpenChange={setShowTestModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Test Connection: {device.device_name}</DialogTitle>
                        <DialogDescription>
                            {device.config?.ip_address
                                ? `Testing connection to ${device.config.ip_address}${device.config.port ? `:${device.config.port}` : ''}`
                                : 'Testing device connectivity'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        {isTestingConnection && (
                            <div className="flex items-center justify-center p-6">
                                <div className="animate-spin">
                                    <Activity className="h-8 w-8 text-blue-500" />
                                </div>
                            </div>
                        )}

                        {testResult && (
                            <div className={`p-4 rounded-lg ${testResult.success ? 'bg-green-50 dark:bg-green-950/20' : 'bg-red-50 dark:bg-red-950/20'}`}>
                                <div className="flex items-center gap-2 mb-2">
                                    {testResult.success ? (
                                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                                    ) : (
                                        <AlertCircle className="h-5 w-5 text-red-600" />
                                    )}
                                    <p className="font-medium text-sm">{testResult.message}</p>
                                </div>
                                {testResult.details && (
                                    <p className="text-xs text-muted-foreground">{testResult.details}</p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowTestModal(false);
                                setTestResult(null);
                            }}
                        >
                            Close
                        </Button>
                        {!testResult && (
                            <Button onClick={handleTestConnection} disabled={isTestingConnection}>
                                {isTestingConnection ? 'Testing...' : 'Run Test'}
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showDeactivateConfirm} onOpenChange={setShowDeactivateConfirm}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete Device</DialogTitle>
                        <DialogDescription>This action cannot be undone.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="p-4 bg-destructive/10 rounded-lg border border-destructive/20">
                            <p className="text-sm font-medium mb-1">Warning</p>
                            <p className="text-sm">
                                Are you sure you want to delete <strong>{device.device_name}</strong>?
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                The device will be permanently removed. The gate PC will stop working until re-registered.
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeactivateConfirm(false)} disabled={isProcessing}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={isProcessing}>
                            {isProcessing ? 'Deleting...' : 'Delete Device'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}