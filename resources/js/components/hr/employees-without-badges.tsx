import React, { useState, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge as BadgeComponent } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AlertTriangle, Plus, Download } from 'lucide-react';
import { differenceInDays, parseISO } from 'date-fns';

interface Employee {
    id: string;
    name: string;
    employee_id: string;
    department: string;
    position: string;
    hire_date: string;
    photo?: string;
}

interface EmployeesWithoutBadgesProps {
    employees: Employee[];
    onIssueBadge: (employee: Employee) => void;
}

export function EmployeesWithoutBadges({ employees, onIssueBadge }: EmployeesWithoutBadgesProps) {
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 10;

    // Calculate days without badge (based on hire date)
    const employeesWithDaysWithoutBadge = useMemo(() => {
        return employees.map((emp) => {
            const hireDate = parseISO(emp.hire_date);
            const daysWithoutBadge = differenceInDays(new Date(), hireDate);
            return {
                ...emp,
                daysWithoutBadge,
                isUrgent: daysWithoutBadge > 7,
            };
        });
    }, [employees]);

    // Sort by days without badge (most urgent first)
    const sortedEmployees = useMemo(() => {
        return [...employeesWithDaysWithoutBadge].sort(
            (a, b) => b.daysWithoutBadge - a.daysWithoutBadge
        );
    }, [employeesWithDaysWithoutBadge]);

    // Paginate
    const totalPages = Math.ceil(sortedEmployees.length / itemsPerPage);
    const paginatedEmployees = useMemo(() => {
        const startIndex = (currentPage - 1) * itemsPerPage;
        return sortedEmployees.slice(startIndex, startIndex + itemsPerPage);
    }, [sortedEmployees, currentPage]);

    // Export list as CSV
    const handleExportList = () => {
        const headers = ['Employee Name', 'Employee ID', 'Department', 'Position', 'Hire Date', 'Days Without Badge'];
        const rows = sortedEmployees.map((emp) => [
            emp.name,
            emp.employee_id,
            emp.department,
            emp.position,
            emp.hire_date,
            emp.daysWithoutBadge.toString(),
        ]);

        const csvContent = [
            headers.join(','),
            ...rows.map((row) => row.map((cell) => `"${cell}"`).join(',')),
        ].join('\n');

        const element = document.createElement('a');
        element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent));
        element.setAttribute('download', `employees-without-badges-${new Date().toISOString().split('T')[0]}.csv`);
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
    };

    if (employees.length === 0) {
        return null;
    }

    return (
        <Card className="border-amber-200 bg-amber-50">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                        <div>
                            <CardTitle className="text-amber-900">
                                Employees Without Badges ({employees.length})
                            </CardTitle>
                            <CardDescription className="text-amber-800">
                                {employees.length} active employee{employees.length !== 1 ? 's' : ''} need RFID badge
                                assignment
                            </CardDescription>
                        </div>
                    </div>
                    <Button
                        onClick={handleExportList}
                        variant="outline"
                        size="sm"
                        className="gap-2 border-amber-300 hover:bg-amber-100"
                    >
                        <Download className="h-4 w-4" />
                        Export List
                    </Button>
                </div>
            </CardHeader>

            <CardContent>
                <div className="space-y-4">
                    {/* Urgent Alert */}
                    {sortedEmployees.some((emp) => emp.isUrgent) && (
                        <Alert className="border-red-300 bg-red-50">
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                            <AlertDescription className="text-red-800">
                                {sortedEmployees.filter((emp) => emp.isUrgent).length} employee
                                {sortedEmployees.filter((emp) => emp.isUrgent).length !== 1 ? 's' : ''} have been
                                without badges for more than 7 days. Please issue badges urgently.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-amber-100/50 hover:bg-amber-100/50">
                                    <TableHead className="text-amber-900">Employee Name</TableHead>
                                    <TableHead className="text-amber-900">Employee ID</TableHead>
                                    <TableHead className="text-amber-900">Department</TableHead>
                                    <TableHead className="text-amber-900">Position</TableHead>
                                    <TableHead className="text-amber-900">Hire Date</TableHead>
                                    <TableHead className="text-amber-900">Days Without Badge</TableHead>
                                    <TableHead className="text-amber-900 text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginatedEmployees.map((emp) => (
                                    <TableRow key={emp.id} className="hover:bg-amber-50/50 border-amber-200">
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                {emp.photo && (
                                                    <img
                                                        src={emp.photo}
                                                        alt={emp.name}
                                                        className="h-8 w-8 rounded-full"
                                                    />
                                                )}
                                                <span className="font-semibold text-amber-950">{emp.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-amber-900">{emp.employee_id}</TableCell>
                                        <TableCell className="text-amber-900">{emp.department}</TableCell>
                                        <TableCell className="text-amber-900">{emp.position}</TableCell>
                                        <TableCell className="text-amber-900">
                                            {new Date(emp.hire_date).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-amber-900">
                                                    {emp.daysWithoutBadge}
                                                </span>
                                                {emp.isUrgent && (
                                                    <BadgeComponent className="bg-red-100 text-red-800 border-red-300">
                                                        Urgent
                                                    </BadgeComponent>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                onClick={() => onIssueBadge(emp)}
                                                size="sm"
                                                variant="outline"
                                                className="gap-1 border-amber-400 hover:bg-amber-200"
                                            >
                                                <Plus className="h-4 w-4" />
                                                Issue Badge
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Pagination */}
                    {totalPages > 1 && (
                        <div className="flex items-center justify-center gap-2">
                            <Button
                                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                disabled={currentPage === 1}
                                variant="outline"
                                size="sm"
                            >
                                Previous
                            </Button>
                            <div className="flex items-center gap-1">
                                {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                                    <Button
                                        key={page}
                                        onClick={() => setCurrentPage(page)}
                                        variant={currentPage === page ? 'default' : 'outline'}
                                        size="sm"
                                        className="w-10"
                                    >
                                        {page}
                                    </Button>
                                ))}
                            </div>
                            <Button
                                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                disabled={currentPage === totalPages}
                                variant="outline"
                                size="sm"
                            >
                                Next
                            </Button>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
