import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Save, Info, FileText, File, Table, Mail, Plus, X, AlertCircle, Check } from 'lucide-react';
import { useState } from 'react';

export interface ReportSettingsData {
    // PDF settings
    pdf_company_logo: boolean;
    pdf_page_size: 'A4' | 'Letter' | 'Legal';
    pdf_orientation: 'portrait' | 'landscape';
    pdf_font_family: 'Arial' | 'Times New Roman' | 'Courier' | 'Helvetica';
    // Excel settings
    excel_auto_width: boolean;
    excel_freeze_panes: boolean;
    excel_summary_sheet: boolean;
    // Scheduled reports
    monthly_payroll_enabled: boolean;
    monthly_payroll_recipients: string[];
    attendance_summary_enabled: boolean;
    attendance_summary_recipients: string[];
    leave_utilization_enabled: boolean;
    leave_utilization_recipients: string[];
    government_remittance_enabled: boolean;
    government_remittance_recipients: string[];
}

interface ReportSettingsFormProps {
    initialData: ReportSettingsData;
    onSubmit: (data: ReportSettingsData) => void;
}

export function ReportSettingsForm({ initialData, onSubmit }: ReportSettingsFormProps) {
    const [formData, setFormData] = useState<ReportSettingsData>(initialData);
    const [errors, setErrors] = useState<Partial<Record<keyof ReportSettingsData, string>>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [newRecipients, setNewRecipients] = useState({
        monthly_payroll: '',
        attendance_summary: '',
        leave_utilization: '',
        government_remittance: '',
    });

    const handleInputChange = (field: keyof ReportSettingsData, value: string | boolean | string[]) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        // Clear error for this field
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field];
            return newErrors;
        });
    };

    const addRecipient = (reportType: 'monthly_payroll' | 'attendance_summary' | 'leave_utilization' | 'government_remittance') => {
        const email = newRecipients[reportType].trim();
        if (!email) return;

        // Validate email format
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setErrors((prev) => ({
                ...prev,
                [`${reportType}_recipients` as keyof ReportSettingsData]: 'Invalid email format',
            }));
            return;
        }

        const recipientsKey = `${reportType}_recipients` as keyof ReportSettingsData;
        const currentRecipients = formData[recipientsKey] as string[];
        
        if (currentRecipients.includes(email)) {
            setErrors((prev) => ({
                ...prev,
                [recipientsKey]: 'Email already added',
            }));
            return;
        }

        handleInputChange(recipientsKey, [...currentRecipients, email]);
        setNewRecipients((prev) => ({ ...prev, [reportType]: '' }));
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[recipientsKey];
            return newErrors;
        });
    };

    const removeRecipient = (reportType: 'monthly_payroll' | 'attendance_summary' | 'leave_utilization' | 'government_remittance', email: string) => {
        const recipientsKey = `${reportType}_recipients` as keyof ReportSettingsData;
        const currentRecipients = formData[recipientsKey] as string[];
        handleInputChange(recipientsKey, currentRecipients.filter((r) => r !== email));
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<Record<keyof ReportSettingsData, string>> = {};

        // Check if at least one recipient is added for enabled reports
        if (formData.monthly_payroll_enabled && formData.monthly_payroll_recipients.length === 0) {
            newErrors.monthly_payroll_recipients = 'At least one recipient required when report is enabled';
        }
        if (formData.attendance_summary_enabled && formData.attendance_summary_recipients.length === 0) {
            newErrors.attendance_summary_recipients = 'At least one recipient required when report is enabled';
        }
        if (formData.leave_utilization_enabled && formData.leave_utilization_recipients.length === 0) {
            newErrors.leave_utilization_recipients = 'At least one recipient required when report is enabled';
        }
        if (formData.government_remittance_enabled && formData.government_remittance_recipients.length === 0) {
            newErrors.government_remittance_recipients = 'At least one recipient required when report is enabled';
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

        setTimeout(() => {
            setIsSubmitting(false);
        }, 2000);
    };

    const enabledReportsCount = [
        formData.monthly_payroll_enabled,
        formData.attendance_summary_enabled,
        formData.leave_utilization_enabled,
        formData.government_remittance_enabled,
    ].filter(Boolean).length;

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Important:</strong> Report settings control PDF and Excel export formats system-wide. Scheduled reports are automatically generated and emailed to specified recipients. Configure report formats before enabling scheduled reports.
                </AlertDescription>
            </Alert>

            {/* PDF Configuration Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        PDF Report Settings
                    </CardTitle>
                    <CardDescription>Configure PDF export format and styling</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="space-y-0.5">
                                <Label htmlFor="pdf_company_logo" className="text-base">Include Company Logo</Label>
                                <p className="text-sm text-muted-foreground">
                                    Display company logo in PDF headers
                                </p>
                            </div>
                            <Switch
                                id="pdf_company_logo"
                                checked={formData.pdf_company_logo}
                                onCheckedChange={(checked) => handleInputChange('pdf_company_logo', checked)}
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="pdf_page_size">
                                    Page Size <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={formData.pdf_page_size}
                                    onValueChange={(value) => handleInputChange('pdf_page_size', value as 'A4' | 'Letter' | 'Legal')}
                                >
                                    <SelectTrigger id="pdf_page_size">
                                        <SelectValue placeholder="Select page size" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="A4">A4 (210 × 297 mm) - Standard</SelectItem>
                                        <SelectItem value="Letter">Letter (8.5 × 11 in)</SelectItem>
                                        <SelectItem value="Legal">Legal (8.5 × 14 in)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="pdf_orientation">
                                    Page Orientation <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={formData.pdf_orientation}
                                    onValueChange={(value) => handleInputChange('pdf_orientation', value as 'portrait' | 'landscape')}
                                >
                                    <SelectTrigger id="pdf_orientation">
                                        <SelectValue placeholder="Select orientation" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="portrait">Portrait (Vertical)</SelectItem>
                                        <SelectItem value="landscape">Landscape (Horizontal)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="pdf_font_family">
                                Font Family <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={formData.pdf_font_family}
                                onValueChange={(value) => handleInputChange('pdf_font_family', value as 'Arial' | 'Times New Roman' | 'Courier' | 'Helvetica')}
                            >
                                <SelectTrigger id="pdf_font_family">
                                    <SelectValue placeholder="Select font family" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Arial">Arial (Modern, Clean)</SelectItem>
                                    <SelectItem value="Times New Roman">Times New Roman (Traditional)</SelectItem>
                                    <SelectItem value="Courier">Courier (Monospace)</SelectItem>
                                    <SelectItem value="Helvetica">Helvetica (Professional)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Excel Configuration Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Table className="h-5 w-5" />
                        Excel Report Settings
                    </CardTitle>
                    <CardDescription>Configure Excel export format and features</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="excel_auto_width" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.excel_auto_width && <Check className="h-4 w-4 text-green-600" />}
                                Auto-Adjust Column Width
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Automatically adjust column widths to fit content
                            </p>
                        </div>
                        <Switch
                            id="excel_auto_width"
                            checked={formData.excel_auto_width}
                            onCheckedChange={(checked) => handleInputChange('excel_auto_width', checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="excel_freeze_panes" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.excel_freeze_panes && <Check className="h-4 w-4 text-green-600" />}
                                Freeze Header Row
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Keep header row visible when scrolling
                            </p>
                        </div>
                        <Switch
                            id="excel_freeze_panes"
                            checked={formData.excel_freeze_panes}
                            onCheckedChange={(checked) => handleInputChange('excel_freeze_panes', checked)}
                        />
                    </div>

                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex-1">
                            <Label htmlFor="excel_summary_sheet" className="flex items-center gap-2 font-semibold cursor-pointer">
                                {formData.excel_summary_sheet && <Check className="h-4 w-4 text-green-600" />}
                                Include Summary Sheet
                            </Label>
                            <p className="text-sm text-muted-foreground mt-1">
                                Add summary sheet with key metrics and totals
                            </p>
                        </div>
                        <Switch
                            id="excel_summary_sheet"
                            checked={formData.excel_summary_sheet}
                            onCheckedChange={(checked) => handleInputChange('excel_summary_sheet', checked)}
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Scheduled Reports Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Mail className="h-5 w-5" />
                        Scheduled Reports
                    </CardTitle>
                    <CardDescription>Configure automated monthly reports sent via email</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Monthly Payroll Report */}
                    <div className="space-y-4 p-4 border rounded-lg">
                        <div className="flex items-center justify-between">
                            <div className="flex-1">
                                <Label htmlFor="monthly_payroll_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                    <File className="h-4 w-4" />
                                    Monthly Payroll Register
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Complete payroll register sent on 1st of each month
                                </p>
                            </div>
                            <Switch
                                id="monthly_payroll_enabled"
                                checked={formData.monthly_payroll_enabled}
                                onCheckedChange={(checked) => handleInputChange('monthly_payroll_enabled', checked)}
                            />
                        </div>

                        {formData.monthly_payroll_enabled && (
                            <div className="space-y-2 border-t pt-4">
                                <Label>Email Recipients</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="email"
                                        placeholder="Enter email address"
                                        value={newRecipients.monthly_payroll}
                                        onChange={(e) => setNewRecipients((prev) => ({ ...prev, monthly_payroll: e.target.value }))}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addRecipient('monthly_payroll');
                                            }
                                        }}
                                    />
                                    <Button type="button" size="sm" onClick={() => addRecipient('monthly_payroll')}>
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </div>
                                {errors.monthly_payroll_recipients && (
                                    <p className="text-sm text-red-500">{errors.monthly_payroll_recipients}</p>
                                )}
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {formData.monthly_payroll_recipients.map((email) => (
                                        <Badge key={email} variant="secondary" className="flex items-center gap-1">
                                            {email}
                                            <X
                                                className="h-3 w-3 cursor-pointer"
                                                onClick={() => removeRecipient('monthly_payroll', email)}
                                            />
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Attendance Summary Report */}
                    <div className="space-y-4 p-4 border rounded-lg">
                        <div className="flex items-center justify-between">
                            <div className="flex-1">
                                <Label htmlFor="attendance_summary_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                    <File className="h-4 w-4" />
                                    Attendance Summary Report
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Monthly attendance statistics with late/absent trends
                                </p>
                            </div>
                            <Switch
                                id="attendance_summary_enabled"
                                checked={formData.attendance_summary_enabled}
                                onCheckedChange={(checked) => handleInputChange('attendance_summary_enabled', checked)}
                            />
                        </div>

                        {formData.attendance_summary_enabled && (
                            <div className="space-y-2 border-t pt-4">
                                <Label>Email Recipients</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="email"
                                        placeholder="Enter email address"
                                        value={newRecipients.attendance_summary}
                                        onChange={(e) => setNewRecipients((prev) => ({ ...prev, attendance_summary: e.target.value }))}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addRecipient('attendance_summary');
                                            }
                                        }}
                                    />
                                    <Button type="button" size="sm" onClick={() => addRecipient('attendance_summary')}>
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </div>
                                {errors.attendance_summary_recipients && (
                                    <p className="text-sm text-red-500">{errors.attendance_summary_recipients}</p>
                                )}
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {formData.attendance_summary_recipients.map((email) => (
                                        <Badge key={email} variant="secondary" className="flex items-center gap-1">
                                            {email}
                                            <X
                                                className="h-3 w-3 cursor-pointer"
                                                onClick={() => removeRecipient('attendance_summary', email)}
                                            />
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Leave Utilization Report */}
                    <div className="space-y-4 p-4 border rounded-lg">
                        <div className="flex items-center justify-between">
                            <div className="flex-1">
                                <Label htmlFor="leave_utilization_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                    <File className="h-4 w-4" />
                                    Leave Utilization Report
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Monthly leave balances and utilization by department
                                </p>
                            </div>
                            <Switch
                                id="leave_utilization_enabled"
                                checked={formData.leave_utilization_enabled}
                                onCheckedChange={(checked) => handleInputChange('leave_utilization_enabled', checked)}
                            />
                        </div>

                        {formData.leave_utilization_enabled && (
                            <div className="space-y-2 border-t pt-4">
                                <Label>Email Recipients</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="email"
                                        placeholder="Enter email address"
                                        value={newRecipients.leave_utilization}
                                        onChange={(e) => setNewRecipients((prev) => ({ ...prev, leave_utilization: e.target.value }))}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addRecipient('leave_utilization');
                                            }
                                        }}
                                    />
                                    <Button type="button" size="sm" onClick={() => addRecipient('leave_utilization')}>
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </div>
                                {errors.leave_utilization_recipients && (
                                    <p className="text-sm text-red-500">{errors.leave_utilization_recipients}</p>
                                )}
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {formData.leave_utilization_recipients.map((email) => (
                                        <Badge key={email} variant="secondary" className="flex items-center gap-1">
                                            {email}
                                            <X
                                                className="h-3 w-3 cursor-pointer"
                                                onClick={() => removeRecipient('leave_utilization', email)}
                                            />
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Government Remittance Report */}
                    <div className="space-y-4 p-4 border rounded-lg">
                        <div className="flex items-center justify-between">
                            <div className="flex-1">
                                <Label htmlFor="government_remittance_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                    <File className="h-4 w-4" />
                                    Government Remittance Report
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Monthly SSS, PhilHealth, Pag-IBIG, and BIR contributions
                                </p>
                            </div>
                            <Switch
                                id="government_remittance_enabled"
                                checked={formData.government_remittance_enabled}
                                onCheckedChange={(checked) => handleInputChange('government_remittance_enabled', checked)}
                            />
                        </div>

                        {formData.government_remittance_enabled && (
                            <div className="space-y-2 border-t pt-4">
                                <Label>Email Recipients</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="email"
                                        placeholder="Enter email address"
                                        value={newRecipients.government_remittance}
                                        onChange={(e) => setNewRecipients((prev) => ({ ...prev, government_remittance: e.target.value }))}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addRecipient('government_remittance');
                                            }
                                        }}
                                    />
                                    <Button type="button" size="sm" onClick={() => addRecipient('government_remittance')}>
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </div>
                                {errors.government_remittance_recipients && (
                                    <p className="text-sm text-red-500">{errors.government_remittance_recipients}</p>
                                )}
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {formData.government_remittance_recipients.map((email) => (
                                        <Badge key={email} variant="secondary" className="flex items-center gap-1">
                                            {email}
                                            <X
                                                className="h-3 w-3 cursor-pointer"
                                                onClick={() => removeRecipient('government_remittance', email)}
                                            />
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Summary Card */}
            <Card className="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 border-indigo-200 dark:border-indigo-800">
                <CardHeader>
                    <CardTitle>Report Configuration Summary</CardTitle>
                    <CardDescription>Review your current report settings</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">PDF Format</p>
                            <p className="text-lg font-bold">{formData.pdf_page_size} {formData.pdf_orientation}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Excel Features</p>
                            <p className="text-lg font-bold">
                                {[formData.excel_auto_width, formData.excel_freeze_panes, formData.excel_summary_sheet].filter(Boolean).length} of 3 Enabled
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Scheduled Reports</p>
                            <p className="text-lg font-bold">{enabledReportsCount} of 4 Active</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
                <Button onClick={handleSubmit} disabled={isSubmitting} size="lg">
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? 'Saving...' : 'Save Report Settings'}
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
