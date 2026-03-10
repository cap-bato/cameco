import { Head, Link, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    ArrowLeft, 
    Plus, 
    Upload, 
    Download, 
    AlertTriangle, 
    CheckCircle, 
    Clock, 
    XCircle,
    RefreshCw 
} from 'lucide-react';
import { BadgeStatsWidget } from '@/components/hr/badge-stats-widget';
import { BadgeManagementTable } from '@/components/hr/badge-management-table';
import { BadgeReplacementModal } from '@/components/hr/badge-replacement-modal';
import { BadgeReportModal } from '@/components/hr/badge-report-modal';
import { BadgeBulkImportModal } from '@/components/hr/badge-bulk-import-modal';
import { BadgeIssuanceModal, type BadgeFormData } from '@/components/hr/badge-issuance-modal';
import { EmployeesWithoutBadges } from '@/components/hr/employees-without-badges';

interface BadgeStats {
    total: number;
    active: number;
    inactive: number;
    expiring_soon: number;
    expiringSoon: number;
    employees_without_badges: number;
    employeesWithoutBadges: number;
}

interface Employee {
    id: string;
    name: string;
    employee_id: string;
    department: string;
    position: string;
    hire_date: string;
    photo?: string;
}

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
    deactivation_reason?: string;
    notes?: string;
}

interface BadgesIndexProps {
    badges: {
        data: Badge[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    stats: BadgeStats;
    filters: {
        search?: string;
        status?: string;
        department?: string;
        card_type?: string;
    };
    employees: Employee[];
    employeesWithoutBadges: Employee[];
}

export default function BadgesIndex({ badges, stats, filters, employees, employeesWithoutBadges }: BadgesIndexProps) {
    // Defensive: ensure props have expected structure
    const safeBadges = badges || { data: [], current_page: 1, last_page: 1, total: 0, per_page: 10 };
    const safeStats = stats || { total: 0, active: 0, inactive: 0, expiring_soon: 0, expiringSoon: 0, employees_without_badges: 0, employeesWithoutBadges: 0 };
    const safeEmployees = employees || [];
    const safeEmployeesWithoutBadges = employeesWithoutBadges || [];
    
    const [activeTab, setActiveTab] = useState<string>('active');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isReplacementModalOpen, setIsReplacementModalOpen] = useState(false);
    const [selectedBadgeForReplacement, setSelectedBadgeForReplacement] = useState<Badge | null>(null);
    const [replacementResult, setReplacementResult] = useState<{ success: boolean; message: string } | null>(null);
    const [isReportModalOpen, setIsReportModalOpen] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [isIssuanceModalOpen, setIsIssuanceModalOpen] = useState(false);
    const [selectedEmployeeForIssuance, setSelectedEmployeeForIssuance] = useState<Employee | null>(null);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'RFID Badges', href: '/hr/timekeeping/badges' },
    ];

    const handleRefresh = useCallback(async () => {
        setIsRefreshing(true);
        router.reload();
        setTimeout(() => setIsRefreshing(false), 1000);
    }, []);

    const handleStatClick = useCallback((filterType: string) => {
        const newFilters = { ...filters };
        switch (filterType) {
            case 'active':
                newFilters.status = 'active';
                break;
            case 'no-badge':
                setActiveTab('no-badge');
                break;
            case 'expiring-soon':
                newFilters.status = 'expiring_soon';
                break;
            case 'all':
                delete newFilters.status;
                break;
        }
        router.visit('/hr/timekeeping/badges', { data: newFilters });
    }, [filters]);

    const handleReplaceBadge = useCallback((badge: Badge) => {
        setSelectedBadgeForReplacement(badge);
        setIsReplacementModalOpen(true);
    }, []);

    const handleReplacementSubmit = useCallback((data: { old_badge_id: string; new_card_uid: string; reason: string }) => {
        // Simulate replacement submission
        console.log('Badge replacement submitted:', data);
        setReplacementResult({
            success: true,
            message: `Badge ${data.new_card_uid} has been successfully issued as replacement for ${data.old_badge_id}. Old badge ${data.old_badge_id} has been deactivated.`,
        });
        setIsReplacementModalOpen(false);
        // In Phase 2, this will submit to the backend
        setTimeout(() => setReplacementResult(null), 5000);
    }, []);

    const handleIssueBadgeToEmployee = useCallback((employee: Employee) => {
        setSelectedEmployeeForIssuance(employee);
        setIsIssuanceModalOpen(true);
    }, []);

    const handleIssuanceSubmit = useCallback((formData: BadgeFormData) => {
        console.log('Badge issuance submitted:', formData);
        // Simulate issuance submission
        const employeeName = selectedEmployeeForIssuance?.name || 'Unknown Employee';
        setIsIssuanceModalOpen(false);
        setReplacementResult({
            success: true,
            message: `Badge ${formData.card_uid} has been successfully issued to ${employeeName}.`,
        });
        setTimeout(() => setReplacementResult(null), 5000);
    }, [selectedEmployeeForIssuance]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RFID Badge Management" />
            
            <div className="container mx-auto space-y-6 p-6">
                {/* Replacement Result Alert */}
                {replacementResult && (
                    <Alert className={replacementResult.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}>
                        <CheckCircle className={`h-4 w-4 ${replacementResult.success ? 'text-green-600' : 'text-red-600'}`} />
                        <AlertDescription className={replacementResult.success ? 'text-green-800' : 'text-red-800'}>
                            {replacementResult.message}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Header Section */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/hr/timekeeping/overview">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Timekeeping
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">RFID Badge Management</h1>
                            <p className="text-muted-foreground mt-1">
                                Issue, track, and manage employee RFID badges
                            </p>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={isRefreshing}
                        >
                            <RefreshCw className={`h-4 w-4 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
                            {isRefreshing ? 'Refreshing...' : 'Refresh'}
                        </Button>
                    </div>
                </div>

                {/* Badge Stats Dashboard - Subtask 1.1.2 */}
                <BadgeStatsWidget stats={safeStats} onStatClick={handleStatClick} />

                {/* Quick Actions Section */}
                <div className="flex gap-3">
                    <Button asChild className="gap-2">
                        <Link href="/hr/timekeeping/badges/create">
                            <Plus className="h-4 w-4" />
                            Issue New Badge
                        </Link>
                    </Button>
                    <Button 
                        variant="outline" 
                        className="gap-2"
                        onClick={() => setIsImportModalOpen(true)}
                    >
                        <Upload className="h-4 w-4" />
                        Bulk Import
                    </Button>
                    <Button 
                        variant="outline" 
                        className="gap-2"
                        onClick={() => setIsReportModalOpen(true)}
                    >
                        <Download className="h-4 w-4" />
                        Generate Report
                    </Button>
                </div>

                {/* Employees Without Badges Widget - Task 1.8.1 & 1.8.2 */}
                {safeStats.employees_without_badges > 0 && (
                    <EmployeesWithoutBadges
                        employees={safeEmployeesWithoutBadges}
                        onIssueBadge={handleIssueBadgeToEmployee}
                    />
                )}

                {/* Tab Navigation - Subtask 1.1.1 */}
                <Tabs defaultValue={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList>
                        <TabsTrigger value="active" className="gap-2">
                            <CheckCircle className="h-4 w-4" />
                            Active Badges
                        </TabsTrigger>
                        <TabsTrigger value="inactive" className="gap-2">
                            <XCircle className="h-4 w-4" />
                            Inactive
                        </TabsTrigger>
                        <TabsTrigger value="expired" className="gap-2">
                            <Clock className="h-4 w-4" />
                            Expired
                        </TabsTrigger>
                        <TabsTrigger value="no-badge" className="gap-2">
                            <AlertTriangle className="h-4 w-4" />
                            No Badge ({safeStats.employees_without_badges})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="active">
                        <Card>
                            <CardHeader>
                                <CardTitle>Active Badges</CardTitle>
                                <CardDescription>
                                    Currently active RFID badges assigned to employees
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BadgeManagementTable 
                                    badges={safeBadges} 
                                    status="active"
                                    onReplace={handleReplaceBadge}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="inactive">
                        <Card>
                            <CardHeader>
                                <CardTitle>Inactive Badges</CardTitle>
                                <CardDescription>
                                    Deactivated badges that are no longer in use
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BadgeManagementTable 
                                    badges={safeBadges} 
                                    status="inactive"
                                    onReplace={handleReplaceBadge}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="expired">
                        <Card>
                            <CardHeader>
                                <CardTitle>Expired Badges</CardTitle>
                                <CardDescription>
                                    Badges that have reached their expiration date
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BadgeManagementTable 
                                    badges={safeBadges} 
                                    status="expired"
                                    onReplace={handleReplaceBadge}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="no-badge">
                        <Card>
                            <CardHeader>
                                <CardTitle>Employees Without Badges</CardTitle>
                                <CardDescription>
                                    {safeStats.employees_without_badges} active employees need RFID badge assignment
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <Button asChild className="w-full">
                                        <Link href="/hr/timekeeping/badges/create">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Issue Badges to These Employees
                                        </Link>
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        Use bulk import to quickly issue badges to multiple employees at once.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Additional Info Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Badge Management Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid md:grid-cols-3 gap-4">
                            <div>
                                <h3 className="font-semibold mb-2">Quick Actions</h3>
                                <ul className="text-sm space-y-1 text-muted-foreground">
                                    <li>• Issue new badge to employee</li>
                                    <li>• Replace lost or damaged badge</li>
                                    <li>• Deactivate employee badge</li>
                                </ul>
                            </div>
                            <div>
                                <h3 className="font-semibold mb-2">Supported Card Types</h3>
                                <ul className="text-sm space-y-1 text-muted-foreground">
                                    <li>• Mifare (Standard)</li>
                                    <li>• DESFire (Advanced)</li>
                                    <li>• EM4100 (Legacy)</li>
                                </ul>
                            </div>
                            <div>
                                <h3 className="font-semibold mb-2">Key Features</h3>
                                <ul className="text-sm space-y-1 text-muted-foreground">
                                    <li>• Automatic expiration tracking</li>
                                    <li>• Usage analytics per badge</li>
                                    <li>• Compliance reporting</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Badge Replacement Modal - Task 1.5 */}
            {selectedBadgeForReplacement && (
                <BadgeReplacementModal
                    isOpen={isReplacementModalOpen}
                    onClose={() => {
                        setIsReplacementModalOpen(false);
                        setSelectedBadgeForReplacement(null);
                    }}
                    badge={selectedBadgeForReplacement}
                    onReplace={handleReplacementSubmit}
                />
            )}

            {/* Badge Report Modal - Task 1.6 */}
            <BadgeReportModal
                isOpen={isReportModalOpen}
                onClose={() => setIsReportModalOpen(false)}
                badges={safeBadges}
                employees={safeEmployees}
            />

            {/* Badge Bulk Import Modal - Task 1.7 */}
            <BadgeBulkImportModal
                isOpen={isImportModalOpen}
                onClose={() => setIsImportModalOpen(false)}
                badges={safeBadges}
                employees={safeEmployees}
            />

            {/* Badge Issuance Modal - Task 1.8.2 */}
            <BadgeIssuanceModal
                isOpen={isIssuanceModalOpen}
                onClose={() => {
                    setIsIssuanceModalOpen(false);
                    setSelectedEmployeeForIssuance(null);
                }}
                onSubmit={handleIssuanceSubmit}
                employees={safeEmployees}
                existingBadgeUids={safeBadges.data?.map((b) => b.card_uid) || []}
            />
        </AppLayout>
    );
}
