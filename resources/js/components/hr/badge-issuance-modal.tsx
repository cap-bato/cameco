import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
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
import { AlertCircle, Loader2, QrCode, AlertTriangle } from 'lucide-react';
import { BadgeScannerModal } from './badge-scanner-modal';
import { format } from 'date-fns';
import { Calendar } from '@/components/ui/calendar';

interface Employee {
    id: string;
    name: string;
    employee_id: string;
    department: string;
    position: string;
    photo?: string;
    badge?: {
        card_uid: string;
        issued_at: string;
        expires_at: string | null;
        last_used_at: string | null;
        is_active: boolean;
    };
}

interface BadgeIssuanceFormProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (formData: BadgeFormData) => void;
    employees: Employee[];
    isLoading?: boolean;
    existingBadgeUids?: string[];
}

export interface BadgeFormData {
    employee_id: string;
    card_uid: string;
    card_type: 'mifare' | 'desfire' | 'em4100';
    expires_at?: string;
    issue_notes?: string;
    employee_acknowledged: boolean;
    badge_tested: boolean;
    acknowledgement_signature?: string;
}

export function BadgeIssuanceModal({
    isOpen,
    onClose,
    onSubmit,
    employees,
    isLoading = false,
    existingBadgeUids = [],
}: BadgeIssuanceFormProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(null);
    const [showScanner, setShowScanner] = useState(false);
    const [showEmployeeDropdown, setShowEmployeeDropdown] = useState(false);
    const [expirationDate, setExpirationDate] = useState<Date | undefined>();

    const [formData, setFormData] = useState<BadgeFormData>({
        employee_id: '',
        card_uid: '',
        card_type: 'mifare',
        employee_acknowledged: false,
        badge_tested: false,
    });

    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isExistingBadgeWarningDismissed, setIsExistingBadgeWarningDismissed] = useState(false);
    const [touched, setTouched] = useState<Record<string, boolean>>({});

    // Filter employees based on search query
    const filteredEmployees = employees.filter((emp) =>
        emp.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        emp.employee_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
        emp.department.toLowerCase().includes(searchQuery.toLowerCase())
    );

    // Validate card UID format
    const validateCardUid = (uid: string) => {
        const uidRegex = /^([0-9A-Fa-f]{2}:){4}[0-9A-Fa-f]{2}$/;
        return uidRegex.test(uid);
    };

    // Subtask 1.3.4: Validate form before submission
    const validateForm = () => {
        const newErrors: Record<string, string> = {};

        // Employee selection required
        if (!formData.employee_id) {
            newErrors.employee_id = 'Employee selection is required';
        }

        // Card UID required with format validation
        if (!formData.card_uid) {
            newErrors.card_uid = 'Card UID is required';
        } else if (!validateCardUid(formData.card_uid)) {
            newErrors.card_uid = 'Card UID format must be XX:XX:XX:XX:XX (e.g., 04:3A:B2:C5:D8)';
        } else if (existingBadgeUids.includes(formData.card_uid)) {
            // Subtask 1.3.4: Card UID uniqueness check
            newErrors.card_uid = 'This card UID is already assigned to another employee';
        }

        // Card type required
        if (!formData.card_type) {
            newErrors.card_type = 'Card type is required';
        }

        // Expiration date must be future
        if (expirationDate && expirationDate < new Date()) {
            newErrors.expires_at = 'Expiration date must be in the future';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Real-time field validation (for touched fields)
    const validateField = (fieldName: string, value: string | null) => {
        const fieldErrors: Record<string, string> = { ...errors };

        switch (fieldName) {
            case 'card_uid':
                if (!value) {
                    fieldErrors.card_uid = 'Card UID is required';
                } else if (!validateCardUid(value)) {
                    fieldErrors.card_uid = 'Card UID format must be XX:XX:XX:XX:XX';
                } else if (existingBadgeUids.includes(value)) {
                    fieldErrors.card_uid = 'This card UID is already in use';
                } else {
                    delete fieldErrors.card_uid;
                }
                break;
            case 'card_type':
                if (!value) {
                    fieldErrors.card_type = 'Card type is required';
                } else {
                    delete fieldErrors.card_type;
                }
                break;
        }

        setErrors(fieldErrors);
    };

    const handleSelectEmployee = (employee: Employee) => {
        setSelectedEmployee(employee);
        setFormData((prev) => ({
            ...prev,
            employee_id: employee.id,
        }));
        setSearchQuery('');
        setShowEmployeeDropdown(false);
        setIsExistingBadgeWarningDismissed(false);
    };

    const handleScanComplete = (cardUid: string, cardType: string) => {
        setFormData((prev) => ({
            ...prev,
            card_uid: cardUid,
            card_type: cardType as 'mifare' | 'desfire' | 'em4100',
        }));
        setShowScanner(false);
    };

    const handleCardUidChange = (value: string) => {
        const upperValue = value.toUpperCase();
        setFormData((prev) => ({
            ...prev,
            card_uid: upperValue,
        }));
        
        // Mark field as touched and validate
        setTouched((prev) => ({ ...prev, card_uid: true }));
        validateField('card_uid', upperValue);
    };

    const handleSubmit = () => {
        if (validateForm()) {
            const dataToSubmit: BadgeFormData = {
                ...formData,
                expires_at: expirationDate ? format(expirationDate, 'yyyy-MM-dd') : undefined,
            };
            onSubmit(dataToSubmit);
        }
    };

    const handleClose = () => {
        setFormData({
            employee_id: '',
            card_uid: '',
            card_type: 'mifare',
            employee_acknowledged: false,
            badge_tested: false,
        });
        setSelectedEmployee(null);
        setSearchQuery('');
        setExpirationDate(undefined);
        setErrors({});
        setTouched({});
        setShowScanner(false);
        setIsExistingBadgeWarningDismissed(false);
        onClose();
    };

    const hasExistingBadge = selectedEmployee?.badge?.is_active;

    return (
        <>
            <Dialog open={isOpen} onOpenChange={handleClose}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Issue New RFID Badge</DialogTitle>
                        <DialogDescription>
                            Complete the form below to issue a new RFID badge to an employee
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-6">
                        {/* Section 1: Employee Selection */}
                        <div className="space-y-3 border rounded-lg p-4 bg-muted/50">
                            <h3 className="font-semibold text-sm">1. Employee Selection</h3>

                            {selectedEmployee ? (
                                <div className="flex items-center gap-4 p-3 bg-white rounded-lg border">
                                    {selectedEmployee.photo && (
                                        <img
                                            src={selectedEmployee.photo}
                                            alt={selectedEmployee.name}
                                            className="h-10 w-10 rounded-full object-cover"
                                        />
                                    )}
                                    <div className="flex-1">
                                        <p className="font-semibold text-sm">{selectedEmployee.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedEmployee.employee_id} • {selectedEmployee.department} •{' '}
                                            {selectedEmployee.position}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSelectedEmployee(null);
                                            setFormData((prev) => ({
                                                ...prev,
                                                employee_id: '',
                                            }));
                                            setIsExistingBadgeWarningDismissed(false);
                                        }}
                                    >
                                        Change
                                    </Button>
                                </div>
                            ) : (
                                <Popover open={showEmployeeDropdown} onOpenChange={setShowEmployeeDropdown}>
                                    <PopoverTrigger asChild>
                                        <Input
                                            placeholder="Search employee by name, ID, or department..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className={errors.employee_id ? 'border-red-500' : ''}
                                        />
                                    </PopoverTrigger>
                                    <PopoverContent className="p-0 w-full" align="start">
                                        <div className="max-h-64 overflow-y-auto">
                                            {filteredEmployees.length > 0 ? (
                                                <div className="space-y-1 p-2">
                                                    {filteredEmployees.map((emp) => (
                                                        <button
                                                            key={emp.id}
                                                            onClick={() => handleSelectEmployee(emp)}
                                                            className="w-full text-left px-3 py-2 rounded-md hover:bg-muted text-sm"
                                                        >
                                                            <div className="font-medium">{emp.name}</div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {emp.employee_id} • {emp.department}
                                                            </div>
                                                        </button>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="p-4 text-center text-sm text-muted-foreground">
                                                    No employees found
                                                </div>
                                            )}
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            )}

                            {errors.employee_id && (
                                <p className="text-sm text-red-600 flex items-center gap-2">
                                    <AlertCircle className="h-4 w-4" />
                                    {errors.employee_id}
                                </p>
                            )}

                            {/* Existing Badge Warning - Subtask 1.3.3 */}
                            {hasExistingBadge && !isExistingBadgeWarningDismissed && (
                                <div className="bg-amber-50 border-2 border-amber-300 rounded-lg p-4">
                                    <div className="flex gap-3">
                                        <AlertTriangle className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <p className="font-semibold text-sm text-amber-900">
                                                ⚠️ Employee Already Has Active Badge
                                            </p>
                                            <p className="text-xs text-amber-800 mt-3 mb-3 space-y-1">
                                                <div><strong>Card UID:</strong> <code className="font-mono bg-amber-100 px-2 py-1 rounded">{selectedEmployee?.badge?.card_uid}</code></div>
                                                <div><strong>Issued:</strong> {selectedEmployee?.badge?.issued_at ? new Date(selectedEmployee.badge.issued_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A'}</div>
                                                {selectedEmployee?.badge?.last_used_at && (
                                                    <div><strong>Last Used:</strong> {new Date(selectedEmployee.badge.last_used_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</div>
                                                )}
                                                {selectedEmployee?.badge?.expires_at && (
                                                    <div><strong>Expires:</strong> {new Date(selectedEmployee.badge.expires_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</div>
                                                )}
                                            </p>
                                            <p className="text-xs text-amber-700 font-medium">
                                                Select "Replace Badge" to issue a new badge or "Cancel" to select another employee
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex gap-2 justify-end mt-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setSelectedEmployee(null);
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    employee_id: '',
                                                }));
                                                setIsExistingBadgeWarningDismissed(false);
                                            }}
                                            className="border-amber-300 text-amber-700 hover:bg-amber-100"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="default"
                                            size="sm"
                                            onClick={() => setIsExistingBadgeWarningDismissed(true)}
                                            className="bg-amber-600 hover:bg-amber-700"
                                        >
                                            Replace Badge
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Section 2: Badge Information */}
                        {selectedEmployee && isExistingBadgeWarningDismissed !== false && (
                            <div className="space-y-4 border rounded-lg p-4 bg-muted/50">
                                <h3 className="font-semibold text-sm">2. Badge Information</h3>

                                {/* Card UID */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Card UID <span className="text-red-600">*</span>
                                    </label>
                                    <p className="text-xs text-muted-foreground">Format: XX:XX:XX:XX:XX (e.g., 04:3A:B2:C5:D8)</p>
                                    <div className="flex gap-2">
                                        <Input
                                            placeholder="XX:XX:XX:XX:XX"
                                            value={formData.card_uid}
                                            onChange={(e) => handleCardUidChange(e.target.value)}
                                            onBlur={() => {
                                                setTouched((prev) => ({ ...prev, card_uid: true }));
                                                validateField('card_uid', formData.card_uid);
                                            }}
                                            className={`font-mono flex-1 ${errors.card_uid && touched.card_uid ? 'border-red-500 focus:border-red-500' : ''}`}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => setShowScanner(true)}
                                            title="Scan Badge (Opens scanner)"
                                            className="flex-shrink-0"
                                        >
                                            <QrCode className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    {errors.card_uid && touched.card_uid && (
                                        <p className="text-sm text-red-600 flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4 flex-shrink-0" />
                                            {errors.card_uid}
                                        </p>
                                    )}
                                </div>

                                {/* Card Type */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        Card Type <span className="text-red-600">*</span>
                                    </label>
                                    <Select 
                                        value={formData.card_type} 
                                        onValueChange={(value) => {
                                            setFormData((prev) => ({
                                                ...prev,
                                                card_type: value as 'mifare' | 'desfire' | 'em4100',
                                            }));
                                            setTouched((prev) => ({ ...prev, card_type: true }));
                                            validateField('card_type', value);
                                        }}
                                    >
                                        <SelectTrigger className={errors.card_type && touched.card_type ? 'border-red-500' : ''}>
                                            <SelectValue placeholder="Select card type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="mifare">Mifare (Standard)</SelectItem>
                                            <SelectItem value="desfire">DESFire (Advanced)</SelectItem>
                                            <SelectItem value="em4100">EM4100 (Legacy)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.card_type && touched.card_type && (
                                        <p className="text-sm text-red-600 flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4 flex-shrink-0" />
                                            {errors.card_type}
                                        </p>
                                    )}
                                </div>

                                {/* Expiration Date */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Expiration Date (Optional)</label>
                                    <p className="text-xs text-muted-foreground">Leave empty for no expiration</p>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className={`w-full justify-start text-left ${errors.expires_at ? 'border-red-500' : ''}`}
                                            >
                                                {expirationDate
                                                    ? format(expirationDate, 'MMM DD, yyyy')
                                                    : 'Select date (optional)...'}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={expirationDate}
                                                onSelect={setExpirationDate}
                                                disabled={(date) =>
                                                    date < new Date(new Date().setHours(0, 0, 0, 0))
                                                }
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    {errors.expires_at && (
                                        <p className="text-sm text-red-600 flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4 flex-shrink-0" />
                                            {errors.expires_at}
                                        </p>
                                    )}
                                </div>

                                {/* Issue Notes */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Issue Notes (Optional)</label>
                                    <textarea
                                        placeholder="e.g., Initial issuance, New hire"
                                        value={formData.issue_notes || ''}
                                        onChange={(e) =>
                                            setFormData((prev) => ({
                                                ...prev,
                                                issue_notes: e.target.value,
                                            }))
                                        }
                                        className="w-full px-3 py-2 text-sm border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                        rows={3}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Section 3: Verification */}
                        {selectedEmployee && isExistingBadgeWarningDismissed !== false && (
                            <div className="space-y-4 border rounded-lg p-4 bg-muted/50">
                                <h3 className="font-semibold text-sm">3. Verification (Optional)</h3>

                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="employee_acknowledged"
                                            checked={formData.employee_acknowledged}
                                            onCheckedChange={(checked) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    employee_acknowledged: checked === true,
                                                }))
                                            }
                                        />
                                        <label
                                            htmlFor="employee_acknowledged"
                                            className="text-sm font-medium cursor-pointer"
                                        >
                                            Employee acknowledged badge receipt
                                        </label>
                                    </div>

                                    <div className="space-y-2 pl-7">
                                        <label className="text-sm font-medium">Signature (Optional)</label>
                                        <Input
                                            placeholder="Employee signature or initials"
                                            value={formData.acknowledgement_signature || ''}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    acknowledgement_signature: e.target.value,
                                                }))
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="badge_tested"
                                            checked={formData.badge_tested}
                                            onCheckedChange={(checked) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    badge_tested: checked === true,
                                                }))
                                            }
                                        />
                                        <label
                                            htmlFor="badge_tested"
                                            className="text-sm font-medium cursor-pointer"
                                        >
                                            Badge tested and working
                                        </label>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSubmit}
                            disabled={isLoading || !selectedEmployee || (hasExistingBadge && !isExistingBadgeWarningDismissed) || Object.keys(errors).length > 0}
                            className="gap-2"
                        >
                            {isLoading && <Loader2 className="h-4 w-4 animate-spin" />}
                            {isLoading ? 'Issuing...' : 'Issue Badge'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Badge Scanner Modal */}
            <BadgeScannerModal
                isOpen={showScanner}
                onClose={() => setShowScanner(false)}
                onScanComplete={handleScanComplete}
            />
        </>
    );
}
