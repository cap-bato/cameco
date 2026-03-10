import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import { BadgeDetailView } from '@/components/hr/badge-detail-view';
import { BadgeUsageTimeline } from '@/components/hr/badge-usage-timeline';
import { BadgeAnalytics } from '@/components/hr/badge-analytics';

interface Badge {
    id: string;
    card_uid: string;
    employee_id: string;
    employee_name: string;
    employee_photo?: string;
    department: string;
    position: string;
    card_type: 'mifare' | 'desfire' | 'em4100';
    issued_at: string;
    issued_by: string;
    expires_at: string | null;
    is_active: boolean;
    last_used_at: string | null;
    usage_count: number;
    status: 'active' | 'inactive' | 'lost' | 'stolen' | 'expired' | 'replaced';
    first_scan_at?: string;
    most_used_device?: string;
    employee_status?: 'active' | 'on_leave' | 'inactive';
}

interface ShowBadgeProps {
    badge: Badge & {
        employee?: {
            id: number;
            full_name: string;
            employee_number: string;
            department_name?: string;
        };
        issued_by_name?: string;
        deactivated_by_name?: string;
    };
    usageStats?: {
        total_scans: number;
        first_scan: string | null;
        last_scan: string | null;
        days_used: number;
        devices_used: number;
    };
    recentScans: Array<{
        id: string;
        timestamp?: string;
        scan_timestamp?: string;
        event_type: string;
        device_id?: string;
        device_name: string;
        location?: string;
        duration_minutes?: number;
    }>;
    dailyScans: Array<{
        date: string;
        scans: number;
    }>;
    hourlyPeaks: Array<{
        hour: number;
        scans: number;
    }>;
    deviceUsage: Array<{
        device: string;
        scans: number;
    }>;
}

export default function ShowBadge({
    badge,
    usageStats,
    recentScans,
    dailyScans,
    hourlyPeaks,
    deviceUsage,
}: ShowBadgeProps) {
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [displayedScans, setDisplayedScans] = useState(recentScans?.slice(0, 10) || []);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'RFID Badges', href: '/hr/timekeeping/badges' },
        { title: 'Badge Details', href: '#' },
    ];

    const handleLoadMore = () => {
        setIsLoadingMore(true);
        // Simulate loading more scans
        setTimeout(() => {
            setIsLoadingMore(false);
        }, 1000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Badge Details" />
            
            <div className="container mx-auto py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/hr/timekeeping/badges">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Badges
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">Badge Details</h1>
                            <p className="text-muted-foreground mt-1">
                                {badge?.employee?.full_name || badge?.employee_name || 'Unknown Employee'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Badge Detail View */}
                <BadgeDetailView
                    badge={badge}
                    onPrint={() => console.log('Print badge sheet')}
                    onReplace={() => console.log('Replace badge')}
                    onExtend={() => console.log('Extend expiration')}
                    onDeactivate={() => console.log('Deactivate badge')}
                />

                {/* Usage Timeline */}
                <BadgeUsageTimeline
                    badge_id={badge?.id}
                    scans={displayedScans}
                    onLoadMore={handleLoadMore}
                    hasMore={recentScans && recentScans.length > 10}
                    isLoading={isLoadingMore}
                />

                {/* Usage Analytics */}
                <BadgeAnalytics
                    dailyScans={dailyScans || []}
                    hourlyPeaks={hourlyPeaks || []}
                    deviceUsage={deviceUsage || []}
                />
            </div>
        </AppLayout>
    );
}
