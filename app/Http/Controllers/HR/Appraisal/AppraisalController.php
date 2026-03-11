<?php

namespace App\Http\Controllers\HR\Appraisal;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\AppraisalScore;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Build query with relationships
        $query = Appraisal::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'cycle:id,name',
        ]);

        // Apply filters
        if ($cycleId) {
            $query->where('appraisal_cycle_id', $cycleId);
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($departmentId) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }
        
        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%")
                  ->orWhereHas('profile', fn($pq) => $pq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                  );
            });
        }

        // Get appraisals and format for frontend
        $appraisals = $query->latest()->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'employee_name' => ($a->employee?->profile?->first_name ?? '') . ' ' . ($a->employee?->profile?->last_name ?? ''),
                'employee_number' => $a->employee?->employee_number,
                'department_id' => $a->employee?->department_id,
                'department_name' => $a->employee?->department?->name,
                'cycle_id' => $a->appraisal_cycle_id,
                'cycle_name' => $a->cycle?->name,
                'status' => $a->status,
                'status_label' => $a->status_label,
                'status_color' => $a->status_color,
                'overall_score' => $a->overall_score,
                'attendance_rate' => null,  // TODO: pull from timekeeping when integrated
                'lateness_count' => 0,
                'violation_count' => 0,
                'created_at' => $a->created_at?->toDateTimeString(),
                'updated_at' => $a->updated_at?->toDateTimeString(),
            ];
        });

        // Get cycles for filter dropdown
        $cycles = AppraisalCycle::orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

        // Get departments for filter dropdown
        $departments = Department::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->name]);

        return Inertia::render('HR/Appraisals/Index', [
            'appraisals' => $appraisals,
            'cycles' => $cycles,
            'departments' => $departments,
            'filters' => compact('cycleId', 'status', 'departmentId', 'search'),
        ]);
    }

    /**
     * Display the specified appraisal details
     */
    public function show($id)
    {
        // Fetch appraisal with all relationships
        $appraisal = Appraisal::with([
            'employee.profile',
            'employee.department:id,name',
            'employee.position:id,title',
            'cycle:id,name,start_date,end_date',
            'scores.criteria:id,name,weight,max_score',
        ])->findOrFail($id);

        // Format appraisal data for frontend
        $appraisalData = [
            'id' => $appraisal->id,
            'employee_id' => $appraisal->employee_id,
            'employee_name' => ($appraisal->employee?->profile?->first_name ?? '') . ' ' . ($appraisal->employee?->profile?->last_name ?? ''),
            'employee_number' => $appraisal->employee?->employee_number,
            'department_name' => $appraisal->employee?->department?->name,
            'cycle_name' => $appraisal->cycle?->name,
            'status' => $appraisal->status,
            'status_label' => $appraisal->status_label,
            'status_color' => $appraisal->status_color,
            'overall_score' => $appraisal->overall_score,
            'feedback' => $appraisal->feedback,
            'attendance_rate' => null,  // TODO: timekeeping integration
            'lateness_count' => 0,
            'violation_count' => 0,
            'created_at' => $appraisal->created_at?->toDateTimeString(),
            'updated_at' => $appraisal->updated_at?->toDateTimeString(),
            'scores' => $appraisal->scores->map(fn($s) => [
                'id' => $s->id,
                'criterion' => $s->criteria?->name,
                'score' => (float) $s->score,
                'weight' => $s->criteria?->weight,
                'notes' => $s->comments,
            ])->values(),
        ];

        // Format employee data for frontend
        $employeeData = [
            'id' => $appraisal->employee->id,
            'employee_number' => $appraisal->employee->employee_number,
            'first_name' => $appraisal->employee->profile?->first_name,
            'last_name' => $appraisal->employee->profile?->last_name,
            'full_name' => ($appraisal->employee->profile?->first_name ?? '') . ' ' . ($appraisal->employee->profile?->last_name ?? ''),
            'department_name' => $appraisal->employee->department?->name,
            'position_name' => $appraisal->employee->position?->title,
            'date_employed' => $appraisal->employee->date_hired?->format('Y-m-d'),
            'status' => $appraisal->employee->status,
        ];

        // Format cycle data for frontend
        $cycleData = [
            'id' => $appraisal->cycle->id,
            'name' => $appraisal->cycle->name,
            'start_date' => $appraisal->cycle->start_date->format('Y-m-d'),
            'end_date' => $appraisal->cycle->end_date->format('Y-m-d'),
        ];

        return Inertia::render('HR/Appraisals/Show', [
            'appraisal' => $appraisalData,
            'employee' => $employeeData,
            'cycle' => $cycleData,
        ]);
    }

    /**
     * Store a newly created appraisal
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'cycle_id' => 'required|integer|exists:appraisal_cycles,id',
        ]);

        // Check for duplicate appraisal
        $exists = Appraisal::where('appraisal_cycle_id', $validated['cycle_id'])
            ->where('employee_id', $validated['employee_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['employee_id' => 'Appraisal already exists for this employee in the selected cycle.']);
        }

        // Create new appraisal
        Appraisal::create([
            'appraisal_cycle_id' => $validated['cycle_id'],
            'employee_id' => $validated['employee_id'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hr.appraisals.index')
            ->with('success', 'Appraisal created successfully.');
    }

    /**
     * Update appraisal scores
     */
    public function updateScores(Request $request, $id)
    {
        $appraisal = Appraisal::findOrFail($id);

        $validated = $request->validate([
            'scores' => 'required|array|min:1',
            'scores.*.appraisal_criteria_id' => 'required|integer|exists:appraisal_criteria,id',
            'scores.*.score' => 'required|numeric|min:0|max:10',
            'scores.*.comments' => 'nullable|string|max:500',
        ]);

        // Use transaction to ensure atomicity
        DB::transaction(function () use ($appraisal, $validated) {
            // Upsert each score
            foreach ($validated['scores'] as $scoreData) {
                AppraisalScore::updateOrCreate(
                    [
                        'appraisal_id' => $appraisal->id,
                        'appraisal_criteria_id' => $scoreData['appraisal_criteria_id'],
                    ],
                    [
                        'score' => $scoreData['score'],
                        'comments' => $scoreData['comments'] ?? null,
                    ]
                );
            }

            // Recalculate overall score as weighted average
            $scores = $appraisal->scores()->with('criteria:id,weight')->get();
            $totalWeight = $scores->sum('criteria.weight');
            $weightedSum = $scores->sum(fn($s) => $s->score * ($s->criteria?->weight ?? 0));
            $overall = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;

            // Update appraisal with calculated overall score
            $appraisal->update([
                'overall_score' => $overall,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Scores updated successfully.');
    }

    /**
     * Update appraisal status
     */
    public function updateStatus(Request $request, $id)
    {
        $appraisal = Appraisal::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,in_progress,completed,acknowledged',
            'notes' => 'nullable|string|max:500',
        ]);

        // Build the update array
        $updates = [
            'status' => $validated['status'],
            'updated_by' => auth()->id(),
        ];

        // Set timestamps based on status transitions
        if ($validated['status'] === 'completed') {
            $updates['submitted_at'] = now();
        }

        if ($validated['status'] === 'acknowledged') {
            $updates['acknowledged_at'] = now();
        }

        // Update the appraisal
        $appraisal->update($updates);

        return back()->with('success', 'Appraisal status updated.');
    }

    /**
     * Submit appraisal feedback
     */
    public function submitFeedback(Request $request, $id)
    {
        $appraisal = Appraisal::findOrFail($id);

        $validated = $request->validate([
            'overall_score' => 'required|numeric|min:0|max:10',
            'feedback' => 'required|string|min:10|max:1000',
            'scores' => 'required|array|min:1',
            'scores.*.appraisal_criteria_id' => 'required|integer|exists:appraisal_criteria,id',
            'scores.*.score' => 'required|numeric|min:0|max:10',
            'scores.*.notes' => 'nullable|string|max:500',
        ]);

        // Use transaction to ensure atomicity
        DB::transaction(function () use ($appraisal, $validated) {
            // Upsert each score
            foreach ($validated['scores'] as $scoreData) {
                AppraisalScore::updateOrCreate(
                    [
                        'appraisal_id' => $appraisal->id,
                        'appraisal_criteria_id' => $scoreData['appraisal_criteria_id'],
                    ],
                    [
                        'score' => $scoreData['score'],
                        'comments' => $scoreData['notes'] ?? null,
                    ]
                );
            }

            // Update appraisal with overall score, feedback, and status
            $appraisal->update([
                'overall_score' => $validated['overall_score'],
                'feedback' => $validated['feedback'],
                'status' => 'completed',
                'submitted_at' => now(),
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Appraisal feedback submitted successfully.');
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
