import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { JobPosting, JobPostingFormData } from '@/types/ats-pages';

interface JobPostingCreateEditModalProps {
  isOpen: boolean;
  isEditing?: boolean;
  jobPosting?: JobPosting;
  departments: Array<{ id: number; name: string }>;
  isLoading?: boolean;
  onClose: () => void;
  onSubmit: (data: JobPostingFormData) => void;
}

/**
 * Job Posting Create/Edit Modal Component
 * Allows creating new or editing existing job postings
 */
export function JobPostingCreateEditModal({
  isOpen,
  isEditing = false,
  jobPosting,
  departments,
  isLoading = false,
  onClose,
  onSubmit,
}: JobPostingCreateEditModalProps) {
  const getInitialFormData = (): JobPostingFormData & { closed_at: string } => {
    if (jobPosting) {
      return {
        title: jobPosting.title || '',
        department_id: jobPosting.department_id || 0,
        description: jobPosting.description || '',
        requirements: jobPosting.requirements || '',
        status: jobPosting.status || 'draft',
        auto_post_facebook: jobPosting.auto_post_facebook || false,
        closed_at: jobPosting.closed_at ? jobPosting.closed_at.split('T')[0] : '',
      };
    }
    return {
      title: '',
      department_id: 0,
      description: '',
      requirements: '',
      status: 'draft',
      auto_post_facebook: false,
      closed_at: '',
    };
  };

  const [formData, setFormData] = useState<JobPostingFormData & { closed_at: string }>(getInitialFormData());
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Reset form data and errors when modal opens
  useEffect(() => {
    if (isOpen) {
      setFormData(getInitialFormData());
      setErrors({});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, jobPosting]);

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.title.trim()) {
      newErrors.title = 'Job title is required';
    }

    if (formData.department_id === 0) {
      newErrors.department_id = 'Department is required';
    }

    if (!formData.description.trim()) {
      newErrors.description = 'Job description is required';
    }

    if (!formData.requirements.trim()) {
      newErrors.requirements = 'Requirements are required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    onSubmit(formData);
  };

  const handleClose = () => {
    setErrors({});
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {isEditing ? 'Edit Job Posting' : 'Create New Job Posting'}
          </DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Title */}
          <div className="space-y-2">
            <Label htmlFor="title">Job Title *</Label>
            <Input
              id="title"
              placeholder="e.g., Senior Software Engineer"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              disabled={isLoading}
              className={errors.title ? 'border-destructive' : ''}
            />
            {errors.title && (
              <p className="text-sm text-destructive">{errors.title}</p>
            )}
          </div>

          {/* Department */}
          <div className="space-y-2">
            <Label htmlFor="department_id">Department *</Label>
            <Select
              value={formData.department_id?.toString() || '0'}
              onValueChange={(value) =>
                setFormData({ ...formData, department_id: parseInt(value) })
              }
              disabled={isLoading}
            >
              <SelectTrigger
                id="department_id"
                className={errors.department_id ? 'border-destructive' : ''}
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="0">Select a department...</SelectItem>
                {departments.map((dept) => (
                  <SelectItem key={dept.id} value={dept.id.toString()}>
                    {dept.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.department_id && (
              <p className="text-sm text-destructive">{errors.department_id}</p>
            )}
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label htmlFor="description">Job Description *</Label>
            <Textarea
              id="description"
              placeholder="Describe the job role and responsibilities..."
              value={formData.description}
              onChange={(e) =>
                setFormData({ ...formData, description: e.target.value })
              }
              disabled={isLoading}
              className={`h-24 ${errors.description ? 'border-destructive' : ''}`}
            />
            {errors.description && (
              <p className="text-sm text-destructive">{errors.description}</p>
            )}
          </div>

          {/* Requirements */}
          <div className="space-y-2">
            <Label htmlFor="requirements">Requirements *</Label>
            <Textarea
              id="requirements"
              placeholder="List required skills, experience, education, etc..."
              value={formData.requirements}
              onChange={(e) =>
                setFormData({ ...formData, requirements: e.target.value })
              }
              disabled={isLoading}
              className={`h-24 ${errors.requirements ? 'border-destructive' : ''}`}
            />
            {errors.requirements && (
              <p className="text-sm text-destructive">{errors.requirements}</p>
            )}
          </div>

          {/* Status */}
          <div className="space-y-2">
            <Label htmlFor="status">Status *</Label>
            <Select
              value={formData.status}
              onValueChange={(value) =>
                setFormData({
                  ...formData,
                  status: value as 'draft' | 'open' | 'closed',
                })
              }
              disabled={isLoading}
            >
              <SelectTrigger id="status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="open">Open</SelectItem>
                <SelectItem value="closed">Closed</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Closing Date */}
          <div className="space-y-2">
            <Label htmlFor="closed_at">Closing Date (Optional)</Label>
            <Input
              id="closed_at"
              type="date"
              value={formData.closed_at}
              onChange={(e) => setFormData({ ...formData, closed_at: e.target.value })}
              disabled={isLoading}
            />
          </div>

          {/* Info Alert */}
          <Alert>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              {isEditing
                ? 'Changes will be saved immediately. You can publish or close this job posting from the main list.'
                : 'Created job postings will start as Draft. You can publish them to make them visible to candidates.'}
            </AlertDescription>
          </Alert>

          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isLoading}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isLoading}>
              {isLoading ? 'Saving...' : isEditing ? 'Update Job Posting' : 'Create Job Posting'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}