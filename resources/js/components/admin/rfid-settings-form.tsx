import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Save, Info, Radio, Wifi, Activity, AlertCircle, CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

export interface RFIDSettingsData {
    rfid_enabled: boolean;
    rfid_device_ip: string;
    rfid_device_port: number;
    rfid_protocol: 'http' | 'https' | 'tcp';
    rfid_event_bus_enabled: boolean;
}

interface RFIDSettingsFormProps {
    initialData: RFIDSettingsData;
    onSubmit: (data: RFIDSettingsData) => void;
}

export function RFIDSettingsForm({ initialData, onSubmit }: RFIDSettingsFormProps) {
    const [formData, setFormData] = useState<RFIDSettingsData>(initialData);
    const [errors, setErrors] = useState<Partial<Record<keyof RFIDSettingsData, string>>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isTestingConnection, setIsTestingConnection] = useState(false);
    const [connectionTestResult, setConnectionTestResult] = useState<'success' | 'error' | null>(null);

    const handleInputChange = (field: keyof RFIDSettingsData, value: string | number | boolean) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        // Clear error for this field
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field];
            return newErrors;
        });
        // Clear connection test result when device settings change
        if (['rfid_device_ip', 'rfid_device_port', 'rfid_protocol'].includes(field)) {
            setConnectionTestResult(null);
        }
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<Record<keyof RFIDSettingsData, string>> = {};

        if (formData.rfid_enabled) {
            if (!formData.rfid_device_ip.trim()) {
                newErrors.rfid_device_ip = 'Device IP address is required when RFID is enabled';
            } else {
                // Validate IP format
                const ipPattern = /^(\d{1,3}\.){3}\d{1,3}$/;
                if (!ipPattern.test(formData.rfid_device_ip)) {
                    newErrors.rfid_device_ip = 'Invalid IP address format (e.g., 192.168.1.100)';
                } else {
                    // Validate each octet
                    const octets = formData.rfid_device_ip.split('.');
                    if (octets.some((octet) => parseInt(octet) > 255)) {
                        newErrors.rfid_device_ip = 'IP address octets must be between 0-255';
                    }
                }
            }

            if (formData.rfid_device_port < 1 || formData.rfid_device_port > 65535) {
                newErrors.rfid_device_port = 'Port must be between 1 and 65535';
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleTestConnection = async () => {
        if (!validateForm()) {
            return;
        }

        setIsTestingConnection(true);
        setConnectionTestResult(null);

        // Simulate connection test (replace with actual API call in production)
        setTimeout(() => {
            // In production: call backend API to test RFID device connection
            // For now, simulate based on device IP
            const success = formData.rfid_device_ip.startsWith('192.168.');
            setConnectionTestResult(success ? 'success' : 'error');
            setIsTestingConnection(false);
        }, 2000);
    };

    const handleSubmit = () => {
        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);
        onSubmit(formData);

        setTimeout(() => {
            setIsSubmitting(false);
        }, 2000);
    };

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Important:</strong> RFID integration connects the system to physical timekeeping devices. Enable event bus routing to automatically process attendance records, update payroll work hours, and send employee notifications. Device specifications are awaiting hardware vendor confirmation.
                </AlertDescription>
            </Alert>

            {/* RFID Enable Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Radio className="h-5 w-5" />
                        RFID Timekeeping Integration
                    </CardTitle>
                    <CardDescription>Configure connection to RFID timekeeping devices</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="rfid_enabled" className="text-base font-semibold">Enable RFID Integration</Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Connect to RFID devices for automated timekeeping
                            </p>
                        </div>
                        <Switch
                            id="rfid_enabled"
                            checked={formData.rfid_enabled}
                            onCheckedChange={(checked) => handleInputChange('rfid_enabled', checked)}
                        />
                    </div>

                    {formData.rfid_enabled && (
                        <Alert className="bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <AlertDescription>
                                <strong>Hardware Requirements:</strong> RFID device specifications are currently being finalized with the hardware vendor. Supported protocols include HTTP, HTTPS, and TCP. Device must be accessible on the local network.
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            {/* Device Configuration Card */}
            {formData.rfid_enabled && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Wifi className="h-5 w-5" />
                            Device Connection Settings
                        </CardTitle>
                        <CardDescription>Configure RFID device network connection parameters</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="rfid_device_ip">
                                    Device IP Address <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="rfid_device_ip"
                                    type="text"
                                    placeholder="192.168.1.100"
                                    value={formData.rfid_device_ip}
                                    onChange={(e) => handleInputChange('rfid_device_ip', e.target.value)}
                                    className={errors.rfid_device_ip ? 'border-red-500' : ''}
                                />
                                {errors.rfid_device_ip && <p className="text-sm text-red-500">{errors.rfid_device_ip}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Local network IP address of the RFID device (e.g., 192.168.1.100)
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="rfid_device_port">
                                        Port <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="rfid_device_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        value={formData.rfid_device_port}
                                        onChange={(e) => handleInputChange('rfid_device_port', parseInt(e.target.value) || 8080)}
                                        className={errors.rfid_device_port ? 'border-red-500' : ''}
                                    />
                                    {errors.rfid_device_port && <p className="text-sm text-red-500">{errors.rfid_device_port}</p>}
                                    <p className="text-xs text-muted-foreground">Port number (typically 8080 or 80)</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="rfid_protocol">
                                        Protocol <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={formData.rfid_protocol}
                                        onValueChange={(value) => handleInputChange('rfid_protocol', value as 'http' | 'https' | 'tcp')}
                                    >
                                        <SelectTrigger id="rfid_protocol">
                                            <SelectValue placeholder="Select protocol" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="http">HTTP (Standard)</SelectItem>
                                            <SelectItem value="https">HTTPS (Secure)</SelectItem>
                                            <SelectItem value="tcp">TCP (Direct Connection)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">Communication protocol</p>
                                </div>
                            </div>
                        </div>

                        {/* Test Connection Button */}
                        <div className="border-t pt-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">Test Device Connection</p>
                                    <p className="text-sm text-muted-foreground">Verify that the device is reachable</p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleTestConnection}
                                    disabled={isTestingConnection}
                                >
                                    {isTestingConnection ? 'Testing...' : 'Test Connection'}
                                </Button>
                            </div>

                            {/* Connection Test Result */}
                            {connectionTestResult && (
                                <Alert
                                    className={`mt-4 ${
                                        connectionTestResult === 'success'
                                            ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800'
                                            : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800'
                                    }`}
                                >
                                    {connectionTestResult === 'success' ? (
                                        <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                                    ) : (
                                        <XCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                    )}
                                    <AlertDescription>
                                        {connectionTestResult === 'success' ? (
                                            <span>
                                                <strong>Connection Successful!</strong> RFID device is reachable and responding at{' '}
                                                {formData.rfid_protocol}://{formData.rfid_device_ip}:{formData.rfid_device_port}
                                            </span>
                                        ) : (
                                            <span>
                                                <strong>Connection Failed!</strong> Unable to reach RFID device. Please verify IP
                                                address, port, protocol, and ensure the device is powered on and connected to the
                                                network.
                                            </span>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>

                        <Alert className="bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                            <AlertCircle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                            <AlertDescription>
                                <strong>Note:</strong> Connection test is currently simulated. Actual device integration will be
                                enabled once hardware vendor confirms device specifications and provides API documentation.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>
            )}

            {/* Event Bus Configuration Card */}
            {formData.rfid_enabled && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="h-5 w-5" />
                            Event Bus Routing
                        </CardTitle>
                        <CardDescription>Configure automated processing of RFID events</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="flex items-center justify-between p-4 border rounded-lg">
                            <div className="flex-1">
                                <Label htmlFor="rfid_event_bus_enabled" className="text-base font-semibold">
                                    Enable Event Bus Processing
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Automatically route RFID tap events to Timekeeping, Payroll, and Notification modules
                                </p>
                            </div>
                            <Switch
                                id="rfid_event_bus_enabled"
                                checked={formData.rfid_event_bus_enabled}
                                onCheckedChange={(checked) => handleInputChange('rfid_event_bus_enabled', checked)}
                            />
                        </div>

                        {formData.rfid_event_bus_enabled && (
                            <div className="space-y-4 border-t pt-4">
                                <p className="text-sm font-medium">Event Flow:</p>
                                <div className="space-y-3 pl-4">
                                    <div className="flex items-start gap-3">
                                        <div className="rounded-full bg-blue-100 dark:bg-blue-950 p-1 mt-0.5">
                                            <Radio className="h-3 w-3 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">1. RFID Card Tap Detected</p>
                                            <p className="text-xs text-muted-foreground">
                                                Device captures card tap with timestamp and employee ID
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <div className="rounded-full bg-green-100 dark:bg-green-950 p-1 mt-0.5">
                                            <Activity className="h-3 w-3 text-green-600 dark:text-green-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">2. Timekeeping Module</p>
                                            <p className="text-xs text-muted-foreground">
                                                Record attendance (Clock In/Out), calculate work hours, flag late/early entries
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <div className="rounded-full bg-purple-100 dark:bg-purple-950 p-1 mt-0.5">
                                            <Activity className="h-3 w-3 text-purple-600 dark:text-purple-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">3. Payroll Module</p>
                                            <p className="text-xs text-muted-foreground">
                                                Update employee work hours for payroll calculation, apply deductions for late/absence
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <div className="rounded-full bg-orange-100 dark:bg-orange-950 p-1 mt-0.5">
                                            <Activity className="h-3 w-3 text-orange-600 dark:text-orange-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">4. Notification Module</p>
                                            <p className="text-xs text-muted-foreground">
                                                Send confirmation to employee, alert supervisors of late arrivals
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <Alert>
                                    <Info className="h-4 w-4" />
                                    <AlertDescription>
                                        Event bus processing ensures seamless integration between RFID hardware and the HRIS
                                        system. All events are logged for audit and replay capabilities.
                                    </AlertDescription>
                                </Alert>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Summary Card */}
            <Card className="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 border-indigo-200 dark:border-indigo-800">
                <CardHeader>
                    <CardTitle>RFID Configuration Summary</CardTitle>
                    <CardDescription>Review your current RFID integration settings</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Integration Status</p>
                            <p className="text-lg font-bold">{formData.rfid_enabled ? 'Enabled' : 'Disabled'}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Device Connection</p>
                            <p className="text-lg font-bold">
                                {formData.rfid_enabled
                                    ? `${formData.rfid_protocol.toUpperCase()}://${formData.rfid_device_ip}:${formData.rfid_device_port}`
                                    : 'Not configured'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Event Bus</p>
                            <p className="text-lg font-bold">
                                {formData.rfid_enabled && formData.rfid_event_bus_enabled ? 'Active' : 'Inactive'}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
                <Button onClick={handleSubmit} disabled={isSubmitting} size="lg">
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? 'Saving...' : 'Save RFID Settings'}
                </Button>
            </div>

            {/* Validation Error Summary */}
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <strong>Please fix the following errors:</strong>
                        <ul className="list-disc list-inside mt-2">
                            {Object.values(errors).map((error, index) => (
                                <li key={index}>{error}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}
