<?php

namespace App\Http\Controllers\HR\Workforce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Display a listing of work schedules with mock data.
     */
    public function index(Request $request): Response
    {
        // Mock data: 8 schedules (5 active, 2 expired, 1 draft)
        $schedules = [
            [
                'id' => 1,
                'name' => 'Standard Day Shift - Production',
                'description' => '8-hour day shift for production employees',
                'effective_date' => '2025-01-01',
                'expires_at' => '2025-12-31',
                'status' => 'active',
                'monday_start' => '06:00:00',
                'monday_end' => '14:00:00',
                'tuesday_start' => '06:00:00',
                'tuesday_end' => '14:00:00',
                'wednesday_start' => '06:00:00',
                'wednesday_end' => '14:00:00',
                'thursday_start' => '06:00:00',
                'thursday_end' => '14:00:00',
                'friday_start' => '06:00:00',
                'friday_end' => '14:00:00',
                'saturday_start' => null,
                'saturday_end' => null,
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.25,
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'assigned_employees_count' => 45,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-15 08:00:00',
                'updated_at' => '2024-12-15 08:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Night Shift - Wire Mill',
                'description' => '8-hour night shift for wire mill operations',
                'effective_date' => '2025-01-01',
                'expires_at' => null,
                'status' => 'active',
                'monday_start' => '22:00:00',
                'monday_end' => '06:00:00',
                'tuesday_start' => '22:00:00',
                'tuesday_end' => '06:00:00',
                'wednesday_start' => '22:00:00',
                'wednesday_end' => '06:00:00',
                'thursday_start' => '22:00:00',
                'thursday_end' => '06:00:00',
                'friday_start' => '22:00:00',
                'friday_end' => '06:00:00',
                'saturday_start' => null,
                'saturday_end' => null,
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.5,
                'department_id' => 4,
                'department_name' => 'Wire Mill',
                'assigned_employees_count' => 32,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-15 08:00:00',
                'updated_at' => '2024-12-15 08:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Afternoon Shift - Quality Control',
                'description' => '8-hour afternoon shift for QC team',
                'effective_date' => '2025-01-01',
                'expires_at' => '2025-06-30',
                'status' => 'active',
                'monday_start' => '14:00:00',
                'monday_end' => '22:00:00',
                'tuesday_start' => '14:00:00',
                'tuesday_end' => '22:00:00',
                'wednesday_start' => '14:00:00',
                'wednesday_end' => '22:00:00',
                'thursday_start' => '14:00:00',
                'thursday_end' => '22:00:00',
                'friday_start' => '14:00:00',
                'friday_end' => '22:00:00',
                'saturday_start' => null,
                'saturday_end' => null,
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.25,
                'department_id' => 5,
                'department_name' => 'Quality Assurance',
                'assigned_employees_count' => 18,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-15 08:00:00',
                'updated_at' => '2024-12-15 08:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Office Hours - Admin',
                'description' => 'Standard office hours for administrative staff',
                'effective_date' => '2025-01-01',
                'expires_at' => null,
                'status' => 'active',
                'monday_start' => '08:00:00',
                'monday_end' => '17:00:00',
                'tuesday_start' => '08:00:00',
                'tuesday_end' => '17:00:00',
                'wednesday_start' => '08:00:00',
                'wednesday_end' => '17:00:00',
                'thursday_start' => '08:00:00',
                'thursday_end' => '17:00:00',
                'friday_start' => '08:00:00',
                'friday_end' => '17:00:00',
                'saturday_start' => null,
                'saturday_end' => null,
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.25,
                'department_id' => 1,
                'department_name' => 'Human Resources',
                'assigned_employees_count' => 12,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-15 08:00:00',
                'updated_at' => '2024-12-15 08:00:00',
            ],
            [
                'id' => 5,
                'name' => '6-Day Work Week - Maintenance',
                'description' => '6-day work week including Saturday for maintenance team',
                'effective_date' => '2025-01-01',
                'expires_at' => null,
                'status' => 'active',
                'monday_start' => '06:00:00',
                'monday_end' => '14:00:00',
                'tuesday_start' => '06:00:00',
                'tuesday_end' => '14:00:00',
                'wednesday_start' => '06:00:00',
                'wednesday_end' => '14:00:00',
                'thursday_start' => '06:00:00',
                'thursday_end' => '14:00:00',
                'friday_start' => '06:00:00',
                'friday_end' => '14:00:00',
                'saturday_start' => '06:00:00',
                'saturday_end' => '14:00:00',
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.5,
                'department_id' => 6,
                'department_name' => 'Maintenance',
                'assigned_employees_count' => 24,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-15 08:00:00',
                'updated_at' => '2024-12-15 08:00:00',
            ],
            [
                'id' => 6,
                'name' => 'Flexible Hours - IT Department',
                'description' => 'Flexible work schedule for IT staff',
                'effective_date' => '2024-06-01',
                'expires_at' => '2024-12-31',
                'status' => 'expired',
                'monday_start' => '09:00:00',
                'monday_end' => '18:00:00',
                'tuesday_start' => '09:00:00',
                'tuesday_end' => '18:00:00',
                'wednesday_start' => '09:00:00',
                'wednesday_end' => '18:00:00',
                'thursday_start' => '09:00:00',
                'thursday_end' => '18:00:00',
                'friday_start' => '09:00:00',
                'friday_end' => '18:00:00',
                'saturday_start' => null,
                'saturday_end' => null,
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.25,
                'department_id' => 7,
                'department_name' => 'IT',
                'assigned_employees_count' => 0,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-06-01 08:00:00',
                'updated_at' => '2024-12-31 23:59:59',
            ],
            [
                'id' => 7,
                'name' => 'Emergency Response Team - 24/7',
                'description' => 'Old schedule for emergency response team',
                'effective_date' => '2024-01-01',
                'expires_at' => '2024-06-30',
                'status' => 'expired',
                'monday_start' => '00:00:00',
                'monday_end' => '23:59:59',
                'tuesday_start' => '00:00:00',
                'tuesday_end' => '23:59:59',
                'wednesday_start' => '00:00:00',
                'wednesday_end' => '23:59:59',
                'thursday_start' => '00:00:00',
                'thursday_end' => '23:59:59',
                'friday_start' => '00:00:00',
                'friday_end' => '23:59:59',
                'saturday_start' => '00:00:00',
                'saturday_end' => '23:59:59',
                'sunday_start' => '00:00:00',
                'sunday_end' => '23:59:59',
                'lunch_break_duration' => 0,
                'morning_break_duration' => 0,
                'afternoon_break_duration' => 0,
                'overtime_threshold' => 12,
                'overtime_rate_multiplier' => 2.0,
                'department_id' => 8,
                'department_name' => 'Safety',
                'assigned_employees_count' => 0,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-01-01 08:00:00',
                'updated_at' => '2024-06-30 23:59:59',
            ],
            [
                'id' => 8,
                'name' => 'Draft Schedule - New Production Line',
                'description' => 'Draft schedule for upcoming production line expansion',
                'effective_date' => '2025-03-01',
                'expires_at' => null,
                'status' => 'draft',
                'monday_start' => '07:00:00',
                'monday_end' => '15:00:00',
                'tuesday_start' => '07:00:00',
                'tuesday_end' => '15:00:00',
                'wednesday_start' => '07:00:00',
                'wednesday_end' => '15:00:00',
                'thursday_start' => '07:00:00',
                'thursday_end' => '15:00:00',
                'friday_start' => '07:00:00',
                'friday_end' => '15:00:00',
                'saturday_start' => null,
                'saturday_end' => null,
                'sunday_start' => null,
                'sunday_end' => null,
                'lunch_break_duration' => 60,
                'morning_break_duration' => 15,
                'afternoon_break_duration' => 15,
                'overtime_threshold' => 8,
                'overtime_rate_multiplier' => 1.25,
                'department_id' => null,
                'department_name' => null,
                'assigned_employees_count' => 0,
                'is_template' => false,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-01-10 10:00:00',
                'updated_at' => '2025-01-10 10:00:00',
            ],
        ];

        // Mock summary statistics
        $summary = [
            'total_schedules' => 8,
            'active_schedules' => 5,
            'expired_schedules' => 2,
            'draft_schedules' => 1,
            'employees_assigned' => 131,
            'templates_available' => 5,
        ];

        // Mock departments for filters
        $departments = [
            ['id' => 1, 'name' => 'Human Resources', 'code' => 'HR'],
            ['id' => 3, 'name' => 'Rolling Mill 3', 'code' => 'RM3'],
            ['id' => 4, 'name' => 'Wire Mill', 'code' => 'WM'],
            ['id' => 5, 'name' => 'Quality Assurance', 'code' => 'QA'],
            ['id' => 6, 'name' => 'Maintenance', 'code' => 'MNT'],
            ['id' => 7, 'name' => 'IT', 'code' => 'IT'],
            ['id' => 8, 'name' => 'Safety', 'code' => 'SFT'],
        ];

        // Mock schedule templates
        $templates = [
            [
                'id' => 1,
                'name' => 'Morning Shift Template (6AM-2PM)',
                'shift_type' => 'morning',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'is_active' => true,
                'usage_count' => 45,
            ],
            [
                'id' => 2,
                'name' => 'Afternoon Shift Template (2PM-10PM)',
                'shift_type' => 'afternoon',
                'shift_start' => '14:00:00',
                'shift_end' => '22:00:00',
                'is_active' => true,
                'usage_count' => 32,
            ],
            [
                'id' => 3,
                'name' => 'Night Shift Template (10PM-6AM)',
                'shift_type' => 'night',
                'shift_start' => '22:00:00',
                'shift_end' => '06:00:00',
                'is_active' => true,
                'usage_count' => 28,
            ],
            [
                'id' => 4,
                'name' => 'Office Hours Template (8AM-5PM)',
                'shift_type' => 'custom',
                'shift_start' => '08:00:00',
                'shift_end' => '17:00:00',
                'is_active' => true,
                'usage_count' => 15,
            ],
            [
                'id' => 5,
                'name' => 'Graveyard Shift Template (11PM-7AM)',
                'shift_type' => 'graveyard',
                'shift_start' => '23:00:00',
                'shift_end' => '07:00:00',
                'is_active' => true,
                'usage_count' => 12,
            ],
        ];

        $filters = [
            'search' => $request->input('search', ''),
            'department_id' => $request->input('department_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'is_template' => $request->input('is_template'),
        ];

        return Inertia::render('HR/Workforce/Schedules/Index', [
            'schedules' => $schedules,
            'summary' => $summary,
            'departments' => $departments,
            'templates' => $templates,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new schedule.
     */
    public function create(): Response
    {
        // Mock departments
        $departments = [
            ['id' => 1, 'name' => 'Human Resources', 'code' => 'HR'],
            ['id' => 3, 'name' => 'Rolling Mill 3', 'code' => 'RM3'],
            ['id' => 4, 'name' => 'Wire Mill', 'code' => 'WM'],
            ['id' => 5, 'name' => 'Quality Assurance', 'code' => 'QA'],
            ['id' => 6, 'name' => 'Maintenance', 'code' => 'MNT'],
        ];

        // Mock employees for assignment
        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
            ['id' => 2, 'employee_number' => 'EMP002', 'full_name' => 'Maria Santos', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
            ['id' => 3, 'employee_number' => 'EMP003', 'full_name' => 'Pedro Reyes', 'department_id' => 4, 'department_name' => 'Wire Mill'],
        ];

        // Mock templates
        $templates = [
            [
                'id' => 1,
                'name' => 'Morning Shift Template (6AM-2PM)',
                'shift_type' => 'morning',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Afternoon Shift Template (2PM-10PM)',
                'shift_type' => 'afternoon',
                'shift_start' => '14:00:00',
                'shift_end' => '22:00:00',
            ],
        ];

        return Inertia::render('HR/Workforce/Schedules/Create', [
            'departments' => $departments,
            'employees' => $employees,
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created schedule in storage.
     */
    public function store(Request $request)
    {
        // Validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'effective_date' => 'required|date',
        ]);

        // Mock: Just return success message
        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule created successfully.');
    }

    /**
     * Display the specified schedule.
     */
    public function show(string $id): Response
    {
        // Mock schedule detail
        $schedule = [
            'id' => 1,
            'name' => 'Standard Day Shift - Production',
            'description' => '8-hour day shift for production employees',
            'effective_date' => '2025-01-01',
            'expires_at' => '2025-12-31',
            'status' => 'active',
            'monday_start' => '06:00:00',
            'monday_end' => '14:00:00',
            'department_id' => 3,
            'department_name' => 'Rolling Mill 3',
            'assigned_employees_count' => 45,
        ];

        return Inertia::render('HR/Workforce/Schedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Show the form for editing the specified schedule.
     */
    public function edit(string $id): Response
    {
        // Mock schedule data
        $schedule = [
            'id' => 1,
            'name' => 'Standard Day Shift - Production',
            'description' => '8-hour day shift for production employees',
            'effective_date' => '2025-01-01',
            'expires_at' => '2025-12-31',
        ];

        $departments = [
            ['id' => 1, 'name' => 'Human Resources'],
            ['id' => 3, 'name' => 'Rolling Mill 3'],
        ];

        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz'],
        ];

        $templates = [
            ['id' => 1, 'name' => 'Morning Shift Template', 'shift_start' => '06:00:00', 'shift_end' => '14:00:00'],
        ];

        return Inertia::render('HR/Workforce/Schedules/Edit', [
            'schedule' => $schedule,
            'departments' => $departments,
            'employees' => $employees,
            'templates' => $templates,
        ]);
    }

    /**
     * Update the specified schedule in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'effective_date' => 'required|date',
        ]);

        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove the specified schedule from storage.
     */
    public function destroy(string $id)
    {
        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule deleted successfully.');
    }
}
