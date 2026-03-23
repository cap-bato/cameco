import React, { useState, useEffect } from 'react';

import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface SalaryComponent {
  id: number;
  code: string;
  name: string;
  component_type: 'allowance' | 'deduction' | 'tax' | 'contribution';
  category: string;
  default_amount: number | null;
  is_taxable: boolean;
  is_deminimis: boolean;
}

interface Employee {
  id: number;
  employee_number: string;
  first_name: string;
  last_name: string;
  department: string;
  position: string;
}

export interface EmployeeComponentAssignmentFormData {
  employee_id: number | null;
  salary_component_id: number | null;
  amount: string;
  percentage: string;
  units: string;
  frequency: 'per_payroll' | 'monthly' | 'quarterly' | 'semi_annual' | 'annually' | 'one_time';
  effective_date: string;
  end_date: string;
  is_prorated: boolean;
  requires_attendance: boolean;
}

interface EmployeeComponentAssignModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: EmployeeComponentAssignmentFormData) => Promise<void>;
  employees: Employee[];
  components: SalaryComponent[];
  initialData?: EmployeeComponentAssignmentFormData;
  isLoading?: boolean;
  mode?: 'create' | 'edit';
  submitError?: string | null;
}

const FREQUENCY_OPTIONS = [
  { value: 'per_payroll', label: 'Per Payroll' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'semi_annual', label: 'Semi-Annual' },
  { value: 'annually', label: 'Annually' },
  { value: 'one_time', label: 'One-Time' },
];

const DEFAULT_FORM: EmployeeComponentAssignmentFormData = {
  employee_id: null,
  salary_component_id: null,
  amount: '',
  percentage: '',
  units: '',
  frequency: 'per_payroll',
  effective_date: new Date().toISOString().split('T')[0],
  end_date: '',
  is_prorated: false,
  requires_attendance: true,
};

const getDefaultAmount = (component: SalaryComponent | undefined): string => {
  if (!component?.default_amount) return '';
  return component.default_amount.toString();
};

export const EmployeeComponentAssignModal: React.FC<EmployeeComponentAssignModalProps> = ({
  isOpen,
  onClose,
  onSubmit,
  employees,
  components,
  initialData,
  isLoading = false,
  mode = 'create',
  submitError,
}) => {
  const [formData, setFormData] = useState<EmployeeComponentAssignmentFormData>(
    initialData || DEFAULT_FORM
  );
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Sync form when initialData changes (e.g. opening in edit mode)
  useEffect(() => {
    setFormData(initialData || DEFAULT_FORM);
    setErrors({});
  }, [initialData, isOpen]);

  const selectedComponent = components.find((c) => c.id === formData.salary_component_id);
  const selectedEmployee = employees.find((e) => e.id === formData.employee_id);

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.employee_id) {
      newErrors.employee_id = 'Employee is required';
    }
    if (!formData.salary_component_id) {
      newErrors.salary_component_id = 'Component is required';
    }
    if (!formData.amount && !formData.percentage && !formData.units) {
      newErrors.amount = 'Please enter amount, percentage, or units';
    }
    if (formData.amount && isNaN(parseFloat(formData.amount))) {
      newErrors.amount = 'Amount must be a valid number';
    }
    if (
      formData.percentage &&
      (isNaN(parseFloat(formData.percentage)) || parseFloat(formData.percentage) > 100)
    ) {
      newErrors.percentage = 'Percentage must be between 0 and 100';
    }
    if (!formData.effective_date) {
      newErrors.effective_date = 'Effective date is required';
    }
    if (formData.end_date && formData.end_date <= formData.effective_date) {
      newErrors.end_date = 'End date must be after effective date';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Delegate to parent — parent owns submitError state
    await onSubmit(formData);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            {mode === 'create' ? 'Assign Component to Employee' : 'Edit Component Assignment'}
          </DialogTitle>
          <DialogDescription>
            Add or edit salary component assignments for employees
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {submitError && (
            <Alert variant="destructive">
              <AlertDescription>{submitError}</AlertDescription>
            </Alert>
          )}

          <div className="grid grid-cols-2 gap-4">
            {/* Employee Selection */}
            <div>
              <Label htmlFor="employee">Employee *</Label>
              <Select
                value={formData.employee_id?.toString() || ''}
                onValueChange={(value) => {
                  setFormData((prev) => ({ ...prev, employee_id: value ? parseInt(value) : null }));
                  setErrors((prev) => { const e = { ...prev }; delete e.employee_id; return e; });
                }}
                disabled={isLoading || mode === 'edit'}
              >
                <SelectTrigger id="employee">
                  <SelectValue placeholder="Select employee" />
                </SelectTrigger>
                <SelectContent>
                  {employees.map((emp) => (
                    <SelectItem key={emp.id} value={emp.id.toString()}>
                      {emp.first_name} {emp.last_name} ({emp.employee_number})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.employee_id && (
                <p className="text-xs text-red-600 mt-1">{errors.employee_id}</p>
              )}
              {selectedEmployee && (
                <p className="text-xs text-gray-600 mt-1">
                  {selectedEmployee.department} • {selectedEmployee.position}
                </p>
              )}
            </div>

            {/* Component Selection */}
            <div>
              <Label htmlFor="component">Component *</Label>
              <Select
                value={formData.salary_component_id?.toString() || ''}
                onValueChange={(value) => {
                  setFormData((prev) => ({
                    ...prev,
                    salary_component_id: value ? parseInt(value) : null,
                    amount: getDefaultAmount(components.find((c) => c.id === parseInt(value))),
                  }));
                  setErrors((prev) => { const e = { ...prev }; delete e.salary_component_id; return e; });
                }}
                disabled={isLoading}
              >
                <SelectTrigger id="component">
                  <SelectValue placeholder="Select component" />
                </SelectTrigger>
                <SelectContent>
                  {components.map((comp) => (
                    <SelectItem key={comp.id} value={comp.id.toString()}>
                      {comp.code} - {comp.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.salary_component_id && (
                <p className="text-xs text-red-600 mt-1">{errors.salary_component_id}</p>
              )}
              {selectedComponent && (
                <p className="text-xs text-gray-600 mt-1">
                  Type: {selectedComponent.component_type} • Category: {selectedComponent.category}
                  {selectedComponent.is_deminimis && ' • De minimis'}
                </p>
              )}
            </div>
          </div>

          {/* Amount Section */}
          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="amount">Amount</Label>
              <Input
                id="amount"
                type="number"
                placeholder="0.00"
                step="0.01"
                value={formData.amount}
                onChange={(e) => {
                  setFormData((prev) => ({ ...prev, amount: e.target.value }));
                  setErrors((prev) => { const err = { ...prev }; delete err.amount; return err; });
                }}
                disabled={isLoading}
              />
              {errors.amount && <p className="text-xs text-red-600 mt-1">{errors.amount}</p>}
            </div>

            <div>
              <Label htmlFor="percentage">Percentage (%)</Label>
              <Input
                id="percentage"
                type="number"
                placeholder="0"
                step="0.01"
                min="0"
                max="100"
                value={formData.percentage}
                onChange={(e) => {
                  setFormData((prev) => ({ ...prev, percentage: e.target.value }));
                  setErrors((prev) => { const err = { ...prev }; delete err.percentage; return err; });
                }}
                disabled={isLoading}
              />
              {errors.percentage && (
                <p className="text-xs text-red-600 mt-1">{errors.percentage}</p>
              )}
            </div>

            <div>
              <Label htmlFor="units">Units</Label>
              <Input
                id="units"
                type="number"
                placeholder="0"
                step="0.01"
                value={formData.units}
                onChange={(e) => setFormData((prev) => ({ ...prev, units: e.target.value }))}
                disabled={isLoading}
              />
            </div>
          </div>

          {/* Frequency and Dates */}
          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="frequency">Frequency *</Label>
              <Select
                value={formData.frequency}
                onValueChange={(value) =>
                  setFormData((prev) => ({
                    ...prev,
                    frequency: value as EmployeeComponentAssignmentFormData['frequency'],
                  }))
                }
                disabled={isLoading}
              >
                <SelectTrigger id="frequency">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {FREQUENCY_OPTIONS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div>
              <Label htmlFor="effective_date">Effective Date *</Label>
              <Input
                id="effective_date"
                type="date"
                value={formData.effective_date}
                onChange={(e) => {
                  setFormData((prev) => ({ ...prev, effective_date: e.target.value }));
                  setErrors((prev) => { const err = { ...prev }; delete err.effective_date; return err; });
                }}
                disabled={isLoading}
              />
              {errors.effective_date && (
                <p className="text-xs text-red-600 mt-1">{errors.effective_date}</p>
              )}
            </div>

            <div>
              <Label htmlFor="end_date">End Date (Optional)</Label>
              <Input
                id="end_date"
                type="date"
                value={formData.end_date}
                onChange={(e) => {
                  setFormData((prev) => ({ ...prev, end_date: e.target.value }));
                  setErrors((prev) => { const err = { ...prev }; delete err.end_date; return err; });
                }}
                disabled={isLoading}
              />
              {errors.end_date && (
                <p className="text-xs text-red-600 mt-1">{errors.end_date}</p>
              )}
            </div>
          </div>

          {/* Options */}
          <div className="space-y-4">
            <div className="flex items-center space-x-2">
              <Checkbox
                id="is_prorated"
                checked={formData.is_prorated}
                onCheckedChange={(checked) =>
                  setFormData((prev) => ({ ...prev, is_prorated: !!checked }))
                }
                disabled={isLoading}
              />
              <Label htmlFor="is_prorated" className="font-normal cursor-pointer">
                Prorated (adjust for partial months)
              </Label>
            </div>

            <div className="flex items-center space-x-2">
              <Checkbox
                id="requires_attendance"
                checked={formData.requires_attendance}
                onCheckedChange={(checked) =>
                  setFormData((prev) => ({ ...prev, requires_attendance: !!checked }))
                }
                disabled={isLoading}
              />
              <Label htmlFor="requires_attendance" className="font-normal cursor-pointer">
                Requires full attendance
              </Label>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-3 justify-end pt-4">
            <Button variant="outline" type="button" onClick={onClose} disabled={isLoading}>
              Cancel
            </Button>
            <Button type="submit" disabled={isLoading}>
              {isLoading ? 'Saving...' : mode === 'create' ? 'Assign Component' : 'Update Assignment'}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
};