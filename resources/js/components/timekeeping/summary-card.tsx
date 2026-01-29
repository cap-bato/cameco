import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowRight } from 'lucide-react';

interface SummaryCardProps {
    title: string;
    value: string | number;
    description?: string;
    variant?: 'default' | 'success' | 'warning' | 'danger';
    className?: string;
    actionLabel?: string;
    onActionClick?: () => void;
}

/**
 * Reusable summary metric card component
 * Displays a single metric with title, value, and optional description
 * Used for all summary statistics throughout timekeeping module
 */
export function SummaryCard({ 
    title, 
    value, 
    description,
    variant = 'default',
    className,
    actionLabel,
    onActionClick
}: SummaryCardProps) {
    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                {description && (
                    <p className="text-xs text-muted-foreground">{description}</p>
                )}
                {actionLabel && onActionClick && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onActionClick}
                        className="mt-3 h-8 text-xs px-2 w-full justify-start group"
                    >
                        {actionLabel}
                        <ArrowRight className="ml-1 h-3 w-3 transition-transform group-hover:translate-x-0.5" />
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
