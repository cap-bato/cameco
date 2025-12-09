import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Bell,
    Filter,
    CheckCheck,
    Trash2,
    Inbox,
} from 'lucide-react';
import { useState } from 'react';
import { NotificationItem } from '@/components/employee/notification-item';
import axios from 'axios';

// ============================================================================
// Type Definitions
// ============================================================================

interface Notification {
    id: number;
    type: 'leave' | 'payroll' | 'attendance' | 'system';
    title: string;
    message: string;
    timestamp: string;
    read: boolean;
}

interface NotificationStats {
    total: number;
    unread: number;
    leave: number;
    payroll: number;
    attendance: number;
    system: number;
}

interface NotificationsIndexProps {
    notifications: Notification[];
    stats: NotificationStats;
    filters: {
        type?: string;
    };
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
        department: string;
    };
}

// ============================================================================
// Main Component
// ============================================================================

export default function NotificationsIndex({
    notifications,
    stats,
    filters,
    employee,
}: NotificationsIndexProps) {
    const [selectedType, setSelectedType] = useState<string>(filters.type || 'all');
    const [optimisticNotifications, setOptimisticNotifications] = useState<Notification[]>(Array.isArray(notifications) ? notifications : []);

    // Handle filter change
    const handleFilterChange = () => {
        router.get(
            '/employee/notifications',
            {
                type: selectedType === 'all' ? undefined : selectedType,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    // Handle mark as read
    const handleMarkAsRead = async (id: number) => {
        // Optimistic update
        setOptimisticNotifications((prev) =>
            prev.map((n) => (n.id === id ? { ...n, read: true } : n))
        );

        try {
            await axios.post(`/employee/notifications/${id}/mark-read`);
            // Refresh data
            router.reload({ only: ['notifications', 'stats'] });
        } catch (error) {
            console.error('Failed to mark notification as read', error);
            // Revert optimistic update on error
            setOptimisticNotifications(notifications);
        }
    };

    // Handle mark all as read
    const handleMarkAllAsRead = async () => {
        // Optimistic update
        setOptimisticNotifications((prev) =>
            prev.map((n) => ({ ...n, read: true }))
        );

        try {
            await axios.post('/employee/notifications/mark-all-read');
            // Refresh data
            router.reload({ only: ['notifications', 'stats'] });
        } catch (error) {
            console.error('Failed to mark all notifications as read', error);
            // Revert optimistic update on error
            setOptimisticNotifications(notifications);
        }
    };

    // Handle delete
    const handleDelete = async (id: number) => {
        // Optimistic update
        setOptimisticNotifications((prev) => prev.filter((n) => n.id !== id));

        try {
            await axios.delete(`/employee/notifications/${id}`);
            // Refresh data
            router.reload({ only: ['notifications', 'stats'] });
        } catch (error) {
            console.error('Failed to delete notification', error);
            // Revert optimistic update on error
            setOptimisticNotifications(notifications);
        }
    };

    // Handle delete all
    const handleDeleteAll = async () => {
        if (!confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
            return;
        }

        // Optimistic update
        setOptimisticNotifications([]);

        try {
            await axios.delete('/employee/notifications/delete-all');
            // Refresh data
            router.reload({ only: ['notifications', 'stats'] });
        } catch (error) {
            console.error('Failed to delete all notifications', error);
            // Revert optimistic update on error
            setOptimisticNotifications(notifications);
        }
    };

    return (
        <AppLayout>
            <Head title="Notifications" />

            <div className="space-y-6 p-6">
            {/* Page Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <Bell className="h-6 w-6" />
                    Notifications
                </h1>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Stay updated with your leave requests, payslips, and attendance alerts
                </p>
            </div>

            {/* Employee Info Card */}
            <Card className="mb-6">
                <CardContent className="pt-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Employee Number</div>
                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {employee.employee_number}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Full Name</div>
                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {employee.full_name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Department</div>
                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {employee.department}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Notification Stats */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {stats?.total || 0}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Total
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6 px-6">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {stats?.unread || 0}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Unread
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {stats?.leave || 0}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Leave
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                                {stats?.payroll || 0}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Payroll
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <div className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                {stats?.attendance || 0}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Attendance
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filters and Actions */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-5 w-5" />
                            Filter & Actions
                        </CardTitle>
                        <div className="flex flex-col md:flex-row gap-2">
                            {(stats?.unread || 0) > 0 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleMarkAllAsRead}
                                >
                                    <CheckCheck className="h-4 w-4 mr-2" />
                                    Mark All as Read
                                </Button>
                            )}
                            {(stats?.total || 0) > 0 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleDeleteAll}
                                    className="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete All
                                </Button>
                            )}
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-col md:flex-row gap-4">
                        <div className="flex-1">
                            <Select
                                value={selectedType}
                                onValueChange={setSelectedType}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Filter by type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Notifications</SelectItem>
                                    <SelectItem value="leave">Leave ({stats?.leave || 0})</SelectItem>
                                    <SelectItem value="payroll">Payroll ({stats?.payroll || 0})</SelectItem>
                                    <SelectItem value="attendance">Attendance ({stats?.attendance || 0})</SelectItem>
                                    <SelectItem value="system">System ({stats?.system || 0})</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={handleFilterChange}>
                            Apply Filter
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Notifications List */}
            <div className="space-y-3">
                {optimisticNotifications.length === 0 ? (
                    <Card>
                        <CardContent className="py-12">
                            <div className="text-center">
                                <Inbox className="h-12 w-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" />
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">
                                    No Notifications
                                </h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {selectedType !== 'all'
                                        ? `You don't have any ${selectedType} notifications.`
                                        : "You're all caught up! No new notifications."}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    optimisticNotifications.map((notification) => (
                        <NotificationItem
                            key={notification.id}
                            id={notification.id}
                            type={notification.type}
                            title={notification.title}
                            message={notification.message}
                            timestamp={notification.timestamp}
                            read={notification.read}
                            onMarkAsRead={handleMarkAsRead}
                            onDelete={handleDelete}
                        />
                    ))
                )}
            </div>

            {/* Help Section */}
            {optimisticNotifications.length > 0 && (
                <Card className="mt-6">
                    <CardContent className="pt-6">
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Notification Tips
                            </h3>
                            <ul className="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <li className="flex items-start gap-2">
                                    <span className="text-blue-600 dark:text-blue-400">•</span>
                                    <span>Click on a notification to mark it as read</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-blue-600 dark:text-blue-400">•</span>
                                    <span>Unread notifications are highlighted with a blue border</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-blue-600 dark:text-blue-400">•</span>
                                    <span>Use the filter to view specific notification types</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-blue-600 dark:text-blue-400">•</span>
                                    <span>Notifications are kept for 30 days before being automatically deleted</span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            )}
            </div>
        </AppLayout>
    );
}
