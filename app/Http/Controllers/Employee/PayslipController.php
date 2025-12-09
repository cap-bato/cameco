<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
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
            // PLACEHOLDER: Awaiting Payroll module integration
            // TODO: Replace with actual payslip data from Payroll module
            // Query should be: Payslip::where('employee_id', $employee->id)->whereYear('pay_date', $year)->get()
            
            $payslips = $this->getMockPayslips($employee->id, $year);
            $availableYears = $this->getAvailableYears();

            return Inertia::render('Employee/Payslips/Index', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'payslips' => $payslips,
                'availableYears' => $availableYears,
                'filters' => [
                    'year' => $year,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Employee payslips data fetch failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Employee/Payslips/Index', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'payslips' => [],
                'availableYears' => $this->getAvailableYears(),
                'filters' => [
                    'year' => $year,
                ],
                'error' => 'Unable to load payslip data. Please refresh or contact Payroll if the issue persists.',
            ]);
        }
    }

    /**
     * Display detailed payslip information.
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
     * @return \Inertia\Response
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
            // PLACEHOLDER: Awaiting Payroll module integration
            // TODO: Replace with actual payslip data from Payroll module
            // Query should be: Payslip::where('id', $id)->where('employee_id', $employee->id)->firstOrFail()
            // This ensures employees can ONLY view their own payslips
            
            $payslip = $this->getMockPayslipDetail($employee->id, $id);

            if (!$payslip) {
                abort(404, 'Payslip not found or you do not have permission to view it.');
            }

            Log::info('Employee payslip detail viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'payslip_id' => $id,
            ]);

            return Inertia::render('Employee/PayslipDetail', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                    'position' => $employee->position->title ?? 'N/A',
                ],
                'payslip' => $payslip,
            ]);
        } catch (\Exception $e) {
            Log::error('Employee payslip detail fetch failed', [
                'employee_id' => $employee->id,
                'payslip_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(404, 'Payslip not found.');
        }
    }

    /**
     * Download payslip as PDF.
     * 
     * Generates and downloads a DOLE-compliant payslip PDF for the authenticated employee.
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
            // PLACEHOLDER: Awaiting Payroll module integration
            // TODO: Replace with actual payslip PDF generation from Payroll module
            // 1. Verify payslip belongs to employee: Payslip::where('id', $id)->where('employee_id', $employee->id)->firstOrFail()
            // 2. Generate PDF using PayslipService or similar
            // 3. Return PDF download response
            
            $payslip = $this->getMockPayslipDetail($employee->id, $id);

            if (!$payslip) {
                abort(404, 'Payslip not found or you do not have permission to download it.');
            }

            Log::info('Employee payslip downloaded', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'payslip_id' => $id,
            ]);

            // Generate mock PDF (placeholder)
            $pdfContent = $this->generateMockPayslipPDF($payslip);
            $filename = 'payslip-' . $payslip['period'] . '-' . $employee->employee_number . '.pdf';

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
     * Enforces "self-only" data access - employees can ONLY view their own annual summary.
     * 
     * @param Request $request
     * @param int $year
     * @return \Inertia\Response
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
            // PLACEHOLDER: Awaiting Payroll module integration
            // TODO: Replace with actual annual summary from Payroll module
            // Query should aggregate all payslips for employee in given year
            
            $annualSummary = $this->getMockAnnualSummary($employee->id, $year);

            Log::info('Employee annual summary viewed', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'year' => $year,
            ]);

            return Inertia::render('Employee/AnnualSummary', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'annualSummary' => $annualSummary,
                'year' => $year,
            ]);
        } catch (\Exception $e) {
            Log::error('Employee annual summary fetch failed', [
                'employee_id' => $employee->id,
                'year' => $year,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Employee/AnnualSummary', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'annualSummary' => [
                    'total_gross' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                ],
                'year' => $year,
                'error' => 'Unable to load annual summary. Please refresh or contact Payroll if the issue persists.',
            ]);
        }
    }

    /**
     * Generate mock payslip list for testing.
     * 
     * PLACEHOLDER: This method should be removed once Payroll module is integrated.
     * 
     * @param int $employeeId
     * @param int $year
     * @return array
     */
    private function getMockPayslips(int $employeeId, int $year): array
    {
        $payslips = [];
        
        // Generate 24 payslips (2 per month for semi-monthly payroll)
        for ($month = 1; $month <= 12; $month++) {
            $payslips[] = [
                'id' => ($year * 100 + $month) * 10 + 1,
                'period' => sprintf('%d-%02d-01 to %d-%02d-15', $year, $month, $year, $month),
                'pay_date' => sprintf('%d-%02d-20', $year, $month),
                'gross_pay' => rand(25000, 35000),
                'total_deductions' => rand(5000, 8000),
                'net_pay' => rand(20000, 27000),
                'status' => 'Released',
            ];

            $payslips[] = [
                'id' => ($year * 100 + $month) * 10 + 2,
                'period' => sprintf('%d-%02d-16 to %d-%02d-30', $year, $month, $year, $month),
                'pay_date' => sprintf('%d-%02d-05', $year, $month + 1 > 12 ? 1 : $month + 1),
                'gross_pay' => rand(25000, 35000),
                'total_deductions' => rand(5000, 8000),
                'net_pay' => rand(20000, 27000),
                'status' => 'Released',
            ];
        }

        return array_reverse($payslips); // Most recent first
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

    /**
     * Generate mock payslip detail for testing.
     * 
     * PLACEHOLDER: This method should be removed once Payroll module is integrated.
     * 
     * @param int $employeeId
     * @param int $payslipId
     * @return array|null
     */
    private function getMockPayslipDetail(int $employeeId, int $payslipId): ?array
    {
        return [
            'id' => $payslipId,
            'period' => '2025-12-01 to 2025-12-15',
            'pay_date' => '2025-12-20',
            
            // Earnings
            'basic_salary' => 28000.00,
            'allowances' => [
                ['name' => 'Transportation Allowance', 'amount' => 2000.00],
                ['name' => 'Meal Allowance', 'amount' => 1500.00],
            ],
            'overtime_pay' => 500.00,
            'gross_pay' => 32000.00,
            
            // Deductions
            'deductions' => [
                ['name' => 'SSS Contribution', 'amount' => 1125.00],
                ['name' => 'PhilHealth Contribution', 'amount' => 437.50],
                ['name' => 'Pag-IBIG Contribution', 'amount' => 200.00],
                ['name' => 'Withholding Tax', 'amount' => 2500.00],
                ['name' => 'SSS Loan', 'amount' => 500.00],
            ],
            'total_deductions' => 4762.50,
            
            // Net Pay
            'net_pay' => 27237.50,
            
            // Year-to-Date
            'ytd_gross' => 384000.00,
            'ytd_deductions' => 57150.00,
            'ytd_net' => 326850.00,
        ];
    }

    /**
     * Generate mock annual summary for testing.
     * 
     * PLACEHOLDER: This method should be removed once Payroll module is integrated.
     * 
     * @param int $employeeId
     * @param int $year
     * @return array
     */
    private function getMockAnnualSummary(int $employeeId, int $year): array
    {
        return [
            'year' => $year,
            'total_gross' => 672000.00,
            'total_basic_salary' => 336000.00,
            'total_allowances' => 42000.00,
            'total_overtime' => 6000.00,
            'total_bonuses' => 0.00,
            'thirteenth_month_pay' => 28000.00,
            'total_deductions' => 100050.00,
            'sss_total' => 13500.00,
            'philhealth_total' => 5250.00,
            'pagibig_total' => 2400.00,
            'tax_withheld' => 30000.00,
            'loan_deductions' => 6000.00,
            'total_net' => 571950.00,
        ];
    }

    /**
     * Generate mock payslip PDF.
     * 
     * PLACEHOLDER: This method should be removed once Payroll module is integrated.
     * 
     * @param array $payslip
     * @return string
     */
    private function generateMockPayslipPDF(array $payslip): string
    {
        // Return mock PDF content (just a placeholder)
        return "%PDF-1.4\n%Mock Payslip PDF\n%%EOF";
    }
}
