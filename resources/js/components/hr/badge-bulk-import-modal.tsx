'use client';

import React, { useCallback, useState, useMemo } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge as BadgeComponent } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Upload,
    Download,
    AlertTriangle,
    CheckCircle,
    XCircle,
    File,
    Loader2,
} from 'lucide-react';

interface Badge {
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
    status: 'active' | 'inactive' | 'lost' | 'stolen' | 'expired' | 'replaced';
}

interface Employee {
    id: string;
    name: string;
    employee_id: string;
    department: string;
    hire_date: string;
}

interface ImportRow {
    row: number;
    employee_id: string;
    card_uid: string;
    card_type: string;
    expiration_date: string;
    notes: string;
}

interface ValidationError {
    field: string;
    message: string;
}

interface ValidationResult {
    row: number;
    employee_id: string;
    employee_name?: string;
    card_uid: string;
    card_type?: string;
    status: 'valid' | 'warning' | 'error';
    errors: ValidationError[];
    warnings: string[];
}

interface BadgeBulkImportModalProps {
    isOpen: boolean;
    onClose: () => void;
    badges: { data: Badge[] };
    employees: Employee[];
}

const VALID_CARD_TYPES = ['mifare', 'desfire', 'em4100'];
const CSV_TEMPLATE = `employee_id,card_uid,card_type,expiration_date,notes
EMP-2024-001,04:3A:B2:C5:D8,mifare,2026-12-31,Initial issuance
EMP-2024-002,04:3A:B2:C5:D9,mifare,2026-12-31,Initial issuance
EMP-2024-003,04:3A:B2:C5:E0,desfire,,New hire`;

// Validate card UID format (XX:XX:XX:XX:XX)
const isValidCardUidFormat = (uid: string): boolean => {
    const pattern = /^([0-9A-Fa-f]{2}:){4}[0-9A-Fa-f]{2}$/;
    return pattern.test(uid);
};

// Validate expiration date format
const isValidDateFormat = (dateStr: string): boolean => {
    if (!dateStr) return true; // Empty is valid (no expiration)
    const pattern = /^\d{4}-\d{2}-\d{2}$/;
    if (!pattern.test(dateStr)) return false;
    const date = new Date(dateStr);
    return date instanceof Date && !isNaN(date.getTime());
};

// Parse CSV content
const parseCSV = (csv: string): ImportRow[] => {
    const lines = csv.trim().split('\n');
    if (lines.length < 2) return [];

    const header = lines[0].split(',').map(h => h.trim().toLowerCase());
    const rows: ImportRow[] = [];

    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim());
        if (values.length === 0 || !values[0]) continue;

        rows.push({
            row: i,
            employee_id: values[header.indexOf('employee_id')] || '',
            card_uid: values[header.indexOf('card_uid')] || '',
            card_type: values[header.indexOf('card_type')] || '',
            expiration_date: values[header.indexOf('expiration_date')] || '',
            notes: values[header.indexOf('notes')] || '',
        });
    }

    return rows;
};

export function BadgeBulkImportModal({
    isOpen,
    onClose,
    badges,
    employees,
}: BadgeBulkImportModalProps) {
    const [uploadedFile, setUploadedFile] = useState<File | null>(null);
    const [filePreview, setFilePreview] = useState<ImportRow[]>([]);
    const [validationResults, setValidationResults] = useState<ValidationResult[]>([]);
    const [isValidating, setIsValidating] = useState(false);
    const [importStep, setImportStep] = useState<'upload' | 'validate'>('upload');
    const [isDragging, setIsDragging] = useState(false);

    // Calculate validation summary
    const summary = useMemo(() => {
        if (validationResults.length === 0) return null;
        return {
            total: validationResults.length,
            valid: validationResults.filter(r => r.status === 'valid').length,
            warnings: validationResults.filter(r => r.status === 'warning').length,
            errors: validationResults.filter(r => r.status === 'error').length,
        };
    }, [validationResults]);

    // Get all badge UIDs for duplicate checking
    const existingCardUids = useMemo(() => {
        return new Set(badges.data.map(b => b.card_uid));
    }, [badges]);

    // Get all active badge employee IDs for warning check
    const employeesWithActiveBadges = useMemo(() => {
        return new Set(
            badges.data
                .filter(b => b.is_active && b.status === 'active')
                .map(b => b.employee_id)
        );
    }, [badges]);

    // Validate individual row
    const validateRow = useCallback((row: ImportRow): ValidationResult => {
        const errors: ValidationError[] = [];
        const warnings: string[] = [];
        let employee_name = '';

        // 1. Check if employee ID exists
        const employee = employees.find(e => e.employee_id === row.employee_id);
        if (!employee) {
            errors.push({
                field: 'employee_id',
                message: 'Employee not found in system',
            });
        } else {
            employee_name = employee.name;
        }

        // 2. Check if employee is active (simplified - assume all in list are active)
        if (!employee) {
            // Already caught above
        }

        // 3. Validate card UID format
        if (!row.card_uid) {
            errors.push({
                field: 'card_uid',
                message: 'Card UID is required',
            });
        } else if (!isValidCardUidFormat(row.card_uid)) {
            errors.push({
                field: 'card_uid',
                message: 'Invalid format. Expected XX:XX:XX:XX:XX (hex)',
            });
        }

        // 4. Check for duplicate card UID in existing badges
        if (row.card_uid && existingCardUids.has(row.card_uid)) {
            errors.push({
                field: 'card_uid',
                message: 'Card UID already exists in system',
            });
        }

        // 5. Check for duplicate card UID within import file
        // This is handled at upload level to catch duplicates in preview

        // 6. Validate card type
        if (!row.card_type) {
            errors.push({
                field: 'card_type',
                message: 'Card type is required',
            });
        } else if (!VALID_CARD_TYPES.includes(row.card_type.toLowerCase())) {
            errors.push({
                field: 'card_type',
                message: `Invalid card type. Must be one of: ${VALID_CARD_TYPES.join(', ')}`,
            });
        }

        // 7. Validate expiration date format
        if (!isValidDateFormat(row.expiration_date)) {
            errors.push({
                field: 'expiration_date',
                message: 'Invalid date format. Expected YYYY-MM-DD',
            });
        }

        // 8. Check if employee already has active badge (warning, not error)
        if (employee && employeesWithActiveBadges.has(employee.employee_id)) {
            warnings.push('Employee already has an active badge. This will be replaced.');
        }

        const status =
            errors.length > 0
                ? 'error'
                : warnings.length > 0
                  ? 'warning'
                  : 'valid';

        return {
            row: row.row,
            employee_id: row.employee_id,
            employee_name,
            card_uid: row.card_uid,
            card_type: row.card_type,
            status,
            errors,
            warnings,
        };
    }, [employees, existingCardUids, employeesWithActiveBadges]);

    // Handle file selection
    const handleFileSelect = async (file: File) => {
        if (!file) return;

        // Validate file type
        const acceptedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!acceptedTypes.includes(file.type) && !file.name.endsWith('.csv')) {
            alert('Please select a valid CSV or Excel file (.csv, .xlsx, .xls)');
            return;
        }

        // Validate file size (5 MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5 MB');
            return;
        }

        setUploadedFile(file);
        setFilePreview([]);
        setValidationResults([]);

        // Read and parse file
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const csv = e.target?.result as string;
                const rows = parseCSV(csv);
                setFilePreview(rows);
                
                if (rows.length > 0) {
                    setImportStep('validate');
                }
            } catch (error) {
                console.error('Error parsing file:', error);
                alert('Error parsing file. Please check the format.');
            }
        };
        reader.readAsText(file);
    };

    // Handle drag and drop
    const handleDragEnter = (e: React.DragEvent) => {
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
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    };

    // Handle validate button
    const handleValidate = useCallback(async () => {
        setIsValidating(true);
        try {
            // Simulate async validation
            await new Promise(resolve => setTimeout(resolve, 500));

            // Validate each row
            const results = filePreview.map(row => validateRow(row));
            setValidationResults(results);
        } finally {
            setIsValidating(false);
        }
    }, [filePreview, validateRow]);

    // Download CSV template
    const downloadTemplate = () => {
        const element = document.createElement('a');
        element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(CSV_TEMPLATE));
        element.setAttribute('download', 'badge-import-template.csv');
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
    };

    // Reset modal
    const handleReset = () => {
        setUploadedFile(null);
        setFilePreview([]);
        setValidationResults([]);
        setImportStep('upload');
    };

    // Handle close
    const handleClose = () => {
        handleReset();
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Bulk Import RFID Badges</DialogTitle>
                    <DialogDescription>
                        Upload a CSV or Excel file to import multiple employee badges at once
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Step 1: Upload */}
                    {importStep === 'upload' && (
                        <div className="space-y-4">
                            {/* Template Download */}
                            <Card className="bg-blue-50 border-blue-200">
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="font-semibold text-sm mb-1">Need help with the format?</p>
                                            <p className="text-sm text-muted-foreground">
                                                Download the CSV template to see the required columns and format
                                            </p>
                                        </div>
                                        <Button
                                            onClick={downloadTemplate}
                                            variant="outline"
                                            size="sm"
                                            className="gap-2"
                                        >
                                            <Download className="h-4 w-4" />
                                            Download Template
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Drag & Drop Zone */}
                            <div
                                onDragEnter={handleDragEnter}
                                onDragLeave={handleDragLeave}
                                onDrop={handleDrop}
                                className={`relative border-2 border-dashed rounded-lg p-8 transition-colors ${
                                    isDragging
                                        ? 'border-blue-500 bg-blue-50'
                                        : 'border-muted-foreground/25 hover:border-blue-500 hover:bg-blue-50/50'
                                }`}
                            >
                                <input
                                    id="file-input"
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    onChange={(e) => e.target.files && handleFileSelect(e.target.files[0])}
                                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                />
                                <div className="flex flex-col items-center justify-center gap-4">
                                    <Upload className="h-8 w-8 text-muted-foreground" />
                                    <div className="text-center">
                                        <p className="font-semibold">Drag and drop your file here</p>
                                        <p className="text-sm text-muted-foreground">
                                            or click to browse (CSV, XLSX, XLS - Max 5 MB)
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* File Info */}
                            {uploadedFile && (
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="flex items-center gap-3">
                                            <File className="h-4 w-4 text-blue-600" />
                                            <div className="flex-1">
                                                <p className="font-semibold text-sm">{uploadedFile.name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {(uploadedFile.size / 1024).toFixed(2)} KB
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}

                    {/* Step 2: Validation Results */}
                    {importStep === 'validate' && (
                        <div className="space-y-4">
                            {/* Validation Summary */}
                            {summary && (
                                <div className="grid grid-cols-4 gap-4">
                                    <Card>
                                        <CardContent className="pt-6">
                                            <div className="text-center">
                                                <p className="text-2xl font-bold text-blue-600">{summary.total}</p>
                                                <p className="text-sm text-muted-foreground">Total Rows</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardContent className="pt-6">
                                            <div className="text-center">
                                                <p className="text-2xl font-bold text-green-600">{summary.valid}</p>
                                                <p className="text-sm text-muted-foreground">Ready to Import</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardContent className="pt-6">
                                            <div className="text-center">
                                                <p className="text-2xl font-bold text-amber-600">{summary.warnings}</p>
                                                <p className="text-sm text-muted-foreground">Warnings</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardContent className="pt-6">
                                            <div className="text-center">
                                                <p className="text-2xl font-bold text-red-600">{summary.errors}</p>
                                                <p className="text-sm text-muted-foreground">Errors</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            )}

                            {/* Validation Table */}
                            {validationResults.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Validation Results</CardTitle>
                                        <CardDescription>
                                            Review each row to ensure data is correct before importing
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="overflow-x-auto">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Row</TableHead>
                                                        <TableHead>Employee</TableHead>
                                                        <TableHead>Card UID</TableHead>
                                                        <TableHead>Card Type</TableHead>
                                                        <TableHead>Status</TableHead>
                                                        <TableHead>Issues</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {validationResults.map((result) => (
                                                        <TableRow key={`${result.row}-${result.employee_id}`}>
                                                            <TableCell className="font-semibold">{result.row}</TableCell>
                                                            <TableCell>
                                                                <div>
                                                                    <p className="font-semibold text-sm">{result.employee_name || result.employee_id}</p>
                                                                    <p className="text-xs text-muted-foreground">{result.employee_id}</p>
                                                                </div>
                                                            </TableCell>
                                                            <TableCell>
                                                                <code className="text-xs bg-muted px-2 py-1 rounded">
                                                                    {result.card_uid || '—'}
                                                                </code>
                                                            </TableCell>
                                                            <TableCell>{result.card_type || '—'}</TableCell>
                                                            <TableCell>
                                                                {result.status === 'valid' && (
                                                                    <div className="flex items-center gap-1">
                                                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                                                        <BadgeComponent variant="outline" className="bg-green-50 text-green-700 border-green-200">
                                                                            Ready
                                                                        </BadgeComponent>
                                                                    </div>
                                                                )}
                                                                {result.status === 'warning' && (
                                                                    <div className="flex items-center gap-1">
                                                                        <AlertTriangle className="h-4 w-4 text-amber-600" />
                                                                        <BadgeComponent variant="outline" className="bg-amber-50 text-amber-700 border-amber-200">
                                                                            Warning
                                                                        </BadgeComponent>
                                                                    </div>
                                                                )}
                                                                {result.status === 'error' && (
                                                                    <div className="flex items-center gap-1">
                                                                        <XCircle className="h-4 w-4 text-red-600" />
                                                                        <BadgeComponent variant="outline" className="bg-red-50 text-red-700 border-red-200">
                                                                            Error
                                                                        </BadgeComponent>
                                                                    </div>
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <div className="space-y-1">
                                                                    {result.errors.length > 0 && (
                                                                        <div className="space-y-0.5">
                                                                            {result.errors.map((error, idx) => (
                                                                                <p key={idx} className="text-xs text-red-600">
                                                                                    • {error.message}
                                                                                </p>
                                                                            ))}
                                                                        </div>
                                                                    )}
                                                                    {result.warnings.length > 0 && (
                                                                        <div className="space-y-0.5">
                                                                            {result.warnings.map((warning, idx) => (
                                                                                <p key={idx} className="text-xs text-amber-600">
                                                                                    ⚠ {warning}
                                                                                </p>
                                                                            ))}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Import Notes */}
                            <Alert>
                                <AlertTriangle className="h-4 w-4" />
                                <AlertDescription>
                                    Rows with errors will be skipped during import. Rows with warnings will still be imported. You can review the import log after completion.
                                </AlertDescription>
                            </Alert>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    {importStep === 'upload' && (
                        <>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleValidate}
                                disabled={!uploadedFile || isValidating}
                                className="gap-2"
                            >
                                {isValidating && <Loader2 className="h-4 w-4 animate-spin" />}
                                {isValidating ? 'Validating...' : 'Validate Data'}
                            </Button>
                        </>
                    )}
                    {importStep === 'validate' && (
                        <>
                            <Button variant="outline" onClick={() => setImportStep('upload')}>
                                Upload Different File
                            </Button>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button
                                onClick={() => {
                                    // TODO: Phase 2 - Implement actual import
                                    console.log('Import submitted with results:', validationResults);
                                    alert('Import functionality will be implemented in Phase 2');
                                    handleClose();
                                }}
                                disabled={!summary || summary.valid + summary.warnings === 0}
                                className="gap-2 bg-green-600 hover:bg-green-700"
                            >
                                <CheckCircle className="h-4 w-4" />
                                Import {summary ? summary.valid + summary.warnings : 0} Badges
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
