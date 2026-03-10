import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { format } from 'date-fns';
import {
    AlertTriangle,
    ChevronRight,
    ChevronLeft,
    QrCode,
    Copy,
    Check,
    AlertCircle,
} from 'lucide-react';

interface ReplacementBadge {
    id: string;
    card_uid: string;
    employee_id: string;
    employee_name: string;
    department: string;
    position: string;
    card_type: 'mifare' | 'desfire' | 'em4100';
    issued_at: string;
    expires_at: string | null;
    is_active: boolean;
    usage_count: number;
    last_used_at: string | null;
}

interface BadgeReplacementModalProps {
    isOpen: boolean;
    onClose: () => void;
    badge: ReplacementBadge;
    onReplace: (data: ReplacementData) => void;
}

interface ReplacementData {
    old_badge_id: string;
    reason: 'lost' | 'stolen' | 'damaged' | 'upgrade' | 'other';
    other_reason?: string;
    notes: string;
    new_card_uid: string;
    new_card_type: 'mifare' | 'desfire' | 'em4100';
    new_expiration_date: string | null;
    replacement_fee: number | null;
    deduct_from_payroll: boolean;
    paid_in_cash: boolean;
    last_known_location?: string;
    last_known_timestamp?: string;
    date_lost_stolen?: string;
    security_notified: boolean;
    incident_report_number?: string;
}

const cardTypeLabel = {
    mifare: 'Mifare (Standard)',
    desfire: 'DESFire (Advanced)',
    em4100: 'EM4100 (Legacy)',
};

const reasonOptions = [
    { value: 'lost', label: 'Lost', icon: '🔴', color: 'bg-red-100 text-red-800' },
    { value: 'stolen', label: 'Stolen', icon: '🟠', color: 'bg-orange-100 text-orange-800' },
    { value: 'damaged', label: 'Damaged/Malfunctioning', icon: '🟡', color: 'bg-yellow-100 text-yellow-800' },
    { value: 'upgrade', label: 'Upgrade', icon: '🔵', color: 'bg-blue-100 text-blue-800' },
    { value: 'other', label: 'Other', icon: '⚪', color: 'bg-gray-100 text-gray-800' },
];

export function BadgeReplacementModal({
    isOpen,
    onClose,
    badge,
    onReplace,
}: BadgeReplacementModalProps) {
    const [currentStep, setCurrentStep] = useState(1);
    const [copied, setCopied] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Step 1 state
    const [reason, setReason] = useState<'lost' | 'stolen' | 'damaged' | 'upgrade' | 'other'>('damaged');
    const [otherReason, setOtherReason] = useState('');
    const [notes, setNotes] = useState('');

    // Step 2 state
    const [newCardUid, setNewCardUid] = useState('');
    const [newCardType, setNewCardType] = useState<'mifare' | 'desfire' | 'em4100'>('mifare');
    const [newExpirationDate, setNewExpirationDate] = useState(badge.expires_at || '');
    const [replacementFee, setReplacementFee] = useState<number | null>(null);
    const [deductFromPayroll, setDeductFromPayroll] = useState(false);
    const [paidInCash, setPaidInCash] = useState(false);

    // Lost/Stolen specific state
    const [dateLostStolen, setDateLostStolen] = useState('');
    const [securityNotified, setSecurityNotified] = useState(false);
    const [incidentReportNumber, setIncidentReportNumber] = useState('');

    // Validation
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleCopyBadgeUid = () => {
        navigator.clipboard.writeText(badge.card_uid);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const validateStep = (step: number): boolean => {
        const newErrors: Record<string, string> = {};

        if (step === 2) {
            if (!newCardUid.trim()) {
                newErrors.newCardUid = 'New card UID is required';
            } else if (!/^([0-9A-Fa-f]{2}:){4}[0-9A-Fa-f]{2}$/.test(newCardUid)) {
                newErrors.newCardUid = 'Card UID format must be XX:XX:XX:XX:XX';
            } else if (newCardUid === badge.card_uid) {
                newErrors.newCardUid = 'New card UID must be different from old card UID';
            }

            if (!newCardType) {
                newErrors.newCardType = 'Card type is required';
            }

            if ((reason === 'lost' || reason === 'stolen') && !dateLostStolen) {
                newErrors.dateLostStolen = 'Date lost/stolen is required';
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleNextStep = () => {
        if (validateStep(currentStep)) {
            setCurrentStep(currentStep + 1);
        }
    };

    const handlePrevStep = () => {
        setCurrentStep(currentStep - 1);
    };

    const handleConfirmReplacement = async () => {
        setIsSubmitting(true);
        try {
            const replacementData: ReplacementData = {
                old_badge_id: badge.id,
                reason: reason as 'lost' | 'stolen' | 'damaged' | 'upgrade' | 'other',
                other_reason: reason === 'other' ? otherReason : undefined,
                notes,
                new_card_uid: newCardUid,
                new_card_type: newCardType,
                new_expiration_date: newExpirationDate || null,
                replacement_fee: replacementFee,
                deduct_from_payroll: deductFromPayroll,
                paid_in_cash: paidInCash,
                security_notified: securityNotified,
                incident_report_number: incidentReportNumber || undefined,
                date_lost_stolen: dateLostStolen || undefined,
                last_known_location: 'Main Gate',
                last_known_timestamp: badge.last_used_at || undefined,
            };

            onReplace(replacementData);
            handleClose();
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        setCurrentStep(1);
        setReason('damaged');
        setOtherReason('');
        setNotes('');
        setNewCardUid('');
        setNewCardType('mifare');
        setNewExpirationDate(badge.expires_at || '');
        setReplacementFee(null);
        setDeductFromPayroll(false);
        setPaidInCash(false);
        setDateLostStolen('');
        setSecurityNotified(false);
        setIncidentReportNumber('');
        setErrors({});
        onClose();
    };

    const isLostOrStolen = reason === 'lost' || reason === 'stolen';

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Replace Badge</DialogTitle>
                </DialogHeader>

                {/* Step Indicator */}
                <div className="flex justify-between mb-6">
                    {[1, 2, 3].map((step) => (
                        <div key={step} className="flex items-center gap-2">
                            <div
                                className={`w-10 h-10 rounded-full flex items-center justify-center font-bold ${
                                    step <= currentStep
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-200 text-gray-600'
                                }`}
                            >
                                {step}
                            </div>
                            <span className="text-sm font-medium hidden sm:inline">
                                {step === 1 && 'Select Reason'}
                                {step === 2 && 'New Badge'}
                                {step === 3 && 'Confirm'}
                            </span>
                            {step < 3 && (
                                <ChevronRight
                                    className={`h-4 w-4 ${step < currentStep ? 'text-blue-600' : 'text-gray-300'}`}
                                />
                            )}
                        </div>
                    ))}
                </div>

                {/* Step 1: Select Reason */}
                {currentStep === 1 && (
                    <div className="space-y-6">
                        {/* Current Badge Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Current Badge (Read-Only)</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Card UID
                                        </p>
                                        <div className="flex gap-2 mt-1">
                                            <code className="text-sm bg-muted p-2 rounded flex-1">
                                                {badge.card_uid}
                                            </code>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleCopyBadgeUid}
                                            >
                                                {copied ? (
                                                    <Check className="h-4 w-4 text-green-600" />
                                                ) : (
                                                    <Copy className="h-4 w-4" />
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Card Type
                                        </p>
                                        <p className="text-sm mt-1">
                                            {cardTypeLabel[badge.card_type]}
                                        </p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Issued
                                        </p>
                                        <p className="text-sm mt-1">
                                            {format(new Date(badge.issued_at), 'MMM dd, yyyy')}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Total Scans
                                        </p>
                                        <p className="text-sm mt-1">{badge.usage_count}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Replacement Reason */}
                        <div className="space-y-3">
                            <Label className="text-base font-semibold">Replacement Reason</Label>
                            <RadioGroup value={reason} onValueChange={(val) => setReason(val as 'lost' | 'stolen' | 'damaged' | 'upgrade' | 'other')}>
                                {reasonOptions.map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center space-x-2 p-3 border rounded-lg hover:bg-muted cursor-pointer"
                                    >
                                        <RadioGroupItem
                                            value={option.value}
                                            id={`reason-${option.value}`}
                                        />
                                        <Label
                                            htmlFor={`reason-${option.value}`}
                                            className="flex-1 cursor-pointer font-normal"
                                        >
                                            <span className="mr-2">{option.icon}</span>
                                            {option.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </div>

                        {/* Other Reason Text Input */}
                        {reason === 'other' && (
                            <div className="space-y-2">
                                <Label htmlFor="other-reason">
                                    Please specify the reason
                                </Label>
                                <Input
                                    id="other-reason"
                                    placeholder="Enter replacement reason"
                                    value={otherReason}
                                    onChange={(e) => setOtherReason(e.target.value)}
                                />
                            </div>
                        )}

                        {/* Additional Notes */}
                        <div className="space-y-2">
                            <Label htmlFor="notes">Additional Notes (Optional)</Label>
                            <Textarea
                                id="notes"
                                placeholder="Add any additional information about the replacement..."
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={3}
                            />
                        </div>

                        {/* Lost/Stolen Warning */}
                        {isLostOrStolen && (
                            <Alert className="border-orange-200 bg-orange-50">
                                <AlertTriangle className="h-4 w-4 text-orange-600" />
                                <AlertDescription className="text-orange-800">
                                    Additional security information will be required in the next step.
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                )}

                {/* Step 2: New Badge Information */}
                {currentStep === 2 && (
                    <div className="space-y-6">
                        {/* Card UID Input */}
                        <div className="space-y-2">
                            <Label htmlFor="new-card-uid">New Card UID *</Label>
                            <p className="text-xs text-muted-foreground">
                                Format: XX:XX:XX:XX:XX (e.g., 04:3A:B2:C5:D8)
                            </p>
                            <div className="flex gap-2">
                                <Input
                                    id="new-card-uid"
                                    placeholder="04:3A:B2:C5:D8"
                                    value={newCardUid}
                                    onChange={(e) => setNewCardUid(e.target.value)}
                                    className={errors.newCardUid ? 'border-red-500' : ''}
                                />
                                <Button variant="outline" size="icon">
                                    <QrCode className="h-4 w-4" />
                                </Button>
                            </div>
                            {errors.newCardUid && (
                                <p className="text-xs text-red-600 flex items-center gap-1">
                                    <AlertCircle className="h-3 w-3" />
                                    {errors.newCardUid}
                                </p>
                            )}
                        </div>

                        {/* Card Type Selector */}
                        <div className="space-y-2">
                            <Label htmlFor="card-type">Card Type *</Label>
                            <Select value={newCardType} onValueChange={(val) => setNewCardType(val as 'mifare' | 'desfire' | 'em4100')}>
                                <SelectTrigger
                                    id="card-type"
                                    className={errors.newCardType ? 'border-red-500' : ''}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="mifare">Mifare (Standard)</SelectItem>
                                    <SelectItem value="desfire">DESFire (Advanced)</SelectItem>
                                    <SelectItem value="em4100">EM4100 (Legacy)</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.newCardType && (
                                <p className="text-xs text-red-600 flex items-center gap-1">
                                    <AlertCircle className="h-3 w-3" />
                                    {errors.newCardType}
                                </p>
                            )}
                        </div>

                        {/* Expiration Date */}
                        <div className="space-y-2">
                            <Label htmlFor="expiration-date">
                                Expiration Date (Copy from old: {badge.expires_at ? format(new Date(badge.expires_at), 'MMM dd, yyyy') : 'No expiration'})
                            </Label>
                            <Input
                                id="expiration-date"
                                type="date"
                                value={newExpirationDate}
                                onChange={(e) => setNewExpirationDate(e.target.value)}
                            />
                        </div>

                        {/* Replacement Fee */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Replacement Fee (Optional)</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <Label htmlFor="replacement-fee">Amount (₱)</Label>
                                    <Input
                                        id="replacement-fee"
                                        type="number"
                                        placeholder="0.00"
                                        value={replacementFee || ''}
                                        onChange={(e) => setReplacementFee(e.target.value ? parseFloat(e.target.value) : null)}
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="deduct-payroll"
                                            checked={deductFromPayroll}
                                            onCheckedChange={(checked) => setDeductFromPayroll(checked === true)}
                                        />
                                        <Label htmlFor="deduct-payroll" className="font-normal cursor-pointer">
                                            Deduct from next payroll
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="paid-cash"
                                            checked={paidInCash}
                                            onCheckedChange={(checked) => setPaidInCash(checked === true)}
                                        />
                                        <Label htmlFor="paid-cash" className="font-normal cursor-pointer">
                                            Paid in cash (attach receipt)
                                        </Label>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Lost/Stolen Additional Fields */}
                        {isLostOrStolen && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Security Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Alert className="border-orange-200 bg-orange-50">
                                        <AlertTriangle className="h-4 w-4 text-orange-600" />
                                        <AlertDescription className="text-orange-800 text-sm">
                                            Last known scan: Main Gate at{' '}
                                            {badge.last_used_at
                                                ? format(new Date(badge.last_used_at), 'MMM dd, yyyy HH:mm')
                                                : 'Unknown'}
                                        </AlertDescription>
                                    </Alert>

                                    <div className="space-y-2">
                                        <Label htmlFor="date-lost">
                                            Date {reason === 'lost' ? 'Lost' : 'Stolen'} *
                                        </Label>
                                        <Input
                                            id="date-lost"
                                            type="date"
                                            value={dateLostStolen}
                                            onChange={(e) => setDateLostStolen(e.target.value)}
                                            className={errors.dateLostStolen ? 'border-red-500' : ''}
                                        />
                                        {errors.dateLostStolen && (
                                            <p className="text-xs text-red-600 flex items-center gap-1">
                                                <AlertCircle className="h-3 w-3" />
                                                {errors.dateLostStolen}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Security Notified?</Label>
                                        <RadioGroup
                                            value={securityNotified ? 'yes' : 'no'}
                                            onValueChange={(val) => setSecurityNotified(val === 'yes')}
                                        >
                                            <div className="flex items-center space-x-2">
                                                <RadioGroupItem value="yes" id="security-yes" />
                                                <Label htmlFor="security-yes" className="font-normal cursor-pointer">
                                                    Yes
                                                </Label>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <RadioGroupItem value="no" id="security-no" />
                                                <Label htmlFor="security-no" className="font-normal cursor-pointer">
                                                    No
                                                </Label>
                                            </div>
                                        </RadioGroup>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="incident-number">
                                            Incident Report Number (Optional)
                                        </Label>
                                        <Input
                                            id="incident-number"
                                            placeholder="e.g., INC-2024-001"
                                            value={incidentReportNumber}
                                            onChange={(e) => setIncidentReportNumber(e.target.value)}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Step 3: Review & Confirm */}
                {currentStep === 3 && (
                    <div className="space-y-6">
                        {/* Badge Comparison */}
                        <div className="grid grid-cols-2 gap-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm">Old Badge</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <div>
                                        <p className="font-medium text-muted-foreground">UID</p>
                                        <p className="font-mono">{badge.card_uid}</p>
                                    </div>
                                    <div>
                                        <p className="font-medium text-muted-foreground">Status</p>
                                        <Badge className="bg-green-100 text-green-800">ACTIVE</Badge>
                                    </div>
                                    <div>
                                        <p className="font-medium text-muted-foreground">Issued</p>
                                        <p>{format(new Date(badge.issued_at), 'MMM dd, yyyy')}</p>
                                    </div>
                                    <div>
                                        <p className="font-medium text-muted-foreground">Scans</p>
                                        <p>{badge.usage_count}</p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm">New Badge</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <div>
                                        <p className="font-medium text-muted-foreground">UID</p>
                                        <p className="font-mono">{newCardUid}</p>
                                    </div>
                                    <div>
                                        <p className="font-medium text-muted-foreground">Status</p>
                                        <Badge className="bg-blue-100 text-blue-800">WILL ACTIVATE</Badge>
                                    </div>
                                    <div>
                                        <p className="font-medium text-muted-foreground">Issued</p>
                                        <p>{format(new Date(), 'MMM dd, yyyy')}</p>
                                    </div>
                                    <div>
                                        <p className="font-medium text-muted-foreground">Scans</p>
                                        <p>0</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Actions Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Actions Summary</CardTitle>
                                <CardDescription>
                                    The following actions will be performed immediately
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-start gap-3 p-3 bg-red-50 border border-red-200 rounded">
                                    <span className="text-xl">❌</span>
                                    <div>
                                        <p className="font-medium">Deactivate Old Badge (Immediate)</p>
                                        <p className="text-sm text-muted-foreground">
                                            Card UID {badge.card_uid} will be marked inactive
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3 p-3 bg-green-50 border border-green-200 rounded">
                                    <span className="text-xl">✅</span>
                                    <div>
                                        <p className="font-medium">Activate New Badge (Immediate)</p>
                                        <p className="text-sm text-muted-foreground">
                                            Card UID {newCardUid} will be active and scanned
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3 p-3 bg-slate-50 border border-slate-200 rounded">
                                    <span className="text-xl">📝</span>
                                    <div>
                                        <p className="font-medium">Log Replacement Reason</p>
                                        <p className="text-sm text-muted-foreground">
                                            Reason: {reasonOptions.find((r) => r.value === reason)?.label}
                                        </p>
                                    </div>
                                </div>

                                {replacementFee && (
                                    <div className="flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded">
                                        <span className="text-xl">💰</span>
                                        <div>
                                            <p className="font-medium">Process Replacement Fee</p>
                                            <p className="text-sm text-muted-foreground">
                                                Amount: ₱{replacementFee.toFixed(2)} (
                                                {deductFromPayroll ? 'Payroll Deduction' : 'Cash Payment'})
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {isLostOrStolen && (
                                    <div className="flex items-start gap-3 p-3 bg-orange-50 border border-orange-200 rounded">
                                        <span className="text-xl">🔔</span>
                                        <div>
                                            <p className="font-medium">Notify Security</p>
                                            <p className="text-sm text-muted-foreground">
                                                {securityNotified
                                                    ? 'Security has been notified'
                                                    : 'Security will be notified of the incident'}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Confirmation */}
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                Please review all information above before confirming. This action cannot be
                                undone.
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {/* Dialog Footer */}
                <DialogFooter className="flex gap-2 justify-between">
                    <div>
                        {currentStep > 1 && (
                            <Button
                                variant="outline"
                                onClick={handlePrevStep}
                                disabled={isSubmitting}
                            >
                                <ChevronLeft className="h-4 w-4 mr-2" />
                                Previous
                            </Button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        {currentStep < 3 ? (
                            <Button onClick={handleNextStep} disabled={isSubmitting}>
                                Next
                                <ChevronRight className="h-4 w-4 ml-2" />
                            </Button>
                        ) : (
                            <Button
                                onClick={handleConfirmReplacement}
                                disabled={isSubmitting}
                                className="bg-green-600 hover:bg-green-700"
                            >
                                {isSubmitting ? 'Processing...' : 'Confirm Replacement'}
                            </Button>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
