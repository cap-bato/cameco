import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    LineChart,
    Line,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { Download, Calendar, Filter } from 'lucide-react';

interface SeparationTrend {
    month: string;
    month_key: string;
    count: number;
}

interface ExitReason {
    reason: string;
    count: number;
    percentage: number;
}

interface SatisfactionScores {
    overall: number;
    environment: number;
    management: number;
    compensation: number;
    growth: number;
    balance: number;
    total_respondents: number;
    chart_data: Array<{ category: string; score: number }>;
}

interface RetentionInsights {
    total_separations: number;
    voluntary_separations: number;
    involuntary_separations: number;
    voluntary_percentage: number;
    involuntary_percentage: number;
    average_tenure_years: number;
    average_tenure_months: number;
    rehire_eligible_count: number;
    rehire_eligible_percentage: number;
}

interface OffboardingEfficiency {
    average_days_to_complete: number;
    average_doc_gen_days: number;
    bottleneck_approvers: Array<{
        approver_id: number;
        avg_days: number;
        total_items: number;
    }>;
}

interface DepartmentAnalytic {
    department_id: number;
    department_name: string;
    employee_count: number;
    separation_count: number;
    separation_rate: number;
}

interface SeparationTypeDistribution {
    type: string;
    type_key: string;
    count: number;
    percentage: number;
}

interface AnalyticsPageProps {
    separationTrends: SeparationTrend[];
    exitReasons: ExitReason[];
    satisfactionScores: SatisfactionScores;
    retentionInsights: RetentionInsights;
    offboardingEfficiency: OffboardingEfficiency;
    departmentAnalytics: DepartmentAnalytic[];
    separationTypeDistribution: SeparationTypeDistribution[];
    dateRange: string;
    department: string | null;
    separationType: string | null;
    startDate: string;
    endDate: string;
    departments: Array<{ id: number; name: string }>;
    separationTypes: string[];
}

const COLORS = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'HR',
        href: '/hr/dashboard',
    },
    {
        title: 'Offboarding',
        href: '/hr/offboarding/cases',
    },
    {
        title: 'Exit Analytics',
        href: '/hr/offboarding/analytics',
    },
];

export default function OffboardingAnalytics({
    separationTrends,
    exitReasons,
    satisfactionScores,
    retentionInsights,
    offboardingEfficiency,
    departmentAnalytics,
    separationTypeDistribution,
    dateRange: initialDateRange,
    department: initialDepartment,
    separationType: initialSepType,
    startDate,
    endDate,
    departments,
    separationTypes,
}: AnalyticsPageProps) {
    const [dateRange, setDateRange] = useState(initialDateRange);
    const [selectedDepartment, setSelectedDepartment] = useState(initialDepartment);
    const [selectedSepType, setSelectedSepType] = useState(initialSepType);

    const handleFilterChange = () => {
        const params = {
            date_range: dateRange,
            ...(selectedDepartment && { department: selectedDepartment }),
            ...(selectedSepType && { separation_type: selectedSepType }),
        };

        router.get(route('hr.offboarding.analytics'), params, {
            preserveScroll: true,
        });
    };

    const handleExport = (format: 'pdf' | 'excel') => {
        // Handle export logic
        console.log(`Exporting as ${format}`);
        // TODO: Implement export functionality
    };

    // Calculate totals for exit reasons
    const totalReasons = exitReasons.reduce((sum, r) => sum + r.count, 0);
    const exitReasonsWithPercentage = exitReasons.map(r => ({
        ...r,
        percentage: totalReasons > 0 ? ((r.count / totalReasons) * 100).toFixed(1) : 0,
    }));

    // Calculate percentages for separation types
    const totalSeparations = separationTypeDistribution.reduce((sum, s) => sum + s.count, 0);
    const sepTypesWithPercentage = separationTypeDistribution.map(s => ({
        ...s,
        percentage: totalSeparations > 0 ? ((s.count / totalSeparations) * 100).toFixed(1) : 0,
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exit Analytics & Reports" />

            {/* Header and Filters */}
            <div className="mb-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Exit Analytics & Reports</h1>
                        <p className="text-gray-600 mt-2">
                            Comprehensive offboarding and separation analytics
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            onClick={() => handleExport('pdf')}
                            variant="outline"
                            size="sm"
                            className="gap-2"
                        >
                            <Download className="w-4 h-4" />
                            PDF
                        </Button>
                        <Button
                            onClick={() => handleExport('excel')}
                            variant="outline"
                            size="sm"
                            className="gap-2"
                        >
                            <Download className="w-4 h-4" />
                            Excel
                        </Button>
                    </div>
                </div>

                {/* Filter Controls */}
                <Card className="bg-gray-50 border-0">
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    <Calendar className="inline-block w-4 h-4 mr-2" />
                                    Date Range
                                </label>
                                <select
                                    value={dateRange}
                                    onChange={(e) => setDateRange(e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="last_30_days">Last 30 Days</option>
                                    <option value="last_90_days">Last 90 Days</option>
                                    <option value="last_6_months">Last 6 Months</option>
                                    <option value="last_12_months">Last 12 Months</option>
                                    <option value="this_year">This Year</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Department
                                </label>
                                <select
                                    value={selectedDepartment || ''}
                                    onChange={(e) => setSelectedDepartment(e.target.value || null)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="">All Departments</option>
                                    {departments.map((dept) => (
                                        <option key={dept.id} value={dept.id}>
                                            {dept.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Separation Type
                                </label>
                                <select
                                    value={selectedSepType || ''}
                                    onChange={(e) => setSelectedSepType(e.target.value || null)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                >
                                    <option value="">All Types</option>
                                    {separationTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {type.replace(/_/g, ' ').charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ')}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-end">
                                <Button
                                    onClick={handleFilterChange}
                                    className="w-full gap-2"
                                >
                                    <Filter className="w-4 h-4" />
                                    Apply Filters
                                </Button>
                            </div>
                        </div>
                        <p className="text-xs text-gray-500 mt-4">
                            Date Range: {startDate} to {endDate}
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Key Metrics Summary */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-600 mb-2">Total Separations</p>
                            <p className="text-3xl font-bold text-gray-900">
                                {retentionInsights.total_separations}
                            </p>
                            <p className="text-xs text-gray-500 mt-2">Period Total</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-600 mb-2">Avg Completion Time</p>
                            <p className="text-3xl font-bold text-gray-900">
                                {offboardingEfficiency.average_days_to_complete}
                            </p>
                            <p className="text-xs text-gray-500 mt-2">days</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-600 mb-2">Avg Tenure</p>
                            <p className="text-3xl font-bold text-gray-900">
                                {retentionInsights.average_tenure_years}
                            </p>
                            <p className="text-xs text-gray-500 mt-2">years</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-600 mb-2">Rehire Eligible</p>
                            <p className="text-3xl font-bold text-gray-900">
                                {retentionInsights.rehire_eligible_percentage}%
                            </p>
                            <p className="text-xs text-gray-500 mt-2">
                                {retentionInsights.rehire_eligible_count} employees
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Charts Row 1 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {/* Separation Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle>Separation Trends</CardTitle>
                        <CardDescription>Monthly separation count over time</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {separationTrends.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={separationTrends}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" angle={-45} textAnchor="end" height={80} />
                                    <YAxis />
                                    <Tooltip />
                                    <Line
                                        type="monotone"
                                        dataKey="count"
                                        stroke="#3b82f6"
                                        name="Separations"
                                        strokeWidth={2}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        ) : (
                            <p className="text-center text-gray-500 py-8">No data available</p>
                        )}
                    </CardContent>
                </Card>

                {/* Separation Type Distribution */}
                <Card>
                    <CardHeader>
                        <CardTitle>Separation Type Distribution</CardTitle>
                        <CardDescription>Breakdown by separation type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {sepTypesWithPercentage.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <PieChart>
                                    <Pie
                                        data={sepTypesWithPercentage}
                                        cx="50%"
                                        cy="50%"
                                        labelLine={false}
                                        label={({ name, value }) => {
                                            const item = sepTypesWithPercentage.find(
                                                (s) => s.count === value
                                            );
                                            return item
                                                ? `${item.type} (${item.percentage}%)`
                                                : `${name}`;
                                        }}
                                        outerRadius={100}
                                        fill="#8884d8"
                                        dataKey="count"
                                    >
                                        {sepTypesWithPercentage.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip
                                        formatter={(value: number) => String(value)}
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <p className="text-center text-gray-500 py-8">No data available</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Charts Row 2 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {/* Exit Reasons */}
                <Card>
                    <CardHeader>
                        <CardTitle>Top Exit Reasons</CardTitle>
                        <CardDescription>Primary reasons for leaving (top 10)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {exitReasonsWithPercentage.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart
                                    data={exitReasonsWithPercentage}
                                    layout="vertical"
                                    margin={{ top: 5, right: 30, left: 300, bottom: 5 }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis type="number" />
                                    <YAxis dataKey="reason" type="category" width={290} />
                                    <Tooltip />
                                    <Bar dataKey="count" fill="#ef4444" name="Count" />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <p className="text-center text-gray-500 py-8">No data available</p>
                        )}
                    </CardContent>
                </Card>

                {/* Satisfaction Scores */}
                <Card>
                    <CardHeader>
                        <CardTitle>Satisfaction Ratings</CardTitle>
                        <CardDescription>
                            Average ratings by category ({satisfactionScores.total_respondents} respondents)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {satisfactionScores.chart_data.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={satisfactionScores.chart_data}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="category" angle={-45} textAnchor="end" height={80} />
                                    <YAxis domain={[0, 5]} />
                                    <Tooltip />
                                    <Bar dataKey="score" fill="#10b981" name="Rating" />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <p className="text-center text-gray-500 py-8">No data available</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Charts Row 3 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {/* Voluntary vs Involuntary */}
                <Card>
                    <CardHeader>
                        <CardTitle>Separation Type Breakdown</CardTitle>
                        <CardDescription>Voluntary vs. Involuntary separations</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <div className="flex justify-between mb-2">
                                    <span className="text-sm font-medium">Voluntary</span>
                                    <Badge variant="outline">
                                        {retentionInsights.voluntary_separations} (
                                        {retentionInsights.voluntary_percentage}%)
                                    </Badge>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className="bg-blue-500 h-2 rounded-full"
                                        style={{
                                            width: `${retentionInsights.voluntary_percentage}%`,
                                        }}
                                    />
                                </div>
                            </div>

                            <div>
                                <div className="flex justify-between mb-2">
                                    <span className="text-sm font-medium">Involuntary</span>
                                    <Badge variant="outline">
                                        {retentionInsights.involuntary_separations} (
                                        {retentionInsights.involuntary_percentage}%)
                                    </Badge>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className="bg-red-500 h-2 rounded-full"
                                        style={{
                                            width: `${retentionInsights.involuntary_percentage}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Department Analytics */}
                <Card>
                    <CardHeader>
                        <CardTitle>Department Separation Rates</CardTitle>
                        <CardDescription>Separation rate by department</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {departmentAnalytics.length > 0 ? (
                            <div className="space-y-4 max-h-80 overflow-y-auto">
                                {departmentAnalytics.map((dept) => (
                                    <div key={dept.department_id}>
                                        <div className="flex justify-between mb-1 text-sm">
                                            <span className="font-medium">{dept.department_name}</span>
                                            <span className="text-gray-600">
                                                {dept.separation_count}/{dept.employee_count} (
                                                {dept.separation_rate}%)
                                            </span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-orange-500 h-2 rounded-full"
                                                style={{
                                                    width: `${Math.min(dept.separation_rate, 100)}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-gray-500 py-8">No data available</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Summary Stats */}
            <Card>
                <CardHeader>
                    <CardTitle>Offboarding Efficiency Summary</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p className="text-sm text-gray-600 mb-2">Avg Days to Complete</p>
                            <p className="text-2xl font-bold">
                                {offboardingEfficiency.average_days_to_complete} days
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-600 mb-2">Avg Doc Generation Time</p>
                            <p className="text-2xl font-bold">
                                {offboardingEfficiency.average_doc_gen_days} days
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-600 mb-2">Satisfaction Score</p>
                            <p className="text-2xl font-bold">
                                {satisfactionScores.overall.toFixed(1)}/5.0
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
