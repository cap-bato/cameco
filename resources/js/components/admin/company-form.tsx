import { useState, useRef } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { 
    Building2, 
    FileText, 
    Landmark, 
    Upload, 
    X, 
    Save,
    AlertCircle 
} from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

export interface CompanyFormData {
    // Basic Information
    name: string;
    address: string;
    city: string;
    province: string;
    postal_code: string;
    phone: string;
    email: string;
    website: string;
    
    // Tax & Registration
    tin: string;
    bir_registration_number: string;
    bir_registration_date: string;
    business_permit_number: string;
    sec_registration_number: string;
    
    // Government Numbers
    sss_number: string;
    philhealth_number: string;
    pagibig_number: string;
}

interface CompanyFormProps {
    initialData: CompanyFormData & { logo_url?: string | null };
    onSubmit: (data: CompanyFormData) => void;
    onLogoUpload: (file: File) => void;
    onLogoDelete: () => void;
    isSubmitting?: boolean;
}

interface ValidationErrors {
    [key: string]: string;
}

export function CompanyForm({ 
    initialData, 
    onSubmit, 
    onLogoUpload, 
    onLogoDelete,
    isSubmitting = false 
}: CompanyFormProps) {
    const [formData, setFormData] = useState<CompanyFormData>({
        name: initialData.name || '',
        address: initialData.address || '',
        city: initialData.city || '',
        province: initialData.province || '',
        postal_code: initialData.postal_code || '',
        phone: initialData.phone || '',
        email: initialData.email || '',
        website: initialData.website || '',
        tin: initialData.tin || '',
        bir_registration_number: initialData.bir_registration_number || '',
        bir_registration_date: initialData.bir_registration_date || '',
        business_permit_number: initialData.business_permit_number || '',
        sec_registration_number: initialData.sec_registration_number || '',
        sss_number: initialData.sss_number || '',
        philhealth_number: initialData.philhealth_number || '',
        pagibig_number: initialData.pagibig_number || '',
    });

    const [logoPreview, setLogoPreview] = useState<string | null>(
        initialData.logo_url ? `/storage/${initialData.logo_url}` : null
    );
    const [selectedLogoFile, setSelectedLogoFile] = useState<File | null>(null);
    const [errors, setErrors] = useState<ValidationErrors>({});
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Validation schema
    const validateForm = (): boolean => {
        const newErrors: ValidationErrors = {};

        // Basic Information - Required fields
        if (!formData.name.trim()) {
            newErrors.name = 'Company name is required';
        }
        if (!formData.address.trim()) {
            newErrors.address = 'Company address is required';
        }
        if (!formData.phone.trim()) {
            newErrors.phone = 'Phone number is required';
        }
        if (!formData.email.trim()) {
            newErrors.email = 'Email is required';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            newErrors.email = 'Invalid email format';
        }

        // Tax & Registration - Required fields
        if (!formData.tin.trim()) {
            newErrors.tin = 'TIN is required';
        }

        // Government Numbers - Required fields
        if (!formData.sss_number.trim()) {
            newErrors.sss_number = 'SSS employer number is required';
        }
        if (!formData.philhealth_number.trim()) {
            newErrors.philhealth_number = 'PhilHealth employer number is required';
        }
        if (!formData.pagibig_number.trim()) {
            newErrors.pagibig_number = 'Pag-IBIG employer number is required';
        }

        // URL validation for website
        if (formData.website && !/^https?:\/\/.+/.test(formData.website)) {
            newErrors.website = 'Website must be a valid URL (http:// or https://)';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleInputChange = (field: keyof CompanyFormData, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear error for this field
        if (errors[field]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            // Validate file type
            if (!file.type.startsWith('image/')) {
                setErrors(prev => ({ ...prev, logo: 'Please select an image file' }));
                return;
            }

            // Validate file size (2MB max)
            if (file.size > 2 * 1024 * 1024) {
                setErrors(prev => ({ ...prev, logo: 'Logo file size must be less than 2MB' }));
                return;
            }

            setSelectedLogoFile(file);
            
            // Create preview
            const reader = new FileReader();
            reader.onloadend = () => {
                setLogoPreview(reader.result as string);
            };
            reader.readAsDataURL(file);

            // Clear logo error
            if (errors.logo) {
                setErrors(prev => {
                    const newErrors = { ...prev };
                    delete newErrors.logo;
                    return newErrors;
                });
            }
        }
    };

    const handleUploadLogo = () => {
        if (selectedLogoFile) {
            onLogoUpload(selectedLogoFile);
            setSelectedLogoFile(null);
        }
    };

    const handleDeleteLogo = () => {
        onLogoDelete();
        setLogoPreview(null);
        setSelectedLogoFile(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (validateForm()) {
            onSubmit(formData);
        } else {
            // Scroll to first error
            const firstErrorField = Object.keys(errors)[0];
            const element = document.getElementById(firstErrorField);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Basic Information Section */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Building2 className="h-5 w-5" />
                        Basic Information
                    </CardTitle>
                    <CardDescription>
                        Company legal name, address, and contact information
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="name">
                                Company Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={formData.name}
                                onChange={(e) => handleInputChange('name', e.target.value)}
                                placeholder="Cathay Metal Corporation"
                                className={errors.name ? 'border-destructive' : ''}
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">{errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="address">
                                Street Address <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id="address"
                                value={formData.address}
                                onChange={(e) => handleInputChange('address', e.target.value)}
                                placeholder="123 Business Street, Building Name"
                                rows={2}
                                className={errors.address ? 'border-destructive' : ''}
                            />
                            {errors.address && (
                                <p className="text-sm text-destructive">{errors.address}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="city">City</Label>
                            <Input
                                id="city"
                                value={formData.city}
                                onChange={(e) => handleInputChange('city', e.target.value)}
                                placeholder="Quezon City"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="province">Province</Label>
                            <Input
                                id="province"
                                value={formData.province}
                                onChange={(e) => handleInputChange('province', e.target.value)}
                                placeholder="Metro Manila"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="postal_code">Postal Code</Label>
                            <Input
                                id="postal_code"
                                value={formData.postal_code}
                                onChange={(e) => handleInputChange('postal_code', e.target.value)}
                                placeholder="1100"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">
                                Phone Number <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="phone"
                                value={formData.phone}
                                onChange={(e) => handleInputChange('phone', e.target.value)}
                                placeholder="+63 2 1234 5678"
                                className={errors.phone ? 'border-destructive' : ''}
                            />
                            {errors.phone && (
                                <p className="text-sm text-destructive">{errors.phone}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="email">
                                Email Address <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                value={formData.email}
                                onChange={(e) => handleInputChange('email', e.target.value)}
                                placeholder="info@company.com"
                                className={errors.email ? 'border-destructive' : ''}
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">{errors.email}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="website">Website (Optional)</Label>
                            <Input
                                id="website"
                                value={formData.website}
                                onChange={(e) => handleInputChange('website', e.target.value)}
                                placeholder="https://www.company.com"
                                className={errors.website ? 'border-destructive' : ''}
                            />
                            {errors.website && (
                                <p className="text-sm text-destructive">{errors.website}</p>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Tax & Registration Section */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Tax & Registration Details
                    </CardTitle>
                    <CardDescription>
                        BIR registration, TIN, and business permit information
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="tin">
                                Tax Identification Number (TIN) <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="tin"
                                value={formData.tin}
                                onChange={(e) => handleInputChange('tin', e.target.value)}
                                placeholder="123-456-789-000"
                                className={errors.tin ? 'border-destructive' : ''}
                            />
                            {errors.tin && (
                                <p className="text-sm text-destructive">{errors.tin}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="bir_registration_number">BIR Registration Number</Label>
                            <Input
                                id="bir_registration_number"
                                value={formData.bir_registration_number}
                                onChange={(e) => handleInputChange('bir_registration_number', e.target.value)}
                                placeholder="BIR-123456"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="bir_registration_date">BIR Registration Date</Label>
                            <Input
                                id="bir_registration_date"
                                type="date"
                                value={formData.bir_registration_date}
                                onChange={(e) => handleInputChange('bir_registration_date', e.target.value)}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="business_permit_number">Business Permit Number</Label>
                            <Input
                                id="business_permit_number"
                                value={formData.business_permit_number}
                                onChange={(e) => handleInputChange('business_permit_number', e.target.value)}
                                placeholder="BP-2024-12345"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sec_registration_number">SEC Registration Number</Label>
                            <Input
                                id="sec_registration_number"
                                value={formData.sec_registration_number}
                                onChange={(e) => handleInputChange('sec_registration_number', e.target.value)}
                                placeholder="CS200012345"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Government Numbers Section */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Landmark className="h-5 w-5" />
                        Government Registration Numbers
                    </CardTitle>
                    <CardDescription>
                        SSS, PhilHealth, and Pag-IBIG employer registration numbers
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="sss_number">
                                SSS Employer Number <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="sss_number"
                                value={formData.sss_number}
                                onChange={(e) => handleInputChange('sss_number', e.target.value)}
                                placeholder="03-1234567-8"
                                className={errors.sss_number ? 'border-destructive' : ''}
                            />
                            {errors.sss_number && (
                                <p className="text-sm text-destructive">{errors.sss_number}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="philhealth_number">
                                PhilHealth Employer Number <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="philhealth_number"
                                value={formData.philhealth_number}
                                onChange={(e) => handleInputChange('philhealth_number', e.target.value)}
                                placeholder="12-345678901-2"
                                className={errors.philhealth_number ? 'border-destructive' : ''}
                            />
                            {errors.philhealth_number && (
                                <p className="text-sm text-destructive">{errors.philhealth_number}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="pagibig_number">
                                Pag-IBIG Employer Number <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="pagibig_number"
                                value={formData.pagibig_number}
                                onChange={(e) => handleInputChange('pagibig_number', e.target.value)}
                                placeholder="1234567890"
                                className={errors.pagibig_number ? 'border-destructive' : ''}
                            />
                            {errors.pagibig_number && (
                                <p className="text-sm text-destructive">{errors.pagibig_number}</p>
                            )}
                        </div>
                    </div>

                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription className="text-sm">
                            These government registration numbers are required for payroll processing and government remittances.
                            Ensure all numbers are accurate and up-to-date.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Company Logo Section */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Upload className="h-5 w-5" />
                        Company Logo
                    </CardTitle>
                    <CardDescription>
                        Upload your company logo (max 2MB, JPG, PNG, SVG)
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start">
                        {/* Logo Preview */}
                        <div className="flex-shrink-0">
                            {logoPreview ? (
                                <div className="relative h-32 w-32 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                                    <img
                                        src={logoPreview}
                                        alt="Company Logo"
                                        className="h-full w-full object-contain p-2"
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="absolute -right-2 -top-2 h-6 w-6 rounded-full p-0"
                                        onClick={handleDeleteLogo}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex h-32 w-32 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                                    <Upload className="h-8 w-8 text-gray-400" />
                                </div>
                            )}
                        </div>

                        {/* Upload Controls */}
                        <div className="flex-1 space-y-3">
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/*"
                                onChange={handleLogoChange}
                                className="hidden"
                            />
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => fileInputRef.current?.click()}
                                >
                                    <Upload className="mr-2 h-4 w-4" />
                                    Choose File
                                </Button>
                                {selectedLogoFile && (
                                    <Button
                                        type="button"
                                        variant="default"
                                        size="sm"
                                        onClick={handleUploadLogo}
                                    >
                                        Upload Logo
                                    </Button>
                                )}
                            </div>
                            {selectedLogoFile && (
                                <p className="text-sm text-muted-foreground">
                                    Selected: {selectedLogoFile.name}
                                </p>
                            )}
                            {errors.logo && (
                                <p className="text-sm text-destructive">{errors.logo}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Recommended dimensions: 200x200px. Accepted formats: JPG, PNG, SVG. Max file size: 2MB.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Submit Button */}
            <div className="flex justify-end gap-3">
                <Button
                    type="submit"
                    size="lg"
                    disabled={isSubmitting}
                >
                    {isSubmitting ? (
                        <>Saving...</>
                    ) : (
                        <>
                            <Save className="mr-2 h-4 w-4" />
                            Save Company Information
                        </>
                    )}
                </Button>
            </div>

            {/* Validation Summary */}
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Please fix the following errors before submitting:
                        <ul className="list-disc list-inside mt-2 space-y-1">
                            {Object.entries(errors).map(([field, error]) => (
                                <li key={field} className="text-sm">{error}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}
        </form>
    );
}
