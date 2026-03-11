'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, LineChart, Line } from 'recharts';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { BudgetVarianceData, ForecastProjection } from '@/types/payroll-pages';

interface DeptVarianceGroup {
    department_name: string;
    department_id: string;
    items: BudgetVarianceData[];
    totalBudget: number;
    totalActual: number;
    totalVariance: number;
}

interface GroupedByDeptRecord {
    [key: string]: DeptVarianceGroup;
}

interface BudgetVarianceProps {
    varianceData: BudgetVarianceData[];
    forecastProjections: ForecastProjection[];
}

export function BudgetVariance({
    varianceData,
    forecastProjections,
}: BudgetVarianceProps) {
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    // Group variance data by department
    const groupedByDept = varianceData.reduce((acc: GroupedByDeptRecord, item) => {
        const deptKey = String(item.department_id);
        if (!acc[deptKey]) {
            acc[deptKey] = {
                department_name: item.department_name,
                department_id: deptKey,
                items: [],
                totalBudget: 0,
                totalActual: 0,
                totalVariance: 0,
            };
        }
        acc[deptKey].items.push(item);
        acc[deptKey].totalBudget += item.budgeted_amount;
        acc[deptKey].totalActual += item.actual_amount;
        acc[deptKey].totalVariance += item.variance;
        return acc;
    }, {} as GroupedByDeptRecord);

    const departmentVariance = Object.values(groupedByDept);

    // Prepare data for budget vs actual chart
    const chartData = varianceData.slice(0, 10).map((item) => ({
        name: `${item.department_name} - ${item.component_name}`,
        budgeted: item.budgeted_amount,
        actual: item.actual_amount,
        variance: item.variance,
    }));

    if (varianceData.length === 0 && forecastProjections.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 py-16 text-center">
                <p className="text-lg font-semibold text-gray-500">No Budget & Forecast Data Available</p>
                <p className="mt-1 text-sm text-gray-400">
                    Budget variance and forecast data will appear once payroll calculations exist for this period.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Budget vs Actual - Department Overview */}
            <Card>
                <CardHeader>
                    <CardTitle>Budget vs Actual by Department</CardTitle>
                    <CardDescription>
                        Variance analysis showing budget vs actual labor costs
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={350}>
                        <BarChart data={chartData}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="name" angle={-45} textAnchor="end" height={100} />
                            <YAxis tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                            <Tooltip formatter={(value: number) => formatCurrency(value)} />
                            <Legend />
                            <Bar dataKey="budgeted" fill="#3B82F6" name="Budgeted" />
                            <Bar dataKey="actual" fill="#10B981" name="Actual" />
                        </BarChart>
                    </ResponsiveContainer>
                </CardContent>
            </Card>

            {/* Department-wise Summary */}
            <Card>
                <CardHeader>
                    <CardTitle>Department Variance Summary</CardTitle>
                    <CardDescription>
                        Overall budget performance by department
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {departmentVariance.map((dept) => {
                            const variancePercent = (dept.totalVariance / dept.totalBudget) * 100;
                            const isFavorable = dept.totalVariance <= 0;

                            return (
                                <div key={dept.department_id} className="rounded-lg border border-gray-200 p-4">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h4 className="font-semibold text-gray-900">{dept.department_name}</h4>
                                            <div className="mt-2 grid grid-cols-3 gap-4">
                                                <div>
                                                    <p className="text-xs text-gray-500">Budgeted</p>
                                                    <p className="font-semibold text-gray-900">{formatCurrency(dept.totalBudget)}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-gray-500">Actual</p>
                                                    <p className="font-semibold text-gray-900">{formatCurrency(dept.totalActual)}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-gray-500">Variance</p>
                                                    <p className={`font-semibold ${isFavorable ? 'text-green-600' : 'text-red-600'}`}>
                                                        {formatCurrency(dept.totalVariance)}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <Badge variant={isFavorable ? 'default' : 'destructive'}>
                                                {isFavorable ? 'Favorable' : 'Unfavorable'}
                                            </Badge>
                                            <p className={`mt-2 text-lg font-bold ${isFavorable ? 'text-green-600' : 'text-red-600'}`}>
                                                {variancePercent > 0 ? '+' : ''}{variancePercent.toFixed(1)}%
                                            </p>
                                        </div>
                                    </div>

                                    {/* Component Details */}
                                    <div className="mt-4 space-y-2 border-t border-gray-100 pt-4">
                                        {dept.items.slice(0, 3).map((item: BudgetVarianceData) => (
                                            <div key={item.component_name} className="flex items-center justify-between text-sm">
                                                <span className="text-gray-600">{item.component_name}</span>
                                                <div className="flex items-center gap-3">
                                                    <span className="text-gray-900">{formatCurrency(item.variance)}</span>
                                                    {item.variance_status === 'favorable' ? (
                                                        <CheckCircle className="h-4 w-4 text-green-500" />
                                                    ) : item.variance_status === 'unfavorable' ? (
                                                        <AlertCircle className="h-4 w-4 text-red-500" />
                                                    ) : (
                                                        <div className="h-4 w-4 rounded-full bg-gray-300" />
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            {/* Forecast Projections */}
            {forecastProjections.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Labor Cost Forecast</CardTitle>
                        <CardDescription>
                            Projected labor costs for upcoming periods
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={350}>
                            <LineChart data={forecastProjections}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="month_label" />
                                <YAxis tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`} />
                                <Tooltip formatter={(value: number) => formatCurrency(value)} />
                                <Legend />
                                <Line 
                                    type="monotone" 
                                    dataKey="projected_labor_cost" 
                                    stroke="#3B82F6" 
                                    name="Total Projected Cost"
                                    strokeWidth={2}
                                    dot={{ r: 4 }}
                                />
                                <Line 
                                    type="monotone" 
                                    dataKey="projected_basic_salary" 
                                    stroke="#10B981" 
                                    name="Basic Salary"
                                    strokeWidth={2}
                                    strokeDasharray="5 5"
                                />
                                <Line 
                                    type="monotone" 
                                    dataKey="projected_allowances" 
                                    stroke="#F59E0B" 
                                    name="Allowances"
                                    strokeWidth={2}
                                    strokeDasharray="5 5"
                                />
                            </LineChart>
                        </ResponsiveContainer>

                        {/* Forecast Confidence Cards */}
                        <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                            {forecastProjections.slice(0, 3).map((forecast) => (
                                <div key={forecast.month} className="rounded-lg border border-gray-200 p-4">
                                    <p className="text-sm font-medium text-gray-600">{forecast.month_label}</p>
                                    <p className="mt-2 text-2xl font-bold text-gray-900">
                                        {formatCurrency(forecast.projected_labor_cost)}
                                    </p>
                                    <div className="mt-3 flex items-center gap-2">
                                        <div
                                            className={`h-2 w-2 rounded-full ${
                                                forecast.confidence_level === 'high'
                                                    ? 'bg-green-500'
                                                    : forecast.confidence_level === 'medium'
                                                      ? 'bg-yellow-500'
                                                      : 'bg-red-500'
                                            }`}
                                        />
                                        <span className="text-xs font-semibold uppercase text-gray-600">
                                            {forecast.confidence_level} confidence
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
