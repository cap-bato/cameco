import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { useToast } from '@/hooks/use-toast';
import { format, parseISO } from 'date-fns';
import {
    AlertTriangle,
    Clock,
    CheckCircle,
    XCircle,
    FileText,
    Bell,
    Upload,
    CalendarDays,
    Eye,
    RotateCw,
    Download,
    Search,
    AlertCircle,
    User,
    CalendarIcon,
} from 'lucide-react';

// ============================================================================
// Type Definitions
// ============================================================================

interface ExpiringDocument {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    document_type: string;
    category: string;
    file_name: string;
    file_size: number;
    expires_at: string;
    days_remaining: number;
    status: 'expired' | 'critical' | 'warning' | 'valid';
    uploaded_at: string;
    uploaded_by: string;
}

interface ExpiryStats {
    expired: number;
    critical: number; // 0-7 days
    warning: number; // 8-30 days
    valid: number; // 30+ days
}

interface ExpiryDashboardProps {
    documents?: ExpiringDocument[];
    stats?: ExpiryStats;
}

// ============================================================================
// Mock Data
// ============================================================================

const mockDocuments: ExpiringDocument[] = [
    {
        id: 1,
        employee_id: 1,
        employee_name: 'Juan Dela Cruz',
        employee_number: 'EMP-2024-001',
        department: 'IT Department',
        document_type: 'NBI Clearance',
        category: 'government',
        file_name: 'nbi_clearance_2024.pdf',
        file_size: 180000,
        expires_at: '2024-12-15',
        days_remaining: -5,
        status: 'expired',
        uploaded_at: '2024-03-20',
        uploaded_by: 'HR Staff',
    },
    {
        id: 2,
        employee_id: 2,
        employee_name: 'Maria Santos',
        employee_number: 'EMP-2024-002',
        department: 'Finance',
        document_type: 'Medical Certificate',
        category: 'medical',
        file_name: 'medical_cert_2024.pdf',
        file_size: 320000,
        expires_at: '2024-12-22',
        days_remaining: 2,
        status: 'critical',
        uploaded_at: '2024-02-10',
        uploaded_by: 'HR Manager',
    },
    {
        id: 3,
        employee_id: 3,
        employee_name: 'Pedro Reyes',
        employee_number: 'EMP-2024-003',
        department: 'Operations',
        document_type: 'Police Clearance',
        category: 'government',
        file_name: 'police_clearance.pdf',
        file_size: 150000,
        expires_at: '2025-01-05',
        days_remaining: 16,
        status: 'warning',
        uploaded_at: '2024-01-05',
        uploaded_by: 'HR Staff',
    },
    {
        id: 4,
        employee_id: 4,
        employee_name: 'Ana Garcia',
        employee_number: 'EMP-2024-004',
        department: 'HR',
        document_type: 'Medical Certificate',
        category: 'medical',
        file_size: 290000,
        file_name: 'annual_medical_2024.pdf',
        expires_at: '2025-02-20',
        days_remaining: 62,
        status: 'valid',
        uploaded_at: '2024-02-20',
        uploaded_by: 'HR Staff',
    },
    {
        id: 5,
        employee_id: 5,
        employee_name: 'Carlos Lopez',
        employee_number: 'EMP-2024-005',
        department: 'Sales',
        document_type: 'NBI Clearance',
        category: 'government',
        file_name: 'nbi_2024.pdf',
        file_size: 175000,
        expires_at: '2024-12-18',
        days_remaining: -2,
        status: 'expired',
        uploaded_at: '2024-03-18',
        uploaded_by: 'HR Staff',
    },
    {
        id: 6,
        employee_id: 6,
        employee_name: 'Lisa Ramos',
        employee_number: 'EMP-2024-006',
        department: 'Marketing',
        document_type: 'Driver License',
        category: 'personal',
        file_name: 'drivers_license.jpg',
        file_size: 245000,
        expires_at: '2024-12-24',
        days_remaining: 4,
        status: 'critical',
        uploaded_at: '2024-01-10',
        uploaded_by: 'HR Staff',
    },
];

const mockStats: ExpiryStats = {
    expired: 2,
    critical: 2,
    warning: 1,
    valid: 1,
};

// ============================================================================
// Main Component
// ============================================================================

export default function ExpiryDashboard({ documents: initialDocuments, stats: initialStats }: ExpiryDashboardProps) {
    const { toast } = useToast();

    // State management
    const [documents, setDocuments] = useState<ExpiringDocument[]>(initialDocuments || mockDocuments);
    const [stats, setStats] = useState<ExpiryStats>(initialStats || mockStats);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [departmentFilter, setDepartmentFilter] = useState('all');

    // Modal states
    const [renewalDocument, setRenewalDocument] = useState<ExpiringDocument | null>(null);
    const [renewalMessage, setRenewalMessage] = useState('');
    const [sendEmail, setSendEmail] = useState(true);
    const [sendSMS, setSendSMS] = useState(false);
    const [sendNotification, setSendNotification] = useState(true);

    const [uploadDocument, setUploadDocument] = useState<ExpiringDocument | null>(null);
    const [uploadFile, setUploadFile] = useState<File | null>(null);
    const [newExpiryDate, setNewExpiryDate] = useState<Date | undefined>(undefined);
    const [uploadNotes, setUploadNotes] = useState('');

    const [extendDocument, setExtendDocument] = useState<ExpiringDocument | null>(null);
    const [extendedExpiryDate, setExtendedExpiryDate] = useState<Date | undefined>(undefined);
    const [extendReason, setExtendReason] = useState('');

    // Fetch documents
    const fetchDocuments = () => {
        setLoading(true);
        setTimeout(() => {
            setDocuments(mockDocuments);
            setStats(mockStats);
            setLoading(false);
        }, 500);
    };

    // Filtered documents
    const filteredDocuments = documents.filter((doc) => {
        if (statusFilter !== 'all' && doc.status !== statusFilter) return false;
        if (categoryFilter !== 'all' && doc.category !== categoryFilter) return false;
        if (departmentFilter !== 'all' && doc.department !== departmentFilter) return false;
        if (searchTerm) {
            const query = searchTerm.toLowerCase();
            return (
                doc.employee_name.toLowerCase().includes(query) ||
                doc.employee_number.toLowerCase().includes(query) ||
                doc.document_type.toLowerCase().includes(query)
            );
        }
        return true;
    });

    // Sort by days remaining (expired first, then critical, warning, valid)
    const sortedDocuments = [...filteredDocuments].sort((a, b) => a.days_remaining - b.days_remaining);

    // Get unique departments
    const departments = Array.from(new Set(documents.map((doc) => doc.department))).sort();

    // Actions
    const handleRequestRenewal = () => {
        if (!renewalDocument) return;

        toast({
            title: 'Renewal Request Sent',
            description: `Renewal request sent to ${renewalDocument.employee_name}`,
        });
        setRenewalDocument(null);
        setRenewalMessage('');
    };

    const handleUploadNewVersion = () => {
        if (!uploadDocument || !uploadFile || !newExpiryDate) return;

        toast({
            title: 'Document Renewed',
            description: 'New version uploaded successfully',
        });
        setUploadDocument(null);
        setUploadFile(null);
        setNewExpiryDate(undefined);
        setUploadNotes('');
        fetchDocuments();
    };

    const handleExtendExpiry = () => {
        if (!extendDocument || !extendedExpiryDate || !extendReason) return;

        toast({
            title: 'Expiry Date Extended',
            description: `Expiry date updated for ${extendDocument.document_type}`,
        });
        setExtendDocument(null);
        setExtendedExpiryDate(undefined);
        setExtendReason('');
        fetchDocuments();
    };

    const handleExportCSV = () => {
        toast({
            title: 'Export Started',
            description: 'Expiry report is being generated...',
        });
    };

    // Helper functions
    const getStatusBadge = (status: string, daysRemaining: number) => {
        switch (status) {
            case 'expired':
                return (
                    <Badge className="bg-red-100 text-red-800">
                        <XCircle className="h-3 w-3 mr-1" />
                        Expired ({Math.abs(daysRemaining)} days ago)
                    </Badge>
                );
            case 'critical':
                return (
                    <Badge className="bg-orange-100 text-orange-800">
                        <AlertTriangle className="h-3 w-3 mr-1" />
                        Critical ({daysRemaining} days)
                    </Badge>
                );
            case 'warning':
                return (
                    <Badge className="bg-yellow-100 text-yellow-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Warning ({daysRemaining} days)
                    </Badge>
                );
            case 'valid':
                return (
                    <Badge className="bg-green-100 text-green-800">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Valid ({daysRemaining} days)
                    </Badge>
                );
            default:
                return null;
        }
    };

    const getCategoryBadgeColor = (category: string): string => {
        switch (category) {
            case 'personal': return 'bg-blue-100 text-blue-800';
            case 'government': return 'bg-green-100 text-green-800';
            case 'medical': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const formatDate = (dateString: string): string => {
        const date = parseISO(dateString);
        return format(date, 'MMM dd, yyyy');
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    // Default renewal message
    const defaultRenewalMessage = renewalDocument
        ? `Dear ${renewalDocument.employee_name},\n\nYour ${renewalDocument.document_type} will expire on ${formatDate(renewalDocument.expires_at)}. Please submit a renewed copy at your earliest convenience.\n\nThank you,\nHR Department`
        : '';

    return (
        <AppLayout>
            <Head title="Document Expiry Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Document Expiry Dashboard</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Monitor and manage expiring documents across all employees
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={fetchDocuments} disabled={loading}>
                            <RotateCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button variant="outline" onClick={handleExportCSV}>
                            <Download className="h-4 w-4 mr-2" />
                            Export Report
                        </Button>
                    </div>
                </div>

                {/* Alert Banner */}
                {(stats.expired > 0 || stats.critical > 0) && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            {stats.expired > 0 && (
                                <span className="font-semibold">
                                    ⚠️ {stats.expired} document{stats.expired > 1 ? 's have' : ' has'} expired and require
                                    immediate attention.
                                </span>
                            )}
                            {stats.expired > 0 && stats.critical > 0 && ' '}
                            {stats.critical > 0 && (
                                <span>
                                    ⏰ {stats.critical} document{stats.critical > 1 ? 's are' : ' is'} expiring within 7 days.
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Expired</CardTitle>
                            <XCircle className="h-5 w-5 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.expired}</div>
                            <p className="text-xs text-muted-foreground">Require immediate action</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Expiring This Week</CardTitle>
                            <AlertTriangle className="h-5 w-5 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">{stats.critical}</div>
                            <p className="text-xs text-muted-foreground">Within 7 days</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Expiring This Month</CardTitle>
                            <Clock className="h-5 w-5 text-yellow-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{stats.warning}</div>
                            <p className="text-xs text-muted-foreground">Within 30 days</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Up to Date</CardTitle>
                            <CheckCircle className="h-5 w-5 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.valid}</div>
                            <p className="text-xs text-muted-foreground">Valid for 30+ days</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card className="p-4">
                    <div className="space-y-4">
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search by employee name, number, or document type..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9"
                            />
                        </div>

                        {/* Filters Row */}
                        <div className="flex flex-wrap gap-4">
                            {/* Status Filter */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium">Status:</span>
                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="expired">Expired</SelectItem>
                                        <SelectItem value="critical">Critical (7 days)</SelectItem>
                                        <SelectItem value="warning">Warning (30 days)</SelectItem>
                                        <SelectItem value="valid">Valid</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Category Filter */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium">Category:</span>
                                <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        <SelectItem value="personal">Personal</SelectItem>
                                        <SelectItem value="government">Government</SelectItem>
                                        <SelectItem value="medical">Medical</SelectItem>
                                        <SelectItem value="educational">Educational</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Department Filter */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium">Department:</span>
                                <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept} value={dept}>
                                                {dept}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Clear Filters */}
                            {(searchTerm || statusFilter !== 'all' || categoryFilter !== 'all' || departmentFilter !== 'all') && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setSearchTerm('');
                                        setStatusFilter('all');
                                        setCategoryFilter('all');
                                        setDepartmentFilter('all');
                                    }}
                                >
                                    Clear Filters
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Documents Table */}
                {loading ? (
                    <div className="space-y-3">
                        {[1, 2, 3].map((i) => (
                            <Card key={i} className="p-4">
                                <div className="flex items-start space-x-4">
                                    <Skeleton className="h-12 w-12 rounded-lg" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-5 w-1/3" />
                                        <Skeleton className="h-4 w-1/2" />
                                        <Skeleton className="h-3 w-2/3" />
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                ) : sortedDocuments.length === 0 ? (
                    <Card className="p-12">
                        <div className="flex flex-col items-center justify-center text-center">
                            <div className="rounded-full bg-muted p-4 mb-4">
                                <FileText className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold mb-2">No Documents Found</h3>
                            <p className="text-sm text-muted-foreground mb-4 max-w-sm">
                                No documents match your filters. Try adjusting your search criteria.
                            </p>
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {sortedDocuments.map((doc) => (
                            <Card
                                key={doc.id}
                                className={`p-4 hover:shadow-md transition-shadow ${
                                    doc.status === 'expired'
                                        ? 'border-l-4 border-red-500'
                                        : doc.status === 'critical'
                                        ? 'border-l-4 border-orange-500'
                                        : ''
                                }`}
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start space-x-4 flex-1">
                                        <div className="rounded-lg bg-muted p-3">
                                            <FileText className="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h4 className="font-semibold">{doc.document_type}</h4>
                                                <Badge className={getCategoryBadgeColor(doc.category)}>
                                                    {doc.category}
                                                </Badge>
                                                {getStatusBadge(doc.status, doc.days_remaining)}
                                            </div>
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                                <User className="h-3 w-3" />
                                                <span className="font-medium">{doc.employee_name}</span>
                                                <span>({doc.employee_number})</span>
                                                <span>•</span>
                                                <span>{doc.department}</span>
                                            </div>
                                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                <span>Expires: {formatDate(doc.expires_at)}</span>
                                                <span>•</span>
                                                <span>File: {doc.file_name}</span>
                                                <span>•</span>
                                                <span>{formatFileSize(doc.file_size)}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 ml-4">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setRenewalDocument(doc);
                                                setRenewalMessage(defaultRenewalMessage);
                                            }}
                                        >
                                            <Bell className="h-4 w-4 mr-2" />
                                            Request Renewal
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setUploadDocument(doc)}
                                        >
                                            <Upload className="h-4 w-4 mr-2" />
                                            Upload New
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setExtendDocument(doc)}
                                        >
                                            <CalendarDays className="h-4 w-4 mr-2" />
                                            Extend
                                        </Button>
                                        <Button variant="ghost" size="sm" title="View Document">
                                            <Eye className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Request Renewal Modal */}
            <Dialog open={!!renewalDocument} onOpenChange={(open) => !open && setRenewalDocument(null)}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Request Document Renewal</DialogTitle>
                        <DialogDescription>
                            Send a renewal request to the employee for this document
                        </DialogDescription>
                    </DialogHeader>

                    {renewalDocument && (
                        <div className="space-y-4">
                            {/* Employee Info */}
                            <Card className="bg-muted/50">
                                <CardContent className="pt-4">
                                    <div className="flex items-center gap-4">
                                        <div className="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center">
                                            <User className="h-6 w-6 text-primary" />
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">{renewalDocument.employee_name}</h4>
                                            <p className="text-sm text-muted-foreground">
                                                {renewalDocument.employee_number} • {renewalDocument.department}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span className="text-muted-foreground">Document:</span>
                                            <p className="font-medium">{renewalDocument.document_type}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Expires:</span>
                                            <p className="font-medium text-red-600">
                                                {formatDate(renewalDocument.expires_at)}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Days Remaining:</span>
                                            <p className="font-medium">
                                                {renewalDocument.days_remaining < 0
                                                    ? `Expired ${Math.abs(renewalDocument.days_remaining)} days ago`
                                                    : `${renewalDocument.days_remaining} days`}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Category:</span>
                                            <p className="font-medium">{renewalDocument.category}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Message */}
                            <div className="space-y-2">
                                <Label htmlFor="renewal-message">Renewal Request Message</Label>
                                <Textarea
                                    id="renewal-message"
                                    value={renewalMessage}
                                    onChange={(e) => setRenewalMessage(e.target.value)}
                                    rows={6}
                                    placeholder="Enter renewal request message..."
                                />
                            </div>

                            {/* Send Via Options */}
                            <div className="space-y-3">
                                <Label>Send Notification Via:</Label>
                                <div className="flex flex-col gap-2">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="send-email"
                                            checked={sendEmail}
                                            onCheckedChange={(checked) => setSendEmail(checked as boolean)}
                                        />
                                        <label htmlFor="send-email" className="text-sm cursor-pointer">
                                            Email
                                        </label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="send-sms"
                                            checked={sendSMS}
                                            onCheckedChange={(checked) => setSendSMS(checked as boolean)}
                                        />
                                        <label htmlFor="send-sms" className="text-sm cursor-pointer">
                                            SMS
                                        </label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="send-notification"
                                            checked={sendNotification}
                                            onCheckedChange={(checked) => setSendNotification(checked as boolean)}
                                        />
                                        <label htmlFor="send-notification" className="text-sm cursor-pointer">
                                            In-App Notification
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRenewalDocument(null)}>
                            Cancel
                        </Button>
                        <Button onClick={handleRequestRenewal}>
                            <Bell className="h-4 w-4 mr-2" />
                            Send Renewal Request
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Upload New Version Modal */}
            <Dialog open={!!uploadDocument} onOpenChange={(open) => !open && setUploadDocument(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Upload New Document Version</DialogTitle>
                        <DialogDescription>
                            Upload a renewed version of this document
                        </DialogDescription>
                    </DialogHeader>

                    {uploadDocument && (
                        <div className="space-y-4">
                            {/* Document Info */}
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    <strong>Document:</strong> {uploadDocument.document_type}
                                    <br />
                                    <strong>Employee:</strong> {uploadDocument.employee_name}
                                </AlertDescription>
                            </Alert>

                            {/* File Upload */}
                            <div className="space-y-2">
                                <Label htmlFor="upload-file">New Document File *</Label>
                                <Input
                                    id="upload-file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.docx"
                                    onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Max 10MB. Accepted formats: PDF, JPG, PNG, DOCX
                                </p>
                            </div>

                            {/* New Expiry Date */}
                            <div className="space-y-2">
                                <Label>New Expiry Date *</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="w-full justify-start text-left font-normal"
                                        >
                                            <CalendarIcon className="mr-2 h-4 w-4" />
                                            {newExpiryDate ? format(newExpiryDate, 'PPP') : <span>Pick a date</span>}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0">
                                        <Calendar
                                            mode="single"
                                            selected={newExpiryDate}
                                            onSelect={setNewExpiryDate}
                                            disabled={(date) => date < new Date()}
                                            initialFocus
                                        />
                                    </PopoverContent>
                                </Popover>
                            </div>

                            {/* Notes */}
                            <div className="space-y-2">
                                <Label htmlFor="upload-notes">Notes (Optional)</Label>
                                <Textarea
                                    id="upload-notes"
                                    value={uploadNotes}
                                    onChange={(e) => setUploadNotes(e.target.value)}
                                    rows={3}
                                    placeholder="e.g., Renewed NBI Clearance valid until 2026"
                                />
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setUploadDocument(null)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleUploadNewVersion}
                            disabled={!uploadFile || !newExpiryDate}
                        >
                            <Upload className="h-4 w-4 mr-2" />
                            Upload Renewal
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Extend Expiry Modal */}
            <Dialog open={!!extendDocument} onOpenChange={(open) => !open && setExtendDocument(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Extend Expiry Date</DialogTitle>
                        <DialogDescription>
                            Extend the expiry date for this document
                        </DialogDescription>
                    </DialogHeader>

                    {extendDocument && (
                        <div className="space-y-4">
                            {/* Document Info */}
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    <strong>Document:</strong> {extendDocument.document_type}
                                    <br />
                                    <strong>Employee:</strong> {extendDocument.employee_name}
                                    <br />
                                    <strong>Current Expiry:</strong> {formatDate(extendDocument.expires_at)}
                                </AlertDescription>
                            </Alert>

                            {/* New Expiry Date */}
                            <div className="space-y-2">
                                <Label>New Expiry Date *</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="w-full justify-start text-left font-normal"
                                        >
                                            <CalendarIcon className="mr-2 h-4 w-4" />
                                            {extendedExpiryDate ? (
                                                format(extendedExpiryDate, 'PPP')
                                            ) : (
                                                <span>Pick a date</span>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0">
                                        <Calendar
                                            mode="single"
                                            selected={extendedExpiryDate}
                                            onSelect={setExtendedExpiryDate}
                                            disabled={(date) => date <= parseISO(extendDocument.expires_at)}
                                            initialFocus
                                        />
                                    </PopoverContent>
                                </Popover>
                                <p className="text-xs text-muted-foreground">
                                    Must be after current expiry date
                                </p>
                            </div>

                            {/* Reason */}
                            <div className="space-y-2">
                                <Label htmlFor="extend-reason">Reason for Extension *</Label>
                                <Textarea
                                    id="extend-reason"
                                    value={extendReason}
                                    onChange={(e) => setExtendReason(e.target.value)}
                                    rows={3}
                                    placeholder="Enter reason for extending expiry date..."
                                    required
                                />
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setExtendDocument(null)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleExtendExpiry}
                            disabled={!extendedExpiryDate || !extendReason}
                        >
                            <CalendarDays className="h-4 w-4 mr-2" />
                            Extend Expiry Date
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
