import { useState, useRef, FormEvent } from 'react';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import {
    Upload,
    FileText,
    X,
    Plus,
    Trash2,
    Sparkles,
    AlertCircle,
} from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';

// ============================================================================
// Type Definitions
// ============================================================================

interface VariableBase {
    name: string;
    label: string;
    type: 'text' | 'date' | 'number' | 'select';
    required: boolean;
    default_value?: string;
    options?: string | string[]; // Can be either format
}

interface Variable extends Omit<VariableBase, 'options'> {
    id: string;
    default_value: string;
    options: string; // Comma-separated string for UI
}

interface TemplateInput {
    id: number;
    name: string;
    category: string;
    description: string;
    status: 'active' | 'draft' | 'archived';
    variables: VariableBase[];
}

interface FormErrors {
    name?: string;
    category?: string;
    template_file?: string;
    variables?: string;
    general?: string;
}

interface CreateTemplateModalProps {
    open: boolean;
    onClose: () => void;
    onSuccess?: () => void;
    template?: TemplateInput; // For edit mode
}

// ============================================================================
// Constants
// ============================================================================

const DOCUMENT_CATEGORIES = [
    { value: 'personal', label: 'Personal Documents' },
    { value: 'educational', label: 'Educational Records' },
    { value: 'employment', label: 'Employment Documents' },
    { value: 'medical', label: 'Medical Records' },
    { value: 'contracts', label: 'Contracts & Agreements' },
    { value: 'benefits', label: 'Benefits Documents' },
    { value: 'performance', label: 'Performance Records' },
    { value: 'separation', label: 'Separation Documents' },
    { value: 'government', label: 'Government Documents' },
    { value: 'special', label: 'Special Documents' },
    { value: 'payroll', label: 'Payroll Documents' },
    { value: 'communication', label: 'Communication Documents' },
];

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ACCEPTED_FILE_TYPE = '.docx';

// Helper function outside component to avoid render impurity
let variableIdCounter = 0;
const generateVariableId = () => {
    variableIdCounter += 1;
    return `var_${variableIdCounter}_${Date.now()}`;
};

// ============================================================================
// Main Component
// ============================================================================

export function CreateTemplateModal({ open, onClose, onSuccess, template }: CreateTemplateModalProps) {
    const { toast } = useToast();
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Convert template variables to internal format with IDs
    const convertToInternalVariables = (vars: VariableBase[]): Variable[] => {
        return vars.map(v => ({
            ...v,
            id: generateVariableId(),
            default_value: v.default_value || '',
            options: Array.isArray(v.options) ? v.options.join(', ') : (v.options || ''),
        }));
    };

    // Form state
    const [name, setName] = useState(template?.name || '');
    const [category, setCategory] = useState(template?.category || '');
    const [description, setDescription] = useState(template?.description || '');
    const [status, setStatus] = useState<'active' | 'draft'>((template?.status === 'archived' ? 'draft' : template?.status) || 'draft');
    const [file, setFile] = useState<File | null>(null);
    const [variables, setVariables] = useState<Variable[]>(
        template?.variables ? convertToInternalVariables(template.variables) : []
    );
    const [errors, setErrors] = useState<FormErrors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [isParsing, setIsParsing] = useState(false);

    // Character counters
    const nameLength = name.length;
    const descriptionLength = description.length;

    // Handle file selection
    const handleFileChange = (selectedFile: File | null) => {
        if (!selectedFile) return;

        // Validate file type
        if (!selectedFile.name.endsWith('.docx')) {
            setErrors(prev => ({
                ...prev,
                template_file: 'Only .docx files are accepted',
            }));
            return;
        }

        // Validate file size
        if (selectedFile.size > MAX_FILE_SIZE) {
            setErrors(prev => ({
                ...prev,
                template_file: `File size must be less than ${formatFileSize(MAX_FILE_SIZE)}`,
            }));
            return;
        }

        setFile(selectedFile);
        setErrors(prev => ({ ...prev, template_file: undefined }));
    };

    // Handle drag and drop
    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
        const droppedFile = e.dataTransfer.files[0];
        handleFileChange(droppedFile);
    };

    // Handle file input click
    const handleFileInputClick = () => {
        fileInputRef.current?.click();
    };

    // Parse variables from file
    const handleParseVariables = async () => {
        if (!file) {
            toast({
                title: 'No file uploaded',
                description: 'Please upload a template file first',
                variant: 'destructive',
            });
            return;
        }

        setIsParsing(true);

        // Simulate parsing (in real implementation, this would parse the DOCX file)
        setTimeout(() => {
            // Mock detected variables
            const detectedVariables: Variable[] = [
                {
                    id: generateVariableId(),
                    name: 'employee_name',
                    label: 'Employee Full Name',
                    type: 'text',
                    required: true,
                    default_value: '',
                    options: '',
                },
                {
                    id: generateVariableId(),
                    name: 'employee_number',
                    label: 'Employee Number',
                    type: 'text',
                    required: true,
                    default_value: '',
                    options: '',
                },
                {
                    id: generateVariableId(),
                    name: 'date',
                    label: 'Document Date',
                    type: 'date',
                    required: true,
                    default_value: '',
                    options: '',
                },
            ];

            setVariables(detectedVariables);
            setIsParsing(false);
            toast({
                title: 'Variables parsed',
                description: `Found ${detectedVariables.length} variables in template`,
            });
        }, 1500);
    };

    // Add new variable
    const handleAddVariable = () => {
        const newVariable: Variable = {
            id: generateVariableId(),
            name: '',
            label: '',
            type: 'text',
            required: false,
            default_value: '',
            options: '',
        };
        setVariables([...variables, newVariable]);
    };

    // Update variable
    const handleUpdateVariable = (id: string, field: keyof Variable, value: string | boolean) => {
        setVariables(variables.map(v => 
            v.id === id ? { ...v, [field]: value } : v
        ));
    };

    // Remove variable
    const handleRemoveVariable = (id: string) => {
        setVariables(variables.filter(v => v.id !== id));
    };

    // Validate form
    const validateForm = (): boolean => {
        const newErrors: FormErrors = {};

        if (!name.trim()) {
            newErrors.name = 'Template name is required';
        } else if (name.length > 255) {
            newErrors.name = 'Template name must not exceed 255 characters';
        }

        if (!category) {
            newErrors.category = 'Category is required';
        }

        if (!template && !file) {
            newErrors.template_file = 'Template file is required';
        }

        // Validate variables
        const variableNames = new Set<string>();
        for (const variable of variables) {
            if (!variable.name) {
                newErrors.variables = 'All variables must have a name';
                break;
            }
            if (!variable.label) {
                newErrors.variables = 'All variables must have a display label';
                break;
            }
            if (variableNames.has(variable.name)) {
                newErrors.variables = `Duplicate variable name: ${variable.name}`;
                break;
            }
            variableNames.add(variable.name);
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Handle submit
    const handleSubmit = async (e: FormEvent, saveStatus: 'active' | 'draft') => {
        e.preventDefault();

        if (!validateForm()) return;

        setIsSubmitting(true);

        try {
            // Convert variables back to API format
            const apiVariables = variables.map(v => ({
                name: v.name,
                label: v.label,
                type: v.type,
                required: v.required,
                default_value: v.default_value || undefined,
                options: v.options ? v.options.split(',').map(o => o.trim()).filter(Boolean) : undefined,
            }));

            const formData = new FormData();
            formData.append('name', name);
            formData.append('category', category);
            formData.append('description', description);
            formData.append('status', saveStatus);
            formData.append('variables', JSON.stringify(apiVariables));
            
            if (file) {
                formData.append('template_file', file);
            }

            const endpoint = template 
                ? `/hr/documents/templates/${template.id}`
                : '/hr/documents/templates';

            router.post(endpoint, formData, {
                onSuccess: () => {
                    toast({
                        title: template ? 'Template updated' : 'Template created',
                        description: `Template "${name}" has been ${saveStatus === 'active' ? 'created and activated' : 'saved as draft'}`,
                    });
                    onSuccess?.();
                    handleClose();
                },
                onError: (errors) => {
                    setErrors(errors as FormErrors);
                    toast({
                        title: 'Error',
                        description: 'Failed to save template. Please check the form.',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        } catch {
            toast({
                title: 'Error',
                description: 'An unexpected error occurred',
                variant: 'destructive',
            });
            setIsSubmitting(false);
        }
    };

    // Handle close
    const handleClose = () => {
        if (!isSubmitting) {
            setName('');
            setCategory('');
            setDescription('');
            setStatus('draft');
            setFile(null);
            setVariables([]);
            setErrors({});
            onClose();
        }
    };

    // Helper: Format file size
    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {template ? 'Edit Template' : 'Create New Template'}
                    </DialogTitle>
                    <DialogDescription>
                        {template 
                            ? 'Update template details, file, and merge variables'
                            : 'Upload a DOCX file with merge fields like {{employee_name}} and define variables'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={(e) => handleSubmit(e, status)}>
                    {errors.general && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{errors.general}</AlertDescription>
                        </Alert>
                    )}

                    {/* Template Name */}
                    <div className="space-y-2 mb-4">
                        <Label htmlFor="name" className="required">
                            Template Name
                        </Label>
                        <Input
                            id="name"
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                setErrors(prev => ({ ...prev, name: undefined }));
                            }}
                            placeholder="e.g., Certificate of Employment"
                            maxLength={255}
                            className={errors.name ? 'border-red-500' : ''}
                        />
                        <div className="flex justify-between text-xs text-gray-500">
                            <span>{errors.name && <span className="text-red-500">{errors.name}</span>}</span>
                            <span>{nameLength}/255 characters</span>
                        </div>
                    </div>

                    {/* Category */}
                    <div className="space-y-2 mb-4">
                        <Label htmlFor="category" className="required">
                            Category
                        </Label>
                        <Select value={category} onValueChange={(value) => {
                            setCategory(value);
                            setErrors(prev => ({ ...prev, category: undefined }));
                        }}>
                            <SelectTrigger className={errors.category ? 'border-red-500' : ''}>
                                <SelectValue placeholder="Select template category" />
                            </SelectTrigger>
                            <SelectContent>
                                {DOCUMENT_CATEGORIES.map((cat) => (
                                    <SelectItem key={cat.value} value={cat.value}>
                                        {cat.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.category && (
                            <p className="text-xs text-red-500">{errors.category}</p>
                        )}
                    </div>

                    {/* Description */}
                    <div className="space-y-2 mb-4">
                        <Label htmlFor="description">
                            Description
                        </Label>
                        <Textarea
                            id="description"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="Describe the purpose and usage of this template..."
                            rows={3}
                            maxLength={1000}
                        />
                        <div className="text-xs text-right text-gray-500">
                            {descriptionLength}/1000 characters
                        </div>
                    </div>

                    {/* Template File Upload */}
                    <div className="space-y-2 mb-4">
                        <Label className="required">
                            Template File {file && '(Uploaded)'}
                        </Label>
                        
                        {!file ? (
                            <div
                                onClick={handleFileInputClick}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                onDrop={handleDrop}
                                className={`
                                    border-2 border-dashed rounded-lg p-8 text-center cursor-pointer
                                    transition-colors duration-200
                                    ${isDragging ? 'border-primary bg-primary/5' : 'border-gray-300 hover:border-primary'}
                                    ${errors.template_file ? 'border-red-500' : ''}
                                `}
                            >
                                <Upload className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                <p className="text-sm text-gray-600 mb-1">
                                    Click to upload or drag and drop
                                </p>
                                <p className="text-xs text-gray-500">
                                    .DOCX files only (Max {formatFileSize(MAX_FILE_SIZE)})
                                </p>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept={ACCEPTED_FILE_TYPE}
                                    onChange={(e) => handleFileChange(e.target.files?.[0] || null)}
                                    className="hidden"
                                />
                            </div>
                        ) : (
                            <div className="border rounded-lg p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="p-2 bg-red-100 rounded">
                                            <FileText className="h-6 w-6 text-red-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">{file.name}</p>
                                            <p className="text-xs text-gray-500">{formatFileSize(file.size)}</p>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setFile(null)}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                        
                        {errors.template_file && (
                            <p className="text-xs text-red-500">{errors.template_file}</p>
                        )}
                        
                        <p className="text-xs text-gray-500">
                            Upload a DOCX file with merge fields like {'{{employee_name}}'}
                        </p>
                    </div>

                    {/* Variables Section */}
                    <div className="space-y-2 mb-4">
                        <div className="flex items-center justify-between">
                            <Label>Merge Variables</Label>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleParseVariables}
                                    disabled={!file || isParsing}
                                >
                                    <Sparkles className={`h-4 w-4 mr-2 ${isParsing ? 'animate-pulse' : ''}`} />
                                    {isParsing ? 'Parsing...' : 'Parse Variables'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAddVariable}
                                >
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Variable
                                </Button>
                            </div>
                        </div>

                        {errors.variables && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>{errors.variables}</AlertDescription>
                            </Alert>
                        )}

                        {variables.length === 0 ? (
                            <div className="border rounded-lg p-8 text-center text-gray-500">
                                <p className="text-sm">No variables defined</p>
                                <p className="text-xs mt-1">
                                    Upload a file and click "Parse Variables" or add manually
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3 border rounded-lg p-4 max-h-96 overflow-y-auto">
                                {variables.map((variable) => (
                                    <div key={variable.id} className="border rounded p-3 bg-gray-50">
                                        <div className="grid grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <Label className="text-xs">Variable Name</Label>
                                                <Input
                                                    value={variable.name}
                                                    onChange={(e) => handleUpdateVariable(variable.id, 'name', e.target.value)}
                                                    placeholder="employee_name"
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Display Label</Label>
                                                <Input
                                                    value={variable.label}
                                                    onChange={(e) => handleUpdateVariable(variable.id, 'label', e.target.value)}
                                                    placeholder="Employee Full Name"
                                                    className="mt-1"
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-3 gap-3 mb-3">
                                            <div>
                                                <Label className="text-xs">Type</Label>
                                                <Select
                                                    value={variable.type}
                                                    onValueChange={(value) => handleUpdateVariable(variable.id, 'type', value)}
                                                >
                                                    <SelectTrigger className="mt-1">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="text">Text</SelectItem>
                                                        <SelectItem value="date">Date</SelectItem>
                                                        <SelectItem value="number">Number</SelectItem>
                                                        <SelectItem value="select">Select</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label className="text-xs">Default Value</Label>
                                                <Input
                                                    value={variable.default_value}
                                                    onChange={(e) => handleUpdateVariable(variable.id, 'default_value', e.target.value)}
                                                    placeholder="Optional"
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div className="flex items-end">
                                                <div className="flex items-center space-x-2">
                                                    <Checkbox
                                                        id={`required-${variable.id}`}
                                                        checked={variable.required}
                                                        onCheckedChange={(checked) => handleUpdateVariable(variable.id, 'required', checked)}
                                                    />
                                                    <Label htmlFor={`required-${variable.id}`} className="text-xs">
                                                        Required
                                                    </Label>
                                                </div>
                                            </div>
                                        </div>
                                        {variable.type === 'select' && (
                                            <div className="mb-3">
                                                <Label className="text-xs">Options (comma-separated)</Label>
                                                <Textarea
                                                    value={variable.options}
                                                    onChange={(e) => handleUpdateVariable(variable.id, 'options', e.target.value)}
                                                    placeholder="Option 1, Option 2, Option 3"
                                                    rows={2}
                                                    className="mt-1"
                                                />
                                            </div>
                                        )}
                                        <div className="flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleRemoveVariable(variable.id)}
                                            >
                                                <Trash2 className="h-4 w-4 mr-1" />
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Status Radio Group */}
                    <div className="space-y-2 mb-6">
                        <Label>Template Status</Label>
                        <RadioGroup value={status} onValueChange={(value) => setStatus(value as 'active' | 'draft')}>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="active" id="active" />
                                <Label htmlFor="active" className="font-normal cursor-pointer">
                                    <span className="font-medium">Active</span> - Template available for document generation
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="draft" id="draft" />
                                <Label htmlFor="draft" className="font-normal cursor-pointer">
                                    <span className="font-medium">Draft</span> - Template not visible in generation list
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Footer Actions */}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={(e) => handleSubmit(e, 'draft')}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Saving...' : 'Save as Draft'}
                        </Button>
                        <Button
                            type="button"
                            onClick={(e) => handleSubmit(e, 'active')}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Saving...' : 'Save & Activate'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
