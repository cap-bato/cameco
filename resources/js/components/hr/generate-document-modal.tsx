import { useState, FormEvent } from 'react';
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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
} from '@/components/ui/command';
import { Calendar } from '@/components/ui/calendar';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useToast } from '@/hooks/use-toast';
import { format } from 'date-fns';
import {
    FileText,
    Building,
    Check,
    ChevronDown,
    CalendarIcon,
    Download,
    Mail,
    AlertCircle,
    Loader2,
} from 'lucide-react';
import axios from 'axios';

// ============================================================================
// Type Definitions
// ============================================================================

interface Variable {
    name: string;
    label: string;
    type: 'text' | 'date' | 'number' | 'select';
    required: boolean;
    default_value?: string;
    options?: string[];
}

interface Template {
    id: number;
    name: string;
    category: string;
    description: string;
    version: string;
    variables: Variable[];
    usage_count: number;
    last_modified: string;
    modified_by: string;
}

interface Employee {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    department: string;
}

interface GenerateDocumentModalProps {
    open: boolean;
    onClose: () => void;
    template: Template;
    employees?: Employee[];
}

interface FormErrors {
    employee_id?: string;
    variables?: Record<string, string>;
    email_subject?: string;
    general?: string;
}

// ============================================================================
// Main Component
// ============================================================================

export function GenerateDocumentModal({ open, onClose, template, employees = [] }: GenerateDocumentModalProps) {
    const { toast } = useToast();

    // Form state
    const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(null);
    const [variableValues, setVariableValues] = useState<Record<string, unknown>>(() => {
        // Initialize with default values
        const defaults: Record<string, unknown> = {};
        template.variables.forEach(v => {
            defaults[v.name] = v.default_value || '';
        });
        return defaults;
    });
    const [outputFormat, setOutputFormat] = useState<'pdf' | 'docx'>('pdf');
    const [sendEmail, setSendEmail] = useState(false);
    const [emailSubject, setEmailSubject] = useState(`Document: ${template.name}`);
    const [emailMessage, setEmailMessage] = useState(
        `Dear Employee,\n\nPlease find attached your ${template.name}.\n\nIf you have any questions, please contact the HR department.\n\nBest regards,\nHR Department`
    );
    
    // UI state
    const [isEmployeePopoverOpen, setIsEmployeePopoverOpen] = useState(false);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<FormErrors>({});
    const [showEmailPreview, setShowEmailPreview] = useState(false);

    // Filter employees by search
    const filteredEmployees = employees.filter(emp => {
        const search = employeeSearch.toLowerCase();
        return (
            emp.employee_number.toLowerCase().includes(search) ||
            `${emp.first_name} ${emp.last_name}`.toLowerCase().includes(search) ||
            emp.department.toLowerCase().includes(search)
        );
    });

    // Get category badge color
    const getCategoryBadgeColor = (category: string): string => {
        const colors: Record<string, string> = {
            personal: 'bg-blue-100 text-blue-800',
            educational: 'bg-purple-100 text-purple-800',
            employment: 'bg-cyan-100 text-cyan-800',
            medical: 'bg-red-100 text-red-800',
            contracts: 'bg-indigo-100 text-indigo-800',
            benefits: 'bg-green-100 text-green-800',
            performance: 'bg-yellow-100 text-yellow-800',
            separation: 'bg-orange-100 text-orange-800',
            government: 'bg-teal-100 text-teal-800',
            special: 'bg-pink-100 text-pink-800',
            payroll: 'bg-emerald-100 text-emerald-800',
            communication: 'bg-violet-100 text-violet-800',
        };
        return colors[category] || 'bg-gray-100 text-gray-800';
    };

    // Handle employee selection
    const handleSelectEmployee = (employee: Employee) => {
        setSelectedEmployee(employee);
        setIsEmployeePopoverOpen(false);
        setErrors(prev => ({ ...prev, employee_id: undefined }));
    };

    // Handle variable value change
    const handleVariableChange = (variableName: string, value: unknown) => {
        setVariableValues(prev => ({
            ...prev,
            [variableName]: value,
        }));
        if (errors.variables) {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const { [variableName]: _removed, ...rest } = errors.variables;
            setErrors(prev => ({
                ...prev,
                variables: rest,
            }));
        }
    };

    // Validate form
    const validateForm = (): boolean => {
        const newErrors: FormErrors = {
            variables: {},
        };

        // Validate employee selection
        if (!selectedEmployee) {
            newErrors.employee_id = 'Please select an employee';
        }

        // Validate required variables
        template.variables.forEach(variable => {
            if (variable.required && !variableValues[variable.name]) {
                newErrors.variables![variable.name] = `${variable.label} is required`;
            }
        });

        // Validate email fields if sending email
        if (sendEmail) {
            if (!emailSubject.trim()) {
                newErrors.email_subject = 'Email subject is required';
            }
        }

        setErrors(newErrors);
        const hasVariableErrors = Object.keys(newErrors.variables || {}).length > 0;
        return !newErrors.employee_id && !newErrors.email_subject && !hasVariableErrors;
    };

    // Handle form submission
    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            toast({
                title: 'Validation Error',
                description: 'Please fill in all required fields',
                variant: 'destructive',
            });
            return;
        }

        setIsSubmitting(true);

        try {
            const response = await axios.post(
                '/hr/documents/api/templates/generate',
                {
                    template_id: template.id,
                    employee_id: selectedEmployee!.id,
                    variables: variableValues,
                    output_format: outputFormat,
                    send_email: sendEmail,
                    email_subject: sendEmail ? emailSubject : undefined,
                    email_message: sendEmail ? emailMessage : undefined,
                },
                {
                    responseType: 'blob',
                }
            );

            // Download generated file
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = `${template.name}_${selectedEmployee!.employee_number}.${outputFormat}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            toast({
                title: 'Document Generated',
                description: `${template.name} has been generated successfully`,
            });

            if (sendEmail) {
                toast({
                    title: 'Email Sent',
                    description: `Document sent to ${selectedEmployee!.first_name} ${selectedEmployee!.last_name}`,
                });
            }

            handleClose();
        } catch (error: unknown) {
            console.error('Generation error:', error);
            
            let errorMessage = 'Failed to generate document';
            if (error && typeof error === 'object' && 'response' in error) {
                const axiosError = error as { response?: { data?: Blob | { message?: string } } };
                if (axiosError.response?.data) {
                    // Try to parse error if it's not a blob
                    if (axiosError.response.data instanceof Blob) {
                        const text = await axiosError.response.data.text();
                        try {
                            const errorData = JSON.parse(text);
                            errorMessage = errorData.message || errorMessage;
                        } catch {
                            // If parsing fails, use default message
                        }
                    } else if (typeof axiosError.response.data === 'object' && 'message' in axiosError.response.data) {
                        errorMessage = axiosError.response.data.message || errorMessage;
                    }
                }
            }

            toast({
                title: 'Generation Failed',
                description: errorMessage,
                variant: 'destructive',
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Handle close
    const handleClose = () => {
        if (!isSubmitting) {
            setSelectedEmployee(null);
            setVariableValues(() => {
                const defaults: Record<string, unknown> = {};
                template.variables.forEach(v => {
                    defaults[v.name] = v.default_value || '';
                });
                return defaults;
            });
            setOutputFormat('pdf');
            setSendEmail(false);
            setEmailSubject(`Document: ${template.name}`);
            setEmailMessage(
                `Dear Employee,\n\nPlease find attached your ${template.name}.\n\nIf you have any questions, please contact the HR department.\n\nBest regards,\nHR Department`
            );
            setErrors({});
            setShowEmailPreview(false);
            onClose();
        }
    };

    // Render variable input based on type
    const renderVariableInput = (variable: Variable) => {
        const value = variableValues[variable.name] || '';
        const valueStr = typeof value === 'string' ? value : String(value);  
        const error = errors.variables?.[variable.name];

        switch (variable.type) {
            case 'date':
                return (
                    <div key={variable.name} className="space-y-2">
                        <Label className={variable.required ? 'required' : ''}>
                            {variable.label}
                        </Label>
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    className={`w-full justify-start text-left font-normal ${error ? 'border-red-500' : ''} ${!value && 'text-muted-foreground'}`}
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    {valueStr ? format(new Date(valueStr), 'PPP') : 'Pick a date'}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                    mode="single"
                                    selected={valueStr ? new Date(valueStr) : undefined}
                                    onSelect={(date) => handleVariableChange(variable.name, date?.toISOString())}
                                    initialFocus
                                />
                            </PopoverContent>
                        </Popover>
                        {error && <p className="text-xs text-red-500">{error}</p>}
                    </div>
                );

            case 'number':
                return (
                    <div key={variable.name} className="space-y-2">
                        <Label className={variable.required ? 'required' : ''}>
                            {variable.label}
                        </Label>
                        <Input
                            type="number"
                            value={valueStr}
                            onChange={(e) => handleVariableChange(variable.name, e.target.value)}
                            placeholder={variable.default_value || 'Enter number'}
                            className={error ? 'border-red-500' : ''}
                        />
                        {error && <p className="text-xs text-red-500">{error}</p>}
                    </div>
                );

            case 'select': {
                const options = variable.options || [];
                return (
                    <div key={variable.name} className="space-y-2">
                        <Label className={variable.required ? 'required' : ''}>
                            {variable.label}
                        </Label>
                        <Select value={valueStr} onValueChange={(val) => handleVariableChange(variable.name, val)}>
                            <SelectTrigger className={error ? 'border-red-500' : ''}>
                                <SelectValue placeholder="Select option" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {error && <p className="text-xs text-red-500">{error}</p>}
                    </div>
                );
            }

            case 'text':
            default:
                return (
                    <div key={variable.name} className="space-y-2">
                        <Label className={variable.required ? 'required' : ''}>
                            {variable.label}
                        </Label>
                        <Input
                            type="text"
                            value={valueStr}
                            onChange={(e) => handleVariableChange(variable.name, e.target.value)}
                            placeholder={variable.default_value || `Enter ${variable.label.toLowerCase()}`}
                            className={error ? 'border-red-500' : ''}
                        />
                        {error && <p className="text-xs text-red-500">{error}</p>}
                    </div>
                );
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Generate Document</DialogTitle>
                    <DialogDescription>
                        Fill in the required information to generate {template.name}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    {errors.general && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{errors.general}</AlertDescription>
                        </Alert>
                    )}

                    {/* Template Preview Section */}
                    <div className="mb-6 p-4 bg-gray-50 rounded-lg border">
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <div className="flex items-center gap-2 mb-2">
                                    <FileText className="h-5 w-5 text-gray-600" />
                                    <h3 className="font-semibold">{template.name}</h3>
                                    <Badge className={getCategoryBadgeColor(template.category)}>
                                        {template.category}
                                    </Badge>
                                </div>
                                {template.description && (
                                    <p className="text-sm text-gray-600 mb-2">{template.description}</p>
                                )}
                                <div className="flex items-center gap-4 text-xs text-gray-500">
                                    <span>Version {template.version}</span>
                                    <span>•</span>
                                    <span>{template.variables.length} variables</span>
                                    <span>•</span>
                                    <span>Last modified: {template.last_modified}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Employee Selector */}
                    <div className="space-y-2 mb-6">
                        <Label className="required">Select Employee</Label>
                        <Popover open={isEmployeePopoverOpen} onOpenChange={setIsEmployeePopoverOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    className={`w-full justify-between ${errors.employee_id ? 'border-red-500' : ''}`}
                                >
                                    {selectedEmployee ? (
                                        <div className="flex items-center gap-2">
                                            <Avatar className="h-6 w-6">
                                                <AvatarFallback className="text-xs">
                                                    {selectedEmployee.first_name[0]}{selectedEmployee.last_name[0]}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="truncate">
                                                {selectedEmployee.employee_number} - {selectedEmployee.first_name} {selectedEmployee.last_name}
                                            </span>
                                            <span className="text-xs text-gray-500">({selectedEmployee.department})</span>
                                        </div>
                                    ) : (
                                        <span className="text-gray-500">Search employee by number, name, or department...</span>
                                    )}
                                    <ChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-full p-0" align="start">
                                <Command>
                                    <CommandInput
                                        placeholder="Search employees..."
                                        value={employeeSearch}
                                        onValueChange={setEmployeeSearch}
                                    />
                                    <CommandEmpty>No employee found.</CommandEmpty>
                                    <CommandGroup className="max-h-64 overflow-auto">
                                        {filteredEmployees.map((employee) => (
                                            <CommandItem
                                                key={employee.id}
                                                value={`${employee.employee_number} ${employee.first_name} ${employee.last_name} ${employee.department}`}
                                                onSelect={() => handleSelectEmployee(employee)}
                                            >
                                                <div className="flex items-center gap-2 w-full">
                                                    <Avatar className="h-8 w-8">
                                                        <AvatarFallback className="text-xs">
                                                            {employee.first_name[0]}{employee.last_name[0]}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium truncate">
                                                                {employee.employee_number}
                                                            </span>
                                                            {selectedEmployee?.id === employee.id && (
                                                                <Check className="h-4 w-4 text-primary" />
                                                            )}
                                                        </div>
                                                        <div className="text-sm text-gray-600 truncate">
                                                            {employee.first_name} {employee.last_name}
                                                        </div>
                                                        <div className="text-xs text-gray-500 truncate">
                                                            <Building className="h-3 w-3 inline mr-1" />
                                                            {employee.department}
                                                        </div>
                                                    </div>
                                                </div>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        {errors.employee_id && (
                            <p className="text-xs text-red-500">{errors.employee_id}</p>
                        )}
                    </div>

                    {/* Dynamic Variable Inputs */}
                    {template.variables.length > 0 && (
                        <div className="space-y-4 mb-6">
                            <Label className="text-base font-semibold">Document Variables</Label>
                            <div className="grid grid-cols-1 gap-4">
                                {template.variables.map(renderVariableInput)}
                            </div>
                        </div>
                    )}

                    {/* Output Format */}
                    <div className="space-y-2 mb-6">
                        <Label>Output Format</Label>
                        <RadioGroup value={outputFormat} onValueChange={(value) => setOutputFormat(value as 'pdf' | 'docx')}>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="pdf" id="pdf" />
                                <Label htmlFor="pdf" className="font-normal cursor-pointer">
                                    <span className="font-medium">PDF</span> (Recommended for final documents)
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="docx" id="docx" />
                                <Label htmlFor="docx" className="font-normal cursor-pointer">
                                    <span className="font-medium">DOCX</span> (Editable document)
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Email Options */}
                    <div className="space-y-4 mb-6 border rounded-lg p-4 bg-gray-50">
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="send-email"
                                checked={sendEmail}
                                onCheckedChange={(checked) => setSendEmail(checked as boolean)}
                            />
                            <Label htmlFor="send-email" className="cursor-pointer">
                                <Mail className="inline h-4 w-4 mr-2" />
                                Send document via email
                            </Label>
                        </div>

                        {sendEmail && (
                            <div className="space-y-4 pl-6 border-l-2 border-primary">
                                <div className="space-y-2">
                                    <Label htmlFor="email-subject" className="required">
                                        Email Subject
                                    </Label>
                                    <Input
                                        id="email-subject"
                                        value={emailSubject}
                                        onChange={(e) => {
                                            setEmailSubject(e.target.value);
                                            setErrors(prev => ({ ...prev, email_subject: undefined }));
                                        }}
                                        placeholder="Document: Certificate of Employment"
                                        className={errors.email_subject ? 'border-red-500' : ''}
                                    />
                                    {errors.email_subject && (
                                        <p className="text-xs text-red-500">{errors.email_subject}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email-message">
                                        Email Message
                                    </Label>
                                    <Textarea
                                        id="email-message"
                                        value={emailMessage}
                                        onChange={(e) => setEmailMessage(e.target.value)}
                                        rows={4}
                                        placeholder="Enter email message..."
                                    />
                                    <p className="text-xs text-gray-500">
                                        The generated document will be attached to this email
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowEmailPreview(!showEmailPreview)}
                                >
                                    {showEmailPreview ? 'Hide' : 'Show'} Email Preview
                                </Button>

                                {showEmailPreview && (
                                    <div className="border rounded p-4 bg-white">
                                        <div className="text-xs text-gray-500 mb-2">EMAIL PREVIEW</div>
                                        <div className="space-y-2">
                                            <div>
                                                <span className="text-xs font-medium text-gray-700">To:</span>{' '}
                                                <span className="text-sm">
                                                    {selectedEmployee 
                                                        ? `${selectedEmployee.first_name} ${selectedEmployee.last_name}` 
                                                        : '(Select employee)'}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-xs font-medium text-gray-700">Subject:</span>{' '}
                                                <span className="text-sm">{emailSubject}</span>
                                            </div>
                                            <div className="border-t pt-2 mt-2">
                                                <pre className="text-sm whitespace-pre-wrap font-sans">{emailMessage}</pre>
                                            </div>
                                            <div className="border-t pt-2 mt-2">
                                                <span className="text-xs font-medium text-gray-700">Attachment:</span>{' '}
                                                <span className="text-sm text-gray-600">
                                                    {template.name}_{selectedEmployee?.employee_number || 'EMP'}.{outputFormat}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
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
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <Download className="mr-2 h-4 w-4" />
                                    Generate Document
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
