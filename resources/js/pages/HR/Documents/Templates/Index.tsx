import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useToast } from '@/hooks/use-toast';
import { CreateTemplateModal } from '@/components/hr/create-template-modal';
import { GenerateDocumentModal } from '@/components/hr/generate-document-modal';
import {
    FileText,
    TrendingUp,
    FileCheck,
    Plus,
    MoreVertical,
    Edit,
    Copy,
    Archive,
    Trash2,
    FileDown,
    Clock,
    CheckCircle,
    Search,
    RotateCw,
} from 'lucide-react';

// ============================================================================
// Type Definitions
// ============================================================================

interface Template {
    id: number;
    name: string;
    category: string;
    description: string;
    version: string;
    variables: Variable[];
    usage_count: number;
    status: 'active' | 'draft' | 'archived';
    last_modified: string;
    modified_by: string;
}

interface Variable {
    name: string;
    label: string;
    type: 'text' | 'date' | 'number' | 'select';
    required: boolean;
    default_value?: string;
    options?: string[];
}

interface TemplateStats {
    total_templates: number;
    active_templates: number;
    most_used_template: {
        id: number;
        name: string;
        usage_count: number;
    };
    generated_this_month: number;
}

interface TemplatesIndexProps {
    templates?: Template[];
    stats?: TemplateStats;
}

// ============================================================================
// Mock Data
// ============================================================================

const mockTemplates: Template[] = [
    {
        id: 1,
        name: 'Certificate of Employment',
        category: 'employment',
        description: 'Standard COE template with employment history',
        version: 'v1.2',
        variables: [
            { name: 'employee_name', label: 'Employee Name', type: 'text', required: true },
            { name: 'position', label: 'Position', type: 'text', required: true },
            { name: 'date_hired', label: 'Date Hired', type: 'date', required: true },
            { name: 'purpose', label: 'Purpose', type: 'text', required: false },
        ],
        usage_count: 156,
        status: 'active',
        last_modified: '2024-03-15',
        modified_by: 'HR Manager',
    },
    {
        id: 2,
        name: 'BIR Form 2316',
        category: 'government',
        description: 'Annual income tax return form',
        version: 'v2.0',
        variables: [
            { name: 'employee_name', label: 'Employee Name', type: 'text', required: true },
            { name: 'tin', label: 'TIN', type: 'text', required: true },
            { name: 'tax_year', label: 'Tax Year', type: 'number', required: true },
            { name: 'total_compensation', label: 'Total Compensation', type: 'number', required: true },
        ],
        usage_count: 89,
        status: 'active',
        last_modified: '2024-01-10',
        modified_by: 'Payroll Staff',
    },
    {
        id: 3,
        name: 'Payslip Template',
        category: 'payroll',
        description: 'Monthly salary statement with deductions',
        version: 'v1.5',
        variables: [
            { name: 'employee_name', label: 'Employee Name', type: 'text', required: true },
            { name: 'employee_number', label: 'Employee Number', type: 'text', required: true },
            { name: 'period_from', label: 'Period From', type: 'date', required: true },
            { name: 'period_to', label: 'Period To', type: 'date', required: true },
            { name: 'basic_salary', label: 'Basic Salary', type: 'number', required: true },
        ],
        usage_count: 234,
        status: 'active',
        last_modified: '2024-02-20',
        modified_by: 'Payroll Staff',
    },
    {
        id: 4,
        name: 'Employment Contract',
        category: 'contracts',
        description: 'Standard employment contract for regular employees',
        version: 'v1.0',
        variables: [
            { name: 'employee_name', label: 'Employee Name', type: 'text', required: true },
            { name: 'position', label: 'Position', type: 'text', required: true },
            { name: 'start_date', label: 'Start Date', type: 'date', required: true },
            { name: 'salary', label: 'Monthly Salary', type: 'number', required: true },
            { name: 'department', label: 'Department', type: 'text', required: true },
        ],
        usage_count: 45,
        status: 'active',
        last_modified: '2024-01-05',
        modified_by: 'HR Manager',
    },
    {
        id: 5,
        name: 'Clearance Form',
        category: 'separation',
        description: 'Employee clearance form for resignations',
        version: 'v1.1',
        variables: [
            { name: 'employee_name', label: 'Employee Name', type: 'text', required: true },
            { name: 'employee_number', label: 'Employee Number', type: 'text', required: true },
            { name: 'last_working_day', label: 'Last Working Day', type: 'date', required: true },
            { name: 'department', label: 'Department', type: 'text', required: true },
        ],
        usage_count: 23,
        status: 'active',
        last_modified: '2024-03-01',
        modified_by: 'HR Staff',
    },
    {
        id: 6,
        name: 'Memo Template',
        category: 'communication',
        description: 'Internal memorandum template',
        version: 'v1.0',
        variables: [
            { name: 'recipient', label: 'Recipient', type: 'text', required: true },
            { name: 'subject', label: 'Subject', type: 'text', required: true },
            { name: 'date', label: 'Date', type: 'date', required: true },
            { name: 'message', label: 'Message', type: 'text', required: true },
        ],
        usage_count: 67,
        status: 'active',
        last_modified: '2024-02-15',
        modified_by: 'HR Manager',
    },
    {
        id: 7,
        name: 'Warning Letter Draft',
        category: 'performance',
        description: 'Template for disciplinary warning letters',
        version: 'v1.0',
        variables: [
            { name: 'employee_name', label: 'Employee Name', type: 'text', required: true },
            { name: 'violation', label: 'Violation', type: 'text', required: true },
            { name: 'date', label: 'Date', type: 'date', required: true },
        ],
        usage_count: 12,
        status: 'draft',
        last_modified: '2024-03-10',
        modified_by: 'HR Manager',
    },
];

const mockStats: TemplateStats = {
    total_templates: 7,
    active_templates: 6,
    most_used_template: {
        id: 3,
        name: 'Payslip Template',
        usage_count: 234,
    },
    generated_this_month: 89,
};

// ============================================================================
// Main Component
// ============================================================================

export default function TemplatesIndex({ templates: initialTemplates, stats: initialStats }: TemplatesIndexProps) {
    const { toast } = useToast();

    // State management
    const [templates, setTemplates] = useState<Template[]>(initialTemplates || mockTemplates);
    const [stats, setStats] = useState<TemplateStats>(initialStats || mockStats);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [templateToDelete, setTemplateToDelete] = useState<Template | null>(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [templateToEdit, setTemplateToEdit] = useState<Template | null>(null);
    const [isGenerateModalOpen, setIsGenerateModalOpen] = useState(false);
    const [templateToGenerate, setTemplateToGenerate] = useState<Template | null>(null);

    // Fetch templates from API
    const fetchTemplates = async () => {
        setLoading(true);
        try {
            const response = await fetch('/hr/documents/templates', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch templates');
            }

            const result = await response.json();
            setTemplates(result.data || mockTemplates);
            setStats(result.meta || mockStats);
            setLoading(false);
        } catch (err) {
            console.error('Error fetching templates:', err);
            // Fallback to mock data on error
            setTemplates(mockTemplates);
            setStats(mockStats);
            setLoading(false);
        }
    };

    // Initialize data on component mount
    useEffect(() => {
        fetchTemplates();
    }, []);

    // Filtered templates
    const filteredTemplates = templates.filter((template) => {
        if (categoryFilter !== 'all' && template.category !== categoryFilter) return false;
        if (statusFilter !== 'all' && template.status !== statusFilter) return false;
        if (searchTerm) {
            const query = searchTerm.toLowerCase();
            return (
                template.name.toLowerCase().includes(query) ||
                template.description.toLowerCase().includes(query)
            );
        }
        return true;
    });

    // Actions
    const handleEdit = (templateId: number) => {
        const template = templates.find(t => t.id === templateId);
        if (template) {
            setTemplateToEdit(template);
            setIsCreateModalOpen(true);
        }
    };

    const handleDuplicate = async (templateId: number) => {
        try {
            const template = templates.find(t => t.id === templateId);
            if (!template) return;

            const response = await fetch(`/hr/documents/templates/${templateId}/duplicate`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to duplicate template');
            }

            toast({
                title: 'Template Duplicated',
                description: 'A copy of the template has been created.',
            });
            fetchTemplates();
        } catch (err) {
            console.error('Error duplicating template:', err);
            toast({
                variant: 'destructive',
                title: 'Duplication Failed',
                description: 'Failed to duplicate template. Please try again.',
            });
        }
    };

    const handleArchive = async (templateId: number) => {
        try {
            const template = templates.find(t => t.id === templateId);
            if (!template) return;

            const newStatus = template.status === 'archived' ? 'active' : 'archived';
            
            const response = await fetch(`/hr/documents/templates/${templateId}`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ status: newStatus }),
            });

            if (!response.ok) {
                throw new Error('Failed to update template');
            }

            toast({
                title: newStatus === 'archived' ? 'Template Archived' : 'Template Restored',
                description: `The template has been ${newStatus === 'archived' ? 'archived' : 'restored'} successfully.`,
            });
            fetchTemplates();
        } catch (err) {
            console.error('Error updating template:', err);
            toast({
                variant: 'destructive',
                title: 'Update Failed',
                description: 'Failed to update template. Please try again.',
            });
        }
    };

    const handleDelete = async () => {
        if (!templateToDelete) return;

        try {
            const response = await fetch(`/hr/documents/templates/${templateToDelete.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to delete template');
            }

            toast({
                title: 'Template Deleted',
                description: 'The template has been deleted successfully.',
            });
            setTemplateToDelete(null);
            fetchTemplates();
        } catch (err) {
            console.error('Error deleting template:', err);
            toast({
                variant: 'destructive',
                title: 'Deletion Failed',
                description: 'Failed to delete template. Please try again.',
            });
        }
    };

    const handleGenerate = (templateId: number) => {
        const template = templates.find(t => t.id === templateId);
        if (template) {
            setTemplateToGenerate(template);
            setIsGenerateModalOpen(true);
        }
    };

    // Helper functions
    const getCategoryBadgeColor = (category: string): string => {
        switch (category) {
            case 'personal': return 'bg-blue-100 text-blue-800';
            case 'educational': return 'bg-purple-100 text-purple-800';
            case 'employment': return 'bg-cyan-100 text-cyan-800';
            case 'medical': return 'bg-red-100 text-red-800';
            case 'contracts': return 'bg-indigo-100 text-indigo-800';
            case 'benefits': return 'bg-emerald-100 text-emerald-800';
            case 'performance': return 'bg-amber-100 text-amber-800';
            case 'separation': return 'bg-gray-100 text-gray-800';
            case 'government': return 'bg-green-100 text-green-800';
            case 'payroll': return 'bg-teal-100 text-teal-800';
            case 'communication': return 'bg-pink-100 text-pink-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return (
                    <Badge className="bg-green-100 text-green-800">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Active
                    </Badge>
                );
            case 'draft':
                return (
                    <Badge className="bg-yellow-100 text-yellow-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Draft
                    </Badge>
                );
            case 'archived':
                return (
                    <Badge className="bg-gray-100 text-gray-800">
                        <Archive className="h-3 w-3 mr-1" />
                        Archived
                    </Badge>
                );
            default:
                return null;
        }
    };

    const formatDate = (dateString: string): string => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    return (
        <AppLayout>
            <Head title="Document Templates" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Document Templates</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Create and manage document templates for automated generation
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={fetchTemplates} disabled={loading}>
                            <RotateCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button onClick={() => {
                            setTemplateToEdit(null);
                            setIsCreateModalOpen(true);
                        }}>
                            <Plus className="h-4 w-4 mr-2" />
                            Create Template
                        </Button>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Templates</CardTitle>
                            <FileText className="h-5 w-5 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.active_templates}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.total_templates} total templates
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Most Used Template</CardTitle>
                            <TrendingUp className="h-5 w-5 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold truncate">{stats.most_used_template.name}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.most_used_template.usage_count} generations
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Generated This Month</CardTitle>
                            <FileCheck className="h-5 w-5 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.generated_this_month}</div>
                            <p className="text-xs text-muted-foreground">
                                Documents from templates
                            </p>
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
                                placeholder="Search templates by name or description..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9"
                            />
                        </div>

                        {/* Filters Row */}
                        <div className="flex flex-wrap gap-4">
                            {/* Category Filter */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium">Category:</span>
                                <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                                    <SelectTrigger className="w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        <SelectItem value="employment">Employment</SelectItem>
                                        <SelectItem value="government">Government</SelectItem>
                                        <SelectItem value="payroll">Payroll</SelectItem>
                                        <SelectItem value="contracts">Contracts</SelectItem>
                                        <SelectItem value="separation">Separation</SelectItem>
                                        <SelectItem value="communication">Communication</SelectItem>
                                        <SelectItem value="performance">Performance</SelectItem>
                                        <SelectItem value="benefits">Benefits</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Status Filter */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium">Status:</span>
                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="archived">Archived</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Clear Filters */}
                            {(searchTerm || categoryFilter !== 'all' || statusFilter !== 'all') && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setSearchTerm('');
                                        setCategoryFilter('all');
                                        setStatusFilter('all');
                                    }}
                                >
                                    Clear Filters
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Templates Table */}
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
                ) : filteredTemplates.length === 0 ? (
                    <Card className="p-12">
                        <div className="flex flex-col items-center justify-center text-center">
                            <div className="rounded-full bg-muted p-4 mb-4">
                                <FileText className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold mb-2">No Templates Found</h3>
                            <p className="text-sm text-muted-foreground mb-4 max-w-sm">
                                {searchTerm || categoryFilter !== 'all' || statusFilter !== 'all'
                                    ? 'No templates match your filters. Try adjusting your search criteria.'
                                    : 'No templates have been created yet. Create your first template to get started.'}
                            </p>
                            <Button>
                                <Plus className="h-4 w-4 mr-2" />
                                Create First Template
                            </Button>
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {filteredTemplates.map((template) => (
                            <Card key={template.id} className="p-4 hover:shadow-md transition-shadow">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start space-x-4 flex-1">
                                        <div className="rounded-lg bg-muted p-3">
                                            <FileText className="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h4 className="font-semibold text-lg">{template.name}</h4>
                                                <Badge className={getCategoryBadgeColor(template.category)}>
                                                    {template.category}
                                                </Badge>
                                                {getStatusBadge(template.status)}
                                                <Badge variant="outline">{template.version}</Badge>
                                            </div>
                                            <p className="text-sm text-muted-foreground mb-2">
                                                {template.description}
                                            </p>
                                            <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                                <span>{template.variables.length} variables</span>
                                                <span>•</span>
                                                <span>{template.usage_count} times used</span>
                                                <span>•</span>
                                                <span>Modified {formatDate(template.last_modified)} by {template.modified_by}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 ml-4">
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={() => handleGenerate(template.id)}
                                        >
                                            <FileDown className="h-4 w-4 mr-2" />
                                            Generate
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem onClick={() => handleEdit(template.id)}>
                                                    <Edit className="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDuplicate(template.id)}>
                                                    <Copy className="h-4 w-4 mr-2" />
                                                    Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleArchive(template.id)}>
                                                    <Archive className="h-4 w-4 mr-2" />
                                                    {template.status === 'archived' ? 'Unarchive' : 'Archive'}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    className="text-red-600"
                                                    onClick={() => setTemplateToDelete(template)}
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={!!templateToDelete} onOpenChange={(open) => !open && setTemplateToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Template?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{templateToDelete?.name}"? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={() => handleDelete()} className="bg-destructive">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Create/Edit Template Modal */}
            <CreateTemplateModal
                open={isCreateModalOpen}
                onClose={() => {
                    setIsCreateModalOpen(false);
                    setTemplateToEdit(null);
                }}
                onSuccess={fetchTemplates}
                template={templateToEdit || undefined}
            />

            {/* Generate Document Modal */}
            {templateToGenerate && (
                <GenerateDocumentModal
                    open={isGenerateModalOpen}
                    onClose={() => {
                        setIsGenerateModalOpen(false);
                        setTemplateToGenerate(null);
                    }}
                    template={templateToGenerate}
                    employees={[]} // TODO: Pass actual employees list from props
                />
            )}
        </AppLayout>
    );
}
