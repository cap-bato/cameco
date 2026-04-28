<?php

namespace App\Http\Controllers\System\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use LogsSecurityAudits;

    /**
     * Display list of departments with hierarchy
     */
    public function index(): Response
    {
        $departments = Department::with(['parent', 'manager', 'children'])
            ->withCount(['positions', 'employees'])
            ->get();

        // Build hierarchical structure
        $hierarchical = $departments
            ->filter(fn($d) => !$d->parent_id)
            ->map(fn($d) => $this->formatDepartmentTree($d));

        $stats = [
            'total' => $departments->count(),
            'active' => $departments->where('is_active', true)->count(),
            'inactive' => $departments->where('is_active', false)->count(),
            'with_manager' => $departments->whereNotNull('manager_id')->count(),
            'total_budget' => $departments->sum('budget'),
        ];

        return Inertia::render('System/Organization/Departments', [
            'departments' => $departments->map($this->formatDepartment()),
            'hierarchical' => $hierarchical,
            'managers' => User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['manager', 'director', 'admin']);
            })->select('id', 'name', 'email')->get(),
            'stats' => $stats,
        ]);
    }

    /**
     * Store a new department
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:departments'],
            'code' => ['required', 'string', 'unique:departments'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'budget' => ['nullable', 'integer', 'min:0'],
        ]);

        $department = Department::create($validated);

        $this->auditLog(
            'department_created',
            "Created department: {$department->name} ({$department->code})",
            'high',
            'Department Management',
            ['department_id' => $department->id, 'department_name' => $department->name]
        );

        return response()->json($this->formatDepartment()($department), 201);
    }

    /**
     * Display department detail
     */
    public function show(Department $department): Response
    {
        return Inertia::render('System/Organization/DepartmentDetail', [
            'department' => $this->formatDepartment()($department->load(['parent', 'manager', 'children', 'positions'])),
        ]);
    }

    /**
     * Update department
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', Rule::unique('departments')->ignore($department->id)],
            'code' => ['required', 'string', Rule::unique('departments')->ignore($department->id)],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $department->update($validated);

        $this->auditLog(
            'department_updated',
            "Updated department: {$department->name} ({$department->code})",
            'medium',
            'Department Management',
            ['department_id' => $department->id]
        );

        return response()->json($this->formatDepartment()($department));
    }

    /**
     * Delete department
     */
    public function destroy(Department $department)
    {
        // Check if department has sub-departments or positions
        if ($department->children->count() > 0 || $department->positions->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department with sub-departments or positions',
            ], 422);
        }

        $name = $department->name;
        $department->delete();

        $this->auditLog(
            'department_deleted',
            "Deleted department: {$name}",
            'high',
            'Department Management',
            ['department_id' => $department->id]
        );

        return response()->json(['message' => 'Department deleted']);
    }

    /**
     * Seed default Philippine company department structure
     * 
     * Only Super Admin can call this endpoint
     * Prevents duplicates - checks if departments already exist
     * Accepts custom departments array from request
     */
    public function seedDefaults(Request $request)
    {
        // Check authorization - Super Admin only
        Gate::authorize('system.onboarding.initialize');

        // Prevent duplicate seeding
        if (Department::count() > 0) {
            return response()->json([
                'message' => 'Departments already exist. Delete existing departments to seed again.',
            ], 422);
        }

        // Get custom departments from request, or use defaults
        $customDepartments = [];
        if ($request->has('departments')) {
            try {
                $customDepartments = json_decode($request->input('departments'), true) ?? [];
            } catch (\Exception $e) {
                $customDepartments = [];
            }
        }

        // If no custom departments provided, use default PH structure
        if (empty($customDepartments)) {
            $departmentData = [
                // Top-level departments
                ['name' => 'Executive Management', 'code' => 'EXEC', 'description' => 'Executive leadership', 'parent_id' => null, 'level' => 0],
                ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'HR operations and services', 'parent_id' => null, 'level' => 0],
                ['name' => 'Finance & Accounting', 'code' => 'FIN', 'description' => 'Finance and accounting services', 'parent_id' => null, 'level' => 0],
                ['name' => 'Operations', 'code' => 'OPS', 'description' => 'Operations management', 'parent_id' => null, 'level' => 0],
                ['name' => 'Sales & Marketing', 'code' => 'SALES', 'description' => 'Sales and marketing', 'parent_id' => null, 'level' => 0],
                ['name' => 'IT & Technology', 'code' => 'IT', 'description' => 'IT and technology services', 'parent_id' => null, 'level' => 0],
                ['name' => 'Administration', 'code' => 'ADMIN', 'description' => 'Administrative services', 'parent_id' => null, 'level' => 0],
                
                // HR sub-departments
                ['name' => 'HR Operations', 'code' => 'HR-OPS', 'description' => 'HR day-to-day operations', 'parent_code' => 'HR', 'level' => 1],
                ['name' => 'Recruitment & Talent', 'code' => 'HR-REC', 'description' => 'Hiring and talent management', 'parent_code' => 'HR', 'level' => 1],
                
                // Finance sub-departments
                ['name' => 'Accounts Payable', 'code' => 'FIN-AP', 'description' => 'AP management', 'parent_code' => 'FIN', 'level' => 1],
                ['name' => 'Accounts Receivable', 'code' => 'FIN-AR', 'description' => 'AR management', 'parent_code' => 'FIN', 'level' => 1],
                ['name' => 'Payroll', 'code' => 'FIN-PAY', 'description' => 'Payroll processing', 'parent_code' => 'FIN', 'level' => 1],
            ];
        } else {
            // Transform custom departments to proper format
            $departmentData = array_map(function ($dept) {
                return [
                    'name' => $dept['name'] ?? '',
                    'code' => $dept['code'] ?? '',
                    'description' => $dept['description'] ?? null,
                    'level' => $dept['level'] ?? 0,
                    'parent_code' => null,
                ];
            }, $customDepartments);
        }

        try {
            // Create departments with parent-child relationships
            $createdDepts = [];
            foreach ($departmentData as $dept) {
                $parentId = null;
                
                // Resolve parent_code to parent_id
                if (isset($dept['parent_code'])) {
                    $parentDept = Department::where('code', $dept['parent_code'])->first();
                    $parentId = $parentDept?->id;
                }

                $created = Department::create([
                    'name' => $dept['name'],
                    'code' => $dept['code'],
                    'description' => $dept['description'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]);

                $createdDepts[] = $created;
            }

            // Log audit trail
            $this->auditLog(
                'seed_departments',
                'Seeded Philippine company department structure',
                'high',
                'System Onboarding',
                [
                    'total_departments' => count($createdDepts),
                    'department_codes' => array_column($createdDepts, 'code'),
                ]
            );

            // Check if user wants to also seed positions
            $shouldSeedPositions = $request->input('seed_positions') === '1';
            if ($shouldSeedPositions && Position::count() === 0) {
                // Automatically seed positions for the created departments
                $positionController = new PositionController();
                // Create a new request with empty positions (will use defaults)
                $positionRequest = new Request();
                $positionController->seedDefaults($positionRequest);
            }

            // Redirect back with success message for Inertia
            return redirect()->back()->with('message', 'Default departments created successfully' . ($shouldSeedPositions && Position::count() > 0 ? ' and positions seeded!' : ''));

        } catch (\Exception $e) {
            // Log error
            $this->auditLog(
                'seed_departments_failed',
                'Failed to seed departments: ' . $e->getMessage(),
                'high',
                'System Onboarding',
                ['error' => $e->getMessage()]
            );

            // Redirect back with error for Inertia
            return redirect()->back()->withErrors(['error' => 'Failed to seed departments: ' . $e->getMessage()]);
        }
    }

    /**
     * Format department for response
     */
    private function formatDepartment()
    {
        return function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'parent_id' => $department->parent_id,
                'parent_name' => $department->parent?->name,
                'manager_id' => $department->manager_id,
                'manager_name' => $department->manager?->name,
                'manager_email' => $department->manager?->email,
                'budget' => $department->budget,
                'is_active' => $department->is_active,
                'positions_count' => $department->positions_count ?? 0,
                'employee_count' => $department->employees_count ?? 0,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ];
        };
    }

    /**
     * Format department tree for hierarchy view
     */
    private function formatDepartmentTree(Department $department, int $depth = 0): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'manager_name' => $department->manager?->name,
            'is_active' => $department->is_active,
            'positions_count' => $department->positions_count ?? 0,
            'employee_count' => $department->employees_count ?? 0,
            'depth' => $depth,
            'children' => $department->children
                ->map(fn($child) => $this->formatDepartmentTree($child, $depth + 1))
                ->values()
                ->all(),
        ];
    }
}
