<?php

namespace App\Http\Controllers\HR\Appraisal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AppraisalController
 *
 * Manages individual appraisals - performance reviews for employees within a cycle.
 * HR Managers enter scores, collect feedback, track status, and manage the appraisal workflow.
 *
 * Workflow:
 * 1. Create appraisal for employee in a cycle (draft status)
 * 2. Enter scores for each criterion
 * 3. Add overall feedback and comments
 * 4. Change status through workflow (draft → in_progress → completed → acknowledged)
 * 5. Employee acknowledges receipt of appraisal
 */
class AppraisalController extends Controller
{
    /**
     * Display a listing of appraisals with filters
     */
    public function index(Request $request)
    {
        $cycleId = $request->input('cycle_id', '');
        $status = $request->input('status', '');
        $departmentId = $request->input('department_id', '');
        $search = $request->input('search', '');

        // Mock appraisals data
        $mockAppraisals = $this->getMockAppraisals();

        // Apply filters
        if ($cycleId) {
            $mockAppraisals = array_filter($mockAppraisals, fn($a) => (string)$a['cycle_id'] === $cycleId);
        }
        if ($status) {
            $mockAppraisals = array_filter($mockAppraisals, fn($a) => $a['status'] === $status);
        }
        if ($departmentId) {
            $mockAppraisals = array_filter($mockAppraisals, fn($a) => (string)$a['department_id'] === $departmentId);
        }
        if ($search) {
            $mockAppraisals = array_filter($mockAppraisals, function ($a) use ($search) {
                return stripos($a['employee_name'], $search) !== false ||
                       stripos($a['employee_number'], $search) !== false;
            });
        }

        // Get cycles and departments for filters
        $cycles = $this->getMockCycles();
        $departments = $this->getMockDepartments();

        return Inertia::render('HR/Appraisals/Index', [
            'appraisals' => array_values($mockAppraisals),
            'cycles' => $cycles,
            'departments' => $departments,
            'filters' => [
                'cycle_id' => $cycleId,
                'status' => $status,
                'department_id' => $departmentId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display the specified appraisal details
     */
    public function show($id)
    {
        // Mock appraisal details
        $appraisal = [
            'id' => 1,
            'employee_id' => 1,
            'employee_name' => 'Juan dela Cruz',
            'employee_number' => 'EMP-2023-001',
            'department_id' => 1,
            'department_name' => 'Engineering',
            'cycle_id' => 1,
            'cycle_name' => 'Annual Review 2025',
            'status' => 'in_progress',
            'status_label' => 'In Progress',
            'status_color' => 'bg-blue-100 text-blue-800',
            'overall_score' => null,
            'feedback' => null,
            'attendance_rate' => 94.5,
            'lateness_count' => 2,
            'violation_count' => 0,
            'created_by' => 'HR Manager',
            'updated_by' => null,
            'created_at' => '2025-01-10 08:00:00',
            'updated_at' => '2025-11-18 14:30:00',
            'scores' => [
                [
                    'id' => 1,
                    'appraisal_id' => 1,
                    'criterion' => 'Quality of Work',
                    'score' => 8,
                    'weight' => 20,
                    'notes' => 'Consistently delivers high-quality work. Attention to detail is excellent.',
                    'created_at' => '2025-11-18 14:00:00',
                    'updated_at' => '2025-11-18 14:00:00',
                ],
                [
                    'id' => 2,
                    'appraisal_id' => 1,
                    'criterion' => 'Attendance & Punctuality',
                    'score' => 9,
                    'weight' => 20,
                    'notes' => 'Excellent attendance record. Always on time.',
                    'created_at' => '2025-11-18 14:00:00',
                    'updated_at' => '2025-11-18 14:00:00',
                ],
                [
                    'id' => 3,
                    'appraisal_id' => 1,
                    'criterion' => 'Behavior & Conduct',
                    'score' => 8.5,
                    'weight' => 20,
                    'notes' => 'Professional demeanor and respectful towards colleagues.',
                    'created_at' => '2025-11-18 14:00:00',
                    'updated_at' => '2025-11-18 14:00:00',
                ],
                [
                    'id' => 4,
                    'appraisal_id' => 1,
                    'criterion' => 'Productivity',
                    'score' => 7.5,
                    'weight' => 20,
                    'notes' => 'Meets deadlines and produces adequate output.',
                    'created_at' => '2025-11-18 14:00:00',
                    'updated_at' => '2025-11-18 14:00:00',
                ],
                [
                    'id' => 5,
                    'appraisal_id' => 1,
                    'criterion' => 'Teamwork',
                    'score' => 8,
                    'weight' => 20,
                    'notes' => 'Collaborates well with team members.',
                    'created_at' => '2025-11-18 14:00:00',
                    'updated_at' => '2025-11-18 14:00:00',
                ],
            ],
        ];

        // Mock employee data
        $employee = [
            'id' => 1,
            'employee_number' => 'EMP-2023-001',
            'first_name' => 'Juan',
            'last_name' => 'dela Cruz',
            'full_name' => 'Juan dela Cruz',
            'department_id' => 1,
            'department_name' => 'Engineering',
            'position_id' => 1,
            'position_name' => 'Software Engineer',
            'email' => 'juan.delacruz@company.com',
            'phone' => '09123456789',
            'date_employed' => '2020-03-15',
            'status' => 'active',
        ];

        // Mock cycle data
        $cycle = [
            'id' => 1,
            'name' => 'Annual Review 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ];

        return Inertia::render('HR/Appraisals/Show', [
            'appraisal' => $appraisal,
            'employee' => $employee,
            'cycle' => $cycle,
        ]);
    }

    /**
     * Store a newly created appraisal
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'cycle_id' => 'required|integer',
        ]);

        // In production, create appraisal record in database
        return redirect()
            ->route('hr.appraisals.index')
            ->with('success', 'Appraisal created successfully');
    }

    /**
     * Update appraisal scores
     */
    public function updateScores(Request $request, $id)
    {
        $validated = $request->validate([
            'scores' => 'required|array|min:1',
            'scores.*.criterion' => 'required|string|max:100',
            'scores.*.score' => 'required|numeric|min:1|max:10',
            'scores.*.notes' => 'nullable|string|max:500',
        ]);

        // In production, update appraisal_scores records
        return back()->with('success', 'Appraisal scores updated successfully');
    }

    /**
     * Update appraisal status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,in_progress,completed,acknowledged',
            'notes' => 'nullable|string|max:500',
        ]);

        // In production, update appraisal status
        return back()->with('success', 'Appraisal status updated successfully');
    }

    /**
     * Submit appraisal feedback
     */
    public function submitFeedback(Request $request, $id)
    {
        $validated = $request->validate([
            'overall_score' => 'required|numeric|min:1|max:10',
            'feedback' => 'required|string|min:10|max:1000',
            'scores' => 'required|array|min:1',
            'scores.*.criterion' => 'required|string',
            'scores.*.score' => 'required|numeric|min:1|max:10',
            'scores.*.notes' => 'nullable|string|max:500',
        ]);

        // In production, save feedback and update appraisal
        return back()->with('success', 'Appraisal feedback submitted successfully');
    }

    /**
     * Get mock appraisals
     */
    private function getMockAppraisals()
    {
        return [
            // Cycle 1 - Annual 2025 (open, 85% complete)
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
                'attendance_rate' => 94.5,
                'lateness_count' => 2,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-01-10 08:00:00',
                'updated_at' => '2025-11-18 14:30:00',
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
                'attendance_rate' => 91.0,
                'lateness_count' => 4,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-01-12 09:30:00',
                'updated_at' => '2025-11-19 10:15:00',
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
                'attendance_rate' => 87.3,
                'lateness_count' => 6,
                'violation_count' => 1,
                'created_by' => 'HR Manager',
                'updated_by' => null,
                'created_at' => '2025-01-15 11:00:00',
                'updated_at' => '2025-11-15 13:45:00',
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
                'attendance_rate' => 88.5,
                'lateness_count' => 5,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => null,
                'created_at' => '2025-01-20 14:20:00',
                'updated_at' => '2025-11-20 09:00:00',
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
                'attendance_rate' => 85.2,
                'lateness_count' => 8,
                'violation_count' => 2,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-01-18 10:15:00',
                'updated_at' => '2025-11-17 16:45:00',
            ],
            // Cycle 2 - Mid-Year 2025 (open, 40% complete)
            [
                'id' => 6,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2023-001',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'cycle_id' => 2,
                'cycle_name' => 'Mid-Year Review 2025',
                'status' => 'completed',
                'status_label' => 'Completed',
                'status_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.9,
                'attendance_rate' => 96.0,
                'lateness_count' => 1,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-06-02 08:30:00',
                'updated_at' => '2025-06-25 11:20:00',
            ],
            [
                'id' => 7,
                'employee_id' => 6,
                'employee_name' => 'Linda Rodriguez',
                'employee_number' => 'EMP-2023-006',
                'department_id' => 5,
                'department_name' => 'HR',
                'cycle_id' => 2,
                'cycle_name' => 'Mid-Year Review 2025',
                'status' => 'draft',
                'status_label' => 'Draft',
                'status_color' => 'bg-gray-100 text-gray-800',
                'overall_score' => null,
                'attendance_rate' => 95.0,
                'lateness_count' => 2,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => null,
                'created_at' => '2025-06-05 09:00:00',
                'updated_at' => '2025-11-20 14:00:00',
            ],
            [
                'id' => 8,
                'employee_id' => 7,
                'employee_name' => 'Ramon Martinez',
                'employee_number' => 'EMP-2023-007',
                'department_id' => 3,
                'department_name' => 'Operations',
                'cycle_id' => 2,
                'cycle_name' => 'Mid-Year Review 2025',
                'status' => 'completed',
                'status_label' => 'Completed',
                'status_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.3,
                'attendance_rate' => 89.0,
                'lateness_count' => 4,
                'violation_count' => 0,
                'created_by' => 'HR Manager',
                'updated_by' => 'HR Manager',
                'created_at' => '2025-06-08 10:45:00',
                'updated_at' => '2025-06-28 15:30:00',
            ],
        ];
    }

    /**
     * Get mock cycles
     */
    private function getMockCycles()
    {
        return [
            ['id' => 1, 'name' => 'Annual Review 2025'],
            ['id' => 2, 'name' => 'Mid-Year Review 2025'],
            ['id' => 3, 'name' => 'Annual Review 2024'],
        ];
    }

    /**
     * Get mock departments
     */
    private function getMockDepartments()
    {
        return [
            ['id' => 1, 'name' => 'Engineering'],
            ['id' => 2, 'name' => 'Finance'],
            ['id' => 3, 'name' => 'Operations'],
            ['id' => 4, 'name' => 'Sales'],
            ['id' => 5, 'name' => 'HR'],
        ];
    }
}
