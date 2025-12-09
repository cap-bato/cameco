<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRulesController extends Controller
{
    /**
     * Display payroll rules configuration page.
     * 
     * Shows salary structures, deductions, government rates, and payment methods.
     * Office Admin configures all payroll-related rules and rates.
     */
    public function index(Request $request): Response
    {
        // Get all payroll rule settings
        $settings = SystemSetting::whereIn('category', ['payroll', 'government_rates', 'payment_methods'])
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                return $items->pluck('value', 'key');
            });

        $payrollRules = [
            'salary_structure' => [
                'minimum_wage' => (float)($settings->get('payroll')['payroll.minimum_wage'] ?? 570),
                'salary_grades_enabled' => (bool)($settings->get('payroll')['payroll.salary_grades_enabled'] ?? false),
                'salary_grades_count' => (int)($settings->get('payroll')['payroll.salary_grades_count'] ?? 15),
            ],
            'allowances' => [
                'housing_allowance' => (float)($settings->get('payroll')['payroll.allowances.housing'] ?? 0),
                'transportation_allowance' => (float)($settings->get('payroll')['payroll.allowances.transportation'] ?? 0),
                'meal_allowance' => (float)($settings->get('payroll')['payroll.allowances.meal'] ?? 0),
                'communication_allowance' => (float)($settings->get('payroll')['payroll.allowances.communication'] ?? 0),
            ],
            'bonuses' => [
                'thirteenth_month_enabled' => (bool)($settings->get('payroll')['payroll.bonuses.thirteenth_month_enabled'] ?? true),
                'performance_bonus_enabled' => (bool)($settings->get('payroll')['payroll.bonuses.performance_enabled'] ?? false),
            ],
            'government_rates' => [
                // SSS
                'sss_employee_rate' => (float)($settings->get('government_rates')['government.sss.employee_rate'] ?? 4.5),
                'sss_employer_rate' => (float)($settings->get('government_rates')['government.sss.employer_rate'] ?? 9.5),
                'sss_max_salary' => (float)($settings->get('government_rates')['government.sss.max_salary'] ?? 30000),
                'sss_effective_date' => $settings->get('government_rates')['government.sss.effective_date'] ?? null,
                
                // PhilHealth
                'philhealth_rate' => (float)($settings->get('government_rates')['government.philhealth.rate'] ?? 5.0),
                'philhealth_employee_share' => (float)($settings->get('government_rates')['government.philhealth.employee_share'] ?? 2.5),
                'philhealth_employer_share' => (float)($settings->get('government_rates')['government.philhealth.employer_share'] ?? 2.5),
                'philhealth_min_salary' => (float)($settings->get('government_rates')['government.philhealth.min_salary'] ?? 10000),
                'philhealth_max_salary' => (float)($settings->get('government_rates')['government.philhealth.max_salary'] ?? 100000),
                'philhealth_effective_date' => $settings->get('government_rates')['government.philhealth.effective_date'] ?? null,
                
                // Pag-IBIG
                'pagibig_employee_rate' => (float)($settings->get('government_rates')['government.pagibig.employee_rate'] ?? 2.0),
                'pagibig_employer_rate' => (float)($settings->get('government_rates')['government.pagibig.employer_rate'] ?? 2.0),
                'pagibig_max_salary' => (float)($settings->get('government_rates')['government.pagibig.max_salary'] ?? 5000),
                'pagibig_effective_date' => $settings->get('government_rates')['government.pagibig.effective_date'] ?? null,
                
                // Withholding Tax (BIR)
                'tax_brackets' => $this->getTaxBrackets($settings),
            ],
            'payment_methods' => [
                'default_method' => $settings->get('payment_methods')['payment.default_method'] ?? 'cash',
                'cash_enabled' => (bool)($settings->get('payment_methods')['payment.cash_enabled'] ?? true),
                'bank_transfer_enabled' => (bool)($settings->get('payment_methods')['payment.bank_transfer_enabled'] ?? false),
                'ewallet_enabled' => (bool)($settings->get('payment_methods')['payment.ewallet_enabled'] ?? false),
                'payment_schedule' => $settings->get('payment_methods')['payment.schedule'] ?? 'bi-monthly',
                'cutoff_dates' => $this->getCutoffDates($settings),
            ],
        ];

        return Inertia::render('Admin/PayrollRules/Index', [
            'payrollRules' => $payrollRules,
        ]);
    }

    /**
     * Update salary structure configuration.
     */
    public function updateSalaryStructure(Request $request)
    {
        $validated = $request->validate([
            'minimum_wage' => 'required|numeric|min:0',
            'salary_grades_enabled' => 'boolean',
            'salary_grades_count' => 'required|integer|min:1|max:30',
        ]);

        $this->updateSettings('payroll', [
            'payroll.minimum_wage' => $validated['minimum_wage'],
            'payroll.salary_grades_enabled' => $validated['salary_grades_enabled'],
            'payroll.salary_grades_count' => $validated['salary_grades_count'],
        ], $request->user());

        return redirect()->route('admin.payroll-rules.index')
            ->with('success', 'Salary structure updated successfully.');
    }

    /**
     * Update allowances configuration.
     */
    public function updateAllowances(Request $request)
    {
        $validated = $request->validate([
            'housing_allowance' => 'required|numeric|min:0',
            'transportation_allowance' => 'required|numeric|min:0',
            'meal_allowance' => 'required|numeric|min:0',
            'communication_allowance' => 'required|numeric|min:0',
        ]);

        $this->updateSettings('payroll', [
            'payroll.allowances.housing' => $validated['housing_allowance'],
            'payroll.allowances.transportation' => $validated['transportation_allowance'],
            'payroll.allowances.meal' => $validated['meal_allowance'],
            'payroll.allowances.communication' => $validated['communication_allowance'],
        ], $request->user());

        return redirect()->route('admin.payroll-rules.index')
            ->with('success', 'Allowances updated successfully.');
    }

    /**
     * Update government rates (SSS, PhilHealth, Pag-IBIG).
     */
    public function updateGovernmentRates(Request $request)
    {
        $validated = $request->validate([
            'agency' => 'required|in:sss,philhealth,pagibig',
            'effective_date' => 'required|date',
            
            // SSS rates
            'sss_employee_rate' => 'required_if:agency,sss|nullable|numeric|min:0|max:100',
            'sss_employer_rate' => 'required_if:agency,sss|nullable|numeric|min:0|max:100',
            'sss_max_salary' => 'required_if:agency,sss|nullable|numeric|min:0',
            
            // PhilHealth rates
            'philhealth_rate' => 'required_if:agency,philhealth|nullable|numeric|min:0|max:100',
            'philhealth_employee_share' => 'required_if:agency,philhealth|nullable|numeric|min:0|max:100',
            'philhealth_employer_share' => 'required_if:agency,philhealth|nullable|numeric|min:0|max:100',
            'philhealth_min_salary' => 'required_if:agency,philhealth|nullable|numeric|min:0',
            'philhealth_max_salary' => 'required_if:agency,philhealth|nullable|numeric|min:0',
            
            // Pag-IBIG rates
            'pagibig_employee_rate' => 'required_if:agency,pagibig|nullable|numeric|min:0|max:100',
            'pagibig_employer_rate' => 'required_if:agency,pagibig|nullable|numeric|min:0|max:100',
            'pagibig_max_salary' => 'required_if:agency,pagibig|nullable|numeric|min:0',
        ]);

        $agency = $validated['agency'];
        $settings = [];

        switch ($agency) {
            case 'sss':
                $settings = [
                    'government.sss.employee_rate' => $validated['sss_employee_rate'],
                    'government.sss.employer_rate' => $validated['sss_employer_rate'],
                    'government.sss.max_salary' => $validated['sss_max_salary'],
                    'government.sss.effective_date' => $validated['effective_date'],
                ];
                break;
                
            case 'philhealth':
                $settings = [
                    'government.philhealth.rate' => $validated['philhealth_rate'],
                    'government.philhealth.employee_share' => $validated['philhealth_employee_share'],
                    'government.philhealth.employer_share' => $validated['philhealth_employer_share'],
                    'government.philhealth.min_salary' => $validated['philhealth_min_salary'],
                    'government.philhealth.max_salary' => $validated['philhealth_max_salary'],
                    'government.philhealth.effective_date' => $validated['effective_date'],
                ];
                break;
                
            case 'pagibig':
                $settings = [
                    'government.pagibig.employee_rate' => $validated['pagibig_employee_rate'],
                    'government.pagibig.employer_rate' => $validated['pagibig_employer_rate'],
                    'government.pagibig.max_salary' => $validated['pagibig_max_salary'],
                    'government.pagibig.effective_date' => $validated['effective_date'],
                ];
                break;
        }

        $this->updateSettings('government_rates', $settings, $request->user());

        return redirect()->route('admin.payroll-rules.index')
            ->with('success', strtoupper($agency) . ' rates updated successfully.');
    }

    /**
     * Update withholding tax table (BIR tax brackets).
     */
    public function updateWithholdingTax(Request $request)
    {
        $validated = $request->validate([
            'tax_brackets' => 'required|array|min:1',
            'tax_brackets.*.min' => 'required|numeric|min:0',
            'tax_brackets.*.max' => 'nullable|numeric|min:0',
            'tax_brackets.*.base_tax' => 'required|numeric|min:0',
            'tax_brackets.*.rate' => 'required|numeric|min:0|max:100',
            'effective_date' => 'required|date',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'government.bir.tax_brackets'],
            [
                'value' => json_encode($validated['tax_brackets']),
                'type' => 'json',
                'category' => 'government_rates',
                'description' => 'BIR withholding tax brackets',
            ]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'government.bir.effective_date'],
            [
                'value' => $validated['effective_date'],
                'type' => 'date',
                'category' => 'government_rates',
                'description' => 'BIR tax table effective date',
            ]
        );

        activity('payroll_configuration')
            ->causedBy($request->user())
            ->withProperties([
                'brackets_count' => count($validated['tax_brackets']),
                'effective_date' => $validated['effective_date'],
            ])
            ->log('Updated BIR withholding tax brackets');

        return redirect()->route('admin.payroll-rules.index')
            ->with('success', 'Withholding tax table updated successfully.');
    }

    /**
     * Update payment methods configuration.
     */
    public function updatePaymentMethods(Request $request)
    {
        $validated = $request->validate([
            'default_method' => 'required|in:cash,bank_transfer,ewallet',
            'cash_enabled' => 'boolean',
            'bank_transfer_enabled' => 'boolean',
            'ewallet_enabled' => 'boolean',
            'payment_schedule' => 'required|in:weekly,bi-monthly,monthly',
            'cutoff_1st' => 'required_if:payment_schedule,bi-monthly|nullable|integer|min:1|max:31',
            'cutoff_2nd' => 'required_if:payment_schedule,bi-monthly|nullable|integer|min:1|max:31',
            'cutoff_monthly' => 'required_if:payment_schedule,monthly|nullable|integer|min:1|max:31',
        ]);

        $this->updateSettings('payment_methods', [
            'payment.default_method' => $validated['default_method'],
            'payment.cash_enabled' => $validated['cash_enabled'] ?? false,
            'payment.bank_transfer_enabled' => $validated['bank_transfer_enabled'] ?? false,
            'payment.ewallet_enabled' => $validated['ewallet_enabled'] ?? false,
            'payment.schedule' => $validated['payment_schedule'],
        ], $request->user());

        // Handle cutoff dates based on schedule
        if ($validated['payment_schedule'] === 'bi-monthly') {
            $cutoffDates = [
                'first' => $validated['cutoff_1st'],
                'second' => $validated['cutoff_2nd'],
            ];
        } elseif ($validated['payment_schedule'] === 'monthly') {
            $cutoffDates = [
                'day' => $validated['cutoff_monthly'],
            ];
        } else {
            $cutoffDates = [];
        }

        SystemSetting::updateOrCreate(
            ['key' => 'payment.cutoff_dates'],
            [
                'value' => json_encode($cutoffDates),
                'type' => 'json',
                'category' => 'payment_methods',
                'description' => 'Payment cutoff dates configuration',
            ]
        );

        return redirect()->route('admin.payroll-rules.index')
            ->with('success', 'Payment methods updated successfully.');
    }

    /**
     * Helper method to update multiple settings at once.
     * 
     * @param string $category
     * @param array $settings
     * @param \App\Models\User $user
     */
    private function updateSettings(string $category, array $settings, $user): void
    {
        foreach ($settings as $key => $value) {
            $setting = SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                    'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string'),
                    'category' => $category,
                    'description' => $this->getSettingDescription($key),
                ]
            );

            activity('payroll_configuration')
                ->causedBy($user)
                ->performedOn($setting)
                ->withProperties([
                    'key' => $key,
                    'old_value' => $setting->getOriginal('value'),
                    'new_value' => $value,
                ])
                ->log('Updated payroll setting: ' . $key);
        }
    }

    /**
     * Get tax brackets from settings.
     * 
     * @param \Illuminate\Support\Collection $settings
     * @return array
     */
    private function getTaxBrackets($settings): array
    {
        $taxBracketsJson = $settings->get('government_rates')['government.bir.tax_brackets'] ?? null;
        
        if ($taxBracketsJson) {
            return json_decode($taxBracketsJson, true);
        }

        // Default TRAIN law tax brackets (2024)
        return [
            ['min' => 0, 'max' => 250000, 'base_tax' => 0, 'rate' => 0],
            ['min' => 250000, 'max' => 400000, 'base_tax' => 0, 'rate' => 15],
            ['min' => 400000, 'max' => 800000, 'base_tax' => 22500, 'rate' => 20],
            ['min' => 800000, 'max' => 2000000, 'base_tax' => 102500, 'rate' => 25],
            ['min' => 2000000, 'max' => 8000000, 'base_tax' => 402500, 'rate' => 30],
            ['min' => 8000000, 'max' => null, 'base_tax' => 2202500, 'rate' => 35],
        ];
    }

    /**
     * Get cutoff dates from settings.
     * 
     * @param \Illuminate\Support\Collection $settings
     * @return array
     */
    private function getCutoffDates($settings): array
    {
        $cutoffJson = $settings->get('payment_methods')['payment.cutoff_dates'] ?? null;
        
        if ($cutoffJson) {
            return json_decode($cutoffJson, true);
        }

        // Default bi-monthly cutoff (15th and end of month)
        return [
            'first' => 15,
            'second' => 30,
        ];
    }

    /**
     * Get human-readable description for setting key.
     * 
     * @param string $key
     * @return string
     */
    private function getSettingDescription(string $key): string
    {
        $descriptions = [
            // Salary structure
            'payroll.minimum_wage' => 'Minimum daily wage rate',
            'payroll.salary_grades_enabled' => 'Enable salary grade system',
            'payroll.salary_grades_count' => 'Number of salary grades',
            
            // Allowances
            'payroll.allowances.housing' => 'Housing allowance amount',
            'payroll.allowances.transportation' => 'Transportation allowance amount',
            'payroll.allowances.meal' => 'Meal allowance amount',
            'payroll.allowances.communication' => 'Communication allowance amount',
            
            // Bonuses
            'payroll.bonuses.thirteenth_month_enabled' => 'Enable 13th month pay',
            'payroll.bonuses.performance_enabled' => 'Enable performance bonus',
            
            // Government rates
            'government.sss.employee_rate' => 'SSS employee contribution rate',
            'government.sss.employer_rate' => 'SSS employer contribution rate',
            'government.sss.max_salary' => 'SSS maximum salary base',
            'government.philhealth.rate' => 'PhilHealth premium rate',
            'government.philhealth.employee_share' => 'PhilHealth employee share',
            'government.philhealth.employer_share' => 'PhilHealth employer share',
            'government.pagibig.employee_rate' => 'Pag-IBIG employee contribution rate',
            'government.pagibig.employer_rate' => 'Pag-IBIG employer contribution rate',
            
            // Payment methods
            'payment.default_method' => 'Default payment method',
            'payment.cash_enabled' => 'Enable cash payment',
            'payment.bank_transfer_enabled' => 'Enable bank transfer payment',
            'payment.ewallet_enabled' => 'Enable e-wallet payment',
            'payment.schedule' => 'Payment schedule frequency',
        ];

        return $descriptions[$key] ?? ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
