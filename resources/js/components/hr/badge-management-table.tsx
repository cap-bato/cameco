import { useState, useMemo } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    MoreVertical, 
    Copy, 
    Eye, 
    Repeat2, 
    Trash2, 
    AlertTriangle, 
    History, 
    Clock, 
    Printer,
    ChevronDown,
    X
} from 'lucide-react';

interface Badge {
    id: string;
    card_uid: string;
    employee_id: string;
    employee_name: string;
    employee_photo?: string;
    department: string;
    position: string;
    card_type: 'mifare' | 'desfire' | 'em4100';
    issued_at: string;
    issued_by: string;
    expires_at: string | null;
    is_active: boolean;
    last_used_at: string | null;
    usage_count: number;
    status: 'active' | 'inactive' | 'lost' | 'stolen' | 'expired' | 'replaced';
    deactivation_reason?: string;
}

interface BadgesData {
    data: Badge[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface Props {
    badges: BadgesData;
    status?: string;
    onReplace?: (badge: Badge) => void;
}

type SortField = 'employee_name' | 'employee_id' | 'department' | 'issued_at' | 'expires_at' | 'last_used_at' | 'usage_count';
type SortOrder = 'asc' | 'desc';

interface SortableHeaderProps {
    field: SortField;
    children: React.ReactNode;
    activeSort: SortField | null;
    sortOrder: SortOrder;
    onSort: (field: SortField) => void;
}

function SortableHeader({ field, children, activeSort, sortOrder, onSort }: SortableHeaderProps) {
    return (
        <TableHead 
            className="cursor-pointer select-none hover:bg-muted-foreground/10"
            onClick={() => onSort(field)}
        >
            <div className="flex items-center gap-2">
                {children}
                {activeSort === field && (
                    <ChevronDown 
                        className={`h-4 w-4 transition-transform ${sortOrder === 'asc' ? 'rotate-180' : ''}`}
                    />
                )}
            </div>
        </TableHead>
    );
}

export function BadgeManagementTable({ badges, onReplace }: Props) {
    const [search, setSearch] = useState('');
    const [filterDepartment, setFilterDepartment] = useState('');
    const [filterCardType, setFilterCardType] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [filterExpiration, setFilterExpiration] = useState(''); // 'expired', 'expiring-soon', 'valid'
    const [dateRangeStart, setDateRangeStart] = useState('');
    const [dateRangeEnd, setDateRangeEnd] = useState('');
    const [perPage, setPerPage] = useState('25');
    const [sortField, setSortField] = useState<SortField>('issued_at');
    const [sortOrder, setSortOrder] = useState<SortOrder>('desc');
    const [activeSort, setActiveSort] = useState<SortField | null>('issued_at');

    const getCardTypeBadge = (cardType: string) => {
        const types: Record<string, string> = {
            'mifare': 'Mifare',
            'desfire': 'DESFire',
            'em4100': 'EM4100',
        };
        return <Badge variant="outline">{types[cardType] || cardType}</Badge>;
    };

    const formatDate = (date: string | null) => {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const formatRelativeTime = (timestamp: string | null) => {
        if (!timestamp) return 'Never';
        const now = new Date();
        const date = new Date(timestamp);
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return formatDate(timestamp);
    };

    const getDaysUntilExpiration = (expiresAt: string | null) => {
        if (!expiresAt) return null;
        const now = new Date();
        const expiry = new Date(expiresAt);
        const diffMs = expiry.getTime() - now.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        return diffDays;
    };

    const isExpirationWarning = (daysLeft: number | null) => {
        return daysLeft !== null && daysLeft <= 30 && daysLeft > 0;
    };

    const isExpired = (daysLeft: number | null) => {
        return daysLeft !== null && daysLeft <= 0;
    };

    const getStatusIndicator = (badge: Badge) => {
        const daysLeft = getDaysUntilExpiration(badge.expires_at);
        const showExpired = isExpired(daysLeft);
        const showWarning = isExpirationWarning(daysLeft);

        let color = 'bg-green-500'; // active
        if (!badge.is_active) color = 'bg-gray-400'; // inactive
        if (showExpired) color = 'bg-red-500'; // expired
        if (badge.status === 'lost' || badge.status === 'stolen') color = 'bg-red-700'; // lost/stolen
        if (showWarning) color = 'bg-amber-500'; // expiring soon

        return (
            <div
                className={`h-3 w-3 rounded-full ${color}`}
                title={badge.status.charAt(0).toUpperCase() + badge.status.slice(1)}
            />
        );
    };

    // Subtask 1.2.2: Filter badges based on all criteria
    const filteredBadges = useMemo(() => {
        return badges.data.filter((badge) => {
            // Search filter: employee name, ID, card UID
            if (search) {
                const searchLower = search.toLowerCase();
                const matchesSearch = 
                    badge.employee_name.toLowerCase().includes(searchLower) ||
                    badge.employee_id.toLowerCase().includes(searchLower) ||
                    badge.card_uid.toLowerCase().includes(searchLower);
                if (!matchesSearch) return false;
            }

            // Department filter
            if (filterDepartment && badge.department.toLowerCase() !== filterDepartment.toLowerCase()) {
                return false;
            }

            // Card type filter
            if (filterCardType && badge.card_type !== filterCardType) {
                return false;
            }

            // Status filter
            if (filterStatus && badge.status !== filterStatus) {
                return false;
            }

            // Expiration filter
            if (filterExpiration) {
                const daysLeft = getDaysUntilExpiration(badge.expires_at);
                if (filterExpiration === 'expired' && !isExpired(daysLeft)) return false;
                if (filterExpiration === 'expiring-soon' && !isExpirationWarning(daysLeft)) return false;
                if (filterExpiration === 'valid' && (isExpired(daysLeft) || isExpirationWarning(daysLeft))) return false;
            }

            // Date range filter (issued date)
            if (dateRangeStart || dateRangeEnd) {
                const issuedDate = new Date(badge.issued_at);
                if (dateRangeStart && issuedDate < new Date(dateRangeStart)) return false;
                if (dateRangeEnd) {
                    const endDate = new Date(dateRangeEnd);
                    endDate.setHours(23, 59, 59, 999);
                    if (issuedDate > endDate) return false;
                }
            }

            return true;
        });
    }, [badges.data, search, filterDepartment, filterCardType, filterStatus, filterExpiration, dateRangeStart, dateRangeEnd]);

    // Subtask 1.2.1: Sort badges
    const sortedBadges = useMemo(() => {
        const sorted = [...filteredBadges].sort((a, b) => {
            let aVal: string | number | null = a[sortField];
            let bVal: string | number | null = b[sortField];

            if (aVal === null || aVal === undefined) aVal = '';
            if (bVal === null || bVal === undefined) bVal = '';

            if (typeof aVal === 'string' && typeof bVal === 'string') {
                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
            }

            if (aVal < bVal) return sortOrder === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortOrder === 'asc' ? 1 : -1;
            return 0;
        });

        return sorted;
    }, [filteredBadges, sortField, sortOrder]);

    // Pagination
    const itemsPerPage = parseInt(perPage);
    const totalPages = Math.ceil(sortedBadges.length / itemsPerPage);
    const paginatedBadges = sortedBadges.slice(0, itemsPerPage);

    const handleSort = (field: SortField) => {
        if (activeSort === field) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortOrder('desc');
            setActiveSort(field);
        }
    };

    const hasActiveFilters = search || filterDepartment || filterCardType || filterStatus || filterExpiration || dateRangeStart || dateRangeEnd;

    const clearAllFilters = () => {
        setSearch('');
        setFilterDepartment('');
        setFilterCardType('');
        setFilterStatus('');
        setFilterExpiration('');
        setDateRangeStart('');
        setDateRangeEnd('');
    };

    if (!badges.data || badges.data.length === 0) {
        return (
            <div className="text-center py-10">
                <p className="text-muted-foreground">No badges found</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Subtask 1.2.2: Enhanced Filter Section */}
            <div className="space-y-4 border rounded-lg p-4 bg-card">
                {/* Primary Filters */}
                <div className="flex gap-3">
                    <Input
                        placeholder="Search by employee name, ID, or card UID..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="flex-1"
                    />
                    <Select value={filterDepartment} onValueChange={setFilterDepartment}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Departments</SelectItem>
                            <SelectItem value="operations">Operations</SelectItem>
                            <SelectItem value="engineering">Engineering</SelectItem>
                            <SelectItem value="warehouse">Warehouse</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={filterCardType} onValueChange={setFilterCardType}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Card Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Types</SelectItem>
                            <SelectItem value="mifare">Mifare</SelectItem>
                            <SelectItem value="desfire">DESFire</SelectItem>
                            <SelectItem value="em4100">EM4100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Secondary Filters */}
                <div className="flex gap-3">
                    <Select value={filterStatus} onValueChange={setFilterStatus}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="lost">Lost</SelectItem>
                            <SelectItem value="stolen">Stolen</SelectItem>
                            <SelectItem value="replaced">Replaced</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={filterExpiration} onValueChange={setFilterExpiration}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Expiration" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Expirations</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="expiring-soon">Expiring Soon (&lt;30 days)</SelectItem>
                            <SelectItem value="valid">Valid</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        type="date"
                        placeholder="From date"
                        value={dateRangeStart}
                        onChange={(e) => setDateRangeStart(e.target.value)}
                        className="w-40"
                    />
                    <Input
                        type="date"
                        placeholder="To date"
                        value={dateRangeEnd}
                        onChange={(e) => setDateRangeEnd(e.target.value)}
                        className="w-40"
                    />
                    {hasActiveFilters && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={clearAllFilters}
                            className="gap-2"
                        >
                            <X className="h-4 w-4" />
                            Clear Filters
                        </Button>
                    )}
                </div>

                {/* Items Per Page */}
                <div className="flex items-center justify-between">
                    <Select value={perPage} onValueChange={setPerPage}>
                        <SelectTrigger className="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="25">25 per page</SelectItem>
                            <SelectItem value="50">50 per page</SelectItem>
                            <SelectItem value="100">100 per page</SelectItem>
                        </SelectContent>
                    </Select>
                    <span className="text-sm text-muted-foreground">
                        Showing {paginatedBadges.length} of {filteredBadges.length} badges
                    </span>
                </div>
            </div>

            {/* Subtask 1.2.1: Enhanced Table */}
            <div className="border rounded-lg overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted">
                            <TableHead className="w-8">Status</TableHead>
                            <TableHead>Employee</TableHead>
                            <SortableHeader field="employee_id" activeSort={activeSort} sortOrder={sortOrder} onSort={handleSort}>Employee ID</SortableHeader>
                            <SortableHeader field="department" activeSort={activeSort} sortOrder={sortOrder} onSort={handleSort}>Department</SortableHeader>
                            <TableHead>Card UID</TableHead>
                            <TableHead>Type</TableHead>
                            <SortableHeader field="issued_at" activeSort={activeSort} sortOrder={sortOrder} onSort={handleSort}>Issued</SortableHeader>
                            <SortableHeader field="expires_at" activeSort={activeSort} sortOrder={sortOrder} onSort={handleSort}>Expires</SortableHeader>
                            <SortableHeader field="last_used_at" activeSort={activeSort} sortOrder={sortOrder} onSort={handleSort}>Last Used</SortableHeader>
                            <SortableHeader field="usage_count" activeSort={activeSort} sortOrder={sortOrder} onSort={handleSort}>Scans</SortableHeader>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {paginatedBadges.map((badge) => {
                            const daysLeft = getDaysUntilExpiration(badge.expires_at);
                            const showWarning = isExpirationWarning(daysLeft);
                            const showExpired = isExpired(daysLeft);

                            return (
                                <TableRow 
                                    key={badge.id}
                                    className={`
                                        transition-colors
                                        ${showExpired ? 'bg-red-50 hover:bg-red-100' : ''}
                                        ${showWarning && !showExpired ? 'bg-amber-50 hover:bg-amber-100' : ''}
                                        ${!showExpired && !showWarning ? 'hover:bg-muted' : ''}
                                    `}
                                >
                                    <TableCell className="w-8">
                                        {getStatusIndicator(badge)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {badge.employee_photo && (
                                                <img 
                                                    src={badge.employee_photo}
                                                    alt={badge.employee_name}
                                                    className="h-8 w-8 rounded-full"
                                                />
                                            )}
                                            <span className="font-medium">{badge.employee_name}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm">{badge.employee_id}</TableCell>
                                    <TableCell className="text-sm">{badge.department}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <code className="text-xs bg-muted px-2 py-1 rounded font-mono">
                                                {badge.card_uid}
                                            </code>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => navigator.clipboard.writeText(badge.card_uid)}
                                                className="h-6 w-6 p-0"
                                                title="Copy card UID"
                                            >
                                                <Copy className="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {getCardTypeBadge(badge.card_type)}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {formatDate(badge.issued_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {badge.expires_at ? (
                                                <>
                                                    <span className="text-sm">
                                                        {formatDate(badge.expires_at)}
                                                    </span>
                                                    {showExpired && (
                                                        <AlertTriangle className="h-4 w-4 text-red-600" aria-label="Badge expired" />
                                                    )}
                                                    {showWarning && (
                                                        <span className="text-xs text-amber-600 font-medium">
                                                            {daysLeft}d left
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">No expiry</span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {formatRelativeTime(badge.last_used_at)}
                                    </TableCell>
                                    <TableCell className="text-sm font-medium">
                                        {badge.usage_count}
                                    </TableCell>
                                    {/* Subtask 1.2.3: Enhanced Actions Dropdown */}
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-48">
                                                <DropdownMenuItem className="gap-2">
                                                    <Eye className="h-4 w-4" />
                                                    <span>View Badge Details</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem className="gap-2">
                                                    <History className="h-4 w-4" />
                                                    <span>View Usage History</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    className="gap-2"
                                                    onClick={() => onReplace?.(badge)}
                                                >
                                                    <Repeat2 className="h-4 w-4" />
                                                    <span>Replace Badge</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem className="gap-2">
                                                    <Clock className="h-4 w-4" />
                                                    <span>Extend Expiration</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem className="gap-2">
                                                    <Printer className="h-4 w-4" />
                                                    <span>Print Badge Info</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    className="gap-2 text-destructive"
                                                    onClick={(e) => {
                                                        if (!window.confirm(`Deactivate badge for ${badge.employee_name}?`)) {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span>Deactivate Badge</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination Info */}
            <div className="flex justify-between items-center text-sm text-muted-foreground px-2">
                <span>
                    {paginatedBadges.length > 0 
                        ? `Showing ${(sortedBadges.length > 0 ? 1 : 0)}-${paginatedBadges.length} of ${filteredBadges.length} badges`
                        : 'No badges found'}
                </span>
                <span>
                    {totalPages > 1 && `Page 1 of ${totalPages}`}
                </span>
            </div>
        </div>
    );
}
