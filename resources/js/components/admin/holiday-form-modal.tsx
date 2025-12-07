import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Calendar as CalendarIcon } from 'lucide-react';
import { format } from 'date-fns';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/use-toast';
import type { Holiday } from './holiday-calendar';

interface HolidayFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    holiday: Holiday | null;
    onSuccess: (holiday: Holiday, isEdit: boolean) => void;
}

export function HolidayFormModal({ isOpen, onClose, holiday, onSuccess }: HolidayFormModalProps) {
    const { toast } = useToast();
    const isEdit = !!holiday;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: holiday?.name || '',
        date: holiday?.date || '',
        type: holiday?.type || 'regular',
        is_recurring: holiday?.is_recurring || false,
        description: holiday?.description || '',
    });

    useEffect(() => {
        if (holiday) {
            setData({
                name: holiday.name,
                date: holiday.date,
                type: holiday.type,
                is_recurring: holiday.is_recurring,
                description: holiday.description || '',
            });
        } else {
            reset();
        }
        clearErrors();
    }, [holiday, isOpen, setData, reset, clearErrors]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                // Create a temporary holiday object for immediate UI update
                const savedHoliday: Holiday = {
                    id: holiday?.id || String(Date.now()), // Temporary ID if creating new
                    name: data.name,
                    date: data.date,
                    type: data.type,
                    is_recurring: data.is_recurring,
                    description: data.description,
                };
                
                onSuccess(savedHoliday, isEdit);
                toast({
                    title: isEdit ? 'Holiday Updated' : 'Holiday Added',
                    description: `${data.name} has been ${isEdit ? 'updated' : 'added'} to the calendar.`,
                });
                reset();
                onClose();
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: `Failed to ${isEdit ? 'update' : 'add'} holiday`,
                    variant: 'destructive',
                });
            },
        };

        if (isEdit && holiday) {
            put(`/admin/business-rules/holidays/${holiday.id}`, options);
        } else {
            post('/admin/business-rules/holidays', options);
        }
    };

    const handleClose = () => {
        reset();
        clearErrors();
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Edit Holiday' : 'Add Holiday'}</DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Update the holiday information below.'
                            : 'Add a new holiday to the company calendar.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Holiday Name */}
                    <div className="space-y-2">
                        <Label htmlFor="name">
                            Holiday Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g., Christmas Day"
                            className={errors.name ? 'border-destructive' : ''}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">{errors.name}</p>
                        )}
                    </div>

                    {/* Date */}
                    <div className="space-y-2">
                        <Label htmlFor="date">
                            Date <span className="text-destructive">*</span>
                        </Label>
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    className={cn(
                                        'w-full justify-start text-left font-normal',
                                        !data.date && 'text-muted-foreground',
                                        errors.date && 'border-destructive'
                                    )}
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    {data.date ? format(new Date(data.date), 'PPP') : <span>Pick a date</span>}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                    mode="single"
                                    selected={data.date ? new Date(data.date) : undefined}
                                    onSelect={(date: Date | undefined) => {
                                        if (date) {
                                            setData('date', format(date, 'yyyy-MM-dd'));
                                        }
                                    }}
                                    initialFocus
                                />
                            </PopoverContent>
                        </Popover>
                        {errors.date && (
                            <p className="text-sm text-destructive">{errors.date}</p>
                        )}
                    </div>

                    {/* Type */}
                    <div className="space-y-2">
                        <Label htmlFor="type">
                            Holiday Type <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => setData('type', value as 'regular' | 'special' | 'company')}
                        >
                            <SelectTrigger className={errors.type ? 'border-destructive' : ''}>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="regular">Regular Holiday (200% pay)</SelectItem>
                                <SelectItem value="special">Special Holiday (130% pay)</SelectItem>
                                <SelectItem value="company">Company Holiday</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <p className="text-sm text-destructive">{errors.type}</p>
                        )}
                    </div>

                    {/* Recurring */}
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="is_recurring"
                            checked={data.is_recurring}
                            onCheckedChange={(checked) => setData('is_recurring', checked as boolean)}
                        />
                        <Label
                            htmlFor="is_recurring"
                            className="text-sm font-normal cursor-pointer"
                        >
                            This holiday occurs every year (recurring)
                        </Label>
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">Description (Optional)</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Additional notes about this holiday"
                            rows={3}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">{errors.description}</p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : isEdit ? 'Update Holiday' : 'Add Holiday'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
