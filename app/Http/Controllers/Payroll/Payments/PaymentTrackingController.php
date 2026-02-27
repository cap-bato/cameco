<?php

namespace App\Http\Controllers\Payroll\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentTrackingController extends Controller
{
    /**
     * Display payment tracking list with filters
     */
    public function index(Request $request)
    {
        // Query all payments with relationships
        $allPaymentsQuery = PayrollPayment::with([
            'employee.profile',
            'employee.department',
            'employee.position',
            'paymentMethod',
            'payrollPeriod',
        ]);

        // Apply filters conditionally
        $query = $allPaymentsQuery
            ->when($request->search, function ($q, $search) {
                return $q->whereHas('employee', function ($emp) use ($search) {
                    $emp->whereRaw("CONCAT(profile_data->>'first_name', ' ', profile_data->>'last_name') ILIKE ?", ["%{$search}%"])
                        ->orWhere('employee_number', 'ILIKE', "%{$search}%");
                });
            })
            ->when($request->period_id && $request->period_id !== 'all', function ($q, $periodId) {
                return $q->where('payroll_period_id', $periodId);
            })
            ->when($request->department_id && $request->department_id !== 'all', function ($q, $deptId) {
                return $q->whereHas('employee', function ($emp) use ($deptId) {
                    $emp->where('department_id', $deptId);
                });
            })
            ->when($request->payment_status && $request->payment_status !== 'all', function ($q, $status) {
                return $q->where('status', $status);
            })
            ->when($request->payment_method && $request->payment_method !== 'all', function ($q, $method) {
                return $q->where('payment_method_id', $method);
            });

        // Get filtered payments
        $paginatedPayments = $query->latest()->paginate(50);
        $filteredPayments = $paginatedPayments->map(function ($payment) {
            /** @var PayrollPayment $payment */
            return $this->formatPayment($payment);
        });

        // Get all payments for summary calculations (unfiltered except failed)
        $allPayments = PayrollPayment::with(['employee.profile', 'employee.department', 'paymentMethod'])->get();
        $failedPaymentsQuery = PayrollPayment::where('status', 'failed')
            ->with(['employee.profile', 'employee.department', 'paymentMethod', 'payrollPeriod'])
            ->latest()
            ->get();
        $failedPayments = $failedPaymentsQuery->map(function ($payment) {
            /** @var PayrollPayment $payment */
            return $this->formatPayment($payment);
        });

        // Calculate summary from all payments
        $summary = $this->buildPaymentSummary($allPayments, $filteredPayments);

        // Get filter options
        $payrollPeriods = PayrollPeriod::select('id', 'period_name')->orderByDesc('payment_date')->get();
        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $paymentMethods = PaymentMethod::enabled()->ordered()->get(['id', 'method_type', 'display_name', 'is_enabled']);
        $paymentStatuses = ['pending', 'processing', 'paid', 'partially_paid', 'failed', 'cancelled', 'unclaimed'];

        return Inertia::render('Payroll/Payments/Tracking/Index', [
            'payments' => $filteredPayments,
            'summary' => $summary,
            'payroll_periods' => $payrollPeriods,
            'departments' => $departments,
            'payment_methods' => $paymentMethods,
            'payment_statuses' => $paymentStatuses,
            'failed_payments' => $failedPayments,
        ]);
    }

    /**
     * Confirm a payment with processing details (for processing status payments)
     * This records proof of payment like date, reference, and confirmation file
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer',
            'payment_date' => 'required|date',
            'payment_reference' => 'required|string|unique:payroll_payments,payment_reference',
            'notes' => 'nullable|string',
        ]);

        // In a real implementation:
        // 1. Update payment record with date, reference, notes
        // 2. Change status from "processing" to "confirming" or "paid"
        // 3. Store confirmation details in database

        return back()->with('success', 'Payment confirmed successfully. Transaction ID: ' . $validated['payment_reference']);
    }

    /**
     * Mark a payment as paid (simple status change for pending payments)
     * This is a quick confirmation without requiring additional data
     */
    public function markPaid(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer',
        ]);

        // In a real implementation:
        // 1. Verify payment exists and status is "pending"
        // 2. Update status to "paid"
        // 3. Record timestamp of mark-paid action
        // 4. If no confirmation data exists, mark as "paid" with system timestamp

        return back()->with('success', 'Payment marked as paid. Status updated to Paid.');
    }

    /**
     * Retry a failed payment
     */
    public function retry(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer',
        ]);

        // In a real implementation, process the payment retry
        // Check if retry limit is reached, validate account, etc.

        return back()->with('success', 'Payment retry queued. Processing will begin shortly.');
    }

    /**
     * Change payment method for a failed payment
     */
    public function changeMethod(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer',
            'new_method' => 'required|string|in:bank_transfer,cash,check',
        ]);

        // In a real implementation, update the payment method and retry

        return back()->with('success', 'Payment method changed to ' . $validated['new_method'] . '. Payment will be retried.');
    }

    /**
     * Format a single payment for frontend consumption
     */
    private function formatPayment(PayrollPayment $payment): array
    {
        $employee = $payment->employee;
        $paymentMethod = $payment->paymentMethod;
        $period = $payment->payrollPeriod;
        
        // Format dates - handle both Carbon and string types
        $paymentDateStr = null;
        if ($payment->payment_date) {
            if (is_object($payment->payment_date)) {
                $dateTime = new \DateTime($payment->payment_date . '');
                $paymentDateStr = $dateTime->format('Y-m-d');
            } else {
                $paymentDateStr = $payment->payment_date;
            }
        }
        
        $nextRetryStr = null;
        if ($payment->last_retry_at) {
            if (is_object($payment->last_retry_at)) {
                $dateTime = new \DateTime($payment->last_retry_at . '');
                $nextRetryStr = $dateTime->format('Y-m-d');
            } else {
                $nextRetryStr = $payment->last_retry_at;
            }
        }

        return [
            'id' => $payment->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'department' => $employee->department?->name ?? 'Unknown',
            'department_id' => $employee->department_id,
            'position' => $employee->position?->name ?? 'Unknown',
            'payroll_period_id' => $payment->payroll_period_id,
            'period_name' => $period?->period_name ?? 'N/A',
            'net_pay' => (float) $payment->net_pay,
            'formatted_net_pay' => '₱' . number_format((float) $payment->net_pay, 2),
            'payment_method_id' => $payment->payment_method_id,
            'payment_method' => $paymentMethod->method_type ?? 'unknown',
            'payment_method_label' => $paymentMethod->display_name ?? 'Unknown',
            'payment_method_icon' => $paymentMethod->method_type === 'bank' ? 'bank' : ($paymentMethod->method_type === 'cash' ? 'cash' : 'ewallet'),
            'current_payment_method' => $paymentMethod->method_type ?? 'unknown',
            'payment_status' => $payment->status,
            'payment_status_label' => ucfirst(str_replace('_', ' ', $payment->status)),
            'payment_status_color' => $this->getStatusColor($payment->status),
            'bank_name' => $payment->bank_name,
            'account_number' => $payment->bank_account_number ? '****' . substr($payment->bank_account_number, -4) : null,
            'payment_date' => $paymentDateStr,
            'payment_reference' => $payment->payment_reference,
            'payment_confirmation_file' => $payment->confirmation_code,
            'failure_reason' => $payment->failure_reason,
            'failure_code' => null,
            'retry_count' => $payment->retry_count ?? 0,
            'max_retries' => 5,
            'next_retry_date' => $nextRetryStr,
            'notes' => $payment->notes,
            'alternative_methods' => [
                ['method' => 'bank', 'label' => 'Bank Transfer', 'available' => true],
                ['method' => 'cash', 'label' => 'Cash', 'available' => true],
            ],
            'can_mark_paid' => in_array($payment->status, ['processing', 'pending']),
            'can_retry' => $payment->status === 'failed',
            'can_confirm' => $payment->status === 'processing',
        ];
    }

    /**
     * Get status color for payment status
     */
    private function getStatusColor($status)
    {
        return match ($status) {
            'paid' => 'green',
            'pending' => 'yellow',
            'processing' => 'blue',
            'partially_paid' => 'orange',
            'failed' => 'red',
            'cancelled' => 'gray',
            'unclaimed' => 'orange',
            default => 'gray',
        };
    }

    /**
     * Build summary from payment data
     */
    private function buildPaymentSummary($allPayments, $filteredPayments)
    {
        // Count statuses for all payments
        $paidCount = $allPayments->where('status', 'paid')->count();
        $pendingCount = $allPayments->where('status', 'pending')->count();
        $processingCount = $allPayments->where('status', 'processing')->count();
        $partiallyPaidCount = $allPayments->where('status', 'partially_paid')->count();
        $failedCount = $allPayments->where('status', 'failed')->count();
        $cancelledCount = $allPayments->where('status', 'cancelled')->count();
        $unclaimedCount = $allPayments->where('status', 'unclaimed')->count();

        // Calculate totals for all payments
        $totalAmount = $allPayments->sum('net_amount');
        $paidAmount = $allPayments->where('status', 'paid')->sum('net_amount');
        $pendingAmount = $allPayments->whereIn('status', ['pending', 'processing', 'unclaimed'])->sum('net_amount');
        $partiallyPaidAmount = $allPayments->where('status', 'partially_paid')->sum('net_amount');
        $failedAmount = $allPayments->where('status', 'failed')->sum('net_amount');

        $totalEmployees = $allPayments->count();

        return [
            'total_employees' => $totalEmployees,
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount + $processingCount + $unclaimedCount,
            'failed_count' => $failedCount,
            'processing_count' => $processingCount,
            'partially_paid_count' => $partiallyPaidCount,
            'cancelled_count' => $cancelledCount,
            'total_amount' => $totalAmount,
            'total_paid_amount' => $paidAmount,
            'total_pending_amount' => $pendingAmount,
            'total_partial_amount' => $partiallyPaidAmount,
            'total_failed_amount' => $failedAmount,
            'formatted_total' => '₱' . number_format($totalAmount, 2),
            'formatted_paid_amount' => '₱' . number_format($paidAmount, 2),
            'formatted_pending' => '₱' . number_format($pendingAmount, 2),
            'formatted_partial' => '₱' . number_format($partiallyPaidAmount, 2),
            'formatted_failed' => '₱' . number_format($failedAmount, 2),
            'paid_percentage' => $totalEmployees > 0 ? round(($paidCount / $totalEmployees) * 100) : 0,
            'pending_percentage' => $totalEmployees > 0 ? round((($pendingCount + $processingCount + $unclaimedCount) / $totalEmployees) * 100) : 0,
            'failed_percentage' => $totalEmployees > 0 ? round(($failedCount / $totalEmployees) * 100) : 0,
        ];
    }

    /**
     * Generate summary from payments [DEPRECATED - kept for reference]
     */
    private function generateSummary($allPayments, $filteredPayments)
    {
        $totalEmployees = count($allPayments);
        $paid = count(array_filter($filteredPayments, fn ($p) => $p['payment_status'] === 'paid'));
        $pending = count(array_filter($filteredPayments, fn ($p) => $p['payment_status'] === 'pending'));
        $processing = count(array_filter($filteredPayments, fn ($p) => $p['payment_status'] === 'processing'));
        $failed = count(array_filter($allPayments, fn ($p) => $p['payment_status'] === 'failed'));

        $totalAmount = array_sum(array_map(fn ($p) => $p['net_pay'], $allPayments));
        $paidAmount = array_sum(array_map(fn ($p) => $p['net_pay'], array_filter($allPayments, fn ($p) => $p['payment_status'] === 'paid')));
        $pendingAmount = array_sum(array_map(fn ($p) => $p['net_pay'], array_filter($allPayments, fn ($p) => $p['payment_status'] === 'pending')));
        $processingAmount = array_sum(array_map(fn ($p) => $p['net_pay'], array_filter($allPayments, fn ($p) => $p['payment_status'] === 'processing')));
        $failedAmount = array_sum(array_map(fn ($p) => $p['net_pay'], array_filter($allPayments, fn ($p) => $p['payment_status'] === 'failed')));

        return [
            'total_employees' => $totalEmployees,
            'paid_count' => $paid,
            'pending_count' => $pending,
            'failed_count' => $failed,
            'total_amount' => $totalAmount,
            'total_paid_amount' => $paidAmount,
            'total_pending_amount' => $pendingAmount,
            'total_failed_amount' => $failedAmount,
            'formatted_total' => '₱' . number_format($totalAmount, 2),
            'formatted_paid_amount' => '₱' . number_format($paidAmount, 2),
            'formatted_pending' => '₱' . number_format($pendingAmount + $processingAmount, 2),
            'formatted_failed' => '₱' . number_format($failedAmount, 2),
            'paid_percentage' => $totalEmployees > 0 ? round(($paid + round(count(array_filter($allPayments, fn ($p) => $p['payment_status'] === 'processing')) / 2)) / $totalEmployees * 100) : 0,
            'pending_percentage' => $totalEmployees > 0 ? round(($pending + round(count(array_filter($allPayments, fn ($p) => $p['payment_status'] === 'processing')) / 2)) / $totalEmployees * 100) : 0,
            'failed_percentage' => $totalEmployees > 0 ? round($failed / $totalEmployees * 100) : 0,
        ];
    }

    /**
     * Apply filters to payments
     */
    private function applyFilters($payments, $filters)
    {
        $filtered = $payments;

        // Search filter
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $filtered = array_filter($filtered, function ($p) use ($search) {
                return stripos($p['employee_name'], $search) !== false ||
                       stripos($p['employee_number'], $search) !== false;
            });
        }

        // Period filter
        if ($filters['period_id'] !== 'all') {
            $filtered = array_filter($filtered, fn ($p) => $p['payroll_period_id'] == $filters['period_id']);
        }

        // Department filter
        if ($filters['department_id'] !== 'all') {
            $filtered = array_filter($filtered, fn ($p) => $p['department_id'] == $filters['department_id']);
        }

        // Status filter
        if ($filters['payment_status'] !== 'all') {
            $filtered = array_filter($filtered, fn ($p) => $p['payment_status'] === $filters['payment_status']);
        }

        // Method filter
        if ($filters['payment_method'] !== 'all') {
            $filtered = array_filter($filtered, fn ($p) => $p['payment_method'] === $filters['payment_method']);
        }

        return array_values($filtered);
    }

    /**
     * Get mock payroll periods
     */
    private function getMockPayrollPeriods()
    {
        return [
            ['id' => 1, 'name' => 'November 2025 - 1st Half'],
            ['id' => 2, 'name' => 'November 2025 - 2nd Half'],
            ['id' => 3, 'name' => 'October 2025'],
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
     * Get mock payment records
     */
    private function getMockPayments()
    {
        $employees = [
            ['id' => 1, 'name' => 'Juan Dela Cruz', 'number' => 'EMP001', 'department_id' => 1],
            ['id' => 2, 'name' => 'Maria Santos', 'number' => 'EMP002', 'department_id' => 1],
            ['id' => 3, 'name' => 'Pedro Reyes', 'number' => 'EMP003', 'department_id' => 2],
            ['id' => 4, 'name' => 'Rosa Garcia', 'number' => 'EMP004', 'department_id' => 2],
            ['id' => 5, 'name' => 'Carlos Mercado', 'number' => 'EMP005', 'department_id' => 3],
            ['id' => 6, 'name' => 'Ana Mendoza', 'number' => 'EMP006', 'department_id' => 3],
            ['id' => 7, 'name' => 'Luis Gutierrez', 'number' => 'EMP007', 'department_id' => 4],
            ['id' => 8, 'name' => 'Sofia Lopez', 'number' => 'EMP008', 'department_id' => 5],
            ['id' => 9, 'name' => 'Antonio Ramos', 'number' => 'EMP009', 'department_id' => 1],
            ['id' => 10, 'name' => 'Lucia Diaz', 'number' => 'EMP010', 'department_id' => 2],
        ];

        $payments = [];
        $statuses = ['paid', 'pending', 'processing', 'failed'];
        $methods = ['bank_transfer', 'cash', 'check'];
        $banks = ['BPI', 'BDO', 'Metrobank', 'Maybank'];

        foreach ($employees as $emp) {
            $status = $statuses[array_rand($statuses)];
            $method = $methods[array_rand($methods)];
            $netPay = rand(20000, 50000);

            $payment = [
                'id' => $emp['id'],
                'employee_id' => $emp['id'],
                'employee_number' => $emp['number'],
                'employee_name' => $emp['name'],
                'department' => $this->getDepartmentName($emp['department_id']),
                'department_id' => $emp['department_id'],
                'position' => 'Production Staff',
                'payroll_period_id' => 1,
                'period_name' => 'November 2025 - 1st Half',
                'net_pay' => $netPay,
                'formatted_net_pay' => '₱' . number_format($netPay, 2),
                'payment_method' => $method,
                'payment_method_label' => ucfirst(str_replace('_', ' ', $method)),
                'payment_method_icon' => $method === 'bank_transfer' ? 'bank' : ($method === 'cash' ? 'cash' : 'check'),
                'current_payment_method' => $method,
                'payment_status' => $status,
                'payment_status_label' => ucfirst($status),
                'payment_status_color' => $status === 'paid' ? 'green' : ($status === 'pending' ? 'yellow' : ($status === 'processing' ? 'blue' : 'red')),
                'bank_name' => $method === 'bank_transfer' ? $banks[array_rand($banks)] : null,
                'account_number' => $method === 'bank_transfer' ? '****' . rand(1000, 9999) : null,
                'payment_date' => $status === 'paid' ? date('Y-m-d', strtotime('-' . rand(1, 10) . ' days')) : null,
                'payment_reference' => $status !== 'pending' ? 'TXN' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT) : null,
                'payment_confirmation_file' => $status === 'paid' ? '/storage/payments/receipt_' . $emp['id'] . '.pdf' : null,
                'failure_reason' => $status === 'failed' ? $this->getRandomFailureReason() : null,
                'failure_code' => $status === 'failed' ? 'ERR_' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT) : null,
                'retry_count' => $status === 'failed' ? rand(0, 3) : 0,
                'max_retries' => 5,
                'next_retry_date' => $status === 'failed' ? date('Y-m-d', strtotime('+1 day')) : null,
                'notes' => $status === 'failed' ? 'Contact HR for account verification' : null,
                'alternative_methods' => [
                    ['method' => 'bank_transfer', 'label' => 'Bank Transfer', 'available' => true],
                    ['method' => 'cash', 'label' => 'Cash', 'available' => true],
                    ['method' => 'check', 'label' => 'Check', 'available' => true],
                ],
                'can_mark_paid' => $status === 'processing' || $status === 'pending',
                'can_retry' => $status === 'failed',
                'can_confirm' => $status === 'processing',
            ];

            $payments[] = $payment;
        }

        return $payments;
    }

    /**
     * Get random failure reason for failed payments
     */
    private function getRandomFailureReason()
    {
        $reasons = [
            'Insufficient balance in account',
            'Invalid or expired bank account',
            'Account holder mismatch',
            'Bank system temporarily unavailable',
            'Declined by bank - fraud detection',
            'Incorrect account number',
        ];

        return $reasons[array_rand($reasons)];
    }

    /**
     * Get department name by ID
     */
    private function getDepartmentName($departmentId)
    {
        $departments = [
            1 => 'Operations',
            2 => 'Sales',
            3 => 'Finance',
            4 => 'HR',
            5 => 'IT',
        ];

        return $departments[$departmentId] ?? 'Unknown';
    }
}
