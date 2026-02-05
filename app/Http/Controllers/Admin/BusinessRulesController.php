<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessRulesController extends Controller
{
    /**
     * Display business rules configuration page.
     * 
     * Shows current configuration for:
     * - Working hours (regular and shift schedules)
     * - Holiday calendar (national and company holidays)
     * - Overtime rules (rates, thresholds, approval requirements)
     * - Attendance policies (grace period, late/undertime deductions)
     */
    public function index(Request $request): Response
    {
        // Get all business rules settings
        $settings = SystemSetting::whereIn('key', [
            // Working Hours
            'business_rules.working_hours.regular_start',
            'business_rules.working_hours.regular_end',
            'business_rules.working_hours.break_duration',
            'business_rules.working_hours.break_start',
            'business_rules.working_hours.work_days',
            'business_rules.working_hours.shift_enabled',
            
            // Overtime Rules
            'business_rules.overtime.threshold_hours',
            'business_rules.overtime.rate_regular',
            'business_rules.overtime.rate_holiday',
            'business_rules.overtime.rate_rest_day',
            'business_rules.overtime.max_hours_per_day',
            'business_rules.overtime.max_hours_per_week',
            'business_rules.overtime.auto_approve_threshold',
            'business_rules.overtime.requires_approval',
            
            // Attendance Policies
            'business_rules.attendance.grace_period_minutes',
            'business_rules.attendance.late_deduction_type',
            'business_rules.attendance.late_deduction_amount',
            'business_rules.attendance.undertime_enabled',
            'business_rules.attendance.undertime_deduction_type',
            'business_rules.attendance.absence_with_leave_deduction',
            'business_rules.attendance.absence_without_leave_deduction',
            
            // Holiday Pay Multipliers
            'business_rules.holiday.regular_multiplier',
            'business_rules.holiday.special_multiplier',
        ])
        ->get()
        ->pluck('value', 'key')
        ->toArray();

        // Prepare business rules data with defaults
        $businessRules = [
            'working_hours' => [
                'regular_start' => $settings['business_rules.working_hours.regular_start'] ?? '08:00',
                'regular_end' => $settings['business_rules.working_hours.regular_end'] ?? '17:00',
                'break_duration' => (int)($settings['business_rules.working_hours.break_duration'] ?? 60),
                'break_start' => $settings['business_rules.working_hours.break_start'] ?? '12:00',
                'work_days' => json_decode($settings['business_rules.working_hours.work_days'] ?? '["monday","tuesday","wednesday","thursday","friday"]', true),
                'shift_enabled' => (bool)($settings['business_rules.working_hours.shift_enabled'] ?? false),
            ],
            'overtime' => [
                'threshold_hours' => (float)($settings['business_rules.overtime.threshold_hours'] ?? 8.0),
                'rate_regular' => (float)($settings['business_rules.overtime.rate_regular'] ?? 1.25),
                'rate_holiday' => (float)($settings['business_rules.overtime.rate_holiday'] ?? 2.0),
                'rate_rest_day' => (float)($settings['business_rules.overtime.rate_rest_day'] ?? 1.3),
                'max_hours_per_day' => (float)($settings['business_rules.overtime.max_hours_per_day'] ?? 4.0),
                'max_hours_per_week' => (float)($settings['business_rules.overtime.max_hours_per_week'] ?? 20.0),
                'auto_approve_threshold' => (float)($settings['business_rules.overtime.auto_approve_threshold'] ?? 2.0),
                'requires_approval' => (bool)($settings['business_rules.overtime.requires_approval'] ?? true),
            ],
            'attendance' => [
                'grace_period_minutes' => (int)($settings['business_rules.attendance.grace_period_minutes'] ?? 15),
                'late_deduction_type' => $settings['business_rules.attendance.late_deduction_type'] ?? 'per_minute',
                'late_deduction_amount' => (float)($settings['business_rules.attendance.late_deduction_amount'] ?? 0.0),
                'undertime_enabled' => (bool)($settings['business_rules.attendance.undertime_enabled'] ?? true),
                'undertime_deduction_type' => $settings['business_rules.attendance.undertime_deduction_type'] ?? 'proportional',
                'absence_with_leave_deduction' => (float)($settings['business_rules.attendance.absence_with_leave_deduction'] ?? 0.0),
                'absence_without_leave_deduction' => (float)($settings['business_rules.attendance.absence_without_leave_deduction'] ?? 1.0),
            ],
            'holiday' => [
                'regular_multiplier' => (float)($settings['business_rules.holiday.regular_multiplier'] ?? 2.0),
                'special_multiplier' => (float)($settings['business_rules.holiday.special_multiplier'] ?? 1.3),
            ],
        ];

        // Get holidays from database (if you have a holidays table)
        // For now, we'll get from system settings if stored as JSON
        $holidays = SystemSetting::where('key', 'business_rules.holidays')
            ->first()?->value;
        
        $businessRules['holidays'] = $holidays ? json_decode($holidays, true) : [];

        return Inertia::render('Admin/BusinessRules/Index', [
            'businessRules' => $businessRules,
        ]);
    }

    /**
     * Update working hours configuration.
     * 
     * Saves regular schedule, shift patterns, and break times.
     */
    public function updateWorkingHours(Request $request)
    {
        $validated = $request->validate([
            'regular_start' => 'required|date_format:H:i',
            'regular_end' => 'required|date_format:H:i|after:regular_start',
            'break_duration' => 'required|integer|min:0|max:240',
            'break_start' => 'nullable|date_format:H:i',
            'work_days' => 'required|array|min:1',
            'work_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'shift_enabled' => 'boolean',
        ]);

        $settingsMap = [
            'regular_start' => 'business_rules.working_hours.regular_start',
            'regular_end' => 'business_rules.working_hours.regular_end',
            'break_duration' => 'business_rules.working_hours.break_duration',
            'break_start' => 'business_rules.working_hours.break_start',
            'shift_enabled' => 'business_rules.working_hours.shift_enabled',
        ];

        foreach ($settingsMap as $field => $key) {
            if (isset($validated[$field])) {
                $this->updateSetting($key, $validated[$field], 'business_rules', $request->user());
            }
        }

        // Handle work_days as JSON array
        $this->updateSetting(
            'business_rules.working_hours.work_days',
            json_encode($validated['work_days']),
            'business_rules',
            $request->user()
        );

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Working hours configuration updated successfully.');
    }

    /**
     * Store a new holiday in the calendar.
     * 
     * Adds national or company-specific holidays.
     */
    public function storeHoliday(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:regular,special,company',
            'is_recurring' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        // Get existing holidays
        $holidaysSetting = SystemSetting::where('key', 'business_rules.holidays')->first();
        $holidays = $holidaysSetting ? json_decode($holidaysSetting->value, true) : [];

        // Add new holiday
        $holidays[] = array_merge($validated, [
            'id' => uniqid(),
            'created_at' => now()->toISOString(),
        ]);

        // Save back to settings
        $setting = SystemSetting::updateOrCreate(
            ['key' => 'business_rules.holidays'],
            [
                'value' => json_encode($holidays),
                'type' => 'json',
                'category' => 'business_rules',
                'description' => 'Company and national holiday calendar',
            ]
        );

        // Log the change
        activity('business_rules_configuration')
            ->causedBy($request->user())
            ->performedOn($setting)
            ->withProperties([
                'holiday_name' => $validated['name'],
                'holiday_date' => $validated['date'],
                'holiday_type' => $validated['type'],
            ])
            ->log('Added holiday: ' . $validated['name']);

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Holiday added successfully.');
    }

    /**
     * Update a specific holiday.
     */
    public function updateHoliday(Request $request, string $holidayId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:regular,special,company',
            'is_recurring' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        // Get existing holidays
        $holidaysSetting = SystemSetting::where('key', 'business_rules.holidays')->first();
        $holidays = $holidaysSetting ? json_decode($holidaysSetting->value, true) : [];

        // Find and update the holiday
        foreach ($holidays as &$holiday) {
            if ($holiday['id'] === $holidayId) {
                $holiday = array_merge($holiday, $validated, [
                    'updated_at' => now()->toISOString(),
                ]);
                break;
            }
        }

        // Save back to settings
        $setting = SystemSetting::updateOrCreate(
            ['key' => 'business_rules.holidays'],
            [
                'value' => json_encode($holidays),
                'type' => 'json',
                'category' => 'business_rules',
                'description' => 'Company and national holiday calendar',
            ]
        );

        // Log the change
        activity('business_rules_configuration')
            ->causedBy($request->user())
            ->performedOn($setting)
            ->withProperties([
                'holiday_id' => $holidayId,
                'holiday_name' => $validated['name'],
            ])
            ->log('Updated holiday: ' . $validated['name']);

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Holiday updated successfully.');
    }

    /**
     * Delete a holiday from the calendar.
     */
    public function deleteHoliday(Request $request, string $holidayId)
    {
        // Get existing holidays
        $holidaysSetting = SystemSetting::where('key', 'business_rules.holidays')->first();
        
        if (!$holidaysSetting) {
            return redirect()->route('admin.business-rules.index')
                ->with('error', 'Holiday not found.');
        }

        $holidays = json_decode($holidaysSetting->value, true);
        
        // Find the holiday to get its name for logging
        $deletedHoliday = null;
        foreach ($holidays as $holiday) {
            if ($holiday['id'] === $holidayId) {
                $deletedHoliday = $holiday;
                break;
            }
        }

        // Remove the holiday
        $holidays = array_filter($holidays, fn($h) => $h['id'] !== $holidayId);

        // Save back to settings
        $setting = SystemSetting::updateOrCreate(
            ['key' => 'business_rules.holidays'],
            [
                'value' => json_encode(array_values($holidays)),
                'type' => 'json',
                'category' => 'business_rules',
                'description' => 'Company and national holiday calendar',
            ]
        );

        // Log the change
        if ($deletedHoliday) {
            activity('business_rules_configuration')
                ->causedBy($request->user())
                ->performedOn($setting)
                ->withProperties([
                    'holiday_id' => $holidayId,
                    'holiday_name' => $deletedHoliday['name'],
                ])
                ->log('Deleted holiday: ' . $deletedHoliday['name']);
        }

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Holiday deleted successfully.');
    }

    /**
     * Update overtime rules configuration.
     * 
     * Saves overtime rates, thresholds, and approval requirements.
     */
    public function updateOvertimeRules(Request $request)
    {
        $validated = $request->validate([
            'threshold_hours' => 'required|numeric|min:0|max:24',
            'rate_regular' => 'required|numeric|min:1|max:5',
            'rate_holiday' => 'required|numeric|min:1|max:5',
            'rate_rest_day' => 'required|numeric|min:1|max:5',
            'max_hours_per_day' => 'required|numeric|min:0|max:12',
            'max_hours_per_week' => 'required|numeric|min:0|max:60',
            'auto_approve_threshold' => 'required|numeric|min:0|max:24',
            'requires_approval' => 'boolean',
        ]);

        $settingsMap = [
            'threshold_hours' => 'business_rules.overtime.threshold_hours',
            'rate_regular' => 'business_rules.overtime.rate_regular',
            'rate_holiday' => 'business_rules.overtime.rate_holiday',
            'rate_rest_day' => 'business_rules.overtime.rate_rest_day',
            'max_hours_per_day' => 'business_rules.overtime.max_hours_per_day',
            'max_hours_per_week' => 'business_rules.overtime.max_hours_per_week',
            'auto_approve_threshold' => 'business_rules.overtime.auto_approve_threshold',
            'requires_approval' => 'business_rules.overtime.requires_approval',
        ];

        foreach ($settingsMap as $field => $key) {
            if (isset($validated[$field])) {
                $this->updateSetting($key, $validated[$field], 'business_rules', $request->user());
            }
        }

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Overtime rules updated successfully.');
    }

    /**
     * Update attendance policies configuration.
     * 
     * Saves grace period, late deduction rules, undertime policy, and absence handling.
     */
    public function updateAttendanceRules(Request $request)
    {
        $validated = $request->validate([
            'grace_period_minutes' => 'required|integer|min:0|max:60',
            'late_deduction_type' => 'required|in:per_minute,per_bracket,fixed',
            'late_deduction_amount' => 'required|numeric|min:0',
            'undertime_enabled' => 'boolean',
            'undertime_deduction_type' => 'required|in:proportional,fixed,none',
            'absence_with_leave_deduction' => 'required|numeric|min:0|max:1',
            'absence_without_leave_deduction' => 'required|numeric|min:0|max:1',
        ]);

        $settingsMap = [
            'grace_period_minutes' => 'business_rules.attendance.grace_period_minutes',
            'late_deduction_type' => 'business_rules.attendance.late_deduction_type',
            'late_deduction_amount' => 'business_rules.attendance.late_deduction_amount',
            'undertime_enabled' => 'business_rules.attendance.undertime_enabled',
            'undertime_deduction_type' => 'business_rules.attendance.undertime_deduction_type',
            'absence_with_leave_deduction' => 'business_rules.attendance.absence_with_leave_deduction',
            'absence_without_leave_deduction' => 'business_rules.attendance.absence_without_leave_deduction',
        ];

        foreach ($settingsMap as $field => $key) {
            if (isset($validated[$field])) {
                $this->updateSetting($key, $validated[$field], 'business_rules', $request->user());
            }
        }

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Attendance policies updated successfully.');
    }

    /**
     * Update holiday pay multipliers.
     */
    public function updateHolidayMultipliers(Request $request)
    {
        $validated = $request->validate([
            'regular_multiplier' => 'required|numeric|min:1|max:5',
            'special_multiplier' => 'required|numeric|min:1|max:5',
        ]);

        $this->updateSetting(
            'business_rules.holiday.regular_multiplier',
            $validated['regular_multiplier'],
            'business_rules',
            $request->user()
        );

        $this->updateSetting(
            'business_rules.holiday.special_multiplier',
            $validated['special_multiplier'],
            'business_rules',
            $request->user()
        );

        return redirect()->route('admin.business-rules.index')
            ->with('success', 'Holiday pay multipliers updated successfully.');
    }

    /**
     * Helper method to update or create a setting with activity logging.
     * 
     * @param string $key
     * @param mixed $value
     * @param string $category
     * @param \App\Models\User $user
     * @return void
     */
    private function updateSetting(string $key, $value, string $category, $user): void
    {
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string'),
                'category' => $category,
                'description' => $this->getSettingDescription($key),
            ]
        );

        // Log the change using Spatie Activity Log
        activity('business_rules_configuration')
            ->causedBy($user)
            ->performedOn($setting)
            ->withProperties([
                'key' => $key,
                'old_value' => $setting->getOriginal('value'),
                'new_value' => $value,
            ])
            ->log('Updated business rule: ' . $key);
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
            // Working Hours
            'business_rules.working_hours.regular_start' => 'Regular working hours start time',
            'business_rules.working_hours.regular_end' => 'Regular working hours end time',
            'business_rules.working_hours.break_duration' => 'Break duration in minutes',
            'business_rules.working_hours.break_start' => 'Break start time',
            'business_rules.working_hours.work_days' => 'Working days of the week',
            'business_rules.working_hours.shift_enabled' => 'Enable shift schedules',
            
            // Overtime Rules
            'business_rules.overtime.threshold_hours' => 'Hours before overtime kicks in',
            'business_rules.overtime.rate_regular' => 'Regular overtime rate multiplier',
            'business_rules.overtime.rate_holiday' => 'Holiday overtime rate multiplier',
            'business_rules.overtime.rate_rest_day' => 'Rest day overtime rate multiplier',
            'business_rules.overtime.max_hours_per_day' => 'Maximum overtime hours per day',
            'business_rules.overtime.max_hours_per_week' => 'Maximum overtime hours per week',
            'business_rules.overtime.auto_approve_threshold' => 'Auto-approve overtime below this threshold',
            'business_rules.overtime.requires_approval' => 'Overtime requires approval',
            
            // Attendance Policies
            'business_rules.attendance.grace_period_minutes' => 'Grace period for late arrival in minutes',
            'business_rules.attendance.late_deduction_type' => 'Late deduction calculation method',
            'business_rules.attendance.late_deduction_amount' => 'Late deduction amount or rate',
            'business_rules.attendance.undertime_enabled' => 'Enable undertime tracking',
            'business_rules.attendance.undertime_deduction_type' => 'Undertime deduction calculation method',
            'business_rules.attendance.absence_with_leave_deduction' => 'Salary deduction for absence with approved leave',
            'business_rules.attendance.absence_without_leave_deduction' => 'Salary deduction for absence without leave',
            
            // Holiday Pay Multipliers
            'business_rules.holiday.regular_multiplier' => 'Regular holiday pay multiplier',
            'business_rules.holiday.special_multiplier' => 'Special holiday pay multiplier',
        ];

        return $descriptions[$key] ?? ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
