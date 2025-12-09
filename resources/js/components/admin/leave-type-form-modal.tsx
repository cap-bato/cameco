import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Loader2 } from 'lucide-react';

interface LeavePolicy {
    id: number;
    code: string;
    name: string;
    description: string;
    annual_entitlement: number;
    max_carryover: number;
    can_carry_forward: boolean;
    is_paid: boolean;
    is_active: boolean;
    effective_date: string;
}

interface LeaveTypeFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    policy: LeavePolicy | null;
    mode: 'create' | 'edit';
    onSuccess: () => void;
}

interface FormData {
    code: string;
    name: string;
    description: string;
    annual_entitlement: number;
    max_carryover: number;
    can_carry_forward: boolean;
    is_paid: boolean;
    is_active: boolean;
    effective_date: string;
}

interface FormErrors {
    [key: string]: string;
}

export function LeaveTypeFormModal({ isOpen, onClose, policy, mode, onSuccess }: LeaveTypeFormModalProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<FormErrors>({});
    const [formData, setFormData] = useState<FormData>({
        code: '',
        name: '',
        description: '',
        annual_entitlement: 15,
        max_carryover: 5,
        can_carry_forward: true,
        is_paid: true,
        is_active: true,
        effective_date: new Date().toISOString().split('T')[0],
    });

    useEffect(() => {
        if (policy && mode === 'edit') {
            setFormData({
                code: policy.code || '',
                name: policy.name || '',
                description: policy.description || '',
                annual_entitlement: policy.annual_entitlement || 15,
                max_carryover: policy.max_carryover || 5,
                can_carry_forward: policy.can_carry_forward ?? true,
                is_paid: policy.is_paid ?? true,
                is_active: policy.is_active ?? true,
                effective_date: policy.effective_date || new Date().toISOString().split('T')[0],
            });
        } else {
            setFormData({
                code: '',
                name: '',
                description: '',
                annual_entitlement: 15,
                max_carryover: 5,
                can_carry_forward: true,
                is_paid: true,
                is_active: true,
                effective_date: new Date().toISOString().split('T')[0],
            });
        }
        setErrors({});
    }, [policy, mode, isOpen]);

    const validateForm = (): boolean => {
        const newErrors: FormErrors = {};

        if (!formData.code.trim()) {
            newErrors.code = 'Code is required';
        } else if (formData.code.length > 20) {
            newErrors.code = 'Code must be 20 characters or less';
        }

        if (!formData.name.trim()) {
            newErrors.name = 'Leave type name is required';
        } else if (formData.name.length > 255) {
            newErrors.name = 'Name must be 255 characters or less';
        }

        if (formData.description && formData.description.length > 1000) {
            newErrors.description = 'Description must be 1000 characters or less';
        }

        if (formData.annual_entitlement < 0 || formData.annual_entitlement > 365) {
            newErrors.annual_entitlement = 'Annual entitlement must be between 0 and 365 days';
        }

        if (formData.max_carryover < 0 || formData.max_carryover > 365) {
            newErrors.max_carryover = 'Max carryover must be between 0 and 365 days';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);

        const url = mode === 'create' 
            ? '/admin/leave-policies' 
            : `/admin/leave-policies/${policy?.id}`;

        const method = mode === 'create' ? 'post' : 'put';

        router[method](url, formData as any, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmitting(false);
                onSuccess();
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setErrors(errors as FormErrors);
            },
        });
    };

    const handleInputChange = (field: keyof FormData, value: string | number | boolean) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear error for this field when user starts typing
        if (errors[field]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create' ? 'Create Leave Type' : 'Edit Leave Type'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create' 
                            ? 'Configure a new leave type with entitlements and carryover rules.' 
                            : 'Update leave type configuration and rules.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Basic Information</h3>
                        
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Leave Type Name *</Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    placeholder="e.g., Vacation Leave"
                                    className={errors.name ? 'border-destructive' : ''}
                                />
                                {errors.name && (
                                    <p className="text-xs text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="code">Code *</Label>
                                <Input
                                    id="code"
                                    value={formData.code}
                                    onChange={(e) => handleInputChange('code', e.target.value.toUpperCase())}
                                    placeholder="e.g., VL"
                                    maxLength={20}
                                    className={errors.code ? 'border-destructive' : ''}
                                />
                                {errors.code && (
                                    <p className="text-xs text-destructive">{errors.code}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => handleInputChange('description', e.target.value)}
                                placeholder="Brief description of this leave type"
                                rows={3}
                                className={errors.description ? 'border-destructive' : ''}
                            />
                            {errors.description && (
                                <p className="text-xs text-destructive">{errors.description}</p>
                            )}
                        </div>
                    </div>

                    {/* Entitlement Settings */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Entitlement Settings</h3>
                        
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="annual_entitlement">Annual Entitlement (Days) *</Label>
                                <Input
                                    id="annual_entitlement"
                                    type="number"
                                    min="0"
                                    max="365"
                                    step="0.5"
                                    value={formData.annual_entitlement}
                                    onChange={(e) => handleInputChange('annual_entitlement', parseFloat(e.target.value) || 0)}
                                    className={errors.annual_entitlement ? 'border-destructive' : ''}
                                />
                                {errors.annual_entitlement && (
                                    <p className="text-xs text-destructive">{errors.annual_entitlement}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Total days per year (0-365)
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="effective_date">Effective Date</Label>
                                <Input
                                    id="effective_date"
                                    type="date"
                                    value={formData.effective_date}
                                    onChange={(e) => handleInputChange('effective_date', e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    When this policy takes effect
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Carryover Settings */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Carryover Settings</h3>
                        
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="can_carry_forward"
                                checked={formData.can_carry_forward}
                                onCheckedChange={(checked) => handleInputChange('can_carry_forward', checked)}
                            />
                            <Label htmlFor="can_carry_forward" className="font-normal cursor-pointer">
                                Allow carrying forward unused days to next year
                            </Label>
                        </div>

                        {formData.can_carry_forward && (
                            <div className="space-y-2 ml-6">
                                <Label htmlFor="max_carryover">Maximum Carryover Days *</Label>
                                <Input
                                    id="max_carryover"
                                    type="number"
                                    min="0"
                                    max="365"
                                    step="0.5"
                                    value={formData.max_carryover}
                                    onChange={(e) => handleInputChange('max_carryover', parseFloat(e.target.value) || 0)}
                                    className={errors.max_carryover ? 'border-destructive' : ''}
                                />
                                {errors.max_carryover && (
                                    <p className="text-xs text-destructive">{errors.max_carryover}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Maximum days that can be carried forward (0-365)
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Additional Settings */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Additional Settings</h3>
                        
                        <div className="space-y-3">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_paid"
                                    checked={formData.is_paid}
                                    onCheckedChange={(checked) => handleInputChange('is_paid', checked)}
                                />
                                <Label htmlFor="is_paid" className="font-normal cursor-pointer">
                                    This is a paid leave type
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={formData.is_active}
                                    onCheckedChange={(checked) => handleInputChange('is_active', checked)}
                                />
                                <Label htmlFor="is_active" className="font-normal cursor-pointer">
                                    Active (available for leave requests)
                                </Label>
                            </div>
                        </div>
                    </div>

                    {/* Accrual Method Info */}
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Accrual Method:</strong> Currently set to Annual accrual. 
                            Employees will receive full entitlement on their hire date anniversary or January 1st.
                            Monthly and pro-rated accrual options will be available in a future update.
                        </AlertDescription>
                    </Alert>

                    {/* Form Errors */}
                    {Object.keys(errors).length > 0 && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                Please correct the errors above before submitting.
                            </AlertDescription>
                        </Alert>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {mode === 'create' ? 'Create Leave Type' : 'Update Leave Type'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
