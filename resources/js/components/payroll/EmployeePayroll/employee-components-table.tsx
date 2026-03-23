import React, { useState } from 'react';
import { ChevronDown, Edit2, Trash2, History } from 'lucide-react';
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  }).format(value);
};

export interface EmployeeComponent {
  id: number;
  salary_component_id: number;
  component_name: string;
  component_code: string;
  component_type: 'allowance' | 'deduction' | 'tax' | 'contribution';
  amount: number | null;
  percentage: number | null;
  units: number | null;
  frequency: 'per_payroll' | 'monthly' | 'quarterly' | 'semi_annual' | 'annually' | 'one_time';
  effective_date: string;
  end_date: string | null;
  is_active: boolean;
  is_prorated: boolean;
  requires_attendance: boolean;
}

export interface EmployeeComponentsTableProps {
  data: Array<{
    id: number;
    employee_id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    department: string;
    position: string;
    components: EmployeeComponent[];
    total_allowances: number;
    total_deductions: number;
  }>;
  onEdit?: (assignment: EmployeeComponent, employeeId: number) => void;
  onDelete?: (assignmentId: number, employeeId: number) => void;
  onViewHistory?: (employeeId: number, componentId: number) => void;
  isLoading?: boolean;
}

const FREQUENCY_LABELS: Record<string, string> = {
  per_payroll: 'Per Payroll',
  monthly: 'Monthly',
  quarterly: 'Quarterly',
  semi_annual: 'Semi-Annual',
  annually: 'Annually',
  one_time: 'One-Time',
};

const getComponentTypeBadgeColor = (type: string) => {
  const colors: Record<string, string> = {
    allowance: 'bg-green-100 text-green-800',
    deduction: 'bg-red-100 text-red-800',
    tax: 'bg-orange-100 text-orange-800',
    contribution: 'bg-blue-100 text-blue-800',
  };
  return colors[type] || 'bg-gray-100 text-gray-800';
};


export const EmployeeComponentsTable: React.FC<EmployeeComponentsTableProps> = ({
  data,
  onEdit,
  onDelete,
  onViewHistory,
  isLoading = false,
}) => {
  const [expandedRows, setExpandedRows] = useState<number[]>([]);

  const toggleRow = (employeeId: number) => {
    setExpandedRows((prev) =>
      prev.includes(employeeId)
        ? prev.filter((id) => id !== employeeId)
        : [...prev, employeeId]
    );
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-500">Loading assignments...</div>
      </div>
    );
  }

  if (data.length === 0) {
    return (
      <div className="flex items-center justify-center h-64 border rounded-lg bg-gray-50">
        <div className="text-center">
          <p className="text-gray-600 text-lg">No component assignments found</p>
          <p className="text-gray-500 text-sm mt-1">Create your first assignment to get started</p>
        </div>
      </div>
    );
  }

  return (
    <div className="border rounded-lg overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow className="bg-gray-50">
            <TableHead className="w-12"></TableHead>
            <TableHead>Employee</TableHead>
            <TableHead>Employee #</TableHead>
            <TableHead>Department</TableHead>
            <TableHead>Position</TableHead>
            <TableHead className="text-right">Allowances</TableHead>
            <TableHead className="text-right">Deductions</TableHead>
            <TableHead className="w-12"></TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {data.map((employee) => (
            <React.Fragment key={employee.id}>
              <TableRow
                className="cursor-pointer hover:bg-gray-50"
                onClick={() => toggleRow(employee.employee_id)}
              >
                <TableCell>
                  <ChevronDown
                    size={16}
                    className={`transition-transform ${
                      expandedRows.includes(employee.employee_id) ? 'rotate-180' : ''
                    }`}
                  />
                </TableCell>
                <TableCell className="font-medium">
                  {employee.first_name} {employee.last_name}
                </TableCell>
                <TableCell className="text-sm text-gray-600">{employee.employee_number}</TableCell>
                <TableCell>{employee.department || <span className="text-gray-400">No Department</span>}</TableCell>
                <TableCell className="text-sm text-gray-600">{employee.position || <span className="text-gray-400">No Position</span>}</TableCell>
                <TableCell className="text-right font-medium text-green-600">
                  +{formatCurrency(employee.total_allowances)}
                </TableCell>
                <TableCell className="text-right font-medium text-red-600">
                  -{formatCurrency(employee.total_deductions)}
                </TableCell>
                <TableCell></TableCell>
              </TableRow>

              {expandedRows.includes(employee.employee_id) && (
                <TableRow className="bg-gray-50">
                  <TableCell colSpan={8}>
                    <div className="py-4">
                      <h4 className="text-sm font-semibold mb-3">Assigned Components</h4>
                      <div className="space-y-2 max-h-96 overflow-y-auto">
                        {employee.components.length === 0 ? (
                          <p className="text-sm text-gray-500 italic">No components assigned</p>
                        ) : (
                          employee.components.map((component) => (
                            <div
                              key={component.id}
                              className="bg-white rounded border p-3 flex items-center justify-between"
                            >
                              <div className="flex-1">
                                <div className="flex items-center gap-3">
                                  <Badge className={getComponentTypeBadgeColor(component.component_type)}>
                                    {component.component_code}
                                  </Badge>
                                  <span className="font-medium text-sm">{component.component_name}</span>
                                  {component.is_active ? (
                                    <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700">
                                      Active
                                    </Badge>
                                  ) : (
                                    <Badge variant="outline" className="border-gray-200 bg-gray-50 text-gray-700">
                                      Inactive
                                    </Badge>
                                  )}
                                </div>
                                <div className="flex items-center gap-4 mt-2 text-xs text-gray-600">
                                  {component.amount !== null && (
                                    <span>Amount: {formatCurrency(component.amount)}</span>
                                  )}
                                  {component.percentage !== null && (
                                    <span>Percentage: {component.percentage}%</span>
                                  )}
                                  {component.units !== null && <span>Units: {component.units}</span>}
                                  <span>Frequency: {FREQUENCY_LABELS[component.frequency]}</span>
                                  <span>
                                    Effective: {new Date(component.effective_date).toLocaleDateString()}
                                  </span>
                                  {component.end_date && (
                                    <span>
                                      Expires: {new Date(component.end_date).toLocaleDateString()}
                                    </span>
                                  )}
                                  {component.is_prorated && (
                                    <Badge variant="secondary" className="bg-blue-50 text-blue-700 border-blue-200">
                                      Prorated
                                    </Badge>
                                  )}
                                  {component.requires_attendance && (
                                    <Badge variant="secondary" className="bg-purple-50 text-purple-700 border-purple-200">
                                      Requires Attendance
                                    </Badge>
                                  )}
                                </div>
                              </div>

                              <div className="flex gap-1">
                                <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="sm">
                                      <ChevronDown size={16} />
                                    </Button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                      onClick={() => onViewHistory?.(employee.employee_id, component.salary_component_id)}
                                    >
                                      <History size={16} className="mr-2" />
                                      View History
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                      onClick={() => onEdit?.(component, employee.employee_id)}
                                    >
                                      <Edit2 size={16} className="mr-2" />
                                      Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                      onClick={() => onDelete?.(component.id, employee.employee_id)}
                                      className="text-red-600"
                                    >
                                      <Trash2 size={16} className="mr-2" />
                                      Delete
                                    </DropdownMenuItem>
                                  </DropdownMenuContent>
                                </DropdownMenu>
                              </div>
                            </div>
                          ))
                        )}
                      </div>
                    </div>
                  </TableCell>
                </TableRow>
              )}
            </React.Fragment>
          ))}
        </TableBody>
      </Table>
    </div>
  );
};