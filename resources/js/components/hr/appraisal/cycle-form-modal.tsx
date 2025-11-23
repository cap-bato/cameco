import React, { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Plus, X } from 'lucide-react';
import { AppraisalCycleFormData } from '@/types/appraisal-pages';

interface CycleFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    cycle?: {
        id?: number;
        name: string;
        start_date: string;
        end_date: string;
        status: 'open' | 'closed';
        criteria?: Array<{ name: string; weight: number }>;
    };
    mode: 'create' | 'edit';
}

const DEFAULT_CRITERIA = [
    { name: 'Quality of Work', weight: 20 },
    { name: 'Attendance', weight: 20 },
    { name: 'Behavior', weight: 20 },
    { name: 'Productivity', weight: 20 },
    { name: 'Teamwork', weight: 20 },
];

export function CycleFormModal({ isOpen, onClose, cycle, mode }: CycleFormModalProps) {
    const [formData, setFormData] = useState<AppraisalCycleFormData>({
        name: (cycle?.name) || '',
        start_date: (cycle?.start_date) || '',
        end_date: (cycle?.end_date) || '',
        criteria: (cycle?.criteria) || DEFAULT_CRITERIA,
    });

    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const totalWeight = formData.criteria.reduce((sum, c) => sum + c.weight, 0);
    const isWeightValid = totalWeight === 100;

    const handleAddCriterion = () => {
        setFormData({
            ...formData,
            criteria: [...formData.criteria, { name: '', weight: 0 }],
        });
    };

    const handleRemoveCriterion = (index: number) => {
        setFormData({
            ...formData,
            criteria: formData.criteria.filter((_, i) => i !== index),
        });
    };

    const handleCriterionChange = (index: number, field: 'name' | 'weight', value: string | number) => {
        const newCriteria = [...formData.criteria];
        newCriteria[index] = {
            ...newCriteria[index],
            [field]: field === 'weight' ? Number(value) : value,
        };
        setFormData({ ...formData, criteria: newCriteria });
    };

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'Cycle name is required';
        }

        if (!formData.start_date) {
            newErrors.start_date = 'Start date is required';
        }

        if (!formData.end_date) {
            newErrors.end_date = 'End date is required';
        }

        if (formData.start_date && formData.end_date && formData.start_date >= formData.end_date) {
            newErrors.end_date = 'End date must be after start date';
        }

        if (!isWeightValid) {
            newErrors.criteria = 'Criteria weights must total 100%';
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

        if (mode === 'edit' && cycle?.id) {
            // @ts-expect-error - Type assertion for Inertia router compatibility
            router.put(`/hr/appraisals/cycles/${cycle.id}`, formData, {
                onSuccess: () => {
                    onClose();
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            });
        } else {
            // @ts-expect-error - Type assertion for Inertia router compatibility
            router.post('/hr/appraisals/cycles', formData, {
                onSuccess: () => {
                    onClose();
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'edit' ? 'Edit Appraisal Cycle' : 'Create New Appraisal Cycle'}
                        </DialogTitle>
                        <DialogDescription>
                            {mode === 'edit'
                                ? 'Update the cycle details and criteria'
                                : 'Set up a new performance review cycle'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-6 py-6">
                        {/* Basic Information */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">Basic Information</h3>

                            {/* Cycle Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">Cycle Name *</Label>
                                <Input
                                    id="name"
                                    placeholder="e.g., Annual Review 2025"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                            </div>

                            {/* Start Date */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="start_date">Start Date *</Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={formData.start_date}
                                        onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                                        className={errors.start_date ? 'border-red-500' : ''}
                                    />
                                    {errors.start_date && <p className="text-sm text-red-500">{errors.start_date}</p>}
                                </div>

                                {/* End Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="end_date">End Date *</Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={formData.end_date}
                                        onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                                        className={errors.end_date ? 'border-red-500' : ''}
                                    />
                                    {errors.end_date && <p className="text-sm text-red-500">{errors.end_date}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Appraisal Criteria */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold">Appraisal Criteria</h3>
                                <div className="flex items-center gap-2">
                                    <Badge variant={isWeightValid ? 'outline' : 'destructive'}>
                                        Total: {totalWeight}%
                                    </Badge>
                                </div>
                            </div>

                            {errors.criteria && (
                                <p className="text-sm text-red-500 bg-red-50 p-3 rounded">{errors.criteria}</p>
                            )}

                            <div className="space-y-3 max-h-[250px] overflow-y-auto">
                                {formData.criteria.map((criterion, index) => (
                                    <div key={index} className="flex gap-3 items-end">
                                        <div className="flex-1">
                                            <Label htmlFor={`criterion-name-${index}`} className="text-xs">
                                                Criterion Name
                                            </Label>
                                            <Input
                                                id={`criterion-name-${index}`}
                                                placeholder="e.g., Quality of Work"
                                                value={criterion.name}
                                                onChange={(e) =>
                                                    handleCriterionChange(index, 'name', e.target.value)
                                                }
                                                className="mt-1"
                                            />
                                        </div>

                                        <div className="w-24">
                                            <Label htmlFor={`criterion-weight-${index}`} className="text-xs">
                                                Weight %
                                            </Label>
                                            <Input
                                                id={`criterion-weight-${index}`}
                                                type="number"
                                                min="0"
                                                max="100"
                                                placeholder="20"
                                                value={criterion.weight}
                                                onChange={(e) =>
                                                    handleCriterionChange(index, 'weight', e.target.value)
                                                }
                                                className="mt-1"
                                            />
                                        </div>

                                        {formData.criteria.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveCriterion(index)}
                                                className="h-9 w-9"
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleAddCriterion}
                                className="w-full mt-2"
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                Add Criterion
                            </Button>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting || !isWeightValid}>
                            {isSubmitting ? 'Saving...' : mode === 'edit' ? 'Update Cycle' : 'Create Cycle'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
