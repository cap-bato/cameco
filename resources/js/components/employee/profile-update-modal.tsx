import { useState } from 'react';
import { useForm } from '@inertiajs/react';
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
import { AlertCircle, Loader2 } from 'lucide-react';

interface ContactInfo {
    contact_number: string | null;
    email: string;
    address: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    country: string;
}

interface EmergencyContact {
    name: string;
    relationship: string;
    phone: string;
    address: string;
}

interface EmployeeData {
    contact_info: ContactInfo | null;
    emergency_contact: EmergencyContact | null;
}

interface ProfileUpdateModalProps {
    isOpen: boolean;
    onClose: () => void;
    employee: EmployeeData;
}

export function ProfileUpdateModal({ isOpen, onClose, employee }: ProfileUpdateModalProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        contact_number: employee.contact_info?.contact_number || '',
        email: employee.contact_info?.email || '',
        address: employee.contact_info?.address || '',
        city: employee.contact_info?.city || '',
        province: employee.contact_info?.province || '',
        postal_code: employee.contact_info?.postal_code || '',
        emergency_contact_name: employee.emergency_contact?.name || '',
        emergency_contact_relationship: employee.emergency_contact?.relationship || '',
        emergency_contact_phone: employee.emergency_contact?.phone || '',
        emergency_contact_address: employee.emergency_contact?.address || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/employee/profile/request-update', {
            onSuccess: () => {
                onClose();
                reset();
            },
        });
    };

    const handleClose = () => {
        if (!processing) {
            reset();
            onClose();
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Update Contact Information</DialogTitle>
                    <DialogDescription>
                        Submit a request to update your contact information. Changes require HR Staff approval.
                    </DialogDescription>
                </DialogHeader>

                {/* Warning Banner */}
                <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900 dark:bg-amber-900/10">
                    <div className="flex gap-3">
                        <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                HR Approval Required
                            </p>
                            <p className="text-sm text-amber-800 dark:text-amber-200">
                                All changes require HR Staff approval before being applied to your profile. 
                                You will be notified once your request is processed.
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Contact Information Section */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Contact Information</h3>
                        
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="contact_number">Contact Number</Label>
                                <Input
                                    id="contact_number"
                                    type="text"
                                    value={data.contact_number}
                                    onChange={(e) => setData('contact_number', e.target.value)}
                                    placeholder="+63 912 345 6789"
                                    disabled={processing}
                                />
                                {errors.contact_number && (
                                    <p className="text-sm text-destructive">{errors.contact_number}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">Email Address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="your.email@example.com"
                                    disabled={processing}
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">{errors.email}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="address">Home Address</Label>
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                placeholder="House No., Street Name, Barangay"
                                rows={3}
                                disabled={processing}
                            />
                            {errors.address && (
                                <p className="text-sm text-destructive">{errors.address}</p>
                            )}
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="city">City</Label>
                                <Input
                                    id="city"
                                    type="text"
                                    value={data.city}
                                    onChange={(e) => setData('city', e.target.value)}
                                    placeholder="Manila"
                                    disabled={processing}
                                />
                                {errors.city && (
                                    <p className="text-sm text-destructive">{errors.city}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="province">Province</Label>
                                <Input
                                    id="province"
                                    type="text"
                                    value={data.province}
                                    onChange={(e) => setData('province', e.target.value)}
                                    placeholder="Metro Manila"
                                    disabled={processing}
                                />
                                {errors.province && (
                                    <p className="text-sm text-destructive">{errors.province}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="postal_code">Postal Code</Label>
                                <Input
                                    id="postal_code"
                                    type="text"
                                    value={data.postal_code}
                                    onChange={(e) => setData('postal_code', e.target.value)}
                                    placeholder="1000"
                                    disabled={processing}
                                />
                                {errors.postal_code && (
                                    <p className="text-sm text-destructive">{errors.postal_code}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Emergency Contact Section */}
                    <div className="space-y-4 pt-4 border-t">
                        <h3 className="text-sm font-semibold">Emergency Contact</h3>
                        
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="emergency_contact_name">Contact Person Name</Label>
                                <Input
                                    id="emergency_contact_name"
                                    type="text"
                                    value={data.emergency_contact_name}
                                    onChange={(e) => setData('emergency_contact_name', e.target.value)}
                                    placeholder="John Doe"
                                    disabled={processing}
                                />
                                {errors.emergency_contact_name && (
                                    <p className="text-sm text-destructive">{errors.emergency_contact_name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="emergency_contact_relationship">Relationship</Label>
                                <Input
                                    id="emergency_contact_relationship"
                                    type="text"
                                    value={data.emergency_contact_relationship}
                                    onChange={(e) => setData('emergency_contact_relationship', e.target.value)}
                                    placeholder="Spouse, Parent, Sibling, etc."
                                    disabled={processing}
                                />
                                {errors.emergency_contact_relationship && (
                                    <p className="text-sm text-destructive">{errors.emergency_contact_relationship}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_phone">Contact Number</Label>
                            <Input
                                id="emergency_contact_phone"
                                type="text"
                                value={data.emergency_contact_phone}
                                onChange={(e) => setData('emergency_contact_phone', e.target.value)}
                                placeholder="+63 912 345 6789"
                                disabled={processing}
                            />
                            {errors.emergency_contact_phone && (
                                <p className="text-sm text-destructive">{errors.emergency_contact_phone}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_address">Address</Label>
                            <Textarea
                                id="emergency_contact_address"
                                value={data.emergency_contact_address}
                                onChange={(e) => setData('emergency_contact_address', e.target.value)}
                                placeholder="Complete address"
                                rows={3}
                                disabled={processing}
                            />
                            {errors.emergency_contact_address && (
                                <p className="text-sm text-destructive">{errors.emergency_contact_address}</p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Submit Request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
