<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DocumentGeneratorService
{
    /**
     * Generate document based on request type and return stored path
     *
     * @param DocumentRequest $request
     * @return string Stored file path
     * @throws \Exception
     */
    public function generate(DocumentRequest $request): string
    {
        return match ($request->document_type) {
            'certificate_of_employment' => $this->generateCOE($request),
            'payslip' => $this->generatePayslip($request),
            'bir_form_2316' => $this->generateBIR2316($request),
            'government_compliance' => $this->generateGovernmentCompliance($request),
            default => throw new \Exception("Unknown document type: {$request->document_type}"),
        };
    }

    /**
     * Generate Certificate of Employment PDF and store it
     */
    private function generateCOE(DocumentRequest $request): string
    {
        $employee = $request->employee;

        $data = [
            'employee' => $employee,
            'profile' => $employee?->profile,
            'employment' => $employee?->employment,
            'position' => $employee?->employment?->position ?? 'N/A',
            'department' => $employee?->department_name ?? 'N/A',
            'hire_date' => $employee?->employment?->hire_date?->format('F d, Y') ?? 'N/A',
            'generated_date' => Carbon::now()->format('F d, Y'),
            'purpose' => $request->purpose ?? 'Whom it may concern',
        ];

        $pdf = Pdf::loadView('documents.certificate-of-employment', $data);

        $filename = sprintf('COE_%s_%s.pdf', $employee->employee_number ?? 'unknown', Carbon::now()->format('Ymd_His'));
        $path = "documents/generated/coe/{$filename}";

        Storage::put($path, $pdf->output());

        return $path;
    }

    /**
     * Generate or fetch Payslip PDF for a given period
     */
    private function generatePayslip(DocumentRequest $request): string
    {
        $employee = $request->employee;

        $period = $request->period ?? $this->extractPayslipPeriodFromNotes($request->notes);
        if (empty($period)) {
            throw new \InvalidArgumentException('Payslip period is required');
        }

        // Expecting period like "01-2024" (month-year)
        [$month, $year] = array_pad(explode('-', $period), 2, null);
        if (empty($month) || empty($year)) {
            throw new \InvalidArgumentException('Payslip period must be in MM-YYYY format');
        }

        $data = [
            'employee' => $employee,
            'profile' => $employee?->profile,
            'period' => Carbon::createFromDate((int)$year, (int)$month, 1)->format('F Y'),
            'generated_date' => Carbon::now()->format('F d, Y'),
            // TODO: add salary breakdown from payroll
        ];

        $pdf = Pdf::loadView('documents.payslip', $data);

        $filename = sprintf('Payslip_%s_%s.pdf', $employee->employee_number ?? 'unknown', $period);
        $path = "documents/generated/payslips/{$filename}";

        Storage::put($path, $pdf->output());

        return $path;
    }

    /**
     * Extract MM-YYYY period from notes such as "Period: 01-2024".
     */
    private function extractPayslipPeriodFromNotes(?string $notes): ?string
    {
        if (!$notes) {
            return null;
        }

        if (preg_match('/Period:\s*(\d{2}-\d{4})/i', $notes, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Generate BIR Form 2316 for previous year by default
     */
    private function generateBIR2316(DocumentRequest $request): string
    {
        $employee = $request->employee;
        $year = Carbon::now()->year - 1;

        $data = [
            'employee' => $employee,
            'profile' => $employee?->profile,
            'year' => $year,
            'generated_date' => Carbon::now()->format('F d, Y'),
            // TODO: add tax computation data
        ];

        $pdf = Pdf::loadView('documents.bir-form-2316', $data);

        $filename = sprintf('BIR2316_%s_%s.pdf', $employee->employee_number ?? 'unknown', $year);
        $path = "documents/generated/bir/{$filename}";

        Storage::put($path, $pdf->output());

        return $path;
    }

    /**
     * Generate Government Compliance document (SSS/PhilHealth/Pag-IBIG)
     */
    private function generateGovernmentCompliance(DocumentRequest $request): string
    {
        $employee = $request->employee;

        $data = [
            'employee' => $employee,
            'profile' => $employee?->profile,
            'generated_date' => Carbon::now()->format('F d, Y'),
            'purpose' => $request->purpose ?? 'Requested document',
            // TODO: add contribution records
        ];

        $pdf = Pdf::loadView('documents.government-compliance', $data);

        $filename = sprintf('GovCompliance_%s_%s.pdf', $employee->employee_number ?? 'unknown', Carbon::now()->format('Ymd'));
        $path = "documents/generated/government/{$filename}";

        Storage::put($path, $pdf->output());

        return $path;
    }
}
