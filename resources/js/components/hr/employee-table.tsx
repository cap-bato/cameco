import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { EmployeeStatusBadge } from './employee-status-badge';
import { EmployeeArchiveDialog } from './employee-archive-dialog';
import { EmployeeRestoreDialog } from './employee-restore-dialog';
import { Link } from '@inertiajs/react';
import { 
    MoreHorizontal, 
    Eye, 
    Edit, 
    Archive, 
    RotateCcw,
    ChevronUp,
    ChevronDown,
    ChevronsUpDown
} from 'lucide-react';
import { useState } from 'react';

// ============================================================================
// Type Definitions
// ============================================================================

export interface Employee {
    id: number;
    employee_number: string;
    profile: {
        first_name: string;
        last_name: string;
        middle_name?: string;
        profile_picture_path?: string | null;
    };
    department?: {
        id: number;
        name: string;
    };
    position?: {
        id: number;
        title: string;
    };
    status: 'active' | 'on_leave' | 'terminated' | 'archived' | 'suspended';
    employment_type: string;
    date_hired: string;
    deleted_at?: string;
}

interface EmployeeTableProps {
    employees: Employee[];
    onSort?: (column: string, direction: 'asc' | 'desc') => void;
    sortColumn?: string;
    sortDirection?: 'asc' | 'desc';
}

// ============================================================================
// Helper Functions
// ============================================================================

function getInitials(firstName: string, lastName: string): string {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

function getFullName(employee: Employee): string {
    const { first_name, middle_name, last_name } = employee.profile;
    return middle_name 
        ? `${first_name} ${middle_name} ${last_name}`
        : `${first_name} ${last_name}`;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function formatEmploymentType(type: string): string {
    return type
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

// ============================================================================
// Sort Icon Component
// ============================================================================

function SortIcon({ 
    column, 
    sortColumn, 
    sortDirection 
}: { 
    column: string;
    sortColumn?: string;
    sortDirection?: 'asc' | 'desc';
}) {
    if (sortColumn !== column) {
        return <ChevronsUpDown className="h-4 w-4 ml-1 text-muted-foreground" />;
    }
    return sortDirection === 'asc' 
        ? <ChevronUp className="h-4 w-4 ml-1" />
        : <ChevronDown className="h-4 w-4 ml-1" />;
}

// ============================================================================
// Component
// ============================================================================

export function EmployeeTable({ 
    employees, 
    onSort,
    sortColumn,
    sortDirection
}: EmployeeTableProps) {
    const [archiveDialogOpen, setArchiveDialogOpen] = useState(false);
    const [restoreDialogOpen, setRestoreDialogOpen] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(null);

    const handleSort = (column: string) => {
        if (!onSort) return;
        
        const newDirection = 
            sortColumn === column && sortDirection === 'asc' 
                ? 'desc' 
                : 'asc';
        
        onSort(column, newDirection);
    };

    const openArchiveDialog = (employee: Employee) => {
        setSelectedEmployee(employee);
        setArchiveDialogOpen(true);
    };

    const openRestoreDialog = (employee: Employee) => {
        setSelectedEmployee(employee);
        setRestoreDialogOpen(true);
    };

    if (employees.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-center border rounded-lg">
                <div className="h-12 w-12 rounded-full bg-muted flex items-center justify-center mb-4">
                    <Archive className="h-6 w-6 text-muted-foreground" />
                </div>
                <h3 className="text-lg font-semibold mb-1">No employees found</h3>
                <p className="text-sm text-muted-foreground mb-4">
                    Try adjusting your filters or create a new employee.
                </p>
                <Link href="/hr/employees/create">
                    <Button>Add Employee</Button>
                </Link>
            </div>
        );
    }

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-12">
                            {/* Checkbox for future bulk actions */}
                        </TableHead>
                        <TableHead 
                            className="cursor-pointer hover:bg-muted/50"
                            onClick={() => handleSort('employee_number')}
                        >
                            <div className="flex items-center">
                                Employee #
                                <SortIcon 
                                    column="employee_number" 
                                    sortColumn={sortColumn}
                                    sortDirection={sortDirection}
                                />
                            </div>
                        </TableHead>
                        <TableHead 
                            className="cursor-pointer hover:bg-muted/50"
                            onClick={() => handleSort('name')}
                        >
                            <div className="flex items-center">
                                Name
                                <SortIcon 
                                    column="name" 
                                    sortColumn={sortColumn}
                                    sortDirection={sortDirection}
                                />
                            </div>
                        </TableHead>
                        <TableHead>Department</TableHead>
                        <TableHead>Position</TableHead>
                        <TableHead>Employment Type</TableHead>
                        <TableHead 
                            className="cursor-pointer hover:bg-muted/50"
                            onClick={() => handleSort('date_hired')}
                        >
                            <div className="flex items-center">
                                Hire Date
                                <SortIcon 
                                    column="date_hired" 
                                    sortColumn={sortColumn}
                                    sortDirection={sortDirection}
                                />
                            </div>
                        </TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {employees.map((employee) => (
                        <TableRow 
                            key={employee.id}
                            className="hover:bg-muted/50 transition-colors"
                        >
                            <TableCell>
                                {/* Checkbox placeholder */}
                            </TableCell>
                            <TableCell className="font-mono text-sm">
                                {employee.employee_number}
                            </TableCell>
                            <TableCell>
                                <div className="flex items-center gap-3">
                                    <Avatar className="h-8 w-8">
                                        {employee.profile.profile_picture_path && (
                                            <AvatarImage 
                                                src={`/storage/${employee.profile.profile_picture_path}`} 
                                                alt={getFullName(employee)} 
                                            />
                                        )}
                                        <AvatarFallback className="text-xs">
                                            {getInitials(
                                                employee.profile.first_name, 
                                                employee.profile.last_name
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex flex-col">
                                        <span className="font-medium">
                                            {getFullName(employee)}
                                        </span>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                {employee.department ? (
                                    <span className="text-sm">
                                        {employee.department.name}
                                    </span>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Not Assigned
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                {employee.position ? (
                                    <span className="text-sm">
                                        {employee.position.title}
                                    </span>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Not Assigned
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" className="text-xs">
                                    {formatEmploymentType(employee.employment_type)}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-sm">
                                {formatDate(employee.date_hired)}
                            </TableCell>
                            <TableCell>
                                <EmployeeStatusBadge status={employee.status} />
                            </TableCell>
                            <TableCell className="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button 
                                            variant="ghost" 
                                            size="sm"
                                            className="h-8 w-8 p-0"
                                        >
                                            <span className="sr-only">Open menu</span>
                                            <MoreHorizontal className="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href={`/hr/employees/${employee.id}`}>
                                                <Eye className="mr-2 h-4 w-4" />
                                                View Details
                                            </Link>
                                        </DropdownMenuItem>
                                        {!employee.deleted_at && (
                                            <>
                                                <DropdownMenuItem asChild>
                                                    <Link href={`/hr/employees/${employee.id}/edit`}>
                                                        <Edit className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem 
                                                    className="text-destructive"
                                                    onClick={() => openArchiveDialog(employee)}
                                                >
                                                    <Archive className="mr-2 h-4 w-4" />
                                                    Archive
                                                </DropdownMenuItem>
                                            </>
                                        )}
                                        {employee.deleted_at && (
                                            <DropdownMenuItem onClick={() => openRestoreDialog(employee)}>
                                                <RotateCcw className="mr-2 h-4 w-4" />
                                                Restore
                                            </DropdownMenuItem>
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {/* Archive Confirmation Dialog */}
            {selectedEmployee && (
                <EmployeeArchiveDialog
                    open={archiveDialogOpen}
                    onOpenChange={setArchiveDialogOpen}
                    employeeId={selectedEmployee.id}
                    employeeName={getFullName(selectedEmployee)}
                    employeeNumber={selectedEmployee.employee_number}
                />
            )}

            {/* Restore Confirmation Dialog */}
            {selectedEmployee && (
                <EmployeeRestoreDialog
                    open={restoreDialogOpen}
                    onOpenChange={setRestoreDialogOpen}
                    employeeId={selectedEmployee.id}
                    employeeName={getFullName(selectedEmployee)}
                    employeeNumber={selectedEmployee.employee_number}
                />
            )}
        </div>
    );
}
