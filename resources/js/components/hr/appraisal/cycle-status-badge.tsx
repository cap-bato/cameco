import React from 'react';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, Clock } from 'lucide-react';
import { AppraisalCycleStatus, CYCLE_STATUS_COLORS } from '@/types/appraisal-pages';

interface CycleStatusBadgeProps {
    status: AppraisalCycleStatus;
    className?: string;
    showIcon?: boolean;
}

export function CycleStatusBadge({ status, className = '', showIcon = true }: CycleStatusBadgeProps) {
    const getStatusIcon = () => {
        switch (status) {
            case 'open':
                return <Clock className="h-3 w-3" />;
            case 'closed':
                return <CheckCircle2 className="h-3 w-3" />;
            default:
                return null;
        }
    };

    const getStatusLabel = () => {
        switch (status) {
            case 'open':
                return 'Open';
            case 'closed':
                return 'Closed';
            default:
                return status;
        }
    };

    return (
        <Badge className={`${CYCLE_STATUS_COLORS[status]} ${className}`}>
            <div className="flex items-center gap-1">
                {showIcon && getStatusIcon()}
                <span>{getStatusLabel()}</span>
            </div>
        </Badge>
    );
}
