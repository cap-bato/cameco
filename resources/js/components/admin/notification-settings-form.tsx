import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Save, Info, Mail, MessageSquare, Bell, AlertCircle, Check } from 'lucide-react';
import { useState } from 'react';

export interface NotificationSettingsData {
    // SMTP Email settings
    smtp_host: string;
    smtp_port: number;
    smtp_username: string;
    smtp_password: string;
    smtp_encryption: 'tls' | 'ssl' | 'none';
    sender_email: string;
    sender_name: string;
    // SMS settings (future)
    sms_enabled: boolean;
    sms_gateway: string;
    sms_api_key: string;
    // Notification templates
    leave_approval_enabled: boolean;
    payslip_enabled: boolean;
    interview_enabled: boolean;
    appraisal_enabled: boolean;
    system_alerts_enabled: boolean;
}

interface NotificationSettingsFormProps {
    initialData: NotificationSettingsData;
    onSubmit: (data: NotificationSettingsData) => void;
}

export function NotificationSettingsForm({ initialData, onSubmit }: NotificationSettingsFormProps) {
    const [formData, setFormData] = useState<NotificationSettingsData>(initialData);
    const [errors, setErrors] = useState<Partial<Record<keyof NotificationSettingsData, string>>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleInputChange = (field: keyof NotificationSettingsData, value: string | number | boolean) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        // Clear error for this field
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field];
            return newErrors;
        });
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<Record<keyof NotificationSettingsData, string>> = {};

        // SMTP Email validation
        if (!formData.smtp_host.trim()) {
            newErrors.smtp_host = 'SMTP host is required';
        }
        if (formData.smtp_port < 1 || formData.smtp_port > 65535) {
            newErrors.smtp_port = 'Port must be between 1 and 65535';
        }
        if (!formData.smtp_username.trim()) {
            newErrors.smtp_username = 'SMTP username is required';
        }
        if (!formData.sender_email.trim()) {
            newErrors.sender_email = 'Sender email is required';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.sender_email)) {
            newErrors.sender_email = 'Invalid email format';
        }
        if (!formData.sender_name.trim()) {
            newErrors.sender_name = 'Sender name is required';
        }

        // SMS validation (only if enabled)
        if (formData.sms_enabled) {
            if (!formData.sms_gateway.trim()) {
                newErrors.sms_gateway = 'SMS gateway is required when SMS is enabled';
            }
            if (!formData.sms_api_key.trim()) {
                newErrors.sms_api_key = 'SMS API key is required when SMS is enabled';
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = () => {
        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);
        onSubmit(formData);

        // Reset submitting state after a delay (Inertia will handle the actual redirect)
        setTimeout(() => {
            setIsSubmitting(false);
        }, 2000);
    };

    const enabledNotificationsCount = [
        formData.leave_approval_enabled,
        formData.payslip_enabled,
        formData.interview_enabled,
        formData.appraisal_enabled,
        formData.system_alerts_enabled,
    ].filter(Boolean).length;

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Important:</strong> Notification settings control system-wide email and SMS alerts. SMTP configuration is required for email notifications. Ensure SMTP credentials are correct to avoid delivery failures. Individual notification types can be enabled or disabled below.
                </AlertDescription>
            </Alert>

            {/* SMTP Email Configuration Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Mail className="h-5 w-5" />
                        SMTP Email Configuration
                    </CardTitle>
                    <CardDescription>Configure email server settings for system notifications</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* SMTP Server Settings */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Server Connection</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="smtp_host">
                                    SMTP Host <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="smtp_host"
                                    type="text"
                                    placeholder="smtp.gmail.com"
                                    value={formData.smtp_host}
                                    onChange={(e) => handleInputChange('smtp_host', e.target.value)}
                                    className={errors.smtp_host ? 'border-red-500' : ''}
                                />
                                {errors.smtp_host && <p className="text-sm text-red-500">{errors.smtp_host}</p>}
                                <p className="text-xs text-muted-foreground">SMTP server hostname (e.g., smtp.gmail.com)</p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="smtp_port">
                                    SMTP Port <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="smtp_port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    value={formData.smtp_port}
                                    onChange={(e) => handleInputChange('smtp_port', parseInt(e.target.value) || 587)}
                                    className={errors.smtp_port ? 'border-red-500' : ''}
                                />
                                {errors.smtp_port && <p className="text-sm text-red-500">{errors.smtp_port}</p>}
                                <p className="text-xs text-muted-foreground">Port 587 (TLS) or 465 (SSL) recommended</p>
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="smtp_username">
                                    SMTP Username <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="smtp_username"
                                    type="text"
                                    placeholder="your-email@example.com"
                                    value={formData.smtp_username}
                                    onChange={(e) => handleInputChange('smtp_username', e.target.value)}
                                    className={errors.smtp_username ? 'border-red-500' : ''}
                                />
                                {errors.smtp_username && <p className="text-sm text-red-500">{errors.smtp_username}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="smtp_password">SMTP Password</Label>
                                <Input
                                    id="smtp_password"
                                    type="password"
                                    placeholder="Leave blank to keep current password"
                                    value={formData.smtp_password}
                                    onChange={(e) => handleInputChange('smtp_password', e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">Leave blank to keep existing password</p>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="smtp_encryption">
                                Encryption Method <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={formData.smtp_encryption}
                                onValueChange={(value) => handleInputChange('smtp_encryption', value as 'tls' | 'ssl' | 'none')}
                            >
                                <SelectTrigger id="smtp_encryption">
                                    <SelectValue placeholder="Select encryption method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tls">TLS (Recommended for port 587)</SelectItem>
                                    <SelectItem value="ssl">SSL (Recommended for port 465)</SelectItem>
                                    <SelectItem value="none">None (Not recommended)</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">TLS is recommended for secure email transmission</p>
                        </div>
                    </div>

                    {/* Sender Information */}
                    <div className="space-y-4 border-t pt-6">
                        <h3 className="text-sm font-semibold">Sender Information</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="sender_email">
                                    Sender Email Address <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="sender_email"
                                    type="email"
                                    placeholder="noreply@company.com"
                                    value={formData.sender_email}
                                    onChange={(e) => handleInputChange('sender_email', e.target.value)}
                                    className={errors.sender_email ? 'border-red-500' : ''}
                                />
                                {errors.sender_email && <p className="text-sm text-red-500">{errors.sender_email}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Email address that appears in the "From" field
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="sender_name">
                                    Sender Name <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="sender_name"
                                    type="text"
                                    placeholder="Company HRIS System"
                                    value={formData.sender_name}
                                    onChange={(e) => handleInputChange('sender_name', e.target.value)}
                                    className={errors.sender_name ? 'border-red-500' : ''}
                                />
                                {errors.sender_name && <p className="text-sm text-red-500">{errors.sender_name}</p>}
                                <p className="text-xs text-muted-foreground">Display name for outgoing emails</p>
                            </div>
                        </div>
                    </div>

                    {/* SMTP Test Info */}
                    <Alert className="bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                        <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <AlertDescription>
                            <strong>Gmail Users:</strong> If using Gmail, you need to generate an App Password. Go to Google Account Settings → Security → 2-Step Verification → App Passwords. Use the generated 16-character password here.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* SMS Configuration Card (Future) */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MessageSquare className="h-5 w-5" />
                        SMS Notifications (Future Feature)
                    </CardTitle>
                    <CardDescription>Configure SMS gateway for urgent notifications and reminders</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div className="space-y-0.5">
                            <Label htmlFor="sms_enabled" className="text-base">Enable SMS Notifications</Label>
                            <p className="text-sm text-muted-foreground">
                                Enable SMS alerts for time-sensitive notifications
                            </p>
                        </div>
                        <Switch
                            id="sms_enabled"
                            checked={formData.sms_enabled}
                            onCheckedChange={(checked) => handleInputChange('sms_enabled', checked)}
                        />
                    </div>

                    {formData.sms_enabled && (
                        <div className="space-y-4 border-t pt-4">
                            <div className="space-y-2">
                                <Label htmlFor="sms_gateway">
                                    SMS Gateway <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={formData.sms_gateway}
                                    onValueChange={(value) => handleInputChange('sms_gateway', value)}
                                >
                                    <SelectTrigger id="sms_gateway" className={errors.sms_gateway ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="Select SMS gateway provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semaphore">Semaphore (Philippines)</SelectItem>
                                        <SelectItem value="globe">Globe Labs (Philippines)</SelectItem>
                                        <SelectItem value="smart">Smart DevNet (Philippines)</SelectItem>
                                        <SelectItem value="twilio">Twilio (International)</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.sms_gateway && <p className="text-sm text-red-500">{errors.sms_gateway}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="sms_api_key">
                                    API Key <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="sms_api_key"
                                    type="password"
                                    placeholder="Enter SMS gateway API key"
                                    value={formData.sms_api_key}
                                    onChange={(e) => handleInputChange('sms_api_key', e.target.value)}
                                    className={errors.sms_api_key ? 'border-red-500' : ''}
                                />
                                {errors.sms_api_key && <p className="text-sm text-red-500">{errors.sms_api_key}</p>}
                                <p className="text-xs text-muted-foreground">API key from your SMS gateway provider</p>
                            </div>

                            <Alert>
                                <Info className="h-4 w-4" />
                                <AlertDescription>
                                    SMS notifications are currently in development. This feature will be available in a future release for emergency alerts, attendance reminders, and critical system notifications.
                                </AlertDescription>
                            </Alert>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Notification Types Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Bell className="h-5 w-5" />
                        Email Notification Types
                    </CardTitle>
                    <CardDescription>Enable or disable specific notification types system-wide</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* Leave Approval Notifications */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="leave_approval_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.leave_approval_enabled && <Check className="h-4 w-4 text-green-600" />}
                                Leave Request Notifications
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Email alerts for leave request submissions, approvals, and rejections
                            </p>
                        </div>
                        <Switch
                            id="leave_approval_enabled"
                            checked={formData.leave_approval_enabled}
                            onCheckedChange={(checked) => handleInputChange('leave_approval_enabled', checked)}
                        />
                    </div>

                    {/* Payslip Notifications */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="payslip_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.payslip_enabled && <Check className="h-4 w-4 text-green-600" />}
                                Payslip Distribution Notifications
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Email payslips to employees when payroll is processed
                            </p>
                        </div>
                        <Switch
                            id="payslip_enabled"
                            checked={formData.payslip_enabled}
                            onCheckedChange={(checked) => handleInputChange('payslip_enabled', checked)}
                        />
                    </div>

                    {/* Interview Notifications */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="interview_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.interview_enabled && <Check className="h-4 w-4 text-green-600" />}
                                Interview Scheduling Notifications
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Email invitations and reminders for scheduled interviews
                            </p>
                        </div>
                        <Switch
                            id="interview_enabled"
                            checked={formData.interview_enabled}
                            onCheckedChange={(checked) => handleInputChange('interview_enabled', checked)}
                        />
                    </div>

                    {/* Appraisal Notifications */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="appraisal_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.appraisal_enabled && <Check className="h-4 w-4 text-green-600" />}
                                Performance Review Notifications
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Email reminders for performance review cycles and deadlines
                            </p>
                        </div>
                        <Switch
                            id="appraisal_enabled"
                            checked={formData.appraisal_enabled}
                            onCheckedChange={(checked) => handleInputChange('appraisal_enabled', checked)}
                        />
                    </div>

                    {/* System Alerts */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="system_alerts_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.system_alerts_enabled && <Check className="h-4 w-4 text-green-600" />}
                                System Alert Notifications
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Critical system alerts, errors, and maintenance notifications
                            </p>
                        </div>
                        <Switch
                            id="system_alerts_enabled"
                            checked={formData.system_alerts_enabled}
                            onCheckedChange={(checked) => handleInputChange('system_alerts_enabled', checked)}
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Summary Card */}
            <Card className="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 border-indigo-200 dark:border-indigo-800">
                <CardHeader>
                    <CardTitle>Notification Configuration Summary</CardTitle>
                    <CardDescription>Review your current notification settings</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">SMTP Server</p>
                            <p className="text-lg font-bold">{formData.smtp_host || 'Not configured'}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Sender Email</p>
                            <p className="text-lg font-bold">{formData.sender_email || 'Not configured'}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Enabled Notifications</p>
                            <p className="text-lg font-bold">{enabledNotificationsCount} of 5</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
                <Button onClick={handleSubmit} disabled={isSubmitting} size="lg">
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? 'Saving...' : 'Save Notification Settings'}
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
