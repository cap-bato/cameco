import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { EmployeeRotation, RotationPatternType, RotationPattern, Department } from '@/types/workforce-pages';
import { AlertCircle, Check, X } from 'lucide-react';

interface CreateEditRotationModalProps {
    isOpen: boolean;
    onClose: () => void;
    rotation?: EmployeeRotation | null;
    departments: Department[];
    pattern_templates?: Array<{
        pattern_type: RotationPatternType;
        name: string;
        pattern: RotationPattern;
    }>;
    onSave: (data: Record<string, unknown>) => void;
}

interface RotationFormData {
    id: number;
    name: string;
    description: string;
    pattern_type: RotationPatternType;
    pattern_json: RotationPattern;
    department_id: string;
    start_date: string;
    end_date: string;
}

const DEFAULT_PATTERNS: Record<RotationPatternType, RotationPattern> = {
    '4x2': {
        work_days: 4,
        rest_days: 2,
        pattern: [1, 1, 1, 1, 0, 0],
        cycle_length: 6,
        description: '4 Days Work / 2 Days Rest',
    },
    '5x2': {
        work_days: 5,
        rest_days: 2,
        pattern: [1, 1, 1, 1, 1, 0, 0],
        cycle_length: 7,
        description: '5 Days Work / 2 Days Rest',
    },
    '6x1': {
        work_days: 6,
        rest_days: 1,
        pattern: [1, 1, 1, 1, 1, 1, 0],
        cycle_length: 7,
        description: '6 Days Work / 1 Day Rest',
    },
    custom: {
        work_days: 0,
        rest_days: 0,
        pattern: [],
        description: 'Custom Pattern',
    },
};

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export function CreateEditRotationModal({
    isOpen,
    onClose,
    rotation,
    departments,
    onSave,
}: CreateEditRotationModalProps) {
    const [formData, setFormData] = useState<RotationFormData>({
        id: 0,
        name: '',
        description: '',
        pattern_type: '4x2' as RotationPatternType,
        pattern_json: DEFAULT_PATTERNS['4x2'],
        department_id: '',
        start_date: new Date().toISOString().split('T')[0],
        end_date: '',
    });

    const [isLoading, setIsLoading] = useState(false);
    const [showPatternBuilder, setShowPatternBuilder] = useState(false);
    const [patternInput, setPatternInput] = useState<number[]>([]);

    useEffect(() => {
        if (rotation) {
            setFormData({
                id: rotation.id,
                name: rotation.name,
                description: rotation.description || '',
                pattern_type: rotation.pattern_type,
                pattern_json: rotation.pattern_json,
                department_id: rotation.department_id?.toString() || '',
                start_date: new Date().toISOString().split('T')[0],
                end_date: '',
            });
            setPatternInput(rotation.pattern_json.pattern);
        } else {
            setFormData({
                id: 0,
                name: '',
                description: '',
                pattern_type: '4x2',
                pattern_json: DEFAULT_PATTERNS['4x2'],
                department_id: '',
                start_date: new Date().toISOString().split('T')[0],
                end_date: '',
            });
            setPatternInput([1, 1, 1, 1, 0, 0]);
        }
    }, [rotation, isOpen]);

    const handleInputChange = (
        field: string,
        value: string
    ) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const handlePatternTypeChange = (patternType: RotationPatternType) => {
        const pattern = DEFAULT_PATTERNS[patternType];
        setFormData((prev) => ({
            ...prev,
            pattern_type: patternType,
            pattern_json: pattern,
        }));
        setPatternInput(pattern.pattern);
    };

    const togglePatternDay = (index: number) => {
        const newPattern = [...patternInput];
        newPattern[index] = newPattern[index] === 1 ? 0 : 1;
        setPatternInput(newPattern);
        
        const workDays = newPattern.filter((d) => d === 1).length;
        const restDays = newPattern.filter((d) => d === 0).length;

        setFormData((prev) => ({
            ...prev,
            pattern_json: {
                ...prev.pattern_json,
                pattern: newPattern,
                work_days: workDays,
                rest_days: restDays,
                cycle_length: newPattern.length,
            },
        }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);

        try {
            await new Promise((resolve) => setTimeout(resolve, 500));
            onSave({
                ...formData,
                pattern_json: formData.pattern_json,
            });
        } finally {
            setIsLoading(false);
        }
    };

    const patternStats = {
        workDays: formData.pattern_json.work_days,
        restDays: formData.pattern_json.rest_days,
        cycleLength: formData.pattern_json.cycle_length || formData.pattern_json.pattern?.length || 0,
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {rotation ? 'Edit Rotation Pattern' : 'Create New Rotation Pattern'}
                    </DialogTitle>
                    <DialogDescription>
                        {rotation ? 'Update the rotation pattern details' : 'Define a new rotation pattern for your team'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Info */}
                    <div className="space-y-4">
                        <h3 className="font-semibold">Basic Information</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Name *</label>
                                <Input
                                    placeholder="e.g., 4 Days Work / 2 Days Rest"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Department</label>
                                <Select value={formData.department_id || 'none'} onValueChange={(v) => handleInputChange('department_id', v === 'none' ? '' : v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select department..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No Department</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.id.toString()}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">Description</label>
                            <Textarea
                                placeholder="Describe the rotation pattern and its purpose..."
                                value={formData.description}
                                onChange={(e) => handleInputChange('description', e.target.value)}
                                rows={2}
                            />
                        </div>
                    </div>

                    {/* Pattern Selection */}
                    <div className="space-y-4">
                        <h3 className="font-semibold">Rotation Pattern</h3>

                        <div className="grid grid-cols-2 gap-2">
                            {(['4x2', '5x2', '6x1', 'custom'] as RotationPatternType[]).map((type) => (
                                <Button
                                    key={type}
                                    type="button"
                                    variant={formData.pattern_type === type ? 'default' : 'outline'}
                                    onClick={() => handlePatternTypeChange(type)}
                                    className="justify-start"
                                >
                                    {type === 'custom' ? 'Custom' : type.toUpperCase()}
                                </Button>
                            ))}
                        </div>

                        {/* Pattern Statistics */}
                        <div className="grid grid-cols-3 gap-2">
                            <Card>
                                <CardContent className="pt-4">
                                    <p className="text-xs text-gray-600">Work Days</p>
                                    <p className="text-2xl font-bold">{patternStats.workDays}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-4">
                                    <p className="text-xs text-gray-600">Rest Days</p>
                                    <p className="text-2xl font-bold">{patternStats.restDays}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-4">
                                    <p className="text-xs text-gray-600">Cycle Length</p>
                                    <p className="text-2xl font-bold">{patternStats.cycleLength}d</p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Pattern Builder */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-sm font-medium">Weekly Pattern</label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowPatternBuilder(!showPatternBuilder)}
                                >
                                    {showPatternBuilder ? 'Hide' : 'Show'} Builder
                                </Button>
                            </div>

                            {showPatternBuilder && (
                                <div className="grid grid-cols-7 gap-1 p-4 bg-gray-50 rounded-lg">
                                    {DAYS.map((day, index) => (
                                        <button
                                            key={index}
                                            type="button"
                                            onClick={() => togglePatternDay(index)}
                                            className={`p-2 rounded text-center text-xs font-medium transition-colors ${
                                                patternInput[index] === 1
                                                    ? 'bg-blue-500 text-white'
                                                    : 'bg-gray-300 text-gray-700'
                                            }`}
                                            title={patternInput[index] === 1 ? 'Work' : 'Rest'}
                                        >
                                            <div>{day}</div>
                                            <div className="text-xs mt-1">
                                                {patternInput[index] === 1 ? (
                                                    <Check className="h-3 w-3 mx-auto" />
                                                ) : (
                                                    <X className="h-3 w-3 mx-auto" />
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Dates */}
                    <div className="space-y-4">
                        <h3 className="font-semibold">Effective Period</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Start Date *</label>
                                <Input
                                    type="date"
                                    value={formData.start_date}
                                    onChange={(e) => handleInputChange('start_date', e.target.value)}
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">End Date (Optional)</label>
                                <Input
                                    type="date"
                                    value={formData.end_date}
                                    onChange={(e) => handleInputChange('end_date', e.target.value)}
                                />
                            </div>
                        </div>

                        {!formData.end_date && (
                            <div className="flex items-start gap-2 p-3 bg-blue-50 rounded border border-blue-200">
                                <AlertCircle className="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                <p className="text-sm text-blue-700">No end date means this pattern runs indefinitely</p>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isLoading || !formData.name}>
                            {isLoading ? 'Saving...' : rotation ? 'Update Pattern' : 'Create Pattern'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
