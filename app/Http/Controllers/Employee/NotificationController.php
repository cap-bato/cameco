<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee Notification Controller
 * 
 * Handles employee notification management:
 * - View all notifications (leave, payroll, attendance, system)
 * - Filter by type and read/unread status
 * - Mark notifications as read
 * - Delete notifications
 */
class NotificationController extends Controller
{
    /**
     * Display all notifications for authenticated employee
     * Shows notifications from various modules with filtering options
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            // Get filter parameters
            $type = $request->input('type'); // 'all', 'leave', 'payroll', 'attendance', 'system'
            $status = $request->input('status'); // 'all', 'unread', 'read'

            // Build notification query
            $query = $user->notifications();

            // Filter by type
            if ($type && $type !== 'all') {
                $query->where('type', 'like', '%' . ucfirst($type) . '%');
            }

            // Filter by read/unread status
            if ($status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($status === 'read') {
                $query->whereNotNull('read_at');
            }

            // Get notifications with pagination
            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate(20)
                ->through(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $this->getNotificationType($notification->type),
                        'type_label' => $this->getTypeLabel($notification->type),
                        'icon' => $this->getNotificationIcon($notification->type),
                        'color' => $this->getNotificationColor($notification->type),
                        'title' => $notification->data['title'] ?? 'Notification',
                        'message' => $notification->data['message'] ?? '',
                        'action_url' => $notification->data['action_url'] ?? null,
                        'action_label' => $notification->data['action_label'] ?? null,
                        'is_read' => $notification->read_at !== null,
                        'read_at' => $notification->read_at?->format('Y-m-d H:i:s'),
                        'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                        'time_ago' => $notification->created_at->diffForHumans(),
                    ];
                });

            // Get unread count for badge
            $unreadCount = $user->unreadNotifications()->count();

            // Get notification counts by type
            $typeCounts = [
                'all' => $user->notifications()->count(),
                'leave' => $user->notifications()->where('type', 'like', '%Leave%')->count(),
                'payroll' => $user->notifications()->where('type', 'like', '%Payroll%')->orWhere('type', 'like', '%Payslip%')->count(),
                'attendance' => $user->notifications()->where('type', 'like', '%Attendance%')->count(),
                'system' => $user->notifications()->where('type', 'like', '%System%')->count(),
            ];

            Log::info('Employee notifications viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'filters' => compact('type', 'status'),
            ]);

            return Inertia::render('Employee/Notifications/Index', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                ],
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
                'typeCounts' => $typeCounts,
                'filters' => [
                    'type' => $type ?? 'all',
                    'status' => $status ?? 'all',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch employee notifications', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Employee/Notifications/Index', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? 'N/A',
                ],
                'notifications' => [],
                'unreadCount' => 0,
                'typeCounts' => [
                    'all' => 0,
                    'leave' => 0,
                    'payroll' => 0,
                    'attendance' => 0,
                    'system' => 0,
                ],
                'filters' => [],
                'error' => 'Failed to load notifications. Please try again or contact support if the issue persists.',
            ]);
        }
    }

    /**
     * Mark notification as read
     * Updates notification read_at timestamp
     */
    public function markRead(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            // Get notification (self-only access check via user->notifications())
            $notification = $user->notifications()->where('id', $id)->firstOrFail();

            // Mark as read if not already read
            if ($notification->read_at === null) {
                $notification->markAsRead();

                Log::info('Employee marked notification as read', [
                    'user_id' => $user->id,
                    'employee_id' => $employee->id,
                    'notification_id' => $id,
                ]);
            }

            return back()->with('success', 'Notification marked as read.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors([
                'error' => 'Notification not found or you do not have permission to access it.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to mark notification as read. Please try again.',
            ]);
        }
    }

    /**
     * Delete notification
     * Permanently removes notification from database
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            // Get notification (self-only access check via user->notifications())
            $notification = $user->notifications()->where('id', $id)->firstOrFail();

            // Delete notification
            $notification->delete();

            Log::info('Employee deleted notification', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'notification_id' => $id,
            ]);

            return back()->with('success', 'Notification deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors([
                'error' => 'Notification not found or you do not have permission to delete it.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to delete notification. Please try again.',
            ]);
        }
    }

    /**
     * Mark all notifications as read
     * Batch operation to mark all unread notifications as read
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            $unreadCount = $user->unreadNotifications()->count();

            // Mark all unread notifications as read
            $user->unreadNotifications()->update(['read_at' => now()]);

            Log::info('Employee marked all notifications as read', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'count' => $unreadCount,
            ]);

            return back()->with('success', "All {$unreadCount} notifications marked as read.");
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to mark all notifications as read. Please try again.',
            ]);
        }
    }

    /**
     * Delete all read notifications
     * Batch operation to clear read notification history
     */
    public function deleteAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'No employee record found for your account.');
        }

        try {
            $readCount = $user->readNotifications()->count();

            // Delete all read notifications
            $user->readNotifications()->delete();

            Log::info('Employee deleted all read notifications', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'count' => $readCount,
            ]);

            return back()->with('success', "All {$readCount} read notifications deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to delete all read notifications', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to delete read notifications. Please try again.',
            ]);
        }
    }

    /**
     * Get notification type category from full class name
     */
    private function getNotificationType(string $fullType): string
    {
        if (str_contains($fullType, 'Leave')) {
            return 'leave';
        } elseif (str_contains($fullType, 'Payroll') || str_contains($fullType, 'Payslip')) {
            return 'payroll';
        } elseif (str_contains($fullType, 'Attendance')) {
            return 'attendance';
        } elseif (str_contains($fullType, 'System')) {
            return 'system';
        } else {
            return 'other';
        }
    }

    /**
     * Get human-readable type label
     */
    private function getTypeLabel(string $fullType): string
    {
        $type = $this->getNotificationType($fullType);
        
        return match($type) {
            'leave' => 'Leave',
            'payroll' => 'Payroll',
            'attendance' => 'Attendance',
            'system' => 'System',
            default => 'Other',
        };
    }

    /**
     * Get icon for notification type
     */
    private function getNotificationIcon(string $fullType): string
    {
        $type = $this->getNotificationType($fullType);
        
        return match($type) {
            'leave' => 'calendar',
            'payroll' => 'dollar-sign',
            'attendance' => 'clock',
            'system' => 'bell',
            default => 'info',
        };
    }

    /**
     * Get color for notification type
     */
    private function getNotificationColor(string $fullType): string
    {
        $type = $this->getNotificationType($fullType);
        
        return match($type) {
            'leave' => 'blue',
            'payroll' => 'green',
            'attendance' => 'orange',
            'system' => 'purple',
            default => 'gray',
        };
    }
}
