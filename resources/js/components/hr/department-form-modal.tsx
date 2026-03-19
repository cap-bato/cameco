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
import { usePage } from '@inertiajs/react';

// ============================================================================
// Type Definitions
// ============================================================================

export interface Department {
    id: number;
    name: string;
    code: string;
    description: string | null;
    parent_id: number | null;
    is_active: boolean;
    employee_count?: number;
}

interface DepartmentFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (data: Omit<Department, 'id' | 'employee_count'>) => Promise<void>;
    department?: Department | null;
    departments?: Department[];
    mode?: 'create' | 'edit';
}

// ============================================================================
// Component
// ============================================================================

export function DepartmentFormModal({
    isOpen,
    onClose,
    onSubmit,
    department,
    departments = [],
    mode = 'create'
}: DepartmentFormModalProps) {
    const page = usePage();
    const [formData, setFormData] = useState({
        name: '',
        code: '',
        description: '',
        parent_id: '',
        is_active: true,
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<{ [key: string]: string }>({});

    // Initialize form with department data when editing
    useEffect(() => {
        if (mode === 'edit' && department) {
            setFormData({
                name: department.name,
                code: department.code,
                description: department.description || '',
                parent_id: department.parent_id ? String(department.parent_id) : '',
                is_active: department.is_active,
            });
        } else {
            // Reset form for create mode
            setFormData({
                name: '',
                code: '',
                description: '',
                // Pre-fill parent_id if passed (e.g. from "Add Child" action)
                parent_id: department?.parent_id ? String(department.parent_id) : '',
                is_active: true,
            });
        }
        
        // Check for validation errors from Inertia
        const errors = page.props.errors as { [key: string]: string } | undefined;
        if (errors && Object.keys(errors).length > 0) {
            setFieldErrors(errors);
        } else {
            setFieldErrors({});
        }
        setError(null);
    }, [mode, department, isOpen, page.props.errors]);

    const handleInputChange = (
        e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
    ) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSelectChange = (value: string) => {
        setFormData(prev => ({
            ...prev,
            parent_id: value
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
        setFieldErrors({});

        // Validation
        if (!formData.name.trim()) {
            setError('Department name is required');
            return;
        }

        if (!formData.code.trim()) {
            setError('Department code is required');
            return;
        }

        // Prevent department from being its own parent
        if (mode === 'edit' && department && formData.parent_id === String(department.id)) {
            setError('A department cannot be its own parent');
            return;
        }

        try {
            setIsLoading(true);
            await onSubmit({
                name: formData.name.trim(),
                code: formData.code.trim(),
                description: formData.description.trim() || null,
                parent_id: formData.parent_id ? Number(formData.parent_id) : null,
                is_active: formData.is_active,
            });
            // Only close if no field errors (Inertia will set them if validation fails)
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsLoading(false);
        }
    };

    // Filter out current department from parent options when editing
    const availableDepartments = departments.filter(
        d => mode !== 'edit' || d.id !== department?.id
    );

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create' ? 'Create Department' : 'Edit Department'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Add a new department to your organization'
                            : 'Update department information'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Error Message */}
                    {error && (
                        <div className="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950 dark:text-red-200">
                            {error}
                        </div>
                    )}

                    {/* Department Name */}
                    <div className="space-y-2">
                        <Label htmlFor="name">Department Name *</Label>
                        <Input
                            id="name"
                            name="name"
                            placeholder="e.g., Human Resources"
                            value={formData.name}
                            onChange={handleInputChange}
                            disabled={isLoading}
                            className={fieldErrors.name ? 'border-red-500' : ''}
                        />
                        {fieldErrors.name && (
                            <p className="text-sm text-red-600 dark:text-red-400">{fieldErrors.name}</p>
                        )}
                    </div>

                    {/* Department Code */}
                    <div className="space-y-2">
                        <Label htmlFor="code">Department Code *</Label>
                        <Input
                            id="code"
                            name="code"
                            placeholder="e.g., HR"
                            value={formData.code}
                            onChange={handleInputChange}
                            disabled={isLoading}
                            className={fieldErrors.code ? 'border-red-500' : ''}
                        />
                        {fieldErrors.code && (
                            <p className="text-sm text-red-600 dark:text-red-400">{fieldErrors.code}</p>
                        )}
                    </div>

                    {/* Description */}
                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            placeholder="Department description..."
                            value={formData.description}
                            onChange={handleInputChange}
                            disabled={isLoading}
                            rows={3}
                            className={fieldErrors.description ? 'border-red-500' : ''}
                        />
                        {fieldErrors.description && (
                            <p className="text-sm text-red-600 dark:text-red-400">{fieldErrors.description}</p>
                        )}
                    </div>

                    {/* Parent Department */}
                    <div className="space-y-2">
                        <Label htmlFor="parent_id">Parent Department (optional)</Label>
                        <Select value={formData.parent_id} onValueChange={handleSelectChange}>
                            <SelectTrigger id="parent_id" disabled={isLoading} className={fieldErrors.parent_id ? 'border-red-500' : ''}>
                                <SelectValue placeholder="Select parent department..." />
                            </SelectTrigger>
                            <SelectContent>
                                {availableDepartments.map(dept => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>
                                        {dept.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {fieldErrors.parent_id && (
                            <p className="text-sm text-red-600 dark:text-red-400">{fieldErrors.parent_id}</p>
                        )}
                    </div>

                    {/* Active Status */}
                    <div className="space-y-2">
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
                        {fieldErrors.is_active && (
                            <p className="text-sm text-red-600 dark:text-red-400">{fieldErrors.is_active}</p>
                        )}
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
