import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { AppraisalCycle } from '@/types/appraisal-pages';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Plus, X } from 'lucide-react';

interface CycleEditProps {
    cycle: AppraisalCycle & {
        criteria?: Array<{ name: string; weight: number }>;
    };
}

export default function CycleEdit({ cycle }: CycleEditProps) {
    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Appraisals', href: '/hr/appraisals' },
        { title: 'Cycles', href: '/hr/appraisals/cycles' },
        { title: 'Edit', href: '#' },
    ];

    const [formData, setFormData] = useState({
        name: cycle.name || '',
        start_date: cycle.start_date || '',
        end_date: cycle.end_date || '',
        criteria: cycle.criteria || [
            { name: 'Quality of Work', weight: 20 },
            { name: 'Attendance & Punctuality', weight: 20 },
            { name: 'Behavior & Conduct', weight: 20 },
            { name: 'Productivity', weight: 20 },
            { name: 'Teamwork', weight: 20 },
        ],
    });

    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: value,
        }));
    };

    const handleCriterionChange = (index: number, field: 'name' | 'weight', value: string | number) => {
        setFormData((prev) => ({
            ...prev,
            criteria: prev.criteria.map((c, i) =>
                i === index
                    ? { ...c, [field]: field === 'weight' ? Number(value) : value }
                    : c
            ),
        }));
    };

    const handleAddCriterion = () => {
        setFormData((prev) => ({
            ...prev,
            criteria: [...prev.criteria, { name: '', weight: 0 }],
        }));
    };

    const handleRemoveCriterion = (index: number) => {
        setFormData((prev) => ({
            ...prev,
            criteria: prev.criteria.filter((_, i) => i !== index),
        }));
    };

    const getTotalWeight = () => {
        return formData.criteria.reduce((sum, c) => sum + (c.weight || 0), 0);
    };

    const validateForm = () => {
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

        if (formData.criteria.length < 3) {
            newErrors.criteria = 'At least 3 criteria are required';
        }

        const totalWeight = getTotalWeight();
        if (totalWeight !== 100) {
            newErrors.criteria = `Total weight must equal 100% (currently ${totalWeight}%)`;
        }

        return newErrors;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const newErrors = validateForm();

        if (Object.keys(newErrors).length === 0) {
            router.put(`/hr/appraisals/cycles/${cycle.id}`, formData, {
                onSuccess: () => {
                    router.visit('/hr/appraisals/cycles');
                },
            });
        } else {
            setErrors(newErrors);
        }
    };

    const totalWeight = getTotalWeight();
    const weightColor = totalWeight === 100 ? 'text-green-600' : 'text-red-600';

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title={`Edit Appraisal Cycle: ${cycle.name}`} />

            <div className="space-y-6 px-6 py-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => router.visit('/hr/appraisals/cycles')}
                            className="inline-flex items-center text-gray-600 hover:text-gray-900"
                        >
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            Back to Cycles
                        </button>
                    </div>
                    <div className="text-sm text-gray-500">Cycle ID: {cycle.id}</div>
                </div>

                <h1 className="text-3xl font-bold">Edit Appraisal Cycle</h1>
                <p className="text-gray-600">Update the cycle details and criteria below</p>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Cycle Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Cycle Name */}
                            <div>
                                <Label htmlFor="name">Cycle Name *</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={formData.name}
                                    onChange={handleInputChange}
                                    placeholder="e.g., Annual Review 2025"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                            </div>

                            {/* Date Range */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="start_date">Start Date *</Label>
                                    <Input
                                        id="start_date"
                                        name="start_date"
                                        type="date"
                                        value={formData.start_date}
                                        onChange={handleInputChange}
                                        className={errors.start_date ? 'border-red-500' : ''}
                                    />
                                    {errors.start_date && (
                                        <p className="text-red-500 text-sm mt-1">{errors.start_date}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="end_date">End Date *</Label>
                                    <Input
                                        id="end_date"
                                        name="end_date"
                                        type="date"
                                        value={formData.end_date}
                                        onChange={handleInputChange}
                                        className={errors.end_date ? 'border-red-500' : ''}
                                    />
                                    {errors.end_date && (
                                        <p className="text-red-500 text-sm mt-1">{errors.end_date}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Criteria Management */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Appraisal Criteria</CardTitle>
                            <div className={`text-sm font-semibold ${weightColor}`}>
                                Total Weight: {totalWeight}%
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {errors.criteria && (
                                <div className="bg-red-50 border border-red-200 rounded p-3 text-red-800 text-sm">
                                    {errors.criteria}
                                </div>
                            )}

                            <div className="space-y-4">
                                {formData.criteria.map((criterion, index) => (
                                    <div key={index} className="flex gap-3 items-end p-4 bg-gray-50 rounded-lg">
                                        <div className="flex-1">
                                            <Label className="text-sm">Criterion Name</Label>
                                            <Input
                                                value={criterion.name}
                                                onChange={(e) =>
                                                    handleCriterionChange(index, 'name', e.target.value)
                                                }
                                                placeholder="e.g., Quality of Work"
                                                className="mt-2"
                                            />
                                        </div>
                                        <div className="w-32">
                                            <Label className="text-sm">Weight %</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="100"
                                                value={criterion.weight}
                                                onChange={(e) =>
                                                    handleCriterionChange(
                                                        index,
                                                        'weight',
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="0"
                                                className="mt-2"
                                            />
                                        </div>
                                        {formData.criteria.length > 3 && (
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="icon"
                                                onClick={() => handleRemoveCriterion(index)}
                                            >
                                                <X className="w-4 h-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleAddCriterion}
                                className="w-full"
                            >
                                <Plus className="w-4 h-4 mr-2" />
                                Add Criterion
                            </Button>

                            <div className="bg-blue-50 border border-blue-200 rounded p-3 text-blue-800 text-sm">
                                <p className="font-semibold mb-2">Criteria Weight Rules:</p>
                                <ul className="list-disc list-inside space-y-1">
                                    <li>Total weight of all criteria must equal 100%</li>
                                    <li>Minimum 3 criteria required</li>
                                    <li>Each criterion weight between 0-100%</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Form Actions */}
                    <div className="flex gap-3 justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit('/hr/appraisals/cycles')}
                        >
                            Cancel
                        </Button>
                        <Button type="submit">Save Changes</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
