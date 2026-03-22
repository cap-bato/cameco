<?php

namespace App\Http\Controllers\Payroll\PayrollProcessing;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayrollCalculation;
use App\Models\Payslip;
use App\Models\PayrollApprovalHistory;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PayrollReviewController extends Controller
{
    private const REVIEWABLE_STATUSES = ['calculated', 'approved'];

    /**
     * Display payroll review and approval page
     */
    public function index(Request $request, ?int $periodId = null)
    {
        // Determine which period to show
        if ($periodId) {
            $period = PayrollPeriod::findOrFail($periodId);
        } else {
            $period = PayrollPeriod::whereIn('status', self::REVIEWABLE_STATUSES)
                ->orderByDesc('period_start')
                ->first();
        }

        // Available periods for the period selector dropdown
        $availablePeriods = PayrollPeriod::whereIn('status', ['calculated', 'approved', 'finalized', 'completed'])
            ->orderByDesc('period_start')
            ->get(['id', 'period_name', 'status', 'period_start', 'period_end'])
            ->map(fn($p) => [
                'id'           => $p->id,
                'label'        => $p->period_name,
                'status'       => $p->status,
                'period_start' => $p->period_start?->format('Y-m-d'),
                'period_end'   => $p->period_end?->format('Y-m-d'),
            ])
            ->values()
            ->toArray();

        if (!$period) {
            return redirect()->route('payroll.calculations.index')
                ->with('info', 'No payroll periods are ready for review. Start a calculation first.');
        }

        // Fetch all calculations for this period
        $calculations = EmployeePayrollCalculation::where('payroll_period_id', $period->id)->get();

        // Previous period for variance comparison
        $previousPeriod = PayrollPeriod::where('id', '<>', $period->id)
            ->where('period_start', '<', $period->period_start)
            ->whereIn('status', ['calculated', 'approved', 'finalized', 'completed'])
            ->orderByDesc('period_start')
            ->first();

        return Inertia::render('Payroll/PayrollProcessing/Review/Index', [
            'payroll_period'        => $this->buildPeriodSummary($period, $calculations),
            'summary'               => $this->buildSummary($period, $calculations, $previousPeriod),
            'departments'           => $this->buildDepartmentBreakdown($calculations),
            'exceptions'            => $this->buildExceptions($calculations),
            'approval_workflow'     => $this->buildApprovalWorkflow($period),
            'employee_calculations' => $calculations->map(fn($c) => $this->toEmployeePreview($c))->values()->toArray(),
            'available_periods'     => $availablePeriods,
        ]);
    }

    /**
     * Approve payroll — advances status through the workflow steps.
     */
    public function approve(Request $request, int $periodId)
    {
        try {
            $period     = PayrollPeriod::findOrFail($periodId);
            $prevStatus = $period->status;

            $period->approve(auth()->id());
            $nextStatus = 'approved';

            PayrollApprovalHistory::create([
                'payroll_period_id' => $periodId,
                'approval_step'     => 'approved',
                'action'            => 'approved',
                'status_from'       => $prevStatus,
                'status_to'         => $nextStatus,
                'user_id'           => auth()->id(),
                'user_name'         => auth()->user()->full_name ?? auth()->user()->name ?? 'Unknown',
                'user_role'         => 'Payroll Officer',
                'comments'          => $request->input('comments'),
                'created_at'        => now(),
            ]);

            Log::info('Payroll approved', [
                'period_id'   => $periodId,
                'period_name' => $period->period_name,
                'prev_status' => $prevStatus,
                'next_status' => $nextStatus,
                'approved_by' => auth()->id(),
            ]);

            return back()->with('success', 'Payroll approved successfully');
        } catch (\Exception $e) {
            Log::error('Payroll approval error', ['period_id' => $periodId, 'error' => $e->getMessage()]);
            return back()->withErrors('Failed to approve payroll: ' . $e->getMessage());
        }
    }

    /**
     * Reject payroll — resets status to 'calculated' and records reason.
     */
    public function reject(Request $request, int $periodId)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $period     = PayrollPeriod::findOrFail($periodId);
            $prevStatus = $period->status;

            $period->update([
                'status'           => 'calculated',
                'rejection_reason' => $validated['reason'],
            ]);

            PayrollApprovalHistory::create([
                'payroll_period_id' => $periodId,
                'approval_step'     => 'rejected',
                'action'            => 'reject',
                'status_from'       => $prevStatus,
                'status_to'         => 'calculated',
                'user_id'           => auth()->id(),
                'user_name'         => auth()->user()->full_name ?? auth()->user()->name ?? 'Unknown',
                'user_role'         => 'Payroll Officer',
                'rejection_reason'  => $validated['reason'],
                'created_at'        => now(),
            ]);

            Log::info('Payroll rejected', [
                'period_id'   => $periodId,
                'period_name' => $period->period_name,
                'reason'      => $validated['reason'],
                'rejected_by' => auth()->id(),
            ]);

            return back()->with('success', 'Payroll rejected successfully');
        } catch (\Exception $e) {
            Log::error('Payroll rejection error', ['period_id' => $periodId, 'error' => $e->getMessage()]);
            return back()->withErrors('Failed to reject payroll: ' . $e->getMessage());
        }
    }

    /**
     * Lock payroll — sets status to 'finalized', prevents further changes.
     */
    public function lock(Request $request, int $periodId)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $period     = PayrollPeriod::findOrFail($periodId);
            $prevStatus = $period->status;

            // Use model method to finalize the period
            $period->finalize(auth()->id());

            PayrollApprovalHistory::create([
                'payroll_period_id' => $periodId,
                'approval_step'     => 'locked',
                'action'            => 'locked',
                'status_from'       => $prevStatus,
                'status_to'         => 'finalized',
                'user_id'           => auth()->id(),
                'user_name'         => auth()->user()->full_name ?? auth()->user()->name ?? 'Unknown',
                'user_role'         => 'Payroll Officer',
                'comments'          => $validated['reason'],
                'created_at'        => now(),
            ]);

            Log::info('Payroll locked', [
                'period_id'   => $periodId,
                'period_name' => $period->period_name,
                'reason'      => $validated['reason'] ?? 'No reason provided',
                'locked_by'   => auth()->id(),
            ]);

            return back()->with('success', 'Payroll locked successfully');
        } catch (\Exception $e) {
            Log::error('Payroll lock error', ['period_id' => $periodId, 'error' => $e->getMessage()]);
            return back()->withErrors('Failed to lock payroll: ' . $e->getMessage());
        }
    }

    /**
     * Generate payslip data from real calculations and create Payslip records in database.
     * This is critical for the employee payslip view to work.
     */
    public function downloadPayslips(Request $request, int $periodId)
    {
        try {
            $period       = PayrollPeriod::findOrFail($periodId);
            $calculations = EmployeePayrollCalculation::where('payroll_period_id', $periodId)->get();

            if ($calculations->isEmpty()) {
                return back()->with('error', 'No payroll calculations found for this period.');
            }

            DB::transaction(function () use ($period, $calculations, $periodId) {
                // Delete any existing payslips for this period to avoid duplicates
                Payslip::where('payroll_period_id', $periodId)->delete();

                $payslipRecords = [];
                $now = now();

                foreach ($calculations as $calc) {
                    // Build earnings breakdown
                    $earningsData = [
                        'basic_pay'             => (float) $calc->basic_monthly_salary,
                        'regular_pay'           => (float) $calc->basic_monthly_salary,
                        'holiday_pay'           => (float) ($calc->holiday_pay ?? 0),
                        'overtime_pay'          => (float) ($calc->overtime_pay ?? 0),
                        'allowance'             => (float) ($calc->allowances ?? 0),
                        'other_earnings'        => (float) ($calc->other_earnings ?? 0),
                    ];

                    // Build deductions breakdown
                    $deductionsData = [
                        'sss'                   => (float) $calc->sss_deduction,
                        'philhealth'            => (float) $calc->philhealth_deduction,
                        'pagibig'               => (float) $calc->pagibig_deduction,
                        'withholding_tax'       => (float) $calc->tax_deduction,
                        'sss_contribution'      => (float) ($calc->sss_contribution ?? 0),
                        'ph_contribution'       => (float) ($calc->philhealth_contribution ?? 0),
                        'pagibig_contribution'  => (float) ($calc->pagibig_contribution ?? 0),
                        'loan'                  => (float) ($calc->loan_deduction ?? 0),
                        'advance'               => (float) ($calc->advance_deduction ?? 0),
                        'leave'                 => (float) ($calc->leave_deduction ?? 0),
                        'attendance'            => (float) ($calc->attendance_deduction ?? 0),
                        'tardiness'             => (float) ($calc->tardiness_deduction ?? 0),
                        'absence'               => (float) ($calc->absence_deduction ?? 0),
                        'miscellaneous'         => (float) ($calc->miscellaneous_deductions ?? 0),
                        'other'                 => (float) ($calc->other_deductions ?? 0),
                    ];

                    $payslipRecords[] = [
                        'payroll_period_id'     => $period->id,
                        'employee_id'           => $calc->employee_id,
                        'payslip_number'        => 'PS-' . $period->period_number . '-' . str_pad($calc->employee_id, 5, '0', STR_PAD_LEFT),
                        'period_start'          => $period->period_start,
                        'period_end'            => $period->period_end,
                        'payment_date'          => $period->payment_date,
                        
                        // Employee snapshot
                        'employee_number'       => $calc->employee_number,
                        'employee_name'         => $calc->employee_name,
                        'department'            => $calc->department,
                        'position'              => $calc->position,
                        'sss_number'            => $calc->sss_number ?? null,
                        'philhealth_number'     => $calc->philhealth_number ?? null,
                        'pagibig_number'        => $calc->pagibig_number ?? null,
                        'tin'                   => $calc->tin ?? null,
                        
                        // Amounts
                        'total_earnings'        => (float) $calc->gross_pay,
                        'total_deductions'      => (float) $calc->total_deductions,
                        'net_pay'               => (float) ($calc->final_net_pay ?? $calc->net_pay),
                        
                        // JSON Data (must be JSON-encoded for insert() to work correctly)
                        'earnings_data'         => json_encode($earningsData),
                        'deductions_data'       => json_encode($deductionsData),
                        
                        // YTD values (simplified — use the calculated totals)
                        'ytd_gross'             => (float) $calc->gross_pay,
                        'ytd_tax'               => (float) $calc->tax_deduction,
                        'ytd_sss'               => (float) $calc->sss_deduction,
                        'ytd_philhealth'        => (float) $calc->philhealth_deduction,
                        'ytd_pagibig'           => (float) $calc->pagibig_deduction,
                        'ytd_net'               => (float) ($calc->final_net_pay ?? $calc->net_pay),
                        
                        // Distribution defaults
                        'status'                => 'generated',
                        'distribution_method'   => 'portal',
                        'generated_by'          => auth()->id(),
                        
                        // Timestamps
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ];
                }

                // Insert all payslips at once (batch insert for performance)
                Payslip::insert($payslipRecords);

                Log::info('Payslips created successfully', [
                    'period_id'      => $periodId,
                    'employee_count' => count($payslipRecords),
                    'created_by'     => auth()->id(),
                ]);
            });

            // Also export to JSON for audit trail (optional)
            $payslips = $calculations->map(fn($c) => [
                'employee_id'     => $c->employee_id,
                'employee_name'   => $c->employee_name,
                'employee_number' => $c->employee_number,
                'department'      => $c->department,
                'position'        => $c->position,
                'period'          => $period->period_name,
                'basic_salary'    => (float) $c->basic_monthly_salary,
                'gross_pay'       => (float) $c->gross_pay,
                'deductions'      => (float) $c->total_deductions,
                'net_pay'         => (float) ($c->final_net_pay ?? $c->net_pay),
                'generated_at'    => now()->format('Y-m-d H:i:s'),
            ])->toArray();

            $timestamp = now()->format('YmdHis');
            $filename  = 'payslips_period_' . $periodId . '_' . $timestamp . '.json';
            $filepath  = storage_path('app/payslips/' . $filename);

            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            file_put_contents($filepath, json_encode($payslips, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return back()->with('success', 'Payslips generated successfully. ' . count($payslips) . ' payslips created and available to employees.');
        } catch (\Exception $e) {
            Log::error('Payslip generation error', ['period_id' => $periodId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withErrors('Failed to generate payslips: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // Private Helpers
    // =====================================================================

    private function buildPeriodSummary(PayrollPeriod $period, $calculations): array
    {
        $totalGross      = $calculations->sum(fn($c) => (float) $c->gross_pay);
        $totalDeductions = $calculations->sum(fn($c) => (float) $c->total_deductions);
        $totalNet        = $calculations->sum(fn($c) => (float) ($c->final_net_pay ?? $c->net_pay));

        return [
            'id'              => $period->id,
            'name'            => $period->period_name,
            'period_type'     => $period->period_type ?? 'semi_monthly',
            'start_date'      => $period->period_start?->format('Y-m-d'),
            'end_date'        => $period->period_end?->format('Y-m-d'),
            'pay_date'        => $period->payment_date?->format('Y-m-d'),
            'status'          => $this->mapStatusToFrontend($period->status),
            'total_employees' => $calculations->count(),
            'total_gross_pay' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net_pay'   => $totalNet,
        ];
    }

    private function buildSummary(PayrollPeriod $period, $calculations, ?PayrollPeriod $previousPeriod): array
    {
        $totalGross      = $calculations->sum(fn($c) => (float) $c->gross_pay);
        $totalStatutory  = $calculations->sum(fn($c) => (float) ($c->total_government_deductions ?? 0));
        $totalOther      = $calculations->sum(fn($c) =>
            (float) ($c->total_loan_deductions ?? 0) +
            (float) ($c->total_advance_deductions ?? 0) +
            (float) ($c->tardiness_deduction ?? 0) +
            (float) ($c->absence_deduction ?? 0) +
            (float) ($c->miscellaneous_deductions ?? 0)
        );
        $totalDeductions = $calculations->sum(fn($c) => (float) $c->total_deductions);
        $totalNet        = $calculations->sum(fn($c) => (float) ($c->final_net_pay ?? $c->net_pay));

        // Approximate employer cost (gross + ~9% employer contributions)
        $totalEmployerCost = $totalGross * 1.09;

        // Variance vs previous period
        $prevNet        = $previousPeriod ? (float) ($previousPeriod->total_net_pay ?? 0) : 0;
        $variance       = $totalNet - $prevNet;
        $variancePct    = $prevNet > 0 ? round(($variance / $prevNet) * 100, 1) : 0;
        $variancePctStr = ($variancePct >= 0 ? '+' : '') . $variancePct . '%';

        return [
            'total_employees_processed'  => $calculations->count(),
            'total_gross_pay'            => $totalGross,
            'total_statutory_deductions' => $totalStatutory,
            'total_other_deductions'     => $totalOther,
            'total_deductions'           => $totalDeductions,
            'total_net_pay'              => $totalNet,
            'total_employer_cost'        => $totalEmployerCost,
            'variance_from_previous'     => $variance,
            'variance_percentage'        => $variancePctStr,
            'previous_period_net_pay'    => $prevNet,
            'formatted_gross_pay'        => '₱' . number_format($totalGross, 2),
            'formatted_deductions'       => '₱' . number_format($totalDeductions, 2),
            'formatted_net_pay'          => '₱' . number_format($totalNet, 2),
            'formatted_employer_cost'    => '₱' . number_format($totalEmployerCost, 2),
            'formatted_variance'         => ($variance >= 0 ? '+' : '') . '₱' . number_format(abs($variance), 2),
        ];
    }

    private function buildDepartmentBreakdown($calculations): array
    {
        $grouped  = $calculations->groupBy('department');
        $totalNet = $calculations->sum(fn($c) => (float) ($c->final_net_pay ?? $c->net_pay));

        return $grouped->map(function ($group, $deptName) use ($totalNet) {
            $gross  = $group->sum(fn($c) => (float) $c->gross_pay);
            $deductions = $group->sum(fn($c) => (float) $c->total_deductions);
            $net    = $group->sum(fn($c) => (float) ($c->final_net_pay ?? $c->net_pay));
            $count  = $group->count();
            $pct    = $totalNet > 0 ? round(($net / $totalNet) * 100, 1) : 0;

            return [
                'id'                         => crc32((string) $deptName),
                'name'                       => $deptName ?? 'Unknown',
                'employee_count'             => $count,
                'total_gross_pay'            => $gross,
                'total_deductions'           => $deductions,
                'total_net_pay'              => $net,
                'total_employer_cost'        => $gross * 1.09,
                'percentage_of_total'        => $pct,
                'average_gross_per_employee' => $count > 0 ? round($gross / $count, 2) : 0,
                'average_net_per_employee'   => $count > 0 ? round($net / $count, 2) : 0,
                'formatted_gross_pay'        => '₱' . number_format($gross, 2),
                'formatted_net_pay'          => '₱' . number_format($net, 2),
                'formatted_employer_cost'    => '₱' . number_format($gross * 1.09, 2),
            ];
        })->values()->toArray();
    }

    private function buildExceptions($calculations): array
    {
        $exceptions  = [];
        $exceptionId = 1;

        foreach ($calculations as $calc) {
            $net   = (float) ($calc->final_net_pay ?? $calc->net_pay);
            $gross = (float) $calc->gross_pay;

            // Zero net pay despite having gross pay
            if ($net == 0.0 && $gross > 0) {
                $exceptions[] = [
                    'id'                 => $exceptionId++,
                    'type'               => 'zero_net_pay',
                    'severity'           => 'critical',
                    'title'              => 'Zero Net Pay',
                    'description'        => 'Employee has gross pay but net pay is zero',
                    'employee_id'        => $calc->employee_id,
                    'employee_name'      => $calc->employee_name,
                    'employee_number'    => $calc->employee_number,
                    'department'         => $calc->department,
                    'affected_amount'    => $gross,
                    'formatted_amount'   => '₱' . number_format($gross, 2),
                    'action_required'    => true,
                    'action_description' => 'Review deductions — they may exceed gross pay',
                ];
            }

            // Negative net pay
            if ($net < 0) {
                $exceptions[] = [
                    'id'                 => $exceptionId++,
                    'type'               => 'negative_net_pay',
                    'severity'           => 'critical',
                    'title'              => 'Negative Net Pay',
                    'description'        => 'Total deductions exceed gross pay',
                    'employee_id'        => $calc->employee_id,
                    'employee_name'      => $calc->employee_name,
                    'employee_number'    => $calc->employee_number,
                    'department'         => $calc->department,
                    'affected_amount'    => abs($net),
                    'formatted_amount'   => '₱' . number_format(abs($net), 2),
                    'action_required'    => true,
                    'action_description' => 'Reduce deductions or adjust salary',
                ];
            }

            // System-flagged calculation exceptions
            if ($calc->has_exceptions) {
                $flags       = $calc->exception_flags ?? [];
                $description = is_array($flags) && count($flags) > 0
                    ? implode('; ', array_slice($flags, 0, 2))
                    : 'Calculation exception detected';

                $exceptions[] = [
                    'id'                 => $exceptionId++,
                    'type'               => 'tax_anomaly',
                    'severity'           => 'warning',
                    'title'              => 'Calculation Exception',
                    'description'        => $description,
                    'employee_id'        => $calc->employee_id,
                    'employee_name'      => $calc->employee_name,
                    'employee_number'    => $calc->employee_number,
                    'department'         => $calc->department,
                    'affected_amount'    => $gross,
                    'formatted_amount'   => '₱' . number_format($gross, 2),
                    'action_required'    => true,
                    'action_description' => 'Review calculation flags',
                ];
            }
        }

        return $exceptions;
    }

    private function buildApprovalWorkflow(?PayrollPeriod $period): array
    {
        if (!$period) {
            return [
                'id'                => 0,
                'payroll_period_id' => 0,
                'current_step'      => 1,
                'total_steps'       => 1,
                'status'            => 'pending',
                'can_approve'       => false,
                'can_reject'        => false,
                'approver_role'     => 'Payroll Officer',
                'steps'             => $this->emptyWorkflowSteps(),
                'rejection_reason'  => null,
                'rejection_date'    => null,
                'rejection_by'      => null,
            ];
        }

        $status = $period->status;

        $history = PayrollApprovalHistory::where('payroll_period_id', $period->id)
            ->orderBy('created_at')
            ->get();

        // Fetch rejection info from last rejection history entry if any
        $rejectionEntry = $history->where('action', 'rejected')->sortByDesc('created_at')->first();

        return [
            'id'                => $period->id,
            'payroll_period_id' => $period->id,
            'current_step'      => 1,
            'total_steps'       => 1,
            'status'            => in_array($status, ['approved', 'finalized', 'completed']) ? 'approved' : 'in_progress',
            'can_approve'       => $status === 'calculated',
            'can_reject'        => $status === 'approved',
            'approver_role'     => 'Payroll Officer',
            'steps'             => $this->buildWorkflowSteps($period, $history),
            'rejection_reason'  => $rejectionEntry?->rejection_reason ?? $period->rejection_reason,
            'rejection_date'    => $rejectionEntry?->created_at?->format('Y-m-d H:i:s'),
            'rejection_by'      => $rejectionEntry?->user_name,
        ];
    }

    private function buildWorkflowSteps(PayrollPeriod $period, $history): array
    {
        $status = $period->status;

        $step1Done = in_array($status, ['calculated', 'under_review', 'pending_approval', 'approved', 'finalized', 'completed']);
        $step2Done = in_array($status, ['pending_approval', 'approved', 'finalized', 'completed']);
        $step3Done = in_array($status, ['approved', 'finalized', 'completed']);

        $step1Active = $status === 'calculated';
        $step2Active = $status === 'under_review';

        $approvalEntry = $history->where('action', 'approved')->sortBy('created_at')->first();
        $approvedByName = $period->approvedBy?->full_name ?? $period->approvedBy?->name;

        return [
            [
                'step_number'  => 1,
                'role'         => 'Payroll Officer',
                'status'       => $step1Done ? 'approved' : 'pending',
                'status_label' => $step1Done ? 'Completed' : 'Pending',
                'status_color' => $step1Done ? 'green' : ($step1Active ? 'yellow' : 'gray'),
                'description'  => 'Calculate and review payroll calculations',
                'approved_by'  => $step1Done ? ($approvalEntry?->user_name ?? 'System') : null,
                'approved_at'  => $step1Done ? $period->calculation_completed_at?->format('Y-m-d H:i:s') : null,
                'comments'     => null,
            ],
            [
                'step_number'  => 2,
                'role'         => 'Payroll Manager',
                'status'       => $step2Done ? 'approved' : 'pending',
                'status_label' => $step2Done ? 'Approved' : 'Pending',
                'status_color' => $step2Done ? 'green' : ($step2Active ? 'yellow' : 'gray'),
                'description'  => 'Review and approve payroll for processing',
                'approved_by'  => null,
                'approved_at'  => null,
                'comments'     => null,
            ],
            [
                'step_number'  => 3,
                'role'         => 'Finance Director',
                'status'       => $step3Done ? 'approved' : 'pending',
                'status_label' => $step3Done ? 'Approved' : 'Pending',
                'status_color' => $step3Done ? 'green' : 'gray',
                'description'  => 'Final authorization for payroll release',
                'approved_by'  => $step3Done ? $approvedByName : null,
                'approved_at'  => $step3Done ? $period->approved_at?->format('Y-m-d H:i:s') : null,
                'comments'     => null,
            ],
        ];
    }

    private function emptyWorkflowSteps(): array
    {
        $steps = [];
        foreach ([
            [1, 'Payroll Officer', 'Calculate and review payroll calculations'],
            [2, 'Payroll Manager', 'Review and approve payroll for processing'],
            [3, 'Finance Director', 'Final authorization for payroll release'],
        ] as [$num, $role, $desc]) {
            $steps[] = [
                'step_number'  => $num,
                'role'         => $role,
                'status'       => 'pending',
                'status_label' => 'Pending',
                'status_color' => 'gray',
                'description'  => $desc,
                'approved_by'  => null,
                'approved_at'  => null,
                'comments'     => null,
            ];
        }
        return $steps;
    }

    private function toEmployeePreview(EmployeePayrollCalculation $calc): array
    {
        $gross = (float) $calc->gross_pay;
        $net   = (float) ($calc->final_net_pay ?? $calc->net_pay);

        return [
            'id'                   => $calc->id,
            'employee_id'          => $calc->employee_id,
            'employee_name'        => $calc->employee_name,
            'employee_number'      => $calc->employee_number,
            'department'           => $calc->department,
            'position'             => $calc->position,
            'basic_salary'         => (float) $calc->basic_monthly_salary,
            'gross_pay'            => $gross,
            'statutory_deductions' => (float) ($calc->total_government_deductions ?? 0),
            'other_deductions'     => (float) ($calc->total_loan_deductions ?? 0) + (float) ($calc->total_advance_deductions ?? 0),
            'total_deductions'     => (float) $calc->total_deductions,
            'net_pay'              => $net,
            'has_adjustments'      => (bool) $calc->has_adjustments,
            'has_errors'           => (bool) $calc->has_exceptions,
            'error_message'        => $calc->has_exceptions
                ? implode(', ', array_slice((array) ($calc->exception_flags ?? []), 0, 3))
                : null,
            'formatted_gross_pay'  => '₱' . number_format($gross, 2),
            'formatted_net_pay'    => '₱' . number_format($net, 2),
        ];
    }

    private function emptyReviewSummary(): array
    {
        return [
            'total_employees_processed'  => 0,
            'total_gross_pay'            => 0,
            'total_statutory_deductions' => 0,
            'total_other_deductions'     => 0,
            'total_deductions'           => 0,
            'total_net_pay'              => 0,
            'total_employer_cost'        => 0,
            'variance_from_previous'     => 0,
            'variance_percentage'        => '+0%',
            'previous_period_net_pay'    => 0,
            'formatted_gross_pay'        => '₱0.00',
            'formatted_deductions'       => '₱0.00',
            'formatted_net_pay'          => '₱0.00',
            'formatted_employer_cost'    => '₱0.00',
            'formatted_variance'         => '+₱0.00',
        ];
    }

    private function mapStatusToFrontend(string $status): string
    {
        return match ($status) {
            'calculated'       => 'calculated',
            'under_review',
            'pending_approval' => 'reviewing',
            'approved'         => 'approved',
            default            => 'calculated',
        };
    }
}

