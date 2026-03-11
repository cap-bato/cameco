'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { TrendingUp, TrendingDown } from 'lucide-react';
import type { MonthlyLaborCostTrend, DepartmentCostComparison, ComponentCostBreakdown, YearOverYearComparison } from '@/types/payroll-pages';

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];

interface ChartDataItem {
    [key: string]: string | number;
}

interface CostTrendChartsProps {
    monthlyTrends: MonthlyLaborCostTrend[];
    departmentComparisons: DepartmentCostComparison[];
    componentBreakdown: ComponentCostBreakdown[];
    yoyComparisons: YearOverYearComparison[];
}

export function CostTrendCharts({
    monthlyTrends,
    departmentComparisons,
    componentBreakdown,
    yoyComparisons,
}: CostTrendChartsProps) {
    // Format currency
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    if (monthlyTrends.length === 0 && departmentComparisons.length === 0 && componentBreakdown.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 py-16 text-center">
                <p className="text-lg font-semibold text-gray-500">No Analytics Data Available</p>
                <p className="mt-1 text-sm text-gray-400">
                    Cost trend charts will appear once payroll calculations exist for this period.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Monthly Labor Cost Trends */}
            <Card>
                <CardHeader>
                    <CardTitle>Monthly Labor Cost Trends</CardTitle>
                    <CardDescription>
                        Total labor cost breakdown by cost components over time
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={400}>
                        <BarChart data={monthlyTrends}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month_label" />
                            <YAxis tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                            <Tooltip formatter={(value: number) => formatCurrency(value)} />
                            <Legend />
                            <Bar dataKey="total_basic_salary" stackId="a" fill="#3B82F6" name="Basic Salary" />
                            <Bar dataKey="total_allowances" stackId="a" fill="#10B981" name="Allowances" />
                            <Bar dataKey="total_overtime" stackId="a" fill="#F59E0B" name="Overtime" />
                            <Bar dataKey="total_benefits" stackId="a" fill="#8B5CF6" name="Benefits" />
                            <Bar dataKey="total_contributions" stackId="a" fill="#EC4899" name="Contributions" />
                            <Bar dataKey="total_taxes" stackId="a" fill="#EF4444" name="Taxes" />
                        </BarChart>
                    </ResponsiveContainer>
                    {/* Summary Cards */}
                    <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                        {monthlyTrends.length > 0 && (
                            <>
                                <div className="rounded-lg bg-blue-50 p-4">
                                    <p className="text-sm font-medium text-gray-600">Latest Total Cost</p>
                                    <p className="mt-1 text-xl font-bold text-blue-600">
                                        {formatCurrency(monthlyTrends[0].total_labor_cost)}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-green-50 p-4">
                                    <p className="text-sm font-medium text-gray-600">Avg Per Employee</p>
                                    <p className="mt-1 text-xl font-bold text-green-600">
                                        {formatCurrency(monthlyTrends[0].cost_per_employee)}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-purple-50 p-4">
                                    <p className="text-sm font-medium text-gray-600">Employee Count</p>
                                    <p className="mt-1 text-xl font-bold text-purple-600">
                                        {monthlyTrends[0].employee_count}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-amber-50 p-4">
                                    <p className="text-sm font-medium text-gray-600">Total Overtime</p>
                                    <p className="mt-1 text-xl font-bold text-amber-600">
                                        {formatCurrency(monthlyTrends[0].total_overtime)}
                                    </p>
                                </div>
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Department Comparisons */}
            <Card>
                <CardHeader>
                    <CardTitle>Department Cost Comparison</CardTitle>
                    <CardDescription>
                        Labor cost analysis by department with trend indicators
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={400}>
                        <BarChart data={departmentComparisons}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="department_name" angle={-45} textAnchor="end" height={80} />
                            <YAxis tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                            <Tooltip formatter={(value: number) => formatCurrency(value)} />
                            <Legend />
                            <Bar dataKey="total_labor_cost" fill="#3B82F6" name="Total Labor Cost" />
                            <Bar dataKey="average_cost_per_employee" fill="#10B981" name="Cost Per Employee" />
                        </BarChart>
                    </ResponsiveContainer>
                    {/* Department Cards */}
                    <div className="mt-6 space-y-3">
                        {departmentComparisons.map((dept) => (
                            <div key={dept.department_id} className="rounded-lg border border-gray-200 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="font-semibold text-gray-900">{dept.department_name}</h4>
                                        <p className="text-sm text-gray-500">
                                            {dept.total_employees} employees • {formatCurrency(dept.total_labor_cost)}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <div className="flex items-center gap-2">
                                            {dept.trend === 'up' ? (
                                                <TrendingUp className="h-4 w-4 text-red-500" />
                                            ) : dept.trend === 'down' ? (
                                                <TrendingDown className="h-4 w-4 text-green-500" />
                                            ) : (
                                                <div className="h-4 w-4 rounded-full bg-gray-300" />
                                            )}
                                            <span className={`font-semibold ${dept.trend === 'up' ? 'text-red-600' : 'text-green-600'}`}>
                                                {dept.trend_percentage > 0 ? '+' : ''}{dept.trend_percentage.toFixed(1)}%
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {formatCurrency(dept.average_cost_per_employee)}/employee
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Component Breakdown - Pie Chart */}
            <Card>
                <CardHeader>
                    <CardTitle>Cost Component Breakdown</CardTitle>
                    <CardDescription>
                        Distribution of labor costs by component type
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={400}>
                        <PieChart>
                            <Pie
                                data={componentBreakdown as unknown as ChartDataItem[]}
                                cx="50%"
                                cy="50%"
                                labelLine={false}
                                outerRadius={120}
                                fill="#8884d8"
                                dataKey="total_amount"
                            >
                                {componentBreakdown.map((_, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip formatter={(value: number) => formatCurrency(value)} />
                        </PieChart>
                    </ResponsiveContainer>
                    {/* Component Details */}
                    <div className="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2">
                        {componentBreakdown.map((component) => (
                            <div key={component.component_id} className="rounded-lg border border-gray-200 p-3">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-semibold text-gray-900">{component.component_name}</p>
                                        <p className="text-xs text-gray-500">{component.component_type}</p>
                                    </div>
                                    <span className="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                        {component.percentage_of_gross.toFixed(1)}%
                                    </span>
                                </div>
                                <p className="mt-2 text-sm font-semibold text-gray-900">{formatCurrency(component.total_amount)}</p>
                                <p className="text-xs text-gray-500">
                                    {component.affected_employees} employees • {formatCurrency(component.average_per_employee)}/emp
                                </p>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Year-over-Year Comparison */}
            {yoyComparisons.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Year-over-Year Comparison</CardTitle>
                        <CardDescription>
                            Labor cost comparison with previous year
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={350}>
                            <LineChart data={yoyComparisons}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="month" />
                                <YAxis tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                                <Tooltip formatter={(value: number) => formatCurrency(value)} />
                                <Legend />
                                <Line 
                                    type="monotone" 
                                    dataKey="current_year_cost" 
                                    stroke="#3B82F6" 
                                    name="Current Year"
                                    strokeWidth={2}
                                />
                                <Line 
                                    type="monotone" 
                                    dataKey="previous_year_cost" 
                                    stroke="#9CA3AF" 
                                    name="Previous Year"
                                    strokeWidth={2}
                                    strokeDasharray="5 5"
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
