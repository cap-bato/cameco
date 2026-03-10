import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Download, MoreVertical, Loader2 } from 'lucide-react';
import { format } from 'date-fns';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Scan {
    id: string;
    timestamp: string;
    device_id: string;
    device_name: string;
    event_type: 'time_in' | 'time_out' | 'break_start' | 'break_end';
    duration_minutes?: number;
}

interface BadgeUsageTimelineProps {
    badge_id: string;
    scans: Scan[];
    onLoadMore?: () => void;
    hasMore?: boolean;
    isLoading?: boolean;
}

export function BadgeUsageTimeline({
    badge_id,
    scans,
    onLoadMore,
    hasMore = false,
    isLoading = false,
}: BadgeUsageTimelineProps) {
    const eventTypeColor = {
        time_in: 'bg-green-100 text-green-800',
        time_out: 'bg-blue-100 text-blue-800',
        break_start: 'bg-orange-100 text-orange-800',
        break_end: 'bg-purple-100 text-purple-800',
    };

    const eventTypeLabel = {
        time_in: 'Time In',
        time_out: 'Time Out',
        break_start: 'Break Start',
        break_end: 'Break End',
    };

    const handleExportCsv = () => {
        // Create CSV header
        const headers = ['Timestamp', 'Device', 'Event Type', 'Duration (minutes)'];

        // Create CSV rows
        const rows = scans.map((scan) => [
            format(new Date(scan.timestamp), 'yyyy-MM-dd HH:mm:ss'),
            scan.device_name,
            eventTypeLabel[scan.event_type],
            scan.duration_minutes || '-',
        ]);

        // Combine header and rows
        const csvContent = [
            headers.join(','),
            ...rows.map((row) => row.join(',')),
        ].join('\n');

        // Create blob and download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', `badge-${badge_id}-usage-history.csv`);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            Recent Scans (Usage History)
                        </CardTitle>
                        <CardDescription>
                            Last 20 scans - showing most recent first
                        </CardDescription>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleExportCsv}
                        disabled={scans.length === 0}
                    >
                        <Download className="h-4 w-4 mr-2" />
                        Export CSV
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                {scans.length === 0 ? (
                    <div className="text-center py-8 text-muted-foreground">
                        <p>No scan records found</p>
                    </div>
                ) : (
                    <>
                        <div className="border rounded-lg overflow-hidden">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Timestamp</TableHead>
                                        <TableHead>Device / Location</TableHead>
                                        <TableHead>Event Type</TableHead>
                                        <TableHead className="text-right">Duration</TableHead>
                                        <TableHead className="w-10"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {scans.map((scan) => (
                                        <TableRow key={scan.id}>
                                            <TableCell className="font-mono text-sm">
                                                {format(
                                                    new Date(scan.timestamp),
                                                    'MMM dd, yyyy HH:mm:ss'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{scan.device_name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {scan.device_id}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={eventTypeColor[scan.event_type]}>
                                                    {eventTypeLabel[scan.event_type]}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {scan.duration_minutes
                                                    ? `${scan.duration_minutes} min`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                        >
                                                            <MoreVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem>
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem>
                                                            View Device Info
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Load More Button */}
                        {hasMore && (
                            <div className="flex justify-center mt-4">
                                <Button
                                    variant="outline"
                                    onClick={onLoadMore}
                                    disabled={isLoading}
                                >
                                    {isLoading ? (
                                        <>
                                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            Loading...
                                        </>
                                    ) : (
                                        'Load More Scans'
                                    )}
                                </Button>
                            </div>
                        )}

                        {/* Summary Stats */}
                        <div className="mt-6 pt-4 border-t grid grid-cols-3 gap-4">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Total in View
                                </p>
                                <p className="text-lg font-semibold">{scans.length}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Time In Events
                                </p>
                                <p className="text-lg font-semibold">
                                    {scans.filter((s) => s.event_type === 'time_in').length}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Time Out Events
                                </p>
                                <p className="text-lg font-semibold">
                                    {scans.filter((s) => s.event_type === 'time_out').length}
                                </p>
                            </div>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
