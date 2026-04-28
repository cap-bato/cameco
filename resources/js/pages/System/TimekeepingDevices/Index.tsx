import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
import { Plus, RefreshCw, Wifi, WifiOff, AlertTriangle, MoreHorizontal, Key, Copy, CheckCircle2, AlertCircle } from 'lucide-react';

interface Device {
    id: number;
    device_id: string;
    device_name: string;
    location: string;
    status: 'online' | 'offline' | 'maintenance';
    last_heartbeat: string | null;
    has_api_key: boolean;
    config: { notes?: string } | null;
    created_at: string;
}

interface Props {
    devices: Device[];
    stats: {
        total_devices: number;
        online_devices: number;
        offline_devices: number;
    };
}

export default function TimekeepingDevicesIndex({ devices, stats }: Props) {
    const { flash } = usePage().props as { flash?: { generated_api_key?: string; for_device_id?: string } };

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
    const [registerErrors, setRegisterErrors] = useState<Record<string, string>>({});
    const defaultForm = { deviceId: '', deviceName: '', location: '', notes: '' };
    const [registerForm, setRegisterForm] = useState(defaultForm);

    const handleField = (field: string, value: string) => {
        setRegisterForm(prev => ({ ...prev, [field]: value }));
        setRegisterErrors(prev => ({ ...prev, [field]: '' }));
    };

    const [isRefreshing, setIsRefreshing] = useState(false);
    const [statusFilter, setStatusFilter] = useState<'all' | 'online' | 'offline' | 'maintenance'>('all');

    const filteredDevices = statusFilter === 'all'
        ? devices
        : devices.filter(d => d.status === statusFilter);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({ onFinish: () => setIsRefreshing(false) });
    };

    const handleRegisterSubmit = () => {
        const errors: Record<string, string> = {};
        if (!registerForm.deviceId.trim()) errors.deviceId = 'Device ID is required';
        else if (devices.some(d => d.device_id.toLowerCase() === registerForm.deviceId.trim().toLowerCase())) errors.deviceId = 'Device ID already exists';
        if (!registerForm.deviceName.trim()) errors.deviceName = 'Device name is required';
        if (!registerForm.location.trim()) errors.location = 'Location is required';
        if (Object.keys(errors).length > 0) { setRegisterErrors(errors); return; }

        setIsSubmitting(true);
        router.post('/system/timekeeping/devices', {
            device_id: registerForm.deviceId.trim(),
            device_name: registerForm.deviceName.trim(),
            location: registerForm.location.trim(),
            config: registerForm.notes ? { notes: registerForm.notes } : null,
        }, {
            onSuccess: () => { setShowRegistrationModal(false); setRegisterForm(defaultForm); setIsSubmitting(false); },
            onError: () => setIsSubmitting(false),
        });
    };

    return (
        <AppLayout>
            <Head title="Device Management" />
            <div className="space-y-6 p-6">

                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Device Management</h1>
                        <p className="text-muted-foreground mt-1">Monitor and manage RFID scanners and readers</p>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={() => { setShowRegistrationModal(true); setRegisterForm(defaultForm); setRegisterErrors({}); }} className="gap-2">
                            <Plus className="h-4 w-4" /> Register Device
                        </Button>
                        <Button onClick={handleRefresh} variant="outline" className="gap-2" disabled={isRefreshing}>
                            <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Total Devices</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold">{stats.total_devices}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Online</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold text-green-600">{stats.online_devices}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Offline</CardTitle></CardHeader>
                        <CardContent><div className="text-2xl font-bold text-red-600">{stats.offline_devices}</div></CardContent>
                    </Card>
                </div>

                {/* Device List */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-4">
                            <CardTitle>Devices</CardTitle>
                            <div className="flex gap-1">
                                {(['all', 'online', 'offline', 'maintenance'] as const).map(s => (
                                    <Button
                                        key={s}
                                        size="sm"
                                        variant={statusFilter === s ? 'default' : 'ghost'}
                                        onClick={() => setStatusFilter(s)}
                                        className="capitalize"
                                    >
                                        {s === 'all' ? `All (${devices.length})` : `${s} (${devices.filter(d => d.status === s).length})`}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {filteredDevices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-8 text-center">
                                <WifiOff className="h-8 w-8 text-muted-foreground mb-2" />
                                <p className="text-muted-foreground">
                                    {statusFilter === 'all' ? 'No devices registered yet' : `No ${statusFilter} devices`}
                                </p>
                                {statusFilter === 'all' && (
                                    <Button onClick={() => setShowRegistrationModal(true)} variant="link" className="mt-2">
                                        Register your first device
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {filteredDevices.map(device => (
                                    <DeviceRow key={device.id} device={device} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* API Key Modal */}
            <Dialog open={showApiKeyModal} onOpenChange={(open) => { if (!open) { setShowApiKeyModal(false); setGeneratedApiKey(null); } }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5 text-green-600" /> API Key Generated
                        </DialogTitle>
                        <DialogDescription>
                            {generatedForDeviceId && <>{`Device: `}<strong>{generatedForDeviceId}</strong><br /></>}
                            {`This key will only be shown `}<strong>once</strong>{`. Add it to the gate PC's `}<code>.env</code>{` as `}<code>API_KEY</code>.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4 space-y-3">
                        <input
                            type="text"
                            readOnly
                            value={generatedApiKey ?? ''}
                            className="w-full rounded-md border bg-muted px-3 py-3 font-mono text-sm text-foreground select-all cursor-text"
                            onClick={(e) => (e.target as HTMLInputElement).select()}
                        />
                        <div className="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 rounded-md">
                            <AlertTriangle className="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" />
                            <p className="text-xs text-amber-800 dark:text-amber-400">
                                {`This key will `}<strong>not be shown again</strong>{`. If lost, generate a new one — the old key is permanently invalidated.`}
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setShowApiKeyModal(false); setGeneratedApiKey(null); }}>Close</Button>
                        <Button onClick={handleCopyKey} className={keyCopied ? 'bg-green-600 hover:bg-green-700' : ''}>
                            {keyCopied ? <><CheckCircle2 className="h-4 w-4 mr-1" />Copied!</> : <><Copy className="h-4 w-4 mr-1" />Copy Key</>}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Registration Modal */}
            <Dialog open={showRegistrationModal} onOpenChange={(open) => { if (!open) { setShowRegistrationModal(false); setRegisterForm(defaultForm); setRegisterErrors({}); } }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Register New Device</DialogTitle>
                        <DialogDescription>
                            After saving, generate an API key so the gate PC can connect.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-1">
                            <label className="text-sm font-medium">Device ID <span className="text-red-500">*</span></label>
                            <input type="text" value={registerForm.deviceId} onChange={e => handleField('deviceId', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md text-sm ${registerErrors.deviceId ? 'border-red-500' : ''}`}
                                placeholder="e.g. GATE-01" />
                            {registerErrors.deviceId && <p className="text-xs text-red-500">{registerErrors.deviceId}</p>}
                            <p className="text-xs text-muted-foreground">{`Must match `}<code>DEVICE_ID</code>{` in the gate PC's `}<code>.env</code></p>
                        </div>
                        <div className="space-y-1">
                            <label className="text-sm font-medium">Device Name <span className="text-red-500">*</span></label>
                            <input type="text" value={registerForm.deviceName} onChange={e => handleField('deviceName', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md text-sm ${registerErrors.deviceName ? 'border-red-500' : ''}`}
                                placeholder="e.g. Main Gate Reader" />
                            {registerErrors.deviceName && <p className="text-xs text-red-500">{registerErrors.deviceName}</p>}
                        </div>
                        <div className="space-y-1">
                            <label className="text-sm font-medium">Location <span className="text-red-500">*</span></label>
                            <input type="text" value={registerForm.location} onChange={e => handleField('location', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md text-sm ${registerErrors.location ? 'border-red-500' : ''}`}
                                placeholder="e.g. Building A - Main Entrance" />
                            {registerErrors.location && <p className="text-xs text-red-500">{registerErrors.location}</p>}
                        </div>
                        <div className="space-y-1">
                            <label className="text-sm font-medium">Notes</label>
                            <textarea value={registerForm.notes} onChange={e => handleField('notes', e.target.value)}
                                className="w-full px-3 py-2 border rounded-md text-sm" rows={2} placeholder="Optional notes" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setShowRegistrationModal(false); setRegisterForm(defaultForm); setRegisterErrors({}); }}>Cancel</Button>
                        <Button onClick={handleRegisterSubmit} disabled={isSubmitting}>
                            {isSubmitting ? 'Saving...' : 'Register Device'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: Device['status'] }) {
    if (status === 'online') return <Badge className="bg-green-600">Online</Badge>;
    if (status === 'offline') return <Badge variant="destructive">Offline</Badge>;
    return <Badge variant="secondary">Maintenance</Badge>;
}

function StatusIcon({ status }: { status: Device['status'] }) {
    if (status === 'online') return <Wifi className="h-4 w-4 text-green-600" />;
    if (status === 'offline') return <WifiOff className="h-4 w-4 text-red-600" />;
    return <AlertTriangle className="h-4 w-4 text-yellow-600" />;
}

function DeviceRow({ device }: { device: Device }) {
    const [showDetails, setShowDetails] = useState(false);
    const [showEdit, setShowEdit] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    const [editForm, setEditForm] = useState({
        device_name: device.device_name,
        location: device.location,
        status: device.status,
        notes: device.config?.notes ?? '',
    });

    const rowClass = device.status === 'offline'
        ? 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900'
        : device.status === 'maintenance'
            ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900'
            : 'bg-white dark:bg-background border-border';

    const handleDelete = () => {
        setIsProcessing(true);
        router.delete(`/system/timekeeping/devices/${device.id}`, {
            onFinish: () => { setIsProcessing(false); setShowDeleteConfirm(false); },
        });
    };

    const handleSaveEdit = () => {
        setIsProcessing(true);
        router.patch(`/system/timekeeping/devices/${device.id}`, editForm, {
            onSuccess: () => { setShowEdit(false); setIsProcessing(false); },
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

    return (
        <>
            <div className={`rounded-lg border p-4 transition-all hover:shadow-sm ${rowClass}`}>
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                        <StatusIcon status={device.status} />
                        <div className="min-w-0">
                            <p className="font-medium truncate">{device.device_name}</p>
                            <p className="text-sm text-muted-foreground truncate">{device.location}</p>
                        </div>
                    </div>
                    <div className="hidden sm:block text-right text-xs text-muted-foreground">
                        <p className="font-mono">{device.device_id}</p>
                        <p>{device.last_heartbeat ? new Date(device.last_heartbeat).toLocaleString() : 'No heartbeat'}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={device.status} />
                        {device.has_api_key && (
                            <Badge variant="outline" className="text-xs hidden sm:flex items-center gap-1">
                                <Key className="h-3 w-3" /> Key
                            </Badge>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm"><MoreHorizontal className="h-4 w-4" /></Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => setShowDetails(true)}>View Details</DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setShowEdit(true)}>Edit</DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleGenerateKey} disabled={isProcessing}>
                                    <Key className="h-4 w-4 mr-2" /> Generate API Key
                                </DropdownMenuItem>
                                {device.has_api_key && (
                                    <DropdownMenuItem onClick={handleRevokeKey} disabled={isProcessing} className="text-amber-600">
                                        <AlertCircle className="h-4 w-4 mr-2" /> Revoke API Key
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => setShowDeleteConfirm(true)} className="text-red-600">
                                    Delete Device
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            {/* Details */}
            <Dialog open={showDetails} onOpenChange={setShowDetails}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{device.device_name}</DialogTitle>
                        <DialogDescription>{device.device_id}</DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4 py-2 text-sm">
                        <div><p className="text-muted-foreground">Status</p><p className="font-medium capitalize">{device.status}</p></div>
                        <div><p className="text-muted-foreground">API Key</p><p className="font-medium">{device.has_api_key ? 'Configured' : 'Not set'}</p></div>
                        <div><p className="text-muted-foreground">Location</p><p className="font-medium">{device.location}</p></div>
                        <div><p className="text-muted-foreground">Registered</p><p className="font-medium">{new Date(device.created_at).toLocaleDateString()}</p></div>
                        <div className="col-span-2"><p className="text-muted-foreground">Last Heartbeat</p><p className="font-medium">{device.last_heartbeat ? new Date(device.last_heartbeat).toLocaleString() : 'Never'}</p></div>
                        {device.config?.notes && (
                            <div className="col-span-2"><p className="text-muted-foreground">Notes</p><p className="font-medium">{device.config.notes}</p></div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDetails(false)}>Close</Button>
                        <Button onClick={() => { setShowDetails(false); setShowEdit(true); }}>Edit</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit */}
            <Dialog open={showEdit} onOpenChange={setShowEdit}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Device</DialogTitle>
                        <DialogDescription>{device.device_id}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2">
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Device Name</label>
                            <input type="text" value={editForm.device_name} onChange={e => setEditForm(f => ({ ...f, device_name: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-md text-sm" />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Location</label>
                            <input type="text" value={editForm.location} onChange={e => setEditForm(f => ({ ...f, location: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-md text-sm" />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Status</label>
                            <select value={editForm.status} onChange={e => setEditForm(f => ({ ...f, status: e.target.value as Device['status'] }))}
                                className="w-full px-3 py-2 border rounded-md text-sm">
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-muted-foreground">Notes</label>
                            <textarea value={editForm.notes} onChange={e => setEditForm(f => ({ ...f, notes: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-md text-sm" rows={3} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowEdit(false)} disabled={isProcessing}>Cancel</Button>
                        <Button onClick={handleSaveEdit} disabled={isProcessing || !editForm.device_name.trim() || !editForm.location.trim()}>
                            {isProcessing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <Dialog open={showDeleteConfirm} onOpenChange={setShowDeleteConfirm}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Device</DialogTitle>
                        <DialogDescription>This action cannot be undone.</DialogDescription>
                    </DialogHeader>
                    <p className="text-sm py-2">
                        Are you sure you want to delete <strong>{device.device_name}</strong>? The gate PC will stop working until re-registered.
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeleteConfirm(false)} disabled={isProcessing}>Cancel</Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={isProcessing}>
                            {isProcessing ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
