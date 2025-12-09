import { AlertCircle, Calendar, TrendingUp } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { format, parseISO } from 'date-fns';

// ============================================================================
// Type Definitions
// ============================================================================

interface AlternativeDate {
    start_date: string;
    end_date: string;
    coverage_percentage: number;
    status: 'optimal' | 'acceptable' | 'warning' | 'critical';
}

interface LeaveCoverageWarningProps {
    coveragePercentage: number;
    status: 'optimal' | 'acceptable' | 'warning' | 'critical';
    message: string;
    alternativeDates?: AlternativeDate[];
    teamMembersOnLeave?: Array<{
        name: string;
        leave_type: string;
        dates: string;
    }>;
    onSelectAlternativeDate?: (startDate: string, endDate: string) => void;
}

// ============================================================================
// Coverage Status Configuration
// ============================================================================

const coverageConfig = {
    optimal: {
        label: 'Optimal Coverage',
        color: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
        icon: '🟢',
        cardBorder: 'border-green-200 dark:border-green-800',
    },
    acceptable: {
        label: 'Acceptable Coverage',
        color: 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-800',
        icon: '🟡',
        cardBorder: 'border-yellow-200 dark:border-yellow-800',
    },
    warning: {
        label: 'Low Coverage',
        color: 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800',
        icon: '🟠',
        cardBorder: 'border-orange-200 dark:border-orange-800',
    },
    critical: {
        label: 'Critical Coverage',
        color: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800',
        icon: '🔴',
        cardBorder: 'border-red-200 dark:border-red-800',
    },
};

// ============================================================================
// Main Component
// ============================================================================

export function LeaveCoverageWarning({
    coveragePercentage,
    status,
    message,
    alternativeDates = [],
    teamMembersOnLeave = [],
    onSelectAlternativeDate,
}: LeaveCoverageWarningProps) {
    const config = coverageConfig[status];

    return (
        <div className="space-y-4">
            {/* Coverage Status Card */}
            <Card className={`border-2 ${config.cardBorder}`}>
                <CardContent className="pt-6">
                    <div className="space-y-4">
                        {/* Status Badge and Percentage */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <span className="text-2xl">{config.icon}</span>
                                <div>
                                    <Badge 
                                        variant="outline" 
                                        className={config.color}
                                    >
                                        {config.label}
                                    </Badge>
                                    <div className="text-2xl font-bold mt-1 text-gray-900 dark:text-gray-100">
                                        {coveragePercentage.toFixed(1)}%
                                    </div>
                                </div>
                            </div>
                            <AlertCircle className="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        </div>

                        {/* Coverage Message */}
                        <div className="rounded-md bg-gray-50 dark:bg-gray-800/50 p-4 border border-gray-200 dark:border-gray-700">
                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                {message}
                            </p>
                        </div>

                        {/* Coverage Status Explanation */}
                        <div className="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                            <p className="font-medium">What does this mean?</p>
                            {status === 'optimal' && (
                                <p>Your requested dates have minimal impact on department staffing. Your request should be processed quickly.</p>
                            )}
                            {status === 'acceptable' && (
                                <p>Your requested dates have slight impact on department staffing, but it's manageable. Your request should be approved.</p>
                            )}
                            {status === 'warning' && (
                                <p>Your requested dates significantly impact department staffing. HR may request date adjustments or require additional justification.</p>
                            )}
                            {status === 'critical' && (
                                <p>Your requested dates severely impact department staffing. Please consider alternative dates to improve approval chances.</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Team Members on Leave */}
            {teamMembersOnLeave && teamMembersOnLeave.length > 0 && (
                <Card>
                    <CardContent className="pt-6">
                        <div className="space-y-3">
                            <div className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <Calendar className="h-4 w-4" />
                                <span>Other Team Members on Leave</span>
                            </div>
                            <div className="space-y-2">
                                {teamMembersOnLeave.map((member, index) => (
                                    <div 
                                        key={index}
                                        className="flex items-center justify-between p-3 rounded-md bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700"
                                    >
                                        <div>
                                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {member.name}
                                            </div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                                {member.leave_type}
                                            </div>
                                        </div>
                                        <div className="text-xs text-gray-600 dark:text-gray-400">
                                            {member.dates}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Alternative Dates Suggestions */}
            {alternativeDates && alternativeDates.length > 0 && (
                <Card>
                    <CardContent className="pt-6">
                        <div className="space-y-3">
                            <div className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <TrendingUp className="h-4 w-4" />
                                <span>Recommended Alternative Dates</span>
                            </div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                These dates have better department coverage and higher approval likelihood.
                            </p>
                            <div className="space-y-2">
                                {alternativeDates.map((alt, index) => {
                                    const altConfig = coverageConfig[alt.status];
                                    return (
                                        <div 
                                            key={index}
                                            className="flex items-center justify-between p-3 rounded-md bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700"
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span>{altConfig.icon}</span>
                                                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {format(parseISO(alt.start_date), 'MMM d')} - {format(parseISO(alt.end_date), 'MMM d, yyyy')}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge 
                                                        variant="outline" 
                                                        className={`${altConfig.color} text-xs`}
                                                    >
                                                        {alt.coverage_percentage.toFixed(0)}% Coverage
                                                    </Badge>
                                                </div>
                                            </div>
                                            {onSelectAlternativeDate && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => onSelectAlternativeDate(alt.start_date, alt.end_date)}
                                                    className="ml-2"
                                                >
                                                    Use These Dates
                                                </Button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Important Note for Emergency/Medical Leave */}
            {(status === 'warning' || status === 'critical') && (
                <div className="rounded-md bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
                    <div className="flex gap-3">
                        <AlertCircle className="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div className="text-sm text-blue-700 dark:text-blue-300">
                            <p className="font-medium mb-1">Important Note</p>
                            <p>
                                Emergency and medical leaves are processed regardless of coverage impact. 
                                If your leave is urgent, you may still submit your request with appropriate documentation.
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
