import { useState, useEffect } from 'react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

interface Notification {
    id: string;
    type: string;
    data: {
        document_request_id: number;
        document_type: string;
        status: string;
    };
    created_at: string;
    read_at: string | null;
}

export default function NotificationBell() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);

    useEffect(() => {
        fetchNotifications();
        
        // Poll for new notifications every 30 seconds
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, []);

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/employee/notifications');
            const data = await response.json();
            setNotifications(data.notifications);
            setUnreadCount(data.unread_count);
        } catch (error) {
            console.error('Failed to fetch notifications', error);
        }
    };

    const markAsRead = async (notificationId: string) => {
        try {
            await fetch(`/employee/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            fetchNotifications();
        } catch (error) {
            console.error('Failed to mark notification as read', error);
        }
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs"
                        >
                            {unreadCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80">
                <div className="space-y-2">
                    <h3 className="font-semibold mb-4">Notifications</h3>
                    {notifications.length === 0 ? (
                        <p className="text-sm text-gray-600 text-center py-4">
                            No notifications
                        </p>
                    ) : (
                        notifications.map((notification) => (
                            <div
                                key={notification.id}
                                className={`p-3 rounded-lg border cursor-pointer ${
                                    notification.read_at ? 'bg-white' : 'bg-blue-50'
                                }`}
                                onClick={() => {
                                    markAsRead(notification.id);
                                    window.location.href = '/employee/documents/requests/history';
                                }}
                            >
                                <p className="text-sm font-medium">
                                    Document Request {notification.data.status}
                                </p>
                                <p className="text-xs text-gray-600 mt-1">
                                    Your {notification.data.document_type} request has been {notification.data.status}
                                </p>
                                <p className="text-xs text-gray-400 mt-1">
                                    {new Date(notification.created_at).toLocaleDateString()}
                                </p>
                            </div>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}