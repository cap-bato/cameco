'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ScatterChart, Scatter } from 'recharts';
import { Search, Download, TrendingUp, TrendingDown } from 'lucide-react';
import { useState } from 'react';
import type { EmployeeCostAnalysis } from '@/types/payroll-pages';

interface DeptStats {
    department_name: string;
    employees: EmployeeCostAnalysis[];
    totalCost: number;
    avgCost: number;
}

interface DeptStatsRecord {
    [key: string]: DeptStats;
}

interface PositionStat {
    position: string;
    employees: EmployeeCostAnalysis[];
    totalCost: number;
    avgCost: number;
    avgSalary: number;
}

interface PositionStatsRecord {
    [key: string]: PositionStat;
}

interface EmployeeCostAnalysisProps {
    employees: EmployeeCostAnalysis[];
}

export function EmployeeCostAnalysisComponent({
    employees,
}: EmployeeCostAnalysisProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [sortBy, setSortBy] = useState<'cost' | 'salary' | 'name'>('cost');

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    // Filter and sort employees
    const filteredEmployees = employees
        .filter(
            (emp) =>
                emp.employee_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                emp.employee_code.toLowerCase().includes(searchQuery.toLowerCase()) ||
                emp.department_name.toLowerCase().includes(searchQuery.toLowerCase())
        )
        .sort((a, b) => {
            if (sortBy === 'cost') return b.cost_to_company - a.cost_to_company;
            if (sortBy === 'salary') return b.basic_salary - a.basic_salary;
            return a.employee_name.localeCompare(b.employee_name);
        });

    // Calculate statistics
    const totalCost = employees.reduce((sum, emp) => sum + emp.cost_to_company, 0);
    const avgCostPerEmployee = employees.length > 0 ? totalCost / employees.length : 0;
    const costStdDev = employees.length > 0
        ? Math.sqrt(
              employees.reduce((sum, emp) => sum + Math.pow(emp.cost_to_company - avgCostPerEmployee, 2), 0) /
                  employees.length
          )
        : 0;
    const maxCost = employees.length > 0 ? Math.max(...employees.map((e) => e.cost_to_company)) : 0;

    // Department averages
    const deptStats = employees.reduce((acc: DeptStatsRecord, emp) => {
        if (!acc[emp.department_id]) {
            acc[emp.department_id] = {
                department_name: emp.department_name,
                employees: [],
                totalCost: 0,
                avgCost: 0,
            };
        }
        acc[emp.department_id].employees.push(emp);
        acc[emp.department_id].totalCost += emp.cost_to_company;
        return acc;
    }, {});

    Object.values(deptStats).forEach((dept: DeptStats) => {
        dept.avgCost = dept.totalCost / dept.employees.length;
    });

    // Prepare data for scatter chart
    const scatterData = employees.map((emp) => ({
        name: emp.employee_name,
        basicSalary: emp.basic_salary,
        costToCompany: emp.cost_to_company,
        department: emp.department_name,
    }));

    // Position averages
    const positionStats = employees.reduce((acc: PositionStatsRecord, emp) => {
        if (!acc[emp.position]) {
            acc[emp.position] = {
                position: emp.position,
                employees: [],
                totalCost: 0,
                avgCost: 0,
                avgSalary: 0,
            };
        }
        acc[emp.position].employees.push(emp);
        acc[emp.position].totalCost += emp.cost_to_company;
        acc[emp.position].avgCost = acc[emp.position].totalCost / acc[emp.position].employees.length;
        acc[emp.position].avgSalary = emp.basic_salary;
        return acc;
    }, {});

    if (employees.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 py-16 text-center">
                <p className="text-lg font-semibold text-gray-500">No Employee Data Available</p>
                <p className="mt-1 text-sm text-gray-400">
                    Employee cost analysis will appear once payroll calculations exist for this period.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Summary Cards */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600">Total Cost to Company</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold text-gray-900">{formatCurrency(totalCost)}</p>
                        <p className="mt-1 text-xs text-gray-500">{employees.length} employees</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600">Average Cost per Employee</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold text-gray-900">{formatCurrency(avgCostPerEmployee)}</p>
                        <p className="mt-1 text-xs text-gray-500">Monthly average</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600">Cost Variation</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold text-gray-900">{formatCurrency(costStdDev)}</p>
                        <p className="mt-1 text-xs text-gray-500">Standard deviation</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600">Highest Cost Employee</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-bold text-gray-900">
                            {formatCurrency(maxCost)}
                        </p>
                        <p className="mt-1 text-xs text-gray-500">Peak employee cost</p>
                    </CardContent>
                </Card>
            </div>

            {/* Salary vs Cost to Company Scatter Chart */}
            <Card>
                <CardHeader>
                    <CardTitle>Basic Salary vs Cost to Company</CardTitle>
                    <CardDescription>
                        Relationship between basic salary and total cost to company
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={350}>
                        <ScatterChart margin={{ top: 20, right: 20, bottom: 20, left: 20 }}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="basicSalary" name="Basic Salary" tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                            <YAxis dataKey="costToCompany" name="Cost to Company" tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                            <Tooltip
                                cursor={{ strokeDasharray: '3 3' }}
                                formatter={(value: number) => formatCurrency(value)}
                                labelFormatter={(value) => `${value}`}
                            />
                            <Scatter name="Employees" data={scatterData} fill="#3B82F6" />
                        </ScatterChart>
                    </ResponsiveContainer>
                </CardContent>
            </Card>

            {/* Department Benchmarking */}
            <Card>
                <CardHeader>
                    <CardTitle>Department Benchmarking</CardTitle>
                    <CardDescription>
                        Average cost per employee by department
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={Object.values(deptStats) as DeptStats[]}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="department_name" angle={-45} textAnchor="end" height={80} />
                            <YAxis tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                            <Tooltip formatter={(value: number) => formatCurrency(value)} />
                            <Bar dataKey="avgCost" fill="#3B82F6" name="Average Cost per Employee" />
                        </BarChart>
                    </ResponsiveContainer>
                </CardContent>
            </Card>

            {/* Position Benchmarking */}
            <Card>
                <CardHeader>
                    <CardTitle>Position-Based Cost Analysis</CardTitle>
                    <CardDescription>
                        Cost analysis grouped by position/title
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {Object.values(positionStats)
                            .sort((a: PositionStat, b: PositionStat) => b.avgCost - a.avgCost)
                            .slice(0, 10)
                            .map((pos: PositionStat) => (
                                <div key={pos.position} className="rounded-lg border border-gray-200 p-4">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h4 className="font-semibold text-gray-900">{pos.position}</h4>
                                            <p className="mt-1 text-sm text-gray-600">{pos.employees.length} employees</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-lg font-bold text-gray-900">{formatCurrency(pos.avgCost)}</p>
                                            <p className="text-xs text-gray-500">Average cost</p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                    </div>
                </CardContent>
            </Card>

            {/* Employee Details Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Employee Cost Details</CardTitle>
                    <CardDescription>
                        Detailed cost breakdown for each employee
                    </CardDescription>
                    <div className="mt-4 flex gap-2">
                        <div className="flex-1 relative">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                            <Input
                                placeholder="Search by name, code, or department..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10"
                            />
                        </div>
                        <select
                            value={sortBy}
                            onChange={(e) => setSortBy(e.target.value as 'cost' | 'salary' | 'name')}
                            className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="cost">Sort by Cost</option>
                            <option value="salary">Sort by Salary</option>
                            <option value="name">Sort by Name</option>
                        </select>
                        <Button variant="outline" size="sm">
                            <Download className="h-4 w-4 mr-2" />
                            Export
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3 max-h-96 overflow-y-auto">
                        {filteredEmployees.map((emp) => {
                            const deptAvg = deptStats[emp.department_id]?.avgCost || 0;
                            const vsDeptAvg = emp.cost_to_company - deptAvg;
                            const vsDeptPercent = (vsDeptAvg / deptAvg) * 100;

                            return (
                                <div key={emp.employee_id} className="rounded-lg border border-gray-200 p-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h4 className="font-semibold text-gray-900">{emp.employee_name}</h4>
                                            <p className="text-xs text-gray-500">{emp.employee_code}</p>
                                            <p className="text-xs text-gray-500">{emp.department_name}</p>
                                            <p className="text-xs text-gray-500">{emp.position}</p>
                                        </div>
                                        <div className="text-right">
                                            <div className="grid gap-2 text-sm">
                                                <div>
                                                    <p className="text-xs text-gray-500">Basic Salary</p>
                                                    <p className="font-semibold text-gray-900">{formatCurrency(emp.basic_salary)}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-gray-500">Cost to Company</p>
                                                    <p className="font-semibold text-gray-900">{formatCurrency(emp.cost_to_company)}</p>
                                                </div>
                                                {vsDeptAvg !== 0 && (
                                                    <div className="flex items-center justify-end gap-1">
                                                        {vsDeptAvg > 0 ? (
                                                            <TrendingUp className="h-3 w-3 text-red-500" />
                                                        ) : (
                                                            <TrendingDown className="h-3 w-3 text-green-500" />
                                                        )}
                                                        <span
                                                            className={`text-xs font-semibold ${vsDeptAvg > 0 ? 'text-red-600' : 'text-green-600'}`}
                                                        >
                                                            {vsDeptPercent > 0 ? '+' : ''}{vsDeptPercent.toFixed(1)}%
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
