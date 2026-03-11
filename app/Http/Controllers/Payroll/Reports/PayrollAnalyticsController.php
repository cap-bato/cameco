<?php

namespace App\Http\Controllers\Payroll\Reports;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'November 2025');

        try {
            $periodDate = Carbon::createFromFormat('F Y', $period)->startOfMonth();
        } catch (\Exception $e) {
            $periodDate = Carbon::now()->startOfMonth();
            $period = $periodDate->format('F Y');
        }

        $costTrendData = $this->getMonthlyLaborCostTrends($periodDate);
        $departmentComparisons = $this->getDepartmentComparisons($periodDate);
        $componentBreakdown = $this->getComponentBreakdown($periodDate);
        $yoyComparisons = $this->getYearOverYearComparisons($periodDate);
        $employeeCostAnalysis = $this->getEmployeeCostAnalysis($periodDate);
        $budgetVarianceData = $this->getBudgetVarianceData($periodDate);
        $forecastProjections = $this->getForecastProjections($periodDate);
        $analyticsSummary = $this->getAnalyticsSummary($periodDate);

        $availablePeriods = PayrollPeriod::orderByDesc('period_start')
            ->limit(12)
            ->get(['period_start'])
            ->map(fn($p) => Carbon::parse((string) $p->period_start)->format('F Y'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($availablePeriods)) {
            $availablePeriods = ['November 2025', 'October 2025', 'September 2025', 'August 2025', 'July 2025', 'June 2025'];
        }

        $availableDepartments = Department::select('id', 'name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->toArray();

        return Inertia::render('Payroll/Reports/Analytics', [
            'cost_trend_data'        => $costTrendData,
            'department_comparisons' => $departmentComparisons,
            'component_breakdown'    => $componentBreakdown,
            'yoy_comparisons'        => $yoyComparisons,
            'employee_cost_analysis' => $employeeCostAnalysis,
            'budget_variance_data'   => $budgetVarianceData,
            'forecast_projections'   => $forecastProjections,
            'analytics_summary'      => $analyticsSummary,
            'selected_period'        => $period,
            'available_periods'      => $availablePeriods,
            'available_departments'  => $availableDepartments,
        ]);
    }

    private function getMonthlyLaborCostTrends(Carbon $periodDate): array
    {
        $periods = PayrollPeriod::where('period_month', '<=', $periodDate->format('Y-m'))
            ->orderByDesc('period_month')
            ->limit(6)
            ->get()
            ->sortBy('period_month')
            ->values();

        if ($periods->isEmpty()) {
            return [];
        }

        $aggregates = EmployeePayrollCalculation::whereIn('payroll_period_id', $periods->pluck('id'))
            ->select(
                'payroll_period_id',
                DB::raw('SUM(gross_pay) as total_labor_cost'),
                DB::raw('SUM(basic_pay) as total_basic_salary'),
                DB::raw('SUM(total_allowances) as total_allowances'),
                DB::raw('SUM(total_overtime_pay) as total_overtime'),
                DB::raw('SUM(total_bonuses) as total_benefits'),
                DB::raw('SUM(total_government_deductions) as total_contributions'),
                DB::raw('SUM(withholding_tax) as total_taxes'),
                DB::raw('COUNT(*) as employee_count')
            )
            ->groupBy('payroll_period_id')
            ->get()
            ->keyBy('payroll_period_id');

        $trends = [];
        foreach ($periods as $period) {
            $agg       = $aggregates->get($period->id);
            $totalCost = $agg ? (float) $agg->total_labor_cost : 0;
            $empCount  = $agg ? (int) $agg->employee_count : 0;
            $start     = Carbon::parse((string) $period->period_start);

            $trends[] = [
                'month'               => $start->format('m'),
                'month_label'         => $start->format('M Y'),
                'total_labor_cost'    => (int) round($totalCost),
                'total_basic_salary'  => (int) round($agg ? (float) $agg->total_basic_salary : 0),
                'total_allowances'    => (int) round($agg ? (float) $agg->total_allowances : 0),
                'total_overtime'      => (int) round($agg ? (float) $agg->total_overtime : 0),
                'total_benefits'      => (int) round($agg ? (float) $agg->total_benefits : 0),
                'total_contributions' => (int) round($agg ? (float) $agg->total_contributions : 0),
                'total_taxes'         => (int) round($agg ? (float) $agg->total_taxes : 0),
                'employee_count'      => $empCount,
                'cost_per_employee'   => $empCount > 0 ? (int) round($totalCost / $empCount) : 0,
            ];
        }

        return $trends;
    }

    private function getDepartmentComparisons(Carbon $periodDate): array
    {
        $periodMonth   = $periodDate->format('Y-m');
        $currentPeriod = PayrollPeriod::where('period_month', $periodMonth)->first();

        if (!$currentPeriod) {
            return [];
        }

        $prevPeriodId = PayrollPeriod::where('period_month', '<', $periodMonth)
            ->orderByDesc('period_month')
            ->value('id');

        $currentAggs = EmployeePayrollCalculation::where('payroll_period_id', $currentPeriod->id)
            ->select(
                'department',
                DB::raw('COUNT(*) as total_employees'),
                DB::raw('SUM(gross_pay) as total_labor_cost'),
                DB::raw('SUM(basic_pay) as basic_salary_cost'),
                DB::raw('SUM(total_allowances) as allowances_cost'),
                DB::raw('SUM(total_overtime_pay) as overtime_cost'),
                DB::raw('SUM(total_bonuses) as benefits_cost'),
                DB::raw('SUM(total_government_deductions) as contributions_cost'),
                DB::raw('SUM(withholding_tax) as tax_cost')
            )
            ->groupBy('department')
            ->get()
            ->keyBy('department');

        $prevAggs = collect();
        if ($prevPeriodId) {
            $prevAggs = EmployeePayrollCalculation::where('payroll_period_id', $prevPeriodId)
                ->select('department', DB::raw('SUM(gross_pay) as total_labor_cost'))
                ->groupBy('department')
                ->get()
                ->keyBy('department');
        }

        $deptsByName = Department::select('id', 'name')->get()->keyBy('name');
        $grandTotal  = (float) $currentAggs->sum('total_labor_cost');
        $result      = [];
        $index       = 1;

        foreach ($currentAggs as $deptName => $agg) {
            $dept      = $deptsByName->get((string) $deptName);
            $deptId    = $dept ? $dept->id : $index;
            $totalCost = (float) $agg->total_labor_cost;
            $empCount  = (int) $agg->total_employees;

            $prevRow  = $prevAggs->get($deptName);
            $prevCost = $prevRow ? (float) $prevRow->total_labor_cost : $totalCost;
            $diff     = $totalCost - $prevCost;
            $trendPct = $prevCost > 0 ? ($diff / $prevCost) * 100 : 0;
            $trend    = $diff > 0.01 ? 'up' : ($diff < -0.01 ? 'down' : 'stable');

            $result[] = [
                'department_id'             => $deptId,
                'department_name'           => (string) $deptName,
                'total_employees'           => $empCount,
                'total_labor_cost'          => (int) round($totalCost),
                'basic_salary_cost'         => (int) round((float) $agg->basic_salary_cost),
                'allowances_cost'           => (int) round((float) $agg->allowances_cost),
                'overtime_cost'             => (int) round((float) $agg->overtime_cost),
                'benefits_cost'             => (int) round((float) $agg->benefits_cost),
                'contributions_cost'        => (int) round((float) $agg->contributions_cost),
                'tax_cost'                  => (int) round((float) $agg->tax_cost),
                'cost_percentage'           => $grandTotal > 0 ? round($totalCost / $grandTotal * 100, 2) : 0.0,
                'average_cost_per_employee' => $empCount > 0 ? (int) round($totalCost / $empCount) : 0,
                'trend'                     => $trend,
                'trend_percentage'          => round(abs($trendPct), 2),
            ];
            $index++;
        }

        return $result;
    }

    private function getComponentBreakdown(Carbon $periodDate): array
    {
        $periodMonth     = $periodDate->format('Y-m');
        $currentPeriodId = PayrollPeriod::where('period_month', $periodMonth)->value('id');

        if (!$currentPeriodId) {
            return [];
        }

        $prevPeriodId = PayrollPeriod::where('period_month', '<', $periodMonth)
            ->orderByDesc('period_month')
            ->value('id');

        $agg = EmployeePayrollCalculation::where('payroll_period_id', $currentPeriodId)
            ->select(
                DB::raw('SUM(basic_pay) as basic_salary'),
                DB::raw('SUM(total_allowances) as allowances'),
                DB::raw('SUM(total_overtime_pay) as overtime'),
                DB::raw('SUM(total_bonuses) as benefits'),
                DB::raw('SUM(total_government_deductions) as contributions'),
                DB::raw('SUM(withholding_tax) as taxes'),
                DB::raw('SUM(gross_pay) as gross_total'),
                DB::raw('COUNT(*) as employee_count')
            )
            ->first();

        if (!$agg || (float) $agg->gross_total == 0) {
            return [];
        }

        $prevAgg = null;
        if ($prevPeriodId) {
            $prevAgg = EmployeePayrollCalculation::where('payroll_period_id', $prevPeriodId)
                ->select(
                    DB::raw('SUM(basic_pay) as basic_salary'),
                    DB::raw('SUM(total_allowances) as allowances'),
                    DB::raw('SUM(total_overtime_pay) as overtime'),
                    DB::raw('SUM(total_bonuses) as benefits'),
                    DB::raw('SUM(total_government_deductions) as contributions'),
                    DB::raw('SUM(withholding_tax) as taxes')
                )
                ->first();
        }

        $grossTotal = (float) $agg->gross_total;
        $empCount   = (int) $agg->employee_count;

        $componentDefs = [
            ['id' => 1, 'name' => 'Basic Salary',             'code' => 'BASIC',   'type' => 'earning',      'key' => 'basic_salary'],
            ['id' => 2, 'name' => 'Allowances',               'code' => 'ALLOW',   'type' => 'earning',      'key' => 'allowances'],
            ['id' => 3, 'name' => 'Overtime',                 'code' => 'OT',      'type' => 'earning',      'key' => 'overtime'],
            ['id' => 4, 'name' => 'Benefits/Bonuses',         'code' => 'BENEF',   'type' => 'benefit',      'key' => 'benefits'],
            ['id' => 5, 'name' => 'Government Contributions', 'code' => 'CONTRIB', 'type' => 'contribution', 'key' => 'contributions'],
            ['id' => 6, 'name' => 'Withholding Tax',          'code' => 'WTAX',    'type' => 'tax',          'key' => 'taxes'],
        ];

        $breakdown = [];
        foreach ($componentDefs as $def) {
            $amount     = (float) ($agg->{$def['key']} ?? 0);
            $prevAmount = $prevAgg ? (float) ($prevAgg->{$def['key']} ?? 0) : $amount;
            $diff       = $amount - $prevAmount;
            $trendPct   = $prevAmount > 0 ? ($diff / $prevAmount) * 100 : 0;
            $trend      = $diff > 0.01 ? 'up' : ($diff < -0.01 ? 'down' : 'stable');

            $breakdown[] = [
                'component_id'         => $def['id'],
                'component_name'       => $def['name'],
                'component_code'       => $def['code'],
                'component_type'       => $def['type'],
                'total_amount'         => (int) round($amount),
                'percentage_of_gross'  => $grossTotal > 0 ? round($amount / $grossTotal * 100, 2) : 0.0,
                'affected_employees'   => $empCount,
                'average_per_employee' => $empCount > 0 ? (int) round($amount / $empCount) : 0,
                'trend'                => $trend,
                'trend_percentage'     => round(abs($trendPct), 2),
            ];
        }

        return $breakdown;
    }

    private function getYearOverYearComparisons(Carbon $periodDate): array
    {
        $currentMonths = [];
        $prevMonths    = [];
        $monthDates    = [];

        for ($i = 5; $i >= 0; $i--) {
            $date            = $periodDate->copy()->subMonths($i);
            $currentMonths[] = $date->format('Y-m');
            $prevMonths[]    = $date->copy()->subYear()->format('Y-m');
            $monthDates[]    = $date;
        }

        $currentPeriods = PayrollPeriod::whereIn('period_month', $currentMonths)->pluck('id', 'period_month');
        $prevPeriods    = PayrollPeriod::whereIn('period_month', $prevMonths)->pluck('id', 'period_month');

        $currentAggs = collect();
        if ($currentPeriods->isNotEmpty()) {
            $currentAggs = EmployeePayrollCalculation::whereIn('payroll_period_id', $currentPeriods->values())
                ->select('payroll_period_id', DB::raw('SUM(gross_pay) as total_cost'), DB::raw('COUNT(*) as employee_count'))
                ->groupBy('payroll_period_id')
                ->get()
                ->keyBy('payroll_period_id');
        }

        $prevAggs = collect();
        if ($prevPeriods->isNotEmpty()) {
            $prevAggs = EmployeePayrollCalculation::whereIn('payroll_period_id', $prevPeriods->values())
                ->select('payroll_period_id', DB::raw('SUM(gross_pay) as total_cost'), DB::raw('COUNT(*) as employee_count'))
                ->groupBy('payroll_period_id')
                ->get()
                ->keyBy('payroll_period_id');
        }

        $comparisons = [];
        foreach ($monthDates as $idx => $date) {
            $curPeriodId  = $currentPeriods->get($currentMonths[$idx]);
            $prevPeriodId = $prevPeriods->get($prevMonths[$idx]);

            $curData    = $curPeriodId ? $currentAggs->get($curPeriodId) : null;
            $prevData   = $prevPeriodId ? $prevAggs->get($prevPeriodId) : null;
            $curCost    = $curData ? (float) $curData->total_cost : 0;
            $prevCost   = $prevData ? (float) $prevData->total_cost : 0;
            $curEmpCnt  = $curData ? (int) $curData->employee_count : 0;
            $prevEmpCnt = $prevData ? (int) $prevData->employee_count : 0;
            $difference = $curCost - $prevCost;

            $comparisons[] = [
                'month'                      => $date->format('m'),
                'current_year_cost'          => (int) round($curCost),
                'previous_year_cost'         => (int) round($prevCost),
                'difference'                 => (int) round($difference),
                'percentage_change'          => $prevCost > 0 ? round($difference / $prevCost * 100, 2) : 0.0,
                'current_year_employees'     => $curEmpCnt,
                'previous_year_employees'    => $prevEmpCnt,
                'cost_per_employee_current'  => $curEmpCnt > 0 ? (int) round($curCost / $curEmpCnt) : 0,
                'cost_per_employee_previous' => $prevEmpCnt > 0 ? (int) round($prevCost / $prevEmpCnt) : 0,
            ];
        }

        return $comparisons;
    }

    private function getEmployeeCostAnalysis(Carbon $periodDate): array
    {
        $periodMonth = $periodDate->format('Y-m');
        $periodId    = PayrollPeriod::where('period_month', $periodMonth)->value('id');

        if (!$periodId) {
            return [];
        }

        $calculations = EmployeePayrollCalculation::where('payroll_period_id', $periodId)
            ->select([
                'id', 'employee_id', 'employee_name', 'employee_number',
                'department', 'position',
                'basic_pay', 'gross_pay', 'total_deductions', 'net_pay',
                'total_allowances', 'total_overtime_pay', 'total_bonuses',
                'total_government_deductions',
            ])
            ->limit(50)
            ->get();

        if ($calculations->isEmpty()) {
            return [];
        }

        $deptsByName = Department::select('id', 'name')->get()->keyBy('name');
        $deptAvgs    = $calculations->groupBy('department')->map(fn($g) => (float) $g->avg('gross_pay'));
        $posAvgs     = $calculations->groupBy('position')->map(fn($g) => (float) $g->avg('gross_pay'));

        $result = [];
        foreach ($calculations as $calc) {
            $dept     = $deptsByName->get((string) $calc->department);
            $deptId   = $dept ? $dept->id : 0;
            $grossPay = (float) $calc->gross_pay;
            $deptAvg  = $deptAvgs->get($calc->department, $grossPay);
            $posAvg   = $posAvgs->get($calc->position, $grossPay);

            $totalGross = (float) $calc->basic_pay + (float) $calc->total_allowances + (float) $calc->total_overtime_pay;
            $ctc        = $totalGross + (float) $calc->total_bonuses + (float) $calc->total_government_deductions;

            $result[] = [
                'employee_id'          => $calc->employee_id,
                'employee_name'        => (string) ($calc->employee_name ?? ''),
                'employee_code'        => (string) ($calc->employee_number ?? ''),
                'department_id'        => $deptId,
                'department_name'      => (string) ($calc->department ?? ''),
                'position'             => (string) ($calc->position ?? ''),
                'basic_salary'         => (int) round((float) $calc->basic_pay),
                'total_gross_pay'      => (int) round($totalGross),
                'total_deductions'     => (int) round((float) $calc->total_deductions),
                'net_pay'              => (int) round((float) $calc->net_pay),
                'cost_to_company'      => (int) round($ctc),
                'component_breakdown'  => [
                    ['component_name' => 'Basic Salary',     'component_type' => 'earning', 'amount' => (int) round((float) $calc->basic_pay)],
                    ['component_name' => 'Allowances',       'component_type' => 'earning', 'amount' => (int) round((float) $calc->total_allowances)],
                    ['component_name' => 'Overtime',         'component_type' => 'earning', 'amount' => (int) round((float) $calc->total_overtime_pay)],
                    ['component_name' => 'Benefits/Bonuses', 'component_type' => 'benefit', 'amount' => (int) round((float) $calc->total_bonuses)],
                ],
                'vs_department_average' => (int) round($grossPay - $deptAvg),
                'vs_position_average'   => (int) round($grossPay - $posAvg),
            ];
        }

        return $result;
    }

    private function getBudgetVarianceData(Carbon $periodDate): array
    {
        $periodMonth = $periodDate->format('Y-m');
        $periodId    = PayrollPeriod::where('period_month', $periodMonth)->value('id');

        if (!$periodId) {
            return [];
        }

        $actuals = EmployeePayrollCalculation::where('payroll_period_id', $periodId)
            ->select(
                'department',
                DB::raw('SUM(basic_pay) as basic_salary'),
                DB::raw('SUM(total_allowances) as allowances'),
                DB::raw('SUM(total_overtime_pay) as overtime'),
                DB::raw('SUM(total_bonuses) as benefits'),
                DB::raw('SUM(total_government_deductions) as contributions')
            )
            ->groupBy('department')
            ->get();

        if ($actuals->isEmpty()) {
            return [];
        }

        $deptsByName  = Department::select('id', 'name', 'budget')->get()->keyBy('name');
        $compLabels   = ['Basic Salary', 'Allowances', 'Overtime', 'Benefits/Bonuses', 'Contributions'];
        $compKeys     = ['basic_salary', 'allowances', 'overtime', 'benefits', 'contributions'];
        $budgetRatios = [0.60, 0.15, 0.08, 0.10, 0.05];

        $variances = [];
        $deptIndex = 1;

        foreach ($actuals as $actual) {
            $dept          = $deptsByName->get((string) $actual->department);
            $deptId        = $dept ? $dept->id : $deptIndex;
            $monthlyBudget = ($dept && $dept->budget > 0) ? $dept->budget / 12 : 0;

            foreach ($compLabels as $k => $label) {
                $actualAmt   = (float) ($actual->{$compKeys[$k]} ?? 0);
                $budgetedAmt = $monthlyBudget > 0
                    ? (int) round($monthlyBudget * $budgetRatios[$k])
                    : (int) round($actualAmt);
                $variance    = (int) round($actualAmt - $budgetedAmt);
                $variancePct = $budgetedAmt > 0 ? round(($actualAmt - $budgetedAmt) / $budgetedAmt * 100, 2) : 0.0;
                $status      = abs($variancePct) <= 2 ? 'on_target' : ($variance <= 0 ? 'favorable' : 'unfavorable');

                $variances[] = [
                    'department_id'       => $deptId,
                    'department_name'     => (string) $actual->department,
                    'component_name'      => $label,
                    'budgeted_amount'     => $budgetedAmt,
                    'actual_amount'       => (int) round($actualAmt),
                    'variance'            => $variance,
                    'variance_percentage' => $variancePct,
                    'variance_status'     => $status,
                    'remaining_budget'    => $budgetedAmt - (int) round($actualAmt),
                ];
            }
            $deptIndex++;
        }

        return $variances;
    }

    private function getForecastProjections(Carbon $periodDate): array
    {
        $historicalPeriods = PayrollPeriod::where('period_month', '<=', $periodDate->format('Y-m'))
            ->orderByDesc('period_month')
            ->limit(6)
            ->get()
            ->sortBy('period_month')
            ->values();

        if ($historicalPeriods->isEmpty()) {
            return [];
        }

        $aggregates = EmployeePayrollCalculation::whereIn('payroll_period_id', $historicalPeriods->pluck('id'))
            ->select('payroll_period_id', DB::raw('SUM(gross_pay) as total_cost'), DB::raw('COUNT(*) as employee_count'))
            ->groupBy('payroll_period_id')
            ->get()
            ->keyBy('payroll_period_id');

        $historicalCosts = $historicalPeriods
            ->map(fn($p) => $aggregates->has($p->id) ? (float) $aggregates->get($p->id)->total_cost : 0)
            ->toArray();

        $lastPeriod   = $historicalPeriods->last();
        $lastAgg      = $lastPeriod ? $aggregates->get($lastPeriod->id) : null;
        $baseCost     = !empty($historicalCosts) ? end($historicalCosts) : 0;
        $baseEmpCount = $lastAgg ? (int) $lastAgg->employee_count : 0;

        $growthRates = [];
        for ($i = 1; $i < count($historicalCosts); $i++) {
            if ($historicalCosts[$i - 1] > 0 && $historicalCosts[$i] > 0) {
                $growthRates[] = ($historicalCosts[$i] - $historicalCosts[$i - 1]) / $historicalCosts[$i - 1];
            }
        }
        $avgGrowth = !empty($growthRates) ? array_sum($growthRates) / count($growthRates) : 0.02;
        $avgGrowth = max(-0.10, min(0.20, $avgGrowth)); // clamp to [-10%, +20%]

        $projections = [];
        for ($i = 1; $i <= 6; $i++) {
            $date          = $periodDate->copy()->addMonths($i);
            $projectedCost = $baseCost > 0 ? (int) round($baseCost * pow(1 + $avgGrowth, $i)) : 0;

            $projections[] = [
                'month'                    => $date->format('m'),
                'month_label'              => $date->format('M Y'),
                'projected_labor_cost'     => $projectedCost,
                'projected_basic_salary'   => (int) round($projectedCost * 0.60),
                'projected_allowances'     => (int) round($projectedCost * 0.15),
                'projected_overtime'       => (int) round($projectedCost * 0.08),
                'projected_benefits'       => (int) round($projectedCost * 0.10),
                'projected_contributions'  => (int) round($projectedCost * 0.05),
                'projected_taxes'          => (int) round($projectedCost * 0.02),
                'projected_employee_count' => $baseEmpCount + ($i - 1),
                'confidence_level'         => $i <= 2 ? 'high' : ($i <= 4 ? 'medium' : 'low'),
            ];
        }

        return $projections;
    }

    private function getAnalyticsSummary(Carbon $periodDate): array
    {
        $periodMonth   = $periodDate->format('Y-m');
        $prevMonth     = $periodDate->copy()->subMonth()->format('Y-m');
        $prevYearMonth = $periodDate->copy()->subYear()->format('Y-m');

        $currentPeriodId = PayrollPeriod::where('period_month', $periodMonth)->value('id');

        if (!$currentPeriodId) {
            return [
                'current_period'                    => $periodDate->format('F Y'),
                'total_labor_cost'                  => 0,
                'average_monthly_cost'              => 0,
                'total_employees'                   => 0,
                'average_cost_per_employee'         => 0,
                'largest_cost_component'            => 'N/A',
                'largest_cost_component_amount'     => 0,
                'largest_cost_component_percentage' => 0.0,
                'highest_cost_department'           => 'N/A',
                'highest_cost_department_amount'    => 0,
                'trend_vs_last_period'              => 0.0,
                'trend_vs_last_year'                => 0.0,
            ];
        }

        $agg = EmployeePayrollCalculation::where('payroll_period_id', $currentPeriodId)
            ->select(
                DB::raw('SUM(gross_pay) as total_cost'),
                DB::raw('SUM(basic_pay) as basic_salary'),
                DB::raw('SUM(total_allowances) as allowances'),
                DB::raw('SUM(total_overtime_pay) as overtime'),
                DB::raw('SUM(total_bonuses) as benefits'),
                DB::raw('SUM(total_government_deductions) as contributions'),
                DB::raw('COUNT(*) as employee_count')
            )
            ->first();

        $totalCost = $agg ? (float) $agg->total_cost : 0;
        $empCount  = $agg ? (int) $agg->employee_count : 0;

        $topDept = EmployeePayrollCalculation::where('payroll_period_id', $currentPeriodId)
            ->select('department', DB::raw('SUM(gross_pay) as dept_cost'))
            ->groupBy('department')
            ->orderByDesc('dept_cost')
            ->first();

        $prevPeriodId     = PayrollPeriod::where('period_month', $prevMonth)->value('id');
        $prevYearPeriodId = PayrollPeriod::where('period_month', $prevYearMonth)->value('id');

        $prevCost     = $prevPeriodId ? (float) EmployeePayrollCalculation::where('payroll_period_id', $prevPeriodId)->sum('gross_pay') : 0;
        $prevYearCost = $prevYearPeriodId ? (float) EmployeePayrollCalculation::where('payroll_period_id', $prevYearPeriodId)->sum('gross_pay') : 0;

        $last6Ids = PayrollPeriod::where('period_month', '<=', $periodMonth)->orderByDesc('period_month')->limit(6)->pluck('id');
        $avgMonthlyCost = 0;
        if ($last6Ids->isNotEmpty()) {
            $total6 = (float) EmployeePayrollCalculation::whereIn('payroll_period_id', $last6Ids)->sum('gross_pay');
            $avgMonthlyCost = (int) round($total6 / $last6Ids->count());
        }

        $components = [
            'Basic Salary'             => $agg ? (float) $agg->basic_salary : 0,
            'Allowances'               => $agg ? (float) $agg->allowances : 0,
            'Overtime'                 => $agg ? (float) $agg->overtime : 0,
            'Benefits/Bonuses'         => $agg ? (float) $agg->benefits : 0,
            'Government Contributions' => $agg ? (float) $agg->contributions : 0,
        ];
        arsort($components);
        $largestName   = (string) (array_key_first($components) ?? 'N/A');
        $largestAmount = (float) ($components[$largestName] ?? 0);
        $largestPct    = $totalCost > 0 ? round($largestAmount / $totalCost * 100, 2) : 0.0;

        return [
            'current_period'                    => $periodDate->format('F Y'),
            'total_labor_cost'                  => (int) round($totalCost),
            'average_monthly_cost'              => $avgMonthlyCost,
            'total_employees'                   => $empCount,
            'average_cost_per_employee'         => $empCount > 0 ? (int) round($totalCost / $empCount) : 0,
            'largest_cost_component'            => $largestName,
            'largest_cost_component_amount'     => (int) round($largestAmount),
            'largest_cost_component_percentage' => $largestPct,
            'highest_cost_department'           => $topDept ? (string) $topDept->department : 'N/A',
            'highest_cost_department_amount'    => $topDept ? (int) round((float) $topDept->dept_cost) : 0,
            'trend_vs_last_period'              => $prevCost > 0 ? round(($totalCost - $prevCost) / $prevCost * 100, 2) : 0.0,
            'trend_vs_last_year'                => $prevYearCost > 0 ? round(($totalCost - $prevYearCost) / $prevYearCost * 100, 2) : 0.0,
        ];
    }
}
