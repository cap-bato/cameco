import { useState } from 'react';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { Calendar, Edit, Plus, Trash2, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useToast } from '@/hooks/use-toast';
import { HolidayFormModal } from './holiday-form-modal';

export interface Holiday {
    id: string;
    name: string;
    date: string;
    type: 'regular' | 'special' | 'company';
    is_recurring: boolean;
    description?: string;
}

interface HolidayCalendarProps {
    holidays: Holiday[];
}

interface HolidayPageProps {
    holiday?: Holiday;
}

export function HolidayCalendar({ holidays: initialHolidays }: HolidayCalendarProps) {
    const { toast } = useToast();
    const [holidays, setHolidays] = useState<Holiday[]>(initialHolidays);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingHoliday, setEditingHoliday] = useState<Holiday | null>(null);
    const [deletingHoliday, setDeletingHoliday] = useState<Holiday | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [typeFilter, setTypeFilter] = useState<string>('all');

    const handleAddHoliday = () => {
        setEditingHoliday(null);
        setIsFormOpen(true);
    };

    const handleEditHoliday = (holiday: Holiday) => {
        setEditingHoliday(holiday);
        setIsFormOpen(true);
    };

    const handleDeleteClick = (holiday: Holiday) => {
        setDeletingHoliday(holiday);
    };

    const handleDeleteConfirm = () => {
        if (!deletingHoliday) return;

        router.delete(`/admin/business-rules/holidays/${deletingHoliday.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setHolidays(holidays.filter(h => h.id !== deletingHoliday.id));
                toast({
                    title: 'Holiday Deleted',
                    description: `${deletingHoliday.name} has been removed from the calendar.`,
                });
                setDeletingHoliday(null);
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: errors.message || 'Failed to delete holiday',
                    variant: 'destructive',
                });
            },
        });
    };

    const handleFormSuccess = (holiday: Holiday, isEdit: boolean) => {
        if (isEdit) {
            setHolidays(holidays.map(h => h.id === holiday.id ? holiday : h));
        } else {
            setHolidays([...holidays, holiday]);
        }
        setIsFormOpen(false);
        setEditingHoliday(null);
    };

    const handleImportNationalHolidays = () => {
        const nationalHolidays: Omit<Holiday, 'id'>[] = [
            { name: "New Year's Day", date: format(new Date(new Date().getFullYear(), 0, 1), 'yyyy-MM-dd'), type: 'regular', is_recurring: true },
            { name: 'Maundy Thursday', date: format(new Date(new Date().getFullYear(), 2, 28), 'yyyy-MM-dd'), type: 'regular', is_recurring: false },
            { name: 'Good Friday', date: format(new Date(new Date().getFullYear(), 2, 29), 'yyyy-MM-dd'), type: 'regular', is_recurring: false },
            { name: 'Araw ng Kagitingan', date: format(new Date(new Date().getFullYear(), 3, 9), 'yyyy-MM-dd'), type: 'regular', is_recurring: true },
            { name: 'Labor Day', date: format(new Date(new Date().getFullYear(), 4, 1), 'yyyy-MM-dd'), type: 'regular', is_recurring: true },
            { name: 'Independence Day', date: format(new Date(new Date().getFullYear(), 5, 12), 'yyyy-MM-dd'), type: 'regular', is_recurring: true },
            { name: 'Ninoy Aquino Day', date: format(new Date(new Date().getFullYear(), 7, 21), 'yyyy-MM-dd'), type: 'special', is_recurring: true },
            { name: 'National Heroes Day', date: format(new Date(new Date().getFullYear(), 7, 28), 'yyyy-MM-dd'), type: 'regular', is_recurring: true },
            { name: "All Saints' Day", date: format(new Date(new Date().getFullYear(), 10, 1), 'yyyy-MM-dd'), type: 'special', is_recurring: true },
            { name: 'Bonifacio Day', date: format(new Date(new Date().getFullYear(), 10, 30), 'yyyy-MM-dd'), type: 'regular', is_recurring: true },
        ];

        // Import holidays by sending them to the backend
        nationalHolidays.forEach((holiday) => {
            router.post('/admin/business-rules/holidays', holiday, {
                preserveScroll: true,
                onSuccess: (page) => {
                    const pageProps = page.props as HolidayPageProps;
                    const newHoliday = pageProps.holiday;
                    if (newHoliday) {
                        setHolidays(prev => [...prev, newHoliday]);
                    }
                },
            });
        });

        toast({
            title: 'Importing Holidays',
            description: 'National holidays are being added to the calendar.',
        });
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'regular':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
            case 'special':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
            case 'company':
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
        }
    };

    const filteredHolidays = holidays
        .filter(holiday => {
            const matchesSearch = holiday.name.toLowerCase().includes(searchQuery.toLowerCase());
            const matchesType = typeFilter === 'all' || holiday.type === typeFilter;
            return matchesSearch && matchesType;
        })
        .sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());

    return (
        <>
            <Card className="p-6">
                <div className="space-y-4">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-semibold">Holiday Calendar</h3>
                            <p className="text-sm text-muted-foreground">
                                Manage company holidays and non-working days
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleImportNationalHolidays}
                            >
                                <Calendar className="h-4 w-4 mr-2" />
                                Import PH Holidays
                            </Button>
                            <Button onClick={handleAddHoliday} size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Holiday
                            </Button>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="flex gap-4">
                        <div className="flex-1">
                            <input
                                type="text"
                                placeholder="Search holidays..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full px-3 py-2 border rounded-md text-sm"
                            />
                        </div>
                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="px-3 py-2 border rounded-md text-sm"
                        >
                            <option value="all">All Types</option>
                            <option value="regular">Regular Holidays</option>
                            <option value="special">Special Holidays</option>
                            <option value="company">Company Holidays</option>
                        </select>
                    </div>

                    {/* Table */}
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Holiday Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Recurring</TableHead>
                                    <TableHead className="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredHolidays.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                                            <div className="flex flex-col items-center gap-2">
                                                <Calendar className="h-8 w-8 opacity-50" />
                                                <p>No holidays found</p>
                                                <Button variant="link" size="sm" onClick={handleAddHoliday}>
                                                    Add your first holiday
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    filteredHolidays.map((holiday) => (
                                        <TableRow key={holiday.id}>
                                            <TableCell className="font-medium">
                                                {format(new Date(holiday.date), 'MMM dd, yyyy')}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">{holiday.name}</div>
                                                    {holiday.description && (
                                                        <div className="text-sm text-muted-foreground">
                                                            {holiday.description}
                                                        </div>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={getTypeColor(holiday.type)} variant="secondary">
                                                    {holiday.type.charAt(0).toUpperCase() + holiday.type.slice(1)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {holiday.is_recurring ? (
                                                    <Badge variant="outline">Yearly</Badge>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">One-time</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleEditHoliday(holiday)}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDeleteClick(holiday)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Info */}
                    <div className="flex items-start gap-2 rounded-lg border p-3 text-sm">
                        <AlertCircle className="h-4 w-4 text-muted-foreground mt-0.5" />
                        <div className="space-y-1">
                            <p className="font-medium">Holiday Types</p>
                            <ul className="text-muted-foreground space-y-1">
                                <li><strong>Regular:</strong> 200% pay rate (Philippine Labor Code)</li>
                                <li><strong>Special:</strong> 130% pay rate for work performed</li>
                                <li><strong>Company:</strong> Custom company-specific holidays</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </Card>

            {/* Holiday Form Modal */}
            <HolidayFormModal
                isOpen={isFormOpen}
                onClose={() => {
                    setIsFormOpen(false);
                    setEditingHoliday(null);
                }}
                holiday={editingHoliday}
                onSuccess={handleFormSuccess}
            />

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={!!deletingHoliday} onOpenChange={() => setDeletingHoliday(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Holiday</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{deletingHoliday?.name}"? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeleteConfirm} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
