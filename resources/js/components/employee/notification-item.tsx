import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Calendar,
    DollarSign,
    Clock,
    AlertCircle,
    X,
    Circle,
} from 'lucide-react';
import { format, parseISO, formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';

// ============================================================================
// Type Definitions
// ============================================================================

interface NotificationItemProps {
    id: number;
    type: 'leave' | 'payroll' | 'attendance' | 'system';
    title: string;
    message: string;
    timestamp: string;
    read: boolean;
    onMarkAsRead?: (id: number) => void;
    onDelete?: (id: number) => void;
}

// ============================================================================
// Notification Type Configuration
// ============================================================================

const notificationConfig = {
    leave: {
        label: 'Leave',
        icon: Calendar,
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-100 dark:bg-blue-900/30',
        badgeColor: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    },
    payroll: {
        label: 'Payroll',
        icon: DollarSign,
        color: 'text-green-600 dark:text-green-400',
        bgColor: 'bg-green-100 dark:bg-green-900/30',
        badgeColor: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    },
    attendance: {
        label: 'Attendance',
        icon: Clock,
        color: 'text-orange-600 dark:text-orange-400',
        bgColor: 'bg-orange-100 dark:bg-orange-900/30',
        badgeColor: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    },
    system: {
        label: 'System',
        icon: AlertCircle,
        color: 'text-purple-600 dark:text-purple-400',
        bgColor: 'bg-purple-100 dark:bg-purple-900/30',
        badgeColor: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    },
};

// ============================================================================
// Main Component
// ============================================================================

export function NotificationItem({
    id,
    type,
    title,
    message,
    timestamp,
    read,
    onMarkAsRead,
    onDelete,
}: NotificationItemProps) {
    const config = notificationConfig[type];
    const IconComponent = config.icon;
    const parsedDate = parseISO(timestamp);
    const relativeTime = formatDistanceToNow(parsedDate, { addSuffix: true });
    const formattedDate = format(parsedDate, 'MMM d, yyyy h:mm a');

    const handleClick = () => {
        if (!read && onMarkAsRead) {
            onMarkAsRead(id);
        }
    };

    const handleDelete = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (onDelete) {
            onDelete(id);
        }
    };

    return (
        <Card
            className={cn(
                'p-4 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50',
                !read && 'bg-blue-50/50 dark:bg-blue-950/20 border-l-4 border-l-blue-500'
            )}
            onClick={handleClick}
        >
            <div className="flex items-start gap-4">
                {/* Icon */}
                <div className={cn('p-2 rounded-full', config.bgColor)}>
                    <IconComponent className={cn('h-5 w-5', config.color)} />
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2 mb-1">
                        <div className="flex items-center gap-2 flex-wrap">
                            <h3
                                className={cn(
                                    'text-sm text-gray-900 dark:text-gray-100',
                                    !read && 'font-semibold'
                                )}
                            >
                                {title}
                            </h3>
                            <Badge variant="outline" className={cn('text-xs', config.badgeColor)}>
                                {config.label}
                            </Badge>
                            {!read && (
                                <div className="flex items-center gap-1">
                                    <Circle className="h-2 w-2 fill-blue-600 text-blue-600 dark:fill-blue-400 dark:text-blue-400" />
                                </div>
                            )}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleDelete}
                            className="h-6 w-6 p-0 hover:bg-red-100 dark:hover:bg-red-900/30"
                        >
                            <X className="h-4 w-4 text-gray-500 hover:text-red-600 dark:hover:text-red-400" />
                        </Button>
                    </div>

                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-2 line-clamp-2">
                        {message}
                    </p>

                    <div className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-500">
                        <Clock className="h-3 w-3" />
                        <span title={formattedDate}>{relativeTime}</span>
                    </div>
                </div>
            </div>
        </Card>
    );
}
