import { Card, CardContent } from '@/components/ui/card';
import { LucideIcon } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface QuickStatCardProps {
    title: string;
    value: string | number;
    icon: LucideIcon;
    linkTo?: string;
    colorScheme?: 'blue' | 'green' | 'orange' | 'purple' | 'red';
    subtitle?: string;
    badge?: string;
}

const colorClasses = {
    blue: {
        icon: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        hover: 'hover:border-blue-200 dark:hover:border-blue-800',
    },
    green: {
        icon: 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
        hover: 'hover:border-green-200 dark:hover:border-green-800',
    },
    orange: {
        icon: 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
        hover: 'hover:border-orange-200 dark:hover:border-orange-800',
    },
    purple: {
        icon: 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        hover: 'hover:border-purple-200 dark:hover:border-purple-800',
    },
    red: {
        icon: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        hover: 'hover:border-red-200 dark:hover:border-red-800',
    },
};

export function QuickStatCard({
    title,
    value,
    icon: Icon,
    linkTo,
    colorScheme = 'blue',
    subtitle,
    badge,
}: QuickStatCardProps) {
    const colors = colorClasses[colorScheme];

    const content = (
        <Card
            className={cn(
                'transition-all duration-200',
                linkTo && cn(
                    'cursor-pointer',
                    colors.hover,
                    'hover:shadow-md'
                )
            )}
        >
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="space-y-2 flex-1">
                        <p className="text-sm font-medium text-muted-foreground">
                            {title}
                        </p>
                        <div className="flex items-baseline gap-2">
                            <p className="text-3xl font-bold tracking-tight">
                                {value}
                            </p>
                            {badge && (
                                <span className="text-xs font-medium text-muted-foreground">
                                    {badge}
                                </span>
                            )}
                        </div>
                        {subtitle && (
                            <p className="text-xs text-muted-foreground">
                                {subtitle}
                            </p>
                        )}
                    </div>
                    <div className={cn(
                        'rounded-lg p-3',
                        colors.icon
                    )}>
                        <Icon className="h-5 w-5" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    if (linkTo) {
        return <Link href={linkTo}>{content}</Link>;
    }

    return content;
}
