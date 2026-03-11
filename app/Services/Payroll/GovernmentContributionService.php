<?php

namespace App\Services\Payroll;

use App\Models\EmployeeGovernmentContribution;
use App\Models\EmployeeLoan;
use App\Models\GovernmentRemittance;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * GovernmentContributionService
 *
 * Shared service for all 4 government agency controllers:
 *   - BIRController   (withholding tax, 1601C, 2316, Alphalist)
 *   - SSSController   (SSS contributions, R3 report)
 *   - PhilHealthController (PhilHealth premiums, RF1 report)
 *   - PagIbigController   (Pag-IBIG contributions, MCRF, loan deductions)
 *
 * Every method that queries `employee_government_contributions` eagerly loads
 * the `employee` relationship to avoid N+1 queries.
 */
class GovernmentContributionService
{
    // =========================================================================
    // Periods
    // =========================================================================

    /**
     * Return the last 12 payroll periods ordered newest-first.
     * Each item is shaped as the frontend Period interfaces expect.
     */
    public function getPeriods(): Collection
    {
        return PayrollPeriod::orderByDesc('period_start')
            ->limit(12)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->period_name,
                'month'      => $p->period_start?->format('Y-m'),
                'start_date' => $p->period_start?->format('Y-m-d'),
                'end_date'   => $p->period_end?->format('Y-m-d'),
                'status'     => $p->status ?? 'open',
            ]);
    }

    // =========================================================================
    // Contributions
    // =========================================================================

    /**
     * Return all employee government contribution records for the given period,
     * shaped for the agency's frontend interface.
     *
     * @param string   $agency    One of: 'sss' | 'philhealth' | 'pagibig' | 'bir'
     * @param int|null $periodId  If null, uses the most recent period.
     */
    public function getContributions(string $agency, ?int $periodId = null): Collection
    {
        $resolvedId = $periodId ?? $this->resolveLatestPeriodId();

        if (!$resolvedId) {
            return collect();
        }

        $rows = EmployeeGovernmentContribution::with('employee')
            ->where('payroll_period_id', $resolvedId)
            ->get();

        return match ($agency) {
            'sss'        => $this->mapSSSContributions($rows),
            'philhealth' => $this->mapPhilHealthContributions($rows),
            'pagibig'    => $this->mapPagIbigContributions($rows),
            'bir'        => $this->mapBIRContributions($rows),
            default      => collect(),
        };
    }

    // =========================================================================
    // Summary
    // =========================================================================

    /**
     * Return summary statistics for a given agency and period.
     */
    public function getSummary(string $agency, ?int $periodId = null): array
    {
        $resolvedId = $periodId ?? $this->resolveLatestPeriodId();

        if (!$resolvedId) {
            return $this->emptyAgencySummary($agency);
        }

        $rows = EmployeeGovernmentContribution::where('payroll_period_id', $resolvedId)->get();

        $lastPaymentDate = GovernmentRemittance::where('agency', $agency)
            ->whereNotNull('payment_date')
            ->orderByDesc('payment_date')
            ->value('payment_date');

        $nextDueDate     = GovernmentRemittance::where('agency', $agency)
            ->whereIn('status', ['pending', 'ready'])
            ->orderBy('due_date')
            ->value('due_date');

        $pendingCount = GovernmentRemittance::where('agency', $agency)
            ->whereIn('status', ['pending', 'ready', 'overdue'])
            ->count();

        // Default next-due to the 10th of next month if no pending record exists
        $nextDue = $nextDueDate
            ?? Carbon::now()->addMonth()->startOfMonth()->addDays(9)->format('Y-m-d');

        return match ($agency) {
            'sss'        => [
                'total_employees'            => $rows->count(),
                'total_monthly_compensation' => (float) $rows->sum('gross_compensation'),
                'total_employee_contribution' => (float) $rows->sum('sss_employee_contribution'),
                'total_employer_contribution' => (float) $rows->sum('sss_employer_contribution'),
                'total_ec_contribution'       => (float) $rows->sum('sss_ec_contribution'),
                'total_contribution'          => (float) $rows->sum('sss_total_contribution'),
                'last_remittance_date'        => $lastPaymentDate,
                'next_due_date'              => $nextDue,
                'pending_remittances'        => $pendingCount,
            ],

            'philhealth' => [
                'total_employees'            => $rows->count(),
                'total_monthly_basic'        => (float) $rows->sum('basic_salary'),
                'total_employee_premium'     => (float) $rows->sum('philhealth_employee_contribution'),
                'total_employer_premium'     => (float) $rows->sum('philhealth_employer_contribution'),
                'total_premium'              => (float) $rows->sum('philhealth_total_contribution'),
                'last_remittance_date'       => $lastPaymentDate,
                'next_due_date'             => $nextDue,
                'pending_remittances'       => $pendingCount,
                'indigent_members'          => 0,  // EmployeeGovernmentContribution has no indigent field; kept 0
            ],

            'pagibig' => [
                'total_employees'            => $rows->count(),
                'total_monthly_compensation' => (float) $rows->sum('gross_compensation'),
                'total_employee_contribution' => (float) $rows->sum('pagibig_employee_contribution'),
                'total_employer_contribution' => (float) $rows->sum('pagibig_employer_contribution'),
                'total_contribution'          => (float) $rows->sum('pagibig_total_contribution'),
                'total_loan_deductions'       => (float) EmployeeLoan::whereIn('loan_type', ['pagibig_calamity', 'pagibig_housing', 'pagibig_multi_purpose'])
                                                    ->where('status', 'active')
                                                    ->sum('installment_amount'),
                'last_remittance_date'        => $lastPaymentDate,
                'next_due_date'              => $nextDue,
                'pending_remittances'        => $pendingCount,
            ],

            'bir' => [
                'total_employees'           => $rows->count(),
                'total_gross_compensation'  => (float) $rows->sum('gross_compensation'),
                'total_withholding_tax'     => (float) $rows->sum('withholding_tax'),
                'reports_generated_count'   => 0,
                'reports_submitted_count'   => 0,
                'last_submission_date'      => null,
                'next_deadline'             => $nextDue,
            ],

            default => $this->emptyAgencySummary($agency),
        };
    }

    // =========================================================================
    // Remittances
    // =========================================================================

    /**
     * Return remittance history for an agency, newest first.
     */
    public function getRemittances(string $agency, int $limit = 12): Collection
    {
        return GovernmentRemittance::where('agency', $agency)
            ->orderByDesc('period_start')
            ->limit($limit)
            ->get()
            ->map(fn (GovernmentRemittance $r) => [
                'id'                => $r->id,
                'period_id'         => $r->payroll_period_id,
                'month'             => $r->remittance_month,
                'remittance_amount' => (float) $r->total_amount,
                'due_date'          => $r->due_date?->format('Y-m-d'),
                'payment_date'      => $r->payment_date?->format('Y-m-d'),
                'payment_reference' => $r->payment_reference,
                'status'            => $r->status,
                'has_penalty'       => (bool) $r->has_penalty,
                'penalty_amount'    => (float) $r->penalty_amount,
                'penalty_reason'    => $r->penalty_reason,
                'contributions'     => [
                    'employee_share' => (float) $r->employee_share,
                    'employer_share' => (float) $r->employer_share,
                    'ec_share'       => (float) ($r->ec_share ?? 0),
                ],
                'created_at'        => $r->created_at?->toISOString(),
                'updated_at'        => $r->updated_at?->toISOString(),
            ]);
    }

    // =========================================================================
    // Pag-IBIG Loan Deductions
    // =========================================================================

    /**
     * Return active Pag-IBIG loans shaped for the `loan_deductions` frontend prop.
     */
    public function getPagIbigLoanDeductions(): Collection
    {
        $pagibigLoanTypes = ['pagibig_calamity', 'pagibig_housing', 'pagibig_multi_purpose', 'pagibig'];

        return EmployeeLoan::with('employee')
            ->whereIn('loan_type', $pagibigLoanTypes)
            ->where('status', 'active')
            ->get()
            ->map(function (EmployeeLoan $loan) {
                $loanTypeMap = [
                    'pagibig_calamity'       => 'calamity',
                    'pagibig_housing'        => 'housing',
                    'pagibig_multi_purpose'  => 'other',
                    'pagibig'                => 'other',
                ];

                return [
                    'id'                    => $loan->id,
                    'employee_id'           => $loan->employee_id,
                    'employee_name'         => $loan->employee?->full_name ?? $loan->employee?->user?->name ?? '',
                    'employee_number'       => $loan->employee?->employee_number ?? '',
                    'loan_number'           => $loan->loan_number,
                    'loan_type'             => $loanTypeMap[$loan->loan_type] ?? 'other',
                    'loan_amount'           => (float) $loan->principal_amount,
                    'disbursement_date'     => $loan->loan_date?->format('Y-m-d'),
                    'monthly_deduction'     => (float) $loan->installment_amount,
                    'months_remaining'      => max(0, (int) $loan->number_of_installments - (int) ($loan->installments_paid ?? 0)),
                    'total_deducted_to_date' => (float) ($loan->total_paid ?? ($loan->principal_amount - $loan->remaining_balance)),
                    'is_active'             => $loan->status === 'active',
                    'maturity_date'         => $loan->last_deduction_date?->format('Y-m-d') ?? '',
                    'created_at'            => $loan->created_at?->toISOString(),
                    'updated_at'            => $loan->updated_at?->toISOString(),
                ];
            });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function resolveLatestPeriodId(): ?int
    {
        return PayrollPeriod::orderByDesc('period_start')->value('id');
    }

    private function mapSSSContributions(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => [
            'id'                   => $row->id,
            'employee_id'          => $row->employee_id,
            'employee_name'        => $row->employee?->full_name ?? $row->employee?->user?->name ?? '',
            'employee_number'      => $row->employee?->employee_number ?? '',
            'sss_number'           => $row->sss_number ?? '',
            'period_id'            => $row->payroll_period_id,
            'month'                => $row->period_month,
            'monthly_compensation' => (float) $row->gross_compensation,
            'sss_bracket'          => $row->sss_bracket ?? 'A',
            'sss_bracket_range'    => ['min' => 0, 'max' => 0],
            'employee_contribution' => (float) $row->sss_employee_contribution,
            'employer_contribution' => (float) $row->sss_employer_contribution,
            'ec_contribution'       => (float) $row->sss_ec_contribution,
            'total_contribution'    => (float) $row->sss_total_contribution,
            'is_processed'         => !is_null($row->processed_at),
            'is_remitted'          => false,
            'is_exempted'          => (bool) $row->is_sss_exempted,
            'created_at'           => $row->created_at?->toISOString(),
            'updated_at'           => $row->updated_at?->toISOString(),
        ]);
    }

    private function mapPhilHealthContributions(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => [
            'id'                => $row->id,
            'employee_id'       => $row->employee_id,
            'employee_name'     => $row->employee?->full_name ?? $row->employee?->user?->name ?? '',
            'employee_number'   => $row->employee?->employee_number ?? '',
            'philhealth_number' => $row->philhealth_number ?? '',
            'period_id'         => $row->payroll_period_id,
            'month'             => $row->period_month,
            'monthly_basic'     => (float) $row->basic_salary,
            'employee_premium'  => (float) $row->philhealth_employee_contribution,
            'employer_premium'  => (float) $row->philhealth_employer_contribution,
            'total_premium'     => (float) $row->philhealth_total_contribution,
            'is_processed'      => !is_null($row->processed_at),
            'is_remitted'       => false,
            'is_indigent'       => false,
            'created_at'        => $row->created_at?->toISOString(),
            'updated_at'        => $row->updated_at?->toISOString(),
        ]);
    }

    private function mapPagIbigContributions(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => [
            'id'                    => $row->id,
            'employee_id'           => $row->employee_id,
            'employee_name'         => $row->employee?->full_name ?? $row->employee?->user?->name ?? '',
            'employee_number'       => $row->employee?->employee_number ?? '',
            'pagibig_number'        => $row->pagibig_number ?? '',
            'period_id'             => $row->payroll_period_id,
            'month'                 => $row->period_month,
            'monthly_compensation'  => (float) $row->pagibig_compensation_base,
            'employee_rate'         => $row->pagibig_compensation_base > 0
                                        ? round((float) $row->pagibig_employee_contribution / (float) $row->pagibig_compensation_base * 100, 4)
                                        : 1.0,
            'employee_contribution' => (float) $row->pagibig_employee_contribution,
            'employer_contribution' => (float) $row->pagibig_employer_contribution,
            'total_contribution'    => (float) $row->pagibig_total_contribution,
            'is_processed'         => !is_null($row->processed_at),
            'is_remitted'          => false,
            'has_active_loan'      => false,
            'created_at'           => $row->created_at?->toISOString(),
            'updated_at'           => $row->updated_at?->toISOString(),
        ]);
    }

    private function mapBIRContributions(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => [
            'id'                       => $row->id,
            'employee_id'              => $row->employee_id,
            'employee_name'            => $row->employee?->full_name ?? $row->employee?->user?->name ?? '',
            'employee_number'          => $row->employee?->employee_number ?? '',
            'tin'                      => $row->tin ?? '',
            'period_id'                => $row->payroll_period_id,
            'month'                    => $row->period_month,
            'taxable_income'           => (float) $row->taxable_income,
            'gross_compensation'       => (float) $row->gross_compensation,
            'annualized_taxable_income' => (float) $row->annualized_taxable_income,
            'withholding_tax'          => (float) $row->withholding_tax,
            'tax_status'               => $row->tax_status ?? 'S',
            'is_minimum_wage_earner'   => (bool) $row->is_minimum_wage_earner,
            'ytd_tax_withheld'         => (float) $row->tax_already_withheld_ytd,
            'is_processed'             => !is_null($row->processed_at),
            'created_at'               => $row->created_at?->toISOString(),
            'updated_at'               => $row->updated_at?->toISOString(),
        ]);
    }

    private function emptyAgencySummary(string $agency): array
    {
        return match ($agency) {
            'sss' => [
                'total_employees'            => 0,
                'total_monthly_compensation' => 0,
                'total_employee_contribution' => 0,
                'total_employer_contribution' => 0,
                'total_ec_contribution'       => 0,
                'total_contribution'          => 0,
                'last_remittance_date'        => null,
                'next_due_date'              => Carbon::now()->addMonth()->startOfMonth()->addDays(9)->format('Y-m-d'),
                'pending_remittances'        => 0,
            ],
            'philhealth' => [
                'total_employees'            => 0,
                'total_monthly_basic'        => 0,
                'total_employee_premium'     => 0,
                'total_employer_premium'     => 0,
                'total_premium'              => 0,
                'last_remittance_date'       => null,
                'next_due_date'             => Carbon::now()->addMonth()->startOfMonth()->addDays(9)->format('Y-m-d'),
                'pending_remittances'       => 0,
                'indigent_members'          => 0,
            ],
            'pagibig' => [
                'total_employees'            => 0,
                'total_monthly_compensation' => 0,
                'total_employee_contribution' => 0,
                'total_employer_contribution' => 0,
                'total_contribution'          => 0,
                'total_loan_deductions'       => 0,
                'last_remittance_date'        => null,
                'next_due_date'              => Carbon::now()->addMonth()->startOfMonth()->addDays(9)->format('Y-m-d'),
                'pending_remittances'        => 0,
            ],
            'bir' => [
                'total_employees'           => 0,
                'total_gross_compensation'  => 0,
                'total_withholding_tax'     => 0,
                'reports_generated_count'   => 0,
                'reports_submitted_count'   => 0,
                'last_submission_date'      => null,
                'next_deadline'             => Carbon::now()->addMonth()->startOfMonth()->addDays(9)->format('Y-m-d'),
            ],
            default => [],
        };
    }
}
