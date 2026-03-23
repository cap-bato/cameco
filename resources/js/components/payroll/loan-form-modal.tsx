import { useState, useEffect } from 'react';
import { EmployeeLoan, EmployeeLoanFormData } from '@/types/payroll-pages';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { AlertCircle } from 'lucide-react';

interface LoanFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (data: EmployeeLoanFormData) => void;
    loan?: EmployeeLoan;
    employees: Array<{ id: number; name: string; employee_number: string; department: string }>;
    approvers: Array<{ id: number; name: string }>;
    isLoading?: boolean;
}

const LOAN_TYPES: Array<{ value: string; label: string }> = [
    { value: 'sss_loan', label: 'SSS Loan' },
    { value: 'pagibig_loan', label: 'Pag-IBIG Loan' },
    { value: 'company_loan', label: 'Company Loan' },
    { value: 'emergency_loan', label: 'Emergency Loan' },
    { value: 'housing_loan', label: 'Housing Loan' },
];

export function LoanFormModal({
    isOpen,
    onClose,
    onSubmit,
    loan,
    employees,
    approvers,
    isLoading = false,
}: LoanFormModalProps) {
    const [formData, setFormData] = useState<EmployeeLoanFormData>({
        employee_id: loan?.employee_id || 0,
        loan_type: loan?.loan_type || 'company',
        principal_amount: loan?.principal_amount || 0,
        interest_rate: loan?.interest_rate || undefined,
        monthly_amortization: loan?.monthly_amortization || 0,
        number_of_installments: loan?.number_of_installments || 0,
        loan_date: loan?.loan_date || new Date().toISOString().split('T')[0],
        start_date: loan?.start_date || new Date().toISOString().split('T')[0],
        approved_by: loan?.approved_by ? parseInt(loan.approved_by) : undefined,
    });

    const [totalAmount, setTotalAmount] = useState(0);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Auto-calculate total amount and validate
    useEffect(() => {
        const total = formData.principal_amount + (formData.interest_rate || 0);
        setTotalAmount(total);

        // Validate monthly amortization doesn't exceed total
        if (formData.monthly_amortization > total && total > 0) {
            setErrors(prev => ({
                ...prev,
                monthly_amortization: 'Monthly amortization cannot exceed total amount',
            }));
        } else {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors.monthly_amortization;
                return newErrors;
            });
        }
    }, [formData.principal_amount, formData.interest_rate, formData.monthly_amortization]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Basic validation
        const newErrors: Record<string, string> = {};

        if (!formData.employee_id) {
            newErrors.employee_id = 'Please select an employee';
        }
        if (formData.principal_amount <= 0) {
            newErrors.principal_amount = 'Principal amount must be greater than 0';
        }
        if (formData.monthly_amortization <= 0) {
            newErrors.monthly_amortization = 'Monthly amortization must be greater than 0';
        }
        if (formData.number_of_installments <= 0) {
            newErrors.number_of_installments = 'Number of installments must be greater than 0';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        onSubmit(formData);
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{loan ? 'Edit Loan' : 'Create New Loan'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Employee Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="employee_id">Employee *</Label>
                        <Select
                            value={formData.employee_id.toString()}
                            onValueChange={(value) =>
                                setFormData({ ...formData, employee_id: parseInt(value) })
                            }
                            disabled={loan !== undefined} // Disable if editing
                        >
                            <SelectTrigger id="employee_id">
                                <SelectValue placeholder="Select employee..." />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((emp) => (
                                    <SelectItem key={emp.id} value={emp.id.toString()}>
                                        {emp.name} ({emp.employee_number})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.employee_id && (
                            <p className="text-xs text-destructive">{errors.employee_id}</p>
                        )}
                    </div>

                    {/* Loan Type */}
                    <div className="space-y-2">
                        <Label htmlFor="loan_type">Loan Type *</Label>
                        <Select
                            value={formData.loan_type}
                            onValueChange={(value) =>
                                setFormData({
                                    ...formData,
                                    loan_type: value as EmployeeLoanFormData['loan_type'],
                                    interest_rate: ['sss_loan', 'pagibig_loan'].includes(value) ? 0 : undefined,
                                })
                            }
                        >
                            <SelectTrigger id="loan_type">
                                <SelectValue placeholder="Select loan type..." />
                            </SelectTrigger>
                            <SelectContent>
                                {LOAN_TYPES.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Principal Amount */}
                    <div className="space-y-2">
                        <Label htmlFor="principal_amount">Principal Amount *</Label>
                        <Input
                            id="principal_amount"
                            type="number"
                            step="0.01"
                            value={formData.principal_amount}
                            onChange={(e) =>
                                setFormData({
                                    ...formData,
                                    principal_amount: parseFloat(e.target.value) || 0,
                                })
                            }
                            placeholder="0.00"
                            className={errors.principal_amount ? 'border-destructive' : ''}
                        />
                        {errors.principal_amount && (
                            <p className="text-xs text-destructive">{errors.principal_amount}</p>
                        )}
                    </div>

                    {/* Interest Rate (only for non-govt loans) */}
                    {['company_loan', 'emergency_loan', 'housing_loan'].includes(formData.loan_type) && (
                        <div className="space-y-2">
                            <Label htmlFor="interest_rate">Interest Rate (%)</Label>
                            <Input
                                id="interest_rate"
                                type="number"
                                step="0.01"
                                value={formData.interest_rate || 0}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        interest_rate: parseFloat(e.target.value) || 0,
                                    })
                                }
                                placeholder="0.00"
                            />
                        </div>
                    )}

                    {/* Total Amount Display */}
                    <div className="space-y-2 rounded-lg bg-muted p-3">
                        <p className="text-xs font-semibold text-muted-foreground">Total Amount</p>
                        <p className="text-xl font-bold">₱{totalAmount.toFixed(2)}</p>
                    </div>

                    {/* Monthly Amortization */}
                    <div className="space-y-2">
                        <Label htmlFor="monthly_amortization">Monthly Amortization *</Label>
                        <Input
                            id="monthly_amortization"
                            type="number"
                            step="0.01"
                            value={formData.monthly_amortization}
                            onChange={(e) =>
                                setFormData({
                                    ...formData,
                                    monthly_amortization: parseFloat(e.target.value) || 0,
                                })
                            }
                            placeholder="0.00"
                            className={errors.monthly_amortization ? 'border-destructive' : ''}
                        />
                        {errors.monthly_amortization && (
                            <p className="text-xs text-destructive">{errors.monthly_amortization}</p>
                        )}
                    </div>

                    {/* Number of Installments */}
                    <div className="space-y-2">
                        <Label htmlFor="number_of_installments">Number of Installments *</Label>
                        <Input
                            id="number_of_installments"
                            type="number"
                            step="1"
                            value={formData.number_of_installments}
                            onChange={(e) =>
                                setFormData({
                                    ...formData,
                                    number_of_installments: parseInt(e.target.value) || 0,
                                })
                            }
                            placeholder="0"
                            className={errors.number_of_installments ? 'border-destructive' : ''}
                        />
                        {errors.number_of_installments && (
                            <p className="text-xs text-destructive">{errors.number_of_installments}</p>
                        )}
                    </div>

                    {/* Loan Date */}
                    <div className="space-y-2">
                        <Label htmlFor="loan_date">Loan Date *</Label>
                        <Input
                            id="loan_date"
                            type="date"
                            value={formData.loan_date}
                            onChange={(e) =>
                                setFormData({ ...formData, loan_date: e.target.value })
                            }
                        />
                    </div>

                    {/* Start Date */}
                    <div className="space-y-2">
                        <Label htmlFor="start_date">Start Deduction Date *</Label>
                        <Input
                            id="start_date"
                            type="date"
                            value={formData.start_date}
                            onChange={(e) =>
                                setFormData({ ...formData, start_date: e.target.value })
                            }
                        />
                    </div>

                    {/* Approval Section */}
                    <div className="space-y-3 rounded-lg border border-border bg-muted/30 p-3">
                        <p className="text-xs font-semibold text-muted-foreground">Approval</p>
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="auto_approve"
                                checked={!!formData.approved_by}
                                onCheckedChange={(checked) =>
                                    setFormData({
                                        ...formData,
                                        approved_by: checked ? approvers[0]?.id : undefined,
                                    })
                                }
                            />
                            <Label htmlFor="auto_approve" className="cursor-pointer text-sm">
                                Mark as approved
                            </Label>
                        </div>

                        {formData.approved_by && (
                            <Select
                                value={formData.approved_by?.toString()}
                                onValueChange={(value) =>
                                    setFormData({ ...formData, approved_by: parseInt(value) })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select approver..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {approvers.map((approver) => (
                                        <SelectItem key={approver.id} value={approver.id.toString()}>
                                            {approver.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>

                    {/* Info Message */}
                    <div className="flex gap-2 rounded-lg bg-blue-50 p-3">
                        <AlertCircle className="h-4 w-4 text-blue-600 flex-shrink-0 mt-0.5" />
                        <p className="text-xs text-blue-700">
                            Maturity date will be auto-calculated based on installments and start date.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={isLoading}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? 'Saving...' : loan ? 'Update' : 'Create'} Loan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
