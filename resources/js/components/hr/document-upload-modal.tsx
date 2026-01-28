import { useState, useRef, FormEvent } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { Progress } from '@/components/ui/progress';
import { 
    Upload, 
    File, 
    FileText, 
    Image as ImageIcon, 
    X, 
    Check,
    CalendarIcon,
    ChevronsUpDown,
} from 'lucide-react';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';

// ============================================================================
// Type Definitions
// ============================================================================

interface Employee {
    id: number;
    employee_number: string;
    name: string;
    department: string;
}

interface DocumentUploadModalProps {
    open: boolean;
    onClose: () => void;
    employees?: Employee[];
}

interface FormData {
    employee_id: string;
    category: string;
    document_type: string;
    file: File | null;
    expires_at: Date | undefined;
    notes: string;
}

interface FormErrors {
    employee_id?: string;
    category?: string;
    document_type?: string;
    file?: string;
    expires_at?: string;
    notes?: string;
}

// ============================================================================
// Helper Functions
// ============================================================================

const DOCUMENT_CATEGORIES = [
    { value: 'personal', label: 'Personal' },
    { value: 'educational', label: 'Educational' },
    { value: 'employment', label: 'Employment' },
    { value: 'medical', label: 'Medical' },
    { value: 'contracts', label: 'Contracts' },
    { value: 'benefits', label: 'Benefits' },
    { value: 'performance', label: 'Performance' },
    { value: 'separation', label: 'Separation' },
    { value: 'government', label: 'Government' },
    { value: 'special', label: 'Special' },
];

const DOCUMENT_TYPE_SUGGESTIONS: Record<string, string[]> = {
    personal: ['Birth Certificate', 'Marriage Certificate', 'Passport', 'Valid ID'],
    educational: ['Diploma', 'Transcript of Records', 'Certificate', 'Training Certificate'],
    employment: ['Employment Contract', 'Job Offer Letter', 'Appointment Letter'],
    medical: ['Medical Certificate', 'Pre-Employment Medical', 'Annual Physical Exam', 'Fit to Work'],
    contracts: ['Employment Contract', 'NDA', 'Non-Compete Agreement', 'Service Agreement'],
    benefits: ['PhilHealth Card', 'SSS ID', 'Pag-IBIG ID', 'TIN ID'],
    performance: ['Performance Appraisal', 'KPI Report', 'Performance Review'],
    separation: ['Resignation Letter', 'Clearance Form', 'Certificate of Employment'],
    government: ['NBI Clearance', 'Police Clearance', 'Barangay Clearance', 'BIR Form 2316'],
    special: ['Authorization Letter', 'Special Permit', 'Other Documents'],
};

const ACCEPTED_FILE_TYPES = '.pdf,.jpg,.jpeg,.png,.docx';
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

function getFileIcon(file: File) {
    const ext = file.name.split('.').pop()?.toLowerCase();
    
    if (ext === 'pdf') {
        return <FileText className="h-8 w-8 text-red-500" />;
    } else if (['jpg', 'jpeg', 'png'].includes(ext || '')) {
        return <ImageIcon className="h-8 w-8 text-blue-500" />;
    } else {
        return <File className="h-8 w-8 text-gray-500" />;
    }
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

function validateFile(file: File): string | null {
    // Check file size
    if (file.size > MAX_FILE_SIZE) {
        return `File size must be less than ${formatFileSize(MAX_FILE_SIZE)}`;
    }

    // Check file type
    const ext = file.name.split('.').pop()?.toLowerCase();
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
    
    if (!ext || !allowedExtensions.includes(ext)) {
        return `File type must be one of: ${allowedExtensions.join(', ').toUpperCase()}`;
    }

    return null;
}

// ============================================================================
// Component
// ============================================================================

export function DocumentUploadModal({ open, onClose, employees = [] }: DocumentUploadModalProps) {
    // State
    const [formData, setFormData] = useState<FormData>({
        employee_id: '',
        category: '',
        document_type: '',
        file: null,
        expires_at: undefined,
        notes: '',
    });
    const [errors, setErrors] = useState<FormErrors>({});
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [employeeSearchOpen, setEmployeeSearchOpen] = useState(false);
    const [calendarOpen, setCalendarOpen] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Mock employees if not provided
    const employeeList = employees.length > 0 ? employees : [
        { id: 1, employee_number: 'EMP-2024-001', name: 'Juan dela Cruz', department: 'IT Department' },
        { id: 2, employee_number: 'EMP-2024-002', name: 'Maria Santos', department: 'HR Department' },
        { id: 3, employee_number: 'EMP-2024-003', name: 'Pedro Reyes', department: 'Finance Department' },
    ];

    // Get selected employee details
    const selectedEmployee = employeeList.find(emp => emp.id.toString() === formData.employee_id);

    // Get document type suggestions based on category
    const documentTypeSuggestions = formData.category
        ? DOCUMENT_TYPE_SUGGESTIONS[formData.category] || []
        : [];

    // Handle field changes
    const handleChange = (field: keyof FormData, value: string | File | Date | undefined) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear error for this field
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: undefined }));
        }
    };

    // Handle file selection
    const handleFileSelect = (file: File) => {
        const error = validateFile(file);
        if (error) {
            setErrors(prev => ({ ...prev, file: error }));
            return;
        }

        setFormData(prev => ({ ...prev, file }));
        setErrors(prev => ({ ...prev, file: undefined }));
    };

    // Handle file input change
    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            handleFileSelect(file);
        }
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

        const file = e.dataTransfer.files[0];
        if (file) {
            handleFileSelect(file);
        }
    };

    // Handle form validation
    const validateForm = (): boolean => {
        const newErrors: FormErrors = {};

        if (!formData.employee_id) {
            newErrors.employee_id = 'Please select an employee';
        }
        if (!formData.category) {
            newErrors.category = 'Please select a category';
        }
        if (!formData.document_type) {
            newErrors.document_type = 'Please enter document type';
        }
        if (!formData.file) {
            newErrors.file = 'Please select a file to upload';
        }
        if (formData.notes && formData.notes.length > 500) {
            newErrors.notes = 'Notes must be less than 500 characters';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Handle form submit
    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsUploading(true);
        setUploadProgress(0);

        try {
            // Create FormData for file upload
            const submitData = new FormData();
            submitData.append('employee_id', formData.employee_id);
            submitData.append('document_category', formData.category);
            submitData.append('document_type', formData.document_type);
            if (formData.file) {
                submitData.append('file', formData.file);
            }
            if (formData.expires_at) {
                submitData.append('expires_at', format(formData.expires_at, 'yyyy-MM-dd'));
            }
            if (formData.notes) {
                submitData.append('notes', formData.notes);
            }

            // Simulate upload progress
            const progressInterval = setInterval(() => {
                setUploadProgress(prev => {
                    if (prev >= 90) {
                        clearInterval(progressInterval);
                        return prev;
                    }
                    return prev + 10;
                });
            }, 200);

            // Make actual API call to upload document
            const response = await fetch('/hr/documents', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: submitData,
            });

            clearInterval(progressInterval);

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to upload document');
            }

            setUploadProgress(100);
            
            // Success - close modal after short delay
            setTimeout(() => {
                handleClose();
                // Show success message
                console.log('Document uploaded successfully');
                window.location.reload(); // Reload to show new document
            }, 500);

        } catch (error) {
            console.error('Upload error:', error);
            setErrors({
                file: error instanceof Error ? error.message : 'Failed to upload document',
            });
            setIsUploading(false);
            setUploadProgress(0);
        }
    };

    // Handle close
    const handleClose = () => {
        if (isUploading) return;

        // Reset form
        setFormData({
            employee_id: '',
            category: '',
            document_type: '',
            file: null,
            expires_at: undefined,
            notes: '',
        });
        setErrors({});
        setUploadProgress(0);
        setIsUploading(false);
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Upload Document</DialogTitle>
                    <DialogDescription>
                        Upload a new document for an employee. All fields marked with * are required.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Employee Selector */}
                    <div className="space-y-2">
                        <Label htmlFor="employee">Employee *</Label>
                        <Popover open={employeeSearchOpen} onOpenChange={setEmployeeSearchOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={employeeSearchOpen}
                                    className="w-full justify-between"
                                >
                                    {selectedEmployee ? (
                                        <span className="truncate">
                                            {selectedEmployee.employee_number} - {selectedEmployee.name} ({selectedEmployee.department})
                                        </span>
                                    ) : (
                                        'Select employee...'
                                    )}
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-[500px] p-0">
                                <Command>
                                    <CommandInput placeholder="Search employee by number, name, or department..." />
                                    <CommandEmpty>No employee found.</CommandEmpty>
                                    <CommandGroup className="max-h-64 overflow-auto">
                                        {employeeList.map((employee) => (
                                            <CommandItem
                                                key={employee.id}
                                                value={`${employee.employee_number} ${employee.name} ${employee.department}`}
                                                onSelect={() => {
                                                    handleChange('employee_id', employee.id.toString());
                                                    setEmployeeSearchOpen(false);
                                                }}
                                            >
                                                <Check
                                                    className={cn(
                                                        'mr-2 h-4 w-4',
                                                        formData.employee_id === employee.id.toString()
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    )}
                                                />
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {employee.employee_number} - {employee.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {employee.department}
                                                    </span>
                                                </div>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        {errors.employee_id && (
                            <p className="text-sm text-red-500">{errors.employee_id}</p>
                        )}
                    </div>

                    {/* Category Selector */}
                    <div className="space-y-2">
                        <Label htmlFor="category">Document Category *</Label>
                        <Select
                            value={formData.category}
                            onValueChange={(value) => {
                                handleChange('category', value);
                                // Reset document type when category changes
                                setFormData(prev => ({ ...prev, document_type: '' }));
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select category" />
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
                            <p className="text-sm text-red-500">{errors.category}</p>
                        )}
                    </div>

                    {/* Document Type Input */}
                    <div className="space-y-2">
                        <Label htmlFor="document_type">Document Type *</Label>
                        <Input
                            id="document_type"
                            value={formData.document_type}
                            onChange={(e) => handleChange('document_type', e.target.value)}
                            placeholder="e.g., Birth Certificate, NBI Clearance"
                            list="document-type-suggestions"
                        />
                        {documentTypeSuggestions.length > 0 && (
                            <datalist id="document-type-suggestions">
                                {documentTypeSuggestions.map((suggestion) => (
                                    <option key={suggestion} value={suggestion} />
                                ))}
                            </datalist>
                        )}
                        {formData.category && documentTypeSuggestions.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                Suggestions: {documentTypeSuggestions.slice(0, 3).join(', ')}
                            </p>
                        )}
                        {errors.document_type && (
                            <p className="text-sm text-red-500">{errors.document_type}</p>
                        )}
                    </div>

                    {/* File Upload */}
                    <div className="space-y-2">
                        <Label htmlFor="file">File Upload *</Label>
                        <div
                            className={cn(
                                'border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors',
                                isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25',
                                formData.file ? 'bg-muted/50' : ''
                            )}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={handleDrop}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            {formData.file ? (
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {getFileIcon(formData.file)}
                                        <div className="text-left">
                                            <p className="font-medium">{formData.file.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatFileSize(formData.file.size)}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setFormData(prev => ({ ...prev, file: null }));
                                        }}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center gap-2">
                                    <Upload className="h-10 w-10 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">
                                            Drag and drop your file here, or click to browse
                                        </p>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            Supported formats: PDF, JPG, PNG, DOCX (Max 10MB)
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept={ACCEPTED_FILE_TYPES}
                            onChange={handleFileInputChange}
                            className="hidden"
                        />
                        {errors.file && (
                            <p className="text-sm text-red-500">{errors.file}</p>
                        )}
                    </div>

                    {/* Expiry Date Picker */}
                    <div className="space-y-2">
                        <Label htmlFor="expires_at">Expiry Date (Optional)</Label>
                        <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    className={cn(
                                        'w-full justify-start text-left font-normal',
                                        !formData.expires_at && 'text-muted-foreground'
                                    )}
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    {formData.expires_at ? (
                                        format(formData.expires_at, 'PPP')
                                    ) : (
                                        <span>Select expiry date</span>
                                    )}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                    mode="single"
                                    selected={formData.expires_at}
                                    onSelect={(date) => {
                                        handleChange('expires_at', date);
                                        setCalendarOpen(false);
                                    }}
                                    disabled={(date) => date < new Date()}
                                    initialFocus
                                />
                            </PopoverContent>
                        </Popover>
                        <p className="text-xs text-muted-foreground">
                            Leave empty if document doesn't expire
                        </p>
                    </div>

                    {/* Notes Textarea */}
                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes (Optional)</Label>
                        <Textarea
                            id="notes"
                            value={formData.notes}
                            onChange={(e) => handleChange('notes', e.target.value)}
                            placeholder="Add any additional notes about this document..."
                            rows={3}
                            maxLength={500}
                        />
                        <p className="text-xs text-muted-foreground text-right">
                            {formData.notes.length}/500 characters
                        </p>
                        {errors.notes && (
                            <p className="text-sm text-red-500">{errors.notes}</p>
                        )}
                    </div>

                    {/* Upload Progress */}
                    {isUploading && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span>Uploading...</span>
                                <span>{uploadProgress}%</span>
                            </div>
                            <Progress value={uploadProgress} />
                        </div>
                    )}

                    {/* Footer */}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isUploading}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isUploading}>
                            {isUploading ? (
                                <>
                                    <Upload className="mr-2 h-4 w-4 animate-pulse" />
                                    Uploading...
                                </>
                            ) : (
                                <>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Upload Document
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
