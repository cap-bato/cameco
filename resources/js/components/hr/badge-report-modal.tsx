import React, { useState, useMemo } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    RadioGroup,
    RadioGroupItem,
} from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Download,
    Mail,
    FileText,
    BarChart3,
    AlertTriangle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface Badge {
    id: string;
    card_uid: string;
    employee_id: string;
    employee_name: string;
    department: string;
    position: string;
    card_type: 'mifare' | 'desfire' | 'em4100';
    issued_at: string;
    expires_at: string | null;
    is_active: boolean;
    last_used_at: string | null;
    usage_count: number;
    status: string;
    employee_photo?: string;
}

interface Employee {
    id: string;
    name: string;
    employee_id: string;
    department: string;
    hire_date: string;
}

interface BadgesData {
    data: Badge[];
}

interface BadgeReportModalProps {
    isOpen: boolean;
    onClose: () => void;
    badges: BadgesData;
    employees: Employee[];
}

type ReportType = 'active' | 'no-badge' | 'expiring' | 'history' | 'lost-stolen';
type ExportFormat = 'pdf' | 'excel' | 'csv' | 'email';

interface ReportFilters {
    reportType: ReportType;
    dateRangeStart: string;
    dateRangeEnd: string;
    department: string;
    status: string;
    search: string;
    groupBy: 'department' | 'status' | 'none';
    sortBy: 'name' | 'department' | 'issued' | 'expires';
    sortOrder: 'asc' | 'desc';
}

interface ReportStats {
    totalRecords: number;
    activeCount: number;
    expiredCount: number;
    lostStolenCount: number;
    coveragePercentage: number;
}

const reportTypeOptions = [
    { value: 'active', label: 'Active Badges Report', icon: '✅', description: 'All active badges grouped by department' },
    { value: 'no-badge', label: 'Employees Without Badges', icon: '⚠️', description: 'Employees needing badge assignment (compliance)' },
    { value: 'expiring', label: 'Expired/Expiring Badges', icon: '⏰', description: 'Badges expired or expiring in next 30 days' },
    { value: 'history', label: 'Badge Issuance History', icon: '📋', description: 'All badge actions in selected date range' },
    { value: 'lost-stolen', label: 'Lost/Stolen Badges', icon: '🔴', description: 'All reported lost or stolen badges' },
];

export function BadgeReportModal({
    isOpen,
    onClose,
    badges,
    employees,
}: BadgeReportModalProps) {
    const [filters, setFilters] = useState<ReportFilters>({
        reportType: 'active',
        dateRangeStart: '',
        dateRangeEnd: '',
        department: '',
        status: '',
        search: '',
        groupBy: 'department',
        sortBy: 'name',
        sortOrder: 'asc',
    });

    const [showPreview, setShowPreview] = useState(true);
    const [printMode, setPrintMode] = useState(false);
    const [exportFormat, setExportFormat] = useState<ExportFormat | null>(null);
    const [emailTo, setEmailTo] = useState('');
    const [includeDetailedData, setIncludeDetailedData] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

    // Get unique departments
    const departments = useMemo(() => {
        const depts = new Set(badges.data.map(b => b.department));
        return Array.from(depts).sort();
    }, [badges.data]);

    // Get unique statuses
    const statuses = useMemo(() => {
        const stats = new Set(badges.data.map(b => b.status));
        return Array.from(stats).sort();
    }, [badges.data]);

    // Generate report data based on report type and filters
    const reportData = useMemo(() => {
        let data: (Badge | Employee & { type?: string })[] = [];

        switch (filters.reportType) {
            case 'active':
                data = badges.data.filter(b => b.is_active && b.status === 'active');
                break;

            case 'no-badge':
                data = employees
                    .filter(emp => !badges.data.some(b => b.employee_id === emp.employee_id))
                    .sort((a, b) => new Date(b.hire_date).getTime() - new Date(a.hire_date).getTime())
                    .map(emp => ({
                        ...emp,
                        type: 'employee-without-badge',
                    }));
                break;

            case 'expiring': {
                const now = new Date();
                const thirtyDaysFromNow = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
                data = badges.data.filter(b => {
                    if (!b.expires_at) return false;
                    const expiryDate = new Date(b.expires_at);
                    return expiryDate <= thirtyDaysFromNow;
                });
                break;
            }

            case 'history':
                data = badges.data as (Badge | Employee & { type?: string })[];
                if (filters.dateRangeStart) {
                    const startDate = new Date(filters.dateRangeStart);
                    data = data.filter(b => {
                        if ('issued_at' in b) return new Date(b.issued_at) >= startDate;
                        return false;
                    });
                }
                if (filters.dateRangeEnd) {
                    const endDate = new Date(filters.dateRangeEnd);
                    endDate.setDate(endDate.getDate() + 1);
                    data = data.filter(b => {
                        if ('issued_at' in b) return new Date(b.issued_at) < endDate;
                        return false;
                    });
                }
                break;

            case 'lost-stolen':
                data = badges.data.filter(b => b.status === 'lost' || b.status === 'stolen');
                break;
        }

        // Apply filters
        if (filters.department && filters.reportType !== 'no-badge') {
            data = data.filter(b => b.department === filters.department);
        }
        if (filters.status && ['active', 'history'].includes(filters.reportType)) {
            data = data.filter(b => {
                if ('status' in b) return b.status === filters.status;
                return false;
            });
        }
        if (filters.search) {
            const searchLower = filters.search.toLowerCase();
            data = data.filter(b => {
                const name = (('employee_name' in b ? b.employee_name : '') || ('name' in b ? b.name : '') || '').toLowerCase();
                const empId = (('employee_id' in b ? b.employee_id : '') || '').toLowerCase();
                const cardUid = (('card_uid' in b ? b.card_uid : '') || '').toLowerCase();
                return name.includes(searchLower) || empId.includes(searchLower) || cardUid.includes(searchLower);
            });
        }

        // Apply sorting
        const sortKey = filters.sortBy as keyof typeof data[0];
        data.sort((a, b) => {
            let aVal = a[sortKey] || '';
            let bVal = b[sortKey] || '';

            if (typeof aVal === 'string') {
                aVal = aVal.toLowerCase();
                bVal = (bVal || '').toLowerCase();
            }

            const comparison = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
            return filters.sortOrder === 'asc' ? comparison : -comparison;
        });

        return data;
    }, [filters, badges.data, employees]);

    // Group data if needed
    const groupedData = useMemo(() => {
        if (filters.groupBy === 'none') {
            return { 'All': reportData };
        }

        const grouped: Record<string, (Badge | Employee & { type?: string })[]> = {};
        reportData.forEach(item => {
            const key = item[filters.groupBy as keyof typeof item] || 'Unknown';
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(item);
        });

        return grouped;
    }, [reportData, filters.groupBy]);

    // Calculate stats
    const stats = useMemo((): ReportStats => {
        const allBadges = badges.data;
        const active = allBadges.filter(b => b.is_active).length;
        const expired = allBadges.filter(b => {
            if (!b.expires_at) return false;
            return new Date(b.expires_at) < new Date();
        }).length;
        const lostStolen = allBadges.filter(b => b.status === 'lost' || b.status === 'stolen').length;
        const coverage = employees.length > 0
            ? ((allBadges.length / employees.length) * 100).toFixed(1)
            : '0.0';

        return {
            totalRecords: reportData.length,
            activeCount: active,
            expiredCount: expired,
            lostStolenCount: lostStolen,
            coveragePercentage: parseFloat(coverage),
        };
    }, [reportData, badges.data, employees.length]);

    const handleExport = async (format: ExportFormat) => {
        setIsExporting(true);
        try {
            // Prepare export data
            const exportPayload = {
                reportType: filters.reportType,
                format: format,
                data: reportData,
                groupBy: filters.groupBy,
                includeStats: true,
                includeDetailedData: includeDetailedData && format === 'email',
                emailTo: format === 'email' ? emailTo : undefined,
            };

            // Call backend export endpoint
            const response = await fetch(route('hr.timekeeping.badges.export'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(exportPayload),
            });

            if (!response.ok) {
                throw new Error('Export failed');
            }

            if (format === 'email') {
                alert('Report sent to ' + emailTo);
                setEmailTo('');
            } else if (format === 'pdf' || format === 'excel') {
                // Download file
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `badge-report-${filters.reportType}-${new Date().toISOString().split('T')[0]}.${format === 'pdf' ? 'pdf' : 'xlsx'}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else if (format === 'csv') {
                // CSV download
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `badge-report-${filters.reportType}-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            }

            setExportFormat(null);
        } catch (error) {
            console.error('Export error:', error);
            alert('Failed to export report');
        } finally {
            setIsExporting(false);
        }
    };

    const reportType = reportTypeOptions.find(r => r.value === filters.reportType);

    if (printMode) {
        return (
            <div className="fixed inset-0 bg-white p-8 overflow-auto z-50">
                <div className="max-w-4xl mx-auto">
                    {/* Print Header */}
                    <div className="text-center mb-8 border-b pb-4">
                        <h1 className="text-3xl font-bold">Badge Report</h1>
                        <p className="text-sm text-gray-600 mt-2">{reportType?.label}</p>
                        <p className="text-xs text-gray-500">Generated: {new Date().toLocaleDateString('en-US', { dateStyle: 'long' })}</p>
                    </div>

                    {/* Summary Stats */}
                    <div className="grid grid-cols-4 gap-4 mb-8 page-break-after">
                        <div className="p-3 border rounded">
                            <p className="text-xs text-gray-600">Total Records</p>
                            <p className="text-2xl font-bold">{stats.totalRecords}</p>
                        </div>
                        <div className="p-3 border rounded">
                            <p className="text-xs text-gray-600">Active</p>
                            <p className="text-2xl font-bold text-green-600">{stats.activeCount}</p>
                        </div>
                        <div className="p-3 border rounded">
                            <p className="text-xs text-gray-600">Expired</p>
                            <p className="text-2xl font-bold text-red-600">{stats.expiredCount}</p>
                        </div>
                        <div className="p-3 border rounded">
                            <p className="text-xs text-gray-600">Lost/Stolen</p>
                            <p className="text-2xl font-bold text-orange-600">{stats.lostStolenCount}</p>
                        </div>
                    </div>

                    {/* Data Table */}
                    <div className="mb-8">
                        {Object.entries(groupedData).map(([group, items]: [string, (Badge | Employee & { type?: string })[]]) => (
                            <div key={group} className="mb-6 page-break-inside-avoid">
                                {filters.groupBy !== 'none' && (
                                    <h3 className="font-bold text-lg mb-3">{group}</h3>
                                )}
                                <table className="w-full text-sm border-collapse">
                                    <thead>
                                        <tr className="bg-gray-100 border">
                                            <th className="border p-2 text-left font-bold">Employee</th>
                                            <th className="border p-2 text-left font-bold">ID</th>
                                            <th className="border p-2 text-left font-bold">Card UID</th>
                                            <th className="border p-2 text-left font-bold">Status</th>
                                            <th className="border p-2 text-left font-bold">Issued</th>
                                            {filters.reportType === 'expiring' && (
                                                <th className="border p-2 text-left font-bold">Expires</th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item, idx) => (
                                            <tr key={idx} className="border">
                                                <td className="border p-2">{('employee_name' in item ? item.employee_name : '') || ('name' in item ? item.name : '')}</td>
                                                <td className="border p-2">{'employee_id' in item ? item.employee_id : 'N/A'}</td>
                                                <td className="border p-2 font-mono text-xs">{'card_uid' in item ? item.card_uid : 'N/A'}</td>
                                                <td className="border p-2">{'status' in item ? item.status : 'Active'}</td>
                                                <td className="border p-2 text-xs">
                                                    {'issued_at' in item && item.issued_at ? new Date(item.issued_at).toLocaleDateString() : 'N/A'}
                                                </td>
                                                {filters.reportType === 'expiring' && (
                                                    <td className="border p-2 text-xs">
                                                        {'expires_at' in item && item.expires_at ? new Date(item.expires_at).toLocaleDateString() : 'N/A'}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ))}
                    </div>

                    {/* Print Footer */}
                    <div className="text-center text-xs text-gray-500 border-t pt-4 mt-8">
                        <p>This is a confidential report. Please handle appropriately.</p>
                        <p>Page {Math.ceil(Object.values(groupedData).reduce((sum, arr) => sum + arr.length, 0) / 20)}</p>
                    </div>

                    {/* Print Controls */}
                    <div className="fixed bottom-4 right-4 flex gap-2 no-print">
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            Print
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setPrintMode(false)}
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-xl">Badge Report & Export</DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Report Type Selection */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Report Type</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <RadioGroup value={filters.reportType} onValueChange={(val) => setFilters({ ...filters, reportType: val as ReportType })}>
                                <div className="space-y-3">
                                    {reportTypeOptions.map((option) => (
                                        <div
                                            key={option.value}
                                            className="flex items-start space-x-3 p-3 border rounded-lg hover:bg-muted cursor-pointer"
                                        >
                                            <RadioGroupItem value={option.value} id={`report-${option.value}`} className="mt-1" />
                                            <div className="flex-1">
                                                <Label htmlFor={`report-${option.value}`} className="font-semibold cursor-pointer">
                                                    {option.icon} {option.label}
                                                </Label>
                                                <p className="text-sm text-muted-foreground mt-1">{option.description}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </RadioGroup>
                        </CardContent>
                    </Card>

                    {/* Filters */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Filters & Options</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Date Range */}
                            {['history', 'no-badge'].includes(filters.reportType) && (
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="date-start">Start Date</Label>
                                        <Input
                                            id="date-start"
                                            type="date"
                                            value={filters.dateRangeStart}
                                            onChange={(e) => setFilters({ ...filters, dateRangeStart: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="date-end">End Date</Label>
                                        <Input
                                            id="date-end"
                                            type="date"
                                            value={filters.dateRangeEnd}
                                            onChange={(e) => setFilters({ ...filters, dateRangeEnd: e.target.value })}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Department Filter */}
                            {filters.reportType !== 'no-badge' && (
                                <div className="space-y-2">
                                    <Label htmlFor="department">Department</Label>
                                    <Select value={filters.department} onValueChange={(val) => setFilters({ ...filters, department: val })}>
                                        <SelectTrigger id="department">
                                            <SelectValue placeholder="All Departments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All Departments</SelectItem>
                                            {departments.map((dept) => (
                                                <SelectItem key={dept} value={dept}>
                                                    {dept}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Status Filter */}
                            {['active', 'history'].includes(filters.reportType) && (
                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select value={filters.status} onValueChange={(val) => setFilters({ ...filters, status: val })}>
                                        <SelectTrigger id="status">
                                            <SelectValue placeholder="All Statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All Statuses</SelectItem>
                                            {statuses.map((status) => (
                                                <SelectItem key={status} value={status}>
                                                    {status.charAt(0).toUpperCase() + status.slice(1)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Search */}
                            <div className="space-y-2">
                                <Label htmlFor="search">Search (Name, ID, Card UID)</Label>
                                <Input
                                    id="search"
                                    placeholder="Enter name, employee ID, or card UID..."
                                    value={filters.search}
                                    onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                                />
                            </div>

                            {/* Grouping Options */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="groupby">Group By</Label>
                                    <Select value={filters.groupBy} onValueChange={(val) => setFilters({ ...filters, groupBy: val as 'department' | 'status' | 'none' })}>
                                        <SelectTrigger id="groupby">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="department">Department</SelectItem>
                                            <SelectItem value="status">Status</SelectItem>
                                            <SelectItem value="none">None</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="sortby">Sort By</Label>
                                    <Select value={filters.sortBy} onValueChange={(val) => setFilters({ ...filters, sortBy: val as 'name' | 'department' | 'issued' | 'expires' })}>
                                        <SelectTrigger id="sortby">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="name">Name</SelectItem>
                                            <SelectItem value="department">Department</SelectItem>
                                            <SelectItem value="issued">Issued Date</SelectItem>
                                            <SelectItem value="expires">Expiration Date</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Preview Toggle */}
                    <div className="flex items-center justify-between">
                        <Label className="flex items-center gap-2 cursor-pointer">
                            <Checkbox
                                checked={showPreview}
                                onCheckedChange={(checked) => setShowPreview(checked as boolean)}
                            />
                            Show Preview
                        </Label>
                    </div>

                    {/* Report Preview */}
                    {showPreview && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BarChart3 className="h-5 w-5" />
                                    Report Preview
                                </CardTitle>
                                <CardDescription>
                                    Total Records: <strong>{stats.totalRecords}</strong>
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Summary Stats */}
                                <div className="grid grid-cols-4 gap-3">
                                    <div className="p-3 bg-green-50 border border-green-200 rounded">
                                        <p className="text-xs text-gray-600">Active</p>
                                        <p className="text-xl font-bold text-green-600">{stats.activeCount}</p>
                                    </div>
                                    <div className="p-3 bg-red-50 border border-red-200 rounded">
                                        <p className="text-xs text-gray-600">Expired</p>
                                        <p className="text-xl font-bold text-red-600">{stats.expiredCount}</p>
                                    </div>
                                    <div className="p-3 bg-orange-50 border border-orange-200 rounded">
                                        <p className="text-xs text-gray-600">Lost/Stolen</p>
                                        <p className="text-xl font-bold text-orange-600">{stats.lostStolenCount}</p>
                                    </div>
                                    <div className="p-3 bg-blue-50 border border-blue-200 rounded">
                                        <p className="text-xs text-gray-600">Coverage</p>
                                        <p className="text-xl font-bold text-blue-600">{stats.coveragePercentage}%</p>
                                    </div>
                                </div>

                                {/* Data Table Preview */}
                                <div className="max-h-96 overflow-y-auto">
                                    {Object.entries(groupedData).map(([group, items]: [string, (Badge | Employee & { type?: string })[]]) => (
                                        <div key={group}>
                                            {filters.groupBy !== 'none' && (
                                                <h4 className="font-semibold text-sm mt-3 mb-2">{group}</h4>
                                            )}
                                            <Table>
                                                <TableHeader>
                                                    <TableRow className="bg-muted">
                                                        <TableHead>Employee</TableHead>
                                                        <TableHead>ID</TableHead>
                                                        <TableHead>Card UID</TableHead>
                                                        <TableHead>Status</TableHead>
                                                        {filters.reportType === 'expiring' && (
                                                            <TableHead>Expires</TableHead>
                                                        )}
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {items.slice(0, 10).map((item, idx) => (
                                                        <TableRow key={idx}>
                                                            <TableCell>{('employee_name' in item ? item.employee_name : '') || ('name' in item ? item.name : '')}</TableCell>
                                                            <TableCell className="text-sm">{'employee_id' in item ? item.employee_id : 'N/A'}</TableCell>
                                                            <TableCell className="font-mono text-xs">{'card_uid' in item ? item.card_uid : 'N/A'}</TableCell>
                                                            <TableCell>
                                                                <Badge variant="outline">
                                                                    {'status' in item ? item.status : 'Active'}
                                                                </Badge>
                                                            </TableCell>
                                                            {filters.reportType === 'expiring' && (
                                                                <TableCell className="text-sm">
                                                                    {'expires_at' in item && item.expires_at ? new Date(item.expires_at).toLocaleDateString() : 'N/A'}
                                                                </TableCell>
                                                            )}
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                            {items.length > 10 && (
                                                <p className="text-xs text-muted-foreground p-3">
                                                    ... and {items.length - 10} more
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                    {reportData.length === 0 && (
                                        <Alert>
                                            <AlertTriangle className="h-4 w-4" />
                                            <AlertDescription>
                                                No records found matching the selected filters
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Export Options */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Export Options</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => handleExport('pdf')}
                                    disabled={isExporting}
                                >
                                    <FileText className="h-4 w-4" />
                                    Export PDF
                                </Button>
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => handleExport('excel')}
                                    disabled={isExporting}
                                >
                                    <BarChart3 className="h-4 w-4" />
                                    Export Excel
                                </Button>
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => handleExport('csv')}
                                    disabled={isExporting}
                                >
                                    <Download className="h-4 w-4" />
                                    Export CSV
                                </Button>
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => setExportFormat('email')}
                                >
                                    <Mail className="h-4 w-4" />
                                    Email Report
                                </Button>
                            </div>

                            {exportFormat === 'email' && (
                                <div className="space-y-3 p-3 bg-muted rounded-lg">
                                    <div className="space-y-2">
                                        <Label htmlFor="email-to">Send To</Label>
                                        <Input
                                            id="email-to"
                                            type="email"
                                            placeholder="Enter email address"
                                            value={emailTo}
                                            onChange={(e) => setEmailTo(e.target.value)}
                                        />
                                    </div>
                                    <Label className="flex items-center gap-2 cursor-pointer">
                                        <Checkbox
                                            checked={includeDetailedData}
                                            onCheckedChange={(checked) => setIncludeDetailedData(checked as boolean)}
                                        />
                                        Include detailed usage data
                                    </Label>
                                    <Button
                                        className="w-full"
                                        onClick={() => handleExport('email')}
                                        disabled={!emailTo || isExporting}
                                    >
                                        {isExporting ? 'Sending...' : 'Send Email'}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        className="w-full"
                                        onClick={() => setExportFormat(null)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        className="gap-2"
                        onClick={() => setPrintMode(true)}
                    >
                        Print Preview
                    </Button>
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
