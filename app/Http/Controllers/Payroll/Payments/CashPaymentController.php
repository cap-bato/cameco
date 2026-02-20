<?php

namespace App\Http\Controllers\Payroll\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PayrollPayment;
use App\Models\CashDistributionBatch;
use App\Models\PaymentMethod;
use App\Models\Department;
use App\Models\PayrollPeriod;

class CashPaymentController extends Controller
{
    /**
     * Display cash payments management page with employee list and distribution tracking
     */
    public function index(Request $request)
    {
        $cashMethodId = PaymentMethod::where('method_type', 'cash')->value('id');

        $cashEmployees = PayrollPayment::with(['employee.profile', 'employee.department', 'employee.position'])
            ->where('payment_method_id', $cashMethodId)
            ->when($request->period_id !== 'all', fn($q) => $q->where('payroll_period_id', $request->period_id))
            ->when($request->status !== 'all', fn($q) => $q->where('status', $request->status))
            ->when($request->department_id !== 'all', fn($q) => $q->whereHas('employee', fn($eq) =>
                $eq->where('department_id', $request->department_id)
            ))
            ->when($request->search, fn($q, $s) => $q->whereHas('employee', fn($eq) =>
                $eq->where('full_name', 'ilike', "%{$s}%")
                    ->orWhere('employee_number', 'ilike', "%{$s}%")
            ))
            ->get()
            ->map(function ($payment) {
                /** @var PayrollPayment $payment */
                return $this->formatCashEmployee($payment);
            });

        $batches = CashDistributionBatch::with(['payrollPeriod', 'preparedBy', 'distributedBy'])
            ->when($request->period_id !== 'all', fn($q) => $q->where('payroll_period_id', $request->period_id))
            ->latest()
            ->get();

        $summary = $this->buildCashSummary($cashEmployees, $batches);

        $unclaimedCash = PayrollPayment::with('employee.profile')
            ->where('payment_method_id', $cashMethodId)
            ->where('status', 'unclaimed')
            ->get()
            ->map(function ($payment) {
                /** @var PayrollPayment $payment */
                return $this->formatUnclaimedCash($payment);
            });

        return Inertia::render('Payroll/Payments/Cash/Index', [
            'cash_employees' => $cashEmployees,
            'summary' => $summary,
            'batches' => $batches,
            'unclaimed_cash' => $unclaimedCash,
            'payroll_periods' => $this->getPayrollPeriods(),
            'departments' => Department::select('id', 'name')->orderBy('name')->get(),
            'query_params' => $request->only(['period_id', 'department_id', 'status', 'search']),
        ]);
    }

    /**
     * Generate envelope data for printing
     */
    public function generateEnvelopes(Request $request)
    {
        $periodId = $request->input('period_id');
        $employeeIds = $request->input('employee_ids', []);

        // Get cash employees for selected period
        $cashEmployees = $this->getMockCashEmployees();
        $filteredEmployees = array_filter($cashEmployees, function ($emp) use ($periodId, $employeeIds) {
            $periodMatches = $periodId === 'all' || $emp['payroll_period_id'] == $periodId;
            $employeeMatches = empty($employeeIds) || in_array($emp['employee_id'], $employeeIds);
            return $periodMatches && $employeeMatches;
        });

        // Generate envelope data
        $envelopes = $this->generateEnvelopeData(array_values($filteredEmployees));
        $totalAmount = array_sum(array_map(fn ($e) => $e['net_pay'], $filteredEmployees));

        // Return Inertia with envelope preview data
        return Inertia::render('Payroll/Payments/Cash/EnvelopePreview', [
            'envelopes' => $envelopes,
            'total_amount' => $totalAmount,
            'formatted_total' => '₱' . number_format($totalAmount, 2),
            'count' => count($envelopes),
            'period_id' => $periodId,
        ]);
    }

    /**
     * Record distribution of cash envelope
     */
    public function recordDistribution(Request $request)
    {
        $validated = $request->validate([
            'cash_employee_id' => 'required|integer',
            'distributed_by' => 'required|string',
            'received_by' => 'nullable|string',
            'recipient_contact' => 'nullable|string',
            'distribution_date' => 'required|date',
            'distribution_time' => 'required|date_format:H:i',
            'signature_file' => 'nullable|file|mimes:jpeg,png,pdf',
            'notes' => 'nullable|string',
        ]);

        // In a real implementation:
        // 1. Update cash employee record with distribution details
        // 2. Store signature file if provided
        // 3. Update envelope status to "distributed"
        // 4. Create distribution tracking record
        // 5. Send confirmation email to employee

        return back()->with('success', 'Cash payment distribution recorded successfully.');
    }

    /**
     * Mark cash as claimed by employee
     */
    public function markClaimed(Request $request)
    {
        $validated = $request->validate([
            'cash_employee_id' => 'required|integer',
            'claimed_by' => 'required|string',
            'claimed_date' => 'required|date',
        ]);

        // In a real implementation:
        // 1. Update distribution record status to "claimed"
        // 2. Update unclaimed cash record if applicable
        // 3. Remove from accountability list

        return back()->with('success', 'Cash payment marked as claimed.');
    }

    /**
     * Record contact attempt for unclaimed cash
     */
    public function recordContactAttempt(Request $request)
    {
        $validated = $request->validate([
            'unclaimed_cash_id' => 'required|integer',
            'contact_method' => 'required|string|in:phone,email,sms,personal',
            'notes' => 'nullable|string',
        ]);

        // In a real implementation:
        // 1. Log contact attempt
        // 2. Update contact attempt count
        // 3. Record timestamp
        // 4. Add notes to unclaimed cash record

        return back()->with('success', 'Contact attempt recorded.');
    }

    /**
     * Generate accountability report for cash distribution
     */
    public function generateAccountabilityReport(Request $request)
    {
        $periodId = $request->input('period_id');

        $cashEmployees = $this->getMockCashEmployees();
        $distributions = $this->getMockDistributions();
        $filteredEmployees = array_filter($cashEmployees, fn ($e) => $periodId === 'all' || $e['payroll_period_id'] == $periodId);

        $totalCash = array_sum(array_map(fn ($e) => $e['net_pay'], $filteredEmployees));
        $distributed = array_sum(array_map(fn ($e) => $e['distribution_status'] === 'distributed' ? $e['net_pay'] : 0, $filteredEmployees));
        $unclaimed = array_sum(array_map(fn ($e) => $e['distribution_status'] === 'unclaimed' ? $e['net_pay'] : 0, $filteredEmployees));

        $report = [
            'period_id' => $periodId,
            'total_cash_employees' => count($filteredEmployees),
            'total_cash_amount' => $totalCash,
            'formatted_total' => '₱' . number_format($totalCash, 2),
            'distributed_count' => count(array_filter($filteredEmployees, fn ($e) => $e['distribution_status'] === 'distributed')),
            'distributed_amount' => $distributed,
            'formatted_distributed' => '₱' . number_format($distributed, 2),
            'unclaimed_count' => count(array_filter($filteredEmployees, fn ($e) => $e['distribution_status'] === 'unclaimed')),
            'unclaimed_amount' => $unclaimed,
            'formatted_unclaimed' => '₱' . number_format($unclaimed, 2),
            'distribution_rate' => count($filteredEmployees) > 0 ? round(($distributed / $totalCash) * 100, 2) : 0,
        ];

        return Inertia::render('Payroll/Payments/Cash/AccountabilityReport', [
            'report' => $report,
            'employees' => array_values($filteredEmployees),
            'distributions' => $distributions,
        ]);
    }

    /**
     * Apply filters to cash employees
     */
    private function applyFilters($employees, $filters)
    {
        $filtered = $employees;

        if ($filters['period_id'] !== 'all') {
            $filtered = array_filter($filtered, fn ($e) => $e['payroll_period_id'] == $filters['period_id']);
        }

        if ($filters['department_id'] !== 'all') {
            $filtered = array_filter($filtered, fn ($e) => $e['department'] === $filters['department_id']);
        }

        if ($filters['envelope_status'] !== 'all') {
            $filtered = array_filter($filtered, fn ($e) => $e['envelope_status'] === $filters['envelope_status']);
        }

        if ($filters['distribution_status'] !== 'all') {
            $filtered = array_filter($filtered, fn ($e) => $e['distribution_status'] === $filters['distribution_status']);
        }

        if (!empty($filters['search'])) {
            $query = strtolower($filters['search']);
            $filtered = array_filter($filtered, function ($e) use ($query) {
                return strpos(strtolower($e['employee_name']), $query) !== false ||
                       strpos(strtolower($e['employee_number']), $query) !== false ||
                       strpos(strtolower($e['department']), $query) !== false ||
                       strpos(strtolower($e['position']), $query) !== false;
            });
        }

        return array_values($filtered);
    }

    /**
     * Generate cash payment summary
     */
    private function generateCashPaymentSummary($cashEmployees)
    {
        $totalAmount = array_sum(array_map(fn ($e) => $e['net_pay'], $cashEmployees));

        $printedEnvelopes = count(array_filter($cashEmployees, fn ($e) => 
            in_array($e['envelope_status'], ['printed', 'prepared', 'distributed', 'unclaimed'])
        ));

        $distributedCount = count(array_filter($cashEmployees, fn ($e) => 
            in_array($e['distribution_status'], ['distributed', 'claimed'])
        ));

        $unclaimedCash = $this->getMockUnclaimedCash();
        $unclaimedAmount = array_sum(array_map(fn ($u) => $u['amount'], $unclaimedCash));

        return [
            'total_cash_employees' => count($cashEmployees),
            'total_cash_amount' => $totalAmount,
            'formatted_total_cash' => '₱' . number_format($totalAmount, 2),
            'envelopes_printed' => $printedEnvelopes,
            'envelopes_pending' => count($cashEmployees) - $printedEnvelopes,
            'distributed_count' => $distributedCount,
            'pending_distribution' => count($cashEmployees) - $distributedCount,
            'unclaimed_count' => count($unclaimedCash),
            'formatted_unclaimed_amount' => '₱' . number_format($unclaimedAmount, 2),
        ];
    }

    /**
     * Generate envelope data for printing
     */
    private function generateEnvelopeData($cashEmployees)
    {
        $envelopes = [];
        $pageCount = ceil(count($cashEmployees) / 3); // 3 envelopes per page

        foreach ($cashEmployees as $index => $emp) {
            $pageNum = floor($index / 3) + 1;
            $barcode = 'ENV-' . strtoupper(uniqid());
            $qrData = json_encode([
                'employee_id' => $emp['employee_id'],
                'employee_number' => $emp['employee_number'],
                'amount' => $emp['net_pay'],
                'period' => $emp['period_name'],
            ]);

            $envelopes[] = [
                'id' => $emp['id'],
                'employee_id' => $emp['employee_id'],
                'employee_number' => $emp['employee_number'],
                'employee_name' => $emp['employee_name'],
                'position' => $emp['position'],
                'period_name' => $emp['period_name'],
                'period_start_date' => date('Y-m-d', strtotime('-14 days')),
                'period_end_date' => date('Y-m-d'),
                'net_pay' => $emp['net_pay'],
                'formatted_net_pay' => $emp['formatted_net_pay'],
                'barcode' => $barcode,
                'qr_code' => 'data:image/svg+xml;base64,' . base64_encode('<svg><text>' . $qrData . '</text></svg>'),
                'department' => $emp['department'],
                'print_date' => date('Y-m-d'),
                'page_number' => $pageNum,
                'total_pages' => $pageCount,
            ];
        }

        return $envelopes;
    }

    /**
     * Get payroll periods from database
     */
    private function getPayrollPeriods()
    {
        return PayrollPeriod::select('id', 'period_name')->orderByDesc('payment_date')->get();
    }

    /**
     * Format a PayrollPayment record as a CashEmployee for the frontend
     */
    private function formatCashEmployee(PayrollPayment $payment)
    {
        $employee = $payment->employee;

        return [
            'id' => $payment->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'department' => $employee->department?->name ?? 'Unknown',
            'department_id' => $employee->department_id,
            'position' => $employee->position?->name ?? 'Unknown',
            'email' => $employee->profile?->email ?? '',
            'phone' => $employee->profile?->phone_number ?? '',
            'payroll_period_id' => $payment->payroll_period_id,
            'net_pay' => $payment->net_amount,
            'formatted_net_pay' => '₱' . number_format($payment->net_amount, 2),
            'gross_pay' => $payment->gross_amount,
            'formatted_gross_pay' => '₱' . number_format($payment->gross_amount, 2),
            'payment_status' => $payment->status,
            'payment_status_label' => ucfirst(str_replace('_', ' ', $payment->status)),
            'payment_method' => 'cash',
            'payment_reference' => $payment->payment_reference,
            'paid_at' => $payment->paid_at,
            'created_at' => $payment->created_at->toDateTimeString(),
        ];
    }

    /**
     * Format a PayrollPayment record as UnclaimedCash for the frontend
     */
    private function formatUnclaimedCash(PayrollPayment $payment)
    {
        $employee = $payment->employee;

        return [
            'id' => $payment->id,
            'payroll_payment_id' => $payment->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'period_name' => $payment->payrollPeriod?->name ?? 'Unknown Period',
            'amount' => $payment->net_amount,
            'formatted_amount' => '₱' . number_format($payment->net_amount, 2),
            'days_unclaimed' => now()->diffInDays($payment->created_at),
            'envelope_prepared_at' => $payment->created_at->toDateString(),
            'distribution_scheduled_for' => null,
            'status' => 'pending_collection',
            'status_label' => 'Pending Collection',
            'status_color' => 'yellow',
            'notes' => 'Cash envelope awaiting employee collection',
        ];
    }

    /**
     * Build cash payment summary from employees and batches data
     */
    private function buildCashSummary($cashEmployees, $batches)
    {
        $totalAmount = collect($cashEmployees)->sum('net_pay');
        $totalBatches = count($batches);
        $completedBatches = collect($batches)->where('status', 'completed')->count();
        $pendingAmount = 0;
        $distributedAmount = collect($cashEmployees)->where('payment_status', 'paid')->sum('net_pay');

        return [
            'total_cash_employees' => count($cashEmployees),
            'total_cash_amount' => $totalAmount,
            'formatted_total_cash' => '₱' . number_format($totalAmount, 2),
            'envelopes_prepared' => $totalBatches,
            'envelopes_distributed' => $completedBatches,
            'envelopes_unclaimed' => collect($cashEmployees)->where('payment_status', 'unclaimed')->count(),
            'amount_distributed' => $distributedAmount,
            'formatted_amount_distributed' => '₱' . number_format($distributedAmount, 2),
            'amount_unclaimed' => $totalAmount - $distributedAmount,
            'formatted_amount_unclaimed' => '₱' . number_format($totalAmount - $distributedAmount, 2),
        ];
    }

    /**
     * Get mock departments
     */
    private function getMockDepartments()
    {
        return [
            ['id' => 1, 'name' => 'Operations'],
            ['id' => 2, 'name' => 'Sales'],
            ['id' => 3, 'name' => 'Finance'],
            ['id' => 4, 'name' => 'HR'],
            ['id' => 5, 'name' => 'IT'],
        ];
    }

    /**
     * Get mock cash payment employees
     */
    private function getMockCashEmployees()
    {
        $employees = [
            ['id' => 1, 'name' => 'Juan Dela Cruz', 'number' => 'EMP001', 'dept' => 'Operations'],
            ['id' => 2, 'name' => 'Maria Santos', 'number' => 'EMP002', 'dept' => 'Operations'],
            ['id' => 3, 'name' => 'Pedro Reyes', 'number' => 'EMP003', 'dept' => 'Sales'],
            ['id' => 4, 'name' => 'Rosa Garcia', 'number' => 'EMP004', 'dept' => 'Sales'],
            ['id' => 5, 'name' => 'Carlos Mercado', 'number' => 'EMP005', 'dept' => 'Finance'],
        ];

        $statuses = ['pending', 'printed', 'prepared', 'distributed', 'unclaimed'];
        $distStatuses = ['pending', 'distributed', 'unclaimed', 'claimed'];
        $colors = ['gray', 'yellow', 'blue', 'green', 'red'];

        $cashEmployees = [];
        foreach ($employees as $emp) {
            $netPay = rand(15000, 45000);
            $statusIndex = array_rand($statuses);
            $distStatusIndex = array_rand($distStatuses);

            $cashEmployees[] = [
                'id' => $emp['id'],
                'employee_id' => $emp['id'],
                'employee_number' => $emp['number'],
                'employee_name' => $emp['name'],
                'department' => $emp['dept'],
                'position' => 'Production Staff',
                'payroll_period_id' => 1,
                'period_name' => 'November 2025 - 1st Half',
                'net_pay' => $netPay,
                'formatted_net_pay' => '₱' . number_format($netPay, 2),
                'payment_method' => 'cash',
                'payment_method_label' => 'Cash',
                'envelope_status' => $statuses[$statusIndex],
                'envelope_status_label' => ucfirst($statuses[$statusIndex]),
                'envelope_status_color' => $colors[$statusIndex],
                'envelope_printed_at' => $statusIndex > 0 ? date('Y-m-d H:i', strtotime('-' . rand(1, 10) . ' days')) : null,
                'envelope_printed_by' => $statusIndex > 0 ? 'Admin User' : null,
                'distribution_status' => $distStatuses[$distStatusIndex],
                'distribution_status_label' => ucfirst($distStatuses[$distStatusIndex]),
                'distributed_at' => $distStatusIndex > 0 ? date('Y-m-d H:i', strtotime('-' . rand(1, 5) . ' days')) : null,
                'distributed_by' => $distStatusIndex > 0 ? 'Manager User' : null,
                'claimed_at' => $distStatusIndex === 3 ? date('Y-m-d H:i', strtotime('-' . rand(0, 5) . ' days')) : null,
                'claimed_by' => $distStatusIndex === 3 ? $emp['name'] : null,
                'contact_number' => '09' . rand(10000000, 99999999),
                'email' => strtolower(str_replace(' ', '.', $emp['name'])) . '@example.com',
            ];
        }

        return $cashEmployees;
    }

    /**
     * Get mock distributions
     */
    private function getMockDistributions()
    {
        return [
            [
                'id' => 1,
                'cash_employee_id' => 1,
                'distribution_date' => date('Y-m-d', strtotime('-3 days')),
                'distribution_time' => '10:30',
                'distributed_by' => 'Manager User',
                'distributed_by_employee_id' => 99,
                'received_by' => 'Juan Dela Cruz',
                'recipient_contact_number' => '09123456789',
                'signature_file_path' => '/storage/signatures/emp001.png',
                'signature_captured_at' => date('Y-m-d H:i', strtotime('-3 days')),
                'amount' => 28000,
                'formatted_amount' => '₱28,000.00',
                'status' => 'claimed',
                'status_label' => 'Claimed',
                'notes' => 'Received and signed by employee',
                'created_at' => date('Y-m-d H:i', strtotime('-3 days')),
                'updated_at' => date('Y-m-d H:i', strtotime('-2 days')),
            ],
            [
                'id' => 2,
                'cash_employee_id' => 2,
                'distribution_date' => date('Y-m-d', strtotime('-2 days')),
                'distribution_time' => '14:15',
                'distributed_by' => 'Manager User',
                'distributed_by_employee_id' => 99,
                'received_by' => null,
                'recipient_contact_number' => null,
                'signature_file_path' => null,
                'signature_captured_at' => null,
                'amount' => 32500,
                'formatted_amount' => '₱32,500.00',
                'status' => 'unclaimed',
                'status_label' => 'Unclaimed',
                'notes' => 'Employee absent, envelope held at office',
                'created_at' => date('Y-m-d H:i', strtotime('-2 days')),
                'updated_at' => date('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * Get mock unclaimed cash
     */
    private function getMockUnclaimedCash()
    {
        return [
            [
                'id' => 1,
                'cash_employee_id' => 2,
                'employee_id' => 2,
                'employee_number' => 'EMP002',
                'employee_name' => 'Maria Santos',
                'period_name' => 'November 2025 - 1st Half',
                'amount' => 32500,
                'formatted_amount' => '₱32,500.00',
                'days_unclaimed' => 2,
                'days_until_returned' => 3,
                'envelope_prepared_at' => date('Y-m-d', strtotime('-2 days')),
                'distribution_scheduled_for' => date('Y-m-d'),
                'contact_attempts' => 1,
                'last_contact_attempt' => date('Y-m-d H:i', strtotime('-1 day')),
                'status' => 'pending_collection',
                'status_label' => 'Pending Collection',
                'status_color' => 'yellow',
                'notes' => 'Employee not available at work. Will attempt again on Monday.',
            ],
        ];
    }
}
