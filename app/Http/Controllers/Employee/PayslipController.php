<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PayslipController extends Controller
{
    /**
     * Display list of all payslips for the authenticated employee.
     * 
     * Shows payslips for current and previous periods (up to 3 years).
     * Employees can view, download, and print their payslips.
     * 
     * Enforces "self-only" data access - employees can ONLY view their own payslips.
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Employee payslips access attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        Log::info('Employee payslips viewed', [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
        ]);

        // Get filter parameters
        $year = $request->input('year', now()->year);

        try {
            $year = (int) $request->input('year', now()->year);

            // Available years from actual payslip records
            $availableYears = Payslip::where('employee_id', $employee->id)
                ->selectRaw('YEAR(period_start) as year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->toArray();

            if (empty($availableYears)) {
                $availableYears = [now()->year];
            }

            $payslips = Payslip::where('employee_id', $employee->id)
                ->whereYear('period_start', $year)
                ->orderBy('period_start', 'desc')
                ->get()
                ->map(fn($p) => $this->transformPayslip($p))
                ->toArray();

            return Inertia::render('Employee/Payslips/Index', [
                'employee' => [
                    'id'              => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name'       => $employee->profile->full_name ?? $user->name,
                    'department'      => $employee->department->name ?? 'N/A',
                    'position'        => $employee->position->title ?? 'N/A',
                ],
                'payslips'       => $payslips,
                'availableYears' => $availableYears,
                'filters'        => ['year' => $year],
            ]);
        } catch (\Exception $e) {
            Log::error('Employee payslips data fetch failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Employee/Payslips/Index', [
                'employee' => [
                    'id'              => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name'       => $employee->profile->full_name ?? $user->name,
                    'department'      => $employee->department->name ?? 'N/A',
                    'position'        => $employee->position->title ?? 'N/A',
                ],
                'payslips' => [],
                'availableYears' => [now()->year],
                'filters' => [
                    'year' => $year,
                ],
                'error' => 'Unable to load payslip data. Please refresh or contact Payroll if the issue persists.',
            ]);
        }
    }

    /**
     * Display detailed payslip information (JSON API).
     * 
     * Shows complete breakdown of:
     * - Pay period and pay date
     * - Basic salary and allowances
     * - Gross pay
     * - Deductions (SSS, PhilHealth, Pag-IBIG, Tax, Loans, Advances)
     * - Net pay (take-home salary)
     * - Year-to-date totals
     * 
     * Enforces "self-only" data access - employees can ONLY view their own payslips.
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Employee payslip detail access attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        try {
            // Fetch payslip from database, ensuring it belongs to the authenticated employee
            $payslip = Payslip::where('employee_id', $employee->id)
                ->findOrFail($id);

            // Mark as viewed if distributed
            if ($payslip->status === 'distributed') {
                $payslip->markAsViewed();
            }

            Log::info('Employee payslip detail viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'payslip_id' => $id,
            ]);

            return response()->json($this->transformPayslip($payslip));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Employee payslip detail not found', [
                'employee_id' => $employee->id,
                'payslip_id' => $id,
            ]);

            abort(404, 'Payslip not found or you do not have permission to view it.');
        } catch (\Exception $e) {
            Log::error('Employee payslip detail fetch failed', [
                'employee_id' => $employee->id,
                'payslip_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Failed to fetch payslip details.');
        }
    }

    /**
     * Download payslip as PDF.
     * 
     * Serves pre-generated PDFs when available, or generates on-demand using dompdf.
     * 
     * Enforces "self-only" data access - employees can ONLY download their own payslips.
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, int $id)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Employee payslip download attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        try {
            // Fetch payslip from database, ensuring it belongs to the authenticated employee
            $payslip = Payslip::where('employee_id', $employee->id)
                ->findOrFail($id);

            // Serve stored PDF if it exists
            if (!empty($payslip->file_path) && Storage::exists($payslip->file_path)) {
                Log::info('Employee payslip downloaded (stored PDF)', [
                    'user_id' => $user->id,
                    'employee_id' => $employee->id,
                    'payslip_id' => $id,
                ]);

                return Storage::download(
                    $payslip->file_path,
                    "payslip-{$payslip->period_start?->format('Y-m')}.pdf"
                );
            }

            // Fallback: Generate PDF on-demand via dompdf
            Log::info('Employee payslip downloaded (generated PDF)', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'payslip_id' => $id,
            ]);

            $data = $this->transformPayslip($payslip);
            $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadView('payslips.pdf', [
                'payslip'  => $data,
                'employee' => [
                    'full_name'       => $employee->profile->full_name ?? $user->name,
                    'employee_number' => $employee->employee_number,
                    'department'      => $employee->department->name ?? 'N/A',
                    'position'        => $employee->position->title ?? 'N/A',
                ],
            ]);

            return $pdf->download("payslip-{$payslip->period_start?->format('Y-m')}.pdf");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Employee payslip not found for download', [
                'employee_id' => $employee->id,
                'payslip_id' => $id,
            ]);

            abort(404, 'Payslip not found or you do not have permission to download it.');
        } catch (\Exception $e) {
            Log::error('Employee payslip download failed', [
                'employee_id' => $employee->id,
                'payslip_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to download payslip. Please try again or contact Payroll if the issue persists.');
        }
    }

    /**
     * Generate annual payslip summary.
     * 
     * Provides year-end summary including:
     * - Total gross income
     * - Total deductions
     * - Total net pay
     * - 13th month pay
     * - Bonuses received
     * - Tax withheld (BIR 2316 data)
     * 
     * Returns JSON API response (not Inertia view).
     * Enforces "self-only" data access - employees can ONLY view their own annual summary.
     * 
     * @param Request $request
     * @param int $year
     * @return \Illuminate\Http\JsonResponse
     */
    public function annualSummary(Request $request, int $year)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Employee annual summary access attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        try {
            // Query all payslips for employee in given year that are distributed or acknowledged
            $payslips = Payslip::where('employee_id', $employee->id)
                ->whereYear('period_start', $year)
                ->whereIn('status', ['distributed', 'acknowledged'])
                ->get();

            if ($payslips->isEmpty()) {
                Log::warning('No payslips found for annual summary', [
                    'employee_id' => $employee->id,
                    'year' => $year,
                ]);
                return response()->json(null);
            }

            // Calculate aggregate totals
            $totalGross      = $payslips->sum('total_earnings');
            $totalDeductions = $payslips->sum('total_deductions');
            $totalNet        = $payslips->sum('net_pay');

            // Tax withheld: sum of withholding_tax from deductions_data JSON
            $taxWithheld = $payslips->sum(function ($p) {
                return (float) (($p->deductions_data ?? [])['withholding_tax'] ?? 0);
            });

            // 13th month: attempt to derive from special payslip markers
            // NOTE: This uses deductions_data to check for thirteenth month flag or period detection
            // If no flag exists, returns null
            $thirteenthMonthData = $payslips->first(function ($p) {
                $data = $p->deductions_data ?? [];
                return isset($data['is_thirteenth_month']) && $data['is_thirteenth_month'] === true;
            });
            
            $thirteenthMonthPay = $thirteenthMonthData ? (float) $thirteenthMonthData->net_pay : null;

            Log::info('Employee annual summary generated', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'year' => $year,
                'payslip_count' => $payslips->count(),
            ]);

            return response()->json([
                'year'                 => $year,
                'total_gross'          => (float) $totalGross,
                'total_deductions'     => (float) $totalDeductions,
                'total_net'            => (float) $totalNet,
                'thirteenth_month_pay' => $thirteenthMonthPay,
                'bonuses_received'     => null, // TODO: derive from bonus payslip type if applicable
                'tax_withheld'         => (float) $taxWithheld,
            ]);
        } catch (\Exception $e) {
            Log::error('Employee annual summary fetch failed', [
                'employee_id' => $employee->id,
                'year' => $year,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Failed to generate annual summary.');
        }
    }

    /**
     * Download BIR 2316 tax certification for a calendar year.
     * 
     * Generates a BIR 2316 certificate showing:
     * - Total income earned in the year
     * - Total tax withheld (income tax)
     * - Net earnings
     * 
     * Enforces "self-only" data access - employees can ONLY download their own BIR 2316.
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadBIR2316(Request $request)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('BIR 2316 download attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        try {
            // Get year from request, default to current year
            $year = (int) $request->input('year', now()->year);

            // Query all payslips for employee in given year that are distributed or acknowledged
            $payslips = Payslip::where('employee_id', $employee->id)
                ->whereYear('period_start', $year)
                ->whereIn('status', ['distributed', 'acknowledged'])
                ->get();

            if ($payslips->isEmpty()) {
                Log::warning('No payslips found for BIR 2316', [
                    'employee_id' => $employee->id,
                    'year' => $year,
                ]);
                abort(404, "No payslips found for {$year}. BIR 2316 cannot be generated.");
            }

            // Calculate aggregate totals for BIR 2316
            $totalGross  = $payslips->sum('total_earnings');
            $totalNet    = $payslips->sum('net_pay');
            
            // Tax withheld: sum of withholding_tax from deductions_data JSON
            $taxWithheld = $payslips->sum(function ($p) {
                return (float) (($p->deductions_data ?? [])['withholding_tax'] ?? 0);
            });

            Log::info('BIR 2316 downloaded', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'year' => $year,
                'total_gross' => $totalGross,
                'tax_withheld' => $taxWithheld,
            ]);

            // Generate PDF via dompdf using bir-2316 view
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payslips.bir-2316', [
                'employee'    => [
                    'full_name'       => $employee->profile->full_name ?? $user->name,
                    'employee_number' => $employee->employee_number,
                    'department'      => $employee->department->name ?? 'N/A',
                    'tin'             => $employee->profile->tin ?? 'N/A',
                ],
                'year'        => $year,
                'total_gross' => $totalGross,
                'total_net'   => $totalNet,
                'tax_withheld'=> $taxWithheld,
            ]);

            return $pdf->download("BIR-2316-{$year}.pdf");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('BIR 2316 employee not found', [
                'user_id' => $user->id,
            ]);

            abort(404, 'Employee record not found.');
        } catch (\Exception $e) {
            Log::error('BIR 2316 download failed', [
                'employee_id' => $employee->id,
                'year' => $year ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to generate BIR 2316. Please try again or contact Payroll if the issue persists.');
        }
    }

    /**
     * Map database payslip status to frontend enum.
     * 
     * @param string $dbStatus
     * @return string
     */
    private function mapPayslipStatus(string $dbStatus): string
    {
        return match ($dbStatus) {
            'draft'        => 'pending',
            'generated'    => 'processing',
            'distributed'  => 'released',
            'acknowledged' => 'released',
            default        => 'pending',
        };
    }

    /**
     * Build allowances array from earnings data JSON.
     * 
     * Extracts allowances from earnings_data and transforms to SalaryComponent[].
     * 
     * @param array $earningsData
     * @return array
     */
    private function buildAllowancesArray(array $earningsData): array
    {
        $allowances = [];

        if (!empty($earningsData['other_allowances']) && $earningsData['other_allowances'] > 0) {
            $allowances[] = ['name' => 'Other Allowances', 'amount' => (float) $earningsData['other_allowances']];
        }

        // If earnings_data has an 'allowances' sub-array, use that instead
        if (!empty($earningsData['allowances']) && is_array($earningsData['allowances'])) {
            return array_map(fn($a) => [
                'name'   => $a['name'] ?? 'Allowance',
                'amount' => (float) ($a['amount'] ?? 0),
            ], $earningsData['allowances']);
        }

        return $allowances;
    }

    /**
     * Build deductions array from deductions data JSON.
     * 
     * Extracts deductions and maps to human-readable labels.
     * 
     * @param array $deductionsData
     * @return array
     */
    private function buildDeductionsArray(array $deductionsData): array
    {
        $labelMap = [
            'sss_contribution'       => 'SSS',
            'philhealth_contribution' => 'PhilHealth',
            'pagibig_contribution'   => 'Pag-IBIG',
            'withholding_tax'        => 'Withholding Tax',
            'total_loan_deductions'  => 'Loan Deductions',
            'tardiness_deduction'    => 'Tardiness',
        ];

        $deductions = [];
        foreach ($labelMap as $key => $label) {
            if (!empty($deductionsData[$key]) && $deductionsData[$key] > 0) {
                $deductions[] = ['name' => $label, 'amount' => (float) $deductionsData[$key]];
            }
        }

        return $deductions;
    }

    /**
     * Transform Payslip model to frontend response DTO.
     * 
     * Converts database payslip record to TypeScript PayslipRecord shape.
     * 
     * @param Payslip $payslip
     * @return array
     */
    private function transformPayslip(Payslip $payslip): array
    {
        $earningsData   = $payslip->earnings_data ?? [];
        $deductionsData = $payslip->deductions_data ?? [];

        $pdfUrl = null;
        if (!empty($payslip->file_path) && \Illuminate\Support\Facades\Storage::exists($payslip->file_path)) {
            try {
                $pdfUrl = \Illuminate\Support\Facades\Storage::temporaryUrl(
                    $payslip->file_path,
                    now()->addMinutes(30)
                );
            } catch (\Exception $e) {
                // Local storage does not support temporary URLs; fall back to url()
                $pdfUrl = \Illuminate\Support\Facades\Storage::url($payslip->file_path);
            }
        }

        return [
            'id'                 => $payslip->id,
            'pay_period_start'   => $payslip->period_start?->format('Y-m-d'),
            'pay_period_end'     => $payslip->period_end?->format('Y-m-d'),
            'pay_date'           => $payslip->payment_date?->format('Y-m-d'),
            'status'             => $this->mapPayslipStatus($payslip->status),
            'basic_salary'       => (float) ($earningsData['basic_monthly_salary'] ?? $earningsData['basic_salary'] ?? 0),
            'allowances'         => $this->buildAllowancesArray($earningsData),
            'gross_pay'          => (float) $payslip->total_earnings,
            'deductions'         => $this->buildDeductionsArray($deductionsData),
            'net_pay'            => (float) $payslip->net_pay,
            'year_to_date_gross' => (float) ($payslip->ytd_gross ?? 0),
            'year_to_date_net'   => (float) ($payslip->ytd_net ?? 0),
            'pdf_url'            => $pdfUrl,
        ];
    }

    /**
     * Get available years for payslip viewing (current year - 3 years).
     * 
     * @return array
     */
    private function getAvailableYears(): array
    {
        $currentYear = now()->year;
        $years = [];

        for ($i = 0; $i < 3; $i++) {
            $years[] = $currentYear - $i;
        }

        return $years;
    }


}
