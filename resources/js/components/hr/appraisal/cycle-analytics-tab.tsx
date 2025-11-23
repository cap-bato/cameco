import React, { useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CycleAnalytics } from '@/types/appraisal-pages';

interface CycleAnalyticsTabProps {
    analytics: CycleAnalytics;
}

export function CycleAnalyticsTab({ analytics }: CycleAnalyticsTabProps) {
    // Calculate performance category distribution
    const performanceDistribution = useMemo(() => {
        return {
            high: analytics.high_performers || 0,
            medium: analytics.medium_performers || 0,
            low: analytics.low_performers || 0,
        };
    }, [analytics]);

    // Get max value for chart scaling
    const maxCount = Math.max(...Object.values(performanceDistribution));

    // Get department performance
    const departmentPerformance = useMemo(() => {
        return analytics.department_breakdown || [];
    }, [analytics]);

    return (
        <div className="space-y-6">
            {/* Key Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium">Average Score</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {analytics.average_score
                                ? Number(analytics.average_score).toFixed(1)
                                : 'N/A'}
                        </div>
                        <p className="text-xs text-gray-500 mt-1">out of 10</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium">Completion Rate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {analytics.completion_rate
                                ? Number(analytics.completion_rate).toFixed(0)
                                : '0'}
                            %
                        </div>
                        <p className="text-xs text-gray-500 mt-1">
                            {analytics.completed_appraisals} of {analytics.total_appraisals} completed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium">High Performers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-green-600">
                            {performanceDistribution.high}
                        </div>
                        <p className="text-xs text-gray-500 mt-1">score 8+</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium">Needs Improvement</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-red-600">
                            {performanceDistribution.low}
                        </div>
                        <p className="text-xs text-gray-500 mt-1">score below 6</p>
                    </CardContent>
                </Card>
            </div>

            {/* Performance Distribution */}
            <Card>
                <CardHeader>
                    <CardTitle>Performance Distribution</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {[
                            { label: 'High Performers', value: performanceDistribution.high, color: 'bg-green-600' },
                            { label: 'Satisfactory', value: performanceDistribution.medium, color: 'bg-yellow-600' },
                            { label: 'Needs Improvement', value: performanceDistribution.low, color: 'bg-red-600' },
                        ].map((category) => (
                            <div key={category.label}>
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">{category.label}</span>
                                    <span className="text-sm text-gray-600">{category.value}</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className={`${category.color} h-2 rounded-full transition-all`}
                                        style={{
                                            width:
                                                maxCount > 0
                                                    ? `${(category.value / maxCount) * 100}%`
                                                    : '0%',
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Department Performance Comparison */}
            {departmentPerformance.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Department Performance Comparison</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {departmentPerformance.map((dept) => (
                                <div key={dept.id} className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="font-medium">{dept.name}</p>
                                            <p className="text-xs text-gray-500">
                                                {dept.appraised_employees} of {dept.total_employees} appraised
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold">
                                                {Number(dept.average_score).toFixed(1)}
                                            </p>
                                            <p className="text-xs text-gray-500">avg score</p>
                                        </div>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-3">
                                        <div
                                            className={`h-3 rounded-full transition-all ${
                                                Number(dept.average_score) >= 8
                                                    ? 'bg-green-500'
                                                    : Number(dept.average_score) >= 6
                                                      ? 'bg-yellow-500'
                                                      : 'bg-red-500'
                                            }`}
                                            style={{
                                                width: `${(Number(dept.average_score) / 10) * 100}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}


            {/* Trend Information */}
            {analytics.completion_rate === 100 && (
                <Card className="bg-green-50 border-green-200">
                    <CardHeader>
                        <CardTitle className="text-green-900">Cycle Complete</CardTitle>
                    </CardHeader>
                    <CardContent className="text-green-800">
                        <p>
                            All appraisals have been completed for this cycle. You can now close the
                            cycle and proceed with rehire recommendations and payroll adjustments.
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
