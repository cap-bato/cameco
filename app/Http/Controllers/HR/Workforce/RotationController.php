<?php

namespace App\Http\Controllers\HR\Workforce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RotationController extends Controller
{
    /**
     * Display a listing of employee rotations with mock data.
     */
    public function index(Request $request): Response
    {
        // Mock data: 6 rotation patterns (4 active, 2 inactive)
        $rotations = [
            [
                'id' => 1,
                'name' => '4 Days Work / 2 Days Rest - Production Team A',
                'description' => 'Standard 4x2 rotation for production line workers',
                'pattern_type' => '4x2',
                'pattern_json' => [
                    'work_days' => 4,
                    'rest_days' => 2,
                    'pattern' => [1, 1, 1, 1, 0, 0],
                ],
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'start_date' => '2025-01-01',
                'end_date' => null,
                'is_active' => true,
                'assigned_employees_count' => 28,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-01 08:00:00',
                'updated_at' => '2024-12-01 08:00:00',
            ],
            [
                'id' => 2,
                'name' => '6 Days Work / 1 Day Rest - Wire Mill Shift',
                'description' => 'Intensive 6x1 rotation for wire mill operations',
                'pattern_type' => '6x1',
                'pattern_json' => [
                    'work_days' => 6,
                    'rest_days' => 1,
                    'pattern' => [1, 1, 1, 1, 1, 1, 0],
                ],
                'department_id' => 4,
                'department_name' => 'Wire Mill',
                'start_date' => '2025-01-01',
                'end_date' => '2025-06-30',
                'is_active' => true,
                'assigned_employees_count' => 24,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-01 08:00:00',
                'updated_at' => '2024-12-01 08:00:00',
            ],
            [
                'id' => 3,
                'name' => '5 Days Work / 2 Days Rest - QA Team',
                'description' => 'Standard 5x2 rotation for quality assurance team',
                'pattern_type' => '5x2',
                'pattern_json' => [
                    'work_days' => 5,
                    'rest_days' => 2,
                    'pattern' => [1, 1, 1, 1, 1, 0, 0],
                ],
                'department_id' => 5,
                'department_name' => 'Quality Assurance',
                'start_date' => '2025-01-01',
                'end_date' => null,
                'is_active' => true,
                'assigned_employees_count' => 15,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-01 08:00:00',
                'updated_at' => '2024-12-01 08:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Custom 3-2-2 Pattern - Maintenance',
                'description' => 'Custom rotation: 3 work, 2 rest, 2 work, 2 rest',
                'pattern_type' => 'custom',
                'pattern_json' => [
                    'work_days' => 5,
                    'rest_days' => 4,
                    'pattern' => [1, 1, 1, 0, 0, 1, 1, 0, 0],
                ],
                'department_id' => 6,
                'department_name' => 'Maintenance',
                'start_date' => '2025-01-01',
                'end_date' => null,
                'is_active' => true,
                'assigned_employees_count' => 18,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-12-01 08:00:00',
                'updated_at' => '2024-12-01 08:00:00',
            ],
            [
                'id' => 5,
                'name' => 'Old 4x2 Pattern - Inactive',
                'description' => 'Previous rotation pattern, now inactive',
                'pattern_type' => '4x2',
                'pattern_json' => [
                    'work_days' => 4,
                    'rest_days' => 2,
                    'pattern' => [1, 1, 1, 1, 0, 0],
                ],
                'department_id' => 3,
                'department_name' => 'Rolling Mill 3',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'is_active' => false,
                'assigned_employees_count' => 0,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-01-01 08:00:00',
                'updated_at' => '2024-12-31 23:59:59',
            ],
            [
                'id' => 6,
                'name' => 'Legacy Wire Mill Pattern',
                'description' => 'Old rotation pattern for wire mill',
                'pattern_type' => '5x2',
                'pattern_json' => [
                    'work_days' => 5,
                    'rest_days' => 2,
                    'pattern' => [1, 1, 1, 1, 1, 0, 0],
                ],
                'department_id' => 4,
                'department_name' => 'Wire Mill',
                'start_date' => '2024-01-01',
                'end_date' => '2024-06-30',
                'is_active' => false,
                'assigned_employees_count' => 0,
                'created_by' => 1,
                'created_by_name' => 'Admin User',
                'created_at' => '2024-01-01 08:00:00',
                'updated_at' => '2024-06-30 23:59:59',
            ],
        ];

        // Mock summary statistics
        $summary = [
            'total_rotations' => 6,
            'active_patterns' => 4,
            'employees_in_rotation' => 85,
            'coverage_percentage' => 92.5,
        ];

        // Mock departments
        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3', 'code' => 'RM3'],
            ['id' => 4, 'name' => 'Wire Mill', 'code' => 'WM'],
            ['id' => 5, 'name' => 'Quality Assurance', 'code' => 'QA'],
            ['id' => 6, 'name' => 'Maintenance', 'code' => 'MNT'],
        ];

        // Mock pattern templates
        $patternTemplates = [
            [
                'pattern_type' => '4x2',
                'name' => '4 Days Work / 2 Days Rest',
                'pattern' => [
                    'work_days' => 4,
                    'rest_days' => 2,
                    'pattern' => [1, 1, 1, 1, 0, 0],
                ],
            ],
            [
                'pattern_type' => '5x2',
                'name' => '5 Days Work / 2 Days Rest',
                'pattern' => [
                    'work_days' => 5,
                    'rest_days' => 2,
                    'pattern' => [1, 1, 1, 1, 1, 0, 0],
                ],
            ],
            [
                'pattern_type' => '6x1',
                'name' => '6 Days Work / 1 Day Rest',
                'pattern' => [
                    'work_days' => 6,
                    'rest_days' => 1,
                    'pattern' => [1, 1, 1, 1, 1, 1, 0],
                ],
            ],
            [
                'pattern_type' => 'custom',
                'name' => 'Custom Pattern',
                'pattern' => [
                    'work_days' => 0,
                    'rest_days' => 0,
                    'pattern' => [],
                ],
            ],
        ];

        $filters = [
            'search' => $request->input('search', ''),
            'pattern_type' => $request->input('pattern_type'),
            'department_id' => $request->input('department_id'),
            'is_active' => $request->input('is_active'),
        ];

        return Inertia::render('HR/Workforce/Rotations/Index', [
            'rotations' => $rotations,
            'summary' => $summary,
            'departments' => $departments,
            'pattern_templates' => $patternTemplates,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new rotation.
     */
    public function create(): Response
    {
        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3', 'code' => 'RM3'],
            ['id' => 4, 'name' => 'Wire Mill', 'code' => 'WM'],
            ['id' => 5, 'name' => 'Quality Assurance', 'code' => 'QA'],
        ];

        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz', 'department_id' => 3],
            ['id' => 2, 'employee_number' => 'EMP002', 'full_name' => 'Maria Santos', 'department_id' => 3],
        ];

        $patternTemplates = [
            [
                'pattern_type' => '4x2',
                'name' => '4 Days Work / 2 Days Rest',
                'pattern' => ['work_days' => 4, 'rest_days' => 2, 'pattern' => [1, 1, 1, 1, 0, 0]],
            ],
            [
                'pattern_type' => '5x2',
                'name' => '5 Days Work / 2 Days Rest',
                'pattern' => ['work_days' => 5, 'rest_days' => 2, 'pattern' => [1, 1, 1, 1, 1, 0, 0]],
            ],
        ];

        return Inertia::render('HR/Workforce/Rotations/Create', [
            'departments' => $departments,
            'employees' => $employees,
            'pattern_templates' => $patternTemplates,
        ]);
    }

    /**
     * Store a newly created rotation in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'pattern_type' => 'required|string|in:4x2,5x2,6x1,custom',
        ]);

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation pattern created successfully.');
    }

    /**
     * Display the specified rotation.
     */
    public function show(string $id): Response
    {
        $rotation = [
            'id' => 1,
            'name' => '4 Days Work / 2 Days Rest - Production Team A',
            'description' => 'Standard 4x2 rotation for production line workers',
            'pattern_type' => '4x2',
            'pattern_json' => [
                'work_days' => 4,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 0, 0],
            ],
            'department_name' => 'Rolling Mill 3',
            'assigned_employees_count' => 28,
        ];

        return Inertia::render('HR/Workforce/Rotations/Show', [
            'rotation' => $rotation,
        ]);
    }

    /**
     * Show the form for editing the specified rotation.
     */
    public function edit(string $id): Response
    {
        $rotation = [
            'id' => 1,
            'name' => '4 Days Work / 2 Days Rest - Production Team A',
            'description' => 'Standard 4x2 rotation for production line workers',
            'pattern_type' => '4x2',
        ];

        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3'],
        ];

        $employees = [
            ['id' => 1, 'employee_number' => 'EMP001', 'full_name' => 'Juan dela Cruz'],
        ];

        $patternTemplates = [
            ['pattern_type' => '4x2', 'name' => '4 Days Work / 2 Days Rest'],
        ];

        return Inertia::render('HR/Workforce/Rotations/Edit', [
            'rotation' => $rotation,
            'departments' => $departments,
            'employees' => $employees,
            'pattern_templates' => $patternTemplates,
        ]);
    }

    /**
     * Update the specified rotation in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'pattern_type' => 'required|string|in:4x2,5x2,6x1,custom',
        ]);

        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation pattern updated successfully.');
    }

    /**
     * Remove the specified rotation from storage.
     */
    public function destroy(string $id)
    {
        return redirect()->route('hr.workforce.rotations.index')
            ->with('success', 'Rotation pattern deleted successfully.');
    }
}
