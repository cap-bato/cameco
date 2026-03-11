<?php

namespace App\Http\Controllers\Payroll\Reports;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollRegisterController extends Controller
{
    /**
     * Display the Payroll Register Report page
     */
    public function index(Request $request)
    {
        $periodId      = $request->input('period_id', 'all');
        $departmentId  = $request->input('department_id', 'all');
        $employeeStatus = $request->input('employee_status', 'all');
        $componentFilter = $request->input('component_filter', 'all');
        $search        = $request->input('search', '');

        // ------------------------------------------------------------------
        // Periods list (last 12 from DB)
        // ------------------------------------------------------------------
        $periods = PayrollPeriod::orderByDesc('period_start')
            ->limit(12)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->period_name,
                'period_type' => $p->period_type ?? 'semi_monthly',
                'start_date'  => $p->period_start?->format('Y-m-d'),
                'end_date'    => $p->period_end?->format('Y-m-d'),
                'pay_date'    => $p->payment_date?->format('Y-m-d'),
            ])
            ->toArray();

        // Resolve selected period
        $resolvedId     = ($periodId !== 'all') ? (int) $periodId : null;
        $selectedPeriod = $resolvedId
            ? PayrollPeriod::find($resolvedId)
            : PayrollPeriod::orderByDesc('period_start')->first();

        // ------------------------------------------------------------------
        // Departments & static status list
        // ------------------------------------------------------------------
        $departments = Department::select('id', 'name')->get()->toArray();

        $employeeStatuses = [
            ['id' => 'active',   'label' => 'Active'],
            ['id' => 'inactive', 'label' => 'Inactive'],
            ['id' => 'on_leave', 'label' => 'On Leave'],
        ];

        // ------------------------------------------------------------------
        // Salary components from DB (id, code, name, type)
        // ------------------------------------------------------------------
        $components = SalaryComponent::where('is_active', true)
            ->select('id', 'code', 'name', 'component_type')
            ->orderBy('display_order')
            ->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->component_type,
            ])
            ->toArray();

        // ------------------------------------------------------------------
        // Employee payroll calculations for the selected period
        // ------------------------------------------------------------------
        $registerData      = collect();
        $summary           = $this->buildEmptySummary();
        $departmentBreakdown = [];

        if ($selectedPeriod) {
            $query = EmployeePayrollCalculation::with('employee')
                ->where('payroll_period_id', $selectedPeriod->id);

            // Department filter
            if ($departmentId !== 'all' && $departmentId) {
                $query->whereHas('employee', fn($q) => $q->where('department_id', (int) $departmentId));
            }

            // Employment status filter
            if ($employeeStatus !== 'all') {
                $statusValues = match ($employeeStatus) {
                    'inactive' => ['inactive', 'terminated', 'archived'],
                    default    => [$employeeStatus],
                };
                $query->whereIn('employment_status', $statusValues);
            }

            // Search filter (PostgreSQL ilike for case-insensitive)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_name', 'ilike', '%' . $search . '%')
                      ->orWhere('employee_number', 'ilike', '%' . $search . '%');
                });
            }

            $calculations = $query->get();

            $statusMap = [
                'active'     => 'active',
                'on_leave'   => 'on_leave',
                'inactive'   => 'inactive',
                'terminated' => 'inactive',
                'archived'   => 'inactive',
            ];

            $registerData = $calculations->map(function ($calc) use ($statusMap) {
                $deptId  = $calc->employee?->department_id ?? 0;
                $netPay  = (float) ($calc->final_net_pay ?? $calc->net_pay ?? 0);

                return [
                    'id'              => $calc->id,
                    'employee_id'     => $calc->employee_id,
                    'period_id'       => $calc->payroll_period_id,
                    'employee_number' => $calc->employee_number ?? '',
                    'full_name'       => $calc->employee_name ?? '',
                    'department_id'   => $deptId,
                    'department_name' => $calc->department ?? '',
                    'position'        => $calc->position ?? '',
                    'status'          => $statusMap[$calc->employment_status ?? 'active'] ?? 'active',
                    // Earnings
                    'basic_salary'    => (float) ($calc->basic_pay ?? 0),
                    'overtime'        => (float) ($calc->total_overtime_pay ?? 0),
                    'rice_allowance'  => (float) ($calc->meal_allowance ?? 0),
                    'cola'            => (float) ($calc->communication_allowance ?? 0),
                    'gross_pay'       => (float) ($calc->gross_pay ?? 0),
                    // Deductions
                    'sss'             => (float) ($calc->sss_contribution ?? 0),
                    'philhealth'      => (float) ($calc->philhealth_contribution ?? 0),
                    'pagibig'         => (float) ($calc->pagibig_contribution ?? 0),
                    'withholding_tax' => (float) ($calc->withholding_tax ?? 0),
                    'total_deductions' => (float) ($calc->total_deductions ?? 0),
                    // Net Pay
                    'net_pay'         => $netPay,
                    // Component breakdown (matches existing frontend shape)
                    'components'      => [
                        ['code' => 'BASIC',      'name' => 'Basic Salary',       'type' => 'earning',   'amount' => (float) ($calc->basic_pay ?? 0)],
                        ['code' => 'OT_REG',     'name' => 'Regular Overtime',   'type' => 'earning',   'amount' => (float) ($calc->total_overtime_pay ?? 0)],
                        ['code' => 'RICE',       'name' => 'Rice Allowance',     'type' => 'benefit',   'amount' => (float) ($calc->meal_allowance ?? 0)],
                        ['code' => 'COLA',       'name' => 'COLA',               'type' => 'allowance', 'amount' => (float) ($calc->communication_allowance ?? 0)],
                        ['code' => 'SSS',        'name' => 'SSS Contribution',   'type' => 'deduction', 'amount' => -(float) ($calc->sss_contribution ?? 0)],
                        ['code' => 'PHILHEALTH', 'name' => 'PhilHealth',         'type' => 'deduction', 'amount' => -(float) ($calc->philhealth_contribution ?? 0)],
                        ['code' => 'PAGIBIG',    'name' => 'Pag-IBIG',           'type' => 'deduction', 'amount' => -(float) ($calc->pagibig_contribution ?? 0)],
                        ['code' => 'WTAX',       'name' => 'Withholding Tax',    'type' => 'tax',       'amount' => -(float) ($calc->withholding_tax ?? 0)],
                    ],
                ];
            });

            $summary           = $this->buildSummary($registerData);
            $departmentBreakdown = $this->buildDepartmentBreakdown($registerData);
        }

        return Inertia::render('Payroll/Reports/Register/Index', [
            'register_data'        => $registerData->values()->toArray(),
            'summary'              => $summary,
            'department_breakdown' => $departmentBreakdown,
            'periods'              => $periods,
            'departments'          => $departments,
            'employee_statuses'    => $employeeStatuses,
            'salary_components'    => $components,
            'filters'              => [
                'period_id'       => $periodId,
                'department_id'   => $departmentId,
                'employee_status' => $employeeStatus,
                'component_filter' => $componentFilter,
                'search'          => $search,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function buildEmptySummary(): array
    {
        return [
            'total_employees'            => 0,
            'total_gross_pay'            => 0,
            'total_deductions'           => 0,
            'total_net_pay'              => 0,
            'formatted_total_gross_pay'  => '₱0.00',
            'formatted_total_deductions' => '₱0.00',
            'formatted_total_net_pay'    => '₱0.00',
        ];
    }

    private function buildSummary(\Illuminate\Support\Collection $registerData): array
    {
        $totalGross      = $registerData->sum('gross_pay');
        $totalDeductions = $registerData->sum('total_deductions');
        $totalNet        = $registerData->sum('net_pay');

        return [
            'total_employees'            => $registerData->count(),
            'total_gross_pay'            => $totalGross,
            'total_deductions'           => $totalDeductions,
            'total_net_pay'              => $totalNet,
            'formatted_total_gross_pay'  => '₱' . number_format($totalGross, 2),
            'formatted_total_deductions' => '₱' . number_format($totalDeductions, 2),
            'formatted_total_net_pay'    => '₱' . number_format($totalNet, 2),
        ];
    }

    private function buildDepartmentBreakdown(\Illuminate\Support\Collection $registerData): array
    {
        return $registerData
            ->groupBy('department_id')
            ->map(function ($rows, $deptId) {
                $grossTotal = $rows->sum('gross_pay');
                $deductions = $rows->sum('total_deductions');
                $netTotal   = $rows->sum('net_pay');
                $count      = $rows->count();
                $avgNet     = $count > 0 ? $netTotal / $count : 0;

                return [
                    'department_id'               => $deptId,
                    'department_name'             => $rows->first()['department_name'],
                    'employee_count'              => $count,
                    'total_gross_pay'             => $grossTotal,
                    'total_deductions'            => $deductions,
                    'total_net_pay'               => $netTotal,
                    'average_net_pay'             => $avgNet,
                    'formatted_gross_pay'         => '₱' . number_format($grossTotal, 2),
                    'formatted_deductions'        => '₱' . number_format($deductions, 2),
                    'formatted_net_pay'           => '₱' . number_format($netTotal, 2),
                    'formatted_average_net_pay'   => '₱' . number_format($avgNet, 2),
                ];
            })
            ->values()
            ->toArray();
    }
}
