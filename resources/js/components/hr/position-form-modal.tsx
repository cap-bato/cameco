import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { useState, useEffect } from 'react';
import { Loader2 } from 'lucide-react';

// ============================================================================
// Type Definitions
// ============================================================================

export interface Position {
    id: number;
    title: string;
    code: string;
    description: string | null;
    level: string;
    department_id: number;
    reports_to: number | null;
    salary_min: number | null;
    salary_max: number | null;
    is_active: boolean;
    employee_count?: number;
}

export interface Department {
    id: number;
    name: string;
    code: string;
}

interface PositionFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (data: Omit<Position, 'id' | 'employee_count'>) => Promise<void>;
    position?: Position | null;
    departments: Department[];
    positions?: Position[];
    mode?: 'create' | 'edit';
}

// ============================================================================
// Component
// ============================================================================

export function PositionFormModal({
    isOpen,
    onClose,
    onSubmit,
    position,
    departments = [],
    positions = [],
    mode = 'create'
}: PositionFormModalProps) {
    const [formData, setFormData] = useState({
        title: '',
        code: '',
        description: '',
        level: '',
        department_id: '',
        reports_to: '',
        salary_min: '',
        salary_max: '',
        is_active: true,
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Initialize form with position data when editing
    useEffect(() => {
        if (mode === 'edit' && position) {
            setFormData({
                title: position.title,
                code: position.code,
                description: position.description || '',
                level: position.level || '',
                department_id: String(position.department_id),
                reports_to: position.reports_to ? String(position.reports_to) : '',
                salary_min: position.salary_min ? String(position.salary_min) : '',
                salary_max: position.salary_max ? String(position.salary_max) : '',
                is_active: position.is_active,
            });
        } else {
            // Reset form for create mode
            setFormData({
                title: '',
                code: '',
                description: '',
                level: '',
                department_id: '',
                reports_to: '',
                salary_min: '',
                salary_max: '',
                is_active: true,
            });
        }
        setError(null);
    }, [mode, position, isOpen]);

    const handleInputChange = (
        e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
    ) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSelectChange = (name: string, value: string) => {
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleCheckboxChange = (checked: boolean) => {
        setFormData(prev => ({
            ...prev,
            is_active: checked
        }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        // Validation
        if (!formData.title.trim()) {
            setError('Position title is required');
            return;
        }

        if (!formData.code.trim()) {
            setError('Position code is required');
            return;
        }

        if (!formData.level.trim()) {
            setError('Position level is required');
            return;
        }

        if (!formData.department_id) {
            setError('Department is required');
            return;
        }

        // Prevent position from reporting to itself
        if (mode === 'edit' && position && formData.reports_to === String(position.id)) {
            setError('A position cannot report to itself');
            return;
        }

        // Validate salary range
        if (formData.salary_min && formData.salary_max) {
            const min = parseFloat(formData.salary_min);
            const max = parseFloat(formData.salary_max);
            if (min > max) {
                setError('Minimum salary cannot be greater than maximum salary');
                return;
            }
        }

        try {
            setIsLoading(true);
            await onSubmit({
                title: formData.title.trim(),
                code: formData.code.trim(),
                description: formData.description.trim() || null,
                level: formData.level.trim(),
                department_id: Number(formData.department_id),
                reports_to: formData.reports_to ? Number(formData.reports_to) : null,
                salary_min: formData.salary_min ? parseFloat(formData.salary_min) : null,
                salary_max: formData.salary_max ? parseFloat(formData.salary_max) : null,
                is_active: formData.is_active,
            });
            onClose();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsLoading(false);
        }
    };

    // Filter positions for reports_to: exclude current position and only show in same department
    const availableReportingPositions = positions.filter(
        p => (mode !== 'edit' || p.id !== position?.id) && p.department_id === Number(formData.department_id)
    );

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[550px]">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create' ? 'Create Position' : 'Edit Position'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Add a new position to your organization'
                            : 'Update position information'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Error Message */}
                    {error && (
                        <div className="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950 dark:text-red-200">
                            {error}
                        </div>
                    )}

                    {/* Position Title */}
                    <div className="space-y-2">
                        <Label htmlFor="title">Position Title *</Label>
                        <Input
                            id="title"
                            name="title"
                            placeholder="e.g., Senior HR Manager"
                            value={formData.title}
                            onChange={handleInputChange}
                            disabled={isLoading}
                        />
                    </div>

                    {/* Position Code */}
                    <div className="space-y-2">
                        <Label htmlFor="code">Position Code *</Label>
                        <Input
                            id="code"
                            name="code"
                            placeholder="e.g., SHM001"
                            value={formData.code}
                            onChange={handleInputChange}
                            disabled={isLoading}
                        />
                    </div>

                    {/* Position Level */}
                    <div className="space-y-2">
                        <Label htmlFor="level">Position Level *</Label>
                        <Input
                            id="level"
                            name="level"
                            placeholder="e.g., junior, mid, senior, lead, manager"
                            value={formData.level}
                            onChange={handleInputChange}
                            disabled={isLoading}
                        />
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            placeholder="Position description and responsibilities..."
                            value={formData.description}
                            onChange={handleInputChange}
                            disabled={isLoading}
                            rows={3}
                        />
                    </div>

                    {/* Department */}
                    <div className="space-y-2">
                        <Label htmlFor="department_id">Department *</Label>
                        <Select
                            value={formData.department_id}
                            onValueChange={(value) => handleSelectChange('department_id', value)}
                        >
                            <SelectTrigger id="department_id" disabled={isLoading}>
                                <SelectValue placeholder="Select department..." />
                            </SelectTrigger>
                            <SelectContent>
                                {departments.map(dept => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>
                                        {dept.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Reports To */}
                    <div className="space-y-2">
                        <Label htmlFor="reports_to">Reports To (optional)</Label>
                        <Select
                            value={formData.reports_to}
                            onValueChange={(value) => handleSelectChange('reports_to', value)}
                        >
                            <SelectTrigger id="reports_to" disabled={isLoading || !formData.department_id}>
                                <SelectValue placeholder="Select reporting position..." />
                            </SelectTrigger>
                            <SelectContent>
                                {availableReportingPositions.map(pos => (
                                    <SelectItem key={pos.id} value={String(pos.id)}>
                                        {pos.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Salary Range */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-2">
                            <Label htmlFor="salary_min">Minimum Salary (optional)</Label>
                            <Input
                                id="salary_min"
                                name="salary_min"
                                type="number"
                                placeholder="0.00"
                                step="0.01"
                                value={formData.salary_min}
                                onChange={handleInputChange}
                                disabled={isLoading}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="salary_max">Maximum Salary (optional)</Label>
                            <Input
                                id="salary_max"
                                name="salary_max"
                                type="number"
                                placeholder="0.00"
                                step="0.01"
                                value={formData.salary_max}
                                onChange={handleInputChange}
                                disabled={isLoading}
                            />
                        </div>
                    </div>

                    {/* Active Status */}
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="is_active"
                            checked={formData.is_active}
                            onCheckedChange={handleCheckboxChange}
                            disabled={isLoading}
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            Active
                        </Label>
                    </div>

                    {/* Buttons */}
                    <div className="flex justify-end gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={isLoading}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={isLoading}
                            className="bg-blue-600 hover:bg-blue-700"
                        >
                            {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {mode === 'create' ? 'Create' : 'Update'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
