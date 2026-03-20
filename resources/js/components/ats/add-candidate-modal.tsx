import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { AlertCircle } from 'lucide-react';
import { FileUpload } from '@/components/ats/file-upload';
import type { CandidateSource } from '@/types/ats-pages';

interface AddCandidateModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (candidateData: CandidateFormData) => Promise<void>;
  isEditing?: boolean;
  initialData?: Partial<CandidateFormData>;
}

export interface CandidateFormData {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  source: CandidateSource;
  resume?: File | null;
}

const sourceOptions: { value: CandidateSource; label: string }[] = [
  { value: 'referral', label: 'Referral' },
  { value: 'job_board', label: 'Job Board' },
  { value: 'walk_in', label: 'Walk-in' },
  { value: 'agency', label: 'Agency' },
  { value: 'internal', label: 'Internal' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'other', label: 'Other' },
];

/**
 * Add/Edit Candidate Modal
 * Modal form for adding or editing a candidate in the system
 */
export function AddCandidateModal({
  isOpen,
  onClose,
  onSubmit,
  isEditing = false,
  initialData,
}: AddCandidateModalProps) {
  const [formData, setFormData] = useState<CandidateFormData>({
    first_name: initialData?.first_name || '',
    last_name: initialData?.last_name || '',
    email: initialData?.email || '',
    phone: initialData?.phone || '',
    source: initialData?.source || 'job_board',
    resume: initialData?.resume || null,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [resumeFileName, setResumeFileName] = useState<string>('');

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.first_name.trim()) {
      newErrors.first_name = 'First name is required';
    }
    if (!formData.last_name.trim()) {
      newErrors.last_name = 'Last name is required';
    }
    if (!formData.email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Invalid email format';
    }
    if (!formData.phone.trim()) {
      newErrors.phone = 'Phone number is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
    // Clear error for this field when user starts typing
    if (errors[name]) {
      setErrors((prev) => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };

  const handleSourceChange = (value: string) => {
    setFormData((prev) => ({
      ...prev,
      source: value as CandidateSource,
    }));
  };

  const handleFileChange = (file: File | null) => {
    if (file) {
      setResumeFileName(file.name);
      setFormData((prev) => ({
        ...prev,
        resume: file,
      }));
      // Clear error
      if (errors.resume) {
        setErrors((prev) => {
          const newErrors = { ...prev };
          delete newErrors.resume;
          return newErrors;
        });
      }
    } else {
      setResumeFileName('');
      setFormData((prev) => ({
        ...prev,
        resume: null,
      }));
    }
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);
    try {
      await onSubmit(formData);
      // Reset form on successful submission
      setFormData({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        source: 'job_board',
        resume: null,
      });
      setResumeFileName('');
      onClose();
    } catch (error: any) {
      // Extract field-specific errors from backend validation
      if (error.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.keys(error.response.data.errors).forEach((field) => {
          const fieldErrors = error.response.data.errors[field];
          backendErrors[field] = Array.isArray(fieldErrors) ? fieldErrors[0] : fieldErrors;
        });
        setErrors(backendErrors);
      } else if (error.response?.data?.message) {
        // Only show submit error for non-validation errors
        setErrors({
          submit: error.response.data.message,
        });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      // Reset form when modal closes
      setFormData({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        source: 'job_board',
        resume: null,
      });
      setResumeFileName('');
      setErrors({});
      onClose();
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{isEditing ? 'Edit Candidate' : 'Add New Candidate'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          {/* Submit Error */}
          {errors.submit && (
            <div className="flex items-center gap-2 bg-destructive/10 border border-destructive/30 rounded-md p-3">
              <AlertCircle className="h-4 w-4 text-destructive flex-shrink-0" />
              <p className="text-sm text-destructive">{errors.submit}</p>
            </div>
          )}

          {/* Name Row */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="first_name" className="text-sm font-medium">
                First Name *
              </Label>
              <Input
                id="first_name"
                name="first_name"
                placeholder="John"
                value={formData.first_name}
                onChange={handleInputChange}
                className={errors.first_name ? 'border-destructive' : ''}
                disabled={isSubmitting}
              />
              {errors.first_name && (
                <p className="text-xs text-destructive mt-1">{errors.first_name}</p>
              )}
            </div>
            <div>
              <Label htmlFor="last_name" className="text-sm font-medium">
                Last Name *
              </Label>
              <Input
                id="last_name"
                name="last_name"
                placeholder="Doe"
                value={formData.last_name}
                onChange={handleInputChange}
                className={errors.last_name ? 'border-destructive' : ''}
                disabled={isSubmitting}
              />
              {errors.last_name && (
                <p className="text-xs text-destructive mt-1">{errors.last_name}</p>
              )}
            </div>
          </div>

          {/* Email */}
          <div>
            <Label htmlFor="email" className="text-sm font-medium">
              Email *
            </Label>
            <Input
              id="email"
              name="email"
              type="email"
              placeholder="john.doe@example.com"
              value={formData.email}
              onChange={handleInputChange}
              className={errors.email ? 'border-destructive' : ''}
              disabled={isSubmitting}
            />
            {errors.email && (
              <p className="text-xs text-destructive mt-1">{errors.email}</p>
            )}
          </div>

          {/* Phone */}
          <div>
            <Label htmlFor="phone" className="text-sm font-medium">
              Phone Number *
            </Label>
            <Input
              id="phone"
              name="phone"
              type="tel"
              placeholder="+1 (555) 123-4567"
              value={formData.phone}
              onChange={handleInputChange}
              className={errors.phone ? 'border-destructive' : ''}
              disabled={isSubmitting}
            />
            {errors.phone && (
              <p className="text-xs text-destructive mt-1">{errors.phone}</p>
            )}
          </div>

          {/* Source */}
          <div>
            <Label htmlFor="source" className="text-sm font-medium">
              Source *
            </Label>
            <Select value={formData.source || ''} onValueChange={handleSourceChange} disabled={isSubmitting}>
              <SelectTrigger id="source">
                <SelectValue placeholder="Select source" />
              </SelectTrigger>
              <SelectContent>
                {sourceOptions.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Resume Upload */}
          <FileUpload
            label="Resume"
            hint="PDF, DOC, DOCX up to 5MB"
            accept=".pdf,.doc,.docx"
            maxSize={5 * 1024 * 1024}
            onFileSelect={handleFileChange}
            value={resumeFileName}
            error={errors.resume}
            disabled={isSubmitting}
          />

          {/* Submit Error Message */}
          {errors.submit && (
            <div className="flex items-start gap-2 p-3 rounded-md bg-destructive/10 border border-destructive/30">
              <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
              <p className="text-sm text-destructive">{errors.submit}</p>
            </div>
          )}
        </div>

        <DialogFooter className="gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => handleOpenChange(false)}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
          <Button
            type="button"
            onClick={handleSubmit}
            disabled={isSubmitting}
          >
            {isSubmitting ? (isEditing ? 'Saving...' : 'Adding...') : (isEditing ? 'Save Changes' : 'Add Candidate')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
