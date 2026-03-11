<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Pre-populates system_settings with default values for all 5 admin pages.
     * Categories: company, business_rules, payroll, government_rates, payment_methods, system_config
     * Total: 69 settings
     */
    public function run(): void
    {
        $settings = [
            // ── COMPANY SETTINGS (16 keys) ─────────────────────────────────────
            ['key' => 'company.name',              'value' => 'CAMECO Corporation',         'type' => 'string',  'category' => 'company'],
            ['key' => 'company.tagline',           'value' => 'Excellence in Every Step',   'type' => 'string',  'category' => 'company'],
            ['key' => 'company.address',           'value' => '',                           'type' => 'string',  'category' => 'company'],
            ['key' => 'company.city',              'value' => 'Manila',                     'type' => 'string',  'category' => 'company'],
            ['key' => 'company.state',             'value' => 'Metro Manila',               'type' => 'string',  'category' => 'company'],
            ['key' => 'company.postal_code',       'value' => '1000',                       'type' => 'string',  'category' => 'company'],
            ['key' => 'company.country',           'value' => 'Philippines',                'type' => 'string',  'category' => 'company'],
            ['key' => 'company.phone',             'value' => '',                           'type' => 'string',  'category' => 'company'],
            ['key' => 'company.email',             'value' => '',                           'type' => 'string',  'category' => 'company'],
            ['key' => 'company.website',           'value' => '',                           'type' => 'string',  'category' => 'company'],
            ['key' => 'company.tax_id',            'value' => '',                           'type' => 'string',  'category' => 'company'],
            ['key' => 'company.registration',      'value' => '',                           'type' => 'string',  'category' => 'company'],
            ['key' => 'company.industry',          'value' => 'Manufacturing',              'type' => 'string',  'category' => 'company'],
            ['key' => 'company.founding_year',     'value' => '2000',                       'type' => 'integer', 'category' => 'company'],
            ['key' => 'company.size',              'value' => '100-500',                    'type' => 'string',  'category' => 'company'],
            ['key' => 'company.logo',              'value' => '',                           'type' => 'string',  'category' => 'company'],

            // ── BUSINESS RULES - WORKING HOURS (5 keys) ────────────────────────
            ['key' => 'business_rules.working_hours.work_start',     'value' => '08:00', 'type' => 'string',  'category' => 'business_rules'],
            ['key' => 'business_rules.working_hours.work_end',       'value' => '17:00', 'type' => 'string',  'category' => 'business_rules'],
            ['key' => 'business_rules.working_hours.break_duration', 'value' => '60',    'type' => 'integer', 'category' => 'business_rules'],
            ['key' => 'business_rules.working_hours.work_days',      'value' => '["Monday","Tuesday","Wednesday","Thursday","Friday"]', 'type' => 'json', 'category' => 'business_rules'],
            ['key' => 'business_rules.working_hours.hours_per_day',  'value' => '8',     'type' => 'integer', 'category' => 'business_rules'],

            // ── BUSINESS RULES - OVERTIME (6 keys) ──────────────────────────────
            ['key' => 'business_rules.overtime.enabled',             'value' => '1',     'type' => 'boolean', 'category' => 'business_rules'],
            ['key' => 'business_rules.overtime.min_hours',           'value' => '0.5',   'type' => 'float',   'category' => 'business_rules'],
            ['key' => 'business_rules.overtime.max_daily_overtime',  'value' => '4',     'type' => 'float',   'category' => 'business_rules'],
            ['key' => 'business_rules.overtime.requires_approval',   'value' => '1',     'type' => 'boolean', 'category' => 'business_rules'],
            ['key' => 'business_rules.overtime.night_differential_start', 'value' => '22:00', 'type' => 'string', 'category' => 'business_rules'],
            ['key' => 'business_rules.overtime.night_differential_end',   'value' => '06:00', 'type' => 'string', 'category' => 'business_rules'],

            // ── BUSINESS RULES - ATTENDANCE (4 keys) ────────────────────────────
            ['key' => 'business_rules.attendance.grace_period_minutes', 'value' => '15', 'type' => 'integer', 'category' => 'business_rules'],
            ['key' => 'business_rules.attendance.late_deduction_enabled', 'value' => '1', 'type' => 'boolean', 'category' => 'business_rules'],
            ['key' => 'business_rules.attendance.absent_deduction_enabled', 'value' => '1', 'type' => 'boolean', 'category' => 'business_rules'],
            ['key' => 'business_rules.attendance.half_day_cutoff_hours', 'value' => '4', 'type' => 'float', 'category' => 'business_rules'],

            // ── BUSINESS RULES - HOLIDAY MULTIPLIERS (5 keys) ───────────────────
            ['key' => 'business_rules.holiday.regular_holiday_multiplier',  'value' => '2.0', 'type' => 'float', 'category' => 'business_rules'],
            ['key' => 'business_rules.holiday.special_holiday_multiplier',  'value' => '1.3', 'type' => 'float', 'category' => 'business_rules'],
            ['key' => 'business_rules.holiday.double_holiday_multiplier',   'value' => '3.0', 'type' => 'float', 'category' => 'business_rules'],
            ['key' => 'business_rules.holiday.rest_day_multiplier',         'value' => '1.3', 'type' => 'float', 'category' => 'business_rules'],
            ['key' => 'business_rules.holiday.holiday_ot_multiplier',       'value' => '2.6', 'type' => 'float', 'category' => 'business_rules'],

            // ── PAYROLL - CUTOFF PERIODS (4 keys) ──────────────────────────────
            ['key' => 'payroll.cutoff.first_cutoff_start',  'value' => '1',  'type' => 'integer', 'category' => 'payroll'],
            ['key' => 'payroll.cutoff.first_cutoff_end',    'value' => '15', 'type' => 'integer', 'category' => 'payroll'],
            ['key' => 'payroll.cutoff.second_cutoff_start', 'value' => '16', 'type' => 'integer', 'category' => 'payroll'],
            ['key' => 'payroll.cutoff.second_cutoff_end',   'value' => '31', 'type' => 'integer', 'category' => 'payroll'],

            // ── PAYROLL - DEDUCTIONS (6 keys) ──────────────────────────────────
            ['key' => 'payroll.deductions.sss_employee',    'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],
            ['key' => 'payroll.deductions.philhealth_employee', 'value' => '1', 'type' => 'boolean', 'category' => 'payroll'],
            ['key' => 'payroll.deductions.pagibig_employee', 'value' => '1', 'type' => 'boolean', 'category' => 'payroll'],
            ['key' => 'payroll.deductions.income_tax',      'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],
            ['key' => 'payroll.deductions.loan_deductions', 'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],
            ['key' => 'payroll.deductions.cash_advance',    'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],

            // ── GOVERNMENT RATES - SSS (3 keys) ────────────────────────────────
            ['key' => 'government_rates.sss.employee_rate',  'value' => '0.045', 'type' => 'float', 'category' => 'government_rates'],
            ['key' => 'government_rates.sss.employer_rate',  'value' => '0.095', 'type' => 'float', 'category' => 'government_rates'],
            ['key' => 'government_rates.sss.max_msc',        'value' => '30000', 'type' => 'integer', 'category' => 'government_rates'],

            // ── GOVERNMENT RATES - PHILHEALTH (3 keys) ─────────────────────────
            ['key' => 'government_rates.philhealth.employee_rate', 'value' => '0.025', 'type' => 'float', 'category' => 'government_rates'],
            ['key' => 'government_rates.philhealth.employer_rate', 'value' => '0.025', 'type' => 'float', 'category' => 'government_rates'],
            ['key' => 'government_rates.philhealth.max_salary', 'value' => '100000', 'type' => 'integer', 'category' => 'government_rates'],

            // ── GOVERNMENT RATES - PAG-IBIG (3 keys) ────────────────────────────
            ['key' => 'government_rates.pagibig.employee_rate', 'value' => '0.02', 'type' => 'float', 'category' => 'government_rates'],
            ['key' => 'government_rates.pagibig.employer_rate', 'value' => '0.02', 'type' => 'float', 'category' => 'government_rates'],
            ['key' => 'government_rates.pagibig.max_contribution', 'value' => '100', 'type' => 'integer', 'category' => 'government_rates'],

            // ── PAYMENT METHODS (4 keys) ───────────────────────────────────────
            ['key' => 'payment_methods.bank_transfer.enabled', 'value' => '1', 'type' => 'boolean', 'category' => 'payment_methods'],
            ['key' => 'payment_methods.gcash.enabled',          'value' => '0', 'type' => 'boolean', 'category' => 'payment_methods'],
            ['key' => 'payment_methods.cash.enabled',           'value' => '1', 'type' => 'boolean', 'category' => 'payment_methods'],
            ['key' => 'payment_methods.check.enabled',          'value' => '0', 'type' => 'boolean', 'category' => 'payment_methods'],

            // ── SYSTEM CONFIG (9 keys) ────────────────────────────────────────
            ['key' => 'system_config.timezone',            'value' => 'Asia/Manila',    'type' => 'string',  'category' => 'system_config'],
            ['key' => 'system_config.date_format',         'value' => 'Y-m-d',          'type' => 'string',  'category' => 'system_config'],
            ['key' => 'system_config.time_format',         'value' => 'H:i',            'type' => 'string',  'category' => 'system_config'],
            ['key' => 'system_config.currency',            'value' => 'PHP',            'type' => 'string',  'category' => 'system_config'],
            ['key' => 'system_config.locale',              'value' => 'en_PH',          'type' => 'string',  'category' => 'system_config'],
            ['key' => 'system_config.maintenance_mode',    'value' => '0',              'type' => 'boolean', 'category' => 'system_config'],
            ['key' => 'system_config.allow_registration',  'value' => '0',              'type' => 'boolean', 'category' => 'system_config'],
            ['key' => 'system_config.session_lifetime',    'value' => '120',            'type' => 'integer', 'category' => 'system_config'],
            ['key' => 'system_config.max_login_attempts',  'value' => '5',              'type' => 'integer', 'category' => 'system_config'],
        ];

        // Total: 69 settings across 6 categories
        // Insert or update each setting using updateOrCreate for idempotency
        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✅ SystemSettings seeded successfully (' . count($settings) . ' records)');
    }
}
