import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Copy, Clock, Users, Calendar } from 'lucide-react';
import { WorkSchedule } from '@/types/workforce-pages';

interface ScheduleCardProps {
    schedule: WorkSchedule;
    onEdit: (schedule: WorkSchedule) => void;
    onDelete: (id: number) => void;
    onDuplicate: (schedule: WorkSchedule) => void;
}

export default function ScheduleCard({
    schedule,
    onEdit,
    onDelete,
    onDuplicate,
}: ScheduleCardProps) {
    const getStatusColor = (status?: string): string => {
        switch (status) {
            case 'active':
                return 'bg-green-50 border-green-200';
            case 'expired':
                return 'bg-red-50 border-red-200';
            case 'draft':
                return 'bg-gray-50 border-gray-200';
            default:
                return 'bg-blue-50 border-blue-200';
        }
    };

    const getStatusBadgeColor = (status?: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
        switch (status) {
            case 'active':
                return 'default';
            case 'expired':
                return 'destructive';
            case 'draft':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    const formatDate = (dateString: string): string => {
        try {
            return new Date(dateString).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        } catch {
            return dateString;
        }
    };

    const getWorkDays = (): string => {
        const workDays = [];

        if (schedule.monday_start) workDays.push('Mon');
        if (schedule.tuesday_start) workDays.push('Tue');
        if (schedule.wednesday_start) workDays.push('Wed');
        if (schedule.thursday_start) workDays.push('Thu');
        if (schedule.friday_start) workDays.push('Fri');
        if (schedule.saturday_start) workDays.push('Sat');
        if (schedule.sunday_start) workDays.push('Sun');

        return workDays.length > 0 ? workDays.join(', ') : 'No work days set';
    };

    const getShiftTimes = (): string => {
        const times = [];
        
        if (schedule.monday_start) times.push(`${schedule.monday_start} - ${schedule.monday_end || 'N/A'}`);
        
        if (times.length > 0) return times[0];
        return 'Not defined';
    };

    return (
        <Card className={`${getStatusColor(schedule.status)} border transition-all hover:shadow-lg`}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                        <CardTitle className="text-base font-semibold truncate">{schedule.name}</CardTitle>
                        <p className="text-xs text-gray-500 truncate">
                            {schedule.department_name || (schedule.department as any)?.name || 'No department'}
                        </p>
                    </div>
                    <Badge variant={getStatusBadgeColor(schedule.status)}>
                        {(schedule.status || 'active').charAt(0).toUpperCase() + (schedule.status || 'active').slice(1)}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {/* Effective Date */}
                <div className="flex items-center gap-2 text-sm">
                    <Calendar className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-700">
                        From {formatDate(schedule.effective_date)}
                        {schedule.expires_at && ` to ${formatDate(schedule.expires_at)}`}
                    </span>
                </div>

                {/* Work Days */}
                <div className="flex items-start gap-2 text-sm">
                    <Clock className="h-4 w-4 text-gray-400 mt-0.5" />
                    <div className="flex-1">
                        <p className="text-xs text-gray-500">Work days</p>
                        <p className="text-gray-700 text-xs font-medium">{getWorkDays()}</p>
                    </div>
                </div>

                {/* Shift Times */}
                <div className="flex items-start gap-2 text-sm">
                    <Clock className="h-4 w-4 text-gray-400 mt-0.5" />
                    <div className="flex-1">
                        <p className="text-xs text-gray-500">Shift times</p>
                        <p className="text-gray-700 text-xs font-medium">{getShiftTimes()}</p>
                    </div>
                </div>

                {/* Assigned Employees */}
                {(schedule.assigned_employees_count ?? 0) > 0 && (
                    <div className="flex items-center gap-2 text-sm">
                        <Users className="h-4 w-4 text-gray-400" />
                        <span className="text-gray-700">
                            {schedule.assigned_employees_count} employee{(schedule.assigned_employees_count ?? 0) !== 1 ? 's' : ''} assigned
                        </span>
                    </div>
                )}

                {/* Description */}
                {schedule.description && (
                    <p className="text-xs text-gray-600 line-clamp-2">{schedule.description}</p>
                )}

                {/* Actions */}
                <div className="flex gap-2 pt-2 border-t">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onEdit(schedule)}
                        className="flex-1 gap-1 text-xs h-8"
                    >
                        <Edit className="h-3 w-3" />
                        Edit
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDuplicate(schedule)}
                        className="flex-1 gap-1 text-xs h-8"
                    >
                        <Copy className="h-3 w-3" />
                        Duplicate
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDelete(schedule.id)}
                        className="flex-1 gap-1 text-xs h-8 text-red-600 hover:text-red-700 hover:bg-red-50"
                    >
                        <Trash2 className="h-3 w-3" />
                        Delete
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
