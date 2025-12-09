import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Calendar,
    Clock,
    TrendingUp,
    AlertCircle,
    Info,
    Plus,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';

// ============================================================================
// Type Definitions
// ============================================================================

interface LeaveTypeBalance {
    id: number;
    leave_type: string;
    leave_type_code: string;
    total_entitled: number;
    used: number;
    pending: number;
    available: number;
    accrual_method: 'monthly' | 'annual' | 'fixed';
    accrual_rate: number | null;
    carryover_allowed: boolean;
    carryover_limit: number | null;
    expiry_date: string | null;
    cash_conversion_allowed: boolean;
    description: string | null;
}

interface LeaveBalancesProps {
    balances: LeaveTypeBalance[];
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
        department: string;
        date_hired: string;
    };
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/employee/dashboard',
    },
    {
        title: 'Leave Management',
        href: '/employee/leave/balances',
    },
    {
        title: 'Leave Balances',
        href: '/employee/leave/balances',
    },
];

// ============================================================================
// Helper Functions
// ============================================================================

const getAccrualMethodLabel = (method: string): string => {
    const labels: Record<string, string> = {
        monthly: 'Accrued Monthly',
        annual: 'Granted Annually',
        fixed: 'Fixed Entitlement',
    };
    return labels[method] || method;
};

const getBalanceStatus = (available: number, total: number) => {
    const percentage = (available / total) * 100;
    
    if (percentage >= 75) {
        return { color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', label: 'Healthy' };
    } else if (percentage >= 50) {
        return { color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', label: 'Moderate' };
    } else if (percentage >= 25) {
        return { color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', label: 'Low' };
    } else {
        return { color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', label: 'Critical' };
    }
};

// ============================================================================
// Main Component
// ============================================================================

export default function LeaveBalances({
    balances,
    employee,
    error,
}: LeaveBalancesProps) {
    // Calculate total statistics
    const totalStats = {
        totalEntitled: balances.reduce((sum, b) => sum + b.total_entitled, 0),
        totalUsed: balances.reduce((sum, b) => sum + b.used, 0),
        totalPending: balances.reduce((sum, b) => sum + b.pending, 0),
        totalAvailable: balances.reduce((sum, b) => sum + b.available, 0),
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Balances" />

            <div className="mb-6 space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Leave Balances
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            View your leave entitlements and available balances
                        </p>
                    </div>
                    <Button asChild>
                        <a href="/employee/leave/request">
                            <Plus className="mr-2 h-4 w-4" />
                            Apply for Leave
                        </a>
                    </Button>
                </div>
            </div>

            <div className="space-y-6 p-6">
                {/* Error Message */}
                {error && (
                    <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/10">
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2 text-red-800 dark:text-red-200">
                                <AlertCircle className="h-5 w-5" />
                                <p className="text-sm font-medium">{error}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Employee Info Card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                                <Calendar className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    {employee.full_name}
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {employee.employee_number} • {employee.department}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Overall Leave Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="h-5 w-5 text-green-600 dark:text-green-400" />
                            Overall Leave Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                                <p className="text-sm text-blue-600 dark:text-blue-400">Total Entitled</p>
                                <p className="mt-2 text-2xl font-bold text-blue-800 dark:text-blue-200">
                                    {totalStats.totalEntitled} days
                                </p>
                            </div>
                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/10">
                                <p className="text-sm text-red-600 dark:text-red-400">Used</p>
                                <p className="mt-2 text-2xl font-bold text-red-800 dark:text-red-200">
                                    {totalStats.totalUsed} days
                                </p>
                            </div>
                            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-900/10">
                                <p className="text-sm text-yellow-600 dark:text-yellow-400">Pending</p>
                                <p className="mt-2 text-2xl font-bold text-yellow-800 dark:text-yellow-200">
                                    {totalStats.totalPending} days
                                </p>
                            </div>
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-900/10">
                                <p className="text-sm text-green-600 dark:text-green-400">Available</p>
                                <p className="mt-2 text-2xl font-bold text-green-800 dark:text-green-200">
                                    {totalStats.totalAvailable} days
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Leave Balances by Type */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            Leave Balances by Type
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {balances.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Calendar className="h-12 w-12 text-gray-400" />
                                <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                    No leave balances available
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {balances.map((balance) => {
                                    const status = getBalanceStatus(balance.available, balance.total_entitled);
                                    
                                    return (
                                        <div 
                                            key={balance.id}
                                            className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                                        >
                                            {/* Leave Type Header */}
                                            <div className="mb-4 flex items-start justify-between">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                                            {balance.leave_type}
                                                        </h3>
                                                        <Badge className={status.color}>
                                                            {status.label}
                                                        </Badge>
                                                    </div>
                                                    {balance.description && (
                                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                            {balance.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Balance Breakdown */}
                                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                                <div>
                                                    <p className="text-xs text-gray-600 dark:text-gray-400">Total Entitled</p>
                                                    <p className="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                                        {balance.total_entitled}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-gray-600 dark:text-gray-400">Used</p>
                                                    <p className="mt-1 text-xl font-bold text-red-600 dark:text-red-400">
                                                        {balance.used}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-gray-600 dark:text-gray-400">Pending</p>
                                                    <p className="mt-1 text-xl font-bold text-yellow-600 dark:text-yellow-400">
                                                        {balance.pending}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-gray-600 dark:text-gray-400">Available</p>
                                                    <p className="mt-1 text-xl font-bold text-green-600 dark:text-green-400">
                                                        {balance.available}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Leave Policy Details */}
                                            <div className="mt-4 space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-600 dark:text-gray-400">Accrual Method:</span>
                                                    <span className="font-medium text-gray-900 dark:text-white">
                                                        {getAccrualMethodLabel(balance.accrual_method)}
                                                    </span>
                                                </div>
                                                
                                                {balance.accrual_rate && (
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="text-gray-600 dark:text-gray-400">Accrual Rate:</span>
                                                        <span className="font-medium text-gray-900 dark:text-white">
                                                            {balance.accrual_rate} days/month
                                                        </span>
                                                    </div>
                                                )}

                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-600 dark:text-gray-400">Carryover:</span>
                                                    <span className="font-medium text-gray-900 dark:text-white">
                                                        {balance.carryover_allowed 
                                                            ? balance.carryover_limit 
                                                                ? `Up to ${balance.carryover_limit} days`
                                                                : 'Unlimited'
                                                            : 'Not Allowed'}
                                                    </span>
                                                </div>

                                                {balance.expiry_date && (
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="text-gray-600 dark:text-gray-400">Expiry Date:</span>
                                                        <span className="font-medium text-gray-900 dark:text-white">
                                                            {format(parseISO(balance.expiry_date), 'MMMM dd, yyyy')}
                                                        </span>
                                                    </div>
                                                )}

                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-600 dark:text-gray-400">Cash Conversion:</span>
                                                    <span className="font-medium text-gray-900 dark:text-white">
                                                        {balance.cash_conversion_allowed ? 'Allowed' : 'Not Allowed'}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Low Balance Warning */}
                                            {balance.available < 5 && balance.available > 0 && (
                                                <div className="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-900 dark:bg-yellow-900/10">
                                                    <div className="flex items-center gap-2">
                                                        <AlertCircle className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                                                        <p className="text-sm text-yellow-800 dark:text-yellow-200">
                                                            Low balance: Only {balance.available} days remaining
                                                        </p>
                                                    </div>
                                                </div>
                                            )}

                                            {/* No Balance Warning */}
                                            {balance.available === 0 && (
                                                <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-900/10">
                                                    <div className="flex items-center gap-2">
                                                        <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                                        <p className="text-sm text-red-800 dark:text-red-200">
                                                            No {balance.leave_type.toLowerCase()} days available
                                                        </p>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Info Box */}
                <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-900/10">
                    <CardContent className="pt-6">
                        <div className="flex gap-3">
                            <Info className="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <h4 className="font-semibold text-blue-900 dark:text-blue-100 text-sm mb-2">
                                    About Leave Balances
                                </h4>
                                <ul className="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                                    <li>• <strong>Entitled:</strong> Total days you can take per year</li>
                                    <li>• <strong>Used:</strong> Days already taken and approved</li>
                                    <li>• <strong>Pending:</strong> Days in submitted requests awaiting approval</li>
                                    <li>• <strong>Available:</strong> Days you can currently request</li>
                                    <li>• Balances are updated in real-time when requests are approved/rejected</li>
                                    <li>• Some leave types may expire at year-end if not used</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
