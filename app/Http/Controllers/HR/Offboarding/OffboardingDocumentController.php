<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\OffboardingCase;
use App\Models\OffboardingDocument;
use App\Models\Employee;
use App\Models\ClearanceItem;
use App\Services\HR\OffboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facades\Pdf;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OffboardingDocumentController extends Controller
{
    protected OffboardingService $offboardingService;

    public function __construct(OffboardingService $offboardingService)
    {
        $this->offboardingService = $offboardingService;
    }

    /**
     * Generate clearance certificate PDF.
     * 
     * Validates all clearances are approved before generating.
     * Creates PDF with company letterhead and clearance checklist.
     */
    public function generateClearanceCertificate($caseId)
    {
        $case = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'clearanceItems',
            'hrCoordinator',
        ])->findOrFail($caseId);

        try {
            DB::beginTransaction();

            // Validate all clearances are approved
            $pendingClearances = $case->clearanceItems()
                ->where('status', '!=', 'approved')
                ->where('status', '!=', 'waived')
                ->count();

            if ($pendingClearances > 0) {
                Log::warning('Cannot generate clearance certificate with pending clearances', [
                    'case_id' => $caseId,
                    'pending_count' => $pendingClearances,
                ]);
                return redirect()->back()->with('error', 'All clearances must be approved before generating certificate.');
            }

            // Prepare data for PDF
            $data = [
                'case_number' => $case->case_number,
                'employee_name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                'employee_number' => $case->employee->employee_number,
                'department' => $case->employee->department?->name,
                'last_working_day' => $case->last_working_day->format('F d, Y'),
                'separation_type' => ucfirst(str_replace('_', ' ', $case->separation_type)),
                'separation_reason' => $case->separation_reason,
                'hr_coordinator' => $case->hrCoordinator?->name ?? 'HR Department',
                'current_date' => now()->format('F d, Y'),
                'clearance_items' => $case->clearanceItems->map(fn($item) => [
                    'category' => $item->category,
                    'description' => $item->description,
                    'status' => $item->status,
                    'approved_by' => $item->approvedBy?->name,
                ]),
            ];

            // Generate PDF
            $pdf = Pdf::loadView('HR.Offboarding.Documents.ClearanceCertificate', $data);
            $pdfContent = $pdf->output();

            // Store PDF file
            $fileName = "offboarding/{$case->case_number}/clearance-certificate.pdf";
            Storage::disk('private')->put($fileName, $pdfContent);

            // Get file size
            $fileSize = strlen($pdfContent);

            // Create or update document record
            $document = OffboardingDocument::updateOrCreate(
                [
                    'offboarding_case_id' => $case->id,
                    'document_type' => 'clearance_certificate',
                ],
                [
                    'employee_id' => $case->employee_id,
                    'document_name' => 'Clearance Certificate',
                    'file_path' => $fileName,
                    'generated_by_system' => true,
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'file_size' => $fileSize,
                    'mime_type' => 'application/pdf',
                ]
            );

            DB::commit();

            Log::info('Clearance certificate generated', [
                'case_id' => $caseId,
                'document_id' => $document->id,
                'file_size' => $fileSize,
            ]);

            return redirect()->back()->with('success', 'Clearance certificate generated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate clearance certificate', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate clearance certificate.');
        }
    }

    /**
     * Generate certificate of employment for separated employee.
     * 
     * Includes employment period, position, separation details.
     */
    public function generateCOE($caseId)
    {
        $case = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'employee.position',
            'hrCoordinator',
        ])->findOrFail($caseId);

        try {
            DB::beginTransaction();

            // Prepare data for PDF
            $employmentPeriod = $case->employee->date_hired
                ? $case->employee->date_hired->diff($case->last_working_day)
                : null;

            $data = [
                'employee_name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                'employee_number' => $case->employee->employee_number,
                'position' => $case->employee->position?->title ?? 'Employee',
                'department' => $case->employee->department?->name,
                'date_hired' => $case->employee->date_hired?->format('F d, Y'),
                'last_working_day' => $case->last_working_day->format('F d, Y'),
                'employment_years' => $employmentPeriod?->y ?? 0,
                'employment_months' => $employmentPeriod?->m ?? 0,
                'employment_days' => $employmentPeriod?->d ?? 0,
                'separation_type' => ucfirst(str_replace('_', ' ', $case->separation_type)),
                'separation_reason' => $case->separation_reason,
                'rehire_eligible' => $case->rehire_eligible,
                'rehire_note' => $case->rehire_eligible_reason,
                'current_date' => now()->format('F d, Y'),
                'issued_by' => $case->hrCoordinator?->name ?? 'HR Department',
            ];

            // Generate PDF
            $pdf = Pdf::loadView('HR.Offboarding.Documents.CertificateOfEmployment', $data);
            $pdfContent = $pdf->output();

            // Store PDF file
            $fileName = "offboarding/{$case->case_number}/certificate-of-employment.pdf";
            Storage::disk('private')->put($fileName, $pdfContent);

            $fileSize = strlen($pdfContent);

            // Create or update document record
            $document = OffboardingDocument::updateOrCreate(
                [
                    'offboarding_case_id' => $case->id,
                    'document_type' => 'certificate_of_employment',
                ],
                [
                    'employee_id' => $case->employee_id,
                    'document_name' => 'Certificate of Employment',
                    'file_path' => $fileName,
                    'generated_by_system' => true,
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'file_size' => $fileSize,
                    'mime_type' => 'application/pdf',
                ]
            );

            DB::commit();

            Log::info('Certificate of employment generated', [
                'case_id' => $caseId,
                'document_id' => $document->id,
            ]);

            return redirect()->back()->with('success', 'Certificate of employment generated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate certificate of employment', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate certificate of employment.');
        }
    }

    /**
     * Generate final pay computation document.
     * 
     * Calculates last salary, unused leave credits, thirteenth month pay.
     * Deducts loans, asset liabilities.
     * Generates detailed breakdown for finance.
     */
    public function generateFinalPay($caseId)
    {
        $case = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'employee.employeePayrollInfo',
        ])->findOrFail($caseId);

        try {
            DB::beginTransaction();

            // Calculate final pay components
            $monthlyBaseSalary = $case->employee->employeePayrollInfo?->monthly_salary ?? 0;
            $daysWorked = max(1, $case->employee->date_hired->diffInDays($case->last_working_day));
            $totalDaysInYear = 365;
            
            // Calculate pro-rata salary for worked days
            $proRataSalary = ($monthlyBaseSalary / 30) * min(30, ceil($daysWorked % 30));

            // Get unused leave balance
            $leaveBalance = DB::table('leave_balances')
                ->where('employee_id', $case->employee_id)
                ->where('year', now()->year)
                ->sum('remaining');

            $leaveValue = $leaveBalance > 0 ? ($monthlyBaseSalary / 22) * $leaveBalance : 0;

            // Calculate thirteenth month pay (1/12 of annual salary)
            $thirteenthMonth = $monthlyBaseSalary / 12;

            // Get asset liabilities
            $assetLiability = DB::table('company_assets')
                ->where('employee_id', $case->employee_id)
                ->where('offboarding_case_id', $case->id)
                ->where('deducted_from_final_pay', true)
                ->sum('liability_amount');

            // Get loan deductions
            $loanDeduction = DB::table('employee_loans')
                ->where('employee_id', $case->employee_id)
                ->where('status', 'active')
                ->sum('remaining_balance');

            // Calculate totals
            $grossAmount = $proRataSalary + $leaveValue + $thirteenthMonth;
            $totalDeductions = $assetLiability + $loanDeduction;
            $netAmount = max(0, $grossAmount - $totalDeductions);

            $data = [
                'case_number' => $case->case_number,
                'employee_name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                'employee_number' => $case->employee->employee_number,
                'department' => $case->employee->department?->name,
                'position' => $case->employee->position?->title ?? 'Employee',
                'last_working_day' => $case->last_working_day->format('F d, Y'),
                'separation_type' => ucfirst(str_replace('_', ' ', $case->separation_type)),
                
                // Breakdown
                'pro_rata_salary' => round($proRataSalary, 2),
                'leave_value' => round($leaveValue, 2),
                'thirteenth_month' => round($thirteenthMonth, 2),
                'gross_amount' => round($grossAmount, 2),
                
                // Deductions
                'asset_liability' => round($assetLiability, 2),
                'loan_deduction' => round($loanDeduction, 2),
                'total_deductions' => round($totalDeductions, 2),
                
                // Net
                'net_amount' => round($netAmount, 2),
                'current_date' => now()->format('F d, Y'),
            ];

            // Generate PDF
            $pdf = Pdf::loadView('HR.Offboarding.Documents.FinalPayComputation', $data);
            $pdfContent = $pdf->output();

            // Store PDF file
            $fileName = "offboarding/{$case->case_number}/final-pay-computation.pdf";
            Storage::disk('private')->put($fileName, $pdfContent);

            $fileSize = strlen($pdfContent);

            // Create or update document record
            $document = OffboardingDocument::updateOrCreate(
                [
                    'offboarding_case_id' => $case->id,
                    'document_type' => 'final_pay_computation',
                ],
                [
                    'employee_id' => $case->employee_id,
                    'document_name' => 'Final Pay Computation',
                    'file_path' => $fileName,
                    'generated_by_system' => true,
                    'status' => 'pending_approval',
                    'file_size' => $fileSize,
                    'mime_type' => 'application/pdf',
                ]
            );

            // Mark case as final pay computed
            $case->update(['final_pay_computed' => true]);

            DB::commit();

            Log::info('Final pay computation generated', [
                'case_id' => $caseId,
                'document_id' => $document->id,
                'net_amount' => $netAmount,
            ]);

            return redirect()->back()->with('success', 'Final pay computation generated. Please review and approve.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to generate final pay computation', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate final pay computation.');
        }
    }

    /**
     * Upload document to offboarding case (resignation, termination, etc).
     * 
     * Validates file type and size.
     * Stores with metadata in private disk.
     */
    public function upload(Request $request, $caseId)
    {
        $case = OffboardingCase::findOrFail($caseId);

        $validated = $request->validate([
            'document_type' => 'required|in:resignation_letter,termination_letter,employee_request,other',
            'document_name' => 'required|string|max:200',
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        try {
            DB::beginTransaction();

            // Store file
            $filePath = $request->file('file')->store(
                "offboarding/{$case->case_number}",
                'private'
            );

            $mimeType = $request->file('file')->getMimeType();
            $fileSize = $request->file('file')->getSize();

            // Create document record
            $document = OffboardingDocument::create([
                'offboarding_case_id' => $case->id,
                'employee_id' => $case->employee_id,
                'document_type' => $validated['document_type'],
                'document_name' => $validated['document_name'],
                'file_path' => $filePath,
                'generated_by_system' => false,
                'uploaded_by' => auth()->id(),
                'status' => 'uploaded',
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
            ]);

            DB::commit();

            Log::info('Document uploaded to offboarding case', [
                'case_id' => $caseId,
                'document_id' => $document->id,
                'document_type' => $validated['document_type'],
            ]);

            return redirect()->back()->with('success', 'Document uploaded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to upload document', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to upload document.');
        }
    }

    /**
     * Download document with authorization check.
     * 
     * Validates user has permission.
     * Streams file to user.
     * Logs download for audit.
     */
    public function download($documentId)
    {
        $document = OffboardingDocument::findOrFail($documentId);

        // Check authorization
        if (!auth()->user()->hasPermissionTo('hr.offboarding.documents.view')) {
            Log::warning('Unauthorized document access attempted', [
                'user_id' => auth()->id(),
                'document_id' => $documentId,
            ]);
            abort(403, 'Unauthorized');
        }

        try {
            // Check file exists
            if (!Storage::disk('private')->exists($document->file_path)) {
                Log::error('Document file not found', [
                    'document_id' => $documentId,
                    'file_path' => $document->file_path,
                ]);
                abort(404, 'Document file not found');
            }

            Log::info('Document downloaded', [
                'document_id' => $documentId,
                'user_id' => auth()->id(),
                'document_type' => $document->document_type,
            ]);

            $path = Storage::disk('private')->path($document->file_path);
            $fileName = basename($document->file_path);

            return response()->download($path, $document->document_name . '.' . pathinfo($fileName, PATHINFO_EXTENSION));

        } catch (\Exception $e) {
            Log::error('Failed to download document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to download document.');
        }
    }

    /**
     * Approve a document (typically final pay computation).
     */
    public function approve(Request $request, $documentId)
    {
        $document = OffboardingDocument::findOrFail($documentId);

        if (!auth()->user()->hasPermissionTo('hr.offboarding.documents.approve')) {
            abort(403, 'Unauthorized');
        }

        try {
            $document->approve(auth()->user());

            Log::info('Document approved', [
                'document_id' => $documentId,
                'approved_by' => auth()->id(),
            ]);

            return redirect()->back()->with('success', 'Document approved successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to approve document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to approve document.');
        }
    }
}
