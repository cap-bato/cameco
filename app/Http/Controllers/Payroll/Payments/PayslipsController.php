<?php

namespace App\Http\Controllers\Payroll\Payments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\PayrollPeriod;
use App\Models\Department;

/**
 * PayslipsController
 * Manages DOLE-compliant payslip generation, distribution, and tracking
 * 
 * Features:
 * - Payslip generation with detailed earnings/deductions breakdown
 * - Multiple distribution methods (Email, Portal, Printed)
 * - YTD (Year-to-Date) totals tracking
 * - Employee acknowledgment tracking
 * - PDF generation and archiving
 * - Bulk operations support
 */
class PayslipsController extends Controller
{
    public function index(Request $request)
    {
        $payslips = Payslip::with(['employee.profile', 'employee.department', 'payrollPayment.payrollPeriod', 'generatedBy'])
            ->when($request->search, fn($q, $s) => $q->whereHas('employee', fn($eq) =>
                $eq->where('full_name', 'ilike', "%{$s}%")
                    ->orWhere('employee_number', 'ilike', "%{$s}%")
            ))
            ->when($request->period_id, fn($q, $id) => $q->whereHas('payrollPayment', fn($pq) =>
                $pq->where('payroll_period_id', $id)
            ))
            ->when($request->department_id, fn($q, $id) => $q->whereHas('employee', fn($eq) =>
                $eq->where('department_id', $id)
            ))
            ->when($request->status && $request->status !== 'all', fn($q, $s) => $q->where('status', $s))
            ->when($request->distribution_method && $request->distribution_method !== 'all', fn($q, $m) =>
                $q->where('distribution_method', $m)
            )
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(50)
            ->through(fn($p) => $this->formatPayslip($p));

        $summary = [
            'total_payslips' => Payslip::count(),
            'draft' => Payslip::where('status', 'draft')->count(),
            'generated' => Payslip::where('status', 'generated')->count(),
            'distributed' => Payslip::where('status', 'distributed')->count(),
            'acknowledged' => Payslip::where('status', 'acknowledged')->count(),
            'total_distribution_email' => Payslip::where('distribution_method', 'email')->count(),
            'total_distribution_portal' => Payslip::where('distribution_method', 'portal')->count(),
            'total_distribution_print' => Payslip::where('distribution_method', 'print')->count(),
            'total_distribution_sms' => Payslip::where('distribution_method', 'sms')->count(),
        ];

        return Inertia::render('Payroll/Payments/Payslips/Index', [
            'payslips' => $payslips,
            'summary' => $summary,
            'filters' => $request->only(['search', 'period_id', 'department_id', 'status', 'distribution_method', 'date_from', 'date_to']),
            'periods' => PayrollPeriod::select('id', 'period_name', 'period_start', 'period_end', 'payment_date')->orderByDesc('payment_date')->get(),
            'departments' => Department::select('id', 'name')->orderBy('name')->get(),
            'distributionMethods' => [
                ['id' => 'email', 'name' => 'Email'],
                ['id' => 'portal', 'name' => 'Self-Service Portal'],
                ['id' => 'print', 'name' => 'Printed'],
                ['id' => 'sms', 'name' => 'SMS'],
            ],
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|integer',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer',
            'regenerate' => 'nullable|boolean',
            'distribution_method' => 'required|in:email,portal,print,sms',
        ]);

        try {
            // Mock generation
            $generatedCount = isset($validated['employee_ids']) 
                ? count($validated['employee_ids']) 
                : 25;

            return back()->with('success', "{$generatedCount} payslips generated successfully for distribution via {$validated['distribution_method']}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate payslips: ' . $e->getMessage());
        }
    }

    public function distribute(Request $request)
    {
        $validated = $request->validate([
            'payslip_ids' => 'required|array',
            'payslip_ids.*' => 'integer',
            'distribution_method' => 'required|in:email,portal,print,sms',
            'email_subject' => 'nullable|string',
            'email_message' => 'nullable|string',
        ]);

        try {
            $sentCount = count($validated['payslip_ids']);
            $method = ucfirst($validated['distribution_method']);

            return back()->with('success', "{$sentCount} payslips distributed via {$method} successfully.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to distribute payslips: ' . $e->getMessage());
        }
    }

    public function preview(int $payslipId)
    {
        $previewData = $this->getMockPreviewData($payslipId);

        return Inertia::render('Payroll/Payments/Payslips/Index', [
            'previewData' => $previewData,
        ]);
    }

    public function download(int $payslipId)
    {
        try {
            // In production, this would generate and download the PDF
            return back()->with('success', 'Payslip downloaded successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to download payslip: ' . $e->getMessage());
        }
    }

    public function email(int $payslipId)
    {
        try {
            // Mock email sending
            return back()->with('success', 'Payslip emailed successfully to employee.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to email payslip: ' . $e->getMessage());
        }
    }

    public function print(int $payslipId)
    {
        try {
            // Mock print functionality
            return back()->with('success', 'Payslip added to print queue.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add payslip to print queue: ' . $e->getMessage());
        }
    }

    public function bulkDownload(Request $request)
    {
        $validated = $request->validate([
            'payslip_ids' => 'required|array',
            'payslip_ids.*' => 'integer',
        ]);

        try {
            $count = count($validated['payslip_ids']);
            return back()->with('success', "{$count} payslips prepared for download as ZIP file.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to prepare payslips for download: ' . $e->getMessage());
        }
    }

    public function bulkEmail(Request $request)
    {
        $validated = $request->validate([
            'payslip_ids' => 'required|array',
            'payslip_ids.*' => 'integer',
        ]);

        try {
            $sentCount = count($validated['payslip_ids']);
            return back()->with('success', "{$sentCount} payslips emailed successfully to employees.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to email payslips: ' . $e->getMessage());
        }
    }

    // ===================================================================
    // HELPER METHODS
    // ===================================================================

    /**
     * Format a Payslip record for the frontend
     */
    private function formatPayslip(Payslip $payslip)
    {
        $employee = $payslip->employee;
        $paymentPeriod = $payslip->payrollPayment?->payrollPeriod;

        return [
            'id' => $payslip->id,
            'payslip_number' => $payslip->payslip_number,
            'payroll_payment_id' => $payslip->payroll_payment_id,
            'employee_id' => $employee->id,
            'payroll_period_id' => $payslip->payroll_payment_id ? $payslip->payrollPayment->payroll_period_id : null,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'position' => $employee->position?->name ?? 'Unknown',
            'department' => $employee->department?->name ?? 'Unknown',
            'department_id' => $employee->department_id,
            'period_name' => $paymentPeriod?->name ?? 'N/A',
            'gross_pay' => $payslip->gross_amount,
            'formatted_gross_pay' => '₱' . number_format((float) $payslip->gross_amount, 2),
            'total_deductions' => $payslip->total_deductions,
            'formatted_total_deductions' => '₱' . number_format((float) $payslip->total_deductions, 2),
            'net_pay' => $payslip->net_amount,
            'formatted_net_pay' => '₱' . number_format((float) $payslip->net_amount, 2),
            'ytd_gross' => 0, // TODO: calculate from database
            'ytd_deductions' => 0, // TODO: calculate from database
            'ytd_net' => 0, // TODO: calculate from database
            'status' => $payslip->status,
            'status_label' => ucfirst(str_replace('_', ' ', $payslip->status)),
            'distribution_method' => $payslip->distribution_method,
            'distribution_method_label' => ucfirst($payslip->distribution_method === 'print' ? 'Printed' : str_replace('_', ' ', $payslip->distribution_method)),
            'file_path' => $payslip->file_path,
            'file_size' => $payslip->file_size,
            'file_hash' => $payslip->file_hash,
            'is_viewed' => $payslip->is_viewed,
            'viewed_at' => $payslip->viewed_at,
            'distributed_at' => $payslip->distributed_at,
            'created_at' => $payslip->created_at->toDateTimeString(),
            'updated_at' => $payslip->updated_at->toDateTimeString(),
        ];
    }

    // ===================================================================
    // MOCK DATA GENERATORS (Legacy - to be removed in Phase 4)
    // ===================================================================

    private function getMockPayslips(array $filters): array
    {
        $employees = [
            ['id' => 1, 'number' => 'EMP-001', 'name' => 'Juan Dela Cruz', 'dept' => 'Production', 'dept_id' => 1, 'pos' => 'Machine Operator'],
            ['id' => 2, 'number' => 'EMP-002', 'name' => 'Maria Santos', 'dept' => 'Quality Control', 'dept_id' => 2, 'pos' => 'QC Inspector'],
            ['id' => 3, 'number' => 'EMP-003', 'name' => 'Pedro Reyes', 'dept' => 'Warehouse', 'dept_id' => 3, 'pos' => 'Warehouse Staff'],
            ['id' => 4, 'number' => 'EMP-004', 'name' => 'Ana Garcia', 'dept' => 'Administration', 'dept_id' => 4, 'pos' => 'Admin Officer'],
            ['id' => 5, 'number' => 'EMP-005', 'name' => 'Jose Torres', 'dept' => 'Production', 'dept_id' => 1, 'pos' => 'Production Supervisor'],
            ['id' => 6, 'number' => 'EMP-006', 'name' => 'Linda Cruz', 'dept' => 'HR Department', 'dept_id' => 5, 'pos' => 'HR Assistant'],
            ['id' => 7, 'number' => 'EMP-007', 'name' => 'Ramon Flores', 'dept' => 'Maintenance', 'dept_id' => 6, 'pos' => 'Technician'],
            ['id' => 8, 'number' => 'EMP-008', 'name' => 'Sofia Mendoza', 'dept' => 'Quality Control', 'dept_id' => 2, 'pos' => 'QC Analyst'],
            ['id' => 9, 'number' => 'EMP-009', 'name' => 'Miguel Ramos', 'dept' => 'Production', 'dept_id' => 1, 'pos' => 'Machine Operator'],
            ['id' => 10, 'number' => 'EMP-010', 'name' => 'Carmen Lopez', 'dept' => 'Administration', 'dept_id' => 4, 'pos' => 'Secretary'],
        ];

        $statuses = ['pending', 'generated', 'sent', 'acknowledged', 'failed'];
        $distributionMethods = ['email', 'portal', 'printed'];

        $payslips = [];
        foreach ($employees as $index => $employee) {
            $statusIndex = $index % count($statuses);
            $status = $statuses[$statusIndex];
            $distributionMethod = $distributionMethods[$index % count($distributionMethods)];

            $basicSalary = rand(15000, 45000);
            $overtime = rand(1000, 5000);
            $nightDiff = rand(500, 2000);
            $holidayPay = rand(0, 3000);
            $allowances = rand(1000, 3000);
            $otherEarnings = rand(0, 1000);
            $grossPay = $basicSalary + $overtime + $nightDiff + $holidayPay + $allowances + $otherEarnings;

            $sss = round($grossPay * 0.045, 2);
            $philhealth = round($grossPay * 0.02, 2);
            $pagibig = 100;
            $tax = round($grossPay * 0.08, 2);
            $loans = rand(0, 2000);
            $otherDeductions = rand(0, 500);
            $totalDeductions = $sss + $philhealth + $pagibig + $tax + $loans + $otherDeductions;

            $netPay = $grossPay - $totalDeductions;

            $payslips[] = [
                'id' => $index + 1,
                'payslip_number' => 'PS-2025-10-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'payroll_calculation_id' => rand(100, 200),
                'employee_id' => $employee['id'],
                'payroll_period_id' => 1,
                'employee_number' => $employee['number'],
                'employee_name' => $employee['name'],
                'position' => $employee['pos'],
                'department' => $employee['dept'],
                'department_id' => $employee['dept_id'],
                'period_name' => 'October 2025 - 1st Half',
                'period_start' => '2025-10-01',
                'period_end' => '2025-10-15',
                'pay_date' => '2025-10-16',
                'basic_salary' => $basicSalary,
                'overtime_pay' => $overtime,
                'night_differential' => $nightDiff,
                'holiday_pay' => $holidayPay,
                'allowances' => $allowances,
                'other_earnings' => $otherEarnings,
                'gross_pay' => $grossPay,
                'sss_contribution' => $sss,
                'philhealth_contribution' => $philhealth,
                'pagibig_contribution' => $pagibig,
                'withholding_tax' => $tax,
                'loans' => $loans,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'ytd_gross' => $grossPay * 10,
                'ytd_deductions' => $totalDeductions * 10,
                'ytd_net' => $netPay * 10,
                'pdf_file_path' => $status !== 'pending' ? "/storage/payslips/PS-2025-10-" . str_pad($index + 1, 5, '0', STR_PAD_LEFT) . ".pdf" : null,
                'pdf_file_size' => $status !== 'pending' ? rand(50000, 200000) : null,
                'pdf_hash' => $status !== 'pending' ? hash('sha256', 'payslip' . ($index + 1)) : null,
                'distribution_method' => $distributionMethod,
                'email_sent' => in_array($status, ['sent', 'acknowledged']),
                'email_sent_at' => in_array($status, ['sent', 'acknowledged']) ? now()->subDays(rand(1, 5))->toDateTimeString() : null,
                'email_address' => $distributionMethod === 'email' ? strtolower(str_replace(' ', '.', $employee['name'])) . '@cathay-metal.com' : null,
                'downloaded_by_employee' => $status === 'acknowledged' || rand(0, 1),
                'downloaded_at' => $status === 'acknowledged' ? now()->subDays(rand(1, 3))->toDateTimeString() : null,
                'printed' => $distributionMethod === 'printed',
                'printed_at' => $distributionMethod === 'printed' ? now()->subDays(rand(1, 5))->toDateTimeString() : null,
                'printed_by' => $distributionMethod === 'printed' ? 'Payroll Officer' : null,
                'acknowledged_by_employee' => $status === 'acknowledged',
                'acknowledged_at' => $status === 'acknowledged' ? now()->subDays(rand(1, 2))->toDateTimeString() : null,
                'status' => $status,
                'status_label' => ucfirst($status),
                'status_color' => $this->getStatusColor($status),
                'generated_at' => now()->subDays(rand(1, 7))->toDateTimeString(),
                'generated_by' => 1,
                'generated_by_name' => 'Payroll Officer',
                'created_at' => now()->subDays(rand(1, 7))->toDateTimeString(),
                'updated_at' => now()->subDays(rand(0, 3))->toDateTimeString(),
            ];
        }

        // Apply filters
        if (!empty($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $payslips = array_filter($payslips, function ($payslip) use ($searchTerm) {
                return stripos($payslip['employee_name'], $searchTerm) !== false ||
                       stripos($payslip['employee_number'], $searchTerm) !== false ||
                       stripos($payslip['payslip_number'], $searchTerm) !== false;
            });
        }

        if (!empty($filters['period_id']) && $filters['period_id'] != 'all') {
            $payslips = array_filter($payslips, function ($payslip) use ($filters) {
                return $payslip['payroll_period_id'] == $filters['period_id'];
            });
        }

        if (!empty($filters['department_id']) && $filters['department_id'] != 'all') {
            $payslips = array_filter($payslips, function ($payslip) use ($filters) {
                return isset($payslip['department_id']) && $payslip['department_id'] == $filters['department_id'];
            });
        }

        if (!empty($filters['status']) && $filters['status'] != 'all') {
            $payslips = array_filter($payslips, function ($payslip) use ($filters) {
                return $payslip['status'] === $filters['status'];
            });
        }

        if (!empty($filters['distribution_method']) && $filters['distribution_method'] != 'all') {
            $payslips = array_filter($payslips, function ($payslip) use ($filters) {
                return $payslip['distribution_method'] === $filters['distribution_method'];
            });
        }

        return array_values($payslips);
    }

    private function getMockSummary(array $payslips): array
    {
        $pending = count(array_filter($payslips, fn($p) => $p['status'] === 'pending'));
        $generated = count(array_filter($payslips, fn($p) => $p['status'] === 'generated'));
        $sent = count(array_filter($payslips, fn($p) => $p['status'] === 'sent'));
        $acknowledged = count(array_filter($payslips, fn($p) => $p['status'] === 'acknowledged'));
        $failed = count(array_filter($payslips, fn($p) => $p['status'] === 'failed'));
        
        $email = count(array_filter($payslips, fn($p) => $p['distribution_method'] === 'email'));
        $portal = count(array_filter($payslips, fn($p) => $p['distribution_method'] === 'portal'));
        $printed = count(array_filter($payslips, fn($p) => $p['distribution_method'] === 'printed'));

        return [
            'total_payslips' => count($payslips),
            'generated' => $generated,
            'pending' => $pending,
            'sent' => $sent,
            'acknowledged' => $acknowledged,
            'failed' => $failed,
            'total_distribution_email' => $email,
            'total_distribution_portal' => $portal,
            'total_distribution_printed' => $printed,
        ];
    }

    private function getMockPeriods(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'October 2025 - 1st Half',
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-15',
                'pay_date' => '2025-10-16',
            ],
            [
                'id' => 2,
                'name' => 'September 2025 - 2nd Half',
                'start_date' => '2025-09-16',
                'end_date' => '2025-09-30',
                'pay_date' => '2025-10-01',
            ],
            [
                'id' => 3,
                'name' => 'September 2025 - 1st Half',
                'start_date' => '2025-09-01',
                'end_date' => '2025-09-15',
                'pay_date' => '2025-09-16',
            ],
        ];
    }

    private function getMockDepartments(): array
    {
        return [
            ['id' => 1, 'name' => 'Production'],
            ['id' => 2, 'name' => 'Quality Control'],
            ['id' => 3, 'name' => 'Warehouse'],
            ['id' => 4, 'name' => 'Administration'],
            ['id' => 5, 'name' => 'HR Department'],
            ['id' => 6, 'name' => 'Maintenance'],
        ];
    }

    private function getMockDistributionMethods(): array
    {
        return [
            ['id' => 'email', 'name' => 'Email'],
            ['id' => 'portal', 'name' => 'Employee Portal'],
            ['id' => 'printed', 'name' => 'Printed'],
        ];
    }

    private function getMockPreviewData(int $payslipId): array
    {
        $basicSalary = 25000;
        $overtime = 3500;
        $nightDiff = 1200;
        $holidayPay = 1500;
        $allowances = 2000;
        $otherEarnings = 500;
        $grossPay = $basicSalary + $overtime + $nightDiff + $holidayPay + $allowances + $otherEarnings;

        $sss = 1125;
        $philhealth = 675;
        $pagibig = 100;
        $tax = 2700;
        $loans = 1500;
        $otherDeductions = 200;
        $totalDeductions = $sss + $philhealth + $pagibig + $tax + $loans + $otherDeductions;

        $netPay = $grossPay - $totalDeductions;

        return [
            'employee_id' => $payslipId,
            'employee_number' => 'EMP-' . str_pad($payslipId, 3, '0', STR_PAD_LEFT),
            'employee_name' => 'Juan Dela Cruz',
            'position' => 'Machine Operator',
            'department' => 'Production',
            'period_name' => 'October 2025 - 1st Half',
            'period_start' => '2025-10-01',
            'period_end' => '2025-10-15',
            'pay_date' => '2025-10-16',
            'earnings' => [
                ['name' => 'Basic Salary', 'amount' => $basicSalary],
                ['name' => 'Overtime Pay', 'amount' => $overtime],
                ['name' => 'Night Differential', 'amount' => $nightDiff],
                ['name' => 'Holiday Pay', 'amount' => $holidayPay],
                ['name' => 'Allowances', 'amount' => $allowances],
                ['name' => 'Other Earnings', 'amount' => $otherEarnings],
            ],
            'gross_pay' => $grossPay,
            'deductions' => [
                ['name' => 'SSS Contribution', 'amount' => $sss],
                ['name' => 'PhilHealth Contribution', 'amount' => $philhealth],
                ['name' => 'Pag-IBIG Contribution', 'amount' => $pagibig],
                ['name' => 'Withholding Tax', 'amount' => $tax],
                ['name' => 'Loans', 'amount' => $loans],
                ['name' => 'Other Deductions', 'amount' => $otherDeductions],
            ],
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'ytd_gross' => $grossPay * 10,
            'ytd_deductions' => $totalDeductions * 10,
            'ytd_net' => $netPay * 10,
        ];
    }

    private function getStatusColor(string $status): string
    {
        return match($status) {
            'acknowledged' => 'green',
            'sent' => 'blue',
            'generated' => 'gray',
            'pending' => 'yellow',
            'failed' => 'red',
            default => 'gray',
        };
    }
}
