import React, { useState, useMemo, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import {
    AlertCircle,
    CheckCircle,
    Filter,
    ChevronDown,
    ChevronUp,
    Download,
    Flag,
    Check,
} from 'lucide-react';
import { useDebouncedCallback } from 'use-debounce';

// ============================================================================
// Type Definitions
// ============================================================================

interface ClearanceItem {
    id: number;
    item_name: string;
    description: string;
    category: string;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    assigned_to: string | null;
    assigned_to_id: number | null;
    approved_by: string | null;
    approved_at: string | null;
    due_date: string | null;
    has_issues: boolean;
    issue_description: string | null;
    resolution_notes: string | null;
    proof_file_path: string | null;
    is_overdue: boolean;
}

interface CaseData {
    id: number;
    case_number: string;
    employee: {
        name: string;
        employee_number: string;
        department: string;
    };
    status: string;
    separation_type: string;
}

interface ItemsByCategory {
    [category: string]: ClearanceItem[];
}

interface Statistics {
    total: number;
    pending: number;
    approved: number;
    issues: number;
}

interface ClearanceApprovalProps {
    itemsByCategory: ItemsByCategory;
    case: CaseData;
    statistics: Statistics;
    categoryLabels: Record<string, string>;
    priorities: Record<string, string>;
    currentUserId: number;
}

interface FilterState {
    priority: string;
    status: string;
    search: string;
}

// ============================================================================
// Helper Functions
// ============================================================================

const getPriorityColor = (priority: string): string => {
    switch (priority) {
        case 'critical':
            return 'bg-red-100 text-red-800 border-l-4 border-red-500';
        case 'high':
            return 'bg-orange-100 text-orange-800 border-l-4 border-orange-500';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800 border-l-4 border-yellow-500';
        case 'low':
            return 'bg-green-100 text-green-800 border-l-4 border-green-500';
        default:
            return 'bg-gray-100 text-gray-800 border-l-4 border-gray-500';
    }
};

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-blue-100 text-blue-800';
        case 'issues':
            return 'bg-red-100 text-red-800';
        case 'waived':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getCategoryColor = (category: string): string => {
    const colors: Record<string, string> = {
        'hr': 'bg-blue-50',
        'it': 'bg-green-50',
        'finance': 'bg-amber-50',
        'admin': 'bg-purple-50',
        'operations': 'bg-cyan-50',
        'security': 'bg-red-50',
        'facilities': 'bg-yellow-50',
    };
    return colors[category] || 'bg-gray-50';
};

// ============================================================================
// Approval Modal Component
// ============================================================================

interface ApprovalModalProps {
    item: ClearanceItem;
    caseId: number;
    isOpen: boolean;
    onClose: () => void;
}

function ApprovalModal({ item, isOpen, onClose }: Omit<ApprovalModalProps, 'caseId'>) {
    const [notes, setNotes] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const formData = new FormData();
        if (notes) formData.append('notes', notes);
        if (file) formData.append('proof_file', file);

        router.post(`/hr/offboarding/clearance/${item.id}/approve`, formData, {
            onFinish: () => {
                setIsSubmitting(false);
                onClose();
                setNotes('');
                setFile(null);
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-lg max-w-md w-full shadow-lg">
                <div className="p-6 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900">Approve Clearance</h3>
                    <p className="text-sm text-gray-600 mt-1">{item.item_name}</p>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Notes (Optional)
                        </label>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Add any approval notes..."
                            maxLength={1000}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            rows={3}
                        />
                        <p className="text-xs text-gray-500 mt-1">{notes.length}/1000</p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Proof File (Optional)
                        </label>
                        <div className="flex items-center gap-2">
                            <Input
                                type="file"
                                onChange={(e) => setFile(e.target.files?.[0] || null)}
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                className="flex-1"
                            />
                            {file && <CheckCircle size={20} className="text-green-600" />}
                        </div>
                        <p className="text-xs text-gray-500 mt-1">PDF, images, or documents (max 10MB)</p>
                    </div>

                    <div className="flex gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={isSubmitting}
                            className="flex-1"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            className="flex-1 bg-green-600 hover:bg-green-700 flex items-center gap-2"
                        >
                            <Check size={18} />
                            {isSubmitting ? 'Approving...' : 'Approve'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ============================================================================
// Issue Modal Component
// ============================================================================

interface IssueModalProps {
    item: ClearanceItem;
    isOpen: boolean;
    onClose: () => void;
}

function IssueModal({ item, isOpen, onClose }: IssueModalProps) {
    const [issueDescription, setIssueDescription] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!issueDescription.trim()) return;

        setIsSubmitting(true);

        // @ts-expect-error - Inertia router type compatibility
        router.post(`/hr/offboarding/clearance/${item.id}/issue`, {
            issue_description: issueDescription,
        } as unknown as Record<string, unknown>, {
            onFinish: () => {
                setIsSubmitting(false);
                onClose();
                setIssueDescription('');
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-lg max-w-md w-full shadow-lg">
                <div className="p-6 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900">Report Issue</h3>
                    <p className="text-sm text-gray-600 mt-1">{item.item_name}</p>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Issue Description <span className="text-red-600">*</span>
                        </label>
                        <textarea
                            value={issueDescription}
                            onChange={(e) => setIssueDescription(e.target.value)}
                            placeholder="Describe the issue that needs to be resolved..."
                            maxLength={1000}
                            required
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                            rows={4}
                        />
                        <p className="text-xs text-gray-500 mt-1">{issueDescription.length}/1000</p>
                    </div>

                    <div className="flex gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={isSubmitting}
                            className="flex-1"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={isSubmitting || !issueDescription.trim()}
                            className="flex-1 bg-red-600 hover:bg-red-700 flex items-center gap-2"
                        >
                            <Flag size={18} />
                            {isSubmitting ? 'Reporting...' : 'Report Issue'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ============================================================================
// Clearance Item Card Component
// ============================================================================

interface ClearanceItemCardProps {
    item: ClearanceItem;
    caseId: number;
    onApproveClick: () => void;
    onIssueClick: () => void;
}

function ClearanceItemCard({ item, onApproveClick, onIssueClick }: Omit<ClearanceItemCardProps, 'caseId'>) {
    return (
        <div className={`p-4 rounded-lg border border-gray-200 ${getPriorityColor(item.priority)}`}>
            <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                    <h4 className="font-semibold text-gray-900">{item.item_name}</h4>
                    {item.description && (
                        <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                    )}
                </div>
                <Badge className={getStatusColor(item.status)}>
                    {item.status_label}
                </Badge>
            </div>

            <div className="grid grid-cols-2 gap-2 text-sm mb-3">
                <div>
                    <span className="text-gray-600">Assigned to:</span>
                    <p className="font-medium">{item.assigned_to || 'Unassigned'}</p>
                </div>
                <div>
                    <span className="text-gray-600">Due Date:</span>
                    <p className={`font-medium ${item.is_overdue ? 'text-red-600' : ''}`}>
                        {item.due_date || 'No due date'}
                        {item.is_overdue && ' ⚠️'}
                    </p>
                </div>
            </div>

            {item.has_issues && (
                <div className="bg-red-50 border border-red-200 rounded p-2 mb-3">
                    <p className="text-sm font-medium text-red-800">Issues Reported:</p>
                    <p className="text-sm text-red-700 mt-1">{item.issue_description}</p>
                </div>
            )}

            {item.approved_at && (
                <div className="bg-green-50 border border-green-200 rounded p-2 mb-3">
                    <p className="text-sm text-green-800">
                        ✓ Approved by {item.approved_by} on {item.approved_at}
                    </p>
                </div>
            )}

            {item.status === 'pending' && (
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        onClick={onApproveClick}
                        className="flex-1 bg-green-600 hover:bg-green-700 flex items-center gap-2"
                    >
                        <Check size={16} />
                        Approve
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={onIssueClick}
                        className="flex-1 flex items-center gap-2 border-red-300 text-red-700 hover:bg-red-50"
                    >
                        <Flag size={16} />
                        Issue
                    </Button>
                </div>
            )}

            {item.proof_file_path && (
                <div className="mt-3 pt-3 border-t border-gray-200">
                    <Button
                        size="sm"
                        variant="outline"
                        className="w-full flex items-center gap-2"
                        onClick={() => window.open(`/storage/${item.proof_file_path}`)}
                    >
                        <Download size={16} />
                        Download Proof
                    </Button>
                </div>
            )}
        </div>
    );
}

// ============================================================================
// Main Component
// ============================================================================

export default function ClearanceApprovalIndex({
    itemsByCategory,
    case: caseData,
    statistics,
    categoryLabels,
}: Omit<ClearanceApprovalProps, 'priorities' | 'currentUserId'>) {
    const [filters, setFilters] = useState<FilterState>({
        priority: 'all',
        status: 'pending',
        search: '',
    });
    const [expandedCategories, setExpandedCategories] = useState<Set<string>>(
        new Set(Object.keys(itemsByCategory))
    );
    const [selectedItems, setSelectedItems] = useState<Set<number>>(new Set());
    const [approvalModalItem, setApprovalModalItem] = useState<ClearanceItem | null>(null);
    const [issueModalItem, setIssueModalItem] = useState<ClearanceItem | null>(null);

    const debouncedSearch = useDebouncedCallback((searchTerm: string) => {
        setFilters((prev) => ({
            ...prev,
            search: searchTerm,
        }));
    }, 300);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        debouncedSearch(e.target.value);
    };

    const toggleCategory = (category: string) => {
        const newCategories = new Set(expandedCategories);
        if (newCategories.has(category)) {
            newCategories.delete(category);
        } else {
            newCategories.add(category);
        }
        setExpandedCategories(newCategories);
    };

    const toggleItemSelection = (itemId: number) => {
        const newSelected = new Set(selectedItems);
        if (newSelected.has(itemId)) {
            newSelected.delete(itemId);
        } else {
            newSelected.add(itemId);
        }
        setSelectedItems(newSelected);
    };

    const handleSelectAll = (items: ClearanceItem[]) => {
        const pendingItems = items
            .filter((item) => item.status === 'pending')
            .map((item) => item.id);

        if (selectedItems.size === pendingItems.length) {
            setSelectedItems(new Set());
        } else {
            setSelectedItems(new Set(pendingItems));
        }
    };

    const handleBulkApprove = useCallback(async () => {
        if (selectedItems.size === 0) return;

        if (!confirm(`Approve ${selectedItems.size} clearance items?`)) return;

        router.post(`/hr/offboarding/clearance/bulk-approve`, {
            item_ids: Array.from(selectedItems),
        }, {
            onFinish: () => {
                setSelectedItems(new Set());
            },
        });
    }, [selectedItems]);

    // Filter items
    const filteredItemsByCategory = useMemo(() => {
        const filtered: ItemsByCategory = {};

        Object.entries(itemsByCategory).forEach(([category, items]) => {
            let categoryItems = items;

            // Filter by priority
            if (filters.priority !== 'all') {
                categoryItems = categoryItems.filter((item) => item.priority === filters.priority);
            }

            // Filter by status
            if (filters.status !== 'all') {
                categoryItems = categoryItems.filter((item) => item.status === filters.status);
            }

            // Filter by search
            if (filters.search) {
                const searchLower = filters.search.toLowerCase();
                categoryItems = categoryItems.filter((item) =>
                    item.item_name.toLowerCase().includes(searchLower) ||
                    item.description?.toLowerCase().includes(searchLower)
                );
            }

            if (categoryItems.length > 0) {
                filtered[category] = categoryItems;
            }
        });

        return filtered;
    }, [itemsByCategory, filters]);

    return (
        <AppLayout>
            <Head title="Clearance Approvals" />

            <div className="max-w-6xl mx-auto py-8 px-4 space-y-8">
                {/* Case Context */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <div className="flex items-start justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Clearance Approvals</h1>
                            <p className="text-gray-600 mt-2">
                                Case #{caseData.case_number} - {caseData.employee.name}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">
                                {caseData.employee.department} • Employee: {caseData.employee.employee_number}
                            </p>
                        </div>
                        <Badge className={getStatusColor(caseData.status)}>
                            {caseData.status}
                        </Badge>
                    </div>
                </div>

                {/* Statistics */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-3xl font-bold text-gray-900">{statistics.total}</p>
                                <p className="text-sm text-gray-600 mt-2">Total Items</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-3xl font-bold text-blue-600">{statistics.pending}</p>
                                <p className="text-sm text-gray-600 mt-2">Pending</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-3xl font-bold text-green-600">{statistics.approved}</p>
                                <p className="text-sm text-gray-600 mt-2">Approved</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-3xl font-bold text-red-600">{statistics.issues}</p>
                                <p className="text-sm text-gray-600 mt-2">Issues</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter size={20} />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Search
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Search clearance items..."
                                    onChange={handleSearchChange}
                                    className="w-full"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Priority
                                </label>
                                <Select
                                    value={filters.priority}
                                    onValueChange={(value) =>
                                        setFilters((prev) => ({ ...prev, priority: value }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Priorities</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="low">Low</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <Select
                                    value={filters.status}
                                    onValueChange={(value) =>
                                        setFilters((prev) => ({ ...prev, status: value }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="approved">Approved</SelectItem>
                                        <SelectItem value="issues">Issues</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Actions */}
                {selectedItems.size > 0 && (
                    <div className="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <AlertCircle className="text-blue-600" size={20} />
                        <p className="text-sm text-blue-900">
                            {selectedItems.size} item{selectedItems.size !== 1 ? 's' : ''} selected
                        </p>
                        <div className="flex-1" />
                        <Button
                            onClick={handleBulkApprove}
                            className="bg-green-600 hover:bg-green-700 flex items-center gap-2"
                        >
                            <Check size={18} />
                            Approve Selected
                        </Button>
                    </div>
                )}

                {/* Clearance Items by Category */}
                <div className="space-y-6">
                    {Object.entries(filteredItemsByCategory).length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <AlertCircle className="mx-auto text-gray-400 mb-4" size={48} />
                                <h3 className="text-lg font-medium text-gray-900">No clearance items</h3>
                                <p className="text-gray-600 mt-2">No items match your current filters</p>
                            </CardContent>
                        </Card>
                    ) : (
                        Object.entries(filteredItemsByCategory).map(([category, items]) => (
                            <div key={category} className={`border rounded-lg overflow-hidden ${getCategoryColor(category)}`}>
                                {/* Category Header */}
                                <button
                                    onClick={() => toggleCategory(category)}
                                    className="w-full flex items-center justify-between p-4 hover:bg-black hover:bg-opacity-5 transition-colors"
                                >
                                    <div className="flex items-center gap-3">
                                        {expandedCategories.has(category) ? (
                                            <ChevronUp className="text-gray-600" size={20} />
                                        ) : (
                                            <ChevronDown className="text-gray-600" size={20} />
                                        )}
                                        <h3 className="font-semibold text-gray-900">
                                            {categoryLabels[category] || category}
                                        </h3>
                                        <Badge variant="secondary">{items.length}</Badge>
                                    </div>
                                </button>

                                {expandedCategories.has(category) && (
                                    <div className="border-t border-black border-opacity-10 p-4 space-y-4">
                                        {/* Select All for This Category */}
                                        {items.some((item) => item.status === 'pending') && (
                                            <label className="flex items-center gap-2 cursor-pointer p-2 hover:bg-black hover:bg-opacity-5 rounded">
                                                <input
                                                    type="checkbox"
                                                    checked={items
                                                        .filter((item) => item.status === 'pending')
                                                        .every((item) => selectedItems.has(item.id))}
                                                    onChange={() => handleSelectAll(items)}
                                                    className="w-4 h-4 rounded"
                                                />
                                                <span className="text-sm font-medium text-gray-700">
                                                    Select all pending in this category
                                                </span>
                                            </label>
                                        )}

                                        {/* Items */}
                                        <div className="space-y-3">
                                            {items.map((item) => (
                                                <div key={item.id} className="flex gap-3 items-start">
                                                    {item.status === 'pending' && (
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedItems.has(item.id)}
                                                            onChange={() => toggleItemSelection(item.id)}
                                                            className="w-4 h-4 rounded mt-4"
                                                        />
                                                    )}
                                                    <div className="flex-1">
                                                        <ClearanceItemCard
                                                            item={item}
                                                            onApproveClick={() => setApprovalModalItem(item)}
                                                            onIssueClick={() => setIssueModalItem(item)}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Modals */}
            {approvalModalItem && (
                <ApprovalModal
                    item={approvalModalItem}
                    isOpen={!!approvalModalItem}
                    onClose={() => setApprovalModalItem(null)}
                />
            )}

            {issueModalItem && (
                <IssueModal
                    item={issueModalItem}
                    isOpen={!!issueModalItem}
                    onClose={() => setIssueModalItem(null)}
                />
            )}
        </AppLayout>
    );
}
