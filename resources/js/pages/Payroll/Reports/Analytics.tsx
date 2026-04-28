import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import type { LaborCostAnalyticsPageProps } from '@/types/payroll-pages';
import { CostTrendCharts } from '@/components/payroll/cost-trend-charts';
import { BudgetVariance } from '@/components/payroll/budget-variance';
import { EmployeeCostAnalysisComponent } from '@/components/payroll/employee-cost-analysis';
import { Button } from '@/components/ui/button';
import { Download, RefreshCw } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: '/payroll/reports',
    },
    {
        title: 'Analytics',
        href: '/payroll/reports/analytics',
    },
];

export default function LaborCostAnalytics({
    cost_trend_data,
    department_comparisons,
    component_breakdown,
    yoy_comparisons,
    employee_cost_analysis,
    budget_variance_data,
    forecast_projections,
    analytics_summary,
    selected_period,
    available_periods,
}: LaborCostAnalyticsPageProps) {
    const [activeTab, setActiveTab] = useState<'overview' | 'cost_trends' | 'budget' | 'employees'>('overview');

    const hasData = analytics_summary.total_employees > 0;

    const handlePeriodChange = (period: string) => {
        router.get('/payroll/reports/analytics', { period }, { preserveState: false });
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Labor Cost Analytics" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight">Labor Cost Analytics</h1>
                        <p className="text-muted-foreground">
                            Comprehensive payroll cost analysis, trends, and forecasting
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <select
                            value={selected_period}
                            onChange={(e) => handlePeriodChange(e.target.value)}
                            className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            {available_periods.map((p) => (
                                <option key={p} value={p}>{p}</option>
                            ))}
                        </select>
                        <Button variant="outline" size="sm">
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                        <Button size="sm">
                            <Download className="h-4 w-4 mr-2" />
                            Export Report
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                {!hasData ? (
                    <div className="rounded-lg border border-dashed border-gray-300 p-8 text-center">
                        <p className="text-lg font-semibold text-gray-500">No Payroll Data for {selected_period}</p>
                        <p className="mt-1 text-sm text-gray-400">Select a different period or run payroll calculations to see analytics.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm font-medium text-gray-600">Total Labor Cost</p>
                            <p className="mt-2 text-2xl font-bold text-gray-900">
                                {formatCurrency(analytics_summary.total_labor_cost)}
                            </p>
                            <p className="mt-1 text-xs text-gray-500">Current period</p>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm font-medium text-gray-600">Avg Monthly Cost</p>
                            <p className="mt-2 text-2xl font-bold text-gray-900">
                                {formatCurrency(analytics_summary.average_monthly_cost)}
                            </p>
                            <p className="mt-1 text-xs text-gray-500">Running average</p>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm font-medium text-gray-600">Employees</p>
                            <p className="mt-2 text-2xl font-bold text-gray-900">
                                {analytics_summary.total_employees}
                            </p>
                            <p className="mt-1 text-xs text-gray-500">Active employees</p>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm font-medium text-gray-600">Cost per Employee</p>
                            <p className="mt-2 text-2xl font-bold text-gray-900">
                                {formatCurrency(analytics_summary.average_cost_per_employee)}
                            </p>
                            <p className="mt-1 text-xs text-gray-500">Monthly average</p>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm font-medium text-gray-600">vs Last Year</p>
                            <p className={`mt-2 text-2xl font-bold ${analytics_summary.trend_vs_last_year >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                                {analytics_summary.trend_vs_last_year > 0 ? '+' : ''}{analytics_summary.trend_vs_last_year.toFixed(1)}%
                            </p>
                            <p className="mt-1 text-xs text-gray-500">Year-over-year change</p>
                        </div>
                    </div>
                )}

                {/* Top Insights */}
                {hasData && (
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <h3 className="font-semibold text-blue-900">Key Insights</h3>
                        <ul className="mt-2 space-y-2 text-sm text-blue-800">
                            <li>
                                • <strong>{analytics_summary.largest_cost_component}</strong> is the largest cost component at{' '}
                                <strong>{analytics_summary.largest_cost_component_percentage.toFixed(1)}%</strong> of total labor cost
                            </li>
                            <li>
                                • <strong>{analytics_summary.highest_cost_department}</strong> department has the highest costs at{' '}
                                <strong>{formatCurrency(analytics_summary.highest_cost_department_amount)}</strong>
                            </li>
                            <li>
                                • Labor costs have {analytics_summary.trend_vs_last_period >= 0 ? 'increased' : 'decreased'} by{' '}
                                <strong>{Math.abs(analytics_summary.trend_vs_last_period).toFixed(1)}%</strong> compared to last period
                            </li>
                        </ul>
                    </div>
                )}

                {/* Tabs */}
                <div className="flex gap-2 border-b border-gray-200">
                    <button
                        onClick={() => setActiveTab('overview')}
                        className={`px-4 py-2 font-medium ${
                            activeTab === 'overview'
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Overview
                    </button>
                    <button
                        onClick={() => setActiveTab('cost_trends')}
                        className={`px-4 py-2 font-medium ${
                            activeTab === 'cost_trends'
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Cost Trends
                    </button>
                    <button
                        onClick={() => setActiveTab('budget')}
                        className={`px-4 py-2 font-medium ${
                            activeTab === 'budget'
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Budget & Forecast
                    </button>
                    <button
                        onClick={() => setActiveTab('employees')}
                        className={`px-4 py-2 font-medium ${
                            activeTab === 'employees'
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Employee Analysis
                    </button>
                </div>

                {/* Tab Content */}
                <div className="mt-6">
                {activeTab === 'overview' && (
                    <div className="space-y-6">
                        {/* hide YoY on overview */}
                        <CostTrendCharts
                            monthlyTrends={cost_trend_data}
                            departmentComparisons={department_comparisons}
                            componentBreakdown={component_breakdown}
                            yoyComparisons={[]}
                        />
                    </div>
                )}

                    {activeTab === 'cost_trends' && (
                        <CostTrendCharts
                            monthlyTrends={cost_trend_data}
                            departmentComparisons={department_comparisons}
                            componentBreakdown={component_breakdown}
                            yoyComparisons={yoy_comparisons}
                        />
                    )}

                    {activeTab === 'budget' && (
                        <BudgetVariance
                            varianceData={budget_variance_data}
                            forecastProjections={forecast_projections}
                        />
                    )}

                    {activeTab === 'employees' && (
                        <EmployeeCostAnalysisComponent
                            employees={employee_cost_analysis}
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
