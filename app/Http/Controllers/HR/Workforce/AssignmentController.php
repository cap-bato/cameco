<?php

namespace App\Http\Controllers\HR\Workforce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    /**
     * Display a listing of shift assignments with mock data.
     */
    public function index(Request $request): Response
    {
        // Mock data: 15 shift assignments (various statuses)
        $assignments = [
            [
                'id' => 1,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP001',
                'schedule_id' => 1,
                'schedule_name' => 'Standard Day Shift - Production',
                'date' => '2025-11-13',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Rolling Mill 3 - Production Floor',
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 2,
                'employee_id' => 2,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP002',
                'schedule_id' => 1,
                'schedule_name' => 'Standard Day Shift - Production',
                'date' => '2025-11-13',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Rolling Mill 3 - Production Floor',
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'in_progress',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-13 06:00:00',
            ],
            [
                'id' => 3,
                'employee_id' => 3,
                'employee_name' => 'Pedro Reyes',
                'employee_number' => 'EMP003',
                'schedule_id' => 2,
                'schedule_name' => 'Night Shift - Wire Mill',
                'date' => '2025-11-13',
                'shift_start' => '22:00:00',
                'shift_end' => '06:00:00',
                'shift_type' => 'night',
                'location' => 'Wire Mill - Building 2',
                'department_id' => 4,
                'department_name' => 'Wire Mill',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 4,
                'employee_id' => 4,
                'employee_name' => 'Ana Garcia',
                'employee_number' => 'EMP004',
                'schedule_id' => 3,
                'schedule_name' => 'Afternoon Shift - Quality Control',
                'date' => '2025-11-13',
                'shift_start' => '14:00:00',
                'shift_end' => '22:00:00',
                'shift_type' => 'afternoon',
                'location' => 'QA Lab - Building 1',
                'department_id' => 5,
                'department_name' => 'Quality Assurance',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 5,
                'employee_id' => 5,
                'employee_name' => 'Carlos Mendoza',
                'employee_number' => 'EMP005',
                'schedule_id' => 1,
                'schedule_name' => 'Standard Day Shift - Production',
                'date' => '2025-11-13',
                'shift_start' => '06:00:00',
                'shift_end' => '16:00:00',
                'shift_type' => 'morning',
                'location' => 'Rolling Mill 3 - Production Floor',
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'is_overtime' => true,
                'overtime_hours' => 2,
                'status' => 'scheduled',
                'notes' => 'Overtime approved for urgent production deadline',
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 6,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP001',
                'schedule_id' => 1,
                'schedule_name' => 'Standard Day Shift - Production',
                'date' => '2025-11-12',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Rolling Mill 3 - Production Floor',
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'completed',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-09 10:00:00',
                'updated_at' => '2025-11-12 14:05:00',
            ],
            [
                'id' => 7,
                'employee_id' => 6,
                'employee_name' => 'Rosa Villanueva',
                'employee_number' => 'EMP006',
                'schedule_id' => 4,
                'schedule_name' => 'Office Hours - Admin',
                'date' => '2025-11-13',
                'shift_start' => '08:00:00',
                'shift_end' => '17:00:00',
                'shift_type' => 'custom',
                'location' => 'Admin Building',
                'department_id' => 1,
                'department_name' => 'Human Resources',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 8,
                'employee_id' => 7,
                'employee_name' => 'Miguel Torres',
                'employee_number' => 'EMP007',
                'schedule_id' => 5,
                'schedule_name' => '6-Day Work Week - Maintenance',
                'date' => '2025-11-13',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Maintenance Shop',
                'department_id' => 6,
                'department_name' => 'Maintenance',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 9,
                'employee_id' => 8,
                'employee_name' => 'Elena Cruz',
                'employee_number' => 'EMP008',
                'schedule_id' => 1,
                'schedule_name' => 'Standard Day Shift - Production',
                'date' => '2025-11-14',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Rolling Mill 3 - Production Floor',
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => true,
                'conflict_reason' => 'Employee is already assigned to another shift on this date',
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 10,
                'employee_id' => 9,
                'employee_name' => 'Roberto Silva',
                'employee_number' => 'EMP009',
                'schedule_id' => 2,
                'schedule_name' => 'Night Shift - Wire Mill',
                'date' => '2025-11-14',
                'shift_start' => '22:00:00',
                'shift_end' => '06:00:00',
                'shift_type' => 'night',
                'location' => 'Wire Mill - Building 2',
                'department_id' => 4,
                'department_name' => 'Wire Mill',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 11,
                'employee_id' => 10,
                'employee_name' => 'Liza Aquino',
                'employee_number' => 'EMP010',
                'schedule_id' => 1,
                'schedule_name' => 'Standard Day Shift - Production',
                'date' => '2025-11-11',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Rolling Mill 3 - Production Floor',
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'cancelled',
                'notes' => 'Cancelled due to employee sick leave',
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-08 10:00:00',
                'updated_at' => '2025-11-11 07:00:00',
            ],
            [
                'id' => 12,
                'employee_id' => 11,
                'employee_name' => 'Fernando Ramos',
                'employee_number' => 'EMP011',
                'schedule_id' => 3,
                'schedule_name' => 'Afternoon Shift - Quality Control',
                'date' => '2025-11-15',
                'shift_start' => '14:00:00',
                'shift_end' => '22:00:00',
                'shift_type' => 'afternoon',
                'location' => 'QA Lab - Building 1',
                'department_id' => 5,
                'department_name' => 'Quality Assurance',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 13,
                'employee_id' => 12,
                'employee_name' => 'Sofia Morales',
                'employee_number' => 'EMP012',
                'schedule_id' => 5,
                'schedule_name' => '6-Day Work Week - Maintenance',
                'date' => '2025-11-16',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'location' => 'Maintenance Shop',
                'department_id' => 6,
                'department_name' => 'Maintenance',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 14,
                'employee_id' => 13,
                'employee_name' => 'Diego Fernandez',
                'employee_number' => 'EMP013',
                'schedule_id' => 2,
                'schedule_name' => 'Night Shift - Wire Mill',
                'date' => '2025-11-14',
                'shift_start' => '22:00:00',
                'shift_end' => '08:00:00',
                'shift_type' => 'graveyard',
                'location' => 'Wire Mill - Building 2',
                'department_id' => 4,
                'department_name' => 'Wire Mill',
                'is_overtime' => true,
                'overtime_hours' => 2,
                'status' => 'scheduled',
                'notes' => 'Extended shift for equipment maintenance',
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
            [
                'id' => 15,
                'employee_id' => 14,
                'employee_name' => 'Carmen Lopez',
                'employee_number' => 'EMP014',
                'schedule_id' => 4,
                'schedule_name' => 'Office Hours - Admin',
                'date' => '2025-11-13',
                'shift_start' => '08:00:00',
                'shift_end' => '17:00:00',
                'shift_type' => 'custom',
                'location' => 'Admin Building',
                'department_id' => 1,
                'department_name' => 'Human Resources',
                'is_overtime' => false,
                'overtime_hours' => 0,
                'status' => 'scheduled',
                'notes' => null,
                'has_conflict' => false,
                'conflict_reason' => null,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2025-11-10 10:00:00',
                'updated_at' => '2025-11-10 10:00:00',
            ],
        ];

        // Mock summary statistics
        $summary = [
            'total_assignments' => 15,
            'todays_shifts' => 7,
            'coverage_percentage' => 88.5,
            'overtime_hours' => 4,
            'conflicts_count' => 1,
            'understaffed_days' => 2,
        ];

        // Mock departments
        $departments = [
            ['id' => 1, 'name' => 'Human Resources', 'code' => 'HR'],
            ['id' => 3, 'name' => 'Rolling Mill 3', 'code' => 'RM3'],
            ['id' => 4, 'name' => 'Wire Mill', 'code' => 'WM'],
            ['id' => 5, 'name' => 'Quality Assurance', 'code' => 'QA'],
            ['id' => 6, 'name' => 'Maintenance', 'code' => 'MNT'],
        ];

        // Mock employees
        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
            ['id' => 2, 'employee_number' => 'EMP002', 'full_name' => 'Maria Santos', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
            ['id' => 3, 'employee_number' => 'EMP003', 'full_name' => 'Pedro Reyes', 'department_id' => 4, 'department_name' => 'Wire Mill'],
            ['id' => 4, 'employee_number' => 'EMP004', 'full_name' => 'Ana Garcia', 'department_id' => 5, 'department_name' => 'Quality Assurance'],
            ['id' => 5, 'employee_number' => 'EMP005', 'full_name' => 'Carlos Mendoza', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
        ];

        // Mock schedules
        $schedules = [
            ['id' => 1, 'name' => 'Standard Day Shift - Production', 'shift_start' => '06:00:00', 'shift_end' => '14:00:00'],
            ['id' => 2, 'name' => 'Night Shift - Wire Mill', 'shift_start' => '22:00:00', 'shift_end' => '06:00:00'],
            ['id' => 3, 'name' => 'Afternoon Shift - Quality Control', 'shift_start' => '14:00:00', 'shift_end' => '22:00:00'],
            ['id' => 4, 'name' => 'Office Hours - Admin', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00'],
            ['id' => 5, 'name' => '6-Day Work Week - Maintenance', 'shift_start' => '06:00:00', 'shift_end' => '14:00:00'],
        ];

        $filters = [
            'search' => $request->input('search', ''),
            'employee_id' => $request->input('employee_id'),
            'department_id' => $request->input('department_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'shift_type' => $request->input('shift_type'),
            'status' => $request->input('status'),
            'is_overtime' => $request->input('is_overtime'),
            'has_conflict' => $request->input('has_conflict'),
        ];

        return Inertia::render('HR/Workforce/Assignments/Index', [
            'assignments' => $assignments,
            'summary' => $summary,
            'departments' => $departments,
            'employees' => $employees,
            'schedules' => $schedules,
            'filters' => $filters,
            'view_mode' => $request->input('view_mode', 'list'),
        ]);
    }

    /**
     * Show the form for creating a new assignment.
     */
    public function create(): Response
    {
        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
            ['id' => 2, 'employee_number' => 'EMP002', 'full_name' => 'Maria Santos', 'department_id' => 3, 'department_name' => 'Rolling Mill 3'],
        ];

        $schedules = [
            ['id' => 1, 'name' => 'Standard Day Shift - Production', 'shift_start' => '06:00:00', 'shift_end' => '14:00:00'],
            ['id' => 2, 'name' => 'Night Shift - Wire Mill', 'shift_start' => '22:00:00', 'shift_end' => '06:00:00'],
        ];

        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3'],
            ['id' => 4, 'name' => 'Wire Mill'],
        ];

        $shiftTemplates = [
            ['id' => 1, 'name' => 'Morning Shift (6AM-2PM)', 'shift_type' => 'morning', 'shift_start' => '06:00:00', 'shift_end' => '14:00:00'],
            ['id' => 2, 'name' => 'Afternoon Shift (2PM-10PM)', 'shift_type' => 'afternoon', 'shift_start' => '14:00:00', 'shift_end' => '22:00:00'],
        ];

        return Inertia::render('HR/Workforce/Assignments/Create', [
            'employees' => $employees,
            'schedules' => $schedules,
            'departments' => $departments,
            'shift_templates' => $shiftTemplates,
        ]);
    }

    /**
     * Store a newly created assignment in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'schedule_id' => 'required|integer',
            'date' => 'required|date',
            'shift_start' => 'required',
            'shift_end' => 'required',
        ]);

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Shift assignment created successfully.');
    }

    /**
     * Bulk assign shifts to multiple employees.
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'schedule_id' => 'required|integer',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Bulk shift assignments created successfully.');
    }

    /**
     * Display the specified assignment.
     */
    public function show(string $id): Response
    {
        $assignment = [
            'id' => 1,
            'employee_name' => 'Juan dela Cruz',
            'employee_number' => 'EMP001',
            'schedule_name' => 'Standard Day Shift - Production',
            'date' => '2025-11-13',
            'shift_start' => '06:00:00',
            'shift_end' => '14:00:00',
            'shift_type' => 'morning',
            'location' => 'Rolling Mill 3 - Production Floor',
            'department_name' => 'Rolling Mill 3',
            'is_overtime' => false,
            'status' => 'scheduled',
        ];

        return Inertia::render('HR/Workforce/Assignments/Show', [
            'assignment' => $assignment,
        ]);
    }

    /**
     * Show the form for editing the specified assignment.
     */
    public function edit(string $id): Response
    {
        $assignment = [
            'id' => 1,
            'employee_id' => 1,
            'schedule_id' => 1,
            'date' => '2025-11-13',
            'shift_start' => '06:00:00',
            'shift_end' => '14:00:00',
            'shift_type' => 'morning',
            'location' => 'Rolling Mill 3 - Production Floor',
            'is_overtime' => false,
            'notes' => null,
        ];

        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz'],
        ];

        $schedules = [
            ['id' => 1, 'name' => 'Standard Day Shift - Production'],
        ];

        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3'],
        ];

        $shiftTemplates = [
            ['id' => 1, 'name' => 'Morning Shift (6AM-2PM)', 'shift_type' => 'morning'],
        ];

        return Inertia::render('HR/Workforce/Assignments/Edit', [
            'assignment' => $assignment,
            'employees' => $employees,
            'schedules' => $schedules,
            'departments' => $departments,
            'shift_templates' => $shiftTemplates,
        ]);
    }

    /**
     * Update the specified assignment in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'schedule_id' => 'required|integer',
            'date' => 'required|date',
            'shift_start' => 'required',
            'shift_end' => 'required',
        ]);

        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Shift assignment updated successfully.');
    }

    /**
     * Remove the specified assignment from storage.
     */
    public function destroy(string $id)
    {
        return redirect()->route('hr.workforce.assignments.index')
            ->with('success', 'Shift assignment deleted successfully.');
    }

    /**
     * Get coverage analytics for a date range.
     */
    public function coverage(Request $request): Response
    {
        // Mock coverage report data
        $coverageReport = [
            [
                'date' => '2025-11-13',
                'total_shifts' => 50,
                'assigned_shifts' => 45,
                'unassigned_shifts' => 5,
                'coverage_percentage' => 90,
                'coverage_level' => 'optimal',
                'department_breakdown' => [
                    ['department_id' => 3, 'department_name' => 'Rolling Mill 3', 'total_shifts' => 20, 'assigned_shifts' => 18, 'coverage_percentage' => 90],
                    ['department_id' => 4, 'department_name' => 'Wire Mill', 'total_shifts' => 15, 'assigned_shifts' => 14, 'coverage_percentage' => 93],
                    ['department_id' => 5, 'department_name' => 'Quality Assurance', 'total_shifts' => 15, 'assigned_shifts' => 13, 'coverage_percentage' => 87],
                ],
            ],
            [
                'date' => '2025-11-14',
                'total_shifts' => 50,
                'assigned_shifts' => 38,
                'unassigned_shifts' => 12,
                'coverage_percentage' => 76,
                'coverage_level' => 'adequate',
                'department_breakdown' => [
                    ['department_id' => 3, 'department_name' => 'Rolling Mill 3', 'total_shifts' => 20, 'assigned_shifts' => 15, 'coverage_percentage' => 75],
                    ['department_id' => 4, 'department_name' => 'Wire Mill', 'total_shifts' => 15, 'assigned_shifts' => 12, 'coverage_percentage' => 80],
                    ['department_id' => 5, 'department_name' => 'Quality Assurance', 'total_shifts' => 15, 'assigned_shifts' => 11, 'coverage_percentage' => 73],
                ],
            ],
            [
                'date' => '2025-11-15',
                'total_shifts' => 50,
                'assigned_shifts' => 30,
                'unassigned_shifts' => 20,
                'coverage_percentage' => 60,
                'coverage_level' => 'low',
                'department_breakdown' => [
                    ['department_id' => 3, 'department_name' => 'Rolling Mill 3', 'total_shifts' => 20, 'assigned_shifts' => 12, 'coverage_percentage' => 60],
                    ['department_id' => 4, 'department_name' => 'Wire Mill', 'total_shifts' => 15, 'assigned_shifts' => 9, 'coverage_percentage' => 60],
                    ['department_id' => 5, 'department_name' => 'Quality Assurance', 'total_shifts' => 15, 'assigned_shifts' => 9, 'coverage_percentage' => 60],
                ],
            ],
        ];

        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3'],
            ['id' => 4, 'name' => 'Wire Mill'],
            ['id' => 5, 'name' => 'Quality Assurance'],
        ];

        $summary = [
            'total_assignments' => 113,
            'todays_shifts' => 45,
            'coverage_percentage' => 75.3,
            'overtime_hours' => 8,
            'conflicts_count' => 2,
            'understaffed_days' => 1,
        ];

        return Inertia::render('HR/Workforce/Assignments/Coverage', [
            'coverage_report' => $coverageReport,
            'departments' => $departments,
            'summary' => $summary,
            'date_range' => [
                'start' => $request->input('date_from', '2025-11-13'),
                'end' => $request->input('date_to', '2025-11-20'),
            ],
        ]);
    }

    /**
     * Resolve conflicts for an assignment.
     */
    public function resolveConflict(string $id)
    {
        // Mock: Resolve conflict
        return response()->json([
            'success' => true,
            'message' => 'Conflict resolved successfully.',
            'assignment_id' => $id,
        ]);
    }

    /**
     * Mark assignment as overtime.
     */
    public function markOvertime(Request $request, string $id)
    {
        $request->validate([
            'overtime_hours' => 'required|numeric|min:0',
        ]);

        // Mock: Mark as overtime
        return response()->json([
            'success' => true,
            'message' => 'Assignment marked as overtime successfully.',
            'overtime_hours' => $request->input('overtime_hours'),
        ]);
    }

    /**
     * Get conflicts for an employee on a specific date.
     */
    public function getConflicts(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date',
        ]);

        // Mock: Get conflicts
        $conflicts = [
            [
                'id' => 1,
                'assignment_id' => 101,
                'conflict_type' => 'overlap',
                'conflicting_assignment_id' => 102,
                'conflicting_shift' => '14:00:00 - 22:00:00',
                'conflict_reason' => 'Overlapping shift times',
            ],
            [
                'id' => 2,
                'assignment_id' => 101,
                'conflict_type' => 'consecutive_days',
                'days_without_rest' => 8,
                'conflict_reason' => 'Employee has worked 8 consecutive days',
            ],
        ];

        return response()->json($conflicts);
    }

    /**
     * Export assignments to CSV.
     */
    public function exportCsv(Request $request)
    {
        // Mock: Generate CSV export
        $filename = 'assignments_' . date('Y-m-d_H-i-s') . '.csv';

        return response()->download(storage_path("app/exports/{$filename}"))
            ->header('Content-Type', 'text/csv');
    }

    /**
     * Get shift assignment statistics.
     */
    public function getStatistics(Request $request)
    {
        // Mock statistics data
        $statistics = [
            'total_assignments' => 125,
            'scheduled_assignments' => 110,
            'overtime_assignments' => 15,
            'conflicted_assignments' => 3,
            'cancelled_assignments' => 2,
            'employee_coverage' => 91.2,
            'department_distribution' => [
                ['department' => 'Rolling Mill 3', 'count' => 45],
                ['department' => 'Wire Mill', 'count' => 32],
                ['department' => 'Quality Assurance', 'count' => 28],
                ['department' => 'Maintenance', 'count' => 20],
            ],
            'shift_type_distribution' => [
                ['shift_type' => 'Morning (6AM-2PM)', 'count' => 45],
                ['shift_type' => 'Afternoon (2PM-10PM)', 'count' => 40],
                ['shift_type' => 'Night (10PM-6AM)', 'count' => 28],
                ['shift_type' => 'Other', 'count' => 12],
            ],
            'overtime_metrics' => [
                'total_overtime_hours' => 127.5,
                'average_overtime_per_employee' => 8.5,
                'employees_with_overtime' => 15,
            ],
        ];

        return response()->json($statistics);
    }

    /**
     * Get assignments for a specific employee.
     */
    public function getEmployeeAssignments(Request $request, string $employeeId)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after:date_from',
        ]);

        // Mock: Employee assignments
        $assignments = [
            [
                'id' => 1,
                'date' => '2025-11-23',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'status' => 'scheduled',
                'is_overtime' => false,
                'overtime_hours' => 0,
            ],
            [
                'id' => 2,
                'date' => '2025-11-24',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'status' => 'scheduled',
                'is_overtime' => false,
                'overtime_hours' => 0,
            ],
            [
                'id' => 3,
                'date' => '2025-11-25',
                'shift_start' => '06:00:00',
                'shift_end' => '16:00:00',
                'shift_type' => 'morning',
                'status' => 'scheduled',
                'is_overtime' => true,
                'overtime_hours' => 2,
            ],
        ];

        return response()->json($assignments);
    }

    /**
     * Get shift assignments for a specific date.
     */
    public function getDateAssignments(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        // Mock: Assignments for date
        $assignments = [
            [
                'id' => 1,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP001',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'status' => 'scheduled',
                'department' => 'Rolling Mill 3',
            ],
            [
                'id' => 2,
                'employee_id' => 2,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP002',
                'shift_start' => '06:00:00',
                'shift_end' => '14:00:00',
                'shift_type' => 'morning',
                'status' => 'scheduled',
                'department' => 'Rolling Mill 3',
            ],
        ];

        return response()->json($assignments);
    }

    /**
     * Get coverage analysis for a date range.
     */
    public function getCoverageAnalysis(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after:date_from',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        // Mock: Coverage analysis
        $analysis = [
            'period' => [
                'start' => $request->input('date_from'),
                'end' => $request->input('date_to'),
            ],
            'coverage_percentage' => 92.5,
            'average_daily_coverage' => 88,
            'days_at_full_capacity' => 5,
            'days_understaffed' => 1,
            'peak_coverage_day' => '2025-11-23',
            'lowest_coverage_day' => '2025-11-25',
            'by_department' => [
                ['department' => 'Rolling Mill 3', 'coverage' => 95],
                ['department' => 'Wire Mill', 'coverage' => 90],
                ['department' => 'Quality Assurance', 'coverage' => 85],
                ['department' => 'Maintenance', 'coverage' => 88],
            ],
        ];

        return response()->json($analysis);
    }
}
