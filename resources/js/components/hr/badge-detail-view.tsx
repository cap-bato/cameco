import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Copy,
    Check,
    Clock,
    Calendar,
    Users,
    AlertCircle,
    Printer,
    History,
    Repeat2,
    Trash2,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { format, differenceInDays, differenceInHours } from 'date-fns';

interface BadgeDetailViewProps {
    badge: {
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
    };
    onReplace?: () => void;
    onExtend?: () => void;
    onDeactivate?: () => void;
    onPrint?: () => void;
}

export function BadgeDetailView({
    badge,
    onReplace,
    onExtend,
    onDeactivate,
    onPrint,
}: BadgeDetailViewProps) {
    const [copied, setCopied] = useState(false);

    const cardTypeLabel = {
        mifare: 'Mifare (Standard)',
        desfire: 'DESFire (Advanced)',
        em4100: 'EM4100 (Legacy)',
    };

    const statusColor = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        lost: 'bg-red-100 text-red-800',
        stolen: 'bg-red-100 text-red-800',
        expired: 'bg-orange-100 text-orange-800',
        replaced: 'bg-blue-100 text-blue-800',
    };

    const employeeStatusColor = {
        active: 'bg-green-100 text-green-800',
        on_leave: 'bg-yellow-100 text-yellow-800',
        inactive: 'bg-gray-100 text-gray-800',
    };

    const handleCopyUid = () => {
        navigator.clipboard.writeText(badge.card_uid);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const daysUntilExpiration = badge.expires_at
        ? differenceInDays(new Date(badge.expires_at), new Date())
        : null;

    const lastUsedTime = badge.last_used_at
        ? differenceInHours(new Date(), new Date(badge.last_used_at))
        : null;

    const averageScansPerDay =
        badge.usage_count && badge.first_scan_at
            ? (
                  badge.usage_count /
                  (differenceInDays(new Date(), new Date(badge.first_scan_at)) + 1)
              ).toFixed(1)
            : '0';

    return (
        <div className="space-y-6">
            {/* Employee Information */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5" />
                        Employee Information
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex gap-6">
                        {/* Employee Photo */}
                        <div className="flex-shrink-0">
                            <img
                                src={
                                    badge.employee_photo ||
                                    `https://api.dicebear.com/7.x/avataaars/svg?seed=${badge.employee_name}`
                                }
                                alt={badge.employee_name}
                                className="h-32 w-32 rounded-lg object-cover border-2 border-muted"
                            />
                        </div>

                        {/* Employee Details */}
                        <div className="flex-1 space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Full Name
                                </p>
                                <p className="text-lg font-semibold">
                                    {badge.employee_name}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Employee ID
                                    </p>
                                    <p className="text-sm font-mono">{badge.employee_id}</p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Status
                                    </p>
                                    <Badge
                                        className={`mt-1 ${
                                            employeeStatusColor[
                                                badge.employee_status || 'active'
                                            ]
                                        }`}
                                    >
                                        {badge.employee_status
                                            ? badge.employee_status
                                                  .replace('_', ' ')
                                                  .toUpperCase()
                                            : 'ACTIVE'}
                                    </Badge>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Department
                                    </p>
                                    <p className="text-sm">{badge.department}</p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Position
                                    </p>
                                    <p className="text-sm">{badge.position}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Badge Information */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Badge className="h-5 w-5" />
                        Badge Information
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Card UID */}
                        <div>
                            <p className="text-sm font-medium text-muted-foreground mb-2">
                                Card UID
                            </p>
                            <div className="flex gap-2">
                                <div className="flex-1 bg-muted p-3 rounded border border-muted-foreground/20">
                                    <p className="font-mono text-sm break-all">
                                        {badge.card_uid}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={handleCopyUid}
                                    title="Copy Card UID"
                                >
                                    {copied ? (
                                        <Check className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                </Button>
                            </div>
                        </div>

                        {/* Card Type */}
                        <div>
                            <p className="text-sm font-medium text-muted-foreground mb-2">
                                Card Type
                            </p>
                            <Badge className="bg-blue-100 text-blue-800">
                                {cardTypeLabel[badge.card_type]}
                            </Badge>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Status */}
                        <div>
                            <p className="text-sm font-medium text-muted-foreground mb-2">
                                Status
                            </p>
                            <Badge className={statusColor[badge.status]}>
                                {badge.status.toUpperCase()}
                            </Badge>
                        </div>

                        {/* Issued By */}
                        <div>
                            <p className="text-sm font-medium text-muted-foreground mb-2">
                                Issued By
                            </p>
                            <p className="text-sm">{badge.issued_by}</p>
                        </div>

                        {/* Issued Date */}
                        <div>
                            <p className="text-sm font-medium text-muted-foreground mb-2">
                                Issued Date
                            </p>
                            <p className="text-sm">
                                {format(new Date(badge.issued_at), 'MMM dd, yyyy')}
                            </p>
                        </div>
                    </div>

                    {/* Expiration Date */}
                    {badge.expires_at && (
                        <Alert
                            className={
                                daysUntilExpiration && daysUntilExpiration < 30
                                    ? 'border-orange-200 bg-orange-50'
                                    : 'border-green-200 bg-green-50'
                            }
                        >
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                <span className="font-medium">
                                    Expires on {format(new Date(badge.expires_at), 'MMM dd, yyyy')}
                                </span>
                                {daysUntilExpiration !== null && (
                                    <span className="ml-2 text-muted-foreground">
                                        ({daysUntilExpiration} days remaining)
                                    </span>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            {/* Usage Statistics */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Usage Statistics
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {/* Total Scans */}
                        <div className="border rounded-lg p-4 space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Scans
                            </p>
                            <p className="text-3xl font-bold">{badge.usage_count}</p>
                        </div>

                        {/* First Scan */}
                        <div className="border rounded-lg p-4 space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                First Scan
                            </p>
                            <p className="text-sm">
                                {badge.first_scan_at
                                    ? format(new Date(badge.first_scan_at), 'MMM dd, yyyy')
                                    : 'No scans yet'}
                            </p>
                        </div>

                        {/* Last Scan */}
                        <div className="border rounded-lg p-4 space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                Last Scan
                            </p>
                            <p className="text-sm">
                                {lastUsedTime !== null
                                    ? lastUsedTime === 0
                                        ? 'Just now'
                                        : `${lastUsedTime} hour${lastUsedTime > 1 ? 's' : ''} ago`
                                    : 'No scans yet'}
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        {/* Average Scans per Day */}
                        <div className="border rounded-lg p-4 space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                Average Scans/Day
                            </p>
                            <p className="text-2xl font-bold">{averageScansPerDay}</p>
                        </div>

                        {/* Most Used Device */}
                        <div className="border rounded-lg p-4 space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                Most Used Device
                            </p>
                            <p className="text-sm font-mono">
                                {badge.most_used_device || 'Main Gate (Gate-01)'}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Action Buttons */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Actions</CardTitle>
                    <CardDescription>
                        Manage this badge and view additional information
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={onPrint}>
                            <Printer className="h-4 w-4 mr-2" />
                            Print Badge Sheet
                        </Button>

                        <Button variant="outline">
                            <History className="h-4 w-4 mr-2" />
                            View Full Usage History
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">More Actions</Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={onReplace}>
                                    <Repeat2 className="h-4 w-4 mr-2" />
                                    Replace Badge
                                </DropdownMenuItem>
                                {badge.expires_at && daysUntilExpiration! > 0 && (
                                    <DropdownMenuItem onClick={onExtend}>
                                        <Calendar className="h-4 w-4 mr-2" />
                                        Extend Expiration
                                    </DropdownMenuItem>
                                )}
                                {badge.is_active && (
                                    <DropdownMenuItem
                                        onClick={onDeactivate}
                                        className="text-red-600"
                                    >
                                        <Trash2 className="h-4 w-4 mr-2" />
                                        Deactivate Badge
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
