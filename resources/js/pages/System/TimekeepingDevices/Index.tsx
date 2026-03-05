import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
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
    Download,
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
} from 'lucide-react';

/**
 * Device interface representing RFID scanner/reader information
 */
interface Device {
    id: string;
    name: string;
    device_type: 'reader' | 'controller' | 'hybrid';
    status: 'online' | 'offline' | 'maintenance' | 'error';
    location: string;
    ip_address: string;
    port: number;
    last_heartbeat: string | null;
    installation_date: string;
    firmware_version: string;
    sync_status: 'synced' | 'pending' | 'failed';
    maintenance_due: boolean;
    last_issue_at: string | null; // For "Last 24h Issues" filter
    notes?: string;
}

/**
 * Props for the Device Management page
 */
interface TimekeepingDevicesProps {
    devices: Device[];
    stats: {
        total_devices: number;
        online_devices: number;
        offline_devices: number;
        maintenance_due: number;
    };
}

/**
 * Mock Device Data - Subtask 1.1.3
 * 
 * 14 sample RFID devices representing a realistic deployment:
 * - 8 Online devices (operational)
 * - 2 Offline devices (needs attention)
 * - 2 Maintenance devices (scheduled service)
 * - 2 Error devices (recent issues)
 * 
 * Variety includes:
 * - Device Types: readers, controllers, hybrid
 * - Locations: Building A, Building B, Warehouse, Parking
 * - Last Issues: Some within 24h (for filter testing), some None
 */
const MOCK_DEVICES: Device[] = [
    // ========== ONLINE DEVICES ==========
    {
        id: '1',
        name: 'Main Gate Entrance',
        device_type: 'reader',
        status: 'online',
        location: 'Building A - Main Gate',
        ip_address: '192.168.1.101',
        port: 8000,
        last_heartbeat: '2026-03-03T14:30:15Z',
        installation_date: '2024-01-15',
        firmware_version: '2.3.1',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
        notes: 'Primary entrance scanner',
    },
    {
        id: '2',
        name: 'Parking Lot South',
        device_type: 'hybrid',
        status: 'online',
        location: 'Parking Lot - South Entrance',
        ip_address: '192.168.1.102',
        port: 8001,
        last_heartbeat: '2026-03-03T14:28:45Z',
        installation_date: '2024-02-20',
        firmware_version: '2.2.8',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
        notes: 'Secondary parking entrance',
    },
    {
        id: '3',
        name: 'Building B Reader',
        device_type: 'reader',
        status: 'online',
        location: 'Building B - Lobby',
        ip_address: '192.168.1.103',
        port: 8002,
        last_heartbeat: '2026-03-03T14:32:20Z',
        installation_date: '2024-03-10',
        firmware_version: '2.3.0',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
    },
    {
        id: '4',
        name: 'Loading Dock Controller',
        device_type: 'controller',
        status: 'online',
        location: 'Warehouse - Loading Dock',
        ip_address: '192.168.1.104',
        port: 8003,
        last_heartbeat: '2026-03-03T14:31:10Z',
        installation_date: '2024-04-05',
        firmware_version: '2.1.5',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
        notes: 'Controls main warehouse access',
    },
    {
        id: '5',
        name: 'Office Floor 2',
        device_type: 'reader',
        status: 'online',
        location: 'Building A - Floor 2',
        ip_address: '192.168.1.105',
        port: 8004,
        last_heartbeat: '2026-03-03T14:29:55Z',
        installation_date: '2024-01-20',
        firmware_version: '2.3.1',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
    },
    {
        id: '6',
        name: 'Emergency Exit',
        device_type: 'reader',
        status: 'online',
        location: 'Building A - Stairwell',
        ip_address: '192.168.1.106',
        port: 8005,
        last_heartbeat: '2026-03-03T14:33:00Z',
        installation_date: '2024-05-12',
        firmware_version: '2.3.0',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
        notes: 'Emergency access point',
    },
    {
        id: '7',
        name: 'Parking Lot North',
        device_type: 'hybrid',
        status: 'online',
        location: 'Parking Lot - North Entrance',
        ip_address: '192.168.1.107',
        port: 8006,
        last_heartbeat: '2026-03-03T14:30:40Z',
        installation_date: '2024-06-01',
        firmware_version: '2.2.9',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
    },
    {
        id: '8',
        name: 'Server Room Access',
        device_type: 'controller',
        status: 'online',
        location: 'Building B - Basement',
        ip_address: '192.168.1.108',
        port: 8007,
        last_heartbeat: '2026-03-03T14:32:50Z',
        installation_date: '2024-03-25',
        firmware_version: '2.3.1',
        sync_status: 'synced',
        maintenance_due: false,
        last_issue_at: null,
        notes: 'Restricted access zone',
    },
    // ========== OFFLINE DEVICES (Needs Attention) ==========
    {
        id: '9',
        name: 'Conference Room A',
        device_type: 'reader',
        status: 'offline',
        location: 'Building A - Floor 3',
        ip_address: '192.168.1.109',
        port: 8008,
        last_heartbeat: '2026-03-02T08:15:00Z',
        installation_date: '2024-07-10',
        firmware_version: '2.2.5',
        sync_status: 'failed',
        maintenance_due: false,
        last_issue_at: '2026-03-03T12:45:00Z',
        notes: 'Connection lost - network issue suspected',
    },
    {
        id: '10',
        name: 'Warehouse Storage B',
        device_type: 'reader',
        status: 'offline',
        location: 'Warehouse - Storage Area B',
        ip_address: '192.168.1.110',
        port: 8009,
        last_heartbeat: '2026-03-01T16:20:00Z',
        installation_date: '2024-08-03',
        firmware_version: '2.1.8',
        sync_status: 'failed',
        maintenance_due: false,
        last_issue_at: '2026-03-03T11:30:00Z',
        notes: 'Offline for 20+ hours - requires immediate attention',
    },
    // ========== MAINTENANCE DEVICES (Scheduled Service) ==========
    {
        id: '11',
        name: 'Visitor Center',
        device_type: 'reader',
        status: 'maintenance',
        location: 'Building A - Ground Floor',
        ip_address: '192.168.1.111',
        port: 8010,
        last_heartbeat: '2026-03-02T17:45:00Z',
        installation_date: '2024-04-20',
        firmware_version: '2.1.2',
        sync_status: 'pending',
        maintenance_due: true,
        last_issue_at: '2026-03-02T14:00:00Z',
        notes: 'Quarterly maintenance scheduled for 2026-03-15',
    },
    {
        id: '12',
        name: 'Service Entrance',
        device_type: 'controller',
        status: 'maintenance',
        location: 'Warehouse - Service Entrance',
        ip_address: '192.168.1.112',
        port: 8011,
        last_heartbeat: '2026-03-03T10:00:00Z',
        installation_date: '2024-05-30',
        firmware_version: '2.2.6',
        sync_status: 'pending',
        maintenance_due: true,
        last_issue_at: null,
        notes: 'Firmware update needed - v2.3.1 available',
    },
    // ========== ERROR DEVICES (Recent Issues) ==========
    {
        id: '13',
        name: 'Laboratory Access',
        device_type: 'hybrid',
        status: 'error',
        location: 'Building B - Laboratory',
        ip_address: '192.168.1.113',
        port: 8012,
        last_heartbeat: '2026-03-03T14:00:00Z',
        installation_date: '2024-09-11',
        firmware_version: '2.2.7',
        sync_status: 'pending',
        maintenance_due: false,
        last_issue_at: '2026-03-03T13:45:00Z',
        notes: 'Intermittent connectivity - troubleshooting in progress',
    },
    {
        id: '14',
        name: 'Cafeteria Entry',
        device_type: 'reader',
        status: 'error',
        location: 'Building A - Cafeteria',
        ip_address: '192.168.1.114',
        port: 8013,
        last_heartbeat: '2026-03-03T13:30:00Z',
        installation_date: '2024-10-02',
        firmware_version: '2.3.0',
        sync_status: 'failed',
        maintenance_due: false,
        last_issue_at: '2026-03-03T13:15:00Z',
        notes: 'High timeout errors - may need replacement',
    },
];

/**
 * Device Management Layout Component - Subtask 1.1.1 & 1.1.3
 * 
 * Main page component displaying RFID device management interface
 * Features:
 * - Page header with title and breadcrumbs
 * - Action buttons: Register New Device, Sync with Server, Export Report
 * - Tab navigation: All Devices, Active, Offline, Maintenance
 * - Responsive layout (grid on desktop, stack on mobile)
 * - Status dashboard with stats cards
 * - Mock data for testing (Subtask 1.1.3)
 */
export default function TimekeepingDevicesIndex({
    devices: propsDevices,
    stats: propsStats,
}: Partial<TimekeepingDevicesProps> = {}) {
    // Use mock data if no devices passed (Subtask 1.1.3)
    const devices = propsDevices && propsDevices.length > 0 ? propsDevices : MOCK_DEVICES;

    // Calculate stats from devices if not provided
    const stats = propsStats || {
        total_devices: devices.length,
        online_devices: devices.filter(d => d.status === 'online').length,
        offline_devices: devices.filter(d => d.status === 'offline').length,
        maintenance_due: devices.filter(d => d.maintenance_due).length,
    };

    // Registration Modal State - Subtask 1.3.1
     
    const [showRegistrationModal, setShowRegistrationModal] = useState(false);
    const [currentStep, setCurrentStep] = useState(1);
    const [registrationFormErrors, setRegistrationFormErrors] = useState<Record<string, string>>({});
    
    // Step 4: Test Connection State - Subtask 1.3.5
    const [testStatus, setTestStatus] = useState<'untested' | 'testing' | 'success' | 'failure'>('untested');
    const [testResults, setTestResults] = useState<{
        reachable: boolean;
        handshake: boolean;
        firmwareConfirmed: boolean;
        certificateWarning: boolean;
    } | null>(null);

    // Form Data by Step
    const [formData, setFormData] = useState({
        // Step 1: Basic Information
        deviceId: `DVC-${String(devices.length + 1).padStart(4, '0')}`,
        deviceName: '',
        location: '',
        deviceType: 'reader' as 'reader' | 'controller' | 'hybrid',
        serialNumber: '',
        installationDate: new Date().toISOString().split('T')[0],
        notes: '',

        // Step 2: Network Configuration
        protocol: 'tcp' as 'tcp' | 'udp' | 'http' | 'mqtt',
        ipAddress: '',
        port: 8000,
        macAddress: '',
        firmwareVersion: '',
        connectionTimeout: 30,

        // Step 3: Maintenance Settings
        maintenanceSchedule: 'monthly' as 'weekly' | 'monthly' | 'quarterly' | 'annually',
        nextMaintenanceDate: '',
        maintenanceReminder: true,
        maintenanceNotes: '',
    });
    const [selectedTab, setSelectedTab] = useState('all');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [showCriticalOnly, setShowCriticalOnly] = useState(false);
    const [showLast24hIssues, setShowLast24hIssues] = useState(false);
    const [lastUpdated, setLastUpdated] = useState<Date>(new Date());

    /**
     * Check if device had issues in the last 24 hours
     */
    const hasRecentIssue = (device: Device): boolean => {
        if (!device.last_issue_at) return false;
        const issueTime = new Date(device.last_issue_at).getTime();
        const now = new Date().getTime();
        const oneDayMs = 24 * 60 * 60 * 1000;
        return (now - issueTime) <= oneDayMs;
    };

    /**
     * Check if device is critical
     */
    const isCritical = (device: Device): boolean => {
        return (
            device.status === 'offline' ||
            device.status === 'error' ||
            (device.status === 'maintenance' && device.maintenance_due)
        );
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
                filtered = filtered.filter(d => d.status === 'maintenance' || d.maintenance_due);
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

    /**
     * Handle register new device - Opens registration modal
     */
    const handleRegisterDevice = () => {
        setShowRegistrationModal(true);
        setCurrentStep(1);
        setRegistrationFormErrors({});
        setTestStatus('untested');
        setTestResults(null);
    };

    /**
     * Handle sync with server
     */
    const handleSyncWithServer = () => {
        // TODO: Trigger sync API call
        console.log('Syncing with FastAPI server...');
    };

    /**
     * Handle export report
     */
    const handleExportReport = () => {
        // TODO: Trigger export API call
        console.log('Exporting device report...');
    };

    /**
     * Get validation errors for a specific registration step
     */
    const getStepValidationErrors = (
        step: number,
        data: typeof formData,
    ): Record<string, string> => {
        const errors: Record<string, string> = {};

        if (step === 1) {
            if (!data.deviceId.trim()) {
                errors.deviceId = 'Device ID is required';
            } else if (devices.some((device) => device.id.toLowerCase() === data.deviceId.trim().toLowerCase())) {
                errors.deviceId = 'Device ID must be unique';
            }

            if (!data.deviceName.trim()) {
                errors.deviceName = 'Device name is required';
            }
            if (!data.location.trim()) {
                errors.location = 'Location is required';
            }
            if (!data.deviceType) {
                errors.deviceType = 'Device type is required';
            }
        } else if (step === 2) {
            const ipRegex = /^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/;
            const macRegex = /^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/;

            if (!data.ipAddress.trim()) {
                errors.ipAddress = 'IP address is required';
            } else if (!ipRegex.test(data.ipAddress.trim())) {
                errors.ipAddress = 'Invalid IP address format';
            }

            if (!data.port || Number.isNaN(data.port) || data.port < 1 || data.port > 65535) {
                errors.port = 'Port must be between 1 and 65535';
            }

            if (data.macAddress && !macRegex.test(data.macAddress.trim())) {
                errors.macAddress = 'Invalid MAC address format';
            }
        } else if (step === 3) {
            if (!data.maintenanceSchedule) {
                errors.maintenanceSchedule = 'Maintenance schedule is required';
            }

            if (data.nextMaintenanceDate) {
                const selectedDate = new Date(data.nextMaintenanceDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDate < today) {
                    errors.nextMaintenanceDate = 'Maintenance date must be today or later';
                }
            }
        }

        return errors;
    };

    /**
     * Validate current step
     */
    const validateStep = (step: number): boolean => {
        const errors = getStepValidationErrors(step, formData);

        setRegistrationFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    /**
     * Handle next step in registration
     */
     
    const handleNextStep = () => {
        if (validateStep(currentStep)) {
            if (currentStep < 4) {
                setCurrentStep(currentStep + 1);
            }
        }
    };

    /**
     * Handle previous step in registration
     */
     
    const handlePreviousStep = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    /**
     * Handle form field changes
     */
     
    const handleFormChange = (field: string, value: string | number | boolean) => {
        setFormData(prev => {
            const nextData = {
                ...prev,
                [field]: value,
            };

            if (currentStep >= 1 && currentStep <= 3) {
                setRegistrationFormErrors(getStepValidationErrors(currentStep, nextData));
            }

            return nextData;
        });
    };

    const isCurrentStepValid = currentStep < 4
        ? Object.keys(getStepValidationErrors(currentStep, formData)).length === 0
        : true;

    /**
     * Handle connection test - Subtask 1.3.5
     * Simulates device connectivity test with mock results
     */
    const handleTestConnection = () => {
        setTestStatus('testing');
        setTestResults(null);

        // Simulate API call with 2-second delay
        setTimeout(() => {
            // Mock test results - all checks pass with certificate warning
            const mockResults = {
                reachable: true,
                handshake: true,
                firmwareConfirmed: true,
                certificateWarning: true, // Warning: Certificate expires in 30 days
            };

            setTestResults(mockResults);
            setTestStatus('success');
        }, 2000);
    };

    /**
     * Handle device registration submission
     */
     
    const handleRegisterDeviceSubmit = () => {
        if (validateStep(3)) {
            // TODO: Call API to register device
            console.log('Registering device:', formData);
            setShowRegistrationModal(false);
            setCurrentStep(1);
            setTestStatus('untested');
            setTestResults(null);
            // Reset form
            setFormData({
                deviceId: `DVC-${String(devices.length + 1).padStart(4, '0')}`,
                deviceName: '',
                location: '',
                deviceType: 'reader',
                serialNumber: '',
                installationDate: new Date().toISOString().split('T')[0],
                notes: '',
                protocol: 'tcp',
                ipAddress: '',
                port: 8000,
                macAddress: '',
                firmwareVersion: '',
                connectionTimeout: 30,
                maintenanceSchedule: 'monthly',
                nextMaintenanceDate: '',
                maintenanceReminder: true,
                maintenanceNotes: '',
            });
        }
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
            case 'error':
                return <Badge variant="destructive" className="bg-red-600">Error</Badge>;
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
                            onClick={handleSyncWithServer}
                            variant="outline"
                            className="gap-2"
                        >
                            <RefreshCw className="h-4 w-4" />
                            Sync Server
                        </Button>
                        <Button
                            onClick={handleExportReport}
                            variant="outline"
                            className="gap-2"
                        >
                            <Download className="h-4 w-4" />
                            Export
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
                                    Offline, Error, or Due
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
                                            ({devices.filter(d => d.status === 'maintenance' || d.maintenance_due).length})
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
                            • Register new RFID scanners and readers using the <strong>Register Device</strong> button
                        </p>
                        <p>
                            • Monitor real-time device status: Online, Offline, or Maintenance
                        </p>
                        <p>
                            • Click <strong>Sync Server</strong> to synchronize device data with FastAPI backend
                        </p>
                        <p>
                            • Export device reports for compliance and auditing
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* ========== DEVICE REGISTRATION MODAL - SUBTASK 1.3.1 ========== */}
            <Dialog open={showRegistrationModal} onOpenChange={setShowRegistrationModal}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Register New Device</DialogTitle>
                        <DialogDescription>
                            Step {currentStep} of 4: {currentStep === 1 && 'Basic Information'}
                            {currentStep === 2 && 'Network Configuration'}
                            {currentStep === 3 && 'Maintenance Settings'}
                            {currentStep === 4 && 'Review & Test'}
                        </DialogDescription>
                    </DialogHeader>

                    {/* Progress Indicator */}
                    <div className="space-y-4 py-4">
                        <div className="flex items-center justify-between">
                            {[1, 2, 3, 4].map((step) => (
                                <div key={step} className="flex items-center flex-1">
                                    <div
                                        className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium ${
                                            currentStep >= step
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {step}
                                    </div>
                                    {step < 4 && (
                                        <div
                                            className={`flex-1 h-1 mx-2 ${
                                                currentStep > step ? 'bg-blue-600' : 'bg-muted'
                                            }`}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Step Labels */}
                        <div className="grid grid-cols-4 gap-2 text-xs">
                            <div className="text-center">Basic Info</div>
                            <div className="text-center">Network</div>
                            <div className="text-center">Maintenance</div>
                            <div className="text-center">Review</div>
                        </div>
                    </div>

                    {/* ========== STEP 1: BASIC INFORMATION ========== */}
                    {currentStep === 1 && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                {/* Device ID */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Device ID <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.deviceId}
                                        onChange={(e) => handleFormChange('deviceId', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.deviceId ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                        placeholder="Auto-generated"
                                    />
                                    {registrationFormErrors.deviceId && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.deviceId}</p>
                                    )}
                                </div>

                                {/* Device Name */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Device Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.deviceName}
                                        onChange={(e) => handleFormChange('deviceName', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.deviceName ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                        placeholder="e.g., Main Gate Reader"
                                    />
                                    {registrationFormErrors.deviceName && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.deviceName}</p>
                                    )}
                                </div>

                                {/* Location */}
                                <div className="space-y-2 col-span-2">
                                    <label className="text-sm font-medium">
                                        Location <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.location}
                                        onChange={(e) => handleFormChange('location', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.location ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                        placeholder="e.g., Building A - Main Entrance"
                                    />
                                    {registrationFormErrors.location && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.location}</p>
                                    )}
                                </div>

                                {/* Device Type */}
                                <div className="space-y-2 col-span-2">
                                    <label className="text-sm font-medium">
                                        Device Type <span className="text-red-500">*</span>
                                    </label>
                                    <div className="flex gap-4">
                                        {[
                                            { value: 'reader', label: 'Reader' },
                                            { value: 'controller', label: 'Controller' },
                                            { value: 'hybrid', label: 'Hybrid' },
                                        ].map((type) => (
                                            <label key={type.value} className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="deviceType"
                                                    value={type.value}
                                                    checked={formData.deviceType === type.value}
                                                    onChange={(e) => handleFormChange('deviceType', e.target.value)}
                                                    className="w-4 h-4"
                                                />
                                                <span className="text-sm">{type.label}</span>
                                            </label>
                                        ))}
                                    </div>
                                    {registrationFormErrors.deviceType && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.deviceType}</p>
                                    )}
                                </div>

                                {/* Serial Number */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Serial Number (Optional)</label>
                                    <input
                                        type="text"
                                        value={formData.serialNumber}
                                        onChange={(e) => handleFormChange('serialNumber', e.target.value)}
                                        className="w-full px-3 py-2 border rounded-md text-sm"
                                        placeholder="e.g., SN-2024-001"
                                    />
                                </div>

                                {/* Installation Date */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Installation Date</label>
                                    <input
                                        type="date"
                                        value={formData.installationDate}
                                        onChange={(e) => handleFormChange('installationDate', e.target.value)}
                                        className="w-full px-3 py-2 border rounded-md text-sm"
                                    />
                                </div>
                            </div>

                            {/* Notes */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Notes (Optional)</label>
                                <textarea
                                    value={formData.notes}
                                    onChange={(e) => handleFormChange('notes', e.target.value)}
                                    className="w-full px-3 py-2 border rounded-md text-sm"
                                    placeholder="Additional information about this device"
                                    rows={3}
                                />
                            </div>
                        </div>
                    )}

                    {/* ========== STEP 2: NETWORK CONFIGURATION ========== */}
                    {currentStep === 2 && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                {/* Protocol */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Protocol <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={formData.protocol}
                                        onChange={(e) => handleFormChange('protocol', e.target.value)}
                                        className="w-full px-3 py-2 border rounded-md text-sm"
                                    >
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="http">HTTP</option>
                                        <option value="mqtt">MQTT</option>
                                    </select>
                                </div>

                                {/* IP Address */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        IP Address <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.ipAddress}
                                        onChange={(e) => handleFormChange('ipAddress', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.ipAddress ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                        placeholder="e.g., 192.168.1.101"
                                    />
                                    {registrationFormErrors.ipAddress && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.ipAddress}</p>
                                    )}
                                </div>

                                {/* Port */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Port <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        value={formData.port}
                                        onChange={(e) => handleFormChange('port', parseInt(e.target.value))}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.port ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                        placeholder="8000"
                                        min="1"
                                        max="65535"
                                    />
                                    {registrationFormErrors.port && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.port}</p>
                                    )}
                                </div>

                                {/* MAC Address */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">MAC Address (Optional)</label>
                                    <input
                                        type="text"
                                        value={formData.macAddress}
                                        onChange={(e) => handleFormChange('macAddress', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.macAddress ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                        placeholder="e.g., 00:1B:44:11:3A:B7"
                                    />
                                    {registrationFormErrors.macAddress && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.macAddress}</p>
                                    )}
                                </div>

                                {/* Firmware Version */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Firmware Version (Optional)</label>
                                    <input
                                        type="text"
                                        value={formData.firmwareVersion}
                                        onChange={(e) => handleFormChange('firmwareVersion', e.target.value)}
                                        className="w-full px-3 py-2 border rounded-md text-sm"
                                        placeholder="e.g., v2.3.1"
                                    />
                                </div>

                                {/* Connection Timeout */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Connection Timeout (seconds)</label>
                                    <input
                                        type="number"
                                        value={formData.connectionTimeout}
                                        onChange={(e) => handleFormChange('connectionTimeout', parseInt(e.target.value))}
                                        className="w-full px-3 py-2 border rounded-md text-sm"
                                        placeholder="30"
                                        min="5"
                                        max="120"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ========== STEP 3: MAINTENANCE SETTINGS ========== */}
                    {currentStep === 3 && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                {/* Maintenance Schedule */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Maintenance Schedule <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={formData.maintenanceSchedule}
                                        onChange={(e) => handleFormChange('maintenanceSchedule', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.maintenanceSchedule ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                    >
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="annually">Annually</option>
                                    </select>
                                    {registrationFormErrors.maintenanceSchedule && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.maintenanceSchedule}</p>
                                    )}
                                </div>

                                {/* Next Maintenance Date */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Next Maintenance Date</label>
                                    <input
                                        type="date"
                                        value={formData.nextMaintenanceDate}
                                        onChange={(e) => handleFormChange('nextMaintenanceDate', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md text-sm ${registrationFormErrors.nextMaintenanceDate ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                    />
                                    {registrationFormErrors.nextMaintenanceDate && (
                                        <p className="text-xs text-red-500">{registrationFormErrors.nextMaintenanceDate}</p>
                                    )}
                                </div>
                            </div>

                            {/* Maintenance Reminder */}
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="maintenanceReminder"
                                    checked={formData.maintenanceReminder}
                                    onChange={(e) => handleFormChange('maintenanceReminder', e.target.checked)}
                                    className="w-4 h-4"
                                />
                                <label htmlFor="maintenanceReminder" className="text-sm cursor-pointer">
                                    Email HR Manager 1 week before scheduled maintenance
                                </label>
                            </div>

                            {/* Maintenance Notes */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Maintenance Notes (Optional)</label>
                                <textarea
                                    value={formData.maintenanceNotes}
                                    onChange={(e) => handleFormChange('maintenanceNotes', e.target.value)}
                                    className="w-full px-3 py-2 border rounded-md text-sm"
                                    placeholder="Special considerations or instructions for maintenance"
                                    rows={3}
                                />
                            </div>
                        </div>
                    )}

                    {/* ========== STEP 4: REVIEW & TEST ========== */}
                    {currentStep === 4 && (
                        <div className="space-y-6">
                            {/* Information Banner */}
                            <div className="bg-blue-50 dark:bg-blue-950/20 p-4 rounded-lg border border-blue-200 dark:border-blue-900">
                                <p className="text-sm font-medium mb-2">Review Your Configuration</p>
                                <p className="text-xs text-muted-foreground">
                                    Please review all information below. Click "Test Connection" to verify device connectivity before registration.
                                </p>
                            </div>

                            {/* Step 1: Basic Information Review */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">Basic Information</CardTitle>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setCurrentStep(1)}
                                            className="h-8 text-xs"
                                        >
                                            <Edit2 className="h-3 w-3 mr-1" />
                                            Edit
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Device ID</p>
                                        <p className="font-medium">{formData.deviceId}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Device Name</p>
                                        <p className="font-medium">{formData.deviceName}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Location</p>
                                        <p className="font-medium">{formData.location}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Device Type</p>
                                        <p className="font-medium capitalize">{formData.deviceType}</p>
                                    </div>
                                    {formData.serialNumber && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">Serial Number</p>
                                            <p className="font-medium">{formData.serialNumber}</p>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Installation Date</p>
                                        <p className="font-medium">{formData.installationDate}</p>
                                    </div>
                                    {formData.notes && (
                                        <div className="col-span-2">
                                            <p className="text-xs font-medium text-muted-foreground">Notes</p>
                                            <p className="font-medium text-xs">{formData.notes}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Step 2: Network Configuration Review */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">Network Configuration</CardTitle>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setCurrentStep(2)}
                                            className="h-8 text-xs"
                                        >
                                            <Edit2 className="h-3 w-3 mr-1" />
                                            Edit
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Protocol</p>
                                        <p className="font-medium uppercase">{formData.protocol}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">IP Address</p>
                                        <p className="font-medium font-mono">{formData.ipAddress}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Port</p>
                                        <p className="font-medium">{formData.port}</p>
                                    </div>
                                    {formData.macAddress && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">MAC Address</p>
                                            <p className="font-medium font-mono">{formData.macAddress}</p>
                                        </div>
                                    )}
                                    {formData.firmwareVersion && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">Firmware Version</p>
                                            <p className="font-medium">{formData.firmwareVersion}</p>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Connection Timeout</p>
                                        <p className="font-medium">{formData.connectionTimeout} seconds</p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Step 3: Maintenance Settings Review */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">Maintenance Settings</CardTitle>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setCurrentStep(3)}
                                            className="h-8 text-xs"
                                        >
                                            <Edit2 className="h-3 w-3 mr-1" />
                                            Edit
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Maintenance Schedule</p>
                                        <p className="font-medium capitalize">{formData.maintenanceSchedule}</p>
                                    </div>
                                    {formData.nextMaintenanceDate && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">Next Maintenance Date</p>
                                            <p className="font-medium">{formData.nextMaintenanceDate}</p>
                                        </div>
                                    )}
                                    <div className="col-span-2">
                                        <p className="text-xs font-medium text-muted-foreground">Maintenance Reminder</p>
                                        <p className="font-medium">
                                            {formData.maintenanceReminder ? '✓ Email HR Manager 1 week before' : '✗ No reminders'}
                                        </p>
                                    </div>
                                    {formData.maintenanceNotes && (
                                        <div className="col-span-2">
                                            <p className="text-xs font-medium text-muted-foreground">Maintenance Notes</p>
                                            <p className="font-medium text-xs">{formData.maintenanceNotes}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Connection Test Section */}
                            <Card className="border-amber-200 dark:border-amber-900">
                                <CardHeader className="pb-3 bg-amber-50 dark:bg-amber-950/20 rounded-t-lg">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Activity className="h-4 w-4 text-amber-600" />
                                            <CardTitle className="text-base">Connection Test</CardTitle>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleTestConnection}
                                            disabled={testStatus === 'testing'}
                                            className="h-8"
                                        >
                                            {testStatus === 'testing' ? (
                                                <>
                                                    <RefreshCw className="h-3 w-3 mr-1 animate-spin" />
                                                    Testing...
                                                </>
                                            ) : (
                                                <>
                                                    <Activity className="h-3 w-3 mr-1" />
                                                    {testStatus === 'success' ? 'Test Again' : 'Test Connection'}
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {testStatus === 'untested' && (
                                        <p className="text-sm text-muted-foreground">
                                            Click "Test Connection" to verify device connectivity before registration.
                                        </p>
                                    )}
                                    
                                    {testStatus === 'testing' && (
                                        <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                            <RefreshCw className="h-4 w-4 animate-spin" />
                                            <span>Testing connection to {formData.ipAddress}:{formData.port}...</span>
                                        </div>
                                    )}

                                    {testStatus === 'success' && testResults && (
                                        <div className="space-y-3">
                                            <div className="flex items-center gap-2 text-sm">
                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                <span className="font-medium">Device reachable at {formData.ipAddress}:{formData.port}</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                <span className="font-medium">Handshake successful</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                <span className="font-medium">Firmware version confirmed</span>
                                            </div>
                                            {testResults.certificateWarning && (
                                                <div className="flex items-start gap-2 text-sm mt-3 p-3 bg-amber-50 dark:bg-amber-950/20 rounded-md border border-amber-200 dark:border-amber-900">
                                                    <AlertTriangle className="h-4 w-4 text-amber-600 mt-0.5 flex-shrink-0" />
                                                    <div>
                                                        <p className="font-medium text-amber-900 dark:text-amber-400">
                                                            Warning: Device certificate expires in 30 days
                                                        </p>
                                                        <p className="text-xs text-amber-700 dark:text-amber-500 mt-1">
                                                            Consider updating the device certificate during the next maintenance window.
                                                        </p>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Dialog Footer with Navigation */}
                    <DialogFooter className="mt-6">
                        <div className="flex items-center justify-between w-full">
                            <Button
                                variant="outline"
                                onClick={handlePreviousStep}
                                disabled={currentStep === 1}
                            >
                                Back
                            </Button>

                            {currentStep < 4 ? (
                                <Button onClick={handleNextStep} disabled={!isCurrentStepValid}>
                                    Next
                                </Button>
                            ) : (
                                <div className="flex flex-col items-end gap-2 w-full">
                                    {testStatus !== 'success' && (
                                        <p className="text-xs text-amber-600 dark:text-amber-500 flex items-center gap-1">
                                            <AlertCircle className="h-3 w-3" />
                                            Connection test required before registration
                                        </p>
                                    )}
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setShowRegistrationModal(false);
                                                setTestStatus('untested');
                                                setTestResults(null);
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            onClick={handleRegisterDeviceSubmit}
                                            disabled={testStatus !== 'success'}
                                            className={testStatus === 'success' ? 'bg-green-600 hover:bg-green-700' : ''}
                                        >
                                            <CheckCircle2 className={`h-4 w-4 mr-1 ${testStatus === 'success' ? '' : 'hidden'}`} />
                                            Register Device
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
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
    const [isTestingConnection, setIsTestingConnection] = useState(false);
    const [testResult, setTestResult] = useState<{
        success: boolean;
        message: string;
        details?: string;
    } | null>(null);
    const [timelineFilter, setTimelineFilter] = useState<'all' | 'heartbeat' | 'scan' | 'config' | 'maintenance' | 'status'>('all');
    const [timelinePage, setTimelinePage] = useState(1);

    const rowClass =
        device.status === 'offline' || device.status === 'error'
            ? 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900'
            : device.status === 'maintenance' || device.maintenance_due
                ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900'
                : 'bg-white dark:bg-background border-border';

    const handleTestConnection = () => {
        setIsTestingConnection(true);
        setTestResult(null);

        setTimeout(() => {
            const success = device.status === 'online' || device.status === 'maintenance';
            setTestResult(
                success
                    ? {
                        success: true,
                        message: 'Connection successful',
                        details: `Device reachable at ${device.ip_address}:${device.port}`,
                    }
                    : {
                        success: false,
                        message: 'Connection failed',
                        details: 'Device unreachable. Check network and power status.',
                    },
            );
            setIsTestingConnection(false);
        }, 1800);
    };

    const handleDeactivate = () => {
        setShowDeactivateConfirm(false);
    };

    const numericDeviceId = Number.parseInt(device.id, 10) || 0;
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

    const lastMaintenance = device.maintenance_due ? '2025-12-20' : '2026-02-15';
    const nextScheduledMaintenance = device.maintenance_due ? 'Due now' : '2026-05-15';
    const maintenanceHistory = device.maintenance_due
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
                            <p className="font-medium truncate">{device.name}</p>
                            <p className="text-sm text-muted-foreground truncate">
                                {device.location} • {device.ip_address}:{device.port}
                            </p>
                        </div>
                    </div>

                    <div className="hidden sm:block text-right">
                        <p className="text-sm capitalize">{device.device_type}</p>
                        <p className="text-xs text-muted-foreground hidden md:block">v{device.firmware_version}</p>
                    </div>

                    <div className="flex items-center gap-2">
                        {getStatusBadge(device.status)}
                        <DropdownMenu>
                            <Button variant="ghost" size="sm" asChild>
                                <div>
                                    <MoreHorizontal className="h-4 w-4" />
                                </div>
                            </Button>
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
                                <DropdownMenuItem onClick={() => setShowTestModal(true)}>
                                    <Activity className="h-4 w-4 mr-2" />
                                    Test Connection
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => setShowDeactivateConfirm(true)} className="text-red-600">
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Deactivate
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
                        <DialogDescription>{device.name}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Overview</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Device ID</p>
                                    <p className="font-medium">{device.id}</p>
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
                                    <p className="font-medium">{device.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Type</p>
                                    <p className="font-medium capitalize">{device.device_type}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">IP Address</p>
                                    <p className="font-medium font-mono">{device.ip_address}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Port</p>
                                    <p className="font-medium">{device.port}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Protocol</p>
                                    <p className="font-medium">TCP</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Firmware</p>
                                    <p className="font-medium">v{device.firmware_version}</p>
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
                        <DialogDescription>{device.name}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2">
                        <input type="text" value={device.name} readOnly className="w-full px-3 py-2 border rounded-md text-sm bg-muted" />
                        <input type="text" value={device.location} readOnly className="w-full px-3 py-2 border rounded-md text-sm bg-muted" />
                        <div className="grid grid-cols-2 gap-3">
                            <input type="text" value={device.ip_address} readOnly className="w-full px-3 py-2 border rounded-md text-sm bg-muted" />
                            <input type="number" value={device.port} readOnly className="w-full px-3 py-2 border rounded-md text-sm bg-muted" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowEditModal(false)}>
                            Cancel
                        </Button>
                        <Button disabled>Save Changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={showTestModal} onOpenChange={setShowTestModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Test Connection: {device.name}</DialogTitle>
                        <DialogDescription>
                            Testing connection to {device.ip_address}:{device.port}
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
                        <DialogTitle>Deactivate Device</DialogTitle>
                        <DialogDescription>This action cannot be undone.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="p-4 bg-destructive/10 rounded-lg border border-destructive/20">
                            <p className="text-sm font-medium mb-1">Warning</p>
                            <p className="text-sm">
                                Are you sure you want to deactivate <strong>{device.name}</strong>?
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                The device will no longer accept badge scans and will be marked as inactive in the system.
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeactivateConfirm(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeactivate}>
                            Deactivate Device
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
