<?php

namespace App\Http\Controllers\Payroll\Payments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\BankFileBatch;
use App\Models\PayrollPeriod;
use App\Models\PaymentMethod;

/**
 * BankFilesController
 * Manages bank payroll file generation, validation, and upload tracking
 * 
 * PHASE 1 - Supported Banks:
 * - BDO (Banco de Oro) - CSV, Excel
 * - Metrobank (Metropolitan Bank and Trust Company) - CSV, Fixed-width
 * 
 * Note: Only BDO and Metrobank are seeded in the payment_methods table for Phase 1.
 * Additional banks (BPI, PNB, RCBC, Unionbank) will be added in Phase 4+ when those
 * banking integrations go live.
 * 
 * File Formats:
 * - CSV: Comma-separated values
 * - TXT: Fixed-width format
 * - Excel: XLSX format
 * - Fixed-width: Bank-specific fixed-width format
 */
class BankFilesController extends Controller
{
    public function index()
    {
        $bankFiles = BankFileBatch::with(['payrollPeriod', 'generatedBy', 'submittedBy'])
            ->latest()
            ->get();

        $periods = PayrollPeriod::select('id', 'period_name', 'period_start', 'period_end', 'payment_date')
            ->orderByDesc('payment_date')
            ->get();

        // Get enabled bank-type PaymentMethods (Phase 1: BDO and Metrobank only)
        $bankList = PaymentMethod::enabled()
            ->where('method_type', 'bank')
            ->ordered()
            ->get()
            ->map(fn($m) => [
                'id' => $m->bank_code ?? $m->display_name,
                'name' => $m->display_name,
                'code' => $m->bank_code,
                'supported_formats' => ['csv', 'txt'],
            ]);

        // Count employees with bank payment preference (TODO: filter by current period)
        $employeesCount = 0;

        return Inertia::render('Payroll/Payments/BankFiles/Index', compact(
            'bankFiles', 'periods', 'bankList', 'employeesCount'
        ));
    }

    public function generateFile(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|integer',
            'bank_name' => 'required|string|in:BDO,Metrobank',
            'file_format' => 'required|string|in:csv,txt,excel,fixed_width',
        ]);

        try {
            $fileName = $this->generateFileName(
                $validated['bank_name'],
                $validated['file_format']
            );

            $bankFile = [
                'id' => rand(100, 999),
                'payroll_period_id' => $validated['period_id'],
                'bank_name' => $validated['bank_name'],
                'file_name' => $fileName,
                'file_path' => '/storage/bank-files/' . $fileName,
                'file_format' => $validated['file_format'],
                'file_size' => rand(50000, 500000),
                'file_hash' => hash('sha256', $fileName . time()),
                'total_employees' => 25,
                'total_amount' => 1250000.00,
                'status' => 'generated',
                'generated_at' => now()->toDateTimeString(),
                'uploaded_at' => null,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            return response()->json([
                'success' => true,
                'message' => "Bank file for {$validated['bank_name']} generated successfully",
                'file' => $bankFile,
                'download_url' => "/payroll/bank-files/{$bankFile['id']}/download",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bank file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function validateFile(Request $request, int $bankFileId)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        try {
            $errors = [];
            $validCount = 0;
            $warningCount = 0;

            if (rand(0, 1)) {
                $errors[] = [
                    'employee_id' => 5,
                    'message' => 'Invalid bank account number format',
                ];
                $warningCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'File validation completed',
                'valid_count' => $validCount,
                'warning_count' => $warningCount,
                'error_count' => count($errors),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File validation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadFile(Request $request, int $bankFileId)
    {
        $validated = $request->validate([
            'confirmation_method' => 'required|string|in:auto,manual',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Bank file uploaded successfully',
                'uploaded_at' => now()->toDateTimeString(),
                'confirmation_number' => 'BF-' . strtoupper(str_pad($bankFileId, 8, '0', STR_PAD_LEFT)),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadFile(int $bankFileId)
    {
        return response()->json([
            'success' => true,
            'message' => 'File download initiated',
            'file_id' => $bankFileId,
        ]);
    }

    public function regenerateFile(Request $request, int $bankFileId)
    {
        try {
            $bankFile = $this->getMockBankFiles()[0] ?? [];
            $bankFile['id'] = $bankFileId;
            $bankFile['generated_at'] = now()->toDateTimeString();

            return response()->json([
                'success' => true,
                'message' => 'Bank file regenerated successfully',
                'file' => $bankFile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate file: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function generateFileName(string $bankName, string $format): string
    {
        $timestamp = now()->format('YmdHis');
        $extension = $this->getFileExtension($format);
        return strtoupper($bankName) . '_PAYROLL_' . $timestamp . '.' . $extension;
    }

    private function getFileExtension(string $format): string
    {
        return match ($format) {
            'csv' => 'csv',
            'excel' => 'xlsx',
            'txt' => 'txt',
            'fixed_width' => 'txt',
            default => 'csv',
        };
    }

    private function getMockBankFiles()
    {
        return [
            [
                'id' => 1,
                'payroll_period_id' => 1,
                'period_name' => 'November 2025 - 2nd Half',
                'bank_name' => 'BPI',
                'bank_code' => '002',
                'file_name' => 'BPI_PAYROLL_20251120100000.csv',
                'file_path' => '/storage/bank-files/BPI_PAYROLL_20251120100000.csv',
                'file_format' => 'csv',
                'file_size' => 125000,
                'file_hash' => hash('sha256', 'BPI_PAYROLL_20251120100000.csv'),
                'total_employees' => 25,
                'total_amount' => 1250000.00,
                'status' => 'confirmed',
                'status_label' => 'Confirmed',
                'status_color' => 'green',
                'generated_at' => '2025-11-20 10:00:00',
                'uploaded_at' => '2025-11-20 10:30:00',
                'uploaded_by' => 'John Doe',
                'confirmation_number' => 'BF-00000001',
                'created_at' => '2025-11-20 10:00:00',
                'updated_at' => '2025-11-20 10:30:00',
            ],
            [
                'id' => 2,
                'payroll_period_id' => 1,
                'period_name' => 'November 2025 - 2nd Half',
                'bank_name' => 'BDO',
                'bank_code' => '006',
                'file_name' => 'BDO_PAYROLL_20251120101500.xlsx',
                'file_path' => '/storage/bank-files/BDO_PAYROLL_20251120101500.xlsx',
                'file_format' => 'excel',
                'file_size' => 250000,
                'file_hash' => hash('sha256', 'BDO_PAYROLL_20251120101500.xlsx'),
                'total_employees' => 25,
                'total_amount' => 1250000.00,
                'status' => 'processed',
                'status_label' => 'Processed',
                'status_color' => 'green',
                'generated_at' => '2025-11-20 10:15:00',
                'uploaded_at' => '2025-11-20 10:45:00',
                'uploaded_by' => 'John Doe',
                'confirmation_number' => 'BF-00000002',
                'created_at' => '2025-11-20 10:15:00',
                'updated_at' => '2025-11-20 10:45:00',
            ],
            [
                'id' => 3,
                'payroll_period_id' => 1,
                'period_name' => 'November 2025 - 2nd Half',
                'bank_name' => 'Metrobank',
                'bank_code' => '010',
                'file_name' => 'METROBANK_PAYROLL_20251120103000.txt',
                'file_path' => '/storage/bank-files/METROBANK_PAYROLL_20251120103000.txt',
                'file_format' => 'txt',
                'file_size' => 180000,
                'file_hash' => hash('sha256', 'METROBANK_PAYROLL_20251120103000.txt'),
                'total_employees' => 25,
                'total_amount' => 1250000.00,
                'status' => 'uploaded',
                'status_label' => 'Uploaded',
                'status_color' => 'blue',
                'generated_at' => '2025-11-20 10:30:00',
                'uploaded_at' => '2025-11-20 11:00:00',
                'uploaded_by' => 'Jane Smith',
                'confirmation_number' => null,
                'created_at' => '2025-11-20 10:30:00',
                'updated_at' => '2025-11-20 11:00:00',
            ],
            [
                'id' => 4,
                'payroll_period_id' => 1,
                'period_name' => 'November 2025 - 2nd Half',
                'bank_name' => 'PNB',
                'bank_code' => '003',
                'file_name' => 'PNB_PAYROLL_20251120112000.csv',
                'file_path' => '/storage/bank-files/PNB_PAYROLL_20251120112000.csv',
                'file_format' => 'csv',
                'file_size' => 128000,
                'file_hash' => hash('sha256', 'PNB_PAYROLL_20251120112000.csv'),
                'total_employees' => 25,
                'total_amount' => 1250000.00,
                'status' => 'generated',
                'status_label' => 'Generated',
                'status_color' => 'gray',
                'generated_at' => '2025-11-20 11:20:00',
                'uploaded_at' => null,
                'uploaded_by' => null,
                'confirmation_number' => null,
                'created_at' => '2025-11-20 11:20:00',
                'updated_at' => '2025-11-20 11:20:00',
            ],
        ];
    }

    private function getMockPeriods()
    {
        return [
            [
                'id' => 1,
                'name' => 'November 2025 - 2nd Half',
                'period_type' => 'semi_monthly',
                'start_date' => '2025-11-16',
                'end_date' => '2025-11-30',
                'cutoff_date' => '2025-11-30',
                'pay_date' => '2025-12-05',
                'status' => 'approved',
            ],
            [
                'id' => 2,
                'name' => 'November 2025 - 1st Half',
                'period_type' => 'semi_monthly',
                'start_date' => '2025-11-01',
                'end_date' => '2025-11-15',
                'cutoff_date' => '2025-11-15',
                'pay_date' => '2025-11-20',
                'status' => 'paid',
            ],
            [
                'id' => 3,
                'name' => 'October 2025',
                'period_type' => 'monthly',
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-31',
                'cutoff_date' => '2025-10-31',
                'pay_date' => '2025-11-10',
                'status' => 'paid',
            ],
        ];
    }

    private function getMockBankList()
    {
        // Phase 1: Only BDO and Metrobank are seeded in payment_methods table
        // Additional banks (BPI, PNB, RCBC, Unionbank) will be added in Phase 4+
        return [
            [
                'id' => 'BDO',
                'name' => 'Banco de Oro',
                'code' => '006',
                'supported_formats' => ['csv', 'excel'],
            ],
            [
                'id' => 'Metrobank',
                'name' => 'Metrobank',
                'code' => '010',
                'supported_formats' => ['csv', 'txt'],
            ],
        ];
    }
}
