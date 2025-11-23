<?php

namespace App\Http\Controllers\HR\Appraisal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

/**
 * AppraisalCycleController
 *
 * Manages appraisal cycles - performance review periods (e.g., Annual Review 2025)
 * HR Managers create cycles, define criteria and scoring rubrics, and manage employee assignments.
 *
 * Workflow:
 * 1. Create cycle with name, date range, and criteria
 * 2. Assign employees to cycle (individually or by department)
 * 3. Monitor completion progress as appraisals are filled out
 * 4. Close cycle when all appraisals are completed and acknowledged
 */
class AppraisalCycleController extends Controller
{
    /**
     * Display a listing of appraisal cycles
     */
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');
        $year = $request->input('year', date('Y'));

        // Mock appraisal cycles data
        $mockCycles = [
            [
                'id' => 1,
                'name' => 'Annual Review 2025',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'status' => 'open',
                'total_appraisals' => 45,
                'completed_appraisals' => 38,
                'average_score' => 7.4,
                'created_by' => 'HR Manager',
                'created_at' => '2025-01-05 08:30:00',
                'updated_at' => '2025-11-20 14:22:00',
            ],
            [
                'id' => 2,
                'name' => 'Mid-Year Review 2025',
                'start_date' => '2025-06-01',
                'end_date' => '2025-06-30',
                'status' => 'open',
                'total_appraisals' => 45,
                'completed_appraisals' => 18,
                'average_score' => 7.1,
                'created_by' => 'HR Manager',
                'created_at' => '2025-05-25 10:15:00',
                'updated_at' => '2025-11-15 09:45:00',
            ],
            [
                'id' => 3,
                'name' => 'Annual Review 2024',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'status' => 'closed',
                'total_appraisals' => 42,
                'completed_appraisals' => 42,
                'average_score' => 7.2,
                'created_by' => 'HR Manager',
                'created_at' => '2024-01-08 07:30:00',
                'updated_at' => '2024-12-20 16:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Mid-Year Review 2024',
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-30',
                'status' => 'closed',
                'total_appraisals' => 42,
                'completed_appraisals' => 42,
                'average_score' => 6.9,
                'created_by' => 'HR Manager',
                'created_at' => '2024-05-20 09:00:00',
                'updated_at' => '2024-06-30 17:30:00',
            ],
            [
                'id' => 5,
                'name' => 'Annual Review 2023',
                'start_date' => '2023-01-01',
                'end_date' => '2023-12-31',
                'status' => 'closed',
                'total_appraisals' => 38,
                'completed_appraisals' => 38,
                'average_score' => 7.0,
                'created_by' => 'HR Manager',
                'created_at' => '2023-01-10 08:00:00',
                'updated_at' => '2023-12-22 15:45:00',
            ],
        ];

        // Filter by status
        if ($status !== 'all') {
            $mockCycles = array_filter($mockCycles, fn($c) => $c['status'] === $status);
        }

        // Filter by year
        $mockCycles = array_filter($mockCycles, function ($c) use ($year) {
            $startYear = substr($c['start_date'], 0, 4);
            return $startYear === $year;
        });

        // Calculate stats
        $allCycles = [
            [
                'id' => 1,
                'name' => 'Annual Review 2025',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'status' => 'open',
                'total_appraisals' => 45,
                'completed_appraisals' => 38,
                'average_score' => 7.4,
                'created_by' => 'HR Manager',
                'created_at' => '2025-01-05 08:30:00',
                'updated_at' => '2025-11-20 14:22:00',
            ],
            [
                'id' => 2,
                'name' => 'Mid-Year Review 2025',
                'start_date' => '2025-06-01',
                'end_date' => '2025-06-30',
                'status' => 'open',
                'total_appraisals' => 45,
                'completed_appraisals' => 18,
                'average_score' => 7.1,
                'created_by' => 'HR Manager',
                'created_at' => '2025-05-25 10:15:00',
                'updated_at' => '2025-11-15 09:45:00',
            ],
            [
                'id' => 3,
                'name' => 'Annual Review 2024',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'status' => 'closed',
                'total_appraisals' => 42,
                'completed_appraisals' => 42,
                'average_score' => 7.2,
                'created_by' => 'HR Manager',
                'created_at' => '2024-01-08 07:30:00',
                'updated_at' => '2024-12-20 16:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Mid-Year Review 2024',
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-30',
                'status' => 'closed',
                'total_appraisals' => 42,
                'completed_appraisals' => 42,
                'average_score' => 6.9,
                'created_by' => 'HR Manager',
                'created_at' => '2024-05-20 09:00:00',
                'updated_at' => '2024-06-30 17:30:00',
            ],
            [
                'id' => 5,
                'name' => 'Annual Review 2023',
                'start_date' => '2023-01-01',
                'end_date' => '2023-12-31',
                'status' => 'closed',
                'total_appraisals' => 38,
                'completed_appraisals' => 38,
                'average_score' => 7.0,
                'created_by' => 'HR Manager',
                'created_at' => '2023-01-10 08:00:00',
                'updated_at' => '2023-12-22 15:45:00',
            ],
        ];

        $totalCycles = count($allCycles);
        $activeCycles = count(array_filter($allCycles, fn($c) => $c['status'] === 'open'));
        $avgCompletion = count($allCycles) > 0 
            ? round(array_reduce($allCycles, function ($carry, $c) {
                return $carry + ($c['completed_appraisals'] / $c['total_appraisals'] * 100);
            }, 0) / count($allCycles), 1)
            : 0;
        $pendingAppraisals = array_reduce($allCycles, function ($carry, $c) {
            return $carry + ($c['total_appraisals'] - $c['completed_appraisals']);
        }, 0);

        // Mock employees data for assignment modal
        $mockEmployees = [
            ['id' => 1, 'name' => 'John Doe', 'employee_number' => 'EMP001', 'department' => 'Engineering', 'position' => 'Software Engineer'],
            ['id' => 2, 'name' => 'Jane Smith', 'employee_number' => 'EMP002', 'department' => 'Engineering', 'position' => 'Senior Engineer'],
            ['id' => 3, 'name' => 'Bob Johnson', 'employee_number' => 'EMP003', 'department' => 'Sales', 'position' => 'Sales Manager'],
            ['id' => 4, 'name' => 'Alice Williams', 'employee_number' => 'EMP004', 'department' => 'Marketing', 'position' => 'Marketing Specialist'],
            ['id' => 5, 'name' => 'Charlie Brown', 'employee_number' => 'EMP005', 'department' => 'HR', 'position' => 'HR Specialist'],
            ['id' => 6, 'name' => 'Diana Davis', 'employee_number' => 'EMP006', 'department' => 'Finance', 'position' => 'Accountant'],
            ['id' => 7, 'name' => 'Eve Miller', 'employee_number' => 'EMP007', 'department' => 'Engineering', 'position' => 'QA Engineer'],
            ['id' => 8, 'name' => 'Frank Wilson', 'employee_number' => 'EMP008', 'department' => 'Operations', 'position' => 'Operations Manager'],
            ['id' => 9, 'name' => 'Grace Taylor', 'employee_number' => 'EMP009', 'department' => 'Sales', 'position' => 'Sales Executive'],
            ['id' => 10, 'name' => 'Henry Anderson', 'employee_number' => 'EMP010', 'department' => 'Marketing', 'position' => 'Marketing Manager'],
        ];

        // Add completion_percentage to cycles
        $filteredCyclesWithPercentage = array_map(function ($c) {
            return array_merge($c, [
                'completion_percentage' => $c['total_appraisals'] > 0 
                    ? round(($c['completed_appraisals'] / $c['total_appraisals']) * 100)
                    : 0,
                'criteria' => [
                    ['name' => 'Quality of Work', 'weight' => 20],
                    ['name' => 'Attendance & Punctuality', 'weight' => 20],
                    ['name' => 'Behavior & Conduct', 'weight' => 20],
                    ['name' => 'Productivity', 'weight' => 20],
                    ['name' => 'Teamwork', 'weight' => 20],
                ],
            ]);
        }, array_values($mockCycles));

        return Inertia::render('HR/Appraisals/Cycles/Index', [
            'cycles' => $filteredCyclesWithPercentage,
            'employees' => $mockEmployees,
            'stats' => [
                'total_cycles' => $totalCycles,
                'active_cycles' => $activeCycles,
                'avg_completion_rate' => $avgCompletion,
                'pending_appraisals' => $pendingAppraisals,
            ],
            'filters' => [
                'status' => $status,
                'year' => $year,
            ],
        ]);
    }

    /**
     * Show the form for creating a new appraisal cycle
     */
    public function create()
    {
        // Return empty form with default criteria
        $defaultCriteria = [
            ['name' => 'Quality of Work', 'weight' => 20],
            ['name' => 'Attendance & Punctuality', 'weight' => 20],
            ['name' => 'Behavior & Conduct', 'weight' => 20],
            ['name' => 'Productivity', 'weight' => 20],
            ['name' => 'Teamwork', 'weight' => 20],
        ];

        return Inertia::render('HR/Appraisals/Cycles/Create', [
            'defaultCriteria' => $defaultCriteria,
        ]);
    }

    /**
     * Store a newly created appraisal cycle
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'criteria' => 'required|array|min:3',
            'criteria.*.name' => 'required|string|max:100',
            'criteria.*.weight' => 'required|numeric|min:1|max:100',
        ]);

        // Verify total weight equals 100
        $totalWeight = array_sum(array_column($validated['criteria'], 'weight'));
        if ($totalWeight !== 100) {
            return back()->withErrors(['criteria' => 'Total weight must equal 100%']);
        }

        // In production, save to database
        // For now, return success response
        return redirect()
            ->route('hr.appraisals.cycles.index')
            ->with('success', 'Appraisal cycle created successfully');
    }

    /**
     * Display the specified appraisal cycle details
     */
    public function show($id)
    {
        // Mock cycle details with analytics
        $cycle = [
            'id' => 1,
            'name' => 'Annual Review 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'open',
            'total_appraisals' => 45,
            'completed_appraisals' => 38,
            'average_score' => 7.4,
            'created_by' => 'HR Manager',
            'created_at' => '2025-01-05 08:30:00',
            'updated_at' => '2025-11-20 14:22:00',
        ];

        // Mock appraisals in this cycle
        $appraisals = [
            [
                'id' => 1,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2023-001',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'cycle_id' => 1,
                'cycle_name' => 'Annual Review 2025',
                'status' => 'completed',
                'status_label' => 'Completed',
                'status_color' => 'bg-green-100 text-green-800',
                'overall_score' => 8.2,
                'feedback' => 'Excellent performance',
                'scores' => [],
                'attendance_rate' => 98,
                'lateness_count' => 1,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-01-10',
                'updated_at' => '2025-01-15',
            ],
            [
                'id' => 2,
                'employee_id' => 2,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP-2023-002',
                'department_id' => 2,
                'department_name' => 'Finance',
                'cycle_id' => 1,
                'cycle_name' => 'Annual Review 2025',
                'status' => 'completed',
                'status_label' => 'Completed',
                'status_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.8,
                'feedback' => 'Good performance',
                'scores' => [],
                'attendance_rate' => 96,
                'lateness_count' => 2,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-01-15',
                'updated_at' => '2025-01-18',
            ],
            [
                'id' => 3,
                'employee_id' => 3,
                'employee_name' => 'Carlos Reyes',
                'employee_number' => 'EMP-2023-003',
                'department_id' => 3,
                'department_name' => 'Operations',
                'cycle_id' => 1,
                'cycle_name' => 'Annual Review 2025',
                'status' => 'in_progress',
                'status_label' => 'In Progress',
                'status_color' => 'bg-blue-100 text-blue-800',
                'overall_score' => null,
                'feedback' => null,
                'scores' => [],
                'attendance_rate' => 94,
                'lateness_count' => 3,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => null,
                'created_at' => '2025-01-12',
                'updated_at' => '2025-01-12',
            ],
            [
                'id' => 4,
                'employee_id' => 4,
                'employee_name' => 'Ana Garcia',
                'employee_number' => 'EMP-2023-004',
                'department_id' => 4,
                'department_name' => 'Sales',
                'cycle_id' => 1,
                'cycle_name' => 'Annual Review 2025',
                'status' => 'draft',
                'status_label' => 'Draft',
                'status_color' => 'bg-gray-100 text-gray-800',
                'overall_score' => null,
                'feedback' => null,
                'scores' => [],
                'attendance_rate' => 92,
                'lateness_count' => 4,
                'violation_count' => 1,
                'created_by' => 'HR Manager',
                'updated_by' => null,
                'created_at' => '2025-01-20',
                'updated_at' => '2025-01-20',
            ],
            [
                'id' => 5,
                'employee_id' => 5,
                'employee_name' => 'Miguel Torres',
                'employee_number' => 'EMP-2023-005',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'cycle_id' => 1,
                'cycle_name' => 'Annual Review 2025',
                'status' => 'completed',
                'status_label' => 'Completed',
                'status_color' => 'bg-green-100 text-green-800',
                'overall_score' => 6.9,
                'feedback' => 'Satisfactory performance',
                'scores' => [],
                'attendance_rate' => 97,
                'lateness_count' => 1,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-01-18',
                'updated_at' => '2025-01-22',
            ],
        ];

        // Mock analytics data - matches CycleAnalytics interface
        $analytics = [
            'cycle_id' => 1,
            'total_appraisals' => 45,
            'completed_appraisals' => 38,
            'completion_rate' => 84.4,
            'average_score' => 7.4,
            'high_performers' => 15,
            'medium_performers' => 18,
            'low_performers' => 5,
            'department_breakdown' => [
                [
                    'id' => 1,
                    'name' => 'Engineering',
                    'average_score' => 7.5,
                    'total_employees' => 10,
                    'appraised_employees' => 8,
                ],
                [
                    'id' => 2,
                    'name' => 'Finance',
                    'average_score' => 7.6,
                    'total_employees' => 8,
                    'appraised_employees' => 6,
                ],
                [
                    'id' => 3,
                    'name' => 'Operations',
                    'average_score' => 7.1,
                    'total_employees' => 14,
                    'appraised_employees' => 12,
                ],
                [
                    'id' => 4,
                    'name' => 'Sales',
                    'average_score' => 7.2,
                    'total_employees' => 13,
                    'appraised_employees' => 10,
                ],
            ],
        ];

        return Inertia::render('HR/Appraisals/Cycles/Show', [
            'cycle' => $cycle,
            'appraisals' => $appraisals,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Show the form for editing an appraisal cycle
     */
    public function edit($id)
    {
        $cycle = [
            'id' => 1,
            'name' => 'Annual Review 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'open',
            'total_appraisals' => 45,
            'completed_appraisals' => 38,
            'average_score' => 7.4,
            'created_by' => 'HR Manager',
            'created_at' => '2025-01-05 08:30:00',
            'updated_at' => '2025-11-20 14:22:00',
            'criteria' => [
                ['name' => 'Quality of Work', 'weight' => 20],
                ['name' => 'Attendance & Punctuality', 'weight' => 20],
                ['name' => 'Behavior & Conduct', 'weight' => 20],
                ['name' => 'Productivity', 'weight' => 20],
                ['name' => 'Teamwork', 'weight' => 20],
            ],
        ];

        return Inertia::render('HR/Appraisals/Cycles/Edit', [
            'cycle' => $cycle,
        ]);
    }

    /**
     * Update the specified appraisal cycle
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'criteria' => 'required|array|min:3',
            'criteria.*.name' => 'required|string|max:100',
            'criteria.*.weight' => 'required|numeric|min:1|max:100',
        ]);

        // Verify total weight equals 100
        $totalWeight = array_sum(array_column($validated['criteria'], 'weight'));
        if ($totalWeight !== 100) {
            return back()->withErrors(['criteria' => 'Total weight must equal 100%']);
        }

        // In production, update database
        return redirect()
            ->route('hr.appraisals.cycles.index')
            ->with('success', 'Appraisal cycle updated successfully');
    }

    /**
     * Close an appraisal cycle
     */
    public function close(Request $request, $id)
    {
        // In production, update cycle status to 'closed'
        return back()->with('success', 'Appraisal cycle closed successfully');
    }

    /**
     * Show employee assignment form
     */
    public function assignEmployees($id)
    {
        // Mock employees for assignment
        $employees = $this->getMockEmployees();

        return Inertia::render('HR/Appraisals/Cycles/AssignEmployees', [
            'cycleId' => $id,
            'employees' => $employees,
        ]);
    }

    /**
     * Store employee assignments
     */
    public function storeAssignment(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|integer',
            'due_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        // In production, create appraisal records for each employee
        $employeeCount = count($validated['employee_ids']);

        return back()->with('success', "Successfully assigned appraisals to {$employeeCount} employee(s)");
    }

    /**
     * Get mock employees list
     */
    private function getMockEmployees()
    {
        return [
            ['id' => 1, 'employee_number' => 'EMP-2023-001', 'name' => 'Juan dela Cruz', 'department' => 'Engineering'],
            ['id' => 2, 'employee_number' => 'EMP-2023-002', 'name' => 'Maria Santos', 'department' => 'Finance'],
            ['id' => 3, 'employee_number' => 'EMP-2023-003', 'name' => 'Carlos Reyes', 'department' => 'Operations'],
            ['id' => 4, 'employee_number' => 'EMP-2023-004', 'name' => 'Ana Garcia', 'department' => 'Sales'],
            ['id' => 5, 'employee_number' => 'EMP-2023-005', 'name' => 'Miguel Torres', 'department' => 'Engineering'],
            ['id' => 6, 'employee_number' => 'EMP-2023-006', 'name' => 'Linda Rodriguez', 'department' => 'HR'],
            ['id' => 7, 'employee_number' => 'EMP-2023-007', 'name' => 'Ramon Martinez', 'department' => 'Operations'],
            ['id' => 8, 'employee_number' => 'EMP-2023-008', 'name' => 'Sophie Mercado', 'department' => 'Finance'],
            ['id' => 9, 'employee_number' => 'EMP-2023-009', 'name' => 'Daniel Perez', 'department' => 'Sales'],
            ['id' => 10, 'employee_number' => 'EMP-2023-010', 'name' => 'Rebecca Lopez', 'department' => 'Engineering'],
        ];
    }
}
